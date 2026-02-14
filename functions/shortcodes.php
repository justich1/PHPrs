<?php
// functions/shortcodes.php - Funkce pro správu a zpracování shortcodů

/**
 * Zpracuje obsah a nahradí všechny shortcody jejich obsahem.
 * @param string $content Vstupní text s potenciálními shortcody.
 * @return string Text po zpracování shortcodů.
 */
function process_shortcodes($content) {
    global $plugin_shortcodes;

    // Regex pro nalezení shortcodů a jejich atributů
    $pattern = '/\[([a-zA-Z0-9_-]+)((?:\s+[a-zA-Z0-9_-]+="[^"]*")*)\]/';

    return preg_replace_callback($pattern, function($matches) use ($plugin_shortcodes) {
        $tag = $matches[1];
        $attrs_string = $matches[2] ?? '';
        
        // Zpracování atributů
        $attrs = [];
        preg_match_all('/([a-zA-Z0-9_-]+)="([^"]*)"/', $attrs_string, $attr_matches, PREG_SET_ORDER);
        foreach ($attr_matches as $match) {
            $attrs[$match[1]] = $match[2];
        }

        // 1. Prioritně hledáme shortcode zaregistrovaný pluginem
        if (isset($plugin_shortcodes[$tag])) {
            return call_user_func($plugin_shortcodes[$tag], $attrs);
        }

        // 2. Pokud nebyl nalezen, hledáme v databázi (starší systém)
        $db = db_connect();
        $stmt = $db->prepare("SELECT content FROM shortcodes WHERE name = ?");
        $stmt->execute([$tag]);
        $shortcode = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($shortcode) {
            ob_start();
            eval('?>' . $shortcode['content']);
            return ob_get_clean();
        }

        return $matches[0]; // Shortcode nenalezen, vrátíme původní text
    }, $content);
}

// ... zbytek souboru (get_all_shortcodes, atd.) zůstává stejný ...
function get_all_shortcodes() {
    $db = db_connect();
    return $db->query("SELECT id, name FROM shortcodes ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
function get_shortcode_by_id($id) {
    $db = db_connect();
    $stmt = $db->prepare("SELECT * FROM shortcodes WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function create_shortcode($data) {
    $db = db_connect();
    $sql = "INSERT INTO shortcodes (name, content) VALUES (?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$data['name'], $data['content']]);
    return $db->lastInsertId();
}
function update_shortcode($id, $data) {
    $db = db_connect();
    $sql = "UPDATE shortcodes SET name = ?, content = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute([$data['name'], $data['content'], $id]);
}
function delete_shortcode($id) {
    $db = db_connect();
    $stmt = $db->prepare("DELETE FROM shortcodes WHERE id = ?");
    $stmt->execute([$id]);
}
