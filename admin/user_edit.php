<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/users.php';

$user_id = $_GET['id'] ?? null;
$user_data = $user_id ? get_user_by_id($user_id) : null;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    // Validace
    if (empty($data['username']) || empty($data['email'])) {
        $error = 'Uživatelské jméno a email jsou povinné.';
    } elseif (!empty($data['password']) && $data['password'] !== $data['password_confirm']) {
        $error = 'Hesla se neshodují.';
    } elseif (empty($data['password']) && !$user_id) {
        $error = 'Heslo je pro nového uživatele povinné.';
    } else {
        if ($user_id) {
            update_user($user_id, $data);
            header('Location: users.php');
            exit;
        } else {
            create_user($data);
            header('Location: users.php');
            exit;
        }
    }
}

$page_title = $user_id ? "Úprava uživatele" : "Vytvoření nového uživatele";
include 'includes/header.php';
?>

<?php if ($error): ?>
    <p style="color: red; font-weight: bold;"><?= $error ?></p>
<?php endif; ?>

<form action="user_edit.php<?= $user_id ? '?id='.$user_id : '' ?>" method="post">
    <div style="margin-bottom: 1rem;">
        <label for="username">Uživatelské jméno:</label>
        <input type="text" id="username" name="username" value="<?= htmlspecialchars($user_data['username'] ?? '') ?>" required style="width:100%; padding: 5px;">
    </div>
    <div style="margin-bottom: 1rem;">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required style="width:100%; padding: 5px;">
    </div>
    <hr style="margin: 2rem 0;">
    <p><small>Vyplňte pouze pokud chcete změnit heslo.</small></p>
    <div style="margin-bottom: 1rem;">
        <label for="password">Nové heslo:</label>
        <input type="password" id="password" name="password" style="width:100%; padding: 5px;">
    </div>
    <div style="margin-bottom: 1rem;">
        <label for="password_confirm">Potvrzení nového hesla:</label>
        <input type="password" id="password_confirm" name="password_confirm" style="width:100%; padding: 5px;">
    </div>
    <br>
    <button type="submit" style="padding: 10px 20px;">Uložit změny</button>
</form>

<?php include 'includes/footer.php'; ?>
