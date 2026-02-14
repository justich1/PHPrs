<?php
// Admin rozhraní pluginu E-shop
session_start();
if (!isset($_SESSION['user_id'])) { die('Přístup odepřen.'); }

require_once '../../config/config.php';
require_once '../../functions/database.php';

$db = db_connect();

function shop_setting_get(PDO $db, string $key, $default='') {
    $st = $db->prepare("SELECT setting_value FROM shop_settings WHERE setting_key=? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? $default : $v;
}
function shop_setting_set(PDO $db, string $key, string $value) {
    $st = $db->prepare("INSERT INTO shop_settings (setting_key, setting_value) VALUES (?,?)
                        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $st->execute([$key,$value]);
}
function shop_slugify($s) {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = preg_replace('~[^\pL\d]+~u', '-', $s);
    $s = trim($s, '-');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = preg_replace('~[^-\w]+~', '', $s);
    return $s ?: 'item';
}
function shop_resize_image(string $src, string $dst, int $maxW=1600, int $maxH=1600, int $quality=85): bool {
    $info = @getimagesize($src);
    if (!$info) return false;
    [$w,$h] = $info;
    $mime = $info['mime'] ?? '';
    $scale = min($maxW / max($w,1), $maxH / max($h,1), 1);
    $nw = (int)floor($w * $scale);
    $nh = (int)floor($h * $scale);

    // If no resize needed, just move
    if ($scale >= 0.999) {
        return @copy($src, $dst);
    }

    switch ($mime) {
        case 'image/jpeg': $im = @imagecreatefromjpeg($src); break;
        case 'image/png':  $im = @imagecreatefrompng($src); break;
        case 'image/gif':  $im = @imagecreatefromgif($src); break;
        case 'image/webp': $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : null; break;
        default: $im = null;
    }
    if (!$im) return false;

    $dstIm = imagecreatetruecolor($nw, $nh);
    // keep alpha for PNG/WebP
    if ($mime === 'image/png' || $mime === 'image/webp' || $mime === 'image/gif') {
        imagealphablending($dstIm, false);
        imagesavealpha($dstIm, true);
        $trans = imagecolorallocatealpha($dstIm, 0,0,0,127);
        imagefilledrectangle($dstIm, 0,0, $nw, $nh, $trans);
    }
    imagecopyresampled($dstIm, $im, 0,0,0,0, $nw,$nh, $w,$h);

    $ok = false;
    $ext = strtolower(pathinfo($dst, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        $ok = imagejpeg($dstIm, $dst, $quality);
    } elseif ($ext === 'png') {
        $ok = imagepng($dstIm, $dst, 6);
    } elseif ($ext === 'gif') {
        $ok = imagegif($dstIm, $dst);
    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
        $ok = imagewebp($dstIm, $dst, $quality);
    } else {
        // fallback to jpeg
        $ok = imagejpeg($dstIm, $dst, $quality);
    }

    imagedestroy($im);
    imagedestroy($dstIm);
    return $ok;
}

function shop_upload_image(array $file, string $destDirAbs, string $destUrlPrefix): ?string {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array(mime_content_type($file['tmp_name']), $allowed, true)) return null;

    if (!is_dir($destDirAbs)) mkdir($destDirAbs, 0775, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
    $name = $base . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    $path = rtrim($destDirAbs,'/') . '/' . $name;

// Resize/compress on upload for faster loading
$tmp = $file['tmp_name'];
$ok = false;

// Ensure extension is sane
$extLower = strtolower($ext);
if (!in_array($extLower, ['jpg','jpeg','png','gif','webp'], true)) {
    $extLower = 'jpg';
    $name = $base . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.jpg';
    $path = rtrim($destDirAbs,'/') . '/' . $name;
}

// Try to resize using GD, fallback to move
if (function_exists('imagecreatetruecolor')) {
    $ok = shop_resize_image($tmp, $path, 1600, 1600, 85);
    if (!$ok) $ok = move_uploaded_file($tmp, $path);
} else {
    $ok = move_uploaded_file($tmp, $path);
}

if ($ok) {
    return rtrim($destUrlPrefix,'/') . '/' . $name;
}
return null;

}

$tab = $_GET['tab'] ?? 'products';
$action = $_GET['action'] ?? 'list';
$msg = '';

/* =======================
   SETTINGS
   ======================= */
if ($tab === 'settings' && $_SERVER['REQUEST_METHOD']==='POST') {
    shop_setting_set($db, 'fio_token', trim($_POST['fio_token'] ?? ''));
    shop_setting_set($db, 'fio_days_back', trim($_POST['fio_days_back'] ?? '30'));
    shop_setting_set($db, 'shop_cod_fee', trim($_POST['shop_cod_fee'] ?? '0'));
    shop_setting_set($db, 'shop_order_prefix', trim($_POST['shop_order_prefix'] ?? 'EV'));
    shop_setting_set($db, 'shop_vat_enabled', isset($_POST['shop_vat_enabled']) ? '1' : '0');
    shop_setting_set($db, 'shop_vat_rate_standard', trim($_POST['shop_vat_rate_standard'] ?? '21'));
    shop_setting_set($db, 'shop_vat_rate_reduced', trim($_POST['shop_vat_rate_reduced'] ?? '12'));
    shop_setting_set($db, 'shop_base_path', trim($_POST['shop_base_path'] ?? ''));
    shop_setting_set($db, 'default_payment_account_id', trim($_POST['default_payment_account_id'] ?? '0'));
// e-mail nastavení
shop_setting_set($db, 'shop_admin_email', trim($_POST['shop_admin_email'] ?? ''));
shop_setting_set($db, 'shop_email_from', trim($_POST['shop_email_from'] ?? ''));
shop_setting_set($db, 'shop_email_from_name', trim($_POST['shop_email_from_name'] ?? ''));
shop_setting_set($db, 'shop_smtp_host', trim($_POST['shop_smtp_host'] ?? ''));
shop_setting_set($db, 'shop_smtp_port', trim($_POST['shop_smtp_port'] ?? '587'));
shop_setting_set($db, 'shop_smtp_user', trim($_POST['shop_smtp_user'] ?? ''));
shop_setting_set($db, 'shop_smtp_pass', trim($_POST['shop_smtp_pass'] ?? ''));
shop_setting_set($db, 'shop_smtp_encryption', trim($_POST['shop_smtp_encryption'] ?? 'none'));

shop_setting_set($db, 'shop_email_subject_new_customer', trim($_POST['shop_email_subject_new_customer'] ?? ''));
shop_setting_set($db, 'shop_email_subject_new_admin', trim($_POST['shop_email_subject_new_admin'] ?? ''));
shop_setting_set($db, 'shop_email_subject_status_customer', trim($_POST['shop_email_subject_status_customer'] ?? ''));
shop_setting_set($db, 'shop_email_subject_status_admin', trim($_POST['shop_email_subject_status_admin'] ?? ''));
shop_setting_set($db, 'shop_email_tpl_new_customer', trim($_POST['shop_email_tpl_new_customer'] ?? ''));
shop_setting_set($db, 'shop_email_tpl_new_admin', trim($_POST['shop_email_tpl_new_admin'] ?? ''));
shop_setting_set($db, 'shop_email_tpl_status_customer', trim($_POST['shop_email_tpl_status_customer'] ?? ''));
shop_setting_set($db, 'shop_email_tpl_status_admin', trim($_POST['shop_email_tpl_status_admin'] ?? ''));

// faktura (prodejce)
shop_setting_set($db, 'invoice_seller_name', trim($_POST['invoice_seller_name'] ?? ''));
shop_setting_set($db, 'invoice_seller_addr1', trim($_POST['invoice_seller_addr1'] ?? ''));
shop_setting_set($db, 'invoice_seller_addr2', trim($_POST['invoice_seller_addr2'] ?? ''));
shop_setting_set($db, 'invoice_seller_addr3', trim($_POST['invoice_seller_addr3'] ?? ''));
shop_setting_set($db, 'invoice_seller_ico', trim($_POST['invoice_seller_ico'] ?? ''));
shop_setting_set($db, 'invoice_seller_dic', trim($_POST['invoice_seller_dic'] ?? ''));
shop_setting_set($db, 'invoice_seller_bank', trim($_POST['invoice_seller_bank'] ?? ''));
shop_setting_set($db, 'invoice_note', trim($_POST['invoice_note'] ?? ''));
    // vygeneruj secret pro token faktury, pokud chybí
    if (trim((string)shop_setting_get($db,'shop_invoice_secret','')) === '') {
        shop_setting_set($db, 'shop_invoice_secret', bin2hex(random_bytes(24)));
    }


    $msg = 'Nastavení uloženo.';
}

/* =======================
   CATEGORIES CRUD
   ======================= */
if ($tab === 'categories') {
    if ($action === 'save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        if ($slug === '') $slug = shop_slugify($name);

        if ($id>0) {
            $st = $db->prepare("UPDATE categories SET name=?, slug=?, description=?, parent_id=?, active=? WHERE id=?");
            $st->execute([$name,$slug,$description, $parent_id ?: null, $active, $id]);
            $msg = 'Kategorie uložena.';
        } else {
            $st = $db->prepare("INSERT INTO categories (name, slug, description, parent_id, active) VALUES (?,?,?,?,?)");
            $st->execute([$name,$slug,$description, $parent_id ?: null, $active]);
            $msg = 'Kategorie vytvořena.';
        }
        $action='list';
    }

    if ($action === 'delete') {
        $id=(int)($_GET['id'] ?? 0);
        if ($id>0) {
            $st=$db->prepare("DELETE FROM categories WHERE id=?");
            $st->execute([$id]);
            $msg='Kategorie smazána.';
        }
        $action='list';
    }
}

/* =======================
   PRODUCTS CRUD
   ======================= */
if ($tab === 'products') {
    if ($action === 'save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $id = (int)($_POST['id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = $_POST['description'] ?? '';
        $additional_info = $_POST['additional_info'] ?? '';
        $price = (float)($_POST['price'] ?? 0);
        $vat_class = (int)($_POST['vat_class'] ?? 1);
        $stock = (int)($_POST['stock'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;

        if ($slug === '') $slug = shop_slugify($name);

        // upload main image
        $uploadsAbs = realpath(__DIR__ . '/../../') . '/uploads/products';
        $uploadsUrl = '/uploads/products';
        $mainImageUrl = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $mainImageUrl = shop_upload_image($_FILES['image'], $uploadsAbs, $uploadsUrl);
        }

        if ($id>0) {
            $sql = "UPDATE products SET category_id=?, name=?, slug=?, description=?, additional_info=?, price=?, vat_class=?, stock=?, active=?, updated_at=NOW()";
            $params = [$category_id,$name,$slug,$description,$additional_info,$price,$vat_class,$stock,$active];
            if ($mainImageUrl) { $sql .= ", image=?"; $params[]=$mainImageUrl; }
            $sql .= " WHERE id=?";
            $params[]=$id;
            $st = $db->prepare($sql);
            $st->execute($params);
            $msg='Produkt uložen.';
        } else {
            $st = $db->prepare("INSERT INTO products (category_id, name, slug, description, additional_info, price, vat_class, stock, image, active)
                                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $st->execute([$category_id,$name,$slug,$description,$additional_info,$price,$vat_class,$stock,$mainImageUrl,$active]);
            $id = (int)$db->lastInsertId();
            $msg='Produkt vytvořen.';
        }

        // upload gallery images (multiple)
        if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
            $count = count($_FILES['gallery']['name']);
            for ($i=0; $i<$count; $i++) {
                if ($_FILES['gallery']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $file = [
                    'name' => $_FILES['gallery']['name'][$i],
                    'type' => $_FILES['gallery']['type'][$i],
                    'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                    'error' => $_FILES['gallery']['error'][$i],
                    'size' => $_FILES['gallery']['size'][$i],
                ];
                $url = shop_upload_image($file, $uploadsAbs, $uploadsUrl);
                if ($url) {
                    $st = $db->prepare("INSERT INTO product_images (product_id, image_path, sort) VALUES (?,?,0)");
                    $st->execute([$id,$url]);
                }
            }
        }

        $action='list';
    }

    if ($action === 'delete') {
        $id=(int)($_GET['id'] ?? 0);
        if ($id>0) {
            $db->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$id]);
            $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            $msg='Produkt smazán.';
        }
        $action='list';
    }

    if ($action === 'delete_image') {
        $imgId=(int)($_GET['img_id'] ?? 0);
        if ($imgId>0) {
            $db->prepare("DELETE FROM product_images WHERE id=?")->execute([$imgId]);
            $msg='Obrázek smazán.';
        }
        $action='edit';
        $_GET['id']=(int)($_GET['product_id'] ?? 0);
    }
}


/* =======================
   SHIPPING METHODS
   ======================= */
if ($tab === 'shipping') {
    if ($action === 'save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $vat_class = (int)($_POST['vat_class'] ?? 1);
        $description = trim($_POST['description'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($id>0) {
            $st = $db->prepare("UPDATE shipping_methods SET name=?, price=?, description=?, active=? WHERE id=?");
            $st->execute([$name,$price,$description,$active,$id]);
            $msg = 'Doprava uložena.';
        } else {
            $st = $db->prepare("INSERT INTO shipping_methods (name, price, description, active) VALUES (?,?,?,?)");
            $st->execute([$name,$price,$description,$active]);
            $msg = 'Doprava vytvořena.';
        }
        $action='list';
    }

    if ($action === 'delete') {
        $id=(int)($_GET['id'] ?? 0);
        if ($id>0) {
            $st=$db->prepare("DELETE FROM shipping_methods WHERE id=?");
            $st->execute([$id]);
            $msg='Doprava smazána.';
        }
        $action='list';
    }
}

/* =======================
   PAYMENT ACCOUNTS
   ======================= */
if ($tab === 'accounts') {
    if ($action === 'save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $id = (int)($_POST['id'] ?? 0);
        $account_name = trim($_POST['account_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $bank_code = trim($_POST['bank_code'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($id>0) {
            $st = $db->prepare("UPDATE payment_accounts SET account_name=?, account_number=?, bank_code=?, active=? WHERE id=?");
            $st->execute([$account_name,$account_number,$bank_code,$active,$id]);
            $msg = 'Účet uložen.';
        } else {
            $st = $db->prepare("INSERT INTO payment_accounts (account_name, account_number, bank_code, active) VALUES (?,?,?,?)");
            $st->execute([$account_name,$account_number,$bank_code,$active]);
            $msg = 'Účet vytvořen.';
        }
        $action='list';
    }

    if ($action === 'delete') {
        $id=(int)($_GET['id'] ?? 0);
        if ($id>0) {
            $st=$db->prepare("DELETE FROM payment_accounts WHERE id=?");
            $st->execute([$id]);
            $msg='Účet smazán.';
        }
        $action='list';
    }
}


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
function shop_invoice_url_for_order(PDO $db, array $order, bool $includeToken=false): string {
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

    $invUrl = shop_invoice_url_for_order($db, $order, true);
    $invLink = ($invUrl !== '') ? '<p style="margin-top:12px;"><a href="'.htmlspecialchars($invUrl,ENT_QUOTES,'UTF-8').'">Stáhnout fakturu (PDF)</a></p>' : '';
    return $table . $summary . $invLink;
}
function shop_email_load_order(PDO $db, int $orderId): array {
    $st = $db->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
    $st->execute([$orderId]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) return [null, []];

    $stI = $db->prepare("SELECT oi.quantity, oi.price, oi.vat_percent, oi.vat_amount, p.name
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

    $to = trim((string)($o['email'] ?? ''));
    $subjTpl = (string)shop_setting_get($db, 'shop_email_subject_status_customer', 'Změna stavu objednávky {{order_number}}');
    $tpl = (string)shop_setting_get($db, 'shop_email_tpl_status_customer', '');
    if ($to !== '' && $tpl !== '') {
        shop_email_send($db, $to, shop_email_render_template($subjTpl, $vars), shop_email_render_template($tpl, $vars));
    }

    $adminTo = trim((string)shop_setting_get($db, 'shop_admin_email', ''));
    $subjTplA = (string)shop_setting_get($db, 'shop_email_subject_status_admin', 'Změna stavu {{order_number}}');
    $tplA = (string)shop_setting_get($db, 'shop_email_tpl_status_admin', '');
    if ($adminTo !== '' && $tplA !== '') {
        shop_email_send($db, $adminTo, shop_email_render_template($subjTplA, $vars), shop_email_render_template($tplA, $vars));
    }
}


function shop_qr_payload_for_order(array $order): string {
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

    ob_start();
    QRcode::png($payload, false, QR_ECLEVEL_M, $size, $margin);
    $png = ob_get_clean();
    if (!$png) return '';
    return 'data:image/png;base64,' . base64_encode($png);
}


/* =======================
   ORDERS
   ======================= */
if ($tab === 'orders') {
if ($action === 'set_status' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)($_POST['id'] ?? 0);
    $status=trim($_POST['status'] ?? 'new');

    // zjistíme původní stav (pro e-mail)
    $oldStatus = '';
    if ($id > 0) {
        $stOld = $db->prepare("SELECT status FROM orders WHERE id=? LIMIT 1");
        $stOld->execute([$id]);
        $oldStatus = (string)($stOld->fetchColumn() ?: '');
    }

    $st=$db->prepare("UPDATE orders SET status=? WHERE id=?");
    $st->execute([$status,$id]);

    // e-maily (zákazník + admin) - chyby mailu nesmí shodit admin
    if ($id > 0 && $oldStatus !== '' && $oldStatus !== $status) {
        try { shop_email_notify_status_changed($db, $id, $oldStatus, $status); } catch (Throwable $e) { /* ignore */ }
    }

    $msg='Stav uložen.';
    $action='list';
}
}

/* =======================
   Render
   ======================= */

$tabs = [
    'products' => 'Produkty',
    'categories' => 'Kategorie',
    'shipping' => 'Doprava',
    'accounts' => 'Účty',
    'orders' => 'Objednávky',
    'settings' => 'Nastavení',
];

?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>E-shop plugin</title>
    <style>
        body{font-family:Arial,sans-serif; background:#f3f4f6; margin:0;}
        .wrap{max-width:1200px; margin:20px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,.08);}
        .tabs a{display:inline-block; padding:10px 14px; margin-right:8px; border-radius:8px; text-decoration:none; color:#111; background:#eef2ff;}
        .tabs a.active{background:#111827; color:#fff;}
        .msg{padding:10px 12px; background:#ecfccb; border:1px solid #a3e635; border-radius:8px; margin:10px 0;}
        table{width:100%; border-collapse:collapse;}
        th,td{padding:10px; border-bottom:1px solid #eee; vertical-align:top;}
        input[type=text], input[type=number], textarea, select{width:100%; padding:8px; border:1px solid #ddd; border-radius:8px;}
        textarea{min-height:100px;}
        .row{display:grid; grid-template-columns:1fr 1fr; gap:14px;}
        .btn{display:inline-block; padding:8px 12px; background:#111827; color:#fff; border:none; border-radius:8px; text-decoration:none; cursor:pointer;}
        .btn.secondary{background:#374151;}
        .btn.danger{background:#b91c1c;}
        .actions a{margin-right:8px;}
        .gallery img{height:64px; border-radius:8px; margin:6px 6px 0 0; border:1px solid #eee;}
        .hint{color:#6b7280; font-size:12px;}
    </style>
</head>
<body>
<div class="wrap">
    <h1>E-shop plugin</h1>

    <div class="tabs">
        <?php foreach ($tabs as $k=>$label): ?>
            <a class="<?= $tab===$k?'active':'' ?>" href="?tab=<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php if ($tab==='products'): ?>

        <?php
            $cats = $db->query("SELECT id,name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            if ($action==='edit') {
                $id=(int)($_GET['id'] ?? 0);
                $p = ['id'=>0,'category_id'=>0,'name'=>'','slug'=>'','description'=>'','additional_info'=>'','price'=>0,'vat_class'=>1,'stock'=>0,'image'=>'','active'=>1];
                if ($id>0) {
                    $st=$db->prepare("SELECT * FROM products WHERE id=?");
                    $st->execute([$id]);
                    $p = $st->fetch(PDO::FETCH_ASSOC) ?: $p;
                }
                $imgs = [];
                if (!empty($p['id'])) {
                    $st=$db->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort,id");
                    $st->execute([(int)$p['id']]);
                    $imgs = $st->fetchAll(PDO::FETCH_ASSOC);
                }
        ?>
            <h2><?= $p['id']? 'Upravit produkt' : 'Nový produkt' ?></h2>
            <form method="post" action="?tab=products&action=save" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <div class="row">
                    <div>
                        <label>Kategorie</label>
                        <select name="category_id">
                            <?php foreach ($cats as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ((int)$p['category_id']===(int)$c['id'])?'selected':'' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Název</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required>
                        <div class="hint">Slug se doplní automaticky, pokud necháš prázdné.</div>
                    </div>
                </div>

                <div class="row">
                    <div>
                        <label>Slug</label>
                        <input type="text" name="slug" value="<?= htmlspecialchars($p['slug']) ?>">
                    </div>
                    <div class="row" style="grid-template-columns:1fr 1fr 1fr;">
                        <div>
                            <label>Cena</label>
                            <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($p['price']) ?>">
                        </div>
                        <div>
                            <label>DPH</label>
                            <?php $vatEnabled = (int)shop_setting_get($db,'shop_vat_enabled','0')===1; ?>
                            <select name="vat_class" <?= $vatEnabled ? '' : 'disabled' ?>>
                                <option value="0" <?= ((int)($p['vat_class'] ?? 0)===0)?'selected':'' ?>>Bez DPH</option>
                                <option value="1" <?= ((int)($p['vat_class'] ?? 1)===1)?'selected':'' ?>>Základní sazba</option>
                                <option value="2" <?= ((int)($p['vat_class'] ?? 1)===2)?'selected':'' ?>>Snížená sazba</option>
                            </select>
                            <?php if(!$vatEnabled): ?><div class="hint">DPH je vypnuté v nastavení.</div><?php endif; ?>
                        </div>
                        <div>
                            <label>Sklad</label>
                            <input type="number" name="stock" value="<?= (int)$p['stock'] ?>">
                        </div>
                    </div>
                </div>

                <label>Popis</label>
                <textarea name="description"><?= htmlspecialchars($p['description']) ?></textarea>

                <label>Doplňující info</label>
                <textarea name="additional_info"><?= htmlspecialchars($p['additional_info']) ?></textarea>

                <div class="row">
                    <div>
                        <label>Hlavní fotka</label>
                        <?php if (!empty($p['image'])): ?>
                            <div class="gallery"><img src="<?= htmlspecialchars($p['image']) ?>" alt=""></div>
                        <?php endif; ?>
                        <input type="file" name="image" accept="image/*">
                    </div>
                    <div>
                        <label>Galerie fotek (může být víc)</label>
                        <input type="file" name="gallery[]" accept="image/*" multiple>
                        <?php if ($imgs): ?>
                            <div class="gallery">
                                <?php foreach ($imgs as $im): ?>
                                    <div style="display:inline-block;">
                                        <img src="<?= htmlspecialchars($im['image_path']) ?>" alt="">
                                        <div class="actions">
                                            <a class="btn danger" href="?tab=products&action=delete_image&product_id=<?= (int)$p['id'] ?>&img_id=<?= (int)$im['id'] ?>" onclick="return confirm('Smazat obrázek?')">Smazat</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <label><input type="checkbox" name="active" <?= ((int)$p['active']===1)?'checked':'' ?>> Aktivní</label>

                <div style="margin-top:12px;">
                    <button class="btn" type="submit">Uložit</button>
                    <a class="btn secondary" href="?tab=products">Zpět</a>
                </div>
            </form>

        <?php } else { ?>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2>Produkty</h2>
                <a class="btn" href="?tab=products&action=edit">+ Nový produkt</a>
            </div>
            <?php
                // Filtrování + vyhledávání + řazení
                $q = trim($_GET['q'] ?? '');
                $catFilter = (int)($_GET['category_id'] ?? 0);
                $activeFilter = trim($_GET['active'] ?? ''); // '', '1', '0'

                $sort = trim($_GET['sort'] ?? 'id');
                $dir = strtolower(trim($_GET['dir'] ?? 'desc'));
                if ($dir !== 'asc') $dir = 'desc';

                $sortMap = [
                    'id' => 'p.id',
                    'name' => 'p.name',
                    'price' => 'p.price',
                    'stock' => 'p.stock',
                    'active' => 'p.active',
                    'category' => 'c.name',
                ];
                $orderBy = $sortMap[$sort] ?? 'p.id';
                $orderDir = ($dir === 'asc') ? 'ASC' : 'DESC';

                $where = [];
                $params = [];
                if ($catFilter > 0) { $where[] = 'p.category_id = ?'; $params[] = $catFilter; }
                if ($activeFilter === '1' || $activeFilter === '0') { $where[] = 'p.active = ?'; $params[] = (int)$activeFilter; }
                if ($q !== '') {
                    if (ctype_digit($q)) {
                        $where[] = '(p.id = ? OR p.name LIKE ? OR p.slug LIKE ? OR p.description LIKE ? OR p.additional_info LIKE ?)';
                        $params[] = (int)$q;
                    } else {
                        $where[] = '(p.name LIKE ? OR p.slug LIKE ? OR p.description LIKE ? OR p.additional_info LIKE ?)';
                    }
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                }

                $sql = "SELECT p.*, c.name AS category_name\n                        FROM products p\n                        LEFT JOIN categories c ON c.id=p.category_id";
                if ($where) $sql .= " WHERE " . implode(' AND ', $where);
                $sql .= " ORDER BY $orderBy $orderDir, p.id DESC";
                $sql .= " LIMIT 500";

                $st = $db->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:10px 0 14px;">
                <input type="hidden" name="tab" value="products">
                <input type="hidden" name="action" value="list">
                <div style="min-width:240px;">
                    <label>Vyhledat</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Název / slug / popis / ID">
                </div>
                <div style="min-width:220px;">
                    <label>Kategorie</label>
                    <select name="category_id">
                        <option value="0">— všechny —</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ($catFilter === (int)$c['id'])?'selected':'' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="min-width:180px;">
                    <label>Aktivní</label>
                    <select name="active">
                        <option value="" <?= ($activeFilter==='')?'selected':'' ?>>— vše —</option>
                        <option value="1" <?= ($activeFilter==='1')?'selected':'' ?>>ANO</option>
                        <option value="0" <?= ($activeFilter==='0')?'selected':'' ?>>NE</option>
                    </select>
                </div>
                <div style="min-width:220px;">
                    <label>Řazení</label>
                    <select name="sort">
                        <option value="id" <?= ($sort==='id')?'selected':'' ?>>ID</option>
                        <option value="name" <?= ($sort==='name')?'selected':'' ?>>Název</option>
                        <option value="category" <?= ($sort==='category')?'selected':'' ?>>Kategorie</option>
                        <option value="price" <?= ($sort==='price')?'selected':'' ?>>Cena</option>
                        <option value="stock" <?= ($sort==='stock')?'selected':'' ?>>Sklad</option>
                        <option value="active" <?= ($sort==='active')?'selected':'' ?>>Aktivní</option>
                    </select>
                </div>
                <div style="min-width:160px;">
                    <label>Směr</label>
                    <select name="dir">
                        <option value="desc" <?= ($dir==='desc')?'selected':'' ?>>Sestupně</option>
                        <option value="asc" <?= ($dir==='asc')?'selected':'' ?>>Vzestupně</option>
                    </select>
                </div>
                <div>
                    <button class="btn" type="submit">Filtrovat</button>
                    <a class="btn secondary" href="?tab=products">Reset</a>
                </div>
                <div class="hint" style="align-self:center;">Zobrazeno max. 500 položek.</div>
            </form>

            <table>
                <thead><tr><th>ID</th><th>Produkt</th><th>Kategorie</th><th>Cena</th><th>Sklad</th><th>Akt.</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['name']) ?></strong><br>
                            <span class="hint"><?= htmlspecialchars($r['slug']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($r['category_name'] ?? '') ?></td>
                        <td><?= number_format((float)$r['price'], 2, ',', ' ') ?> Kč</td>
                        <td><?= (int)$r['stock'] ?></td>
                        <td><?= ((int)$r['active']===1)?'ANO':'NE' ?></td>
                        <td class="actions">
                            <a class="btn secondary" href="?tab=products&action=edit&id=<?= (int)$r['id'] ?>">Upravit</a>
                            <a class="btn danger" href="?tab=products&action=delete&id=<?= (int)$r['id'] ?>" onclick="return confirm('Smazat produkt?')">Smazat</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php } ?>

    <?php elseif ($tab==='categories'): ?>

        <?php
            $allCats=$db->query("SELECT * FROM categories ORDER BY parent_id ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
            if ($action==='edit') {
                $id=(int)($_GET['id'] ?? 0);
                $c = ['id'=>0,'name'=>'','slug'=>'','description'=>'','parent_id'=>null,'active'=>1];
                if ($id>0) {
                    $st=$db->prepare("SELECT * FROM categories WHERE id=?");
                    $st->execute([$id]);
                    $c = $st->fetch(PDO::FETCH_ASSOC) ?: $c;
                }
        ?>
            <h2><?= $c['id']?'Upravit kategorii':'Nová kategorie' ?></h2>
            <form method="post" action="?tab=categories&action=save">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <div class="row">
                    <div>
                        <label>Název</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($c['name']) ?>" required>
                    </div>
                    <div>
                        <label>Slug</label>
                        <input type="text" name="slug" value="<?= htmlspecialchars($c['slug']) ?>">
                        <div class="hint">Slug se doplní automaticky, pokud necháš prázdné.</div>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Nadřazená kategorie</label>
                        <select name="parent_id">
                            <option value="0">— žádná —</option>
                            <?php foreach ($allCats as $r): ?>
                                <option value="<?= (int)$r['id'] ?>" <?= ((int)$c['parent_id']===(int)$r['id'])?'selected':'' ?>>
                                    <?= htmlspecialchars($r['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><input type="checkbox" name="active" <?= ((int)$c['active']===1)?'checked':'' ?>> Aktivní</label>
                    </div>
                </div>
                <label>Popis</label>
                <textarea name="description"><?= htmlspecialchars($c['description']) ?></textarea>

                <div style="margin-top:12px;">
                    <button class="btn" type="submit">Uložit</button>
                    <a class="btn secondary" href="?tab=categories">Zpět</a>
                </div>
            </form>

        <?php } else { ?>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2>Kategorie</h2>
                <a class="btn" href="?tab=categories&action=edit">+ Nová kategorie</a>
            </div>
            <?php
                // Filtrování + vyhledávání + řazení
                $q = trim($_GET['q'] ?? '');
                $parentFilter = (int)($_GET['parent_id'] ?? -1); // -1 = všechny
                $activeFilter = trim($_GET['active'] ?? ''); // '', '1', '0'

                $sort = trim($_GET['sort'] ?? 'name');
                $dir = strtolower(trim($_GET['dir'] ?? 'asc'));
                if ($dir !== 'asc') $dir = 'desc';

                $sortMap = [
                    'id' => 'id',
                    'name' => 'name',
                    'slug' => 'slug',
                    'parent' => 'parent_id',
                    'active' => 'active',
                ];
                $orderBy = $sortMap[$sort] ?? 'name';
                $orderDir = ($dir === 'asc') ? 'ASC' : 'DESC';

                $where = [];
                $params = [];
                if ($parentFilter >= 0) { // 0 = pouze root, >0 = konkrétní parent
                    if ($parentFilter === 0) {
                        $where[] = 'parent_id IS NULL';
                    } else {
                        $where[] = 'parent_id = ?';
                        $params[] = $parentFilter;
                    }
                }
                if ($activeFilter === '1' || $activeFilter === '0') { $where[] = 'active = ?'; $params[] = (int)$activeFilter; }
                if ($q !== '') {
                    if (ctype_digit($q)) {
                        $where[] = '(id = ? OR name LIKE ? OR slug LIKE ? OR description LIKE ?)';
                        $params[] = (int)$q;
                    } else {
                        $where[] = '(name LIKE ? OR slug LIKE ? OR description LIKE ?)';
                    }
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                    $params[] = '%' . $q . '%';
                }

                $sql = 'SELECT * FROM categories';
                if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
                $sql .= " ORDER BY $orderBy $orderDir, id DESC";
                $sql .= ' LIMIT 500';
                $st = $db->prepare($sql);
                $st->execute($params);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:10px 0 14px;">
                <input type="hidden" name="tab" value="categories">
                <input type="hidden" name="action" value="list">
                <div style="min-width:240px;">
                    <label>Vyhledat</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Název / slug / popis / ID">
                </div>
                <div style="min-width:240px;">
                    <label>Nadřazená</label>
                    <select name="parent_id">
                        <option value="-1" <?= ($parentFilter===-1)?'selected':'' ?>>— všechny —</option>
                        <option value="0" <?= ($parentFilter===0)?'selected':'' ?>>— bez nadřazené —</option>
                        <?php foreach ($allCats as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ($parentFilter === (int)$c['id'])?'selected':'' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="min-width:180px;">
                    <label>Aktivní</label>
                    <select name="active">
                        <option value="" <?= ($activeFilter==='')?'selected':'' ?>>— vše —</option>
                        <option value="1" <?= ($activeFilter==='1')?'selected':'' ?>>ANO</option>
                        <option value="0" <?= ($activeFilter==='0')?'selected':'' ?>>NE</option>
                    </select>
                </div>
                <div style="min-width:220px;">
                    <label>Řazení</label>
                    <select name="sort">
                        <option value="name" <?= ($sort==='name')?'selected':'' ?>>Název</option>
                        <option value="id" <?= ($sort==='id')?'selected':'' ?>>ID</option>
                        <option value="slug" <?= ($sort==='slug')?'selected':'' ?>>Slug</option>
                        <option value="parent" <?= ($sort==='parent')?'selected':'' ?>>Parent</option>
                        <option value="active" <?= ($sort==='active')?'selected':'' ?>>Aktivní</option>
                    </select>
                </div>
                <div style="min-width:160px;">
                    <label>Směr</label>
                    <select name="dir">
                        <option value="asc" <?= ($dir==='asc')?'selected':'' ?>>Vzestupně</option>
                        <option value="desc" <?= ($dir==='desc')?'selected':'' ?>>Sestupně</option>
                    </select>
                </div>
                <div>
                    <button class="btn" type="submit">Filtrovat</button>
                    <a class="btn secondary" href="?tab=categories">Reset</a>
                </div>
                <div class="hint" style="align-self:center;">Zobrazeno max. 500 položek.</div>
            </form>
            <table>
                <thead><tr><th>ID</th><th>Název</th><th>Slug</th><th>Parent</th><th>Akt.</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= htmlspecialchars($r['slug']) ?></td>
                        <td><?= htmlspecialchars($r['parent_id'] ?? '') ?></td>
                        <td><?= ((int)$r['active']===1)?'ANO':'NE' ?></td>
                        <td class="actions">
                            <a class="btn secondary" href="?tab=categories&action=edit&id=<?= (int)$r['id'] ?>">Upravit</a>
                            <a class="btn danger" href="?tab=categories&action=delete&id=<?= (int)$r['id'] ?>" onclick="return confirm('Smazat kategorii?')">Smazat</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php } ?>

    <?php elseif ($tab==='orders'): ?>

        <h2>Objednávky</h2>

        <?php
            $statusFilter = trim($_GET['status'] ?? '');
            $q = trim($_GET['q'] ?? '');

            $where = [];
            $params = [];

            if ($statusFilter !== '') {
                $where[] = "status = ?";
                $params[] = $statusFilter;
            }
            if ($q !== '') {
                $where[] = "(order_number LIKE ? OR email LIKE ? OR name LIKE ? OR fio_variable_symbol LIKE ?)";
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
            }

            $sql = "SELECT * FROM orders";
            if ($where) $sql .= " WHERE " . implode(" AND ", $where);
            $sql .= " ORDER BY id DESC LIMIT 200";

            $st = $db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            // Položky pro zobrazené objednávky (1 dotaz)
            $itemsByOrder = [];
            if ($rows) {
                $ids = array_map(fn($o)=>(int)$o['id'], $rows);
                $place = implode(',', array_fill(0, count($ids), '?'));
                $sqlI = "SELECT oi.order_id, oi.quantity, oi.price, oi.vat_percent, oi.vat_amount, p.name
                         FROM order_items oi
                         LEFT JOIN products p ON p.id = oi.product_id
                         WHERE oi.order_id IN ($place)
                         ORDER BY oi.order_id DESC, oi.id ASC";
                $stI = $db->prepare($sqlI);
                $stI->execute($ids);
                while ($r = $stI->fetch(PDO::FETCH_ASSOC)) {
                    $oid = (int)$r['order_id'];
                    if (!isset($itemsByOrder[$oid])) $itemsByOrder[$oid] = [];
                    $itemsByOrder[$oid][] = $r;
                }
            }

            $statusMap = shop_order_statuses();
        ?>

        <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:10px 0 14px;">
            <input type="hidden" name="tab" value="orders">
            <div>
                <label>Stav</label>
                <select name="status">
                    <option value="">Vše</option>
                    <?php foreach ($statusMap as $k=>$v): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= ($statusFilter===$k)?'selected':'' ?>><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Hledat</label>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="číslo, e-mail, jméno, VS">
            </div>
            <div>
                <button class="btn" type="submit">Filtrovat</button>
                <a class="btn secondary" href="?tab=orders">Reset</a>
            </div>
        </form>

        <?php if (!$rows): ?>
            <div class="hint">Žádné objednávky.</div>
        <?php else: ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Stav</th>
                    <th>Zákazník</th>
                    <th>Celkem</th>
                    <th>Platba</th>
                    <th>VS</th>
                    <th>Vytvořeno</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): 
                $oid = (int)$r['id'];
                $statusCode = (string)($r['status'] ?? 'new');
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['order_number']) ?></strong></td>
                    <td><?= htmlspecialchars(shop_order_status_label($statusCode)) ?></td>
                    <td>
                        <?= htmlspecialchars($r['name'] ?? '') ?><br>
                        <span class="hint"><?= htmlspecialchars($r['email'] ?? '') ?></span>
                    </td>
                    <td><?= number_format((float)$r['total_price'], 2, ',', ' ') ?> Kč</td>
                    <td><?= htmlspecialchars(shop_payment_method_label((string)$r['payment_method'])) ?></td>
                    <td><?= htmlspecialchars($r['fio_variable_symbol'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn secondary js-od-toggle" data-target="od-<?= $oid ?>">Detail</button>
                    </td>
                </tr>

                <tr id="od-<?= $oid ?>" class="js-od-row" style="display:none;">
                    <td colspan="8">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;padding:10px 0;">
                            <div>
                                <h3 style="margin:0 0 8px;">Položky</h3>
                                <?php $items = $itemsByOrder[$oid] ?? []; ?>
                                <?php if (!$items): ?>
                                    <div class="hint">Položky nejsou k dispozici.</div>
                                <?php else: ?>
                                    <table>
                                        <thead><tr><th>Zboží</th><th>Množství</th><th>Cena/ks</th><th>Celkem</th></tr></thead>
                                        <tbody>
                                        <?php $sum=0.0; foreach ($items as $it):
                                            $qty=(int)$it['quantity'];
                                            $price=(float)$it['price'];
                                            $line=$qty*$price;
                                            $sum += $line;
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($it['name'] ?? 'Produkt') ?></td>
                                                <td><?= $qty ?></td>
                                                <td><?= number_format($price, 2, ',', ' ') ?> Kč</td>
                                                <td><?= number_format($line, 2, ',', ' ') ?> Kč</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                    <div style="margin-top:8px;display:grid;gap:6px;max-width:420px;">
                                        <div style="display:flex;justify-content:space-between;gap:10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;">
                                            <span>Mezisoučet</span>
                                            <strong><?= number_format($sum, 2, ',', ' ') ?> Kč</strong>
                                        </div>
<?php
$shipP = (float)($r['shipping_price'] ?? 0);
$totalP = (float)($r['total_price'] ?? 0);
$codFee = 0.0;
if (($r['payment_method'] ?? '') === 'cod') {
    $codFee = $totalP - $shipP - $sum;
    if ($codFee < 0.01) $codFee = 0.0;
}
?>

<div style="display:flex;justify-content:space-between;gap:10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;">
    <span>Doprava</span>
    <strong><?= number_format($shipP, 2, ',', ' ') ?> Kč</strong>
</div>

<?php if ($codFee > 0): ?>
<div style="display:flex;justify-content:space-between;gap:10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;">
    <span>Dobírka</span>
    <strong><?= number_format($codFee, 2, ',', ' ') ?> Kč</strong>
</div>
<?php endif; ?>


<?php
$vatEnabled = ((int)shop_setting_get($db,'shop_vat_enabled','0')===1);
$orderNet = (float)($r['total_net'] ?? 0);
$orderVat = (float)($r['total_vat'] ?? 0);
if ($orderNet <= 0.0 && $orderVat <= 0.0) {
    $orderNet = $sum + $shipP + $codFee;
    $orderVat = 0.0;
    foreach ($its as $__it) $orderVat += (float)($__it['vat_amount'] ?? 0);
}
$vatGroups = [];
foreach ($its as $__it) {
    $pct = (float)($__it['vat_percent'] ?? 0);
    if ($pct <= 0.0) continue;
    $base = (float)($__it['price'] ?? 0) * (int)($__it['quantity'] ?? 0);
    $vat  = (float)($__it['vat_amount'] ?? 0);
    $key = (string)$pct;
    if (!isset($vatGroups[$key])) $vatGroups[$key] = ['pct'=>$pct,'base'=>0.0,'vat'=>0.0];
    $vatGroups[$key]['base'] += $base;
    $vatGroups[$key]['vat']  += $vat;
}
if ($vatEnabled && $orderVat > 0.009):
?>
<div style="display:flex;justify-content:space-between;gap:10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;">
    <span>Základ (bez DPH)</span>
    <strong><?= number_format($orderNet, 2, ',', ' ') ?> Kč</strong>
</div>
<div style="display:flex;justify-content:space-between;gap:10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;">
    <span>DPH</span>
    <strong><?= number_format($orderVat, 2, ',', ' ') ?> Kč</strong>
</div>
<?php
    if ($vatGroups) {
        $vatGroups = array_values($vatGroups);
        usort($vatGroups, fn($a,$b)=>($b['pct']<=>$a['pct']));
        foreach ($vatGroups as $__g) {
            $p = rtrim(rtrim(number_format((float)$__g['pct'], 2, ',', ' '), '0'), ',');
            ?>
            <div style="display:flex;justify-content:space-between;gap:10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;">
                <span>DPH <?= htmlspecialchars($p) ?>%</span>
                <strong><?= number_format((float)$__g['vat'], 2, ',', ' ') ?> Kč</strong>
            </div>
            <?php
        }
    }
?>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;gap:10px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:8px 10px;">
    <span>Celkem</span>
    <strong><?= number_format($totalP, 2, ',', ' ') ?> Kč</strong>
</div>

                                    </div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <h3 style="margin:0 0 8px;">Zákazník a doručení</h3>
                                <div><strong><?= htmlspecialchars($r['name'] ?? '') ?></strong></div>
                                <div><?= htmlspecialchars($r['telephone'] ?? '') ?></div>
                                <div><?= htmlspecialchars($r['email'] ?? '') ?></div>
                                <div style="margin-top:8px;">
                                    <div><strong><?= htmlspecialchars($r['shipping_name'] ?? '') ?></strong></div>
                                    <div><?= htmlspecialchars($r['adress1'] ?? '') ?></div>
                                    <?php if (!empty($r['adress2'])): ?><div><?= htmlspecialchars($r['adress2']) ?></div><?php endif; ?>
                                    <?php if (!empty($r['adress3'])): ?><div><?= htmlspecialchars($r['adress3']) ?></div><?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <h3 style="margin:0 0 8px;">Stav a platba</h3>

                                <form method="post" action="?tab=orders&action=set_status" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
                                    <input type="hidden" name="id" value="<?= $oid ?>">
                                    <div>
                                        <label>Stav</label>
                                        <select name="status">
                                            <?php foreach ($statusMap as $k=>$v): ?>
                                                <option value="<?= htmlspecialchars($k) ?>" <?= ($statusCode===$k)?'selected':'' ?>><?= htmlspecialchars($v) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <button class="btn" type="submit">Uložit</button>
                                    </div>
                                </form>

                                <div style="margin-top:10px;">
                                    <div>Platba: <strong><?= htmlspecialchars(shop_payment_method_label((string)$r['payment_method'])) ?></strong></div>
                                    <?php if (!empty($r['fio_account'])): ?>
                                        <div>Účet: <strong><?= htmlspecialchars($r['fio_account']) ?></strong></div>
                                    <?php endif; ?>
                                    <?php if (!empty($r['fio_variable_symbol'])): ?>
                                        <div>VS: <strong><?= htmlspecialchars($r['fio_variable_symbol']) ?></strong></div>
                                    <?php endif; ?>
                                    <div>Částka: <strong><?= number_format((float)($r['total_price'] ?? 0), 2, ',', ' ') ?> Kč</strong></div>
                                    <?php $invUrl = shop_invoice_url_for_order($db, $r, false); if($invUrl): ?>
                                        <div style="margin-top:10px;"><a href="<?= htmlspecialchars($invUrl) ?>" target="_blank" rel="noopener">Stáhnout fakturu (PDF)</a></div>
                                    <?php endif; ?>
                                </div>

                                <?php if (($r['payment_method'] ?? '') === 'fio_qr'): ?>
                                    <?php
                                        $payload = shop_qr_payload_for_order($r);
                                        $uri = shop_qr_img_data_uri($payload);
                                    ?>
                                    <?php if ($uri): ?>
                                        <div style="margin-top:10px;">
                                            <img src="<?= htmlspecialchars($uri) ?>" alt="QR platba" style="max-width:220px;height:auto;border:1px solid #e5e7eb;border-radius:10px;padding:8px;background:#fff;">
                                        </div>
                                    <?php else: ?>
                                        <div class="hint" style="margin-top:8px;">QR kód nelze vygenerovat.</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>

            <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        document.addEventListener('click', function(e){
            const btn = e.target.closest('.js-od-toggle');
            if(!btn) return;
            const id = btn.getAttribute('data-target');
            const row = id ? document.getElementById(id) : null;
            if(!row) return;
            const open = (row.style.display === 'table-row');
            row.style.display = open ? 'none' : 'table-row';
            btn.textContent = open ? 'Detail' : 'Skrýt';
        });
        </script>

        <div class="hint">Fio párování plateb spusť cronem: <code>/plugins/shop/cron/fio_check_payments.php</code></div>

        <?php endif; ?>

    <?php elseif ($tab==='shipping'): ?>

        <h2>Doprava</h2>
        <?php
            if ($action==='edit') {
                $id=(int)($_GET['id'] ?? 0);
                $s = ['id'=>0,'name'=>'','price'=>0,'description'=>'','active'=>1];
                if ($id>0) {
                    $st=$db->prepare("SELECT * FROM shipping_methods WHERE id=?");
                    $st->execute([$id]);
                    $s = $st->fetch(PDO::FETCH_ASSOC) ?: $s;
                }
        ?>
            <form method="post" action="?tab=shipping&action=save">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <div class="row">
                    <div>
                        <label>Název</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($s['name']) ?>" required>
                    </div>
                    <div>
                        <label>Cena (Kč)</label>
                        <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($s['price']) ?>">
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label>Popis</label>
                    <textarea name="description"><?= htmlspecialchars($s['description']) ?></textarea>
                </div>
                <div style="margin-top:12px;">
                    <label><input type="checkbox" name="active" <?= ((int)$s['active'])===1?'checked':'' ?>> Aktivní</label>
                </div>
                <div style="margin-top:12px;">
                    <button class="btn" type="submit">Uložit</button>
                    <a class="btn secondary" href="?tab=shipping">Zpět</a>
                </div>
            </form>
        <?php
            } else {
                $rows = $db->query("SELECT * FROM shipping_methods ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        ?>
            <div style="margin:12px 0;">
                <a class="btn" href="?tab=shipping&action=edit">+ Přidat dopravu</a>
            </div>
            <table>
                <thead><tr><th>ID</th><th>Název</th><th>Cena</th><th>Aktivní</th><th>Akce</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= number_format((float)$r['price'],2,',',' ') ?> Kč</td>
                        <td><?= ((int)$r['active'])===1?'Ano':'Ne' ?></td>
                        <td class="actions">
                            <a class="btn secondary" href="?tab=shipping&action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
                            <a class="btn danger" href="?tab=shipping&action=delete&id=<?= (int)$r['id'] ?>" onclick="return confirm('Smazat?')">Smazat</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php } ?>

    <?php elseif ($tab==='accounts'): ?>

        <h2>Účty pro platbu (QR/Fio)</h2>
        <?php
            if ($action==='edit') {
                $id=(int)($_GET['id'] ?? 0);
                $a = ['id'=>0,'account_name'=>'','account_number'=>'','bank_code'=>'','active'=>1];
                if ($id>0) {
                    $st=$db->prepare("SELECT * FROM payment_accounts WHERE id=?");
                    $st->execute([$id]);
                    $a = $st->fetch(PDO::FETCH_ASSOC) ?: $a;
                }
        ?>
            <form method="post" action="?tab=accounts&action=save">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <div class="row">
                    <div>
                        <label>Název účtu</label>
                        <input type="text" name="account_name" value="<?= htmlspecialchars($a['account_name']) ?>" required>
                    </div>
                    <div>
                        <label>Číslo účtu</label>
                        <input type="text" name="account_number" value="<?= htmlspecialchars($a['account_number']) ?>" required>
                    </div>
                </div>
                <div class="row" style="margin-top:12px;">
                    <div>
                        <label>Kód banky</label>
                        <input type="text" name="bank_code" value="<?= htmlspecialchars($a['bank_code']) ?>" required>
                    </div>
                    <div>
                        <label><input type="checkbox" name="active" <?= ((int)$a['active'])===1?'checked':'' ?>> Aktivní</label>
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <button class="btn" type="submit">Uložit</button>
                    <a class="btn secondary" href="?tab=accounts">Zpět</a>
                </div>
            </form>
        <?php
            } else {
                $rows = $db->query("SELECT * FROM payment_accounts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        ?>
            <div style="margin:12px 0;">
                <a class="btn" href="?tab=accounts&action=edit">+ Přidat účet</a>
            </div>
            <table>
                <thead><tr><th>ID</th><th>Název</th><th>Účet</th><th>Aktivní</th><th>Akce</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= htmlspecialchars($r['account_name']) ?></td>
                        <td><?= htmlspecialchars($r['account_number'].'/'.$r['bank_code']) ?></td>
                        <td><?= ((int)$r['active'])===1?'Ano':'Ne' ?></td>
                        <td class="actions">
                            <a class="btn secondary" href="?tab=accounts&action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
                            <a class="btn danger" href="?tab=accounts&action=delete&id=<?= (int)$r['id'] ?>" onclick="return confirm('Smazat?')">Smazat</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php } ?>

<?php elseif ($tab==='settings'): ?>

        <h2>Nastavení</h2>
        <form method="post">
            <div class="row">
                <div>
                    <label>Fio token</label>
                    <input type="text" name="fio_token" value="<?= htmlspecialchars(shop_setting_get($db,'fio_token','')) ?>">
                    <div class="hint">Token nebude nikde vypisován na webu.</div>
                </div>
                <div>
                    <label>Dny zpětně (Fio)</label>
                    <input type="number" name="fio_days_back" value="<?= htmlspecialchars(shop_setting_get($db,'fio_days_back','30')) ?>">
                </div>
            </div>

            <div class="row">
                <div>
                    <label>Poplatek dobírka (Kč)</label>
                    <input type="number" step="0.01" name="shop_cod_fee" value="<?= htmlspecialchars(shop_setting_get($db,'shop_cod_fee','0')) ?>">
                </div>
                <div>
                    <label>Prefix objednávky</label>
                    <input type="text" name="shop_order_prefix" value="<?= htmlspecialchars(shop_setting_get($db,'shop_order_prefix','EV')) ?>">
                </div>
            

            <hr style="margin:16px 0;">
            <h3 style="margin:0 0 10px;">DPH</h3>
            <?php $vatEnabledSet = (int)shop_setting_get($db,'shop_vat_enabled','0')===1; ?>
            <div class="row" style="grid-template-columns:1fr 1fr 1fr;">
                <div>
                    <label style="display:flex;align-items:center;gap:10px;">
                        <input type="checkbox" name="shop_vat_enabled" value="1" <?= $vatEnabledSet?'checked':'' ?>>
                        <span>Používat DPH</span>
                    </label>
                    <div class="hint">Pokud je DPH vypnuté, ceny se berou jako koncové (bez rozpisu DPH) a u produktu se volba DPH nepoužije.</div>
                </div>
                <div>
                    <label>DPH základní (%)</label>
                    <input type="number" step="0.01" name="shop_vat_rate_standard" value="<?= htmlspecialchars(shop_setting_get($db,'shop_vat_rate_standard','21')) ?>">
                </div>
                <div>
                    <label>DPH snížená (%)</label>
                    <input type="number" step="0.01" name="shop_vat_rate_reduced" value="<?= htmlspecialchars(shop_setting_get($db,'shop_vat_rate_reduced','12')) ?>">
                </div>
            </div>

</div>

            
            <div class="row">
                <div>
                    <label>Základní URL obchodu</label>
                    <input type="text" name="shop_base_path" value="<?= htmlspecialchars(shop_setting_get($db,'shop_base_path','')) ?>" placeholder="/cs/obchod">
                    <div class="hint">Zadej cestu, kde běží obchod (bez domény). Plugin pak bude generovat čisté odkazy: /kategorie/..., /produkt/..., /kosik… Pokud necháš prázdné, zkusí se odhadnout z aktuální URL.</div>
                </div>
                <div>
                    <label>Výchozí účet pro QR platby</label>
                    <?php $accs = $db->query("SELECT id,account_name,account_number,bank_code FROM payment_accounts WHERE active=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC); ?>
                    <select name="default_payment_account_id">
                        <option value="0">— automaticky (první aktivní) —</option>
                        <?php $def = (int)shop_setting_get($db,'default_payment_account_id','0'); ?>
                        <?php foreach ($accs as $ac): ?>
                            <option value="<?= (int)$ac['id'] ?>" <?= $def===(int)$ac['id']?'selected':'' ?>>
                                <?= htmlspecialchars($ac['account_name'].' ('.$ac['account_number'].'/'.$ac['bank_code'].')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            
<hr style="margin:16px 0;">
<h3 style="margin:0 0 10px;">E-maily</h3>

<label>Admin e-mail (notifikace)</label>
<input type="email" name="shop_admin_email" value="<?= htmlspecialchars(shop_setting_get($db,'shop_admin_email','')) ?>" placeholder="např. admin@domena.cz">

<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
    <div>
        <label>Odesílatel e-mail</label>
        <input type="email" name="shop_email_from" value="<?= htmlspecialchars(shop_setting_get($db,'shop_email_from','')) ?>" placeholder="např. obchod@domena.cz">
    </div>
    <div>
        <label>Odesílatel jméno</label>
        <input type="text" name="shop_email_from_name" value="<?= htmlspecialchars(shop_setting_get($db,'shop_email_from_name','')) ?>" placeholder="např. Můj E-shop">
    </div>

</div>

<hr style="margin:16px 0;">
<h4 style="margin:0 0 10px;">SMTP (doporučeno)</h4>
<div class="hint" style="margin:0 0 10px;">Pokud necháš <strong>SMTP host</strong> prázdný, e-maily se odešlou přes PHP <code>mail()</code>.</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;">
    <div>
        <label>SMTP host</label>
        <input type="text" name="shop_smtp_host" value="<?= htmlspecialchars(shop_setting_get($db,'shop_smtp_host',''), ENT_QUOTES, 'UTF-8') ?>" placeholder="např. smtp.seznam.cz">
    </div>
    <div>
        <label>SMTP port</label>
        <input type="number" name="shop_smtp_port" value="<?= htmlspecialchars(shop_setting_get($db,'shop_smtp_port','587'), ENT_QUOTES, 'UTF-8') ?>" placeholder="587">
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px;">
    <div>
        <label>SMTP uživatel</label>
        <input type="text" name="shop_smtp_user" value="<?= htmlspecialchars(shop_setting_get($db,'shop_smtp_user',''), ENT_QUOTES, 'UTF-8') ?>" placeholder="např. obchod@domena.cz">
    </div>
    <div>
        <label>SMTP heslo</label>
        <input type="password" name="shop_smtp_pass" value="<?= htmlspecialchars(shop_setting_get($db,'shop_smtp_pass',''), ENT_QUOTES, 'UTF-8') ?>" placeholder="••••••••">
    </div>
</div>

<div style="max-width:220px;margin-top:8px;">
    <label>Šifrování</label>
    <?php $enc = shop_setting_get($db,'shop_smtp_encryption','tls'); ?>
    <select name="shop_smtp_encryption">
        <option value="none" <?= ($enc==='none')?'selected':'' ?>>Žádné</option>
        <option value="tls" <?= ($enc==='tls')?'selected':'' ?>>TLS (STARTTLS)</option>
        <option value="ssl" <?= ($enc==='ssl')?'selected':'' ?>>SSL</option>
    </select>
</div>

<div style="margin-top:10px;">

    <div class="hint">Placeholdery: <code>{{order_number}}</code>, <code>{{status}}</code>, <code>{{status_old}}</code>, <code>{{total}}</code>, <code>{{items}}</code></div>
</div>

<h4 style="margin:12px 0 6px;">Nová objednávka (zákazník)</h4>
<label>Předmět</label>
<input type="text" name="shop_email_subject_new_customer" value="<?= htmlspecialchars(shop_setting_get($db,'shop_email_subject_new_customer','')) ?>">
<label>Šablona (HTML)</label>
<textarea name="shop_email_tpl_new_customer" rows="6" style="width:100%;"><?= htmlspecialchars(shop_setting_get($db,'shop_email_tpl_new_customer','')) ?></textarea>

<h4 style="margin:12px 0 6px;">Nová objednávka (admin)</h4>
<label>Předmět</label>
<input type="text" name="shop_email_subject_new_admin" value="<?= htmlspecialchars(shop_setting_get($db,'shop_email_subject_new_admin','')) ?>">
<label>Šablona (HTML)</label>
<textarea name="shop_email_tpl_new_admin" rows="6" style="width:100%;"><?= htmlspecialchars(shop_setting_get($db,'shop_email_tpl_new_admin','')) ?></textarea>

<h4 style="margin:12px 0 6px;">Změna stavu (zákazník)</h4>
<label>Předmět</label>
<input type="text" name="shop_email_subject_status_customer" value="<?= htmlspecialchars(shop_setting_get($db,'shop_email_subject_status_customer','')) ?>">
<label>Šablona (HTML)</label>
<textarea name="shop_email_tpl_status_customer" rows="6" style="width:100%;"><?= htmlspecialchars(shop_setting_get($db,'shop_email_tpl_status_customer','')) ?></textarea>

<h4 style="margin:12px 0 6px;">Změna stavu (admin)</h4>
<label>Předmět</label>
<input type="text" name="shop_email_subject_status_admin" value="<?= htmlspecialchars(shop_setting_get($db,'shop_email_subject_status_admin','')) ?>">
<label>Šablona (HTML)</label>
<textarea name="shop_email_tpl_status_admin" rows="4" style="width:100%;"><?= htmlspecialchars(shop_setting_get($db,'shop_email_tpl_status_admin','')) ?></textarea>

<hr style="margin:16px 0;">
<h3 style="margin:0 0 10px;">Faktura (PDF)</h3>

<label>Prodejce – název</label>
<input type="text" name="invoice_seller_name" value="<?= htmlspecialchars(shop_setting_get($db,'invoice_seller_name','')) ?>">

<label>Prodejce – adresa řádek 1</label>
<input type="text" name="invoice_seller_addr1" value="<?= htmlspecialchars(shop_setting_get($db,'invoice_seller_addr1','')) ?>">
<label>Prodejce – adresa řádek 2</label>
<input type="text" name="invoice_seller_addr2" value="<?= htmlspecialchars(shop_setting_get($db,'invoice_seller_addr2','')) ?>">
<label>Prodejce – adresa řádek 3</label>
<input type="text" name="invoice_seller_addr3" value="<?= htmlspecialchars(shop_setting_get($db,'invoice_seller_addr3','')) ?>">

<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
    <div>
        <label>IČO</label>
        <input type="text" name="invoice_seller_ico" value="<?= htmlspecialchars(shop_setting_get($db,'invoice_seller_ico','')) ?>">
    </div>
    <div>
        <label>DIČ</label>
        <input type="text" name="invoice_seller_dic" value="<?= htmlspecialchars(shop_setting_get($db,'invoice_seller_dic','')) ?>">
    </div>
</div>

<label>Bankovní údaje</label>
<input type="text" name="invoice_seller_bank" value="<?= htmlspecialchars(shop_setting_get($db,'invoice_seller_bank','')) ?>" placeholder="např. 123456789/2010">

<label>Poznámka na faktuře</label>
<textarea name="invoice_note" rows="3" style="width:100%;"><?= htmlspecialchars(shop_setting_get($db,'invoice_note','')) ?></textarea>

<div style="margin-top:12px;">
                <button class="btn" type="submit">Uložit</button>
            </div>
        </form>

    <?php endif; ?>

</div>
</body>
</html>
