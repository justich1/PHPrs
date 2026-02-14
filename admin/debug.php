<?php
// admin/debug.php - Soubor pro ladění chyb

// Zapneme zobrazení všech chyb přímo na stránce.
// Toto je pouze pro ladění, na funkčním webu by to mělo být vypnuté!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
