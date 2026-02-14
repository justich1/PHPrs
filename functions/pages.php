<?php
// functions/pages.php - Funkce pro načítání obsahu stránek

/**
 * Načte data stránky z databáze na základě její URL adresy (slug) a jazyka.
 * @param string $slug URL slug stránky (např. 'o-nas').
 * @param string $lang Jazykový kód (např. 'cs').
 * @return array|false Asociativní pole s daty stránky nebo false, pokud nebyla nalezena.
 */
function get_page_by_slug($slug, $lang) {
    try {
        $db = db_connect();
        $sql = "SELECT p.id, p.created_at, pt.title, pt.content, pt.slug, pt.language_code
                FROM pages AS p
                JOIN pages_translations AS pt ON p.id = pt.page_id
                WHERE pt.slug = :slug AND pt.language_code = :lang";

        $stmt = $db->prepare($sql);
        $stmt->execute(['slug' => $slug, 'lang' => $lang]);
        return $stmt->fetch();
    } catch (\PDOException $e) {
        return false;
    }
}

/**
 * Načte všechny stránky pro výpis v administraci.
 * @return array Pole stránek.
 */
function get_all_pages() {
    try {
        $db = db_connect();
        // Načteme název v defaultním jazyce pro přehlednost
        $sql = "SELECT p.id, p.created_at, pt.title
                FROM pages p
                LEFT JOIN pages_translations pt ON p.id = pt.page_id AND pt.language_code = :default_lang
                ORDER BY p.id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute(['default_lang' => DEFAULT_LANG]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Načte detail stránky včetně všech jejích překladů.
 * @param int $id ID stránky.
 * @return array|null Data stránky nebo null.
 */
function get_page_details($id) {
    try {
        $db = db_connect();
        $page = ['id' => $id, 'translations' => []];
        $sql = "SELECT * FROM pages_translations WHERE page_id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $translations = $stmt->fetchAll();
        foreach ($translations as $t) {
            $page['translations'][$t['language_code']] = $t;
        }
        return $page;
    } catch (\PDOException $e) {
        return null;
    }
}

/**
 * Vytvoří novou stránku a její překlady.
 * @param array $translations Data z formuláře.
 * @return int ID nově vytvořené stránky.
 */
function create_page($translations) {
    $db = db_connect();
    $db->beginTransaction();
    try {
        // Vytvoříme hlavní záznam stránky
        $db->exec("INSERT INTO pages (created_at) VALUES (NOW())");
        $page_id = $db->lastInsertId();

        // Vložíme překlady
        $sql = "INSERT INTO pages_translations (page_id, language_code, title, slug, content) VALUES (:page_id, :lang, :title, :slug, :content)";
        $stmt = $db->prepare($sql);
        foreach ($translations as $lang => $data) {
            if (!empty($data['title'])) { // Ukládáme jen vyplněné jazyky
                $stmt->execute([
                    'page_id' => $page_id,
                    'lang' => $lang,
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'content' => $data['content']
                ]);
            }
        }
        $db->commit();
        return $page_id;
    } catch (\PDOException $e) {
        $db->rollBack();
        return 0;
    }
}

/**
 * Aktualizuje stávající stránku.
 * @param int $id ID stránky.
 * @param array $translations Data z formuláře.
 */
function update_page($id, $translations) {
    $db = db_connect();
    try {
        $db->beginTransaction();
        $stmt_del = $db->prepare("DELETE FROM pages_translations WHERE page_id = :id");
        $stmt_del->execute(['id' => $id]);

        $sql_ins = "INSERT INTO pages_translations (page_id, language_code, title, slug, content) VALUES (:page_id, :lang, :title, :slug, :content)";
        $stmt_ins = $db->prepare($sql_ins);
        foreach ($translations as $lang => $data) {
             if (!empty($data['title'])) {
                $stmt_ins->execute([
                    'page_id' => $id,
                    'lang' => $lang,
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'content' => $data['content']
                ]);
            }
        }
        $db->commit();
    } catch (\PDOException $e) {
        $db->rollBack();
    }
}

/**
 * Smaže stránku.
 * @param int $id ID stránky.
 */
function delete_page($id) {
    try {
        $db = db_connect();
        // Díky ON DELETE CASCADE se smažou i překlady
        $stmt = $db->prepare("DELETE FROM pages WHERE id = :id");
        $stmt->execute(['id' => $id]);
    } catch (\PDOException $e) {
        // Logování
    }
}

/**
 * Načte všechny slugy pro danou stránku pro přepínač jazyků.
 * @param int $page_id ID stránky.
 * @return array Pole slugů ve formátu [jazyk => slug].
 */
function get_page_slugs($page_id) {
    if (!$page_id) return [];
    try {
        $db = db_connect();
        $stmt = $db->prepare("SELECT language_code, slug FROM pages_translations WHERE page_id = ?");
        $stmt->execute([$page_id]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Získá slugy pro hlavní stránku ve všech jazycích.
 * @return array Pole slugů ve formátu [jazyk => slug].
 */
function get_homepage_slugs() {
    try {
        $db = db_connect();
        $sql = "SELECT pt.language_code, pt.slug 
                FROM pages p 
                JOIN pages_translations pt ON p.id = pt.page_id 
                WHERE p.is_homepage = 1";
        return $db->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Nastaví danou stránku jako hlavní.
 * @param int $page_id ID stránky, která má být hlavní.
 */
function set_homepage($page_id) {
    $db = db_connect();
    $db->beginTransaction();
    try {
        // Nejprve zrušíme příznak u všech ostatních stránek
        $db->exec("UPDATE pages SET is_homepage = 0");
        // Poté nastavíme příznak u vybrané stránky
        $stmt = $db->prepare("UPDATE pages SET is_homepage = 1 WHERE id = ?");
        $stmt->execute([$page_id]);
        $db->commit();
    } catch (\PDOException $e) {
        $db->rollBack();
    }
}