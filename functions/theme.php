<?php
// functions/theme.php - Funkce pro práci s nastavením vzhledu

/**
 * Načte hodnotu nastavení vzhledu z databáze.
 * Pokud hodnota neexistuje, vrátí výchozí hodnotu.
 *
 * @param string $name Název volby.
 * @param mixed $default Výchozí hodnota.
 * @return mixed Hodnota z databáze nebo výchozí.
 */
function get_theme_option($name, $default = '') {
    // Statická proměnná zajistí, že se všechna nastavení načtou z DB pouze jednou.
    static $options = null;

    if ($options === null) {
        try {
            $db = db_connect();
            $options = $db->query("SELECT option_name, option_value FROM theme_options")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\PDOException $e) {
            $options = []; // V případě chyby použijeme prázdné pole
        }
    }

    return $options[$name] ?? $default;
}
