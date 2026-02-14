<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/users.php';

// Zpracování požadavku na smazání
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    delete_user((int)$_GET['id']);
    header('Location: users.php?status=deleted');
    exit;
}

$users = get_all_users();
$page_title = "Správa uživatelů";
include 'includes/header.php';
?>

<?php if (isset($_GET['status'])): ?>
    <p style="color: green; font-weight: bold;">Uživatel byl úspěšně smazán.</p>
<?php endif; ?>

<a href="user_edit.php" style="display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px;">Přidat nového uživatele</a>

<table>
    <thead>
        <tr>
            <th>Uživatelské jméno</th>
            <th>Email</th>
            <th>Akce</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td>
                <a href="user_edit.php?id=<?= $user['id'] ?>">Upravit</a>
                <?php if ($user['id'] != 1): // Zabrání zobrazení odkazu pro smazání admina ?>
                    | <a href="users.php?action=delete&id=<?= $user['id'] ?>" onclick="return confirm('Opravdu si přejete smazat tohoto uživatele?');">Smazat</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>
