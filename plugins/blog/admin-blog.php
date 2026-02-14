<?php
/**
 * Hlavní stránka administrace blogu - Seznam článků
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/database.php';
require_once __DIR__ . '/functions-blog.php';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    blog_delete_post((int)$_GET['id']);
    header('Location: admin-blog.php?status=deleted');
    exit;
}

$posts = blog_get_all_posts();
$pending_comments = blog_get_pending_comments_count();
?>

<style>
    .blog-admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .blog-admin-header h1 { margin: 0; }
    .button { display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
    .button-secondary { background-color: #6c757d; }
    .button-warning { background-color: #ffc107; color: #000; }
    .pending-count { background-color: #dc3545; color: white; border-radius: 50%; padding: 2px 8px; font-size: 0.8em; margin-left: 5px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .status-published { color: green; font-weight: bold; }
    .status-draft { color: orange; font-weight: bold; }
</style>

<div class="blog-admin-header">
    <h1>Správa článků</h1>
    <div>
        <?php if ($pending_comments > 0): ?>
            <a href="comments-blog.php" class="button button-warning">
                Komentáře ke schválení <span class="pending-count"><?php echo $pending_comments; ?></span>
            </a>
        <?php endif; ?>
        <a href="categories-blog.php" class="button button-secondary">Spravovat kategorie</a>
        <a href="edit-post.php" class="button">Vytvořit nový článek</a>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
    <p style="color: green; font-weight: bold;">
        <?php 
        if ($_GET['status'] === 'deleted') echo 'Článek byl úspěšně smazán.';
        if ($_GET['status'] === 'saved') echo 'Článek byl úspěšně uložen.';
        ?>
    </p>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>Název článku</th>
            <th>Stav</th>
            <th>Datum vytvoření</th>
            <th>Akce</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($posts)): ?>
            <tr><td colspan="4">Zatím nebyly vytvořeny žádné články.</td></tr>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <tr>
                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                    <td>
                        <span class="status-<?php echo htmlspecialchars($post['status']); ?>">
                            <?php echo $post['status'] === 'published' ? 'Publikováno' : 'Koncept'; ?>
                        </span>
                    </td>
                    <td><?php echo date('d. m. Y H:i', strtotime($post['created_at'])); ?></td>
                    <td>
                        <a href="edit-post.php?id=<?php echo $post['id']; ?>">Upravit</a> |
                        <a href="admin-blog.php?action=delete&id=<?php echo $post['id']; ?>" onclick="return confirm('Opravdu chcete smazat tento článek?');">Smazat</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
