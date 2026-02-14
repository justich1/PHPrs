<?php
// functions/database.php - Funkce pro práci s databází

/**
 * Připojí se k databázi pomocí PDO a vrátí objekt připojení.
 * Používá konstanty definované v config.php.
 * @return PDO|null Objekt připojení nebo null v případě chyby.
 */
function db_connect() {
    // Statická proměnná zajistí, že se připojení vytvoří pouze jednou za běh skriptu
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Vyhazovat výjimky při chybách
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Vracet asociativní pole
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Používat nativní prepared statements
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            // V produkčním prostředí by zde mělo být logování, ne výpis chyby
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    return $pdo;
}
