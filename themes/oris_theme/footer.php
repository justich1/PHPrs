</div> <!-- .container -->
    <div class="footer-wrapper">
        <footer class="site-footer">
            <div class="container-footer">
                <p style="color: var(--accent); font-weight: bold; margin-bottom: 15px; letter-spacing: 1px;">ORIS CORE SYSTEMS</p>
                <p>
                    <?php 
                        $footer_text = get_theme_option('footer_text', '&copy; ' . date('Y') . ' ' . SITE_NAME);
                        echo htmlspecialchars(process_shortcodes($footer_text));
                    ?>
                </p>

                <?php
                    $footer_menu_items = get_menu_items('footer', $current_lang);
                    if (!empty($footer_menu_items)):
                ?>
                    <nav class="footer-navigation">
                        <ul style="display: flex; justify-content: center; gap: 20px; list-style: none; margin: 20px 0;">
                            <?php foreach ($footer_menu_items as $item): ?>
                                <?php
                                    $url = $item['is_internal'] && defined('SITE_URL')
                                         ? rtrim(SITE_URL, '/') . '/' . ltrim($item['url'], '/')
                                         : $item['url'];
                                ?>
                                <li>
                                    <a href="<?= htmlspecialchars($url) ?>" style="color: var(--text-dim); text-decoration: none; font-size: 0.8rem;">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

                <div class="lang-switcher">
                    <?php
                    $home_slugs = get_homepage_slugs();
                    $slugs_by_lang = get_page_slugs($page_data['id'] ?? 0);
                    foreach (SUPPORTED_LANGS as $code => $name): ?>
                        <a href="/<?= $code ?>/<?= htmlspecialchars($slugs_by_lang[$code] ?? $home_slugs[$code] ?? 'domu') ?>" 
                           style="color: <?= $code == $current_lang ? 'var(--accent)' : '#444' ?>; margin: 0 5px; font-size: 0.8rem; text-decoration: none;">
                            <?= htmlspecialchars($name) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php do_action('footer_end'); ?>
        </footer>
    </div>

    <!-- Lightbox z vašeho vzoru -->
    <div class="lightbox" id="lightbox">
        <div class="lightbox-content">
            <span class="lightbox-close">&times;</span>
            <img class="lightbox-image" src="">
        </div>
    </div>

    <script src="/themes/<?= ACTIVE_THEME ?>/assets/js/main.js"></script>
    <?php do_action('theme_footer_js'); ?>
</body>
</html>