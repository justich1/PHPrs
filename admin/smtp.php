<?php
// Advanced PHP Connection Diagnostics

header('Content-Type: text/plain; charset=utf-8');

echo "=============================================\n";
echo "Pokročilá diagnostika PHP připojení\n";
echo "=============================================\n\n";

// 1. Kontrola verze PHP
echo "PHP verze: " . PHP_VERSION . "\n\n";

// 2. Kontrola zakázaných funkcí
echo "--- Kontrola 'disable_functions' v php.ini ---\n";
$disabled_functions = ini_get('disable_functions');
if (empty($disabled_functions)) {
    echo "OK: Žádné funkce nejsou zakázané.\n";
} else {
    echo "Nalezeny zakázané funkce: " . htmlspecialchars($disabled_functions) . "\n";
    if (strpos($disabled_functions, 'fsockopen') !== false) {
        echo "KRITICKÁ CHYBA: Funkce 'fsockopen' je zakázaná! To je příčina problému.\n";
    }
    if (strpos($disabled_functions, 'stream_socket_client') !== false) {
        echo "KRITICKÁ CHYBA: Funkce 'stream_socket_client' je zakázaná! To je příčina problému.\n";
    }
}
echo "\n";

// 3. Kontrola allow_url_fopen
echo "--- Kontrola 'allow_url_fopen' v php.ini ---\n";
if (ini_get('allow_url_fopen')) {
    echo "OK: Direktivum 'allow_url_fopen' je povoleno.\n";
} else {
    echo "VAROVÁNÍ: Direktivum 'allow_url_fopen' je zakázáno. To může způsobovat problémy.\n";
}
echo "\n";

// 4. Test DNS překladu z PHP
echo "--- Test DNS překladu z PHP ---\n";
$hostname_to_test = 'admin.pc-pohotovost.eu';
echo "Pokouším se přeložit hostname: " . $hostname_to_test . "\n";
$ip = @gethostbyname($hostname_to_test);
if ($ip === $hostname_to_test) {
    echo "CHYBA: Nepodařilo se přeložit hostname na IP adresu. Problém s DNS.\n";
} else {
    echo "OK: Hostname úspěšně přeložen na IP: " . htmlspecialchars($ip) . "\n";
}
echo "\n";

// 5. Test přímého připojení na Seznam.cz
echo "--- Test přímého připojení na smtp.seznam.cz:465 ---\n";
$host = 'ssl://admin.pc-pohotovost.eu';
$port = 465;
$timeout = 10;

$socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

if (!$socket) {
    echo "SPOJENÍ SELHALO!\n";
    echo "Číslo chyby (errno): " . htmlspecialchars($errno) . "\n";
    echo "Text chyby (errstr): " . htmlspecialchars($errstr) . "\n\n";
    echo "ZÁVĚR: Pokud všechny předchozí testy prošly, problém je s největší pravděpodobností mimo PHP.\n";
    echo "Pravděpodobně se jedná o bezpečnostní modul serveru (SELinux nebo AppArmor), který blokuje\n";
    echo "odchozí síťovou komunikaci pro webový server (uživatele www-data).\n\n";
    echo "Další kroky: Zkontrolujte logy SELinuxu (příkaz: sudo ausearch -m avc -ts recent)\n";
    echo "nebo AppArmor (příkaz: sudo aa-status).\n";

} else {
    echo "SPOJENÍ ÚSPĚŠNÉ!\n";
    echo "Odpověď serveru: " . htmlspecialchars(fgets($socket, 512)) . "\n";
    fclose($socket);
    echo "\nZÁVĚR: Vše se zdá být v pořádku. Problém musí být ve specifické kombinaci host/port/šifrování\n";
    echo "ve vašem pluginu. Tato diagnostika ale potvrzuje, že PHP jako takové komunikovat umí.\n";
}

?>
