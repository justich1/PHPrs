<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../functions/database.php';
$db = db_connect();

// přehoď warningy na výjimky, ať chytáme i Notice/Warning
set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });

try {

    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'empty_cart']);
        exit;
    }

    function shop_setting_get(PDO $db, string $key, $default='') {
        $st = $db->prepare("SELECT setting_value FROM shop_settings WHERE setting_key=? LIMIT 1");
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : $v;
    }

    function shop_vat_enabled(PDO $db): bool { return (int)shop_setting_get($db,'shop_vat_enabled','0')===1; }
    function shop_vat_percent_for_class(PDO $db, int $vatClass): float {
        if ($vatClass === 2) return (float)shop_setting_get($db,'shop_vat_rate_reduced','12');
        if ($vatClass === 1) return (float)shop_setting_get($db,'shop_vat_rate_standard','21');
        return 0.0;
    }
    function formatAccountNumber($accountNumber, $bankCode) {
        return trim((string)$accountNumber) . '/' . trim((string)$bankCode);
    }
    // CZ IBAN generator (z e-votop projektu)
    function generateCzIban($accountNumber, $bankCode) {
        $accountNumber = preg_replace('/\D/', '', (string)$accountNumber);
        $bankCode = preg_replace('/\D/', '', (string)$bankCode);
        $accountNumber = str_pad($accountNumber, 16, '0', STR_PAD_LEFT);
        $bban = $bankCode . $accountNumber . '123500'; // "CZ00" -> CZ=12 35; 00
        // mod 97 for huge numbers
        $remainder = 0;
        for ($i=0; $i<strlen($bban); $i++) {
            $remainder = ($remainder * 10 + (int)$bban[$i]) % 97;
        }
        $check = 98 - $remainder;
        return 'CZ' . str_pad((string)$check, 2, '0', STR_PAD_LEFT) . $bankCode . $accountNumber;
    }


function shop_order_status_label(string $status): string {
    $map = [
        'new' => 'Nová',
        'pending_payment' => 'Čeká na platbu',
        'paid' => 'Zaplaceno',
        'processing' => 'Příprava objednávky',
        'shipped' => 'Odesláno',
        'delivered' => 'Doručeno',
        'canceled' => 'Zrušeno',
    ];
    return $map[$status] ?? $status;
}
function shop_payment_method_label(string $method): string {
    $map = [
        'fio_qr' => 'Platba QR (bankovní převod)',
        'bank_transfer' => 'Bankovní převod',
        'cash' => 'Hotově',
        'cod' => 'Dobírka / platba při převzetí',
        'card' => 'Karta',
    ];
    return $map[$method] ?? $method;
}
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
function shop_invoice_url_for_order(PDO $db, array $order): string {
    $oid = (int)($order['id'] ?? 0);
    if ($oid <= 0) return '';
    $url = '/plugins/shop/api/invoice.php?order_id=' . rawurlencode((string)$oid);
    $tok = shop_invoice_token_for_order($db, $order);
    if ($tok !== '') $url .= '&token=' . rawurlencode($tok);
    return $url;
}
function shop_email_render_template(string $tpl, array $vars): string {
    foreach ($vars as $k=>$v) $tpl = str_replace('{{'.$k.'}}', (string)$v, $tpl);
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
function shop_email_send(PDO $db, string $to, string $subject, string $html): bool {
    $to = trim($to);
    if ($to === '') return false;
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $subj, $html, shop_email_headers($db));
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

    $invUrl = shop_invoice_url_for_order($db, $order);
    $invLink = ($invUrl !== '') ? '<p style="margin-top:12px;"><a href="'.htmlspecialchars($invUrl,ENT_QUOTES,'UTF-8').'">Stáhnout fakturu (PDF)</a></p>' : '';
    return $table . $summary . $invLink;
}
function shop_email_notify_order_created(PDO $db, int $orderId): void {
    $st = $db->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
    $st->execute([$orderId]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) return;

    $stI = $db->prepare("SELECT oi.quantity, oi.price, oi.vat_percent, oi.vat_amount, p.name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC");
    $stI->execute([$orderId]);
    $items = $stI->fetchAll(PDO::FETCH_ASSOC);

    $vars = [
        'order_number' => (string)($o['order_number'] ?? ''),
        'status' => shop_order_status_label((string)($o['status'] ?? 'new')),
        'payment' => shop_payment_method_label((string)($o['payment_method'] ?? '')),
        'total' => number_format((float)($o['total_price'] ?? 0), 2, ',', ' ') . ' Kč',
        'items' => shop_email_items_table($db, $o, $items),
    ];

    $to = trim((string)($o['email'] ?? ''));
    $subjTpl = (string)shop_setting_get($db, 'shop_email_subject_new_customer', 'Potvrzení objednávky {{order_number}}');
    $tpl = (string)shop_setting_get($db, 'shop_email_tpl_new_customer', '');
    if ($to !== '' && $tpl !== '') {
        shop_email_send($db, $to, shop_email_render_template($subjTpl, $vars), shop_email_render_template($tpl, $vars));
    }

    $adminTo = trim((string)shop_setting_get($db, 'shop_admin_email', ''));
    $subjTplA = (string)shop_setting_get($db, 'shop_email_subject_new_admin', 'Nová objednávka {{order_number}}');
    $tplA = (string)shop_setting_get($db, 'shop_email_tpl_new_admin', '');
    if ($adminTo !== '' && $tplA !== '') {
        shop_email_send($db, $adminTo, shop_email_render_template($subjTplA, $vars), shop_email_render_template($tplA, $vars));
    }
}


    $userId = $_SESSION['client_user_id'] ?? null;

    // basic customer fields (guest allowed)
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adress1 = trim($_POST['adress1'] ?? '');
    $adress2 = trim($_POST['adress2'] ?? '');
    $adress3 = trim($_POST['adress3'] ?? '');

    // Pokud je uživatel přihlášen přes plugin Uživatelé, předvyplníme údaje z plugin_clients.
    // Necháváme ale možnost přepsat (pokud POST hodnoty nejsou prázdné).
    if ($userId) {
        try {
            $stU = $db->prepare("SELECT email,contact_email,first_name,last_name,street,street_no,city,zip,phone,address FROM plugin_clients WHERE id=? LIMIT 1");
            $stU->execute([(int)$userId]);
            $u = $stU->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                if ($email === '') {
                    $email = trim($u['contact_email'] ?: $u['email']);
                }
                if ($telephone === '') {
                    $telephone = trim((string)($u['phone'] ?? ''));
                }
                if ($name === '') {
                    $fn = trim((string)($u['first_name'] ?? ''));
                    $ln = trim((string)($u['last_name'] ?? ''));
                    $name = trim($fn . ' ' . $ln);
                }

                // mapujeme fakturační adresu na 3 řádky adresy objednávky
                if ($adress1 === '') {
                    $street = trim((string)($u['street'] ?? ''));
                    $no = trim((string)($u['street_no'] ?? ''));
                    $adress1 = trim($street . ' ' . $no);

                    // fallback na staré pole address
                    if ($adress1 === '' && !empty($u['address'])) {
                        $lines = preg_split('/\r\n|\r|\n/', trim($u['address']));
                        if (!empty($lines[0])) $adress1 = trim($lines[0]);
                    }
                }
                if ($adress2 === '') {
                    $adress2 = trim((string)($u['city'] ?? ''));
                    if ($adress2 === '' && !empty($u['address'])) {
                        $lines = preg_split('/\r\n|\r|\n/', trim($u['address']));
                        if (!empty($lines[1])) $adress2 = trim($lines[1]);
                    }
                }
                if ($adress3 === '') {
                    $adress3 = trim((string)($u['zip'] ?? ''));
                    if ($adress3 === '' && !empty($u['address'])) {
                        $lines = preg_split('/\r\n|\r|\n/', trim($u['address']));
                        if (!empty($lines[2])) $adress3 = trim($lines[2]);
                    }
                }
            }
        } catch (Throwable $e) {
            // plugin_users not installed or table missing - ignorujeme a uložíme objednávku jako host
            $userId = null;
        }
    }

    $shipping_id = (int)($_POST['shipping_id'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'fio_qr'; // fio_qr | cod | cash
    $payment_account_id = (int)($_POST['payment_account_id'] ?? 0);

    // shipping
    $shipping = null;
    if ($shipping_id > 0) {
        $st = $db->prepare("SELECT * FROM shipping_methods WHERE id=? AND active=1");
        $st->execute([$shipping_id]);
        $shipping = $st->fetch(PDO::FETCH_ASSOC);
    }
    if (!$shipping) {
        $shipping = $db->query("SELECT * FROM shipping_methods WHERE active=1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }

    $cod_fee = (float)shop_setting_get($db, 'shop_cod_fee', '0');
    $shipping_price = (float)($shipping['price'] ?? 0);

    $sum_net = 0.0;
    $sum_vat = 0.0;

    // Recalculate prices from DB (anti-tamper)
    $items = [];
    foreach ($_SESSION['cart'] as $it) {
        $pid = (int)$it['id'];
        $qty = (int)$it['quantity'];
        if ($qty <= 0) continue;

        $st = $db->prepare("SELECT id,name,price,stock,active,vat_class FROM products WHERE id=? AND active=1 LIMIT 1");
        $st->execute([$pid]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) continue;

        // stock check (soft)
        if ((int)$p['stock'] < $qty) {
            http_response_code(409);
            echo json_encode(['ok'=>false,'error'=>'out_of_stock','product_id'=>$pid,'available'=>(int)$p['stock']]);
            exit;
        }

        $price_net = (float)$p['price'];
        $vat_class = (int)($p['vat_class'] ?? 0);
        $line_net = $price_net * $qty;
        $line_vat = 0.0;
        $vat_percent = 0.0;

        if (shop_vat_enabled($db)) {
            $vat_percent = shop_vat_percent_for_class($db, $vat_class);
            $line_vat = $line_net * ($vat_percent / 100.0);
        }

        $sum_net += $line_net;
        $sum_vat += $line_vat;

        $items[] = [
            'product_id'=>$pid,
            'quantity'=>$qty,
            'price'=>$price_net, // net
            'vat_percent'=>$vat_percent,
            'vat_amount'=>$line_vat
        ];
    }

    if (!$items) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'no_items']);
        exit;
    }

    $total_net = $sum_net + $shipping_price;
    if ($payment_method === 'cod') $total_net += $cod_fee;
    $total_vat = $sum_vat;
    $total = $total_net + $total_vat;

    $prefix = shop_setting_get($db, 'shop_order_prefix', 'EV');
    $order_number = $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $fio_variable_symbol = preg_replace('/\D/', '', (string)(time() % 1000000000)); // jednoduché VS

    // Payment account (selected -> default -> first) pouze pro fio_qr
    $account = null;
    if ($payment_method === 'fio_qr') {
        $defaultAccId = (int)shop_setting_get($db, 'default_payment_account_id', '0');
        $accId = $payment_account_id > 0 ? $payment_account_id : ($defaultAccId > 0 ? $defaultAccId : 0);

        if ($accId > 0) {
            $st = $db->prepare("SELECT * FROM payment_accounts WHERE id=? AND active=1 LIMIT 1");
            $st->execute([$accId]);
            $account = $st->fetch(PDO::FETCH_ASSOC);
        }
        if (!$account) {
            $account = $db->query("SELECT * FROM payment_accounts WHERE active=1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        if (!$account) {
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'no_payment_account']);
            exit;
        }
    }

    try {

        $db->beginTransaction();

        // Stav objednávky
        $status = 'new';
        if ($payment_method === 'cod' || $payment_method === 'cash') {
            $status = 'processing';
        }

// VS ukládáme VŽDY (i cash / cod)
// účet jen pro fio_qr
$fio_account_db = null;
$fio_vs_db = $fio_variable_symbol;

if ($payment_method === 'fio_qr') {
    $fio_account_db = formatAccountNumber($account['account_number'], $account['bank_code']);
}

        $st = $db->prepare("INSERT INTO orders
            (user_id, order_number, fio_account, fio_variable_symbol, total_price, total_net, total_vat, email, status, payment_method, created_at,
             name, telephone, adress1, adress2, adress3, shipping_id, shipping_name, shipping_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([
            $userId ? (int)$userId : null,
            $order_number,
            $fio_account_db,
            $fio_vs_db,
            $total,
            $total_net,
            $total_vat,
            $email,
            $status,
            $payment_method,
            $name,
            $telephone,
            $adress1,
            $adress2,
            $adress3,
            (int)($shipping['id'] ?? 0),
            $shipping['name'] ?? '',
            $shipping_price
        ]);
        $order_id = (int)$db->lastInsertId();

        $stItem = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, vat_percent, vat_amount) VALUES (?,?,?,?,?,?)");
        $stStock = $db->prepare("UPDATE products SET stock = stock - ? WHERE id=?");

        foreach ($items as $it) {
            $stItem->execute([$order_id, $it['product_id'], $it['quantity'], $it['price'], $it['vat_percent'], $it['vat_amount']]);
            $stStock->execute([$it['quantity'], $it['product_id']]);
        }

        $db->commit();

        // QR pouze pro fio_qr
        $qrData = null;
        $qrImageUrl = null;

        if ($payment_method === 'fio_qr') {
            $iban = generateCzIban($account['account_number'], $account['bank_code']);
            $amount = number_format($total, 2, '.', '');
            $currency = shop_setting_get($db,'shop_currency','CZK');
            $message = 'Objednávka ' . $order_number;

            $qrData = sprintf('SPD*1.0*ACC:%s*AM:%s*CC:%s*MSG:%s*X-VS:%s',
                $iban,
                $amount,
                $currency,
                str_replace(['*',"\n","\r"], [' ',' ',' '], $message),
                $fio_variable_symbol
            );

            // --- QR image endpoint (server-side) using bundled phpqrcode ---
            $qrToken = bin2hex(random_bytes(16));
            if (!isset($_SESSION['shop_qr'])) $_SESSION['shop_qr'] = [];

            $_SESSION['shop_qr'][$order_id] = ['token'=>$qrToken, 'payload'=>$qrData, 'ts'=>time()];
            if (count($_SESSION['shop_qr']) > 10) {
                uasort($_SESSION['shop_qr'], fn($a,$b)=>($a['ts']??0)<=>($b['ts']??0));
                $_SESSION['shop_qr'] = array_slice($_SESSION['shop_qr'], -10, null, true);
            }

            // ✅ /plugins/shop/api
            $apiBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
            $qrImageUrl = $apiBase . '/qr.php?order_id=' . rawurlencode((string)$order_id) . '&token=' . rawurlencode($qrToken);
        }


// e-maily (zákazník + admin) - chyby mailu nesmí shodit objednávku
try {
    shop_email_notify_order_created($db, $order_id);
} catch (Throwable $e) { /* ignore */ }

        // clear cart
        $_SESSION['cart'] = [];

        $response = [
            'ok'=>true,
            'order'=>[
                'id'=>$order_id,
                'order_number'=>$order_number,
                'status'=>$status,
                'total'=>$total,
                'payment_method'=>$payment_method,
                'fio_account'=>$fio_account_db,
                'fio_variable_symbol'=>$fio_vs_db,
            ],
        ];

        if ($payment_method === 'fio_qr') {
            $response['qr'] = [
                'payload'=>$qrData,
                'image_url'=>$qrImageUrl
            ];
        }

        echo json_encode($response);

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'exception','message'=>$e->getMessage()]);
    }

} catch (Throwable $e) {
    http_response_code(500);
    $msg = 'server_error';
    if ($e instanceof PDOException) {
        if (strpos($e->getMessage(),'Base table') !== false || strpos($e->getMessage(),'42S02') !== false) $msg = 'not_installed';
    }
    echo json_encode(['ok'=>false,'error'=>$msg,'message'=>$e->getMessage()]);
    exit;
}
