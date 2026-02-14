<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/menus.php';

$menus = get_all_menus();
$page_title = "Správa menu";
include 'includes/header.php';
?>

<p>Vyberte menu, které si přejete upravit. Nová menu se v budoucnu budou přidávat zde.</p>

<table>
    <thead>
        <tr>
            <th>Název menu</th>
            <th>Umístění</th>
            <th>Akce</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($menus)): ?>
            <tr>
                <td colspan="3">Nebyly nalezeny žádné struktury menu.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($menus as $menu): ?>
            <tr>
                <td><?= htmlspecialchars($menu['name']) ?></td>
                <td><?= htmlspecialchars($menu['location']) ?></td>
                <td>
                    <a href="menu_edit.php?id=<?= $menu['id'] ?>">Spravovat položky</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>
