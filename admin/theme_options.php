<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/config.php';
require_once '../functions/database.php';
require_once '../functions/theme.php';

$message = '';
$upload_dir_path = __DIR__ . '/../uploads/';
$upload_dir_url = '/uploads/';

// Vytvoření adresáře pro nahrávání, pokud neexistuje
if (!is_dir($upload_dir_path)) {
    mkdir($upload_dir_path, 0755, true);
}

// Zpracování smazání loga
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logo'])) {
    $current_logo_path_db = get_theme_option('logo_url', '');
    if (!empty($current_logo_path_db)) {
        $file_to_delete = __DIR__ . '/..' . $current_logo_path_db;
        if (file_exists($file_to_delete) && is_file($file_to_delete)) {
            unlink($file_to_delete);
        }
    }
    $_POST['options']['logo_url'] = ''; // Připraví prázdnou hodnotu pro uložení
    $message = "Logo bylo smazáno. ";
}

// Zpracování nahrání loga
if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['logo_upload'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
    
    if (in_array($file['type'], $allowed_types) && $file['size'] < 2000000) { // Limit 2MB
        $filename = uniqid('logo_') . '-' . basename($file['name']);
        $target_path = $upload_dir_path . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $_POST['options']['logo_url'] = $upload_dir_url . $filename;
            $message .= "Logo bylo úspěšně nahráno.";
        } else {
            $message .= "Chyba při přesouvání souboru.";
        }
    } else {
        $message .= "Neplatný typ souboru nebo soubor je příliš velký.";
    }
}

// Uložení všech nastavení
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['options'])) {
    try {
        $db = db_connect();
        $sql = "INSERT INTO theme_options (option_name, option_value) VALUES (:name, :value)
                ON DUPLICATE KEY UPDATE option_value = :value_update";
        $stmt = $db->prepare($sql);

        foreach ($_POST['options'] as $name => $value) {
            $stmt->execute(['name' => $name, 'value' => $value, 'value_update' => $value]);
        }
        $message .= " Nastavení bylo uloženo.";
    } catch (\PDOException $e) {
        $message = "Chyba při ukládání: " . $e->getMessage();
    }
}

$page_title = "Přizpůsobení vzhledu";
include 'includes/header.php'; 
?>

<!-- Vlastní styly pro rozložení editoru -->
<style>
    .customizer-container { display: flex; height: calc(100vh - 120px); }
    .customizer-controls { width: 380px; padding: 0 20px; overflow-y: auto; border-right: 1px solid #dee2e6; }
    .customizer-preview { flex-grow: 1; position: relative; }
    .preview-iframe { width: 100%; height: 100%; border: 1px solid #ccc; transition: width 0.3s ease-in-out; background: #fff; }
    .preview-actions { text-align: center; margin-bottom: 1rem; border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; }
    .preview-actions button { padding: 5px 10px; cursor: pointer; }
    .preview-iframe.mobile { width: 375px; height: 667px; margin: 0 auto; border: 8px solid #333; border-radius: 20px; box-shadow: 0 0 15px rgba(0,0,0,0.2); }
    .option-section { margin-bottom: 2rem; }
    .option-section h3 { border-bottom: 2px solid #dee2e6; padding-bottom: 0.5rem; margin-bottom: 1.5rem; color: #343a40; }
    .option-group { margin-bottom: 1.5rem; }
    .option-group label { display: block; font-weight: bold; margin-bottom: 0.5rem; }
    .option-group input, .option-group select { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .option-group input[type="color"] { height: 40px; padding: 0.25rem; }
    .option-group small { color: #6c757d; display: block; margin-top: 0.25rem; font-size: 0.85em; }
    .input-suffix { position: relative; }
    .input-suffix span { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6c757d; }
    .device-inputs { display: flex; gap: 10px; }
    .device-inputs > div { flex: 1; }
    .current-logo { max-width: 200px; max-height: 80px; background: #eee; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; }
</style>

<?php if ($message): ?>
    <p style="color: green; font-weight: bold;"><?= trim($message) ?></p>
<?php endif; ?>

<form action="theme_options.php" method="post" id="customizer-form" enctype="multipart/form-data">
    <div class="customizer-container">
        <!-- LEVÝ PANEL S NASTAVENÍM -->
        <div class="customizer-controls">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #dee2e6;">
                <h2 style="margin:0;">Možnosti vzhledu</h2>
                <button type="submit" style="padding: 10px 20px; background-color: #007bff; color: white; border:0; border-radius: 4px; cursor: pointer;">Uložit</button>
            </div>
            
            <div class="option-section">
                <h3>Hlavička</h3>
                <div class="option-group">
                    <label>Aktuální logo</label>
                    <?php $current_logo = get_theme_option('logo_url'); ?>
                    <?php if ($current_logo): ?>
                        <img src="<?= htmlspecialchars($current_logo) ?>" class="current-logo">
                        <button type="submit" name="delete_logo" value="1" onclick="return confirm('Opravdu smazat logo?');">Smazat logo</button>
                    <?php else: ?>
                        <p><small>Logo není nahráno.</small></p>
                    <?php endif; ?>
                </div>
                <div class="option-group">
                    <label for="logo_upload">Nahrát nové logo</label>
                    <input type="file" id="logo_upload" name="logo_upload">
                    <small>Doporučený formát: PNG, JPG, GIF, SVG. Max 2MB.</small>
                </div>
                <div class="option-group">
                    <label for="header_bg_color">Barva pozadí hlavičky</label>
                    <input type="color" id="header_bg_color" name="options[header_bg_color]" value="<?= htmlspecialchars(get_theme_option('header_bg_color', '#343a40')) ?>" data-css-var="--header-bg-color">
                </div>
                <div class="option-group">
                    <label for="header_padding">Vnitřní odsazení (padding)</label>
                    <div class="input-suffix">
                        <input type="number" id="header_padding" name="options[header_padding]" value="<?= htmlspecialchars(get_theme_option('header_padding', '1')) ?>" min="0.5" max="3" step="0.1" data-css-var="--header-padding" data-unit="rem">
                        <span>rem</span>
                    </div>
                </div>
            </div>

            <div class="option-section">
                <h3>Navigace</h3>
                <div class="option-group">
                    <label for="nav_link_color">Barva textu odkazů</label>
                    <input type="color" id="nav_link_color" name="options[nav_link_color]" value="<?= htmlspecialchars(get_theme_option('nav_link_color', '#f8f9fa')) ?>" data-css-var="--nav-link-color">
                </div>
                <div class="option-group">
                    <label for="nav_link_hover_bg">Barva pozadí odkazu po najetí</label>
                    <input type="color" id="nav_link_hover_bg" name="options[nav_link_hover_bg]" value="<?= htmlspecialchars(get_theme_option('nav_link_hover_bg', '#495057')) ?>" data-css-var="--nav-link-hover-bg">
                </div>
            </div>

            <div class="option-section">
                <h3>Barvy</h3>
                <div class="option-group">
                    <label for="background_color">Barva pozadí stránky</label>
                    <input type="color" id="background_color" name="options[background_color]" value="<?= htmlspecialchars(get_theme_option('background_color', '#f8f9fa')) ?>" data-css-var="--background-color">
                </div>
                <div class="option-group">
                    <label for="container_bg_color">Barva pozadí obsahu</label>
                    <input type="color" id="container_bg_color" name="options[container_bg_color]" value="<?= htmlspecialchars(get_theme_option('container_bg_color', '#ffffff')) ?>" data-css-var="--container-bg-color">
                </div>
                <div class="option-group">
                    <label for="text_color">Barva textu</label>
                    <input type="color" id="text_color" name="options[text_color]" value="<?= htmlspecialchars(get_theme_option('text_color', '#212529')) ?>" data-css-var="--text-color">
                </div>
                <div class="option-group">
                    <label for="link_color">Barva odkazů v obsahu</label>
                    <input type="color" id="link_color" name="options[link_color]" value="<?= htmlspecialchars(get_theme_option('link_color', '#007bff')) ?>" data-css-var="--link-color">
                </div>
            </div>

            <div class="option-section">
                <h3>Typografie</h3>
<div class="option-group">
    <label for="font_family">Rodina písma</label>
    <select id="font_family" name="options[font_family]" data-css-var="--font-family">
        <?php $current_font = get_theme_option('font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'); ?>

        <option value='-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif' <?= $current_font == '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif' ? 'selected' : '' ?>>Systémové (Sans-serif)</option>

        <?php
        $system_fonts = [
            ["Arial", "Arial, Helvetica, sans-serif"],
            ["Verdana", "Verdana, Geneva, sans-serif"],
            ["Tahoma", "Tahoma, Geneva, sans-serif"],
            ["Trebuchet MS", "\"Trebuchet MS\", Helvetica, sans-serif"],
            ["Gill Sans", "\"Gill Sans\", Calibri, sans-serif"],
            ["Segoe UI", "\"Segoe UI\", Tahoma, Geneva, sans-serif"],
            ["Calibri", "Calibri, Candara, sans-serif"],
            ["Cambria", "Cambria, Georgia, serif"],
            ["Helvetica Neue", "\"Helvetica Neue\", Helvetica, Arial, sans-serif"],
            ["Lucida Grande", "\"Lucida Grande\", sans-serif"],
            ["Geneva", "Geneva, Tahoma, sans-serif"],
            ["Impact", "Impact, Charcoal, sans-serif"],
            ["Franklin Gothic Medium", "\"Franklin Gothic Medium\", Arial, sans-serif"],
            ["System UI", "system-ui, sans-serif"],
            ["Sans-serif", "sans-serif"],

            ["Georgia", "Georgia, serif"],
            ["Times New Roman", "\"Times New Roman\", Times, serif"],
            ["Palatino Linotype", "\"Palatino Linotype\", Palatino, serif"],
            ["Book Antiqua", "\"Book Antiqua\", Palatino, serif"],
            ["Garamond", "Garamond, serif"],
            ["Baskerville", "Baskerville, serif"],
            ["Didot", "Didot, serif"],
            ["Constantia", "Constantia, serif"],
            ["Big Caslon", "\"Big Caslon\", serif"],
            ["Lucida Bright", "\"Lucida Bright\", serif"],
            ["Serif", "serif"],

            ["Courier New", "\"Courier New\", Courier, monospace"],
            ["Lucida Console", "\"Lucida Console\", Monaco, monospace"],
            ["Monaco", "Monaco, Consolas, monospace"],
            ["Consolas", "Consolas, \"Courier New\", monospace"],
            ["Andale Mono", "\"Andale Mono\", monospace"],
            ["Courier", "Courier, monospace"],
            ["Monospace", "monospace"],

            ["Comic Sans MS", "\"Comic Sans MS\", cursive, sans-serif"],
            ["Brush Script MT", "\"Brush Script MT\", cursive"],
            ["Segoe Script", "\"Segoe Script\", cursive"],
            ["Segoe Print", "\"Segoe Print\", cursive"],
            ["Papyrus", "Papyrus, fantasy"],
            ["Fantasy", "fantasy"],
            ["Cursive", "cursive"],

            ["Symbol", "Symbol"],
            ["Wingdings", "Wingdings"],
            ["Webdings", "Webdings"],
            ["Zapf Dingbats", "\"Zapf Dingbats\""],
            ["Arial Black", "\"Arial Black\", Gadget, sans-serif"],
            ["Lucida Sans", "\"Lucida Sans\", Verdana, sans-serif"],
            ["Century Gothic", "\"Century Gothic\", sans-serif"],
            ["Optima", "Optima, sans-serif"],
            ["Futura", "Futura, sans-serif"],
            ["Rockwell", "Rockwell, serif"]
        ];

        foreach ($system_fonts as [$name, $css]) {
            $type = (strpos($css, 'serif') !== false && strpos($css, 'monospace') === false) ? '(Serif)' :
                    (strpos($css, 'monospace') !== false ? '(Monospace)' :
                    (strpos($css, 'cursive') !== false ? '(Cursive)' :
                    (strpos($css, 'fantasy') !== false ? '(Fantasy)' : '(Sans-serif)')));
            echo "<option value='$css'" . ($current_font == $css ? ' selected' : '') . ">$name $type</option>";
        }
        ?>
    </select>
</div>

                <div class="option-group">
                    <label>Základní velikost písma</label>
                    <div class="device-inputs">
                        <div>
                            <small>Desktop</small>
                            <div class="input-suffix">
                                <input type="number" name="options[font_size_base]" value="<?= htmlspecialchars(get_theme_option('font_size_base', '16')) ?>" min="10" max="24" data-css-var="--font-size-base" data-unit="px">
                                <span>px</span>
                            </div>
                        </div>
                        <div>
                            <small>Mobil</small>
                            <div class="input-suffix">
                                <input type="number" name="options[font_size_base_mobile]" value="<?= htmlspecialchars(get_theme_option('font_size_base_mobile', '15')) ?>" min="10" max="24" data-css-var="--font-size-base" data-unit="px" data-device="mobile">
                                <span>px</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="option-group">
                    <label>Velikost nadpisu H1</label>
                    <div class="device-inputs">
                        <div>
                            <small>Desktop</small>
                            <div class="input-suffix">
                                <input type="number" name="options[font_size_h1]" value="<?= htmlspecialchars(get_theme_option('font_size_h1', '2.5')) ?>" min="1.5" max="5" step="0.1" data-css-var="--font-size-h1" data-unit="rem">
                                <span>rem</span>
                            </div>
                        </div>
                        <div>
                            <small>Mobil</small>
                            <div class="input-suffix">
                                <input type="number" name="options[font_size_h1_mobile]" value="<?= htmlspecialchars(get_theme_option('font_size_h1_mobile', '2')) ?>" min="1.5" max="5" step="0.1" data-css-var="--font-size-h1" data-unit="rem" data-device="mobile">
                                <span>rem</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="option-section">
                <h3>Rozložení</h3>
                <div class="option-group">
                    <label>Maximální šířka obsahu</label>
                    <div class="device-inputs">
                        <div>
                            <small>Desktop</small>
                            <div class="input-suffix">
                                <input type="number" name="options[container_width]" value="<?= htmlspecialchars(get_theme_option('container_width', '960')) ?>" min="600" max="1400" step="20" data-css-var="--container-width" data-unit="px">
                                <span>px</span>
                            </div>
                        </div>
                         <div>
                            <small>Mobil</small>
                            <div class="input-suffix">
                                <input type="text" value="100%" disabled>
                                <span>%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="option-section">
                <h3>Patička</h3>
                <div class="option-group">
                    <label for="footer_bg_color">Barva pozadí</label>
                    <input type="color" id="footer_bg_color" name="options[footer_bg_color]" value="<?= htmlspecialchars(get_theme_option('footer_bg_color', '#ffffff')) ?>" data-css-var="--footer-bg-color">
                </div>
                <div class="option-group">
                    <label for="footer_text_color">Barva textu</label>
                    <input type="color" id="footer_text_color" name="options[footer_text_color]" value="<?= htmlspecialchars(get_theme_option('footer_text_color', '#6c757d')) ?>" data-css-var="--footer-text-color">
                </div>
                <div class="option-group">
                    <label for="footer_text">Text v patičce</label>
                    <input type="text" id="footer_text" name="options[footer_text]" value="<?= htmlspecialchars(get_theme_option('footer_text', '© 2025 Šumílek. Všechna práva vyhrazena.')) ?>" data-type="text" data-selector="footer p">
                </div>
            </div>
        </div>

        <!-- PRAVÝ PANEL S NÁHLEDEM -->
        <div class="customizer-preview">
            <div class="preview-actions">
                <button type="button" id="desktop-view">Desktop</button>
                <button type="button" id="mobile-view">Mobil</button>
            </div>
            <iframe id="preview-frame" class="preview-iframe" src="/?preview=true"></iframe>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const iframe = document.getElementById('preview-frame');
    const form = document.getElementById('customizer-form');
    let mobileStyles = {};

    function generateMobileCss() {
        let css = '@media (max-width: 768px) { :root {';
        for (const [key, value] of Object.entries(mobileStyles)) {
            css += `${key}: ${value};`;
        }
        css += '} }';
        return css;
    }

    function updatePreview(event) {
        const input = event.target;
        if (!input || input.type === 'file') return;

        const type = input.getAttribute('data-type');
        const cssVar = input.getAttribute('data-css-var');
        const selector = input.getAttribute('data-selector');
        const unit = input.getAttribute('data-unit') || '';
        const device = input.getAttribute('data-device');
        const value = input.value;

        if (!iframe.contentWindow) return;
        const iframeDoc = iframe.contentWindow.document;

        if (type === 'logo') {
            const logoLink = iframeDoc.querySelector('header h1 a');
            if (logoLink) {
                if (value) {
                    logoLink.innerHTML = `<img src="${value}" alt="<?= htmlspecialchars(SITE_NAME) ?>" style="max-height: 50px; display: block;">`;
                } else {
                    logoLink.innerHTML = '<?= htmlspecialchars(SITE_NAME) ?>';
                }
            }
        } else if (type === 'text') {
            const element = iframeDoc.querySelector(selector);
            if (element) {
                element.textContent = value;
            }
        } else if (cssVar) {
            if (device === 'mobile') {
                mobileStyles[cssVar] = value + unit;
                let styleTag = iframeDoc.getElementById('mobile-preview-styles');
                if (!styleTag) {
                    styleTag = iframeDoc.createElement('style');
                    styleTag.id = 'mobile-preview-styles';
                    iframeDoc.head.appendChild(styleTag);
                }
                styleTag.textContent = generateMobileCss();
            } else {
                iframeDoc.documentElement.style.setProperty(cssVar, value + unit);
            }
        }
    }

    form.addEventListener('input', updatePreview);

    document.getElementById('desktop-view').addEventListener('click', () => iframe.classList.remove('mobile'));
    document.getElementById('mobile-view').addEventListener('click', () => iframe.classList.add('mobile'));
});
</script>
