<?php
// setup.php
// Tento skript automaticky vytvoří databázi a tabulky. Zjednodušení pro MAMP/XAMPP.

$mamp_hosts = [
    ['host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'pass' => ''],     // XAMPP / běžné
    ['host' => '127.0.0.1', 'port' => '8889', 'user' => 'root', 'pass' => 'root'], // MAMP pøeddefinované
];

$pdo = null;
foreach ($mamp_hosts as $h) {
    try {
        $dsn = "mysql:host=" . $h['host'] . ";port=" . $h['port'] . ";charset=utf8mb4";
        $test_pdo = new PDO($dsn, $h['user'], $h['pass']);
        $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo = $test_pdo;
        
        // Uložení funkčního připojení do db.php
        $db_code = "<?php\n"
                 . "\$host = '" . $h['host'] . "';\n"
                 . "\$port = '" . $h['port'] . "';\n"
                 . "\$dbname = 'karta_db';\n"
                 . "\$username = '" . $h['user'] . "';\n"
                 . "\$password = '" . $h['pass'] . "';\n\n"
                 . "try {\n"
                 . "    \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$dbname;charset=utf8mb4\", \$username, \$password);\n"
                 . "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n"
                 . "    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);\n"
                 . "} catch (PDOException \$e) {\n"
                 . "    die(\"<h3>Chyba připojení</h3><p>\" . \$e->getMessage() . \"</p>\");\n"
                 . "}\n"
                 . "?>";
        file_put_contents('db.php', $db_code);
        break;
    } catch (PDOException $e) {
        // Zkusí další
    }
}

if (!$pdo) {
    die("<h3>Nepodařilo se připojit k MySQL</h3><p>Ujistěte se, že máte zapnutý MAMP nebo jiný lokální server.</p>");
}

// Načtení a spuštění příkazů z schema.sql
$sql = file_get_contents('schema.sql');
$pdo->exec($sql);

echo "<h2>Instalace úspěšná!</h2>";
echo "<p>Databáze byla vytvořena. Připojovací údaje se uložily do db.php.</p>";
echo "<a href='index.php'>Zpět do aplikace</a>";
?>
