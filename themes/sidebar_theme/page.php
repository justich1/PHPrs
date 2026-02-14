<?php
// UPRAVENO: Odstraněn .main-layout a vkládání panelů.
// Tento soubor nyní obsahuje pouze hlavní obsahovou část.
?>
<main>
    <h2><?= htmlspecialchars($page_data['title']) ?></h2>
    <article>
        <?php
            $processed_content = process_shortcodes($page_data['content']);
            echo $processed_content;
        ?>
    </article>
</main>
