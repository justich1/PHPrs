<?php
// functions/plugins.php - Pokročilý systém pro správu pluginů

$hooks = [];
$plugin_shortcodes = []; // Nové pole pro shortcody z pluginů

function add_action($hook_name, $callable) { global $hooks; $hooks[$hook_name][] = $callable; }
function do_action($hook_name, $args = null) {
    global $hooks;
    if (isset($hooks[$hook_name])) {
        foreach ($hooks[$hook_name] as $callable) {
            call_user_func($callable, $args);
        }
    }
}

/**
 * Zaregistruje shortcode pro plugin.
 * @param string $tag Název shortcodu (bez závorek).
 * @param callable $callable Funkce, která se má zavolat pro vykreslení.
 */
function add_shortcode($tag, $callable) {
    global $plugin_shortcodes;
    if (is_callable($callable)) {
        $plugin_shortcodes[$tag] = $callable;
    }
}

// ... zbytek souboru (load_active_plugins, get_all_plugins, atd.) zůstává stejný ...
function register_activation_hook($plugin_file, $callable) { add_action('plugin_activation_' . basename(dirname($plugin_file)), $callable); }
function register_deactivation_hook($plugin_file, $callable) { add_action('plugin_deactivation_' . basename(dirname($plugin_file)), $callable); }
function register_uninstall_hook($plugin_file, $callable) { add_action('plugin_uninstall_' . basename(dirname($plugin_file)), $callable); }

function load_active_plugins() {
    try {
        $db = db_connect();
        $stmt = $db->query("SELECT plugin_folder FROM plugin_status WHERE is_active = 1");
        $active_plugins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($active_plugins as $plugin_folder) {
            $main_file = __DIR__ . '/../plugins/' . $plugin_folder . '/plugin.php';
            if (file_exists($main_file)) {
                require_once $main_file;
            }
        }
    } catch (\PDOException $e) { return; }
}

function get_all_plugins() {
    $plugins_dir = __DIR__ . '/../plugins';
    $plugin_folders = array_diff(scandir($plugins_dir), ['.', '..']);
    $all_plugins = [];
    try {
        $db = db_connect();
        $stmt = $db->query("SELECT plugin_folder, is_active FROM plugin_status");
        $statuses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (\PDOException $e) { $statuses = []; }
    foreach ($plugin_folders as $folder) {
        $plugin_path = $plugins_dir . '/' . $folder;
        if (is_dir($plugin_path)) {
            $info = ['folder' => $folder, 'name' => $folder, 'description' => '', 'settings_url' => null];
            $json_file = $plugin_path . '/plugin.json';
            if (file_exists($json_file)) {
                $json_data = json_decode(file_get_contents($json_file), true);
                $info['name'] = $json_data['name'] ?? $folder;
                $info['description'] = $json_data['description'] ?? '';
                $info['settings_url'] = $json_data['settings_page'] ?? null;
            }
            $info['is_active'] = isset($statuses[$folder]) && $statuses[$folder] == 1;
            $all_plugins[$folder] = $info;
        }
    }
    return $all_plugins;
}

function activate_plugin($plugin_folder) {
    $db = db_connect();
    $stmt = $db->prepare("INSERT INTO plugin_status (plugin_folder, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1");
    $stmt->execute([$plugin_folder]);
    $main_file = __DIR__ . '/../plugins/' . $plugin_folder . '/plugin.php';
    if (file_exists($main_file)) {
        require_once $main_file;
        do_action('plugin_activation_' . $plugin_folder);
    }
}

function deactivate_plugin($plugin_folder) {
    $db = db_connect();
    $stmt = $db->prepare("UPDATE plugin_status SET is_active = 0 WHERE plugin_folder = ?");
    $stmt->execute([$plugin_folder]);
    $main_file = __DIR__ . '/../plugins/' . $plugin_folder . '/plugin.php';
    if (file_exists($main_file)) {
        require_once $main_file;
        do_action('plugin_deactivation_' . $plugin_folder);
    }
}

function delete_plugin($plugin_folder) {
    $plugins_dir = __DIR__ . '/../plugins/';
    $plugin_path = $plugins_dir . $plugin_folder;
    $main_file = $plugin_path . '/plugin.php';
    if (file_exists($main_file)) {
        require_once $main_file;
        do_action('plugin_uninstall_' . $plugin_folder);
    }
    if (is_dir($plugin_path)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($plugin_path);
    }
    $db = db_connect();
    $stmt = $db->prepare("DELETE FROM plugin_status WHERE plugin_folder = ?");
    $stmt->execute([$plugin_folder]);
}
