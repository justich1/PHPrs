<?php
// PDF faktura - stažení
session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../functions/database.php';
$db = db_connect();

/* =======================
   Helpers
   ======================= */

function shop_setting_get(PDO $db, string $key, $default='') {
    $st = $db->prepare("SELECT setting_value FROM shop_settings WHERE setting_key=? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? $default : $v;
}

function shop_invoice_secret(PDO $db): string {
    $s = trim((string)shop_setting_get($db, 'shop_invoice_secret', ''));
    if ($s !== '') return $s;
    if (defined('APP_SECRET') && APP_SECRET) return (string)APP_SECRET;
    if (defined('SECRET_KEY') && SECRET_KEY) return (string)SECRET_KEY;
    return '';
}

function shop_invoice_token(PDO $db, array $order): string {
    $secret = shop_invoice_secret($db);
    if ($secret === '') return '';
    $data = (string)($order['id'] ?? '') . '|' . (string)($order['order_number'] ?? '') . '|' . (string)($order['email'] ?? '');
    return hash_hmac('sha256', $data, $secret);
}

function shop_ascii(string $s): string {
    $s = (string)$s;
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) $s = $t;
    $s = preg_replace('/[\x00-\x1F\x7F]/', ' ', $s);
    return trim($s);
}

function pdf_escape(string $s): string {
    return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $s);
}

/* =======================
   Input + load order
   ======================= */

$order_id = (int)($_GET['order_id'] ?? ($_GET['id'] ?? 0));
$token    = (string)($_GET['token'] ?? '');

if ($order_id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "missing_order_id";
    exit;
}

$st = $db->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
$st->execute([$order_id]);
$order = $st->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "order_not_found";
    exit;
}

/* =======================
   Auth
   ======================= */

// Admin session (u tebe může být jiné – pokud máš jiný flag, uprav)
$adminOk = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;

$customerOk = false;
if (isset($_SESSION['client_user_id']) && (int)$_SESSION['client_user_id'] > 0) {
    $customerOk = ((int)$_SESSION['client_user_id'] === (int)($order['user_id'] ?? 0));
}

$tokenOk = false;
if ($token !== '') {
    $expected = shop_invoice_token($db, $order);
    $tokenOk = ($expected !== '' && hash_equals($expected, $token));
}

if (!$adminOk && !$customerOk && !$tokenOk) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "forbidden";
    exit;
}

/* =======================
   Settings + availability rules
   ======================= */

$allowed = trim((string)shop_setting_get($db, 'invoice_allowed_statuses', 'paid,processing,shipped,delivered'));
$allowedArr = array_values(array_filter(array_map('trim', explode(',', $allowed))));

$status = (string)($order['status'] ?? 'new');
$paidAt = trim((string)($order['paid_at'] ?? ''));
$pm     = (string)($order['payment_method'] ?? '');

$allowedByStatus = in_array($status, $allowedArr, true);

// "po zaplacení" – nejjistější je paid_at
$allowedByPaidAt = ($paidAt !== '');

// volitelně: pro dobírku / platbu při převzetí povolit i bez paid_at
$allowOnHandoverPayments = true; // pokud nechceš, dej false
$allowedByPayment = $allowOnHandoverPayments && ($pm === 'cod' || $pm === 'cash');

if (!$allowedByPaidAt && !$allowedByStatus && !$allowedByPayment) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');

    // lepší hláška + info pro admina
    $msg = "Faktura je dostupná až po zaplacení objednávky.";
    if ($adminOk) {
        $msg .= " (stav=" . $status . ", paid_at=" . ($paidAt !== '' ? $paidAt : 'NULL') . ", allowed=" . $allowed . ")";
    }
    echo $msg;
    exit;
}

/* =======================
   Debug
   ======================= */

if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    header('Content-Type: text/plain; charset=utf-8');

    echo "=== INVOICE DEBUG ===\n";
    echo "GET: " . var_export($_GET, true) . "\n\n";
    echo "order_id(parsed): " . $order_id . "\n";
    echo "order_number: " . ($order['order_number'] ?? '') . "\n";
    echo "status(DB): " . var_export($order['status'] ?? null, true) . "\n";
    echo "paid_at(DB): " . var_export($order['paid_at'] ?? null, true) . "\n";
    echo "payment_method(DB): " . var_export($order['payment_method'] ?? null, true) . "\n";
    echo "allowed setting(raw): " . var_export($allowed ?? null, true) . "\n";
    echo "allowedArr: " . var_export($allowedArr ?? null, true) . "\n";
    echo "allowedByPaidAt: " . ($allowedByPaidAt ? "YES" : "NO") . "\n";
    echo "allowedByStatus: " . ($allowedByStatus ? "YES" : "NO") . "\n";
    echo "allowedByPayment: " . ($allowedByPayment ? "YES" : "NO") . "\n";
    exit;
}

/* =======================
   Load items
   ======================= */

$stI = $db->prepare("SELECT oi.quantity, oi.price, oi.vat_percent, oi.vat_amount, p.name
                     FROM order_items oi
                     LEFT JOIN products p ON p.id = oi.product_id
                     WHERE oi.order_id=? ORDER BY oi.id ASC");
$stI->execute([$order_id]);
$items = $stI->fetchAll(PDO::FETCH_ASSOC);

$sumItemsNet = 0.0;
$sumItemsVat = 0.0;
$sumItemsGross = 0.0;

foreach ($items as $it) {
    $qty = (int)($it['quantity'] ?? 0);
    $net = ((float)($it['price'] ?? 0)) * $qty;
    $vat = (float)($it['vat_amount'] ?? 0);
    $sumItemsNet += $net;
    $sumItemsVat += $vat;
    $sumItemsGross += ($net + $vat);
}

$shipping = (float)($order['shipping_price'] ?? 0);

// poplatek dobírky dopočítáme z uložené celkové částky (zpětná kompatibilita)
$codFee = 0.0;
if (($order['payment_method'] ?? '') === 'cod') {
    $codFee = (float)($order['total_price'] ?? 0) - $shipping - $sumItemsGross;
    if ($codFee < 0.01) $codFee = 0.0;
}

// celkové součty (preferujeme uložené total_net/total_vat, když existují)
$totalNet = (float)($order['total_net'] ?? 0);
$totalVat = (float)($order['total_vat'] ?? 0);
if ($totalNet <= 0.0 && $totalVat <= 0.0) {
    $totalNet = $sumItemsNet + $shipping + $codFee;
    $totalVat = $sumItemsVat;
}
$totalGross = $totalNet + $totalVat;

$seller = [
    'name'  => (string)shop_setting_get($db,'invoice_seller_name',''),
    'addr1' => (string)shop_setting_get($db,'invoice_seller_addr1',''),
    'addr2' => (string)shop_setting_get($db,'invoice_seller_addr2',''),
    'addr3' => (string)shop_setting_get($db,'invoice_seller_addr3',''),
    'ico'   => (string)shop_setting_get($db,'invoice_seller_ico',''),
    'dic'   => (string)shop_setting_get($db,'invoice_seller_dic',''),
    'bank'  => (string)shop_setting_get($db,'invoice_seller_bank',''),
    'note'  => (string)shop_setting_get($db,'invoice_note',''),
];

/* =======================
   Invoice meta (VS/DUZP/splatnost)
   ======================= */

$vs = trim((string)($order['fio_variable_symbol'] ?? ''));
if ($vs === '') $vs = (string)$order_id;

$issueDate = (string)($order['created_at'] ?? date('Y-m-d H:i:s'));
$issueTs = strtotime($issueDate) ?: time();

$dueDays = (int)shop_setting_get($db, 'invoice_due_days', '14');
$dueTs = strtotime('+' . $dueDays . ' days', $issueTs);
$dueDate = date('Y-m-d', $dueTs);

$duzp = !empty($order['paid_at']) ? (string)$order['paid_at'] : $issueDate;

/* =======================
   Build nicer PDF
   ======================= */

function pdf_build_invoice_pretty(array $order, array $items, array $seller, float $sumItemsGross, float $shipping, float $codFee, float $totalNet, float $totalVat, float $totalGross, string $vs, string $duzp, string $dueDate): string {
    // A4: 595 x 842 points
    $w = 595; $h = 842;

    $content = [];

    $txt = function(float $x, float $y, string $text, int $size=11, string $font='F1') use (&$content) {
        $t = pdf_escape(shop_ascii($text));
        $content[] = "BT /{$font} {$size} Tf {$x} {$y} Td ({$t}) Tj ET";
    };

    $line = function(float $x1, float $y1, float $x2, float $y2, float $width=1) use (&$content) {
        $content[] = "{$width} w {$x1} {$y1} m {$x2} {$y2} l S";
    };

    $rect = function(float $x, float $y, float $rw, float $rh, float $lw=1) use (&$content) {
        $content[] = "{$lw} w {$x} {$y} {$rw} {$rh} re S";
    };

    // Header
    $txt(40, 800, "FAKTURA", 22, 'F2');
    $txt(40, 780, "Cislo: " . (string)($order['order_number'] ?? ''), 11, 'F1');

    $txt(360, 800, "Vystaveno: " . substr((string)($order['created_at'] ?? ''), 0, 10), 11, 'F1');
    $txt(360, 784, "DUZP: " . substr($duzp, 0, 10), 11, 'F1');
    $txt(360, 768, "Splatnost: " . $dueDate, 11, 'F1');
    $txt(360, 752, "VS: " . $vs, 11, 'F1');

    $line(40, 742, 555, 742, 1);

    // Seller / Buyer boxes
$rect(40, 610, 255, 120, 1);
$rect(300, 610, 255, 120, 1);

    $txt(48, 718, "Dodavatel", 12, 'F2');
    $yy = 696;
    if (!empty($seller['name'])) { $txt(48, $yy, $seller['name'], 11, 'F1'); $yy -= 14; }
    foreach (['addr1','addr2','addr3'] as $k) { if (!empty($seller[$k])) { $txt(48, $yy, $seller[$k], 10, 'F1'); $yy -= 13; } }
    if (!empty($seller['ico'])) { $txt(48, $yy, "ICO: " . $seller['ico'], 10, 'F1'); $yy -= 13; }
    if (!empty($seller['dic'])) { $txt(48, $yy, "DIC: " . $seller['dic'], 10, 'F1'); $yy -= 13; }

    $txt(308, 718, "Odberatel", 12, 'F2');
    $yy = 696;
    if (!empty($order['name'])) { $txt(308, $yy, (string)$order['name'], 11, 'F1'); $yy -= 14; }
    foreach (['adress1','adress2','adress3'] as $k) { if (!empty($order[$k])) { $txt(308, $yy, (string)$order[$k], 10, 'F1'); $yy -= 13; } }
    if (!empty($order['email'])) { $txt(308, $yy, "Email: " . (string)$order['email'], 10, 'F1'); $yy -= 13; }
    if (!empty($order['telephone'])) { $txt(308, $yy, "Tel: " . (string)$order['telephone'], 10, 'F1'); $yy -= 13; }

    // Table header
    $tableTop = 580;
    $rect(40, $tableTop-18, 515, 18, 1);
    $txt(48,  $tableTop-13, "Polozka", 11, 'F2');
    $txt(360, $tableTop-13, "Ks", 11, 'F2');
    $txt(420, $tableTop-13, "Cena/ks", 11, 'F2');
    $txt(505, $tableTop-13, "Celkem", 11, 'F2');

    $y = $tableTop - 32;
    $rowH = 16;

    $maxRowsY = 170; // aby se nám to nerozbilo na jedné stránce

    foreach ($items as $it) {
        if ($y < $maxRowsY) break;

        $name = (string)($it['name'] ?? 'Produkt');
        $qty  = (int)($it['quantity'] ?? 0);
        $priceNet= (float)($it['price'] ?? 0);
        $vatPercent = (float)($it['vat_percent'] ?? 0);
        $price= $priceNet;
        if ($vatPercent > 0) $price = $priceNet * (1.0 + ($vatPercent/100.0));
        $lineTotal = $qty * $price;

        // rozdělení dlouhého názvu
        $name = shop_ascii($name);
        $nameChunks = str_split($name, 55);
        $first = true;

        foreach ($nameChunks as $chunk) {
            if ($y < $maxRowsY) break;

            $rect(40, $y-4, 515, $rowH, 0.5);

            $txt(48, $y+2, $chunk, 10, 'F1');

            if ($first) {
                // čísla doprava: použijeme fixní x, text bude kratší
                $txt(365, $y+2, (string)$qty, 10, 'F1');
                $txt(430, $y+2, number_format($price, 2, '.', ''), 10, 'F1');
                $txt(505, $y+2, number_format($lineTotal, 2, '.', ''), 10, 'F1');
                $first = false;
            }
            $y -= $rowH;
        }
    }

    // Summary box
    $sumX = 320;
    $sumY = 120;
    $rect($sumX, $sumY, 235, 125, 1);

    $txt($sumX+10, $sumY+103, "Souhrn", 12, 'F2');

    $txt($sumX+10, $sumY+84, "Mezisoucet:", 11, 'F1');
    $txt($sumX+140, $sumY+84, number_format($sumItemsGross, 2, '.', '') . " CZK", 11, 'F1');

    $txt($sumX+10, $sumY+68, "Doprava:", 11, 'F1');
    $txt($sumX+140, $sumY+68, number_format($shipping, 2, '.', '') . " CZK", 11, 'F1');

    $yy = $sumY + 52;

    // DPH rozpis podle sazeb (pokud je více sazeb)
    $vatGroups = [];
    foreach ($items as $__it) {
        $pct = (float)($__it['vat_percent'] ?? 0);
        if ($pct <= 0.0) continue;
        $base = (float)($__it['price'] ?? 0) * (int)($__it['quantity'] ?? 0);
        $vat = (float)($__it['vat_amount'] ?? 0);
        $key = (string)$pct;
        if (!isset($vatGroups[$key])) $vatGroups[$key] = ['pct'=>$pct,'base'=>0.0,'vat'=>0.0];
        $vatGroups[$key]['base'] += $base;
        $vatGroups[$key]['vat']  += $vat;
    }
    $vatGroups = array_values($vatGroups);
    usort($vatGroups, fn($a,$b)=>($b['pct'] <=> $a['pct']));


    if ($codFee > 0.0) {
        $txt($sumX+10, $yy, "Dobirka:", 11, 'F1');
        $txt($sumX+140, $yy, number_format($codFee, 2, '.', '') . " CZK", 11, 'F1');
        $yy -= 16;
    }

    if ($totalVat > 0.009) {
        $txt($sumX+10, $yy, "Zaklad:", 11, 'F1');
        $txt($sumX+140, $yy, number_format($totalNet, 2, '.', '') . " CZK", 11, 'F1');
        $yy -= 16;

        $txt($sumX+10, $yy, "DPH:", 11, 'F1');
        $txt($sumX+140, $yy, number_format($totalVat, 2, '.', '') . " CZK", 11, 'F1');
        $yy -= 16;

        if ($vatGroups) {
            $vatGroups = array_values($vatGroups);
            usort($vatGroups, fn($a,$b)=>($b['pct']<=>$a['pct']));
            foreach ($vatGroups as $__g) {
                $pTxt = rtrim(rtrim(number_format((float)$__g['pct'], 2, '.', ''), '0'), '.');
                $txt($sumX+10, $yy, "DPH {$pTxt}%:", 9, 'F1');
                $txt($sumX+140, $yy, number_format((float)$__g['vat'], 2, '.', '') . " CZK", 9, 'F1');
                $yy -= 12;
                if ($yy < $sumY + 20) break;
            }
        }
    }

    $txt($sumX+10, $sumY+4, "Celkem:", 12, 'F2');
    $txt($sumX+140, $sumY+4, number_format($totalGross, 2, '.', '') . " CZK", 12, 'F2');

// Footer / note
    $footY = 100;
    $line(40, $footY+10, 555, $footY+10, 1);

    $bank = trim((string)($seller['bank'] ?? ''));
    if ($bank !== '') $txt(40, $footY-4, "Bankovni spojeni: " . $bank, 10, 'F1');

    $note = trim((string)($seller['note'] ?? ''));
    if ($note !== '') {
        $note = shop_ascii($note);
        $chunks = str_split($note, 95);
        $yy = $footY - 18;
        foreach ($chunks as $c) {
            $txt(40, $yy, $c, 9, 'F1');
            $yy -= 12;
            if ($yy < 30) break;
        }
    }

    $stream = implode("\n", $content);
    $len = strlen($stream);

    // PDF objects: add bold font F2
    $objs = [];
    $objs[] = "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj";
    $objs[] = "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj";
    $objs[] = "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$w} {$h}] /Resources<< /Font<< /F1 4 0 R /F2 6 0 R >> >> /Contents 5 0 R >>endobj";
    $objs[] = "4 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj";
    $objs[] = "5 0 obj<< /Length {$len} >>stream\n{$stream}\nendstream endobj";
    $objs[] = "6 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>endobj";

    $pdf = "%PDF-1.4\n%âãÏÓ\n";
    $offsets = [0];
    foreach ($objs as $o) {
        $offsets[] = strlen($pdf);
        $pdf .= $o . "\n";
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs)+1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i=1; $i<=count($objs); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer<< /Size " . (count($objs)+1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefPos}\n%%EOF";
    return $pdf;
}

$pdf = pdf_build_invoice_pretty($order, $items, $seller, $sumItemsGross, $shipping, $codFee, $totalNet, $totalVat, $totalGross, $vs, $duzp, $dueDate);

$fn = 'Faktura-' . preg_replace('/[^A-Za-z0-9\-_]/','_', (string)($order['order_number'] ?? $order_id)) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$fn.'"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
