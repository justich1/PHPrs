<main>
    <h2><?= htmlspecialchars($page_data['title']) ?></h2>
    <article>
        <?php
            // POZOR: Pokud vkládáte obsah z WYSIWYG editoru, který obsahuje HTML,
            // je potřeba ho ošetřit proti XSS útokům pomocí specializované knihovny (např. HTML Purifier).
            // Pro jednoduchý text je htmlspecialchars() bezpečné, ale odstraní HTML tagy.
            // Pro ukázku zde obsah vypisujeme přímo, ale v reálné aplikaci je to RIZIKO!
            //echo $page_data['content'];
            $processed_content = process_shortcodes($page_data['content']);
            echo $processed_content;
        ?>
    </article>
</main>