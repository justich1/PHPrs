<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/pages.php';

// Zpracování akcí
$action = $_GET['action'] ?? '';
$page_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'delete' && $page_id) {
    delete_page($page_id);
    header('Location: pages.php?status=deleted');
    exit;
}

if ($action === 'set_homepage' && $page_id) {
    set_homepage($page_id);
    header('Location: pages.php?status=homepage_set');
    exit;
}


$pages = get_all_pages();
$page_title = "Správa stránek";
include 'includes/header.php';
?>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] === 'deleted'): ?>
        <p style="color: green; font-weight: bold;">Stránka byla úspěšně smazána.</p>
    <?php elseif ($_GET['status'] === 'homepage_set'): ?>
        <p style="color: green; font-weight: bold;">Hlavní stránka byla úspěšně nastavena.</p>
    <?php endif; ?>
<?php endif; ?>

<a href="page_edit.php" style="display: inline-block; margin-bottom: 20px; padding: 10px 15px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px;">Přidat novou stránku</a>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Název (výchozí jazyk)</th>
            <th>Vytvořeno</th>
            <th>Akce</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($pages)): ?>
            <tr>
                <td colspan="4">Nebyly nalezeny žádné stránky.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($pages as $page): ?>
            <tr>
                <td><?= $page['id'] ?></td>
                <td>
                    <?= htmlspecialchars($page['title']) ?>
                    <?php if ($page['is_homepage']): ?>
                        <strong>(Hlavní stránka)</strong>
                    <?php endif; ?>
                </td>
                <td><?= date('d. m. Y', strtotime($page['created_at'])) ?></td>
                <td>
                    <a href="page_edit.php?id=<?= $page['id'] ?>">Upravit</a> |
                    <a href="pages.php?action=delete&id=<?= $page['id'] ?>" onclick="return confirm('Opravdu si přejete smazat tuto stránku?');">Smazat</a>
                    <?php if (!$page['is_homepage']): ?>
                        | <a href="pages.php?action=set_homepage&id=<?= $page['id'] ?>">Nastavit jako hlavní</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>
