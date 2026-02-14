<?php
// Hlavní soubor pluginu E-shop

register_activation_hook(__FILE__, 'shop_plugin_activate');
register_uninstall_hook(__FILE__, 'shop_plugin_uninstall');

add_action('theme_head', 'shop_plugin_enqueue_styles');
add_action('theme_footer_js', 'shop_plugin_enqueue_scripts');
add_action('footer_end', 'shop_plugin_render_floating_cart');

// Shortcodes
add_shortcode('shop', 'shop_plugin_shortcode_router'); // Doporučený: jedna stránka /cs/obchod
add_shortcode('shop_categories', 'shop_plugin_shortcode_categories');
add_shortcode('shop_products', 'shop_plugin_shortcode_products');
add_shortcode('shop_product', 'shop_plugin_shortcode_product');
add_shortcode('shop_cart', 'shop_plugin_shortcode_cart');
add_shortcode('shop_orders', 'shop_plugin_shortcode_orders');

/* =======================
   Helpers
   ======================= */

function shop_plugin_db() {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../functions/database.php';
    $db = db_connect();

    // Bezpečný upgrade databáze (přidání sloupců / default settings).
    // Nesmí shodit celý web, když už je schema v pořádku nebo host omezí info_schema apod.
    try {
        shop_plugin_upgrade_schema($db);
    } catch (Throwable $e) {
        // log jen pokud je k dispozici error_log; web dál běží (přehledy pak jen nebudou mít nové sloupce)
        @error_log('[SHOP] shop_plugin_upgrade_schema failed: ' . $e->getMessage());
    }

    return $db;
}

function shop_setting_get(PDO $db, string $key, $default='') {
    $st = $db->prepare("SELECT setting_value FROM shop_settings WHERE setting_key=? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? $default : $v;
}

/* =======================
   Cena / DPH helpers
   ======================= */

// vat_class: 0 = bez DPH, 1 = základní, 2 = snížená
function shop_vat_enabled(PDO $db): bool {
    return (string)shop_setting_get($db, 'shop_vat_enabled', '0') === '1';
}

function shop_vat_percent(PDO $db, int $vatClass): float {
    if (!shop_vat_enabled($db)) return 0.0;
    if ($vatClass === 1) return (float)shop_setting_get($db, 'shop_vat_rate_standard', '21');
    if ($vatClass === 2) return (float)shop_setting_get($db, 'shop_vat_rate_reduced', '12');
    return 0.0;
}

/**
 * Vykreslí cenu produktu.
 *
 * Pozn.: V pluginu uvažujeme, že cena v DB (products.price) je **bez DPH**.
 * Pokud je DPH zapnuté a produkt má přiřazenou sazbu, vrátíme cenu **vč. DPH**.
 */
function shop_price_html(PDO $db, float $priceNet, int $vatClass = 0): string {
    $priceNet = max(0.0, (float)$priceNet);
    $vatPct = shop_vat_percent($db, $vatClass);

    $netTxt = number_format($priceNet, 2, ',', ' ') . ' Kč';

    if ($vatPct > 0.0) {
        $gross = $priceNet * (1.0 + ($vatPct / 100.0));
        $grossTxt = number_format($gross, 2, ',', ' ') . ' Kč';
        $pctTxt = rtrim(rtrim(number_format($vatPct, 2, ',', ' '), '0'), ',');

        return '<span class="shop-price-gross"><strong>' . $grossTxt . '</strong></span> '
             . '<span class="shop-price-note" style="opacity:.75;font-size:.9em;">(vč. DPH ' . $pctTxt . '%)</span>'
             . '<br><span class="shop-price-net" style="opacity:.85;font-size:.9em;">' . $netTxt . ' bez DPH</span>';
    }

    // DPH vypnuto nebo produkt bez DPH
    return $netTxt . (shop_vat_enabled($db) ? ' <span class="shop-price-note" style="opacity:.75;font-size:.9em;">(bez DPH)</span>' : '');
}


/**
 * Bezpečný upgrade DB schématu.
 * - volá se při každém vytvoření DB spojení (shop_plugin_db)
 * - musí být "idempotentní" (bezpečné spouštět opakovaně)
 */
function shop_plugin_upgrade_schema(PDO $db): void {
    // Bezpečné helpery (fungují i na hostinzích s omezeným access k information_schema)
    $tableExists = function(string $table) use ($db): bool {
        try {
            $st = $db->prepare("SHOW TABLES LIKE ?");
            $st->execute([$table]);
            return ($st->fetchColumn() !== false);
        } catch (Throwable $e) {
            try { $db->query("SELECT 1 FROM `{$table}` LIMIT 1"); return true; } catch (Throwable $e2) { return false; }
        }
    };

    $columnExists = function(string $table, string $column) use ($db): bool {
        try {
            $st = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
            $st->execute([$column]);
            return ($st->fetch(PDO::FETCH_ASSOC) !== false);
        } catch (Throwable $e) {
            return false;
        }
    };

    // 1) shop_settings musí existovat (protože se přes něj čte konfigurace)
    if (!$tableExists('shop_settings')) {
        $db->exec("CREATE TABLE IF NOT EXISTS `shop_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(255) NOT NULL UNIQUE,
            `setting_value` MEDIUMTEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // 2) DPH / defaults (INSERT IGNORE = bezpečné opakovaně)
    $db->exec("INSERT IGNORE INTO shop_settings (setting_key, setting_value) VALUES
        ('shop_vat_enabled','0'),
        ('shop_vat_rate_standard','21'),
        ('shop_vat_rate_reduced','12')
    ;");

    // invoice secret – ať fungují bezpečné odkazy na faktury
    $db->exec("INSERT IGNORE INTO shop_settings (setting_key, setting_value) VALUES ('shop_invoice_secret','');");
    try {
        $invSecret = trim((string)shop_setting_get($db, 'shop_invoice_secret', ''));
        if ($invSecret === '') {
            $newSecret = bin2hex(random_bytes(24));
            $stS2 = $db->prepare("UPDATE shop_settings SET setting_value=? WHERE setting_key='shop_invoice_secret' AND (setting_value IS NULL OR setting_value='')");
            $stS2->execute([$newSecret]);
        }
    } catch (Throwable $e) {
        // ignore
    }

    // 3) products: vat_class (0 = bez DPH / 1 = standard / 2 = reduced)
    if ($tableExists('products') && !$columnExists('products', 'vat_class')) {
        try { $db->exec("ALTER TABLE `products` ADD COLUMN `vat_class` TINYINT(1) NOT NULL DEFAULT 0 AFTER `price`;"); } catch (Throwable $e) {}
    }

    // 4) orders: total_net + total_vat
    if ($tableExists('orders')) {
        if (!$columnExists('orders', 'total_net')) {
            try { $db->exec("ALTER TABLE `orders` ADD COLUMN `total_net` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `total_price`;"); } catch (Throwable $e) {}
        }
        if (!$columnExists('orders', 'total_vat')) {
            try { $db->exec("ALTER TABLE `orders` ADD COLUMN `total_vat` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `total_net`;"); } catch (Throwable $e) {}
        }
    }

    // 5) order_items: uložení DPH na položce
    if ($tableExists('order_items')) {
        if (!$columnExists('order_items', 'vat_percent')) {
            try { $db->exec("ALTER TABLE `order_items` ADD COLUMN `vat_percent` DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER `price`;"); } catch (Throwable $e) {}
        }
        if (!$columnExists('order_items', 'vat_amount')) {
            try { $db->exec("ALTER TABLE `order_items` ADD COLUMN `vat_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `vat_percent`;"); } catch (Throwable $e) {}
        }
    }
}



/* =======================
   E-mail + faktura helpers
   ======================= */

function shop_invoice_token_for_order(PDO $db, array $order): string {
    $secret = trim((string)shop_setting_get($db, 'shop_invoice_secret', ''));
    if ($secret === '') {
        if (defined('APP_SECRET') && APP_SECRET) $secret = (string)APP_SECRET;
        elseif (defined('SECRET_KEY') && SECRET_KEY) $secret = (string)SECRET_KEY;
    }
    if ($secret === '') return '';
    $data = (string)($order['id'] ?? '') . '|' . (string)($order['order_number'] ?? '') . '|' . (string)($order['email'] ?? '');
    return hash_hmac('sha256', $data, $secret);
}

function shop_invoice_url_for_order(PDO $db, array $order, bool $includeToken=true): string {
    $oid = (int)($order['id'] ?? 0);
    if ($oid <= 0) return '';
    $url = '/plugins/shop/api/invoice.php?order_id=' . rawurlencode((string)$oid);
    if ($includeToken) {
        $tok = shop_invoice_token_for_order($db, $order);
        if ($tok !== '') $url .= '&token=' . rawurlencode($tok);
    }
    return $url;
}

function shop_email_render_template(string $tpl, array $vars): string {
    foreach ($vars as $k=>$v) {
        $tpl = str_replace('{{'.$k.'}}', (string)$v, $tpl);
    }
    return $tpl;
}

function shop_email_headers(PDO $db): string {
    $fromEmail = trim((string)shop_setting_get($db, 'shop_email_from', ''));
    $fromName  = trim((string)shop_setting_get($db, 'shop_email_from_name', ''));
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    if ($fromEmail !== '') {
        $from = $fromEmail;
        if ($fromName !== '') $from = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';
        $headers[] = 'From: ' . $from;
        $headers[] = 'Reply-To: ' . $fromEmail;
    }
    return implode("\r\n", $headers);
}


function shop_smtp_read_response($socket, string $expectedCode) {
    $response = '';
    while (substr($response, 3, 1) !== ' ') {
        $response = fgets($socket, 512);
        if ($response === false) return false;
        if (substr($response, 0, 3) !== $expectedCode) return $response;
    }
    return true;
}

function shop_send_smtp_email(string $to, string $from, string $subject, string $rawMessage, array $config) {
    $prefix = '';
    if (($config['encryption'] ?? 'none') === 'ssl') {
        $prefix = 'ssl://';
    }

    $host = (string)($config['host'] ?? '');
    $port = (int)($config['port'] ?? 25);

    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);
    if (!$socket) {
        return "Nepodařilo se připojit k SMTP serveru: $errstr ($errno)";
    }

    $server_response = fgets($socket, 512);
    if (substr((string)$server_response, 0, 3) != '220') {
        fclose($socket);
        return "Chyba SMTP (připojení): $server_response";
    }

    $server_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    fputs($socket, "EHLO $server_host
");
    $ehlo_response = shop_smtp_read_response($socket, '250');
    if ($ehlo_response !== true) { fclose($socket); return "Chyba SMTP (EHLO): $ehlo_response"; }

    if (($config['encryption'] ?? 'none') === 'tls') {
        fputs($socket, "STARTTLS
");
        $starttls_response = fgets($socket, 512);
        if (substr((string)$starttls_response, 0, 3) != '220') { fclose($socket); return "Chyba SMTP (STARTTLS): $starttls_response"; }

        if (!function_exists('stream_socket_enable_crypto')) {
            fclose($socket);
            return "Chyba: Pro TLS šifrování je vyžadováno PHP rozšíření OpenSSL, které není na serveru dostupné.";
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return "Nepodařilo se zapnout TLS šifrování.";
        }

        fputs($socket, "EHLO $server_host
");
        $ehlo_tls_response = shop_smtp_read_response($socket, '250');
        if ($ehlo_tls_response !== true) { fclose($socket); return "Chyba SMTP (EHLO po STARTTLS): $ehlo_tls_response"; }
    }

    $user = (string)($config['user'] ?? '');
    $pass = (string)($config['pass'] ?? '');

    if ($user !== '' && $pass !== '') {
        fputs($socket, "AUTH LOGIN
");
        $server_response = fgets($socket, 512);
        if (substr((string)$server_response, 0, 3) != '334') { fclose($socket); return "Chyba SMTP (AUTH LOGIN): $server_response"; }

        fputs($socket, base64_encode($user) . "
");
        $server_response = fgets($socket, 512);
        if (substr((string)$server_response, 0, 3) != '334') { fclose($socket); return "Chyba SMTP (Uživatel): $server_response"; }

        fputs($socket, base64_encode($pass) . "
");
        $server_response = fgets($socket, 512);
        if (substr((string)$server_response, 0, 3) != '235') { fclose($socket); return "Chyba SMTP (Heslo): $server_response"; }
    }

    // MAIL FROM (použij From adresu; pokud není, fallback na user)
    $mailFrom = $from !== '' ? $from : $user;
    fputs($socket, "MAIL FROM: <{$mailFrom}>
");
    $server_response = fgets($socket, 512);
    if (substr((string)$server_response, 0, 3) != '250') { fclose($socket); return "Chyba SMTP (MAIL FROM): $server_response"; }

    fputs($socket, "RCPT TO: <$to>
");
    $server_response = fgets($socket, 512);
    if (substr((string)$server_response, 0, 3) != '250') { fclose($socket); return "Chyba SMTP (RCPT TO): $server_response"; }

    fputs($socket, "DATA
");
    $server_response = fgets($socket, 512);
    if (substr((string)$server_response, 0, 3) != '354') { fclose($socket); return "Chyba SMTP (DATA): $server_response"; }

    // dot-stuffing
    $rawMessage = str_replace("
", "
", $rawMessage);
    $rawMessage = str_replace("
", "
", $rawMessage);
    $rawMessage = preg_replace("/
\./", "
..", $rawMessage);
    $rawMessage = str_replace("
", "
", $rawMessage);

    fputs($socket, $rawMessage . "
.
");

    $server_response = fgets($socket, 512);
    if (substr((string)$server_response, 0, 3) != '250') { fclose($socket); return "Chyba SMTP (Odeslání obsahu): $server_response"; }

    fputs($socket, "QUIT
");
    fclose($socket);
    return true;
}

function shop_email_build_message(PDO $db, string $to, string $subject, string $html, array $attachments = []): array {
    $smtpUser = trim((string)shop_setting_get($db, 'shop_smtp_user', ''));
    $fromEmail = trim((string)shop_setting_get($db, 'shop_email_from', $smtpUser));
    $fromName  = trim((string)shop_setting_get($db, 'shop_email_from_name', ''));
    if ($fromEmail === '') {
        $server_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $fromEmail = 'noreply@' . $server_host;
    }

    $encSubj = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers = [];
    $headers[] = 'From: ' . ($fromName !== '' ? ('=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>') : $fromEmail);
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: ' . $encSubj;
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'MIME-Version: 1.0';

    if (!$attachments) {
        $headers[] = 'Content-Type: text/html; charset=utf-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $raw = implode("
", $headers) . "

" . $html;
        return [$fromEmail, $encSubj, $raw];
    }

    $boundary = 'bnd_' . bin2hex(random_bytes(12));
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $body  = "--{$boundary}
";
    $body .= "Content-Type: text/html; charset=utf-8
";
    $body .= "Content-Transfer-Encoding: 8bit

";
    $body .= $html . "
";

    foreach ($attachments as $a) {
        $fn = (string)($a['filename'] ?? 'attachment.bin');
        $mime = (string)($a['mime'] ?? 'application/octet-stream');
        $data = (string)($a['data'] ?? '');
        $body .= "--{$boundary}
";
        $body .= "Content-Type: {$mime}; name=\"{$fn}\"
";
        $body .= "Content-Transfer-Encoding: base64
";
        $body .= "Content-Disposition: attachment; filename=\"{$fn}\"

";
        $body .= chunk_split(base64_encode($data)) . "
";
    }

    $body .= "--{$boundary}--
";

    $raw = implode("
", $headers) . "

" . $body;
    return [$fromEmail, $encSubj, $raw];
}

function shop_email_send(PDO $db, string $to, string $subject, string $html, array $attachments = []): bool {
    $to = trim($to);
    if ($to === '') return false;

    $smtpHost = trim((string)shop_setting_get($db, 'shop_smtp_host', ''));
    $smtpPort = (int)shop_setting_get($db, 'shop_smtp_port', '587');
    $smtpUser = trim((string)shop_setting_get($db, 'shop_smtp_user', ''));
    $smtpPass = (string)shop_setting_get($db, 'shop_smtp_pass', '');
    $smtpEnc  = trim((string)shop_setting_get($db, 'shop_smtp_encryption', 'tls')); // none|tls|ssl

    // postavíme raw zprávu (hlavičky + tělo) jednou pro SMTP i pro fallback mail()
    [$fromEmail, $encSubj, $raw] = shop_email_build_message($db, $to, $subject, $html, $attachments);

    if ($smtpHost === '') {
        // fallback přes mail(); zde se Subject předává zvlášť, raw obsahuje i Subject/To -> pro mail() použijeme jednodušší režim
        $headers = shop_email_headers($db);

        if (!$attachments) {
            return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
        }

        // pro mail() v režimu příloh použijeme stejné MIME tělo jako v raw, ale bez prvních hlaviček
        $parts = explode("

", $raw, 2);
        $mimeBody = $parts[1] ?? $html;

        // a hlavičky si složíme z raw hlaviček, ale bez To/Subject/Date (mail() je řeší zvlášť)
        $rawHeaders = explode("
", $parts[0] ?? '');
        $filtered = [];
        foreach ($rawHeaders as $h) {
            if (stripos($h, 'To:') === 0) continue;
            if (stripos($h, 'Subject:') === 0) continue;
            if (stripos($h, 'Date:') === 0) continue;
            $filtered[] = $h;
        }
        $hdr = implode("
", $filtered);

        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $mimeBody, $hdr);
    }

    $config = [
        'host' => $smtpHost,
        'port' => $smtpPort,
        'user' => $smtpUser,
        'pass' => $smtpPass,
        'encryption' => ($smtpEnc === '' ? 'none' : $smtpEnc),
    ];

    $res = shop_send_smtp_email($to, $fromEmail, $encSubj, $raw, $config);
    return ($res === true);
}


function shop_email_items_table(PDO $db, array $order, array $items): string {
    $sum = 0.0;
    $rows = '';
    foreach ($items as $it) {
        $name = htmlspecialchars((string)($it['name'] ?? 'Produkt'), ENT_QUOTES, 'UTF-8');
        $qty = (int)($it['quantity'] ?? 0);
        $price = (float)($it['price'] ?? 0);
        $line = $qty * $price;
        $sum += $line;
        $rows .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #eee;">'.$name.'</td>'
              .  '<td style="padding:6px 8px;text-align:right;border-bottom:1px solid #eee;">'.$qty.'</td>'
              .  '<td style="padding:6px 8px;text-align:right;border-bottom:1px solid #eee;">'.number_format($price,2,',',' ').' Kč</td>'
              .  '<td style="padding:6px 8px;text-align:right;border-bottom:1px solid #eee;">'.number_format($line,2,',',' ').' Kč</td></tr>';
    }
    $ship = (float)($order['shipping_price'] ?? 0);
    $total = (float)($order['total_price'] ?? 0);
    $codFee = 0.0;
    if (($order['payment_method'] ?? '') === 'cod') {
        $codFee = $total - $ship - $sum;
        if ($codFee < 0.01) $codFee = 0.0;
    }

    $summary = '<div style="margin-top:10px;max-width:520px">'
             . '<div style="display:flex;justify-content:space-between;padding:6px 8px;background:#f7f7f7;border:1px solid #eee;border-radius:8px;margin-bottom:6px;"><span>Mezisoučet</span><strong>'.number_format($sum,2,',',' ').' Kč</strong></div>'
             . '<div style="display:flex;justify-content:space-between;padding:6px 8px;background:#f7f7f7;border:1px solid #eee;border-radius:8px;margin-bottom:6px;"><span>Doprava</span><strong>'.number_format($ship,2,',',' ').' Kč</strong></div>';
    if ($codFee > 0) {
        $summary .= '<div style="display:flex;justify-content:space-between;padding:6px 8px;background:#f7f7f7;border:1px solid #eee;border-radius:8px;margin-bottom:6px;"><span>Dobírka</span><strong>'.number_format($codFee,2,',',' ').' Kč</strong></div>';
    }
    $summary .= '<div style="display:flex;justify-content:space-between;padding:8px 10px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;"><span>Celkem</span><strong>'.number_format($total,2,',',' ').' Kč</strong></div>'
              . '</div>';

    $table = '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;max-width:720px;">'
           . '<thead><tr>'
           . '<th align="left" style="padding:6px 8px;border-bottom:2px solid #ddd;">Zboží</th>'
           . '<th align="right" style="padding:6px 8px;border-bottom:2px solid #ddd;">Množství</th>'
           . '<th align="right" style="padding:6px 8px;border-bottom:2px solid #ddd;">Cena/ks</th>'
           . '<th align="right" style="padding:6px 8px;border-bottom:2px solid #ddd;">Celkem</th>'
           . '</tr></thead><tbody>'.$rows.'</tbody></table>';

    $invUrl = shop_invoice_url_for_order($db, $order, true);
    $invLink = ($invUrl !== '') ? '<p style="margin-top:12px;"><a href="'.htmlspecialchars($invUrl,ENT_QUOTES,'UTF-8').'">Stáhnout fakturu (PDF)</a></p>' : '';

    return $table . $summary . $invLink;
}

function shop_email_load_order(PDO $db, int $orderId): array {
    $st = $db->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
    $st->execute([$orderId]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) return [null, []];

    $stI = $db->prepare("SELECT oi.quantity, oi.price, p.name
                         FROM order_items oi
                         LEFT JOIN products p ON p.id = oi.product_id
                         WHERE oi.order_id=? ORDER BY oi.id ASC");
    $stI->execute([$orderId]);
    $items = $stI->fetchAll(PDO::FETCH_ASSOC);
    return [$o, $items];
}

function shop_email_notify_order_created(PDO $db, int $orderId): void {
    [$o, $items] = shop_email_load_order($db, $orderId);
    if (!$o) return;

    $statusLabel = shop_order_status_label((string)($o['status'] ?? 'new'));
    $payLabel = shop_payment_method_label((string)($o['payment_method'] ?? ''));

    $vars = [
        'order_number' => (string)($o['order_number'] ?? ''),
        'status' => (string)$statusLabel,
        'payment' => (string)$payLabel,
        'total' => number_format((float)($o['total_price'] ?? 0), 2, ',', ' ') . ' Kč',
        'items' => shop_email_items_table($db, $o, $items),
    ];

    // zákazník
    $to = trim((string)($o['email'] ?? ''));
    $subjTpl = (string)shop_setting_get($db, 'shop_email_subject_new_customer', 'Potvrzení objednávky {{order_number}}');
    $tpl = (string)shop_setting_get($db, 'shop_email_tpl_new_customer', '');
    if ($to !== '' && $tpl !== '') {
        shop_email_send($db, $to, shop_email_render_template($subjTpl, $vars), shop_email_render_template($tpl, $vars));
    }

    // admin
    $adminTo = trim((string)shop_setting_get($db, 'shop_admin_email', ''));
    $subjTplA = (string)shop_setting_get($db, 'shop_email_subject_new_admin', 'Nová objednávka {{order_number}}');
    $tplA = (string)shop_setting_get($db, 'shop_email_tpl_new_admin', '');
    if ($adminTo !== '' && $tplA !== '') {
        shop_email_send($db, $adminTo, shop_email_render_template($subjTplA, $vars), shop_email_render_template($tplA, $vars));
    }
}

function shop_email_notify_status_changed(PDO $db, int $orderId, string $oldStatus, string $newStatus): void {
    [$o, $items] = shop_email_load_order($db, $orderId);
    if (!$o) return;

    $vars = [
        'order_number' => (string)($o['order_number'] ?? ''),
        'status' => shop_order_status_label($newStatus),
        'status_old' => shop_order_status_label($oldStatus),
        'total' => number_format((float)($o['total_price'] ?? 0), 2, ',', ' ') . ' Kč',
        'items' => shop_email_items_table($db, $o, $items),
    ];

    // zákazník
    $to = trim((string)($o['email'] ?? ''));
    $subjTpl = (string)shop_setting_get($db, 'shop_email_subject_status_customer', 'Změna stavu objednávky {{order_number}}');
    $tpl = (string)shop_setting_get($db, 'shop_email_tpl_status_customer', '');
    if ($to !== '' && $tpl !== '') {
        shop_email_send($db, $to, shop_email_render_template($subjTpl, $vars), shop_email_render_template($tpl, $vars));
    }

    // admin
    $adminTo = trim((string)shop_setting_get($db, 'shop_admin_email', ''));
    $subjTplA = (string)shop_setting_get($db, 'shop_email_subject_status_admin', 'Změna stavu {{order_number}}');
    $tplA = (string)shop_setting_get($db, 'shop_email_tpl_status_admin', '');
    if ($adminTo !== '' && $tplA !== '') {
        shop_email_send($db, $adminTo, shop_email_render_template($subjTplA, $vars), shop_email_render_template($tplA, $vars));
    }
}


function shop_plugin_current_path(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return $path ?: '/';
}

function shop_plugin_detect_base_path(string $path): string {
    $path = rtrim($path, '/');
    if ($path === '') return '/';

    // Pokud už jsme na podstránce shopu, uřízneme známé suffixy
    $markers = ['kategorie', 'produkt', 'kosik', 'pokladna', 'objednavky'];
    foreach ($markers as $m) {
        $pos = strpos($path, '/' . $m);
        if ($pos !== false) {
            $base = substr($path, 0, $pos);
            return $base === '' ? '/' : $base;
        }
    }
    return $path;
}

function shop_plugin_base_path(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    try {
        $db = shop_plugin_db();
        $set = trim((string)shop_setting_get($db, 'shop_base_path', ''));
    } catch (Exception $e) {
        $set = '';
    }

    if ($set !== '') {
        $cached = '/' . ltrim(rtrim($set, '/'), '/');
        return $cached;
    }

    $cached = shop_plugin_detect_base_path(shop_plugin_current_path());
    return $cached;
}

function shop_plugin_url(string $route = '', array $query = []): string {
    // PHPRS friendly: everything stays on one shop page (base path), switching via ?shop=
    $base = rtrim(shop_plugin_base_path(), '/');
    if ($base === '') $base = '/';

    $route = trim($route, '/');
    $q = $query;

    if ($route !== '') {
        $parts = array_values(array_filter(explode('/', $route), fn($s) => $s !== ''));
        $view = $parts[0] ?? '';
        if ($view !== '') {
            $q = array_merge(['shop' => $view], $q);

            // Support legacy route patterns: kategorie/<slug>, produkt/<slug>
            if (($view === 'kategorie' || $view === 'produkt') && !empty($parts[1]) && empty($q['slug'])) {
                $q['slug'] = $parts[1];
            }
        }
    }

    $url = $base;
    if (!empty($q)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($q);
    }
    return $url;
}


function shop_plugin_route_segments(): array {
    $base = rtrim(shop_plugin_base_path(), '/');
    $path = rtrim(shop_plugin_current_path(), '/');

    if ($base !== '' && $base !== '/' && strpos($path, $base) === 0) {
        $rest = substr($path, strlen($base));
    } else {
        $rest = '';
    }

    $rest = trim($rest, '/');
    if ($rest === '') return [];
    return array_values(array_filter(explode('/', $rest), fn($s) => $s !== ''));
}

/* =======================
   Aktivace / uninstall
   ======================= */

function shop_plugin_activate() {
    $db = shop_plugin_db();

    $db->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `parent_id` INT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`slug`),
        INDEX (`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL,
        `description` MEDIUMTEXT NULL,
        `additional_info` MEDIUMTEXT NULL,
        `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `vat_class` TINYINT(1) NOT NULL DEFAULT 0,
        `stock` INT NOT NULL DEFAULT 0,
        `image` VARCHAR(512) NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL,
        INDEX (`category_id`),
        INDEX (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `product_images` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` INT NOT NULL,
        `image_path` VARCHAR(512) NOT NULL,
        `sort` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `shipping_methods` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `description` TEXT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `payment_accounts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `account_name` VARCHAR(255) NOT NULL,
        `account_number` VARCHAR(64) NOT NULL,
        `bank_code` VARCHAR(16) NOT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `order_number` VARCHAR(64) NOT NULL,
        `fio_account` VARCHAR(128) NULL,
        `fio_variable_symbol` VARCHAR(32) NULL,
        `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_net` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_vat` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `email` VARCHAR(255) NULL,
        `status` VARCHAR(32) NOT NULL DEFAULT 'new',
        `payment_method` VARCHAR(32) NOT NULL DEFAULT 'fio_qr',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `paid_at` DATETIME NULL DEFAULT NULL,
        `name` VARCHAR(255) NULL,
        `telephone` VARCHAR(64) NULL,
        `adress1` VARCHAR(255) NULL,
        `adress2` VARCHAR(255) NULL,
        `adress3` VARCHAR(255) NULL,
        `shipping_id` INT NULL,
        `shipping_name` VARCHAR(255) NULL,
        `shipping_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
        INDEX (`order_number`),
        INDEX (`fio_variable_symbol`),
        INDEX (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `order_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `quantity` INT NOT NULL DEFAULT 1,
        `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `vat_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
        `vat_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        INDEX (`order_id`),
        INDEX (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `shop_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(255) NOT NULL UNIQUE,
        `setting_value` MEDIUMTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Default settings
    $db->exec("INSERT IGNORE INTO shop_settings (setting_key, setting_value) VALUES
        ('fio_token',''),
        ('fio_days_back','30'),
        ('shop_currency','CZK'),
        ('shop_vat_enabled','0'),
        ('shop_vat_rate_standard','21'),
        ('shop_vat_rate_reduced','12'),
        ('shop_cod_fee','0'),
        ('shop_order_prefix','EV'),
        ('shop_base_path',''),
        ('default_payment_account_id','0'),
        ('shop_admin_email',''),
        ('shop_email_from',''),
        ('shop_email_from_name',''),
        ('shop_smtp_host',''),
        ('shop_smtp_port','587'),
        ('shop_smtp_user',''),
        ('shop_smtp_pass',''),
        ('shop_smtp_encryption','tls'),
        ('shop_email_subject_new_customer','Potvrzení objednávky {{order_number}}'),
        ('shop_email_tpl_new_customer','<p>Děkujeme za objednávku <strong>{{order_number}}</strong>.</p>{{items}}<p>Celkem: <strong>{{total}}</strong></p>'),
        ('shop_email_subject_status_customer','Změna stavu objednávky {{order_number}}'),
        ('shop_email_tpl_status_customer','<p>Objednávka <strong>{{order_number}}</strong> změnila stav na <strong>{{status}}</strong>.</p>{{items}}<p>Celkem: <strong>{{total}}</strong></p>'),
        ('shop_email_subject_new_admin','Nová objednávka {{order_number}}'),
        ('shop_email_tpl_new_admin','<p>Nová objednávka <strong>{{order_number}}</strong>.</p>{{items}}<p>Celkem: <strong>{{total}}</strong></p>'),
        ('shop_email_subject_status_admin','Změna stavu {{order_number}}'),
        ('shop_email_tpl_status_admin','<p>Objednávka <strong>{{order_number}}</strong> změnila stav na <strong>{{status}}</strong>.</p>'),
        ('shop_invoice_secret',''),
        ('invoice_seller_name',''),
        ('invoice_seller_addr1',''),
        ('invoice_seller_addr2',''),
        ('invoice_seller_addr3',''),
        ('invoice_seller_ico',''),
        ('invoice_seller_dic',''),
        ('invoice_seller_bank',''),
        ('invoice_note','')
    ;");

// Invoice secret (pro token v odkazu na fakturu)
$invSecret = trim((string)shop_setting_get($db, 'shop_invoice_secret', ''));
if ($invSecret === '') {
    $newSecret = bin2hex(random_bytes(24));
    $stS = $db->prepare("INSERT IGNORE INTO shop_settings (setting_key, setting_value) VALUES ('shop_invoice_secret', ?)");
    $stS->execute([$newSecret]);
    $stS2 = $db->prepare("UPDATE shop_settings SET setting_value=? WHERE setting_key='shop_invoice_secret' AND (setting_value IS NULL OR setting_value='')");
    $stS2->execute([$newSecret]);
}


    // Defaultní dopravy + účet
    $cnt = (int)$db->query("SELECT COUNT(*) FROM shipping_methods")->fetchColumn();
    if ($cnt === 0) {
        $stmt = $db->prepare("INSERT INTO shipping_methods (name, price, description, active) VALUES (?,?,?,1)");
        $stmt->execute(['Osobní odběr', 0, 'Osobní vyzvednutí']);
        $stmt->execute(['Zásilkovna', 89, 'Doručení přes Zásilkovnu']);
    }

    $cnt = (int)$db->query("SELECT COUNT(*) FROM payment_accounts")->fetchColumn();
    if ($cnt === 0) {
        $stmt = $db->prepare("INSERT INTO payment_accounts (account_name, account_number, bank_code, active) VALUES (?,?,?,1)");
        $stmt->execute(['Hlavní účet', '123456789', '2010']);
    }
}

function shop_plugin_uninstall() {
    $db = shop_plugin_db();
    $db->exec("DROP TABLE IF EXISTS order_items;");
    $db->exec("DROP TABLE IF EXISTS orders;");
    $db->exec("DROP TABLE IF EXISTS product_images;");
    $db->exec("DROP TABLE IF EXISTS products;");
    $db->exec("DROP TABLE IF EXISTS categories;");
    $db->exec("DROP TABLE IF EXISTS shipping_methods;");
    $db->exec("DROP TABLE IF EXISTS payment_accounts;");
    $db->exec("DROP TABLE IF EXISTS shop_settings;");
}

/* =======================
   Assets
   ======================= */

function shop_plugin_enqueue_styles() {
    $css_path = __DIR__ . '/assets/css/shop.css';
    if (file_exists($css_path)) {
        $url = '/plugins/shop/assets/css/shop.css?v=' . filemtime($css_path);
        echo '<link rel="stylesheet" href="' . $url . '">';
    }
}

function shop_plugin_enqueue_scripts() {
    // QR JS (CDN) – fallback: zobrazí se jen text QR payload.
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>';
    $js_path = __DIR__ . '/assets/js/shop.js';
    if (file_exists($js_path)) {
        $url = '/plugins/shop/assets/js/shop.js?v=' . filemtime($js_path);
        $base = htmlspecialchars(shop_plugin_base_path(), ENT_QUOTES, 'UTF-8');
        echo '<script>window.SHOP_BASE_PATH=' . json_encode($base) . ';</script>';
        echo '<script src="' . $url . '"></script>';
    }
}

function shop_plugin_render_floating_cart() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $cartUrl = htmlspecialchars(shop_plugin_url('kosik'), ENT_QUOTES, 'UTF-8');

    echo '<div id="shop-floating-cart" class="shop-floating-cart" aria-live="polite" style="display:none;">'
       . '  <button type="button" class="shop-floating-cart-btn" id="shop-cart-open">'
       . '    <span class="shop-cart-icon">🛒</span> '
       . '    <span id="shop-cart-count">0</span>'
       . '  </button>'
       . '</div>'
       . '<div id="shop-cart-drawer" class="shop-cart-drawer" style="display:none;">'
       . '  <div class="shop-cart-drawer__header">'
       . '    <strong>Košík</strong>'
       . '    <button type="button" id="shop-cart-close" class="shop-cart-close">×</button>'
       . '  </div>'
       . '  <div id="shop-cart-drawer-body" class="shop-cart-drawer__body"></div>'
       . '  <div class="shop-cart-drawer__footer">'
       . '    <a class="shop-cart-go" href="' . $cartUrl . '">Přejít do košíku</a>'
       . '  </div>'
       . '</div>';
}

/* =======================
   Router shortcode [shop]
   ======================= */

function shop_plugin_shortcode_router($atts = []) {
    // Preferred in PHPRS: /cs/obchod?shop=... (no subpaths)
    $view = $_GET['shop'] ?? '';

    if ($view === '' || $view === 'home' || $view === 'obchod') {
        return shop_plugin_shortcode_categories() . shop_plugin_shortcode_products();
    }

    if ($view === 'kategorie') {
        // slug can be provided as ?slug=... or legacy ?category=...
        if (!empty($_GET['slug']) && empty($_GET['category'])) $_GET['category'] = $_GET['slug'];
        return shop_plugin_shortcode_categories() . shop_plugin_shortcode_products();
    }

    if ($view === 'produkt') {
        // slug can be provided as ?slug=...
        return shop_plugin_shortcode_product();
    }

    if ($view === 'kosik' || $view === 'pokladna') {
        return shop_plugin_shortcode_cart();
    }

    if ($view === 'objednavky') {
        return shop_plugin_shortcode_orders();
    }

    // Backward compatible: if someone uses subpaths, keep old behavior
    $segments = shop_plugin_route_segments();
    $first = $segments[0] ?? '';

    if ($first === '' || $first === 'obchod') {
        return shop_plugin_shortcode_categories() . shop_plugin_shortcode_products();
    }
    if ($first === 'kategorie') {
        if (!empty($segments[1])) $_GET['category'] = $segments[1];
        return shop_plugin_shortcode_categories() . shop_plugin_shortcode_products();
    }
    if ($first === 'produkt') {
        if (!empty($segments[1])) $_GET['slug'] = $segments[1];
        return shop_plugin_shortcode_product();
    }
    if ($first === 'kosik' || $first === 'pokladna') {
        return shop_plugin_shortcode_cart();
    }
    if ($first === 'objednavky') {
        return shop_plugin_shortcode_orders();
    }

    return shop_plugin_shortcode_categories() . shop_plugin_shortcode_products();
}


/* =======================
   Shortcodes
   ======================= */

function shop_plugin_shortcode_categories($atts = []) {
    try {
        $db = shop_plugin_db();
    } catch (Throwable $e) {
        @error_log('[SHOP] categories load failed: ' . $e->getMessage());
        return '<div class="shop-empty">Nepodařilo se načíst kategorie (chyba databáze). Zkontroluj prosím log serveru.</div>';
    }

    $cats = $db->query("SELECT * FROM categories WHERE active=1 ORDER BY parent_id ASC, name ASC")
               ->fetchAll(PDO::FETCH_ASSOC);
    if (!$cats) return '<div class="shop-empty">Žádné kategorie.</div>';

    $html = '<div class="shop-categories">';
    foreach ($cats as $c) {
        $url = shop_plugin_url('kategorie/' . rawurlencode($c['slug']));
        $html .= '<a class="shop-category" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
              .  htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8')
              . '</a>';
    }
    $html .= '</div>';
    return $html;
}

function shop_plugin_shortcode_products($atts = []) {
    try {
        $db = shop_plugin_db();
    } catch (Throwable $e) {
        @error_log('[SHOP] products list load failed: ' . $e->getMessage());
        return '<div class="shop-empty">Nepodařilo se načíst produkty (chyba databáze). Zkontroluj prosím log serveru.</div>';
    }

    $categorySlug = $_GET['category'] ?? ($atts['category'] ?? '');
    $q = trim((string)($_GET['q'] ?? ''));
    $sort = (string)($_GET['sort'] ?? '');
    $dir  = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

    $where = "p.active=1";
    $params = [];

    if (!empty($categorySlug)) {
        $where .= " AND c.slug = ?";
        $params[] = $categorySlug;
    }

    if ($q !== '') {
        // hledání v názvu, slug, popisu
        $where .= " AND (p.name LIKE ? OR p.slug LIKE ? OR p.description LIKE ? OR p.additional_info LIKE ?)";
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }

    // whitelist řazení (frontend)
    $orderBy = "p.id DESC";
    if ($sort === 'price') {
        $orderBy = "p.price " . strtoupper($dir);
    } elseif ($sort === 'name') {
        $orderBy = "p.name " . strtoupper($dir);
    } elseif ($sort === 'newest') {
        $orderBy = "p.id DESC";
    }

    $stmt = $db->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug
                          FROM products p
                          LEFT JOIN categories c ON c.id=p.category_id
                          WHERE $where
                          ORDER BY $orderBy");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- ovládací panel (vyhledávání + řazení) ----
    $html = '<div class="shop-toolbar" style="margin:10px 0 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';
    $action = htmlspecialchars(shop_plugin_base_path(), ENT_QUOTES, 'UTF-8');

    // zachovej kontext routeru
    $shopView = (string)($_GET['shop'] ?? '');
    if ($shopView !== '') {
        $html .= '<input type="hidden" name="shop" value="'.htmlspecialchars($shopView, ENT_QUOTES, 'UTF-8').'">';
    }
    if ($categorySlug !== '') {
        // v routeru používáme "category" pro filtr kategorií
        $html .= '<form method="get" action="'.$action.'" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">'
              .  ($shopView !== '' ? '<input type="hidden" name="shop" value="'.htmlspecialchars($shopView, ENT_QUOTES, 'UTF-8').'">' : '')
              .  '<input type="hidden" name="category" value="'.htmlspecialchars($categorySlug, ENT_QUOTES, 'UTF-8').'">';
    } else {
        $html .= '<form method="get" action="'.$action.'" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">'
              .  ($shopView !== '' ? '<input type="hidden" name="shop" value="'.htmlspecialchars($shopView, ENT_QUOTES, 'UTF-8').'">' : '');
    }

    $html .= '<input type="text" name="q" value="'.htmlspecialchars($q, ENT_QUOTES, 'UTF-8').'" placeholder="Hledat produkt…" style="padding:8px 10px;border:1px solid #ddd;border-radius:10px;min-width:220px;">';

    $html .= '<select name="sort" style="padding:8px 10px;border:1px solid #ddd;border-radius:10px;">'
          .  '<option value="newest"'.($sort===''||$sort==='newest'?' selected':'').'>Nejnovější</option>'
          .  '<option value="price"'.($sort==='price'?' selected':'').'>Cena</option>'
          .  '<option value="name"'.($sort==='name'?' selected':'').'>Název</option>'
          .  '</select>';

    $html .= '<select name="dir" style="padding:8px 10px;border:1px solid #ddd;border-radius:10px;">'
          .  '<option value="asc"'.($dir==='asc'?' selected':'').'>Vzestupně</option>'
          .  '<option value="desc"'.($dir==='desc'?' selected':'').'>Sestupně</option>'
          .  '</select>';

    $html .= '<button class="shop-btn" type="submit" style="white-space:nowrap;">Použít</button>';
    $html .= '</form></div>';

    if (!$rows) return $html . '<div class="shop-empty">Žádné produkty.</div>';

    $html .= '<div class="shop-products">';
    foreach ($rows as $p) {
        $img = $p['image'] ? htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8') : '';
        $url = shop_plugin_url('produkt/' . rawurlencode($p['slug']));
        $html .= '<div class="shop-product-card">'
              .  ($img ? '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'"><img class="shop-product-img" src="'. $img .'" alt=""></a>' : '')
              .  '<div class="shop-product-body">'
              .  '<a class="shop-product-title" href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8').'</a>'
              .  '<div class="shop-product-price">'.shop_price_html($db, (float)$p['price'], (int)($p['vat_class'] ?? 0)).'</div>'
              .  '<button class="shop-btn" data-shop-add="'.(int)$p['id'].'">Do košíku</button>'
              .  '</div>'
              . '</div>';
    }
    $html .= '</div>';
    return $html;
}


function shop_plugin_shortcode_product($atts = []) {
    try {
        $db = shop_plugin_db();
    } catch (Throwable $e) {
        @error_log('[SHOP] product detail load failed: ' . $e->getMessage());
        return '<div class="shop-empty">Nepodařilo se načíst produkt (chyba databáze). Zkontroluj prosím log serveru.</div>';
    }

    $slug = $_GET['slug'] ?? ($atts['slug'] ?? '');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($atts['id']) ? (int)$atts['id'] : 0);

    if (!$slug && !$id) return '<div class="shop-empty">Produkt nenalezen.</div>';

    if ($slug) {
        $stmt = $db->prepare("SELECT * FROM products WHERE slug=? AND active=1 LIMIT 1");
        $stmt->execute([$slug]);
    } else {
        $stmt = $db->prepare("SELECT * FROM products WHERE id=? AND active=1 LIMIT 1");
        $stmt->execute([$id]);
    }
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) return '<div class="shop-empty">Produkt nenalezen.</div>';

    $imgsStmt = $db->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort ASC, id ASC");
    $imgsStmt->execute([(int)$p['id']]);
    $imgs = $imgsStmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '<div class="shop-product-detail">';
    if (!empty($p['image'])) {
        $html .= '<img class="shop-product-detail__main" src="'.htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8').'" alt="">';
    }
    if ($imgs) {
        $html .= '<div class="shop-gallery">';
        foreach ($imgs as $im) {
            $html .= '<img class="shop-gallery__img" src="'.htmlspecialchars($im['image_path'], ENT_QUOTES, 'UTF-8').'" alt="">';
        }
        $html .= '</div>';
    }
    $html .= '<h2>'.htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8').'</h2>';
    $html .= '<div class="shop-product-price">'.shop_price_html($db, (float)$p['price'], (int)($p['vat_class'] ?? 0)).'</div>';
    $html .= '<button class="shop-btn" data-shop-add="'.(int)$p['id'].'">Do košíku</button>';
    if (!empty($p['description'])) $html .= '<div class="shop-desc">'.$p['description'].'</div>';
    if (!empty($p['additional_info'])) $html .= '<div class="shop-additional">'.$p['additional_info'].'</div>';
    $html .= '</div>';

    return $html;
}

function shop_plugin_shortcode_cart($atts = []) {
    // Kompletní checkout se vykreslí přes JS (načte košík + dopravy + účty)
    return '<div class="shop-cart-page">'
         . '  <h2>Košík</h2>'
         . '  <div id="shop-cart-page-body">Načítám…</div>'
         . '</div>';
}


/* =======================
   Orders helpers
   ======================= */

function shop_order_statuses(): array {
    return [
        'new' => 'Nová',
        'pending_payment' => 'Čeká na platbu',
        'paid' => 'Zaplaceno',
        'processing' => 'Příprava objednávky',
        'shipped' => 'Odesláno',
        'delivered' => 'Doručeno',
        'canceled' => 'Zrušeno',
    ];
}

function shop_order_status_label(string $status): string {
    $map = shop_order_statuses();
    return $map[$status] ?? $status;
}

function shop_payment_method_label(string $method): string {
    $map = [
        'fio_qr' => 'Platba QR (bankovní převod)',
        'bank_transfer' => 'Bankovní převod',
        'cash' => 'Hotově',
        'cod' => 'Dobírka',
        'card' => 'Karta',
    ];
    return $map[$method] ?? $method;
}

function shop_qr_payload_for_order(array $order): string {
    // Formát SPD (česká QR platba)
    $acc = trim((string)($order['fio_account'] ?? ''));
    $vs  = preg_replace('~\D+~', '', (string)($order['fio_variable_symbol'] ?? ''));
    $amount = number_format((float)($order['total_price'] ?? 0), 2, '.', '');
    $msg = 'Objednávka ' . (string)($order['order_number'] ?? '');
    $msg = str_replace(['*',"\n","\r"], [' ',' ',' '], $msg);

    if ($acc === '') return '';

    return sprintf('SPD*1.0*ACC:%s*AM:%s*CC:CZK*MSG:%s%s',
        $acc,
        $amount,
        $msg,
        ($vs !== '' ? '*X-VS:' . $vs : '')
    );
}

function shop_qr_img_data_uri(string $payload, int $size=6, int $margin=2): string {
    if ($payload === '') return '';
    $lib = __DIR__ . '/assets/phpqrcode/qrlib.php';
    if (is_file($lib)) require_once $lib;

    if (!class_exists('QRcode')) return '';

    // POZOR: QRcode::png() při $outfile=false posílá header('Content-Type: image/png'),
    // což by přepsalo hlavičky celé stránky (pak se místo HTML zobrazí jen PNG).
    // Proto generujeme do dočasného souboru a načteme binárku zpět.
    $tmp = tempnam(sys_get_temp_dir(), 'qr_');
    if ($tmp === false) return '';

    try {
        QRcode::png($payload, $tmp, QR_ECLEVEL_M, $size, $margin);
        $png = @file_get_contents($tmp);
    } finally {
        @unlink($tmp);
    }

    if (!$png) return '';
    return 'data:image/png;base64,' . base64_encode($png);
}


function shop_plugin_shortcode_orders($atts = []) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = $_SESSION['client_user_id'] ?? null;
    if (!$userId) {
        return '<div class="shop-empty">Pro zobrazení objednávek se přihlas (plugin Uživatelé – shortcode [prihlaseni]).</div>';
    }

    $db = shop_plugin_db();

    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC");
    $stmt->execute([(int)$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$orders) return '<div class="shop-empty">Zatím nemáš žádné objednávky.</div>';

    // Načteme všechny položky pro zobrazené objednávky v jedné dávce
    $ids = array_map(fn($o)=>(int)$o['id'], $orders);
    $place = implode(',', array_fill(0, count($ids), '?'));

    $itemsByOrder = [];
    if ($place) {
        $sql = "SELECT oi.order_id, oi.quantity, oi.price, oi.vat_percent, oi.vat_amount, p.name, p.slug
                FROM order_items oi
                LEFT JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id IN ($place)
                ORDER BY oi.order_id DESC, oi.id ASC";
        $st = $db->prepare($sql);
        $st->execute($ids);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $oid = (int)$row['order_id'];
            if (!isset($itemsByOrder[$oid])) $itemsByOrder[$oid] = [];
            $itemsByOrder[$oid][] = $row;
        }
    }

    $html = '<div class="shop-orders">';
    $html .= '<h2>Moje objednávky</h2>';

    $html .= '<div class="shop-orders-wrap">';
    $html .= '<table class="shop-orders-table">';
    $html .= '<thead><tr>'
          .  '<th>Objednávka</th>'
          .  '<th>Datum</th>'
          .  '<th>Stav</th>'
          .  '<th>Celkem</th>'
          .  '<th>Platba</th>'
          .  '<th></th>'
          .  '</tr></thead><tbody>';

    foreach ($orders as $o) {
        $oid = (int)$o['id'];
        $num = htmlspecialchars((string)$o['order_number'], ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars((string)$o['created_at'], ENT_QUOTES, 'UTF-8');
        $statusCode = (string)($o['status'] ?? 'new');
        $statusLabel = htmlspecialchars(shop_order_status_label($statusCode), ENT_QUOTES, 'UTF-8');
        $total = number_format((float)$o['total_price'], 2, ',', ' ');
        $payLabel = htmlspecialchars(shop_payment_method_label((string)$o['payment_method']), ENT_QUOTES, 'UTF-8');

        $html .= '<tr class="shop-order-row" data-order-id="'.$oid.'">'
              .  '<td><strong>#'.$num.'</strong></td>'
              .  '<td>'.$date.'</td>'
              .  '<td><span class="shop-order-status shop-order-status--'.htmlspecialchars($statusCode, ENT_QUOTES, 'UTF-8').'">'.$statusLabel.'</span></td>'
              .  '<td>'.$total.' Kč</td>'
              .  '<td>'.$payLabel.'</td>'
              .  '<td><button type="button" class="shop-btn shop-btn--sm shop-order-toggle" data-target="shop-od-'.$oid.'">Detail</button></td>'
              .  '</tr>';

        // detail row
        $items = $itemsByOrder[$oid] ?? [];
        $html .= '<tr id="shop-od-'.$oid.'" class="shop-order-detail-row" style="display:none;">'
              .  '<td colspan="6">'
              .  '<div class="shop-order-detail">';

        // items table
        $html .= '<h3>Položky</h3>';
        if (!$items) {
            $html .= '<div class="shop-empty">Položky objednávky nejsou k dispozici.</div>';
        } else {
            $html .= '<table class="shop-order-items">'
                  .  '<thead><tr><th>Zboží</th><th>Množství</th><th>Cena/ks</th><th>Celkem</th></tr></thead><tbody>';
            $sumItems = 0.0;
            foreach ($items as $it) {
                $name = htmlspecialchars((string)($it['name'] ?? 'Produkt'), ENT_QUOTES, 'UTF-8');
                $qty = (int)($it['quantity'] ?? 0);
                $price = (float)($it['price'] ?? 0);
                $line = $price * $qty;
                $sumItems += $line;

// optional product link (správný router URL)
$slug = (string)($it['slug'] ?? '');
if ($slug !== '') {
    $href = '/cs/obchod?shop=produkt&slug=' . rawurlencode($slug);
    $name = '<a class="shop-link" href="'.$href.'">'.$name.'</a>';
}


                $html .= '<tr>'
                      .  '<td>'.$name.'</td>'
                      .  '<td>'.$qty.'</td>'
                      .  '<td>'.number_format($price, 2, ',', ' ').' Kč</td>'
                      .  '<td>'.number_format($line, 2, ',', ' ').' Kč</td>'
                      .  '</tr>';
            }
            $html .= '</tbody></table>';

$shippingPrice = (float)($o['shipping_price'] ?? 0);
$totalFloat = (float)($o['total_price'] ?? 0);

// dopočet poplatku dobírky z uložených dat (funguje i zpětně)
$codFee = 0.0;
if (($o['payment_method'] ?? '') === 'cod') {
    $codFee = $totalFloat - $shippingPrice - $sumItems;
    if ($codFee < 0.01) $codFee = 0.0; // ochrana proti haléřovým odchylkám
}

$html .= '<div class="shop-order-summary">'
      .  '<div><span>Mezisoučet</span><strong>'.number_format($sumItems, 2, ',', ' ').' Kč</strong></div>'
      .  '<div><span>Doprava</span><strong>'.number_format($shippingPrice, 2, ',', ' ').' Kč</strong></div>';

if ($codFee > 0) {
    $html .= '<div><span>Dobírka</span><strong>'.number_format($codFee, 2, ',', ' ').' Kč</strong></div>';
}


// DPH rozpis (pokud je zapnuté a objednávka obsahuje DPH)
$orderNet = (float)($o['total_net'] ?? 0);
$orderVat = (float)($o['total_vat'] ?? 0);
if ($orderNet <= 0.0 && $orderVat <= 0.0) {
    // fallback pro starší objednávky: DPH dopočítáme z položek (pokud jsou sloupce k dispozici)
    $orderNet = $sumItems + $shippingPrice + $codFee;
    $orderVat = 0.0;
    foreach ($items as $__it) {
        $orderVat += (float)($__it['vat_amount'] ?? 0);
    }
}

$vatGroups = [];
foreach ($items as $__it) {
    $pct = (float)($__it['vat_percent'] ?? 0);
    if ($pct <= 0.0) continue;
    $base = (float)($__it['price'] ?? 0) * (int)($__it['quantity'] ?? 0);
    $vat  = (float)($__it['vat_amount'] ?? 0);
    $key = (string)$pct;
    if (!isset($vatGroups[$key])) $vatGroups[$key] = ['pct'=>$pct,'base'=>0.0,'vat'=>0.0];
    $vatGroups[$key]['base'] += $base;
    $vatGroups[$key]['vat']  += $vat;
}
if ($orderVat > 0.009) {
    $html .= '<div><span>Základ (bez DPH)</span><strong>'.number_format($orderNet, 2, ',', ' ').' Kč</strong></div>';
    $html .= '<div><span>DPH</span><strong>'.number_format($orderVat, 2, ',', ' ').' Kč</strong></div>';
    if ($vatGroups) {
        usort($vatGroups, fn($a,$b)=>($b['pct'] <=> $a['pct']));
        foreach ($vatGroups as $__g) {
            $p = rtrim(rtrim(number_format((float)$__g['pct'], 2, ',', ' '), '0'), ',');
            $html .= '<div><span>DPH '.$p.'%</span><strong>'.number_format((float)$__g['vat'], 2, ',', ' ').' Kč</strong></div>';
        }
    }
}

$html .= '<div class="shop-order-summary-total"><span>Celkem</span><strong>'.number_format($totalFloat, 2, ',', ' ').' Kč</strong></div>'
      .  '</div>';

        }

        // status + payment
        $html .= '<div class="shop-order-meta">';
        $html .= '<div class="shop-order-meta__col">';
        $html .= '<h3>Stav objednávky</h3>';
        $html .= '<div><strong>'.$statusLabel.'</strong></div>';
        if (!empty($o['paid_at'])) {
            $html .= '<div class="shop-hint">Zaplaceno: '.htmlspecialchars((string)$o['paid_at'], ENT_QUOTES, 'UTF-8').'</div>';
        }
        $html .= '</div>';

        $html .= '<div class="shop-order-meta__col">';
        $html .= '<h3>Platební údaje</h3>';

        $pm = (string)($o['payment_method'] ?? '');
        if ($pm === 'fio_qr') {
            $acc = htmlspecialchars((string)($o['fio_account'] ?? ''), ENT_QUOTES, 'UTF-8');
            $vs  = htmlspecialchars((string)($o['fio_variable_symbol'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<div>Účet: <strong>'.$acc.'</strong></div>';
            if ($vs !== '') $html .= '<div>VS: <strong>'.$vs.'</strong></div>';
            $html .= '<div>Částka: <strong>'.number_format((float)($o['total_price'] ?? 0), 2, ',', ' ').' Kč</strong></div>';

            $payload = shop_qr_payload_for_order($o);
            $uri = shop_qr_img_data_uri($payload);
            if ($uri !== '') {
                $html .= '<div class="shop-order-qr"><img alt="QR platba" src="'.$uri.'"></div>';
            } else {
                $html .= '<div class="shop-hint">QR kód nelze vygenerovat.</div>';
            }
        } else {
            $html .= '<div class="shop-hint">Platba: '.$payLabel.'</div>';
        }

// faktura (PDF)
$invUrl = shop_invoice_url_for_order($db, $o, true);
if ($invUrl !== '') {
    $html .= '<div class="shop-hint" style="margin-top:10px;"><a class="shop-link" href="'.htmlspecialchars($invUrl, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener">Stáhnout fakturu (PDF)</a></div>';
}

        $html .= '</div>'; // col

        $html .= '<div class="shop-order-meta__col">';
        $html .= '<h3>Doručení</h3>';
        $shipName = htmlspecialchars((string)($o['shipping_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        if ($shipName !== '') $html .= '<div><strong>'.$shipName.'</strong></div>';
        $a1 = trim((string)($o['adress1'] ?? ''));
        $a2 = trim((string)($o['adress2'] ?? ''));
        $a3 = trim((string)($o['adress3'] ?? ''));
        if ($a1.$a2.$a3 !== '') {
            $html .= '<div>'.htmlspecialchars($a1, ENT_QUOTES, 'UTF-8').'</div>';
            if ($a2 !== '') $html .= '<div>'.htmlspecialchars($a2, ENT_QUOTES, 'UTF-8').'</div>';
            if ($a3 !== '') $html .= '<div>'.htmlspecialchars($a3, ENT_QUOTES, 'UTF-8').'</div>';
        }
        $html .= '</div>'; // col

        $html .= '</div>'; // meta
        $html .= '</div></td></tr>';
    }

    $html .= '</tbody></table></div></div>';

    $html .= <<<HTML
<script>
document.addEventListener('click', function(e){
  const btn = e.target.closest('.shop-order-toggle');
  if(!btn) return;

  const id = btn.getAttribute('data-target');
  if(!id) return;

  const row = document.getElementById(id);
  if(!row) return;

  const isHidden = (row.style.display === 'none' || getComputedStyle(row).display === 'none');
  row.style.display = isHidden ? 'table-row' : 'none';

  // volitelně změna textu tlačítka
  btn.textContent = isHidden ? 'Skrýt' : 'Detail';
});
</script>
HTML;

    return $html;
}

