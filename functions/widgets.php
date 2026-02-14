<?php
// functions/widgets.php - Funkce pro správu widgetů v postranních panelech

/**
 * Načte widgety pro daný postranní panel z databáze.
 * Tato funkce se používá na frontendu webu pro zobrazení panelů.
 *
 * @param string $sidebar_id Identifikátor panelu ('left' nebo 'right').
 * @return array Pole s widgety, seřazené podle pozice.
 */
function get_sidebar_widgets(string $sidebar_id): array {
    try {
        $db = db_connect(); // Použijeme vaši existující funkci pro připojení k DB

        // Zkontrolujeme, zda tabulka 'widgets' existuje, abychom předešli chybám
        $table_check = $db->query("SHOW TABLES LIKE 'widgets'");
        if ($table_check->rowCount() == 0) {
            return []; // Tabulka neexistuje, není co načítat
        }

        $stmt = $db->prepare(
            "SELECT title, content FROM widgets WHERE sidebar = :sidebar_id ORDER BY position ASC, id DESC"
        );
        $stmt->execute(['sidebar_id' => $sidebar_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (\PDOException $e) {
        // V případě chyby vrátíme prázdné pole, aby web nespadl.
        // V produkčním prostředí by se chyba měla logovat.
        // error_log('Widget loading error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Načte widgety pro stránku administrace.
 * Načítá všechny potřebné sloupce pro editační formuláře.
 *
 * @param string $sidebar_id Identifikátor panelu ('left' nebo 'right').
 * @return array Pole s kompletními daty widgetů.
 */
function get_sidebar_widgets_for_admin(string $sidebar_id): array {
    try {
        $db = db_connect();

        $table_check = $db->query("SHOW TABLES LIKE 'widgets'");
        if ($table_check->rowCount() == 0) {
            return [];
        }

        $stmt = $db->prepare(
            "SELECT id, title, content, position FROM widgets WHERE sidebar = :sidebar_id ORDER BY position ASC, id DESC"
        );
        $stmt->execute(['sidebar_id' => $sidebar_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (\PDOException $e) {
        // error_log('Admin widget loading error: ' . $e->getMessage());
        return [];
    }
}
