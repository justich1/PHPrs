<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/plugins.php';

$message = '';
$error = '';

// Zpracování akcí (aktivace, deaktivace, smazání)
$action = $_GET['action'] ?? '';
$plugin = $_GET['plugin'] ?? '';
if ($action && $plugin) {
    if ($action === 'activate') {
        activate_plugin($plugin);
        $message = "Plugin byl aktivován.";
    } elseif ($action === 'deactivate') {
        deactivate_plugin($plugin);
        $message = "Plugin byl deaktivován.";
    } elseif ($action === 'delete') {
        if (isset($_POST['confirm_delete'])) { // Potvrzení smazání
            deactivate_plugin($plugin); // Nejdříve deaktivovat
            delete_plugin($plugin);
            header('Location: plugins.php?status=deleted');
            exit;
        } else { // Zobrazit potvrzovací dialog
            $page_title = "Smazat plugin";
            include 'includes/header.php';
            echo "<h2>Opravdu si přejete smazat plugin '" . htmlspecialchars($plugin) . "'?</h2>";
            echo "<p class='warning'>Tato akce je nevratná a smaže všechny soubory pluginu.</p>";
            echo "<form method='post' action='plugins.php?action=delete&plugin=" . htmlspecialchars($plugin) . "'>";
            echo "<input type='hidden' name='confirm_delete' value='1'>";
            echo "<button type='submit' style='background: #dc3545; color: white;'>Ano, smazat</button> ";
            echo "<a href='plugins.php'>Zrušit</a>";
            echo "</form>";
            include 'includes/footer.php';
            exit;
        }
    }
    // Přesměrování po akci pro čisté URL
    if ($action === 'activate' || $action === 'deactivate') {
        header('Location: plugins.php?status=' . $action);
        exit;
    }
}

// Zpracování nahrání ZIPu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['plugin_zip'])) {
    $file = $_FILES['plugin_zip'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] === UPLOAD_ERR_OK && $file_ext === 'zip') {
        $zip = new ZipArchive;
        if ($zip->open($file['tmp_name']) === TRUE) {
            $plugins_dir = __DIR__ . '/../plugins/';
            $zip->extractTo($plugins_dir);
            $zip->close();
            $message = 'Plugin byl úspěšně nainstalován.';
        } else {
            $error = 'Nepodařilo se otevřít ZIP archiv.';
        }
    } else {
        // Podrobnější chybové hlášky
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = 'Nahrávaný soubor je příliš velký.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error = 'Nebyl vybrán žádný soubor.';
                break;
            default:
                $error = 'Chyba při nahrávání nebo neplatný formát souboru (vyžadován je .zip).';
                break;
        }
    }
}


$all_plugins = get_all_plugins();
$page_title = "Správa pluginů";
include 'includes/header.php';
?>

<?php if ($message): ?><p style="color: green; font-weight: bold;"><?= $message ?></p><?php endif; ?>
<?php if ($error): ?><p style="color: red; font-weight: bold;"><?= $error ?></p><?php endif; ?>

<div class="option-section">
    <h3>Nainstalovat plugin</h3>
    <form action="plugins.php" method="post" enctype="multipart/form-data">
        <label for="plugin_zip">Nahrát plugin v .zip formátu</label>
        <input type="file" name="plugin_zip" id="plugin_zip" accept=".zip">
        <button type="submit" style="margin-top: 10px;">Nainstalovat</button>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>Plugin</th>
            <th>Popis</th>
            <th>Akce</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($all_plugins as $folder => $plugin): ?>
        <tr style="<?= $plugin['is_active'] ? 'background-color: #eaf7ff;' : '' ?>">
            <td><strong><?= htmlspecialchars($plugin['name']) ?></strong></td>
            <td><?= htmlspecialchars($plugin['description']) ?></td>
            <td>
                <?php if ($plugin['is_active']): ?>
                    <a href="plugins.php?action=deactivate&plugin=<?= urlencode($folder) ?>">Deaktivovat</a>
                    <?php if ($plugin['settings_url']): ?>
                        | <a href="#" class="plugin-settings-link" data-url="../plugins/<?= urlencode($folder) ?>/<?= urlencode($plugin['settings_url']) ?>">Nastavení</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="plugins.php?action=activate&plugin=<?= urlencode($folder) ?>">Aktivovat</a>
                <?php endif; ?>
                | <a href="plugins.php?action=delete&plugin=<?= urlencode($folder) ?>" style="color: #dc3545;">Smazat</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('plugin-modal');
    const iframe = document.getElementById('plugin-modal-iframe');
    const closeBtn = document.getElementById('plugin-modal-close');

    document.querySelectorAll('.plugin-settings-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.dataset.url;
            iframe.src = url;
            modal.style.display = 'flex';
        });
    });

    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        iframe.src = 'about:blank'; // Vyčistí iframe
    });
});
</script>

<?php include 'includes/footer.php'; ?>
