<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/config_manager.php';

$message = '';

// Zobrazí zprávu o úspěchu po přesměrování
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $message = "Nastavení bylo úspěšně uloženo.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config_content = get_config_content();
    
    // Aktualizace názvu webu
    if (isset($_POST['site_name'])) {
        $new_site_name = $_POST['site_name'];
        $config_content = preg_replace("/define\('SITE_NAME', '.*?'\);/", "define('SITE_NAME', '" . addslashes($new_site_name) . "');", $config_content);
    }

    // Aktualizace šablony
    if (isset($_POST['active_theme'])) {
        $new_theme = $_POST['active_theme'];
        $config_content = preg_replace("/define\('ACTIVE_THEME', '.*?'\);/", "define('ACTIVE_THEME', '" . addslashes($new_theme) . "');", $config_content);
    }

    // Aktualizace jazyků
    if (isset($_POST['languages'])) {
        $new_langs_string = format_languages_for_config($_POST['languages']);
        $config_content = preg_replace("/define\('SUPPORTED_LANGS', .*?\);/", "define('SUPPORTED_LANGS', " . $new_langs_string . ");", $config_content);
    }

    if (write_config_content($config_content)) {
        // Po úspěšném uložení přesměrujeme zpět na stránku s parametrem o úspěchu
        header('Location: settings.php?status=success');
        exit;
    } else {
        $message = "Chyba: Soubor config.php není zapisovatelný.";
    }
}

$available_themes = get_available_themes();
$page_title = "Hlavní nastavení";
include 'includes/header.php';
?>
<div class="warning">
    <strong>Varování:</strong> Úprava těchto hodnot mění soubor <code>config.php</code>. Nesprávné nastavení může způsobit nefunkčnost webu.
</div>

<?php if ($message): ?>
    <p style="color: green; font-weight: bold;"><?= $message ?></p>
<?php endif; ?>

<form action="settings.php" method="post">
    <div class="option-section">
        <h3>Základní</h3>
        <div class="option-group">
            <label for="site_name">Název webu</label>
            <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars(SITE_NAME) ?>">
        </div>
    </div>

    <div class="option-section">
        <h3>Šablona</h3>
        <div class="option-group">
            <label for="active_theme">Aktivní šablona vzhledu</label>
            <select name="active_theme" id="active_theme">
                <?php foreach($available_themes as $theme): ?>
                    <option value="<?= htmlspecialchars($theme) ?>" <?= ACTIVE_THEME == $theme ? 'selected' : '' ?>>
                        <?= htmlspecialchars($theme) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="option-section">
        <h3>Jazyky</h3>
        <p><small>Přidejte nebo odeberte podporované jazyky. První jazyk v seznamu je považován za výchozí.</small></p>
        <div id="languages-container">
            <?php $i = 0; ?>
            <?php foreach(SUPPORTED_LANGS as $code => $name): ?>
            <div class="language-pair" style="display: flex; gap: 10px; margin-bottom: 10px;">
                <input type="text" name="languages[<?= $i ?>][code]" value="<?= htmlspecialchars($code) ?>" placeholder="Kód (např. cs)">
                <input type="text" name="languages[<?= $i ?>][name]" value="<?= htmlspecialchars($name) ?>" placeholder="Název (např. Čeština)">
                <button type="button" class="remove-lang" style="background: #dc3545; color: white; border: 0; cursor: pointer;">Odebrat</button>
            </div>
            <?php $i++; ?>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-lang" style="margin-top: 10px;">Přidat jazyk</button>
    </div>

    <button type="submit" style="padding: 10px 20px;">Uložit nastavení</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('languages-container');
    // Začneme počítat od počtu již existujících jazyků
    let langIndex = <?= count(SUPPORTED_LANGS) ?>;
    
    document.getElementById('add-lang').addEventListener('click', function() {
        const div = document.createElement('div');
        div.className = 'language-pair';
        div.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
        div.innerHTML = `
            <input type="text" name="languages[${langIndex}][code]" placeholder="Kód (např. de)">
            <input type="text" name="languages[${langIndex}][name]" placeholder="Název (např. Deutsch)">
            <button type="button" class="remove-lang" style="background: #dc3545; color: white; border: 0; cursor: pointer;">Odebrat</button>
        `;
        container.appendChild(div);
        langIndex++; // Zvýšíme index pro další řádek
    });

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-lang')) {
            e.target.parentElement.remove();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
