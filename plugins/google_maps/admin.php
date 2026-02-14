<?php
session_start();
if (!isset($_SESSION['user_id'])) { die('Přístup odepřen.'); }
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Návod pro Google Maps Plugin</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f1f1f1; line-height: 1.6; }
        .content { background: white; padding: 20px; border-radius: 5px; }
        h1 { margin-top: 0; }
        code { background: #e9ecef; padding: 2px 5px; border-radius: 3px; }
        .example { border-left: 4px solid #007bff; padding-left: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="content">
        <h1>Plugin: Google Maps Shortcode</h1>
        <p>Tento plugin umožňuje jednoduše vložit responzivní mapu z Google Maps do obsahu jakékoliv stránky.</p>
        
        <h2>Jak použít</h2>
        <p>V editoru stránek vložte na místo, kde chcete zobrazit mapu, následující shortcode:</p>
        <div class="example">
            <code>[mapa adresa="ZDE ZADEJTE ADRESU"]</code>
        </div>

        <h3>Příklady použití:</h3>
        <ul>
            <li><code>[mapa adresa="Václavské náměstí, Praha"]</code></li>
            <li><code>[mapa adresa="Karlův most, Praha 1"]</code></li>
            <li><code>[mapa adresa="Eiffelova věž, Paříž, Francie"]</code></li>
        </ul>

        <p>Mapa se automaticky přizpůsobí šířce obsahu.</p>
    </div>
</body>
</html>
