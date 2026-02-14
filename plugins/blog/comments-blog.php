<?php
/**
 * Správa komentářů blogu
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/database.php';
require_once __DIR__ . '/functions-blog.php';

// Zpracování akcí
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $comment_id = (int)$_GET['id'];
    // Zde by mělo být ověření CSRF

    if ($action === 'approve') {
        blog_update_comment_status($comment_id, 'approved');
    } elseif ($action === 'unapprove') {
        blog_update_comment_status($comment_id, 'pending');
    } elseif ($action === 'delete') {
        blog_delete_comment($comment_id);
    }
    header('Location: comments-blog.php');
    exit;
}

$comments = blog_get_all_comments_admin();
?>
<h1>Správa komentářů</h1>
<a href="admin-blog.php" style="display: inline-block; margin-bottom: 20px;">&larr; Zpět na přehled článků</a>

<style>
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .status-approved { color: green; }
    .status-pending { color: orange; font-weight: bold; }
    .comment-content { max-width: 400px; }
</style>

<table>
    <thead>
        <tr>
            <th>Autor</th>
            <th>Komentář</th>
            <th>V reakci na</th>
            <th>Datum</th>
            <th>Stav</th>
            <th>Akce</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($comments)): ?>
            <tr><td colspan="6">Nebyly nalezeny žádné komentáře.</td></tr>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($comment['author_name']); ?></strong><br>
                        <?php echo htmlspecialchars($comment['author_email']); ?>
                    </td>
                    <td class="comment-content"><?php echo htmlspecialchars($comment['content']); ?></td>
                    <td><a href="edit-post.php?id=<?php echo $comment['post_id']; ?>"><?php echo htmlspecialchars($comment['post_title']); ?></a></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?></td>
                    <td>
                        <span class="status-<?php echo htmlspecialchars($comment['status']); ?>">
                            <?php echo $comment['status'] === 'approved' ? 'Schváleno' : 'Čeká na schválení'; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($comment['status'] === 'pending'): ?>
                            <a href="?action=approve&id=<?php echo $comment['id']; ?>">Schválit</a>
                        <?php else: ?>
                            <a href="?action=unapprove&id=<?php echo $comment['id']; ?>">Zamítnout</a>
                        <?php endif; ?>
                        | <a href="?action=delete&id=<?php echo $comment['id']; ?>" onclick="return confirm('Opravdu smazat?');" style="color: red;">Smazat</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
