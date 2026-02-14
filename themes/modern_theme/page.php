<main>
    <article>
        <h1><?= htmlspecialchars($page_data['title']) ?></h1>
        <div class="page-content">
            <?php
                $processed_content = process_shortcodes($page_data['content']);
                echo $processed_content;
            ?>
        </div>
    </article>
</main>
