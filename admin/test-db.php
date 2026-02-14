<?php
// Soubor: db_test.php (umístěte ho do kořenového adresáře vedle index.php)

// Načteme jen konfiguraci
require_once '../config/config.php';

echo "<h1>Test připojení k databázi</h1>";
echo "Pokouším se připojit s následujícími údaji:<br>";
echo "<strong>Host:</strong> " . DB_HOST . "<br>";
echo "<strong>Název DB:</strong> " . DB_NAME . "<br>";
echo "<strong>Uživatel:</strong> " . DB_USER . "<br><hr>";

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2 style='color: green;'>Úspěšně připojeno!</h2>";
    echo "Vaše databáze je v pořádku a údaje v `config.php` jsou správné.";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Chyba připojení!</h2>";
    echo "<p>Databáze vrátila následující chybu:</p>";
    echo "<pre style='background: #eee; padding: 10px; border: 1px solid #ccc;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Zkontrolujte prosím, že údaje v souboru <strong>config/config.php</strong> jsou 100% správné a že databáze a uživatel existují.</p>";
}
?>
