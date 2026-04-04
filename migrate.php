<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db.php';

try {
    echo "Kontroluji databázi...\n";
    $result = $pdo->query("DESCRIBE materials");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('needs_buying', $columns)) {
        echo "Sloupec 'needs_buying' chybí. Přidávám ho...\n";
        $pdo->exec("ALTER TABLE materials ADD COLUMN needs_buying TINYINT(1) DEFAULT 0");
        echo "Sloupec byl úspěšně přidán.\n";
    } else {
        echo "Sloupec 'needs_buying' už existuje.\n";
    }
    
    echo "Povedlo se! Aplikace by měla být zase funkční.\n";
} catch (PDOException $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}
?>
