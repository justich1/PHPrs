<?php
/**
 * Správa kategorií blogu
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../functions/database.php';
require_once __DIR__ . '/functions-blog.php';

$category_id = $_GET['edit'] ?? null;
$message = '';

// Zpracování formulářů
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Zde by mělo být ověření CSRF
    if (isset($_POST['action']) && $_POST['action'] === 'save_category') {
        blog_save_category($_POST['category_id'], $_POST['translations']);
        header('Location: categories-blog.php?status=saved');
        exit;
    }
}

// Zpracování mazání
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    blog_delete_category((int)$_GET['id']);
    header('Location: categories-blog.php?status=deleted');
    exit;
}

$all_categories = blog_get_all_categories();
$edit_category = $category_id ? blog_get_category_details($category_id) : null;

?>
<style>
    .category-admin-container { display: flex; gap: 30px; }
    .form-column { flex: 1; }
    .list-column { flex: 2; }
    .widget { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 4px; }
    .widget h3 { margin-top: 0; }
    .widget label { display: block; margin-bottom: 5px; font-weight: bold; }
    .widget input[type="text"] { width: 100%; padding: 8px; box-sizing: border-box; margin-bottom: 10px; }
    .button { display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
    tr:nth-child(even) { background-color: #f2f2f2; }
</style>

<h1>Správa kategorií</h1>

<?php if (isset($_GET['status'])): ?>
    <p style="color: green; font-weight: bold;">
        <?php 
        if ($_GET['status'] === 'saved') echo 'Kategorie byla úspěšně uložena.';
        if ($_GET['status'] === 'deleted') echo 'Kategorie byla úspěšně smazána.';
        ?>
    </p>
<?php endif; ?>

<div class="category-admin-container">
    <div class="form-column">
        <div class="widget">
            <h3><?php echo $category_id ? 'Upravit kategorii' : 'Přidat novou kategorii'; ?></h3>
            <form action="categories-blog.php" method="post">
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="category_id" value="<?php echo $category_id; ?>">
                
                <?php foreach(SUPPORTED_LANGS as $code => $name): ?>
                    <h4><?php echo $name; ?></h4>
                    <?php $t = $edit_category['translations'][$code] ?? null; ?>
                    <div>
                        <label for="name_<?= $code ?>">Název:</label>
                        <input type="text" id="name_<?= $code ?>" name="translations[<?= $code ?>][name]" value="<?= htmlspecialchars($t['name'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="slug_<?= $code ?>">URL (slug):</label>
                        <input type="text" id="slug_<?= $code ?>" name="translations[<?= $code ?>][slug]" value="<?= htmlspecialchars($t['slug'] ?? '') ?>">
                    </div>
                <?php endforeach; ?>
                <br>
                <button type="submit" class="button">Uložit kategorii</button>
                <?php if ($category_id): ?>
                    <a href="categories-blog.php" style="margin-left: 10px;">Zrušit úpravy</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="list-column">
        <table>
            <thead>
                <tr>
                    <th>Název kategorie</th>
                    <th>URL (slug)</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_categories as $category): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><?php echo htmlspecialchars($category['slug']); ?></td>
                        <td>
                            <a href="categories-blog.php?edit=<?php echo $category['id']; ?>">Upravit</a> |
                            <a href="categories-blog.php?action=delete&id=<?php echo $category['id']; ?>" onclick="return confirm('Opravdu smazat?');">Smazat</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>