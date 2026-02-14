<?php
// admin/index.php - Vstupní brána do administrace

// Jako úplně první řádek vložíme náš ladící skript
require_once 'debug.php';

session_start();

// Pokud uživatel není přihlášen, přesměrujeme ho na přihlašovací stránku
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Načtení potřebných souborů pro zobrazení stránky
require_once '../config/config.php';
require_once '../functions/database.php';

// Pokud je přihlášen, zobrazíme hlavní panel (dashboard)
$page_title = "Nástěnka";
include 'includes/header.php';
include 'dashboard.php';
include 'includes/footer.php';
