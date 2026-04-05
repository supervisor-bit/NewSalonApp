<?php require_once 'auth.php';

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

    $clientColumns = $pdo->query("DESCRIBE clients")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_active', $clientColumns)) {
        echo "Sloupec 'is_active' u klientek chybí. Přidávám ho...\n";
        $pdo->exec("ALTER TABLE clients ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        echo "Sloupec pro neaktivní klientky byl úspěšně přidán.\n";
    } else {
        echo "Sloupec 'is_active' u klientek už existuje.\n";
    }

    if (!in_array('client_tags', $clientColumns)) {
        echo "Sloupec 'client_tags' u klientek chybí. Přidávám ho...\n";
        $pdo->exec("ALTER TABLE clients ADD COLUMN client_tags VARCHAR(255) DEFAULT NULL");
        echo "Sloupec pro interní štítky byl úspěšně přidán.\n";
    } else {
        echo "Sloupec 'client_tags' u klientek už existuje.\n";
    }
    
    echo "Povedlo se! Aplikace by měla být zase funkční.\n";
} catch (PDOException $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}
?>
