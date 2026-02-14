<?php
/**
 * get_shortcodes.php - UNIVERZÁLNÍ VERZE
 *
 * Tento skript načte aktivní pluginy a pro každý z nich zkusí najít
 * soubor 'shortcode-provider.php'. Pokud existuje, načte z něj
 * dynamicky vygenerovaný seznam shortcodů.
 */

// Nastavení pro ladění
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Načtení potřebných souborů
require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/shortcodes.php';

// Záslepky pro funkce, které pluginy mohou volat (kvůli kompatibilitě)
global $plugin_shortcodes;
$plugin_shortcodes = [];
if (!function_exists('add_shortcode')) { function add_shortcode($tag, $callback) { global $plugin_shortcodes; $plugin_shortcodes[$tag] = $callback; } }
if (!function_exists('register_activation_hook')) { function register_activation_hook($file, $callback) {} }
if (!function_exists('register_uninstall_hook')) { function register_uninstall_hook($file, $callback) {} }
if (!function_exists('add_action')) { function add_action($tag, $callback) {} }

$all_shortcodes = [];

try {
    $db = db_connect();
    $stmt = $db->query("SELECT plugin_folder FROM plugin_status WHERE is_active = 1");
    $active_plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($active_plugins) {
        foreach ($active_plugins as $plugin) {
            // Cesta k univerzálnímu provider souboru v každém pluginu
            $provider_path = '../plugins/' . $plugin['plugin_folder'] . '/shortcode-provider.php';

            // Pokud plugin takový soubor má, načteme ho
            if (file_exists($provider_path)) {
                // 'include' vrátí hodnotu, kterou vrací soubor pomocí 'return'
                $provided_shortcodes = include $provider_path;
                
                if (is_array($provided_shortcodes)) {
                    // Sloučíme pole z provideru s naším hlavním polem
                    $all_shortcodes = array_merge($all_shortcodes, $provided_shortcodes);
                }
            } else {
                // ZPĚTNÁ KOMPATIBILITA: Pokud plugin nemá provider, zkusíme načíst jeho hlavní soubor
                // a najít shortcody registrované přes add_shortcode()
                $plugin_path = '../plugins/' . $plugin['plugin_folder'] . '/plugin.php';
                 if (file_exists($plugin_path)) {
                    require_once $plugin_path;
                }
            }
        }
    }
    
    // Zpracujeme shortcody z pluginů bez provideru
     if (!empty($plugin_shortcodes)) {
        foreach ($plugin_shortcodes as $tag => $callback) {
             $all_shortcodes["[$tag]"] = "Plugin: $tag";
        }
    }

    // Zpracování shortcodů z databáze (starý systém)
    $db_shortcodes = get_all_shortcodes();
    if ($db_shortcodes) {
        foreach ($db_shortcodes as $shortcode) {
            $tag = $shortcode['name'];
            $all_shortcodes["[$tag]"] = "Databáze: $tag";
        }
    }

    asort($all_shortcodes);

} catch (Exception $e) {
    http_response_code(500);
    $all_shortcodes = ['error' => 'Chyba serveru: ' . $e->getMessage()];
}

ini_set('display_errors', 0);
header('Content-Type: application/json');
echo json_encode($all_shortcodes);
exit;