<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/shortcodes.php';

$shortcode_id = $_GET['id'] ?? null;
$shortcode_data = $shortcode_id ? get_shortcode_by_id($shortcode_id) : null;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    // Jednoduchá validace
    if (empty($data['name'])) {
        $error = 'Název je povinný.';
    } else {
        // Očistíme název od nežádoucích znaků
        $data['name'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['name']);
        
        if ($shortcode_id) {
            update_shortcode($shortcode_id, $data);
        } else {
            create_shortcode($data);
        }
        header('Location: shortcodes.php');
        exit;
    }
}

$page_title = $shortcode_id ? "Úprava shortcodu" : "Vytvoření nového shortcodu";
include 'includes/header.php';
?>
<div class="warning">
    <strong>Varování:</strong> Do obsahu můžete vkládat HTML a PHP kód. Nesprávně napsaný PHP kód může způsobit nefunkčnost celého webu!
</div>

<?php if ($error): ?>
    <p style="color: red; font-weight: bold;"><?= $error ?></p>
<?php endif; ?>

<form action="shortcode_edit.php<?= $shortcode_id ? '?id='.$shortcode_id : '' ?>" method="post">
    <div style="margin-bottom: 1rem;">
        <label for="name">Název shortcodu (bez závorek):</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($shortcode_data['name'] ?? '') ?>" required style="width:100%; padding: 5px;">
        <small>Pouze písmena, čísla, pomlčka a podtržítko. Např. <code>kontaktni_formular</code></small>
    </div>
    <div style="margin-bottom: 1rem;">
        <label for="content">Obsah (HTML/PHP kód):</label>
        <textarea id="content" name="content" rows="20" style="width:100%; font-family: monospace;"><?= htmlspecialchars($shortcode_data['content'] ?? '') ?></textarea>
        <small>Příklad pro vypsání aktuálního roku: <code>&lt;?php echo date('Y'); ?&gt;</code></small>
    </div>
    <br>
    <button type="submit" style="padding: 10px 20px;">Uložit</button>
</form>

<?php include 'includes/footer.php'; ?>
