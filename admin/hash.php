<?php
// hash.php - Jednoduchý skript pro vygenerování hashe hesla

$passwordToHash = '57324962AbC';
$hashedPassword = password_hash($passwordToHash, PASSWORD_DEFAULT);

echo "<h1>Nový hash pro heslo 'password'</h1>";
echo "<p>Zkopírujte následující řetězec a vložte ho do databáze:</p>";
echo "<pre style='background: #eee; padding: 10px; border: 1px solid #ccc; font-size: 16px;'>" . htmlspecialchars($hashedPassword) . "</pre>";
?>