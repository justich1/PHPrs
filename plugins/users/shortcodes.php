<?php
/**
 * Logika a vykreslování shortcodů pro frontend.
 */

// Globální proměnná pro ukládání zpráv mezi funkcemi.
$user_plugin_messages = [];

/**
 * Vypíše CSS styly pro frontend. Použije se statická proměnná, aby se styly vložily jen jednou.
 */
function user_plugin_output_frontend_styles() {
    static $styles_outputted = false;
    if ($styles_outputted) {
        return;
    }
    ?>
    <style>
        .user-form-wrapper {
            max-width: 500px;
            margin: 20px auto;
        }
        .user-form-container {
            padding: 20px 30px;
            background-color: rgba(249, 249, 249, 0.6); 
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .user-form-container h3, .user-form-container h4 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 25px;
            border-bottom: 2px solid rgba(238, 238, 238, 0.8);
            padding-bottom: 15px;
        }
        .user-form-container h4 {
             border-bottom: none;
             margin-bottom: 20px;
             text-align: left;
        }
        .user-form-container p {
            margin: 0 0 15px 0;
        }
        .user-form-container p:last-child {
            margin-bottom: 0;
        }
        .user-form-container label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .user-form-container input[type="text"],
        .user-form-container input[type="email"],
        .user-form-container input[type="password"],
        .user-form-container textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .user-form-container input[type="checkbox"] {
            margin-right: 5px;
            vertical-align: middle;
        }
        .user-form-container button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        .user-form-container button:hover {
            background-color: #0056b3;
        }
        .user-form-links {
            text-align: center;
            margin-top: 15px;
        }
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
    <?php
    $styles_outputted = true;
}


/**
 * Hlavní funkce, která zpracovává všechny POST požadavky z formulářů pluginu.
 */
function user_plugin_handle_post_requests() {
    global $user_plugin_messages;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_action'])) return;

    if (!user_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $user_plugin_messages[] = ['type' => 'error', 'text' => 'Neplatný bezpečnostní token. Obnovte prosím stránku a zkuste to znovu.'];
        return;
    }
    
    $db = db_connect();
    $action = $_POST['user_action'];

    try {
        switch ($action) {
            case 'login':
                $stmt = $db->prepare("SELECT * FROM plugin_clients WHERE email = ?");
                $stmt->execute([$_POST['email']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($_POST['heslo'], $user['password'])) {
                    if ($user['is_blocked']) {
                        $user_plugin_messages[] = ['type' => 'error', 'text' => 'Váš účet je zablokován.'];
                    } elseif (!$user['is_active']) {
                        $user_plugin_messages[] = ['type' => 'error', 'text' => 'Váš účet není aktivní. Zkontrolujte prosím svůj e-mail.'];
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['client_user_id'] = $user['id'];
                        $_SESSION['client_user_email'] = $user['email'];
                        header("Location: " . $_SERVER['REQUEST_URI']);
                        exit;
                    }
                } else {
                    $user_plugin_messages[] = ['type' => 'error', 'text' => 'Nesprávný e-mail nebo heslo.'];
                }
                break;

            case 'register':
                if (user_get_setting('registrations_enabled', '1') !== '1') throw new Exception('Registrace jsou dočasně pozastaveny.');
                if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Prosím zadejte platný e-mail.');
                if (empty($_POST['heslo']) || $_POST['heslo'] !== $_POST['heslo_znovu']) throw new Exception('Hesla se neshodují.');
                if (strlen($_POST['heslo']) < 8) throw new Exception('Heslo musí mít alespoň 8 znaků.');
                if (empty($_POST['podminky'])) throw new Exception('Musíte souhlasit s obchodními podmínkami.');
                if (!isset($_SESSION['spam_check_answer']) || $_SESSION['spam_check_answer'] != $_POST['spam_check']) throw new Exception('Nesprávná odpověď na kontrolní otázku.');

                $stmt = $db->prepare("SELECT id FROM plugin_clients WHERE email = ?");
                $stmt->execute([$_POST['email']]);
                if ($stmt->fetch()) throw new Exception('Uživatel s tímto e-mailem již existuje.');

                $token = user_generate_token();
                $hashed_password = password_hash($_POST['heslo'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO plugin_clients (email, password, activation_token) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['email'], $hashed_password, $token]);

                $activation_slug = user_get_setting('activation_page_slug', 'aktivace');
                $activation_link = rtrim(SITE_URL, '/') . '/' . $activation_slug . '?token=' . $token;
                $email_body = str_replace('{AKTIVACNI_ODKAZ}', $activation_link, user_get_setting('email_activation_body'));
                user_send_email($_POST['email'], user_get_setting('email_activation_subject'), $email_body);

                $user_plugin_messages[] = ['type' => 'success', 'text' => 'Registrace proběhla úspěšně. Na Váš e-mail byl odeslán odkaz pro aktivaci účtu.'];
                break;

            case 'update_profile':
                if (!isset($_SESSION['client_user_id'])) return;
                $stmt = $db->prepare("UPDATE plugin_clients SET first_name = ?, last_name = ?, address = ?, phone = ? WHERE id = ?");
                $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['address'], $_POST['phone'], $_SESSION['client_user_id']]);
                $user_plugin_messages[] = ['type' => 'success', 'text' => 'Profil byl úspěšně aktualizován.'];
                break;
            
            case 'change_password':
                if (!isset($_SESSION['client_user_id'])) return;

                if (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['new_password_confirm'])) {
                    throw new Exception('Všechna pole pro změnu hesla jsou povinná.');
                }
                if ($_POST['new_password'] !== $_POST['new_password_confirm']) {
                    throw new Exception('Nová hesla se neshodují.');
                }
                if (strlen($_POST['new_password']) < 8) {
                    throw new Exception('Nové heslo musí mít alespoň 8 znaků.');
                }

                $stmt = $db->prepare("SELECT password FROM plugin_clients WHERE id = ?");
                $stmt->execute([$_SESSION['client_user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !password_verify($_POST['current_password'], $user['password'])) {
                    throw new Exception('Vaše aktuální heslo není správné.');
                }

                $new_hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE plugin_clients SET password = ? WHERE id = ?");
                $stmt->execute([$new_hashed_password, $_SESSION['client_user_id']]);
                $user_plugin_messages[] = ['type' => 'success', 'text' => 'Heslo bylo úspěšně změněno.'];
                break;

            case 'delete_account':
                if (!isset($_SESSION['client_user_id'])) return;
                $stmt = $db->prepare("SELECT password FROM plugin_clients WHERE id = ?");
                $stmt->execute([$_SESSION['client_user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($_POST['heslo_pro_smazani'], $user['password'])) {
                    $stmt = $db->prepare("DELETE FROM plugin_clients WHERE id = ?");
                    $stmt->execute([$_SESSION['client_user_id']]);
                    session_destroy();
                    header("Location: /");
                    exit;
                } else {
                    $user_plugin_messages[] = ['type' => 'error', 'text' => 'Nesprávné heslo. Účet nebyl smazán.'];
                }
                break;
                
            case 'request_reset':
                $stmt = $db->prepare("SELECT id FROM plugin_clients WHERE email = ? AND is_active = 1 AND is_blocked = 0");
                $stmt->execute([$_POST['email']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $token = user_generate_token();
                    $expires = new DateTime('+1 hour');
                    $stmt = $db->prepare("UPDATE plugin_clients SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                    $stmt->execute([$token, $expires->format('Y-m-d H:i:s'), $user['id']]);

                    $reset_slug = user_get_setting('password_reset_page_slug', 'reset-hesla');
                    $reset_link = rtrim(SITE_URL, '/') . '/' . $reset_slug . '?token=' . $token;
                    $email_body = str_replace('{RESET_ODKAZ}', $reset_link, user_get_setting('email_password_reset_body'));
                    user_send_email($_POST['email'], user_get_setting('email_password_reset_subject'), $email_body);
                }
                $user_plugin_messages[] = ['type' => 'success', 'text' => 'Pokud Váš e-mail existuje v naší databázi, byl na něj odeslán odkaz pro obnovení hesla.'];
                break;

            case 'perform_reset':
                if (empty($_POST['token'])) throw new Exception('Chybí token pro obnovu.');
                if (empty($_POST['heslo']) || $_POST['heslo'] !== $_POST['heslo_znovu']) throw new Exception('Hesla se neshodují.');
                if (strlen($_POST['heslo']) < 8) throw new Exception('Heslo musí mít alespoň 8 znaků.');

                $stmt = $db->prepare("SELECT id FROM plugin_clients WHERE password_reset_token = ? AND password_reset_expires > NOW()");
                $stmt->execute([$_POST['token']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $hashed_password = password_hash($_POST['heslo'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE plugin_clients SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
                    $stmt->execute([$hashed_password, $user['id']]);
                    $user_plugin_messages[] = ['type' => 'success', 'text' => 'Vaše heslo bylo úspěšně změněno. Nyní se můžete přihlásit.'];
                } else {
                    throw new Exception('Neplatný nebo expirovaný odkaz pro obnovu hesla.');
                }
                break;
        }
    } catch (Exception $e) {
        $user_plugin_messages[] = ['type' => 'error', 'text' => $e->getMessage()];
    }
}
user_plugin_handle_post_requests();


// --- Vykreslovací funkce pro shortcody ---

function user_plugin_render_login_shortcode() {
    if (isset($_SESSION['client_user_id'])) {
        return "<div class='user-form-wrapper'><div class='user-form-container'>Jste přihlášen jako " . htmlspecialchars($_SESSION['client_user_email']) . ". <a href=\"?logout=1\">Odhlásit</a></div></div>";
    }
    
    global $user_plugin_messages;
    ob_start();
    user_plugin_output_frontend_styles();
    
    echo '<div class="user-form-wrapper">';
    if (!empty($user_plugin_messages)) {
        foreach($user_plugin_messages as $msg) { echo user_display_message($msg['text'], $msg['type'] === 'error'); }
    }
    ?>
    <div class="user-form-container">
        <form action="" method="post">
            <input type="hidden" name="user_action" value="login">
            <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
            <h3>Přihlášení</h3>
            <p>
                <label for="login-email">E-mail:</label>
                <input id="login-email" type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </p>
            <p>
                <label for="login-heslo">Heslo:</label>
                <input id="login-heslo" type="password" name="heslo" required>
            </p>
            <p>
                <button type="submit">Přihlásit</button>
            </p>
        </form>
        <div class="user-form-links">
            <a href="/<?php echo user_get_setting('password_reset_page_slug', 'reset-hesla'); ?>">Zapomenuté heslo?</a>
            <br>
            <a href="/<?php echo user_get_setting('registration_page_slug', 'registrace'); ?>">Zaregistrujte se</a>
        </div>
    </div>
    <?php
    echo '</div>';
    return ob_get_clean();
}

function user_plugin_render_registration_shortcode() {
    if (user_get_setting('registrations_enabled', '1') !== '1') {
        return user_display_message('Nové registrace jsou dočasně pozastaveny.');
    }
    if (isset($_SESSION['client_user_id'])) return '';

    $num1 = rand(1, 5);
    $num2 = rand(1, 5);
    $_SESSION['spam_check_answer'] = $num1 + $num2;

    global $user_plugin_messages;
    ob_start();
    user_plugin_output_frontend_styles();

    echo '<div class="user-form-wrapper">';
    if (!empty($user_plugin_messages)) {
        foreach($user_plugin_messages as $msg) { echo user_display_message($msg['text'], $msg['type'] === 'error'); }
    }
    ?>
    <div class="user-form-container">
        <form action="" method="post">
            <input type="hidden" name="user_action" value="register">
            <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
            <h3>Registrace nového účtu</h3>
            <p>
                <label for="reg-email">E-mail:</label>
                <input id="reg-email" type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </p>
            <p>
                <label for="reg-heslo">Heslo (min. 8 znaků):</label>
                <input id="reg-heslo" type="password" name="heslo" required>
            </p>
            <p>
                <label for="reg-heslo-znovu">Heslo znovu:</label>
                <input id="reg-heslo-znovu" type="password" name="heslo_znovu" required>
            </p>
            <p>
                <label for="reg-spam">Kontrolní otázka: Kolik je <?php echo $num1; ?> + <?php echo $num2; ?>?</label>
                <input id="reg-spam" type="text" name="spam_check" required>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="podminky" required>
                    Souhlasím s <a href="/<?php echo user_get_setting('terms_page_slug', 'obchodni-podminky'); ?>" target="_blank">obchodními podmínkami</a>.
                </label>
            </p>
            <p>
                <button type="submit">Zaregistrovat se</button>
            </p>
        </form>
    </div>
    <?php
    echo '</div>';
    return ob_get_clean();
}

function user_plugin_render_profile_shortcode() {
    if (!isset($_SESSION['client_user_id'])) {
        $login_slug = user_get_setting('login_page_slug', 'prihlaseni');
        $register_slug = user_get_setting('registration_page_slug', 'registrace');
        
        $message_html = "Pro zobrazení profilu se musíte přihlásit. <br><br>";
        $message_html .= "<a href='/" . htmlspecialchars($login_slug) . "'>Přejít na přihlášení</a>";
        if (user_get_setting('registrations_enabled', '1') === '1') {
            $message_html .= " nebo se <a href='/" . htmlspecialchars($register_slug) . "'>zaregistrujte</a>.";
        }
        
        $class = 'user-plugin-error';
        $style = "padding: 15px; margin-bottom: 20px; border: 1px solid; border-radius: 4px; color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;";
        return "<div class='user-form-wrapper'><div class=\"{$class}\" style=\"{$style}\">" . $message_html . "</div></div>";
    }

    $db = db_connect();
    $stmt = $db->prepare("SELECT * FROM plugin_clients WHERE id = ?");
    $stmt->execute([$_SESSION['client_user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    global $user_plugin_messages;
    ob_start();
    user_plugin_output_frontend_styles();

    echo '<div class="user-form-wrapper">';
    if (!empty($user_plugin_messages)) {
        foreach($user_plugin_messages as $msg) { echo user_display_message($msg['text'], $msg['type'] === 'error'); }
    }
    ?>
    <div class="user-form-container">
        <h3>Můj profil</h3>
        <form action="" method="post">
            <input type="hidden" name="user_action" value="update_profile">
            <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
            <h4>Fakturační údaje</h4>
            <p>
                <label for="prof-first-name">Jméno:</label>
                <input id="prof-first-name" type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
            </p>
            <p>
                <label for="prof-last-name">Příjmení:</label>
                <input id="prof-last-name" type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
            </p>
            <p>
                <label for="prof-address">Adresa (ulice, č.p., město, PSČ):</label>
                <textarea id="prof-address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
            </p>
            <p>
                <label for="prof-phone">Telefon:</label>
                <input id="prof-phone" type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </p>
            <p>
                <button type="submit">Uložit změny</button>
            </p>
        </form>
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
        
        <form action="" method="post">
            <input type="hidden" name="user_action" value="change_password">
            <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
            <h4>Změna hesla</h4>
            <p>
                <label for="prof-current-pass">Aktuální heslo:</label>
                <input id="prof-current-pass" type="password" name="current_password" required>
            </p>
            <p>
                <label for="prof-new-pass">Nové heslo (min. 8 znaků):</label>
                <input id="prof-new-pass" type="password" name="new_password" required>
            </p>
            <p>
                <label for="prof-new-pass-confirm">Potvrzení nového hesla:</label>
                <input id="prof-new-pass-confirm" type="password" name="new_password_confirm" required>
            </p>
            <p>
                <button type="submit">Změnit heslo</button>
            </p>
        </form>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
        <h4>Smazat účet</h4>
        <form action="" method="post" onsubmit="return confirm('Opravdu chcete trvale smazat svůj účet? Tato akce je nevratná!')">
            <input type="hidden" name="user_action" value="delete_account">
            <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
            <p>Pro smazání účtu zadejte své heslo pro potvrzení.</p>
            <p>
                <label for="prof-delete-pass">Heslo:</label>
                <input id="prof-delete-pass" type="password" name="heslo_pro_smazani" required>
            </p>
            <p>
                <button type="submit" style="background-color: #dc3545;">Smazat můj účet</button>
            </p>
        </form>
    </div>
    <?php
    echo '</div>';
    return ob_get_clean();
}

function user_plugin_render_activation_shortcode() {
    $token = $_GET['token'] ?? '';
    ob_start();
    user_plugin_output_frontend_styles();
    echo '<div class="user-form-wrapper">';
    
    if (empty($token)) {
        echo user_display_message('Chybí aktivační token.', true);
    } else {
        try {
            $db = db_connect();
            $stmt = $db->prepare("SELECT id, is_active FROM plugin_clients WHERE activation_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                echo user_display_message('Neplatný aktivační odkaz.', true);
            } elseif ($user['is_active']) {
                echo user_display_message('Tento účet již byl aktivován.');
            } else {
                $stmt = $db->prepare("UPDATE plugin_clients SET is_active = 1, activation_token = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);
                echo user_display_message('Váš účet byl úspěšně aktivován. Nyní se můžete přihlásit.');
            }
        } catch (Exception $e) {
            echo user_display_message('Při aktivaci nastala chyba. Zkuste to prosím později.', true);
        }
    }
    echo '</div>';
    return ob_get_clean();
}

function user_plugin_render_password_reset_shortcode() {
    $token = $_GET['token'] ?? null;
    global $user_plugin_messages;
    ob_start();
    user_plugin_output_frontend_styles();

    echo '<div class="user-form-wrapper">';
    if (!empty($user_plugin_messages)) {
        foreach($user_plugin_messages as $msg) { echo user_display_message($msg['text'], $msg['type'] === 'error'); }
    }
    ?>
    <div class="user-form-container">
    <?php if ($token): ?>
        <form action="" method="post">
            <input type="hidden" name="user_action" value="perform_reset">
            <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <h3>Nastavení nového hesla</h3>
            <p>
                <label for="reset-heslo">Nové heslo (min. 8 znaků):</label>
                <input id="reset-heslo" type="password" name="heslo" required>
            </p>
            <p>
                <label for="reset-heslo-znovu">Nové heslo znovu:</label>
                <input id="reset-heslo-znovu" type="password" name="heslo_znovu" required>
            </p>
            <p>
                <button type="submit">Změnit heslo</button>
            </p>
        </form>
    <?php else: ?>
        <form action="" method="post">
            <input type="hidden" name="user_action" value="request_reset">
            <input type="hidden" name="csrf_token" value="<?php echo user_generate_csrf_token(); ?>">
            <h3>Obnovení zapomenutého hesla</h3>
            <p style="text-align: center;">Zadejte svůj e-mail a my Vám pošleme odkaz pro obnovení hesla.</p>
            <p>
                <label for="reset-email">E-mail:</label>
                <input id="reset-email" type="email" name="email" required>
            </p>
            <p>
                <button type="submit">Odeslat</button>
            </p>
        </form>
    <?php endif; ?>
    </div>
    <?php
    echo '</div>';
    return ob_get_clean();
}
