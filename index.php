<?php
// index.php - Srdce celé aplikace

$config_file = __DIR__ . '/config/config.php';

if (!file_exists($config_file)) {
    // Pokud existuje instalační soubor, přesměrujeme na něj.
    if (file_exists(__DIR__ . '/install.php')) {
        header('Location: install.php');
        exit;
    } else {
        // Kritická chyba, pokud chybí config i instalátor.
        die('CHYBA: Konfigurační soubor nebyl nalezen a instalační skript chybí. Systém nelze spustit.');
    }
}

// --- DOČASNÉ LADĚNÍ PRO CHYBU 500 ---
// Zapne zobrazení všech PHP chyb. Po opravě můžete tyto 2 řádky smazat.
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
// --- KONEC LADĚNÍ ---

session_start();

// 1. Načtení všech potřebných souborů
require_once 'config/config.php';
require_once 'functions/database.php';
require_once 'functions/pages.php';
require_once 'functions/plugins.php';
require_once 'functions/menus.php';
require_once 'functions/theme.php';
require_once 'functions/shortcodes.php';
require_once 'functions/widgets.php';

// 2. Zpracování URL a nastavení jazyka
$current_lang = $_GET['lang'] ?? DEFAULT_LANG;

// OPRAVA: Pokud není zadána stránka v URL, načteme výchozí slug z databáze
if (empty($_GET['page'])) {
    $home_slugs = get_homepage_slugs(); // Získá pole slugů pro hlavní stránky napříč jazyky
    $page_slug = $home_slugs[$current_lang] ?? 'domu'; // Fallback na 'domu', pokud není v DB nic definováno
} else {
    $page_slug = $_GET['page'];
}

// 3. Načtení a spuštění pluginů
load_active_plugins();

// 4. Načtení dat z databáze
$page_data = get_page_by_slug($page_slug, $current_lang);
$menu_items = get_menu_items('header', $current_lang);

if (!$page_data) {
    http_response_code(404);
    $page_data = [
        'id' => 0,
        'title' => 'Chyba 404 - Stránka nenalezena',
        'content' => 'Litujeme, ale požadovaná stránka na tomto webu neexistuje.',
        'slug' => ''
    ];
}

// 5. Sestavení cesty k souborům aktivního vzhledu
$theme_path = 'themes/' . ACTIVE_THEME . '/';

// --- Vykreslení finální stránky ---
include $theme_path . 'header.php';
include $theme_path . 'page.php';
include $theme_path . 'footer.php';