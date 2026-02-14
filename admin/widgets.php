<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/widgets.php';

// --- ZPRACOVÁNÍ FORMULÁŘE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = db_connect();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $sidebar = in_array($_POST['sidebar'], ['left', 'right']) ? $_POST['sidebar'] : 'left';
        $position = filter_input(INPUT_POST, 'position', FILTER_VALIDATE_INT) ?? 0;

        if ($content && $sidebar) {
            if ($id) {
                $stmt = $db->prepare("UPDATE widgets SET title = ?, content = ?, sidebar = ?, position = ? WHERE id = ?");
                $stmt->execute([$title, $content, $sidebar, $position, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO widgets (title, content, sidebar, position) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $content, $sidebar, $position]);
            }
        }
    }

    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $db->prepare("DELETE FROM widgets WHERE id = ?");
            $stmt->execute([$id]);
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- ZOBRAZENÍ STRÁNKY ---
$widgets_left = get_sidebar_widgets_for_admin('left');
$widgets_right = get_sidebar_widgets_for_admin('right');
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa Widgetů</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f0f0f1; color: #1d2327; line-height: 1.5; margin: 0; }
        .wrap { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        h1, h2 { color: #2c3338; }
        h1 { border-bottom: 1px solid #ddd; padding-bottom: 1rem; margin-bottom: 1rem; }
        .widgets-container { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .sidebar-admin { background: #f6f7f7; padding: 1.5rem; border: 1px solid #ddd; border-radius: 4px; }
        .widget-card { background: #fff; border: 1px solid #c8d7e1; padding: 1.5rem; margin-bottom: 1rem; border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .widget-card h3 { margin: 0 0 1rem 0; font-size: 1rem; }
        form { display: flex; flex-direction: column; gap: 0.75rem; }
        label { font-weight: 600; display: block; margin-bottom: .25rem; }
        input[type="text"], input[type="number"], textarea, select { width: 100%; padding: 0.5rem; border: 1px solid #8c8f94; border-radius: 3px; box-sizing: border-box; }
        textarea { min-height: 120px; resize: vertical; }
        .form-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; }
        button { font-size: 14px; padding: 0.6rem 1rem; border: 1px solid transparent; border-radius: 3px; cursor: pointer; color: white; text-decoration: none; display: inline-block; }
        .btn-primary { background: #2271b1; border-color: #2271b1; }
        .btn-primary:hover { background: #1e659d; }
        .btn-danger { background: #d63638; border-color: #d63638; }
        .btn-danger:hover { background: #b92e30; }
        .new-widget-form { margin-top: 2rem; padding: 2rem; border: 1px solid #c8d7e1; background: #fff; border-radius: 4px; }
        @media (max-width: 782px) { .widgets-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Správa Widgetů</h1>
    <p>Zde můžete spravovat obsah levého a pravého postranního panelu. Obsah widgetu podporuje shortcody.</p>

    <div class="widgets-container">
        <div class="sidebar-admin">
            <h2>Levý panel</h2>
            <?php foreach ($widgets_left as $widget): ?>
                <div class="widget-card">
                    <form method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= $widget['id'] ?>">
                        <div><label>Titulek:</label><input type="text" name="title" value="<?= htmlspecialchars($widget['title']) ?>"></div>
                        <div><label>Obsah:</label><textarea name="content" required><?= htmlspecialchars($widget['content']) ?></textarea></div>
                        <div><label>Panel:</label>
                            <select name="sidebar">
                                <option value="left" selected>Levý</option>
                                <option value="right">Pravý</option>
                            </select>
                        </div>
                        <div><label>Pořadí:</label><input type="number" name="position" value="<?= $widget['position'] ?>"></div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Uložit</button>
                            <button type="submit" name="action" value="delete" class="btn-danger" onclick="return confirm('Smazat widget?');">Smazat</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-admin">
            <h2>Pravý panel</h2>
            <?php foreach ($widgets_right as $widget): ?>
                 <div class="widget-card">
                    <form method="POST">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= $widget['id'] ?>">
                        <div><label>Titulek:</label><input type="text" name="title" value="<?= htmlspecialchars($widget['title']) ?>"></div>
                        <div><label>Obsah:</label><textarea name="content" required><?= htmlspecialchars($widget['content']) ?></textarea></div>
                        <div><label>Panel:</label>
                            <select name="sidebar">
                                <option value="left">Levý</option>
                                <option value="right" selected>Pravý</option>
                            </select>
                        </div>
                        <div><label>Pořadí:</label><input type="number" name="position" value="<?= $widget['position'] ?>"></div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">Uložit</button>
                            <button type="submit" name="action" value="delete" class="btn-danger" onclick="return confirm('Smazat widget?');">Smazat</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="new-widget-form">
        <h2>Přidat nový widget</h2>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <div><label>Titulek:</label><input type="text" name="title" placeholder="Název widgetu"></div>
            <div><label>Obsah:</label><textarea name="content" placeholder="Zadejte obsah, můžete použít shortcody..." required></textarea></div>
            <div><label>Umístění:</label>
                <select name="sidebar">
                    <option value="left">Levý panel</option>
                    <option value="right">Pravý panel</option>
                </select>
            </div>
            <div><label>Pořadí:</label><input type="number" name="position" value="0"></div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Přidat widget</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>

<?php
include 'includes/footer.php';
?>
