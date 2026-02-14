<?php
/**
 * Knihovna funkcí pro Cookie Bar plugin
 * Verze 3.1 - Vylepšený vzhled
 */

/**
 * Zajistí, že databázová tabulka pro plugin existuje. Pokud ne, vytvoří ji.
 */
function cookie_bar_ensure_db_table() {
    static $table_checked = false;
    if ($table_checked) {
        return;
    }

    try {
        $db = db_connect();
        $db->query("SELECT 1 FROM `plugin_cookie_settings` LIMIT 1");
    } catch (PDOException $e) {
        try {
            $db_create = db_connect();
            $db_create->exec("
                CREATE TABLE `plugin_cookie_settings` (
                    `setting_key` VARCHAR(100) PRIMARY KEY,
                    `setting_value` TEXT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            $default_settings = [
                'cookie_bar_text' => 'Tento web používá soubory cookie k poskytování služeb, personalizaci reklam a analýze návštěvnosti. Používáním tohoto webu s tím souhlasíte.',
                'cookie_policy_slug' => 'ochrana-osobnich-udaju'
            ];
            
            $stmt = $db_create->prepare("INSERT INTO `plugin_cookie_settings` (setting_key, setting_value) VALUES (?, ?)");
            foreach ($default_settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
        } catch (PDOException $ex) {
            die("Kritická chyba: Nepodařilo se vytvořit databázovou tabulku pro Cookie Bar plugin. Zkontrolujte prosím oprávnění databázového uživatele.");
        }
    }
    $table_checked = true;
}

/**
 * Získá hodnotu nastavení z databáze.
 */
function cookie_bar_get_setting($key, $default = '') {
    cookie_bar_ensure_db_table();
    static $settings = null;
    if ($settings === null) {
        try {
            $db = db_connect();
            $stmt = $db->query("SELECT setting_key, setting_value FROM plugin_cookie_settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Aktualizuje hodnotu nastavení v databázi.
 */
function cookie_bar_update_setting($key, $value) {
    cookie_bar_ensure_db_table();
    $db = db_connect();
    $stmt = $db->prepare("INSERT INTO plugin_cookie_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

/**
 * Vygeneruje CSRF token a uloží ho do session.
 */
function cookie_bar_generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['cookie_csrf_token'])) {
        $_SESSION['cookie_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cookie_csrf_token'];
}

/**
 * Ověří CSRF token.
 */
function cookie_bar_verify_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['cookie_csrf_token'], $token) && !empty($token) && hash_equals($_SESSION['cookie_csrf_token'], $token);
}

/**
 * Kontroluje, zda uživatel udělil souhlas s cookies.
 */
function can_use_tracking_scripts() {
    return isset($_COOKIE['cookie_consent_status']) && $_COOKIE['cookie_consent_status'] === 'accepted';
}

/**
 * Vykreslí HTML, CSS a JS pro cookie lištu.
 */
function cookie_bar_render_html() {
    $text = cookie_bar_get_setting('cookie_bar_text');
    $slug = cookie_bar_get_setting('cookie_policy_slug');
    $policy_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/' . htmlspecialchars($slug) : '#';
    ?>
    <style>
        #cookie-bar-container {
            position: fixed;
            bottom: 20px;
            left: 50%;
            width: auto;
            max-width: 90%;
            transform: translateX(-50%) translateY(150%);
            background-color: rgba(33, 37, 41, 0.95);
            color: white;
            padding: 15px 25px;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 14px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transition: transform 0.5s ease-in-out;
        }
        #cookie-bar-container.show {
            transform: translateX(-50%) translateY(0);
        }
        #cookie-bar-text {
            text-align: center;
            flex-grow: 1;
            margin: 0;
        }
        #cookie-bar-text a {
            color: #58a6ff; /* Světlejší modrá pro lepší kontrast */
            text-decoration: underline;
        }
        #cookie-bar-buttons button {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-left: 10px;
            transition: background-color 0.2s;
        }
        #cookie-bar-accept {
            background-color: #28a745;
            color: white;
        }
        #cookie-bar-accept:hover {
            background-color: #218838;
        }
        #cookie-bar-reject {
            background-color: #6c757d;
            color: white;
        }
        #cookie-bar-reject:hover {
            background-color: #5a6268;
        }
        #cookie-settings-reopen {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background-color: #fff;
            color: #444;
            border-radius: 50%;
            cursor: pointer;
            z-index: 999;
            display: none; /* Skryto defaultně */
            width: 50px;
            height: 50px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            /* Flexbox pro centrování ikony */
            justify-content: center;
            align-items: center;
            transition: transform 0.2s;
        }
        #cookie-settings-reopen:hover {
            transform: scale(1.1);
        }
        #cookie-settings-reopen svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }
    </style>

    <!-- Odkaz pro znovuotevření nastavení -->
    <div id="cookie-settings-reopen" title="Změnit nastavení cookies">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM120 288c-17.7 0-32-14.3-32-32s14.3-32 32-32s32 14.3 32 32s-14.3 32-32 32zm88-64c0 17.7-14.3 32-32 32s-32-14.3-32-32s14.3-32 32-32s32 14.3 32 32zm104-32c-17.7 0-32-14.3-32-32s14.3-32 32-32s32 14.3 32 32s-14.3 32-32 32zM320 352a32 32 0 1 0 0-64 32 32 0 1 0 0 64zM208 384c-17.7 0-32-14.3-32-32s14.3-32 32-32s32 14.3 32 32s-14.3 32-32 32z"/></svg>
    </div>

    <!-- Cookie lišta -->
    <div id="cookie-bar-container">
        <p id="cookie-bar-text">
            <?php echo htmlspecialchars($text); ?>
            <a href="<?php echo $policy_url; ?>">Více informací</a>
        </p>
        <div id="cookie-bar-buttons">
            <button id="cookie-bar-reject">Nesouhlasím</button>
            <button id="cookie-bar-accept">Souhlasím</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('cookie-bar-container');
        const acceptBtn = document.getElementById('cookie-bar-accept');
        const rejectBtn = document.getElementById('cookie-bar-reject');
        const reopenBtn = document.getElementById('cookie-settings-reopen');

        const getCookie = (name) => {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
        };

        const setCookie = (name, value, days) => {
            let expires = "";
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
        };

        const handleConsent = (status) => {
            setCookie('cookie_consent_status', status, 365);
            container.classList.remove('show');
            reopenBtn.style.display = 'flex'; // Použijeme flex pro centrování ikony
        };

        if (!getCookie('cookie_consent_status')) {
            setTimeout(() => {
                container.classList.add('show');
            }, 100);
        } else {
            reopenBtn.style.display = 'flex'; // Použijeme flex pro centrování ikony
        }

        if(acceptBtn) acceptBtn.addEventListener('click', () => handleConsent('accepted'));
        if(rejectBtn) rejectBtn.addEventListener('click', () => handleConsent('rejected'));
        if(reopenBtn) reopenBtn.addEventListener('click', () => {
            setCookie('cookie_consent_status', '', -1);
            reopenBtn.style.display = 'none';
            container.classList.add('show');
        });
    });
    </script>
    <?php
}
