<main>
    <article>
        <!-- Ukázka Hero sekce pro domovskou stránku, pokud byste ji chtěli mít dynamicky -->
        <?php if ($page_data['id'] == get_theme_option('homepage_id')): ?>
        <div class="hero-banner">
            <h1><?= htmlspecialchars($page_data['title']) ?></h1>
            <p>Pokročilé systémy pro průmyslovou automatizaci a řízení procesů.</p>
        </div>
        <?php else: ?>
            <h1 style="color: var(--accent); margin-bottom: 2rem;"><?= htmlspecialchars($page_data['title']) ?></h1>
        <?php endif; ?>

        <div class="page-content">
            <?php
                $processed_content = process_shortcodes($page_data['content']);
                echo $processed_content;
            ?>
        </div>
    </article>
</main>