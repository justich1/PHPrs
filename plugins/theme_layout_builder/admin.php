<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Přístup odepřen.');
}

require_once __DIR__ . '/../../config/config.php';

$themes_root = realpath(__DIR__ . '/../../themes');
$plugins_root = realpath(__DIR__ . '/../../plugins');

if ($themes_root === false || $plugins_root === false) {
    die('Adresář themes nebo plugins neexistuje.');
}

$layout_templates = [
    'basic' => [
        'label' => 'Základní layout',
        'description' => 'Jednoduchá hlavička + obsah + patička.',
        'files' => [
            'header.php' => <<<'PHP'
<?php
$site_name = defined('SITE_NAME') ? SITE_NAME : 'PHPrs';
$page_title_safe = htmlspecialchars($page_data['title'] ?? 'Stránka', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title_safe ?> - <?= htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="themes/<?= htmlspecialchars(ACTIVE_THEME, ENT_QUOTES, 'UTF-8') ?>/assets/css/style.css">
</head>
<body>
<header>
    <h1><?= htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') ?></h1>
    <nav>
        <?php foreach (($menu_items ?? []) as $item): ?>
            <a href="?page=<?= urlencode($item['slug']) ?><?= isset($_GET['lang']) ? '&lang=' . urlencode($_GET['lang']) : '' ?>"><?= htmlspecialchars($item['title']) ?></a>
        <?php endforeach; ?>
    </nav>
</header>
<main>
PHP,
            'page.php' => <<<'PHP'
<article>
    <h2><?= htmlspecialchars($page_data['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
    <div>
        <?= process_shortcodes($page_data['content'] ?? '') ?>
    </div>
</article>
PHP,
            'footer.php' => <<<'PHP'
</main>
<footer>
    <small>&copy; <?= date('Y') ?> <?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'PHPrs', ENT_QUOTES, 'UTF-8') ?></small>
    <?php do_action('footer_end'); ?>
</footer>
<script src="themes/<?= htmlspecialchars(ACTIVE_THEME, ENT_QUOTES, 'UTF-8') ?>/assets/js/main.js"></script>
</body>
</html>
PHP,
            'assets/css/style.css' => <<<'CSS'
body { margin: 0; font-family: Arial, sans-serif; line-height: 1.6; background: #f5f7fb; color: #1f2937; }
header, footer { background: #111827; color: #fff; padding: 1rem; }
header nav { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: .5rem; }
header nav a { color: #93c5fd; text-decoration: none; }
main { max-width: 900px; margin: 2rem auto; background: #fff; padding: 1.5rem; border-radius: 8px; }
CSS,
            'assets/js/main.js' => <<<'JS'
console.log('Theme Layout Builder: basic layout loaded');
JS,
        ],
    ],
    'sidebar' => [
        'label' => 'Layout se sidebar',
        'description' => 'Obsah + pravý sidebar pro widgety.',
        'files' => [
            'header.php' => <<<'PHP'
<?php
$site_name = defined('SITE_NAME') ? SITE_NAME : 'PHPrs';
$page_title_safe = htmlspecialchars($page_data['title'] ?? 'Stránka', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title_safe ?> - <?= htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="themes/<?= htmlspecialchars(ACTIVE_THEME, ENT_QUOTES, 'UTF-8') ?>/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <h1><?= htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') ?></h1>
</header>
<div class="layout">
    <main class="content">
PHP,
            'page.php' => <<<'PHP'
<article>
    <h2><?= htmlspecialchars($page_data['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
    <?= process_shortcodes($page_data['content'] ?? '') ?>
</article>
PHP,
            'footer.php' => <<<'PHP'
    </main>
    <aside class="sidebar">
        <?php render_widgets('sidebar-right'); ?>
    </aside>
</div>
<footer>
    <small>&copy; <?= date('Y') ?> <?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'PHPrs', ENT_QUOTES, 'UTF-8') ?></small>
    <?php do_action('footer_end'); ?>
</footer>
<script src="themes/<?= htmlspecialchars(ACTIVE_THEME, ENT_QUOTES, 'UTF-8') ?>/assets/js/main.js"></script>
</body>
</html>
PHP,
            'assets/css/style.css' => <<<'CSS'
body { margin: 0; font-family: Arial, sans-serif; background: #eef2ff; color: #111827; }
.topbar, footer { background: #1e293b; color: white; padding: 1rem; }
.layout { max-width: 1200px; margin: 2rem auto; display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; }
.content, .sidebar { background: white; padding: 1.5rem; border-radius: 8px; }
@media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }
CSS,
            'assets/js/main.js' => <<<'JS'
console.log('Theme Layout Builder: sidebar layout loaded');
JS,
        ],
    ],
];

function write_template_files(string $target_root, array $files): array {
    foreach ($files as $relative_path => $content) {
        $destination = $target_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
        $destination_dir = dirname($destination);

        if (!is_dir($destination_dir) && !mkdir($destination_dir, 0755, true)) {
            return [false, 'Nepodařilo se vytvořit adresář: ' . htmlspecialchars($destination_dir, ENT_QUOTES, 'UTF-8')];
        }

        if (file_put_contents($destination, $content) === false) {
            return [false, 'Nepodařilo se uložit soubor: ' . htmlspecialchars($relative_path, ENT_QUOTES, 'UTF-8')];
        }
    }

    return [true, ''];
}

function create_theme_from_template(string $themes_root, string $theme_folder, array $template_files): array {
    $target_dir = $themes_root . DIRECTORY_SEPARATOR . $theme_folder;

    if (is_dir($target_dir)) {
        return [false, 'Šablona s tímto názvem již existuje.'];
    }

    return write_template_files($target_dir, $template_files);
}

function plugin_php_template(array $config): string {
    $plugin_name = $config['plugin_name'];
    $slug = $config['slug'];
    $with_activation = $config['with_activation'];
    $with_deactivation = $config['with_deactivation'];
    $with_uninstall = $config['with_uninstall'];
    $with_shortcode = $config['with_shortcode'];
    $shortcode_tag = $config['shortcode_tag'];
    $with_assets = $config['with_assets'];

    $chunks = [];
    $chunks[] = "<?php\n";
    $chunks[] = "/**\n * {$plugin_name}\n * Generováno přes Theme Layout Builder\n */\n\n";

    if ($with_assets) {
        $chunks[] = "function {$slug}_enqueue_assets() {\n";
        $chunks[] = "    echo '<link rel=\"stylesheet\" href=\"plugins/{$slug}/assets/css/style.css\">';\n";
        $chunks[] = "    echo '<script src=\"plugins/{$slug}/assets/js/main.js\" defer></script>';\n";
        $chunks[] = "}\n";
        $chunks[] = "add_action('footer_end', '{$slug}_enqueue_assets');\n\n";
    }

    if ($with_activation) {
        $chunks[] = "function {$slug}_activate() {\n";
        $chunks[] = "    // TODO: inicializace tabulek / výchozí data\n";
        $chunks[] = "}\n";
        $chunks[] = "register_activation_hook(__FILE__, '{$slug}_activate');\n\n";
    }

    if ($with_deactivation) {
        $chunks[] = "function {$slug}_deactivate() {\n";
        $chunks[] = "    // TODO: cleanup při deaktivaci\n";
        $chunks[] = "}\n";
        $chunks[] = "register_deactivation_hook(__FILE__, '{$slug}_deactivate');\n\n";
    }

    if ($with_uninstall) {
        $chunks[] = "function {$slug}_uninstall() {\n";
        $chunks[] = "    // TODO: finální úklid při smazání pluginu\n";
        $chunks[] = "}\n";
        $chunks[] = "register_uninstall_hook(__FILE__, '{$slug}_uninstall');\n\n";
    }

    if ($with_shortcode) {
        $chunks[] = "function {$slug}_shortcode(\$atts = []) {\n";
        $chunks[] = "    return '<div class=\"{$slug}-shortcode\">Výstup shortcode [{$shortcode_tag}]</div>';\n";
        $chunks[] = "}\n";
        $chunks[] = "add_shortcode('{$shortcode_tag}', '{$slug}_shortcode');\n\n";
    }

    return implode('', $chunks);
}

function plugin_admin_template(string $plugin_name): string {
    $safe_name = htmlspecialchars($plugin_name, ENT_QUOTES, 'UTF-8');
    $template = <<<'HTML'
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Přístup odepřen.');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>__PLUGIN_NAME__ – Nastavení</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f8fafc; }
        .box { max-width: 900px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; }
    </style>
</head>
<body>
<div class="box">
    <h1>__PLUGIN_NAME__</h1>
    <p>Tady si doprogramujte administrační nastavení pluginu.</p>
</div>
</body>
</html>
HTML;

    return str_replace('__PLUGIN_NAME__', $safe_name, $template);
}

function create_plugin_template(string $plugins_root, array $config): array {
    $slug = $config['slug'];
    $target_dir = $plugins_root . DIRECTORY_SEPARATOR . $slug;

    if (is_dir($target_dir)) {
        return [false, 'Plugin s tímto názvem složky už existuje.'];
    }

    $plugin_json = [
        'name' => $config['plugin_name'],
        'description' => $config['plugin_description'],
        'version' => $config['version'],
        'settings_page' => $config['with_admin_page'] ? 'admin.php' : ''
    ];

    $files = [
        'plugin.json' => json_encode($plugin_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'plugin.php' => plugin_php_template($config),
    ];

    if ($config['with_admin_page']) {
        $files['admin.php'] = plugin_admin_template($config['plugin_name']);
    }

    if ($config['with_assets']) {
        $files['assets/css/style.css'] = ".{$slug}-shortcode { padding: 10px; background: #e2e8f0; border-radius: 6px; }\n";
        $files['assets/js/main.js'] = "console.log('Plugin {$slug} loaded');\n";
    }

    return write_template_files($target_dir, $files);
}

$message = '';
$error = '';
$active_tab = $_POST['builder_type'] ?? 'theme';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $builder_type = $_POST['builder_type'] ?? 'theme';

    if ($builder_type === 'theme') {
        $new_theme_name = trim($_POST['new_theme_name'] ?? '');
        $layout_key = $_POST['layout_type'] ?? 'basic';

        if ($new_theme_name === '') {
            $error = 'Vyplňte název nové šablony.';
        } elseif (!preg_match('/^[a-z0-9_\-]+$/', $new_theme_name)) {
            $error = 'Název šablony smí obsahovat pouze malá písmena, čísla, pomlčku a podtržítko.';
        } elseif (!isset($layout_templates[$layout_key])) {
            $error = 'Neplatný typ layoutu.';
        } else {
            [$ok, $internal_error] = create_theme_from_template(
                $themes_root,
                $new_theme_name,
                $layout_templates[$layout_key]['files']
            );

            if ($ok) {
                $message = 'Nový layout byl vytvořen ve složce themes/' . htmlspecialchars($new_theme_name, ENT_QUOTES, 'UTF-8') . '.';
            } else {
                $error = $internal_error;
            }
        }
    }

    if ($builder_type === 'plugin') {
        $plugin_name = trim($_POST['plugin_name'] ?? '');
        $plugin_slug = trim($_POST['plugin_slug'] ?? '');
        $plugin_description = trim($_POST['plugin_description'] ?? '');
        $version = trim($_POST['plugin_version'] ?? '1.0.0');
        $shortcode_tag = trim($_POST['shortcode_tag'] ?? 'my_shortcode');

        if ($plugin_name === '' || $plugin_slug === '') {
            $error = 'Vyplňte název pluginu i název složky.';
        } elseif (!preg_match('/^[a-z0-9_\-]+$/', $plugin_slug)) {
            $error = 'Název složky pluginu smí obsahovat pouze malá písmena, čísla, pomlčku a podtržítko.';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $shortcode_tag)) {
            $error = 'Shortcode tag smí obsahovat pouze písmena, čísla, pomlčku a podtržítko.';
        } else {
            $config = [
                'plugin_name' => $plugin_name,
                'slug' => $plugin_slug,
                'plugin_description' => $plugin_description !== '' ? $plugin_description : 'Generovaný plugin z Theme Layout Builderu',
                'version' => $version !== '' ? $version : '1.0.0',
                'with_admin_page' => isset($_POST['with_admin_page']),
                'with_activation' => isset($_POST['with_activation']),
                'with_deactivation' => isset($_POST['with_deactivation']),
                'with_uninstall' => isset($_POST['with_uninstall']),
                'with_shortcode' => isset($_POST['with_shortcode']),
                'shortcode_tag' => $shortcode_tag,
                'with_assets' => isset($_POST['with_assets']),
            ];

            [$ok, $internal_error] = create_plugin_template($plugins_root, $config);

            if ($ok) {
                $message = 'Nový plugin byl vytvořen ve složce plugins/' . htmlspecialchars($plugin_slug, ENT_QUOTES, 'UTF-8') . '.';
            } else {
                $error = $internal_error;
            }
        }
    }
}

$existing_themes = array_values(array_filter(scandir($themes_root), function ($item) use ($themes_root) {
    return $item !== '.' && $item !== '..' && is_dir($themes_root . DIRECTORY_SEPARATOR . $item);
}));
sort($existing_themes);

$existing_plugins = array_values(array_filter(scandir($plugins_root), function ($item) use ($plugins_root) {
    return $item !== '.' && $item !== '..' && is_dir($plugins_root . DIRECTORY_SEPARATOR . $item);
}));
sort($existing_plugins);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme & Plugin Builder</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 24px; }
        .wrap { max-width: 980px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        h1, h2, h3 { margin-top: 0; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .tabs { display: flex; gap: 8px; margin-bottom: 16px; }
        .tab { display: inline-block; padding: 8px 12px; border-radius: 8px; background: #e2e8f0; cursor: pointer; font-weight: bold; }
        .tab.active { background: #2563eb; color: #fff; }
        .panel { display: none; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 20px; }
        .panel.active { display: block; }
        .field { margin-bottom: 14px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        label { display: block; font-weight: bold; margin-bottom: 6px; }
        input[type="text"], textarea, select { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        textarea { min-height: 90px; }
        button { border: 0; background: #2563eb; color: #fff; border-radius: 8px; padding: 10px 16px; font-weight: bold; cursor: pointer; }
        .hint { color: #475569; font-size: 14px; }
        .checks label { font-weight: normal; margin-bottom: 8px; }
        ul { margin: 10px 0 0; padding-left: 18px; color: #334155; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Theme & Plugin Builder</h1>
    <p class="hint">Nástroj pro rychlé vytvoření nové šablony i pluginu přímo z administrace.</p>

    <?php if ($message): ?>
        <div class="alert success"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <div class="tabs">
        <div class="tab <?= $active_tab === 'theme' ? 'active' : '' ?>" data-tab="theme">Generátor šablon</div>
        <div class="tab <?= $active_tab === 'plugin' ? 'active' : '' ?>" data-tab="plugin">Pokročilý generátor pluginů</div>
    </div>

    <div class="panel <?= $active_tab === 'theme' ? 'active' : '' ?>" id="panel-theme">
        <h2>Vytvořit layout</h2>
        <form method="post" action="">
            <input type="hidden" name="builder_type" value="theme">
            <div class="field">
                <label for="new_theme_name">Název nové složky šablony</label>
                <input type="text" id="new_theme_name" name="new_theme_name" placeholder="napr. custom_layout" required>
                <div class="hint">Pouze <code>a-z</code>, <code>0-9</code>, <code>-</code>, <code>_</code>.</div>
            </div>

            <div class="field">
                <label for="layout_type">Typ layoutu</label>
                <select id="layout_type" name="layout_type">
                    <?php foreach ($layout_templates as $layout_key => $layout): ?>
                        <option value="<?= htmlspecialchars($layout_key, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($layout['label'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($layout['description'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">Vytvořit layout</button>
        </form>

        <h3>Existující šablony</h3>
        <ul>
            <?php foreach ($existing_themes as $theme_name): ?>
                <li><?= htmlspecialchars($theme_name, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="panel <?= $active_tab === 'plugin' ? 'active' : '' ?>" id="panel-plugin">
        <h2>Pokročilé vytváření pluginů</h2>
        <form method="post" action="">
            <input type="hidden" name="builder_type" value="plugin">

            <div class="grid">
                <div class="field">
                    <label for="plugin_name">Název pluginu</label>
                    <input type="text" id="plugin_name" name="plugin_name" placeholder="Např. FAQ Manager" required>
                </div>
                <div class="field">
                    <label for="plugin_slug">Složka pluginu</label>
                    <input type="text" id="plugin_slug" name="plugin_slug" placeholder="faq_manager" required>
                </div>
            </div>

            <div class="grid">
                <div class="field">
                    <label for="plugin_version">Verze</label>
                    <input type="text" id="plugin_version" name="plugin_version" value="1.0.0">
                </div>
                <div class="field">
                    <label for="shortcode_tag">Shortcode tag (pokud je zapnutý shortcode)</label>
                    <input type="text" id="shortcode_tag" name="shortcode_tag" value="my_shortcode">
                </div>
            </div>

            <div class="field">
                <label for="plugin_description">Popis pluginu</label>
                <textarea id="plugin_description" name="plugin_description" placeholder="Krátký popis funkce pluginu"></textarea>
            </div>

            <div class="field checks">
                <label><input type="checkbox" name="with_admin_page" checked> Vytvořit admin stránku pluginu (admin.php)</label>
                <label><input type="checkbox" name="with_shortcode" checked> Přidat shortcode šablonu</label>
                <label><input type="checkbox" name="with_assets" checked> Vytvořit assets (CSS + JS)</label>
                <label><input type="checkbox" name="with_activation" checked> Přidat activation hook</label>
                <label><input type="checkbox" name="with_deactivation"> Přidat deactivation hook</label>
                <label><input type="checkbox" name="with_uninstall"> Přidat uninstall hook</label>
            </div>

            <button type="submit">Vytvořit plugin</button>
        </form>

        <h3>Existující pluginy</h3>
        <ul>
            <?php foreach ($existing_plugins as $plugin_name): ?>
                <li><?= htmlspecialchars($plugin_name, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            const selected = tab.dataset.tab;

            document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.panel').forEach(function(panel) { panel.classList.remove('active'); });

            tab.classList.add('active');
            document.getElementById('panel-' + selected).classList.add('active');
        });
    });
</script>
</body>
</html>
