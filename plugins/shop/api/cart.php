<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../functions/database.php';
$db = db_connect();

set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
try {

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$action = $_POST['action'] ?? ($_GET['action'] ?? 'get');


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
function shop_price_gross(PDO $db, float $net, int $vatClass): float {
    if (!shop_vat_enabled($db)) return $net;
    $p = shop_vat_percent_for_class($db,$vatClass);
    return $net * (1.0 + ($p/100.0));
}

function cart_payload(PDO $db): array {
    $vatEnabled = shop_vat_enabled($db);

    $count = 0;
    $sumNet = 0.0;
    $sumVat = 0.0;
    $sumGross = 0.0;
    $groups = []; // percent => ['base'=>..,'vat'=>..]

    $cartOut = [];
    foreach ($_SESSION['cart'] as $k=>$it) {
        $qty = (int)($it['quantity'] ?? 0);
        if ($qty <= 0) continue;

        $priceNet = (float)($it['price'] ?? 0);
        $vatClass = (int)($it['vat_class'] ?? 0);
        $vatPct = ($vatEnabled ? shop_vat_percent_for_class($db, $vatClass) : 0.0);

        $lineNet = $priceNet * $qty;
        $lineVat = ($vatPct > 0 ? ($lineNet * ($vatPct/100.0)) : 0.0);
        $lineGross = $lineNet + $lineVat;
        $priceGross = $priceNet + ($vatPct > 0 ? ($priceNet * ($vatPct/100.0)) : 0.0);

        $count += $qty;
        $sumNet += $lineNet;
        $sumVat += $lineVat;
        $sumGross += $lineGross;

        if ($vatPct > 0) {
            $key = (string)$vatPct;
            if (!isset($groups[$key])) $groups[$key] = ['percent'=>$vatPct,'base'=>0.0,'vat'=>0.0];
            $groups[$key]['base'] += $lineNet;
            $groups[$key]['vat']  += $lineVat;
        }

        $it['price_net'] = $priceNet;
        $it['price_gross'] = $priceGross;
        $it['vat_percent'] = $vatPct;
        $it['line_net'] = $lineNet;
        $it['line_vat'] = $lineVat;
        $it['line_gross'] = $lineGross;
        $cartOut[$k] = $it;
    }

    // normalize + sort groups by percent desc
    $vats = array_values($groups);
    usort($vats, fn($a,$b)=>($b['percent'] <=> $a['percent']));

    $summary = [
        'count'=>$count,
        // pro zpětnou kompatibilitu
        'total'=>$sumGross,
        // nové položky
        'vat_enabled'=>$vatEnabled ? 1 : 0,
        'total_net'=>$sumNet,
        'total_vat'=>$sumVat,
        'total_gross'=>$sumGross,
        'vats'=>$vats,
    ];

    return ['cart'=>$cartOut, 'summary'=>$summary];
}

function cart_summary(PDO $db) {
    return cart_payload($db)['summary'];
}

if ($action === 'add') {
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'product_id']); exit; }

    $st = $db->prepare("SELECT id,name,price,stock,active,vat_class FROM products WHERE id=? AND active=1 LIMIT 1");
    $st->execute([$productId]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

    if (!isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = ['id'=>$productId,'name'=>$p['name'],'price'=>$p['price'],'vat_class'=>(int)($p['vat_class'] ?? 0),'quantity'=>0];
    }
    $_SESSION['cart'][$productId]['quantity'] += 1;

    $_out = cart_payload($db); echo json_encode(['ok'=>true,'cart'=>$_out['cart'],'summary'=>$_out['summary']]);
    exit;
}

if ($action === 'update') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);
    if ($productId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'product_id']); exit; }

    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
    } else if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] = $qty;
    }
    $_out = cart_payload($db); echo json_encode(['ok'=>true,'cart'=>$_out['cart'],'summary'=>$_out['summary']]);
    exit;
}

if ($action === 'remove') {
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId>0) unset($_SESSION['cart'][$productId]);
    $_out = cart_payload($db); echo json_encode(['ok'=>true,'cart'=>$_out['cart'],'summary'=>$_out['summary']]);
    exit;
}

if ($action === 'clear') {
    $_SESSION['cart'] = [];
    $_out = cart_payload($db); echo json_encode(['ok'=>true,'cart'=>$_out['cart'],'summary'=>$_out['summary']]);
    exit;
}

// get
$_out = cart_payload($db); echo json_encode(['ok'=>true,'cart'=>$_out['cart'],'summary'=>$_out['summary']]);

} catch (Throwable $e) {
    http_response_code(500);
    // Keep JSON output even on fatals/warnings, otherwise JS shows 'Bad JSON'
    $msg = 'server_error';
    if ($e instanceof PDOException) {
        if (strpos($e->getMessage(),'Base table') !== false || strpos($e->getMessage(),'42S02') !== false) $msg = 'not_installed';
    }
    echo json_encode(['ok'=>false,'error'=>$msg]);
    exit;
}