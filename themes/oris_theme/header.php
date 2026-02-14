<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_data['title']) ?> - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="/themes/<?= ACTIVE_THEME ?>/assets/css/style.css">
    
    <style>
        :root {
            /* Dynamické barvy z administrace */
            --header-bg-color: <?= htmlspecialchars(get_theme_option('header_bg_color', '#1a1e23')); ?>;
            --accent: <?= htmlspecialchars(get_theme_option('link_color', '#0095ff')); ?>;
            --text-color: <?= htmlspecialchars(get_theme_option('text_color', '#ffffff')); ?>;
            --background-color: <?= htmlspecialchars(get_theme_option('background_color', '#111418')); ?>;
        }
    </style>
    <?php do_action('theme_head'); ?>
</head>
<body>
    <div class="header-wrapper">
        <header class="site-header">
            <?php
                $home_slugs = get_homepage_slugs(); 
                $home_slug = $home_slugs[$current_lang] ?? 'domu';
                $logo_url = get_theme_option('logo_url');
            ?>
            <div class="site-logo">
                <a href="/<?= $current_lang ?>/<?= $home_slug ?>">
                    <?php if (!empty($logo_url)): ?>
                        <img src="<?= htmlspecialchars($logo_url) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>">
                    <?php else: ?>
                        ORIS<span>CORE</span>
                    <?php endif; ?>
                </a>
            </div>
            
            <button class="mobile-nav-toggle" id="nav-btn" aria-controls="primary-navigation" aria-expanded="false">
                <span class="sr-only">Menu</span>
            </button>

            <nav class="primary-navigation" id="primary-navigation" data-visible="false">
                <ul>
                    <?php if (!empty($menu_items)): ?>
                        <?php foreach($menu_items as $item): ?>
                            <?php 
                                $url = $item['is_internal'] ? "/{$current_lang}/" . htmlspecialchars($item['url']) : htmlspecialchars($item['url']);
                            ?>
                            <li><a href="<?= $url ?>"><?= htmlspecialchars($item['title']) ?></a></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </nav>
        </header>
    </div>
    <div class="container">