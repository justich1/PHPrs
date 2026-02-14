<?php
/**
 * Instalační skript pro Redakční Systém
 *
 * Tento skript provede uživatele procesem instalace ve třech krocích:
 * 1. Kontrola prostředí
 * 2. Nastavení databáze
 * 3. Vytvoření administrátorského účtu
 *
 * Po úspěšné instalaci se skript pokusí sám smazat.
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- ZÁKLADNÍ NASTAVENÍ ---
$config_folder_path = 'config/';
$config_file_path = $config_folder_path . 'config.php';
$current_step = $_GET['step'] ?? 1;
$errors = [];
$success_message = '';

// --- KROKY INSTALACE ---

// Krok 1: Kontrola prostředí
if ($current_step == 1) {
    $php_version_ok = version_compare(PHP_VERSION, '8.0.0', '>=');
    $pdo_ok = extension_loaded('pdo_mysql');
    $config_writable = is_writable($config_folder_path);
    
    $all_ok = $php_version_ok && $pdo_ok && $config_writable;
}

// Krok 2: Zpracování formuláře pro databázi
if ($current_step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';

    if (empty($db_name) || empty($db_user)) {
        $errors[] = 'Název databáze a uživatelské jméno jsou povinné.';
    } else {
        // Test připojení k databázi
        try {
            $dsn = "mysql:host=$db_host;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Zkusíme vytvořit databázi, pokud neexistuje
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Uložíme údaje do session a přesměrujeme na další krok
            $_SESSION['install_db_config'] = [
                'host' => $db_host,
                'name' => $db_name,
                'user' => $db_user,
                'pass' => $db_pass,
            ];
            header('Location: install.php?step=3');
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Nepodařilo se připojit k databázi. Zkontrolujte údaje. Chyba: ' . $e->getMessage();
        }
    }
}

// Krok 3: Zpracování formuláře pro admina a finální instalace
if ($current_step == 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['install_db_config'])) {
        header('Location: install.php?step=2');
        exit;
    }

    $admin_user = $_POST['admin_user'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_pass = $_POST['admin_pass'] ?? '';

    if (empty($admin_user) || empty($admin_email) || empty($admin_pass)) {
        $errors[] = 'Všechny údaje pro administrátorský účet jsou povinné.';
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Zadejte platnou e-mailovou adresu.';
    } elseif (strlen($admin_pass) < 8) {
        $errors[] = 'Heslo musí mít alespoň 8 znaků.';
    } else {
        try {
            // 1. Vytvoření konfiguračního souboru
            $db_config = $_SESSION['install_db_config'];
            $config_content = "<?php
// config.php - Konfigurační soubor aplikace

// --- Připojení k databázi ---
define('DB_HOST', '{$db_config['host']}');
define('DB_NAME', '{$db_config['name']}');
define('DB_USER', '{$db_config['user']}');
define('DB_PASS', '{$db_config['pass']}');

// --- Nastavení systému ---
define('SITE_NAME', 'Můj nový web');
define('ACTIVE_THEME', 'sidebar_theme'); // Výchozí vzhled

// --- Nastavení vícejazyčnosti ---
define('DEFAULT_LANG', 'cs');
define('SUPPORTED_LANGS', ['cs' => 'Čeština']);
";
            if (!file_put_contents($config_file_path, $config_content)) {
                throw new Exception('Nepodařilo se zapsat konfigurační soubor. Zkontrolujte oprávnění složky /config.');
            }

            // 2. Připojení k databázi s vybraným DB
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_config['user'], $db_config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 3. Vytvoření tabulek
            $sql_schema = get_database_schema(); // Získáme SQL z funkce níže
            $pdo->exec($sql_schema);

            // 4. Vložení admin účtu
            $hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password`, `email`) VALUES (?, ?, ?)");
            $stmt->execute([$admin_user, $hashed_password, $admin_email]);

            // 5. Úspěch a přechod na další krok
            $current_step = 4; // Krok "Hotovo"
            
            // 6. Pokus o smazání instalačního souboru
            @unlink(__FILE__);
            
            session_destroy();


        } catch (Exception $e) {
            $errors[] = 'Během instalace nastala kritická chyba: ' . $e->getMessage();
        }
    }
}

/**
 * Funkce, která vrací SQL kód pro vytvoření struktury databáze.
 * @return string
 */
function get_database_schema() {
    // Zde je vložena struktura vaší databáze bez dat
    return "
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
START TRANSACTION;
SET time_zone = '+00:00';

CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `menus` (`id`, `name`, `location`) VALUES
(1, 'Hlavní menu', 'header'),
(2, 'Menu v patičce', 'footer');

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `page_id` int(11) DEFAULT NULL,
  `custom_url` varchar(255) DEFAULT NULL,
  `item_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_items_translations` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `language_code` varchar(5) NOT NULL,
  `title` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_homepage` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pages_translations` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  `language_code` varchar(5) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `plugin_status` (
  `plugin_folder` varchar(191) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shortcodes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `content` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `theme_options` (
  `option_name` varchar(100) NOT NULL,
  `option_value` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `widgets` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `sidebar` varchar(50) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `menus` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `location` (`location`);
ALTER TABLE `menu_items` ADD PRIMARY KEY (`id`), ADD KEY `menu_id` (`menu_id`);
ALTER TABLE `menu_items_translations` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `item_language` (`item_id`,`language_code`);
ALTER TABLE `pages` ADD PRIMARY KEY (`id`);
ALTER TABLE `pages_translations` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `page_lang_slug` (`page_id`,`language_code`,`slug`), ADD KEY `page_id` (`page_id`);
ALTER TABLE `plugin_status` ADD PRIMARY KEY (`plugin_folder`);
ALTER TABLE `shortcodes` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `name` (`name`);
ALTER TABLE `theme_options` ADD PRIMARY KEY (`option_name`);
ALTER TABLE `users` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `username` (`username`);
ALTER TABLE `widgets` ADD PRIMARY KEY (`id`);

ALTER TABLE `menus` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `menu_items` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `menu_items_translations` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `pages` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `pages_translations` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `shortcodes` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `widgets` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
    ";
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace Redakčního Systému</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #2c3e50; }
        .steps { display: flex; justify-content: space-between; margin-bottom: 30px; padding: 0; list-style: none; }
        .steps li { flex: 1; text-align: center; color: #bdc3c7; }
        .steps li.active { color: #3498db; font-weight: bold; }
        .steps li:not(:last-child)::after { content: '→'; padding-left: 10px; color: #bdc3c7; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 12px; background-color: #3498db; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; text-align: center; text-decoration: none; }
        .btn:hover { background-color: #2980b9; }
        .btn-disabled { background-color: #bdc3c7; cursor: not-allowed; }
        .error { background-color: #e74c3c; color: white; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .success { background-color: #2ecc71; color: white; padding: 15px; border-radius: 4px; text-align: center; }
        .check-item { margin-bottom: 10px; }
        .check-item span { font-weight: bold; }
        .ok { color: #2ecc71; }
        .fail { color: #e74c3c; }
    </style>
</head>
<body>

<div class="container">
    <h1>Instalace RS</h1>

    <ul class="steps">
        <li class="<?= $current_step == 1 ? 'active' : '' ?>">Kontrola</li>
        <li class="<?= $current_step == 2 ? 'active' : '' ?>">Databáze</li>
        <li class="<?= $current_step == 3 ? 'active' : '' ?>">Administrátor</li>
        <li class="<?= $current_step == 4 ? 'active' : '' ?>">Hotovo</li>
    </ul>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($current_step == 1): ?>
        <h2>Krok 1: Kontrola prostředí</h2>
        <div class="check-item">Verze PHP (vyžadováno 8.0+): <span class="<?= $php_version_ok ? 'ok' : 'fail' ?>"><?= htmlspecialchars(PHP_VERSION) ?></span></div>
        <div class="check-item">PDO MySQL rozšíření: <span class="<?= $pdo_ok ? 'ok' : 'fail' ?>"><?= $pdo_ok ? 'Dostupné' : 'Chybí' ?></span></div>
        <div class="check-item">Zápis do složky /config: <span class="<?= $config_writable ? 'ok' : 'fail' ?>"><?= $config_writable ? 'Možný' : 'Není možný' ?></span></div>
        
        <?php if ($all_ok): ?>
            <a href="install.php?step=2" class="btn">Pokračovat k nastavení databáze</a>
        <?php else: ?>
            <a href="#" class="btn btn-disabled">Prosím, opravte chyby výše</a>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($current_step == 2): ?>
        <h2>Krok 2: Nastavení databáze</h2>
        <p>Zadejte prosím údaje pro připojení k vaší MySQL/MariaDB databázi. Pokud databáze neexistuje, instalátor se ji pokusí vytvořit.</p>
        <form action="install.php?step=2" method="post">
            <div class="form-group">
                <label for="db_host">Hostitel databáze</label>
                <input type="text" id="db_host" name="db_host" value="localhost" required>
            </div>
            <div class="form-group">
                <label for="db_name">Název databáze</label>
                <input type="text" id="db_name" name="db_name" required>
            </div>
            <div class="form-group">
                <label for="db_user">Uživatelské jméno</label>
                <input type="text" id="db_user" name="db_user" required>
            </div>
            <div class="form-group">
                <label for="db_pass">Heslo</label>
                <input type="password" id="db_pass" name="db_pass">
            </div>
            <button type="submit" class="btn">Otestovat a pokračovat</button>
        </form>
    <?php endif; ?>

    <?php if ($current_step == 3): ?>
        <h2>Krok 3: Vytvoření administrátorského účtu</h2>
        <p>Připojení k databázi bylo úspěšné. Nyní vytvořte hlavní účet pro správu webu.</p>
        <form action="install.php?step=3" method="post">
            <div class="form-group">
                <label for="admin_user">Uživatelské jméno</label>
                <input type="text" id="admin_user" name="admin_user" required>
            </div>
            <div class="form-group">
                <label for="admin_email">E-mail</label>
                <input type="text" id="admin_email" name="admin_email" required>
            </div>
            <div class="form-group">
                <label for="admin_pass">Heslo (min. 8 znaků)</label>
                <input type="password" id="admin_pass" name="admin_pass" required>
            </div>
            <button type="submit" class="btn">Dokončit instalaci</button>
        </form>
    <?php endif; ?>

    <?php if ($current_step == 4): ?>
        <div class="success">
            <h2>🎉 Instalace dokončena!</h2>
            <p>Váš redakční systém je úspěšně nainstalován.</p>
            <p>Nyní se můžete přihlásit do <a href="admin/">administrace.</a></p>
        </div>
        
        <?php if (file_exists(__FILE__)): ?>
            <div class="error" style="margin-top: 20px; text-align: center;">
                <h3 style="margin-top:0;">DŮLEŽITÉ!</h3>
                <p>Instalační soubor se nepodařilo automaticky smazat. Z bezpečnostních důvodů nyní **OKAMŽITĚ SMAŽTE** soubor <strong>install.php</strong> z vašeho serveru.</p>
            </div>
        <?php else: ?>
            <div class="success" style="margin-top: 20px; text-align: center; background-color: #1abc9c;">
                 <p>Instalační soubor byl úspěšně smazán.</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>
