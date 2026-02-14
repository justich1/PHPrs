<?php
/**
 * Hlavní soubor pluginu pro Cookie Bar
 */

// Načtení pomocných funkcí
require_once __DIR__ . '/functions.php';

// === REGISTRACE HOOKŮ SYSTÉMU ===

// Funkce, která se spustí při aktivaci pluginu
register_activation_hook(__FILE__, 'cookie_bar_activate');

// Funkce, která se spustí při odinstalaci pluginu
register_uninstall_hook(__FILE__, 'cookie_bar_uninstall');

// Připojení naší funkce k patičce webu
add_action('footer_end', 'cookie_bar_render');


// === FUNKCE PRO INSTALACI A ODINSTALACI ===

/**
 * Vytvoří potřebné zázemí v databázi při aktivaci pluginu.
 */
function cookie_bar_activate() {
    // Připojení k databázi (předpokládá existenci funkce db_connect())
    $db = db_connect();

    // Vytvoření tabulky pro nastavení, pokud neexistuje
    $db->exec("
        CREATE TABLE IF NOT EXISTS `plugin_cookie_settings` (
            `setting_key` VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    
    // Vložení výchozích nastavení (pouze pokud ještě neexistují)
    $default_settings = [
        'cookie_bar_text' => 'Tento web používá soubory cookie k poskytování služeb, personalizaci reklam a analýze návštěvnosti. Používáním tohoto webu s tím souhlasíte.',
        'cookie_policy_slug' => 'ochrana-osobnich-udaju'
    ];
    
    $stmt = $db->prepare("INSERT IGNORE INTO `plugin_cookie_settings` (setting_key, setting_value) VALUES (?, ?)");
    foreach ($default_settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}

/**
 * Smaže tabulku pluginu při jeho odinstalaci.
 */
function cookie_bar_uninstall() {
    $db = db_connect();
    $db->exec("DROP TABLE IF EXISTS `plugin_cookie_settings`;");
}
