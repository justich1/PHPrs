<?php
/**
 * Hlavní soubor pluginu pro Cookie Bar
 * Verze 3.0 - Robustní opravená verze
 */

// Načtení všech funkcí pluginu
require_once __DIR__ . '/functions.php';

// === REGISTRACE HOOKŮ SYSTÉMU ===
// Tento kód se spustí pouze tehdy, když je plugin načítán hlavním systémem.

/**
 * Funkce, která se spustí při aktivaci pluginu.
 */
function cookie_bar_activate_plugin() {
    cookie_bar_ensure_db_table();
}

/**
 * Funkce, která se spustí při odinstalaci pluginu.
 */
function cookie_bar_uninstall_plugin() {
    try {
        $db = db_connect();
        $db->exec("DROP TABLE IF EXISTS `plugin_cookie_settings`;");
    } catch (PDOException $e) {
        // Chybu je možné zalogovat
    }
}

// Registrace samotných hooků
register_activation_hook(__FILE__, 'cookie_bar_activate_plugin');
register_uninstall_hook(__FILE__, 'cookie_bar_uninstall_plugin');
add_action('footer_end', 'cookie_bar_render_html');
