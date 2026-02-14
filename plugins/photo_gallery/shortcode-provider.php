<?php
// /plugins/photo_gallery/shortcode-provider.php

/**
 * Tento soubor se stará o poskytnutí dynamického seznamu shortcodů
 * pro plugin Fotogalerie.
 */

// Bezpečnostní pojistka, aby soubor nešel spustit přímo
if (!defined('ABSPATH')) { // Předpokládáme, že máte v config.php definovanou konstantu ABSPATH
    // Pokud ne, můžete tento řádek smazat, ale je to dobrý zvyk
    // define('ABSPATH', dirname(__DIR__, 2)); // Příklad definice v config.php
}

$gallery_shortcodes = [];

try {
    // Připojení k databázi musíme navázat znovu, protože tento soubor je izolovaný
    $db_path = __DIR__ . '/../../functions/database.php';
    if(file_exists($db_path)) {
        require_once $db_path;
        $db = db_connect();

        $stmt = $db->query("SELECT id, name FROM galleries ORDER BY name ASC");
        $galleries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($galleries) {
            foreach ($galleries as $gallery) {
                $id = htmlspecialchars($gallery['id']);
                $name = htmlspecialchars($gallery['name']);
                // Pro každou galerii vytvoříme platný a popsaný shortcode
                $gallery_shortcodes['[gallery id="' . $id . '"]'] = "Galerie: $name";
            }
        } else {
             $gallery_shortcodes[''] = "Zatím nebyly vytvořeny žádné galerie.";
        }
    }
} catch (PDOException $e) {
    // Chyba při připojení k DB nebo tabulka neexistuje
    $gallery_shortcodes[''] = "Chyba při načítání galerií.";
}

// Klíčový krok: Soubor VRÁTÍ pole s připravenými shortcody
return $gallery_shortcodes;