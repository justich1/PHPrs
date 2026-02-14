<?php
// Párování Fio plateb s objednávkami (cron)
// Spouštěj např. každou hodinu.

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../functions/database.php';
$db = db_connect();

function shop_setting_get(PDO $db, string $key, $default='') {
    $st = $db->prepare("SELECT setting_value FROM shop_settings WHERE setting_key=? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null) ? $default : $v;
}

$fioApiToken = trim(shop_setting_get($db, 'fio_token', ''));
$daysBack = (int)shop_setting_get($db, 'fio_days_back', '30');

if ($fioApiToken === '') {
    echo "FIO token není nastaven (admin -> Nastavení).\n";
    exit(1);
}

$to = date('Y-m-d');
$from = date('Y-m-d', strtotime("-{$daysBack} days"));

$url = "https://fioapi.fio.cz/v1/rest/periods/$fioApiToken/$from/$to/transactions.json";

echo "FIO: stahuji transakce $from -> $to\n";

$json = @file_get_contents($url);
if ($json === false) {
    echo "Chyba: nelze stáhnout data z Fio.\n";
    exit(2);
}

$data = json_decode($json, true);
if (!is_array($data)) {
    echo "Chyba: neplatná odpověď.\n";
    exit(3);
}

$txs = $data['accountStatement']['transactionList']['transaction'] ?? [];
if (!is_array($txs)) $txs = [];

$paid = 0;

foreach ($txs as $tx) {
    $vs = (string)($tx['column5']['value'] ?? ''); // VS
    $amount = (float)($tx['column1']['value'] ?? 0);

    if ($vs === '' || $amount <= 0) continue;

    // Najdi objednávku čekající na platbu
    $st = $db->prepare("SELECT id, total_price, status, payment_method FROM orders
                        WHERE fio_variable_symbol=? AND payment_method='fio_qr' AND status IN ('new','processing')
                        ORDER BY id DESC LIMIT 1");
    $st->execute([$vs]);
    $o = $st->fetch(PDO::FETCH_ASSOC);
    if (!$o) continue;

    // jednoduchá kontrola částky (tolerance 0.01)
    if (abs(((float)$o['total_price']) - $amount) > 0.01) continue;

    $upd = $db->prepare("UPDATE orders SET status='paid', paid_at=NOW() WHERE id=?");
    $upd->execute([(int)$o['id']]);
    $paid++;
}

echo "Hotovo. Spárováno: $paid\n";
