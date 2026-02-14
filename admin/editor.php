<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';

$page_title = "Editor šablony";
$theme_dir = __DIR__ . '/../themes/' . ACTIVE_THEME . '/';
$message = '';

/**
 * Rekurzivní funkce pro načtení všech souborů v adresáři a podadresářích.
 * @param string $dir Adresář k prohledání.
 * @param string $base Základní cesta pro relativní výpis.
 * @return array Pole souborů s relativními cestami.
 */
function get_all_files($dir, $base = '') {
    $files = [];
    $items = scandir($dir . $base);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        $path = $dir . $base . '/' . $item;
        $relative_path = $base . '/' . $item;
        if (is_dir($path)) {
            $files = array_merge($files, get_all_files($dir, $relative_path));
        } else {
            $files[] = ltrim($relative_path, '/');
        }
    }
    return $files;
}

$editable_files = get_all_files($theme_dir);
$selected_file = $_GET['file'] ?? '';
$file_content = '';

// --- Vylepšené zabezpečení ---
$real_theme_dir = realpath($theme_dir);
$file_path = $theme_dir . $selected_file;
$real_file_path = realpath($file_path);

// Zkontrolujeme, zda se cesta k souboru nachází uvnitř adresáře šablony
if ($selected_file && ($real_file_path === false || strpos($real_file_path, $real_theme_dir) !== 0)) {
    die('Neplatný soubor nebo pokus o přístup mimo povolený adresář!');
}


// Uložení souboru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content']) && $selected_file) {
    if (is_writable($file_path)) {
        file_put_contents($file_path, $_POST['content']);
        $message = "Soubor " . htmlspecialchars($selected_file) . " byl úspěšně uložen.";
    } else {
        $message = "Chyba: Soubor " . htmlspecialchars($selected_file) . " není zapisovatelný.";
    }
}

// Načtení obsahu souboru pro zobrazení v editoru
if ($selected_file && file_exists($file_path) && is_file($file_path)) {
    $file_content = file_get_contents($file_path);
}

include 'includes/header.php';
?>

<div class="warning">
    <strong>Varování:</strong> Přímá úprava souborů šablony je riskantní. Chyba v kódu může způsobit nefunkčnost celého webu. Vždy si před úpravami vytvořte zálohu.
</div>

<?php if ($message): ?>
    <p style="color: green;"><?= $message ?></p>
<?php endif; ?>

<form action="editor.php" method="get">
    <label for="file">Vyberte soubor k úpravě:</label>
    <select name="file" id="file" onchange="this.form.submit()">
        <option value="">-- Vyberte soubor --</option>
        <?php foreach ($editable_files as $file): ?>
            <option value="<?= htmlspecialchars($file) ?>" <?= $selected_file == $file ? 'selected' : '' ?>>
                <?= htmlspecialchars($file) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($selected_file && !empty($file_content)): ?>
<hr>
<h3>Upravujete soubor: <?= htmlspecialchars($selected_file) ?></h3>
<form action="editor.php?file=<?= urlencode($selected_file) ?>" method="post">
    <textarea name="content" style="height: 60vh;"><?= htmlspecialchars($file_content) ?></textarea>
    <br><br>
    <button type="submit" style="padding: 10px 20px; font-size: 16px;">Uložit změny</button>
</form>
<?php elseif ($selected_file): ?>
<hr>
<p>Soubor "<?= htmlspecialchars($selected_file) ?>" nelze načíst nebo se jedná o adresář.</p>
<?php endif; ?>

<?php
include 'includes/footer.php';
?>
