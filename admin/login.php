<?php
require_once 'debug.php'; // TENTO ŘÁDEK PŘIDEJTE
session_start();
require_once '../config/config.php';
require_once '../functions/database.php';

$error_message = '';

// Zpracování přihlašovacího formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Prosím, vyplňte uživatelské jméno i heslo.';
    } else {
        try {
            $db = db_connect();
            $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            // Ověření hesla pomocí password_verify
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: index.php');
                exit;
            } else {
                $error_message = 'Nesprávné uživatelské jméno nebo heslo.';
            }
        } catch (\PDOException $e) {
            $error_message = 'Chyba databáze. Zkuste to prosím později.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení do administrace</title>
<style>
    body { 
        font-family: sans-serif; 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        height: 100vh; 
        background-color: #f0f2f5; 
        margin: 0;
        padding: 1rem;
    }
    .login-box { 
        background: white; 
        padding: 2rem; 
        border-radius: 8px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
        width: 300px; 
        max-width: 100%;
        box-sizing: border-box;
    }
    h2 { 
        text-align: center; 
        margin-bottom: 1.5rem; 
    }
    .input-group { 
        margin-bottom: 1rem; 
    }
    label { 
        display: block; 
        margin-bottom: 0.5rem; 
    }
    input { 
        width: 100%; 
        padding: 0.5rem; 
        border: 1px solid #ccc; 
        border-radius: 4px; 
        box-sizing: border-box; 
    }
    button { 
        width: 100%; 
        padding: 0.75rem; 
        border: none; 
        border-radius: 4px; 
        background-color: #007bff; 
        color: white; 
        font-size: 1rem; 
        cursor: pointer; 
        transition: background-color 0.3s ease;
    }
    button:hover { 
        background-color: #0056b3; 
    }
    .error { 
        color: #dc3545; 
        text-align: center; 
        margin-bottom: 1rem; 
    }

    /* Responsivita */
    @media (max-width: 400px) {
        body {
            padding: 0.5rem;
            height: auto;
            min-height: 100vh;
        }
        .login-box {
            width: 100%;
            padding: 1.5rem;
        }
    }
</style>

</head>
<body>
    <div class="login-box">
        <h2>Přihlášení</h2>
        <?php if ($error_message): ?>
            <p class="error"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>
        <form action="login.php" method="post">
            <div class="input-group">
                <label for="username">Uživatelské jméno:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="input-group">
                <label for="password">Heslo:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Přihlásit se</button>
        </form>
    </div>
</body>
</html>
