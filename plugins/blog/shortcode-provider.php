<?php
// /plugins/blog/shortcode-provider.php

/**
 * Poskytuje dynamický seznam shortcodů pro blog,
 * například pro výpis článků z každé existující kategorie.
 */

// Bezpečnostní pojistka
if (!defined('ABSPATH')) {
    // Tuto konstantu byste si měl definovat v hlavním config.php
    // define('ABSPATH', dirname(__DIR__, 2));
}

$blog_shortcodes = [];

try {
    // Připojení k databázi
    require_once __DIR__ . '/../../functions/database.php';
    require_once __DIR__ . '/functions-blog.php'; // Musíme načíst i funkce blogu, kvůli konstantě DEFAULT_LANG

    $db = db_connect();

    // Správný SQL dotaz pro získání kategorií a jejich názvů
    $stmt = $db->prepare("SELECT c.id, t.name FROM plugin_blog_categories c JOIN plugin_blog_categories_translations t ON c.id = t.category_id WHERE t.lang_code = ? ORDER BY t.name ASC");
    $stmt->execute([DEFAULT_LANG]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($categories) {
        foreach ($categories as $category) {
            $id = htmlspecialchars($category['id']);
            $name = htmlspecialchars($category['name']);
            // Pro každou kategorii vytvoříme samostatný, hotový shortcode
            $blog_shortcodes['[blog category_id="' . $id . '"]'] = "Blog: Výpis z kategorie '$name'";
        }
    } else {
        // Můžeme přidat i obecný shortcode, pokud nejsou žádné kategorie
        $blog_shortcodes['[blog]'] = "Blog: Výpis všech příspěvků";
    }

} catch (PDOException $e) {
    // Pokud se něco pokazí, nabídneme alespoň obecný shortcode
    $blog_shortcodes['[blog]'] = "Blog: Výpis všech příspěvků";
}

// Vrátíme finální pole shortcodů pro tento plugin
return $blog_shortcodes;