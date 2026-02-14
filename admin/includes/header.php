<?php
// Ověření, zda je soubor volán z chráněné části
if (!isset($_SESSION['user_id'])) {
    die('Přímý přístup není povolen.');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Administrace') ?> - <?= SITE_NAME ?></title>
<style>
    body {
        font-family: sans-serif;
        margin: 0;
        background-color: #f8f9fa;
    }

    .admin-header {
        background-color: #343a40;
        color: white;
        padding: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .admin-header h1 {
        margin: 0;
        font-size: 1.5rem;
    }

    .admin-header a {
        color: white;
        text-decoration: none;
    }

    .admin-nav {
        display: flex;
        flex-wrap: wrap;
        margin-top: 0.5rem;
    }

    .admin-nav a {
        padding: 0.5rem 1rem;
        text-decoration: none;
        color: #f8f9fa;
        background-color: #495057;
        margin: 5px 5px 0 0;
        border-radius: 4px;
    }

    .admin-nav a:hover {
        background-color: #6c757d;
    }

    .admin-container {
        display: flex;
    }

    .admin-sidebar {
        width: 200px;
        background-color: #e9ecef;
        min-height: calc(100vh - 70px);
        padding: 1rem;
        transition: transform 0.3s ease;
    }

    .admin-sidebar ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .admin-sidebar a {
        text-decoration: none;
        color: #212529;
        display: block;
        padding: 0.75rem;
        border-radius: 4px;
    }

    .admin-sidebar a:hover {
        background-color: #dee2e6;
    }

    .admin-content {
        flex-grow: 1;
        padding: 2rem;
        min-width: 0;
    }

    .admin-content h2 {
        margin-top: 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }

    th, td {
        padding: 0.75rem;
        border: 1px solid #dee2e6;
        text-align: left;
    }

    th {
        background-color: #f8f9fa;
    }

    .warning {
        background-color: #fff3cd;
        border: 1px solid #ffeeba;
        color: #856404;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
    }

    textarea {
        width: 100%;
        min-height: 250px;
        font-family: monospace;
    }

    .hamburger {
        display: none;
        cursor: pointer;
        font-size: 1.5rem;
        background: none;
        border: none;
        color: white;
    }

    /* RESPONSIVITA */
@media (max-width: 768px) {
    .admin-container {
        flex-direction: column;
    }

    .admin-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        width: 250px;
        background-color: #e9ecef;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 10000;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
    }

    .admin-sidebar.show {
        transform: translateX(0);
    }

    .hamburger {
        display: block;
    }

    .admin-content {
        padding: 1rem;
    }

    .user-info {
        margin-top: 10px;
        font-size: 0.9rem;
    }

    table, th, td {
        font-size: 0.9rem;
    }
}

    @media (max-width: 480px) {
        .admin-header h1 {
            font-size: 1.25rem;
        }

        th, td {
            padding: 0.5rem;
        }

        textarea {
            min-height: 150px;
        }
    }
</style>
</head>
<body>
<header class="admin-header">
    <h1><a href="index.php">Administrace</a></h1>
    <button class="hamburger" onclick="toggleSidebar()">☰</button>
    <div class="user-info">
        Přihlášen jako: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        <a href="logout.php" style="margin-left: 20px;">Odhlásit se</a>
    </div>
</header>
<div class="admin-container">
    <aside class="admin-sidebar" id="adminSidebar">
        <nav>
            <ul>
                <li><a href="index.php">Nástěnka</a></li>
                <li><a href="pages.php">Stránky</a></li>
                <li><a href="menus.php">Menu</a></li>
                <li><a href="shortcodes.php">Shortcody</a></li>
                <li><a href="plugins.php">Pluginy</a></li>
                <li><a href="theme_options.php">Vzhled</a></li>
                <li><a href="widgets.php">Widgety</a></li>
                <li><a href="editor.php">Editor šablony</a></li>
                <li><a href="users.php">Správa uživatelů</a></li>
                <li><a href="settings.php">Nastavení</a></li>
            </ul>
        </nav>
    </aside>
    <main class="admin-content">
        <h2><?= htmlspecialchars($page_title ?? '') ?></h2>
