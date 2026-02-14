<?php
/**
 * Hlavní soubor pluginu pro správu uživatelů
 */

// --- OŠETŘENÍ ZÁVISLOSTÍ ---
// Dynamicky definujeme SITE_URL, pokud ji systém již nedefinoval.
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('SITE_URL', $protocol . $host);
}

// Musíme spustit session, abychom s ní mohli pracovat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Zpracování odhlášení klienta
if (isset($_GET['logout'])) {
    // Zničí všechny proměnné v session
    $_SESSION = [];

    // Zničí samotnou session
    session_destroy();

    // Přesměruje uživatele na stejnou stránku, ale bez parametru ?logout=1
    // aby se při obnovení stránky znovu neodhlásil.
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Načtení pomocných funkcí a logiky shortcodů
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/shortcodes.php';

// === REGISTRACE HOOKŮ SYSTÉMU ===

register_activation_hook(__FILE__, 'user_plugin_activate');
register_uninstall_hook(__FILE__, 'user_plugin_uninstall');

add_shortcode('registrace', 'user_plugin_render_registration_shortcode');
add_shortcode('profil', 'user_plugin_render_profile_shortcode');
add_shortcode('prihlaseni', 'user_plugin_render_login_shortcode');
add_shortcode('reset_hesla', 'user_plugin_render_password_reset_shortcode');
add_shortcode('aktivace', 'user_plugin_render_activation_shortcode');


// === FUNKCE PRO INSTALACI A ODINSTALACI ===

/**
 * Vytvoří potřebné tabulky v databázi při aktivaci pluginu.
 */
function user_plugin_activate() {
    try {
        $db = db_connect();
        $db->exec("
            CREATE TABLE IF NOT EXISTS `plugin_clients` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `first_name` VARCHAR(100) NULL,
                `last_name` VARCHAR(100) NULL,
                `address` TEXT NULL,
                `phone` VARCHAR(50) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
                `activation_token` VARCHAR(255) NULL,
                `password_reset_token` VARCHAR(255) NULL,
                `password_reset_expires` DATETIME NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `plugin_client_settings` (
                `setting_key` VARCHAR(100) PRIMARY KEY,
                `setting_value` TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
        // Vložení výchozích nastavení
        $default_settings = [
            'registrations_enabled' => '1',
            'login_page_slug' => 'prihlaseni',
            'registration_page_slug' => 'registrace',
            'terms_page_slug' => 'obchodni-podminky',
            'activation_page_slug' => 'aktivace',
            'password_reset_page_slug' => 'reset-hesla',
            'email_activation_subject' => 'Aktivace účtu',
            'email_activation_body' => "Dobrý den,\nděkujeme za registraci. Pro aktivaci účtu klikněte na odkaz:\n{AKTIVACNI_ODKAZ}",
            'email_password_reset_subject' => 'Obnovení hesla',
            'email_password_reset_body' => "Dobrý den,\npro obnovení Vašeho hesla klikněte na následující odkaz:\n{RESET_ODKAZ}",
            'smtp_host' => '',
            'smtp_port' => '',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_encryption' => 'none'
        ];
        
        $stmt = $db->prepare("INSERT IGNORE INTO `plugin_client_settings` (setting_key, setting_value) VALUES (?, ?)");
        foreach ($default_settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }

    } catch (PDOException $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Chyba při aktivaci user pluginu: ' . $e->getMessage());
        }
    }
}

/**
 * Smaže tabulky pluginu při jeho odinstalaci.
 */
function user_plugin_uninstall() {
    try {
        $db = db_connect();
        $db->exec("DROP TABLE IF EXISTS `plugin_clients`, `plugin_client_settings`;");
    } catch (PDOException $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Chyba při odinstalaci user pluginu: ' . $e->getMessage());
        }
    }
}
