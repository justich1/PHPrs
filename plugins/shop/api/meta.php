<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../functions/database.php';
$db = db_connect();

set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
try {

function shop_setting_get(PDO $db, string $key, $default='') {
    $st = $db->prepare("SELECT setting_value FROM shop_settings WHERE setting_key=? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? $default : $v;
}

$shipping = $db->query("SELECT id,name,price,description FROM shipping_methods WHERE active=1 ORDER BY id ASC")
              ->fetchAll(PDO::FETCH_ASSOC);
$accounts = $db->query("SELECT id,account_name,account_number,bank_code FROM payment_accounts WHERE active=1 ORDER BY id ASC")
              ->fetchAll(PDO::FETCH_ASSOC);


$client = ['logged_in' => false];
if (isset($_SESSION['client_user_id'])) {
    try {
        $stU = $db->prepare("SELECT id,email,first_name,last_name,street,street_no,city,zip,phone,contact_email,address FROM plugin_clients WHERE id=? LIMIT 1");
        $stU->execute([$_SESSION['client_user_id']]);
        $u = $stU->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $u['logged_in'] = true;
            $client = $u;
        }
    } catch (Throwable $e) {
        // plugin_users not installed or table missing - ignore
    }
}

echo json_encode([
    'ok' => true,
    'currency' => shop_setting_get($db,'shop_currency','CZK'),
    'cod_fee' => (float)shop_setting_get($db,'shop_cod_fee','0'),
    'shop_base_path' => shop_setting_get($db,'shop_base_path',''),
    'default_payment_account_id' => (int)shop_setting_get($db,'default_payment_account_id','0'),
    'shipping_methods' => $shipping,
    'payment_accounts' => $accounts,
    'client' => $client,
]);

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