<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/shortcodes.php';

// Zpracování požadavku na smazání
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    delete_shortcode((int)$_GET['id']);
    header('Location: shortcodes.php?status=deleted');
    exit;
}

$shortcodes = get_all_shortcodes();
$page_title = "Správa shortcodů";
include 'includes/header.php';
?>

<?php if (isset($_GET['status'])): ?>
    <p style="color: green; font-weight: bold;">Shortcode byl úspěšně smazán.</p>
<?php endif; ?>

<a href="shortcode_edit.php" style="display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px;">Přidat nový shortcode</a>

<table>
    <thead>
        <tr>
            <th>Název (tag)</th>
            <th>Shortcode</th>
            <th>Akce</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($shortcodes as $shortcode): ?>
        <tr>
            <td><?= htmlspecialchars($shortcode['name']) ?></td>
            <td><code>[<?= htmlspecialchars($shortcode['name']) ?>]</code></td>
            <td>
                <a href="shortcode_edit.php?id=<?= $shortcode['id'] ?>">Upravit</a>
                | <a href="shortcodes.php?action=delete&id=<?= $shortcode['id'] ?>" onclick="return confirm('Opravdu si přejete smazat tento shortcode?');">Smazat</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>
