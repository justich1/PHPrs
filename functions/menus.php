<?php
// functions/menus.php - Funkce pro zobrazení menu na webu a pro správu v administraci

/**
 * Načte položky menu pro dané umístění a jazyk pro zobrazení na veřejném webu.
 * @param string $location Umístění menu (např. 'header').
 * @param string $lang Jazykový kód.
 * @return array Pole položek menu.
 */
function get_menu_items($location, $lang) {
    try {
        $db = db_connect();

        // KROK 1: Načteme základní informace o položkách a jejich názvy.
        $sql_items = "SELECT
                        mi.id as item_id,
                        mit.title,
                        mi.page_id,
                        mi.custom_url
                    FROM
                        menus AS m
                    JOIN
                        menu_items AS mi ON m.id = mi.menu_id
                    JOIN
                        menu_items_translations AS mit ON mi.id = mit.item_id
                    WHERE
                        m.location = :location AND mit.language_code = :lang
                    ORDER BY
                        mi.item_order ASC";

        $stmt_items = $db->prepare($sql_items);
        $stmt_items->execute(['location' => $location, 'lang' => $lang]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        // KROK 2: Pro každou položku zvlášť získáme její URL.
        $page_slugs_stmt = $db->prepare("SELECT slug FROM pages_translations WHERE page_id = :page_id AND language_code = :lang");

        $final_menu = [];
        foreach ($items as $item) {
            $url = '#'; // Bezpečný výchozí odkaz
            $is_internal = false;

            // Primárně se snažíme najít slug pro interní stránku
            if (!empty($item['page_id'])) {
                $page_slugs_stmt->execute(['page_id' => $item['page_id'], 'lang' => $lang]);
                $slug_result = $page_slugs_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Použijeme slug pouze pokud byl nalezen
                if ($slug_result && !empty($slug_result['slug'])) {
                    $url = $slug_result['slug'];
                    $is_internal = true;
                }
            }
            
            // Pokud se nepodařilo najít interní odkaz A existuje vlastní URL, použijeme ho
    if ($url === '#' && !empty($item['custom_url'])) {
        $url = $item['custom_url'];
        $is_internal = false;
        $target = '_blank';  
    } else {
        $target = '_self';
    }

    $final_menu[] = [
        'title' => $item['title'],
        'url' => $url,
        'is_internal' => $is_internal,
        'target' => $target
    ];
}

        return $final_menu;

    } catch (\PDOException $e) {
        // V případě chyby databáze vrátíme prázdné pole, aby web nespadl
        return [];
    }
}

// --- Funkce pro administraci ---

function get_all_menus() {
    $db = db_connect();
    return $db->query("SELECT * FROM menus ORDER BY name ASC")->fetchAll();
}

function get_menu_details($menu_id) {
    $db = db_connect();
    $details = [];
    
    $stmt = $db->prepare("SELECT * FROM menus WHERE id = ?");
    $stmt->execute([$menu_id]);
    $details = $stmt->fetch();
    $details['items'] = [];

    $sql = "SELECT mi.id, mi.page_id, mi.custom_url, mi.item_order, pt.slug as page_slug
            FROM menu_items mi
            LEFT JOIN pages_translations pt ON mi.page_id = pt.page_id AND pt.language_code = :default_lang
            WHERE mi.menu_id = :menu_id
            ORDER BY mi.item_order ASC";
    $stmt_items = $db->prepare($sql);
    $stmt_items->execute(['menu_id' => $menu_id, 'default_lang' => DEFAULT_LANG]);
    $items = $stmt_items->fetchAll();

    $stmt_trans = $db->prepare("SELECT * FROM menu_items_translations WHERE item_id = ?");
    foreach($items as $item) {
        $stmt_trans->execute([$item['id']]);
        $translations = $stmt_trans->fetchAll();
        $item['translations'] = [];
        foreach($translations as $t) {
            $item['translations'][$t['language_code']] = $t;
        }
        $details['items'][] = $item;
    }
    return $details;
}

function add_menu_item($menu_id, $data) {
    $db = db_connect();
    $db->beginTransaction();
    try {
        $page_id = ($data['link_type'] == 'page') ? (int)$data['page_id'] : null;
        $custom_url = ($data['link_type'] == 'custom') ? $data['custom_url'] : null;

        $sql = "INSERT INTO menu_items (menu_id, page_id, custom_url) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$menu_id, $page_id, $custom_url]);
        $item_id = $db->lastInsertId();

        $sql_trans = "INSERT INTO menu_items_translations (item_id, language_code, title) VALUES (?, ?, ?)";
        $stmt_trans = $db->prepare($sql_trans);
        foreach($data['translations'] as $lang => $t) {
            if (!empty($t['title'])) {
                $stmt_trans->execute([$item_id, $lang, $t['title']]);
            }
        }
        $db->commit();
    } catch (\PDOException $e) {
        $db->rollBack();
    }
}

function update_menu_item($item_id, $translations) {
    $db = db_connect();
    $db->beginTransaction();
    try {
        $stmt_del = $db->prepare("DELETE FROM menu_items_translations WHERE item_id = ?");
        $stmt_del->execute([$item_id]);

        $stmt_ins = $db->prepare("INSERT INTO menu_items_translations (item_id, language_code, title) VALUES (?, ?, ?)");
        foreach($translations as $lang => $t) {
            if (!empty($t['title'])) {
                $stmt_ins->execute([$item_id, $lang, $t['title']]);
            }
        }
        $db->commit();
    } catch (\PDOException $e) {
        $db->rollBack();
    }
}

function delete_menu_item($item_id) {
    $db = db_connect();
    $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$item_id]);
}

/**
 * Aktualizuje pořadí položek v menu na základě pole ID.
 * @param array $order_data Pole ID položek v novém pořadí.
 */
function update_menu_order($order_data) {
    $db = db_connect();
    try {
        // Připravíme si SQL dotaz pro aktualizaci
        $stmt = $db->prepare("UPDATE menu_items SET item_order = ? WHERE id = ?");
        
        // Projdeme pole ID a jako pořadí použijeme index (0, 1, 2...)
        foreach ($order_data as $order => $id) {
            // Provedeme dotaz pro každý řádek zvlášť
            $stmt->execute([(int)$order, (int)$id]);
        }
    } catch (\PDOException $e) {
        // Pokud nastane chyba, zobrazíme ji pro snadnější ladění.
        // V produkčním prostředí by se toto mělo logovat do souboru.
        die("Došlo k chybě databáze při ukládání pořadí: " . $e->getMessage());
    }
}
