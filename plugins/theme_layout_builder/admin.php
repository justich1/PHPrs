<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Přístup odepřen.');
}

require_once __DIR__ . '/../../config/config.php';

$themes_root = realpath(__DIR__ . '/../../themes');
if ($themes_root === false) {
    die('Adresář themes neexistuje.');
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
<header class="topbar">
    <h1><?= htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') ?></h1>
    <nav>
        <?php foreach (($menu_items ?? []) as $item): ?>
            <a href="?page=<?= urlencode($item['slug']) ?><?= isset($_GET['lang']) ? '&lang=' . urlencode($_GET['lang']) : '' ?>"><?= htmlspecialchars($item['title']) ?></a>
        <?php endforeach; ?>
    </nav>
</header>
<main class="container">
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
.topbar, footer { background: #111827; color: #fff; padding: 1rem; }
.topbar nav { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: .5rem; }
.topbar nav a { color: #93c5fd; text-decoration: none; }
.container { max-width: 960px; margin: 2rem auto; background: #fff; padding: 1.5rem; border-radius: 8px; }
CSS,
            'assets/js/main.js' => <<<'JS'
console.log('Theme Layout Builder: basic layout loaded');
JS,
        ],
    ],
    'sidebar_right' => [
        'label' => 'Layout se sidebarem vpravo',
        'description' => 'Obsah vlevo, sidebar vpravo.',
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
    <?php include __DIR__ . '/sidebar-right.php'; ?>
</div>
<footer>
    <small>&copy; <?= date('Y') ?> <?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'PHPrs', ENT_QUOTES, 'UTF-8') ?></small>
    <?php do_action('footer_end'); ?>
</footer>
<script src="themes/<?= htmlspecialchars(ACTIVE_THEME, ENT_QUOTES, 'UTF-8') ?>/assets/js/main.js"></script>
</body>
</html>
PHP,
            'sidebar-right.php' => <<<'PHP'
<aside class="sidebar sidebar-right">
    <?php render_widgets('sidebar-right'); ?>
</aside>
PHP,
            'assets/css/style.css' => <<<'CSS'
body { margin: 0; font-family: Arial, sans-serif; background: #eef2ff; color: #111827; }
.topbar, footer { background: #1e293b; color: white; padding: 1rem; }
.layout { max-width: 1200px; margin: 2rem auto; display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; }
.content, .sidebar { background: white; padding: 1.5rem; border-radius: 8px; }
@media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }
CSS,
            'assets/js/main.js' => <<<'JS'
console.log('Theme Layout Builder: right sidebar layout loaded');
JS,
        ],
    ],
    'sidebar_left' => [
        'label' => 'Layout se sidebarem vlevo',
        'description' => 'Sidebar vlevo, obsah vpravo.',
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
    <?php include __DIR__ . '/sidebar-left.php'; ?>
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
</div>
<footer>
    <small>&copy; <?= date('Y') ?> <?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'PHPrs', ENT_QUOTES, 'UTF-8') ?></small>
    <?php do_action('footer_end'); ?>
</footer>
<script src="themes/<?= htmlspecialchars(ACTIVE_THEME, ENT_QUOTES, 'UTF-8') ?>/assets/js/main.js"></script>
</body>
</html>
PHP,
            'sidebar-left.php' => <<<'PHP'
<aside class="sidebar sidebar-left">
    <?php render_widgets('sidebar-left'); ?>
</aside>
PHP,
            'assets/css/style.css' => <<<'CSS'
body { margin: 0; font-family: Arial, sans-serif; background: #eef2ff; color: #111827; }
.topbar, footer { background: #1e293b; color: white; padding: 1rem; }
.layout { max-width: 1200px; margin: 2rem auto; display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; }
.content, .sidebar { background: white; padding: 1.5rem; border-radius: 8px; }
@media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }
CSS,
            'assets/js/main.js' => <<<'JS'
console.log('Theme Layout Builder: left sidebar layout loaded');
JS,
        ],

    ],
    'full_site' => [
        'label' => 'Kompletní návrh vzhledu (header + hero + obě sidebar oblasti)',
        'description' => 'Plnější startovní šablona s moderním rozložením, obsahem a widget zónami.',
        'files' => [
            'header.php' => <<<'PHP'
<?php
$site_name = defined('SITE_NAME') ? SITE_NAME : 'PHPrs';
$page_title_safe = htmlspecialchars($page_data['title'] ?? 'Stránka', ENT_QUOTES, 'UTF-8');
$lang_suffix = isset($_GET['lang']) ? '&lang=' . urlencode($_GET['lang']) : '';
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
<header class="site-header">
    <div class="site-header__inner">
        <a class="brand" href="?page=domu<?= $lang_suffix ?>"><?= htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') ?></a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">☰</button>
        <nav id="main-nav" class="main-nav">
            <?php foreach (($menu_items ?? []) as $item): ?>
                <a href="?page=<?= urlencode($item['slug']) ?><?= $lang_suffix ?>"><?= htmlspecialchars($item['title']) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<section class="hero">
    <div class="hero__inner">
        <p class="hero__kicker">NOVÁ ŠABLONA</p>
        <h1><?= $page_title_safe ?></h1>
        <p>Kompletní výchozí vzhled se sidebar oblastmi vlevo i vpravo, připravený pro další úpravy.</p>
    </div>
</section>
<div class="layout layout--three-cols">
    <?php include __DIR__ . '/sidebar-left.php'; ?>
    <main class="content">
PHP,
            'page.php' => <<<'PHP'
<article class="content-card">
    <header class="content-card__header">
        <h2><?= htmlspecialchars($page_data['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>
    </header>
    <div class="content-card__body">
        <?= process_shortcodes($page_data['content'] ?? '') ?>
    </div>
</article>
PHP,
            'footer.php' => <<<'PHP'
    </main>
    <?php include __DIR__ . '/sidebar-right.php'; ?>
</div>
<footer class="site-footer">
    <div class="site-footer__inner">
        <div>
            <h3><?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'PHPrs', ENT_QUOTES, 'UTF-8') ?></h3>
            <p>Flexibilní výchozí vzhled vytvořený přes Theme Layout Builder.</p>
        </div>
        <div>
            <h4>Rychlé odkazy</h4>
            <ul>
                <?php foreach (array_slice(($menu_items ?? []), 0, 4) as $item): ?>
                    <li><a href="?page=<?= urlencode($item['slug']) ?><?= isset($_GET['lang']) ? '&lang=' . urlencode($_GET['lang']) : '' ?>"><?= htmlspecialchars($item['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="site-footer__bottom">
        <small>&copy; <?= date('Y') ?> <?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'PHPrs', ENT_QUOTES, 'UTF-8') ?></small>
        <?php do_action('footer_end'); ?>
    </div>
</footer>
<script src="themes/<?= htmlspecialchars(ACTIVE_THEME, ENT_QUOTES, 'UTF-8') ?>/assets/js/main.js"></script>
</body>
</html>
PHP,
            'sidebar-left.php' => <<<'PHP'
<aside class="sidebar sidebar-left">
    <h3>Levý panel</h3>
    <?php render_widgets('sidebar-left'); ?>
</aside>
PHP,
            'sidebar-right.php' => <<<'PHP'
<aside class="sidebar sidebar-right">
    <h3>Pravý panel</h3>
    <?php render_widgets('sidebar-right'); ?>
</aside>
PHP,
            'assets/css/style.css' => <<<'CSS'
:root {
    --bg: #f8fafc;
    --surface: #ffffff;
    --line: #dbe3ef;
    --text: #0f172a;
    --muted: #475569;
    --brand: #2563eb;
    --brand-2: #1d4ed8;
}
* { box-sizing: border-box; }
body { margin: 0; font-family: Inter, Arial, sans-serif; color: var(--text); background: var(--bg); }
a { color: var(--brand); text-decoration: none; }
a:hover { text-decoration: underline; }
.site-header { background: var(--surface); border-bottom: 1px solid var(--line); position: sticky; top: 0; z-index: 10; }
.site-header__inner { max-width: 1280px; margin: 0 auto; padding: 1rem; display: flex; align-items: center; gap: 1rem; }
.brand { font-weight: 800; font-size: 1.2rem; color: var(--text); }
.main-nav { margin-left: auto; display: flex; gap: .9rem; flex-wrap: wrap; }
.menu-toggle { display: none; margin-left: auto; border: 1px solid var(--line); background: #fff; border-radius: 8px; padding: .4rem .6rem; }
.hero { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; }
.hero__inner { max-width: 1280px; margin: 0 auto; padding: 2rem 1rem; }
.hero__kicker { opacity: .85; margin: 0; font-size: .8rem; letter-spacing: .08em; }
.hero h1 { margin: .35rem 0 .5rem; }
.hero p { margin: 0; max-width: 760px; opacity: .92; }
.layout { max-width: 1280px; margin: 1.4rem auto; padding: 0 1rem; display: grid; gap: 1rem; }
.layout--three-cols { grid-template-columns: 260px minmax(0, 1fr) 260px; }
.sidebar, .content-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 1rem; }
.content-card__header { border-bottom: 1px solid var(--line); margin-bottom: 1rem; }
.site-footer { margin-top: 1.5rem; background: #0b1220; color: #dbeafe; }
.site-footer__inner { max-width: 1280px; margin: 0 auto; padding: 1.5rem 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.site-footer__inner h3, .site-footer__inner h4 { color: #fff; margin-top: 0; }
.site-footer__inner ul { margin: 0; padding-left: 1rem; }
.site-footer__inner a { color: #93c5fd; }
.site-footer__bottom { border-top: 1px solid rgba(255,255,255,.18); max-width: 1280px; margin: 0 auto; padding: .9rem 1rem; }
@media (max-width: 1024px) {
    .layout--three-cols { grid-template-columns: 1fr; }
}
@media (max-width: 760px) {
    .menu-toggle { display: inline-block; }
    .main-nav { display: none; width: 100%; margin-left: 0; }
    .main-nav.open { display: flex; flex-direction: column; }
    .site-header__inner { flex-wrap: wrap; }
    .site-footer__inner { grid-template-columns: 1fr; }
}
CSS,
            'assets/js/main.js' => <<<'JS'
(function () {
    var toggle = document.querySelector('.menu-toggle');
    var nav = document.getElementById('main-nav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', function () {
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        nav.classList.toggle('open');
    });
})();
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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

$existing_themes = array_values(array_filter(scandir($themes_root), function ($item) use ($themes_root) {
    return $item !== '.' && $item !== '..' && is_dir($themes_root . DIRECTORY_SEPARATOR . $item);
}));
sort($existing_themes);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Layout Builder</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 24px; }
        .wrap { max-width: 980px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        h1, h2, h3 { margin-top: 0; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .field { margin-bottom: 14px; }
        label { display: block; font-weight: bold; margin-bottom: 6px; }
        input[type="text"], select { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        button { border: 0; background: #2563eb; color: #fff; border-radius: 8px; padding: 10px 16px; font-weight: bold; cursor: pointer; }
        .hint { color: #475569; font-size: 14px; }
        ul { margin: 10px 0 0; padding-left: 18px; color: #334155; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Theme Layout Builder</h1>
    <p class="hint">Nástroj pro vytvoření nové šablony vzhledu (layoutu) přímo z administrace.</p>

    <?php if ($message): ?>
        <div class="alert success"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" action="">
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
</body>
</html>
