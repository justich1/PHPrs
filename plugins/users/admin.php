<?php
/**
 * Administrační rozhraní pluginu Uživatelé
 */

// Z bezpečnostních důvodů je dobré kontrolovat, zda je soubor volán v rámci systému
// a ne přímo. Pokud máte hlavní soubor, který definuje konstantu, použijte ji.
// Příklad: defined('MY_APP') or die('Přístup odepřen.');

session_start();
// Toto by mělo být součástí vašeho hlavního systému, ne pluginu.
// Předpokládáme, že administrátor je již ověřen.
if (!isset($_SESSION['user_id'])) { 
    // Místo die() je lepší přesměrování na přihlášení nebo zobrazení chybové stránky.
    die('Přístup odepřen.'); 
}

// Načtení potřebných souborů
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/database.php';
require_once __DIR__ . '/functions.php';

$db = db_connect();
$action = $_GET['action'] ?? 'list';
$client_id = $_GET['id'] ?? null;
$message = '';

// --- ZPRACOVÁNÍ FORMULÁŘŮ (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!user_verify_csrf_token($_POST['csrf_token'] ?? '')) {
         $message = user_display_message('Chyba: Neplatný bezpečnostní token. Zkuste to znovu.', true);
    } else {
        $post_action = $_POST['action'] ?? '';
        try {
            switch($post_action) {
                case 'save_settings':
                    user_update_setting('registrations_enabled', isset($_POST['registrations_enabled']) ? '1' : '0');
                    user_update_setting('login_page_slug', trim($_POST['login_page_slug']));
                    user_update_setting('registration_page_slug', trim($_POST['registration_page_slug']));
                    user_update_setting('terms_page_slug', trim($_POST['terms_page_slug']));
                    user_update_setting('activation_page_slug', trim($_POST['activation_page_slug']));
                    user_update_setting('password_reset_page_slug', trim($_POST['password_reset_page_slug']));
                    user_update_setting('email_activation_subject', $_POST['email_activation_subject']);
                    user_update_setting('email_activation_body', $_POST['email_activation_body']);
                    user_update_setting('email_password_reset_subject', $_POST['email_password_reset_subject']);
                    user_update_setting('email_password_reset_body', $_POST['email_password_reset_body']);
                    user_update_setting('smtp_host', trim($_POST['smtp_host']));
                    user_update_setting('smtp_port', trim($_POST['smtp_port']));
                    user_update_setting('smtp_user', trim($_POST['smtp_user']));
                    if (!empty($_POST['smtp_pass'])) {
                        user_update_setting('smtp_pass', trim($_POST['smtp_pass']));
                    }
                    user_update_setting('smtp_encryption', $_POST['smtp_encryption']);
                    $message = user_display_message('Nastavení bylo uloženo.');
                    break;
                
                case 'test_smtp':
                    $test_config = [
                        'host' => user_get_setting('smtp_host'),
                        'port' => user_get_setting('smtp_port'),
                        'user' => user_get_setting('smtp_user'),
                        'pass' => user_get_setting('smtp_pass'),
                        'encryption' => user_get_setting('smtp_encryption')
                    ];
                    $test_recipient = trim($_POST['test_recipient']);
                    if (!filter_var($test_recipient, FILTER_VALIDATE_EMAIL)) throw new Exception('Zadejte platný e-mail příjemce pro test.');

                    $result = user_send_smtp_email(
                        $test_recipient,
                        'Testovací e-mail z webu',
                        'Toto je testovací zpráva pro ověření SMTP nastavení.',
                        $test_config
                    );

                    if ($result === true) {
                        $message = user_display_message('Testovací e-mail byl úspěšně odeslán na ' . htmlspecialchars($test_recipient) . '.');
                    } else {
                        $message = user_display_message('Chyba při odesílání: ' . $result, true);
                    }
                    break;

                case 'add_user':
                    if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Neplatný email.');
                    if (empty($_POST['password'])) throw new Exception('Heslo nesmí být prázdné.');
                    
                    $stmt = $db->prepare("SELECT id FROM plugin_clients WHERE email = ?");
                    $stmt->execute([$_POST['email']]);
                    if ($stmt->fetch()) throw new Exception('Klient s tímto e-mailem již existuje.');

                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO plugin_clients (email, password, first_name, last_name, address, phone, is_active, is_blocked) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['email'], $hashed_password, $_POST['first_name'], $_POST['last_name'], $_POST['address'], $_POST['phone'],
                        isset($_POST['is_active']) ? 1 : 0, isset($_POST['is_blocked']) ? 1 : 0
                    ]);
                    $message = user_display_message('Klient byl úspěšně vytvořen.');
                    $action = 'list';
                    break;

                case 'update_user':
                    if (!$client_id) throw new Exception('Chybí ID klienta.');
                    $params = [
                        $_POST['email'], $_POST['first_name'], $_POST['last_name'], $_POST['address'], $_POST['phone'],
                        isset($_POST['is_active']) ? 1 : 0, isset($_POST['is_blocked']) ? 1 : 0, $client_id
                    ];
                    if (!empty($_POST['password'])) {
                        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $sql = "UPDATE plugin_clients SET email=?, first_name=?, last_name=?, address=?, phone=?, is_active=?, is_blocked=?, password=? WHERE id=?";
                        $params[] = $hashed_password;
                    } else {
                        $sql = "UPDATE plugin_clients SET email=?, first_name=?, last_name=?, address=?, phone=?, is_active=?, is_blocked=? WHERE id=?";
                    }
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $message = user_display_message('Klient byl úspěšně aktualizován.');
                    break;
            }
        } catch (Exception $e) {
            $message = user_display_message($e->getMessage(), true);
        }
    }
}

// --- ZPRACOVÁNÍ AKCÍ (GET) ---
if (isset($_GET['do'], $_GET['id'])) {
    // Zde by mělo být také ověření CSRF tokenu pro GET akce, které mění data
    $do_action = $_GET['do'];
    $do_id = $_GET['id'];
    $sql = '';
    switch ($do_action) {
        case 'delete': $sql = "DELETE FROM plugin_clients WHERE id = ?"; break;
        case 'block': $sql = "UPDATE plugin_clients SET is_blocked = 1 WHERE id = ?"; break;
        case 'unblock': $sql = "UPDATE plugin_clients SET is_blocked = 0 WHERE id = ?"; break;
        case 'activate': $sql = "UPDATE plugin_clients SET is_active = 1, activation_token = NULL WHERE id = ?"; break;
    }
    if ($sql) {
        $stmt = $db->prepare($sql);
        $stmt->execute([$do_id]);
        // Je lepší přidat zprávu do session a pak přesměrovat, aby se zobrazila po načtení stránky.
        header('Location: ?page=uzivatele/admin.php&action=list&status=ok');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa klientů</title>
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --border-color: #dee2e6;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body { 
            font-family: var(--font-family); 
            margin: 0; 
            padding: 20px; 
            background: #f1f1f1; 
            color: var(--dark-gray);
        }
        .user-plugin-admin-wrap { 
            max-width: 1000px; 
            margin: 20px auto; 
            background: white; 
            padding: 20px 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h3 {
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        nav { 
            margin-bottom: 20px; 
            background: var(--light-gray);
            padding: 10px 15px;
            border-radius: 5px;
        }
        nav a { 
            margin-right: 15px; 
            text-decoration: none; 
            color: var(--primary-color);
            font-weight: 500;
        }
        nav a:hover {
            text-decoration: underline;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px;
        }
        th, td { 
            padding: 12px 15px; 
            border: 1px solid var(--border-color); 
            text-align: left; 
            vertical-align: middle;
        }
        thead th {
            background-color: var(--light-gray);
            font-weight: 600;
        }
        tbody tr:nth-child(even) { 
            background-color: var(--light-gray); 
        }
        tbody tr:hover {
            background-color: #e9ecef;
        }
        .form-table th { 
            width: 250px; 
            vertical-align: top; 
            padding-top: 15px; 
            font-weight: normal;
            background-color: transparent;
        }
        .form-table td {
             background-color: transparent;
        }
        .form-table input[type="text"], 
        .form-table input[type="email"], 
        .form-table input[type="password"], 
        .form-table input[type="number"], 
        .form-table textarea, 
        .form-table select { 
            width: calc(100% - 20px); 
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: border-color 0.2s;
        }
        .form-table input:focus, .form-table textarea:focus, .form-table select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        .form-table small {
            color: var(--secondary-color);
            display: block;
            margin-top: 5px;
        }
        .button, button { 
            display: inline-block; 
            padding: 10px 20px; 
            background: var(--primary-color); 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            border: none; 
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s;
        }
        .button:hover, button:hover {
            background: #0056b3;
        }
        .button-secondary { background-color: var(--secondary-color); }
        .button-secondary:hover { background-color: #5a6268; }
        
        .user-plugin-success, .user-plugin-error {
            padding: 15px; 
            margin-bottom: 20px; 
            border: 1px solid transparent; 
            border-radius: 4px;
        }
        .user-plugin-success {
            color: #155724; 
            background-color: #d4edda; 
            border-color: #c3e6cb;
        }
        .user-plugin-error {
            color: #721c24; 
            background-color: #f8d7da; 
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
<div class="user-plugin-admin-wrap">
    <h1>Správa klientů</h1>
    <?php echo $message; ?>
    <nav>
        <a href="?page=uzivatele/admin.php&action=list">Seznam klientů</a>
        <a href="?page=uzivatele/admin.php&action=add">Přidat nového klienta</a>
        <a href="?page=uzivatele/admin.php&action=settings">Nastavení</a>
    </nav>

    <?php if ($action == 'list'): ?>
        <h3>Seznam klientů</h3>
        <table>
            <thead><tr><th>ID</th><th>E-mail</th><th>Jméno</th><th>Stav</th><th>Akce</th></tr></thead>
            <tbody>
            <?php
            $clients = $db->query("SELECT id, email, first_name, last_name, is_active, is_blocked FROM plugin_clients ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($clients as $client): ?>
                <tr>
                    <td><?php echo $client['id']; ?></td>
                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                    <td><?php echo htmlspecialchars(trim($client['first_name'] . ' ' . $client['last_name'])); ?></td>
                    <td>
                        <?php echo $client['is_active'] ? '<span style="color:var(--success-color);">Aktivní</span>' : '<span style="color:orange;">Neaktivní</span>'; ?>
                        <?php echo $client['is_blocked'] ? '<br><span style="color:var(--danger-color);">Blokován</span>' : ''; ?>
                    </td>
                    <td>
                        <a href="?page=uzivatele/admin.php&action=edit&id=<?php echo $client['id']; ?>">Upravit</a> |
                        <a href="?page=uzivatele/admin.php&do=delete&id=<?php echo $client['id']; ?>" onclick="return confirm('Opravdu smazat tohoto klienta?');">Smazat</a><br>
                        <?php if ($client['is_blocked']): ?>
                            <a href="?page=uzivatele/admin.php&do=unblock&id=<?php echo $client['id']; ?>">Odblokovat</a>
                        <?php else: ?>
                            <a href="?page=uzivatele/admin.php&do=block&id=<?php echo $client['id']; ?>">Blokovat</a>
                        <?php endif; ?>
                        <?php if (!$client['is_active']): ?>
                            | <a href="?page=uzivatele/admin.php&do=activate&id=<?php echo $client['id']; ?>">Aktivovat</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($action == 'edit' || $action == 'add'):
        $client = null;
        if ($action == 'edit' && $client_id) {
            $stmt = $db->prepare("SELECT * FROM plugin_clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        ?>
        <h3><?php echo $action == 'edit' ? 'Úprava klienta' : 'Vytvoření nového klienta'; ?></h3>
        <form method="post" action="?page=uzivatele/admin.php&action=<?php echo $action; ?><?php if($client_id) echo '&id='.$client_id; ?>">
            <input type="hidden" name="action" value="<?php echo $action == 'edit' ? 'update_user' : 'add_user'; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
            <table class="form-table">
                <tr><th><label for="email">E-mail</label></th><td><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>" required></td></tr>
                <tr><th><label for="password">Heslo</label></th><td><input type="password" id="password" name="password" placeholder="<?php echo $action == 'edit' ? 'Zadejte pro změnu' : ''; ?>" <?php if($action == 'add') echo 'required'; ?>></td></tr>
                <tr><th><label for="first_name">Jméno</label></th><td><input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($client['first_name'] ?? ''); ?>"></td></tr>
                <tr><th><label for="last_name">Příjmení</label></th><td><input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($client['last_name'] ?? ''); ?>"></td></tr>
                <tr><th><label for="address">Adresa</label></th><td><textarea id="address" name="address"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea></td></tr>
                <tr><th><label for="phone">Telefon</label></th><td><input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>"></td></tr>
                <tr>
                    <th>Stav</th>
                    <td>
                        <label><input type="checkbox" name="is_active" value="1" <?php echo ($client['is_active'] ?? 0) ? 'checked' : ''; ?>> Aktivní</label><br>
                        <label><input type="checkbox" name="is_blocked" value="1" <?php echo ($client['is_blocked'] ?? 0) ? 'checked' : ''; ?>> Blokován</label>
                    </td>
                </tr>
            </table>
            <p><button type="submit" class="button">Uložit</button></p>
        </form>

    <?php elseif ($action == 'settings'): ?>
        <h3>Nastavení pluginu</h3>
        <form method="post" action="?page=uzivatele/admin.php&action=settings">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
            <table class="form-table">
                <tr>
                    <th>Obecné</th>
                    <td>
                        <label><input type="checkbox" name="registrations_enabled" value="1" <?php echo user_get_setting('registrations_enabled') ? 'checked' : ''; ?>> Povolit nové registrace</label>
                    </td>
                </tr>
                <tr><td colspan="2"><h3>Slugs (části URL) pro stránky</h3></td></tr>
                <tr><th><label for="login_page_slug">Slug stránky pro přihlášení</label></th><td><input type="text" id="login_page_slug" name="login_page_slug" value="<?php echo htmlspecialchars(user_get_setting('login_page_slug')); ?>"></td></tr>
                <tr><th><label for="registration_page_slug">Slug stránky pro registraci</label></th><td><input type="text" id="registration_page_slug" name="registration_page_slug" value="<?php echo htmlspecialchars(user_get_setting('registration_page_slug')); ?>"></td></tr>
                <tr><th><label for="terms_page_slug">Slug stránky s obch. podmínkami</label></th><td><input type="text" id="terms_page_slug" name="terms_page_slug" value="<?php echo htmlspecialchars(user_get_setting('terms_page_slug')); ?>"></td></tr>
                <tr><th><label for="activation_page_slug">Slug stránky pro aktivaci</label></th><td><input type="text" id="activation_page_slug" name="activation_page_slug" value="<?php echo htmlspecialchars(user_get_setting('activation_page_slug')); ?>"></td></tr>
                <tr><th><label for="password_reset_page_slug">Slug stránky pro reset hesla</label></th><td><input type="text" id="password_reset_page_slug" name="password_reset_page_slug" value="<?php echo htmlspecialchars(user_get_setting('password_reset_page_slug')); ?>"></td></tr>
                
                <tr><td colspan="2"><h3>E-mailové šablony</h3></td></tr>
                <tr><th><label for="email_activation_subject">Předmět aktivačního e-mailu</label></th><td><input type="text" id="email_activation_subject" name="email_activation_subject" value="<?php echo htmlspecialchars(user_get_setting('email_activation_subject')); ?>"></td></tr>
                <tr>
                    <th><label for="email_activation_body">Tělo aktivačního e-mailu</label></th>
                    <td>
                        <textarea name="email_activation_body" id="email_activation_body" rows="5"><?php echo htmlspecialchars(user_get_setting('email_activation_body')); ?></textarea><br>
                        <small>Použijte zástupný symbol <code>{AKTIVACNI_ODKAZ}</code>.</small>
                    </td>
                </tr>
                <tr><th><label for="email_password_reset_subject">Předmět e-mailu pro reset hesla</label></th><td><input type="text" id="email_password_reset_subject" name="email_password_reset_subject" value="<?php echo htmlspecialchars(user_get_setting('email_password_reset_subject')); ?>"></td></tr>
                <tr>
                    <th><label for="email_password_reset_body">Tělo e-mailu pro reset hesla</label></th>
                    <td>
                        <textarea name="email_password_reset_body" id="email_password_reset_body" rows="5"><?php echo htmlspecialchars(user_get_setting('email_password_reset_body')); ?></textarea><br>
                        <small>Použijte zástupný symbol <code>{RESET_ODKAZ}</code>.</small>
                    </td>
                </tr>

                <tr><td colspan="2"><h3>Nastavení SMTP pro odesílání e-mailů</h3><small>Pokud necháte pole prázdná, použije se standardní funkce serveru.</small></td></tr>
                <tr><th><label for="smtp_host">SMTP Host</label></th><td><input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars(user_get_setting('smtp_host')); ?>"></td></tr>
                <tr><th><label for="smtp_port">SMTP Port</label></th><td><input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars(user_get_setting('smtp_port')); ?>"></td></tr>
                <tr><th><label for="smtp_user">SMTP Uživatel (e-mail)</label></th><td><input type="text" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars(user_get_setting('smtp_user')); ?>"></td></tr>
                <tr><th><label for="smtp_pass">SMTP Heslo</label></th><td><input type="password" id="smtp_pass" name="smtp_pass" placeholder="Zadejte pro změnu nebo nastavení"></td></tr>
                <tr>
                    <th><label for="smtp_encryption">SMTP Šifrování</label></th>
                    <td>
                        <select name="smtp_encryption" id="smtp_encryption">
                            <option value="none" <?php user_plugin_selected(user_get_setting('smtp_encryption'), 'none'); ?>>Žádné</option>
                            <option value="ssl" <?php user_plugin_selected(user_get_setting('smtp_encryption'), 'ssl'); ?>>SSL</option>
                            <option value="tls" <?php user_plugin_selected(user_get_setting('smtp_encryption'), 'tls'); ?>>TLS</option>
                        </select>
                    </td>
                </tr>
            </table>
             <p><button type="submit" name="action" value="save_settings" class="button">Uložit nastavení</button></p>
        </form>
        
        <hr style="margin: 30px 0;">

        <h3>Otestovat SMTP připojení</h3>
        <p><small>Nejprve uložte nastavení, poté můžete otestovat odeslání e-mailu.</small></p>
        <form method="post" action="?page=uzivatele/admin.php&action=settings">
             <input type="hidden" name="action" value="test_smtp">
             <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
             <table class="form-table">
                 <tr>
                     <th><label for="test_recipient">Odeslat test na e-mail</label></th>
                     <td><input type="email" id="test_recipient" name="test_recipient" required placeholder="vas.email@domena.cz"></td>
                 </tr>
             </table>
             <p><button type="submit" class="button button-secondary">Odeslat testovací e-mail</button></p>
        </form>

    <?php endif; ?>
</div>
</body>
</html>
