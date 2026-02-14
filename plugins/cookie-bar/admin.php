<?php
/**
 * Administrační rozhraní pluginu Cookie Bar
 * Verze 3.0 - Robustní opravená verze
 */

// KROK 1: Načtení základních souborů systému pro připojení k databázi
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/database.php';

// KROK 2: Načtení knihovny s funkcemi tohoto pluginu
require_once __DIR__ . '/functions.php';

// KROK 3: Bezpečnostní kontrola a spuštění session
session_start();
if (!isset($_SESSION['user_id'])) { 
    die('Přístup odepřen.'); 
}

// Zpracování formuláře pro uložení nastavení
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cookie_bar_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = "<div class='cookie-alert cookie-error'>Chyba: Neplatný bezpečnostní token. Zkuste to znovu.</div>";
    } else if (isset($_POST['action']) && $_POST['action'] === 'save_cookie_settings') {
        try {
            cookie_bar_update_setting('cookie_bar_text', $_POST['cookie_bar_text']);
            cookie_bar_update_setting('cookie_policy_slug', trim($_POST['cookie_policy_slug']));
            $message = "<div class='cookie-alert cookie-success'>Nastavení bylo úspěšně uloženo.</div>";
        } catch (Exception $e) {
            $message = "<div class='cookie-alert cookie-error'>Chyba při ukládání: " . $e->getMessage() . "</div>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nastavení Cookie Lišty</title>
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --border-color: #dee2e6;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body { 
            font-family: var(--font-family); 
            margin: 0; 
            padding: 20px; 
            background: #f1f1f1; 
        }
        .plugin-admin-wrap { 
            max-width: 1000px; 
            margin: 20px auto; 
            background: white; 
            padding: 20px 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-field {
            margin-bottom: 20px;
        }
        .form-field label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .form-field input[type="text"], 
        .form-field textarea { 
            box-sizing: border-box;
            width: 100%; 
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-field textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-field small {
            color: var(--secondary-color);
            display: block;
            margin-top: 5px;
        }
        .button { 
            display: inline-block; 
            padding: 10px 20px; 
            background: var(--primary-color); 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            border: none; 
            cursor: pointer;
            font-size: 16px;
        }
        .cookie-alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .cookie-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .cookie-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
<div class="plugin-admin-wrap">
    <h1>Nastavení Cookie Lišty</h1>
    <?php echo $message; ?>
    <form method="post" action="">
        <input type="hidden" name="action" value="save_cookie_settings">
        <input type="hidden" name="csrf_token" value="<?php echo cookie_bar_generate_csrf_token(); ?>">
        
        <div class="form-field">
            <label for="cookie_bar_text">Text v cookie liště</label>
            <textarea id="cookie_bar_text" name="cookie_bar_text" rows="4"><?php echo htmlspecialchars(cookie_bar_get_setting('cookie_bar_text')); ?></textarea>
        </div>

        <div class="form-field">
            <label for="cookie_policy_slug">Slug stránky s ochranou os. údajů</label>
            <input type="text" id="cookie_policy_slug" name="cookie_policy_slug" value="<?php echo htmlspecialchars(cookie_bar_get_setting('cookie_policy_slug')); ?>">
            <small>Např. "ochrana-osobnich-udaju". Výsledný odkaz bude: <?php echo defined('SITE_URL') ? rtrim(SITE_URL, '/') : ''; ?>/vase-stranka</small>
        </div>

        <div class="form-field">
            <button type="submit" class="button">Uložit nastavení</button>
        </div>

    </form>
</div>
</body>
</html>
