<?php
// UPRAVENO: Odstraněn původní .container a přidán nový .site-content-wrapper
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_data['title']) ?> - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="/themes/<?= ACTIVE_THEME ?>/assets/css/style.css">
    
    <style>
        :root {
            /* Desktop hodnoty */
            --header-bg-color: <?= htmlspecialchars(get_theme_option('header_bg_color', '#343a40')); ?>;
            --header-padding: <?= htmlspecialchars(get_theme_option('header_padding', '1')); ?>rem;
            --nav-link-color: <?= htmlspecialchars(get_theme_option('nav_link_color', '#f8f9fa')); ?>;
            --nav-link-hover-bg: <?= htmlspecialchars(get_theme_option('nav_link_hover_bg', '#495057')); ?>;
            --background-color: <?= htmlspecialchars(get_theme_option('background_color', '#f8f9fa')); ?>;
            --container-bg-color: <?= htmlspecialchars(get_theme_option('container_bg_color', '#ffffff')); ?>;
            --text-color: <?= htmlspecialchars(get_theme_option('text_color', '#212529')); ?>;
            --link-color: <?= htmlspecialchars(get_theme_option('link_color', '#007bff')); ?>;
            --font-family: <?= get_theme_option('font_family', 'sans-serif'); ?>;
            --font-size-base: <?= htmlspecialchars(get_theme_option('font_size_base', '16')); ?>px;
            --line-height: <?= htmlspecialchars(get_theme_option('line_height', '1.6')); ?>;
            --font-size-h1: <?= htmlspecialchars(get_theme_option('font_size_h1', '2.5')); ?>rem;
            --font-size-h2: <?= htmlspecialchars(get_theme_option('font_size_h2', '2')); ?>rem;
            --container-width: <?= htmlspecialchars(get_theme_option('container_width', '1200')); ?>px; /* Zvětšíme pro panely */
            --container-padding: <?= htmlspecialchars(get_theme_option('container_padding', '2')); ?>rem;
            --footer-bg-color: <?= htmlspecialchars(get_theme_option('footer_bg_color', '#ffffff')); ?>;
            --footer-text-color: <?= htmlspecialchars(get_theme_option('footer_text_color', '#6c757d')); ?>;
        }

        /* Mobilní hodnoty */
        @media (max-width: 768px) {
            :root {
                --font-size-base: <?= htmlspecialchars(get_theme_option('font_size_base_mobile', '15')); ?>px;
                --font-size-h1: <?= htmlspecialchars(get_theme_option('font_size_h1_mobile', '2')); ?>rem;
                --font-size-h2: <?= htmlspecialchars(get_theme_option('font_size_h2_mobile', '1.7')); ?>rem;
            }
        }
    </style>
    <?php do_action('theme_head'); ?>
</head>
<body>
    <header>
        <?php
            $home_slugs = get_homepage_slugs(); 
            $home_slug = $home_slugs[$current_lang] ?? 'domu';
            $logo_url = get_theme_option('logo_url');
        ?>
        <h1>
            <a href="/<?= $current_lang ?>/<?= $home_slug ?>">
                <?php if (!empty($logo_url)): ?>
                    <img src="<?= htmlspecialchars($logo_url) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>" style="max-height: 80px; display: block;">
                <?php else: ?>
                    <?= htmlspecialchars(SITE_NAME) ?>
                <?php endif; ?>
            </a>
        </h1>
        
        <button class="mobile-nav-toggle" aria-controls="primary-navigation" aria-expanded="false">
            <span class="sr-only">Menu</span>
        </button>

        <nav class="primary-navigation" id="primary-navigation">
            <ul>
                <?php foreach($menu_items as $item): ?>
                    <?php 
                        $url = $item['is_internal'] ? "/{$current_lang}/" . htmlspecialchars($item['url']) : htmlspecialchars($item['url']);
                        $target = isset($item['target']) ? $item['target'] : '_self';
                    ?>
                    <li><a href="<?= $url ?>" target="<?= $target ?>"><?= htmlspecialchars($item['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </header>

    <?php // Nový hlavní obal pro celý obsah včetně panelů ?>
    <div class="site-content-wrapper">
        <?php
            // Levý panel je nyní zde, mimo .container
            if (file_exists(__DIR__ . '/sidebar-left.php')) {
                include __DIR__ . '/sidebar-left.php';
            }
        ?>
        <?php // Tento .container obaluje už jen hlavní obsah (modrý blok) ?>
        <div class="container">
