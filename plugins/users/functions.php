<?php
/**
 * Pomocné funkce pro plugin Uživatelé
 */

/**
 * Získá hodnotu nastavení z databáze.
 */
function user_get_setting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        try {
            $db = db_connect();
            $stmt = $db->query("SELECT * FROM plugin_client_settings");
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
function user_update_setting($key, $value) {
    try {
        $db = db_connect();
        $stmt = $db->prepare("INSERT INTO plugin_client_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Samotná logika pro odeslání e-mailu přes SMTP.
 * @param string $to Adresát.
 * @param string $subject Předmět.
 * @param string $body Tělo zprávy.
 * @param array $config Pole s SMTP konfigurací (host, port, user, pass, encryption).
 * @return bool|string True v případě úspěchu, chybová zpráva v případě neúspěchu.
 */
function user_send_smtp_email($to, $subject, $body, $config) {
    $prefix = '';
    if ($config['encryption'] === 'ssl') {
        $prefix = 'ssl://';
    }

    $socket = @fsockopen($prefix . $config['host'], $config['port'], $errno, $errstr, 15);
    if (!$socket) {
        return "Nepodařilo se připojit k SMTP serveru: $errstr ($errno)";
    }

    $server_response = fgets($socket, 512);
    if (substr($server_response, 0, 3) != '220') { return "Chyba SMTP (připojení): $server_response"; }

    $read_response = function($socket, $expected_code) {
        $response = '';
        while (substr($response, 3, 1) != ' ') {
            if (!($response = fgets($socket, 512))) { return false; }
            if (substr($response, 0, 3) != $expected_code) { return $response; }
        }
        return true;
    };

    $server_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    fputs($socket, "EHLO $server_host\r\n");
    $ehlo_response = $read_response($socket, '250');
    if ($ehlo_response !== true) { return "Chyba SMTP (EHLO): $ehlo_response"; }

    if ($config['encryption'] === 'tls') {
        fputs($socket, "STARTTLS\r\n");
        $starttls_response = fgets($socket, 512);
        if (substr($starttls_response, 0, 3) != '220') { return "Chyba SMTP (STARTTLS): $starttls_response"; }
        
        if (!function_exists('stream_socket_enable_crypto')) {
            return "Chyba: Pro TLS šifrování je vyžadováno PHP rozšíření OpenSSL, které není na serveru dostupné.";
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return "Nepodařilo se zapnout TLS šifrování.";
        }
        
        fputs($socket, "EHLO $server_host\r\n");
        $ehlo_tls_response = $read_response($socket, '250');
        if ($ehlo_tls_response !== true) { return "Chyba SMTP (EHLO po STARTTLS): $ehlo_tls_response"; }
    }

    if (!empty($config['user']) && !empty($config['pass'])) {
        fputs($socket, "AUTH LOGIN\r\n");
        $server_response = fgets($socket, 512);
        if (substr($server_response, 0, 3) != '334') { return "Chyba SMTP (AUTH LOGIN): $server_response"; }

        fputs($socket, base64_encode($config['user']) . "\r\n");
        $server_response = fgets($socket, 512);
        if (substr($server_response, 0, 3) != '334') { return "Chyba SMTP (Uživatel): $server_response"; }

        fputs($socket, base64_encode($config['pass']) . "\r\n");
        $server_response = fgets($socket, 512);
        if (substr($server_response, 0, 3) != '235') { return "Chyba SMTP (Heslo): $server_response"; }
    }

    fputs($socket, "MAIL FROM: <{$config['user']}>\r\n");
    $server_response = fgets($socket, 512);
    if (substr($server_response, 0, 3) != '250') { return "Chyba SMTP (MAIL FROM): $server_response"; }

    fputs($socket, "RCPT TO: <$to>\r\n");
    $server_response = fgets($socket, 512);
    if (substr($server_response, 0, 3) != '250') { return "Chyba SMTP (RCPT TO): $server_response"; }

    fputs($socket, "DATA\r\n");
    $server_response = fgets($socket, 512);
    if (substr($server_response, 0, 3) != '354') { return "Chyba SMTP (DATA): $server_response"; }

    $headers = "From: Váš Web <{$config['user']}>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");

    $server_response = fgets($socket, 512);
    if (substr($server_response, 0, 3) != '250') { return "Chyba SMTP (Odeslání obsahu): $server_response"; }

    fputs($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

/**
 * Odesílá e-mail. Načte nastavení a rozhodne, zda použít SMTP nebo mail().
 */
function user_send_email($to, $subject, $body) {
    $smtp_host = user_get_setting('smtp_host');
    
    if (empty($smtp_host)) {
        $server_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $headers = "Content-Type: text/plain; charset=utf-8\r\n";
        $headers .= "From: Váš Web <noreply@" . $server_host . ">\r\n";
        $headers .= "Reply-To: noreply@" . $server_host . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        return mail($to, $subject, $body, $headers);
    }

    $config = [
        'host' => $smtp_host,
        'port' => user_get_setting('smtp_port'),
        'user' => user_get_setting('smtp_user'),
        'pass' => user_get_setting('smtp_pass'),
        'encryption' => user_get_setting('smtp_encryption', 'none')
    ];

    $result = user_send_smtp_email($to, $subject, $body, $config);
    return $result === true;
}

/**
 * Vygeneruje náhodný bezpečný token.
 */
function user_generate_token() {
    return bin2hex(random_bytes(32));
}

/**
 * Vygeneruje CSRF token a uloží ho do session.
 */
function user_generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Ověří CSRF token.
 */
function user_verify_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token'], $token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Zobrazí formátovanou zprávu (úspěch/chyba).
 */
function user_display_message($message, $is_error = false) {
    $class = $is_error ? 'user-plugin-error' : 'user-plugin-success';
    return "<div class=\"{$class}\" style=\"padding: 15px; margin-bottom: 20px; border: 1px solid; border-radius: 4px; " . ($is_error ? 'color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;' : 'color: #155724; background-color: #d4edda; border-color: #c3e6cb;') . "\">" . htmlspecialchars($message) . "</div>";
}

/**
 * Pomocná funkce pro vypsání 'selected' atributu v select boxu.
 */
function user_plugin_selected($value, $current) {
    if ($value == $current) {
        echo ' selected="selected"';
    }
}
