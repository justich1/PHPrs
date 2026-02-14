</div> <!-- .container -->
<footer>
    <p>
        <?php 
            // Zde vezmeme text pro patičku (může být uložený v nastavení)
            $footer_text = get_theme_option('footer_text', '&copy; ' . date('Y') . ' ' . SITE_NAME);

            // Zpracujeme případné shortcody ve footer textu
            $processed_footer_text = process_shortcodes($footer_text);

            // Vytiskneme zpracovaný text s podporou shortcodů
            echo htmlspecialchars($processed_footer_text);
        ?>
    </p>
    <?php
        do_action('footer_end');
    ?>

    <?php
        // Načteme položky menu pro umístění 'footer' a aktuální jazyk
        $footer_menu_items = get_menu_items('footer', $current_lang);

        // Zobrazíme menu, pouze pokud obsahuje nějaké položky
        if (!empty($footer_menu_items)):
    ?>
        <nav class="footer-navigation">
            <ul>
                <?php foreach ($footer_menu_items as $item): ?>
                    <?php
                        // Sestavíme finální URL na základě typu odkazu
                        $url = $item['is_internal'] && defined('SITE_URL')
                             ? rtrim(SITE_URL, '/') . '/' . ltrim($item['url'], '/')
                             : $item['url'];
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars($url) ?>" target="<?= htmlspecialchars($item['target']) ?>">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <div class="lang-switcher">
        <?php
        $slugs_by_lang = get_page_slugs($page_data['id'] ?? 0);
        foreach (SUPPORTED_LANGS as $code => $name):
            if (!empty($slugs_by_lang[$code])): ?>
            <a href="/<?= $code ?>/<?= htmlspecialchars($slugs_by_lang[$code]) ?>" class="<?= $code == $current_lang ? 'active' : '' ?>">
                <?= htmlspecialchars($name) ?>
            </a>
            <?php 
            elseif ($page_data['id'] === 0): 
                $home_slug_for_lang = ($code === 'en') ? 'home' : 'domu';
            ?>
            <a href="/<?= $code ?>/<?= $home_slug_for_lang ?>" class="<?= $code == $current_lang ? 'active' : '' ?>">
                <?= htmlspecialchars($name) ?>
            </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- HTML PRO LIGHTBOX (standardně skryté) -->
    <div class="lightbox" id="lightbox">
        <div class="lightbox-content">
            <span class="lightbox-close">&times;</span>
            <img class="lightbox-image" src="">
        </div>
    </div>

</footer>
<script src="/themes/<?= ACTIVE_THEME ?>/assets/js/main.js"></script>
<?php do_action('theme_footer_js'); // Tento hook použije plugin pro vložení svého JS ?>
</body>
</html>
