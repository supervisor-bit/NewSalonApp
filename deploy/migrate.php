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

    if (!in_array('shopping_qty', $columns)) {
        echo "Sloupec 'shopping_qty' chybí. Přidávám ho...\n";
        $pdo->exec("ALTER TABLE materials ADD COLUMN shopping_qty INT NOT NULL DEFAULT 1");
        echo "Sloupec pro počet kusů byl úspěšně přidán.\n";
    } else {
        echo "Sloupec 'shopping_qty' už existuje.\n";
    }

    if (!in_array('stock_state', $columns)) {
        echo "Sloupec 'stock_state' chybí. Přidávám ho...\n";
        $pdo->exec("ALTER TABLE materials ADD COLUMN stock_state VARCHAR(20) NOT NULL DEFAULT 'none'");
        echo "Sloupec pro stav materiálu byl úspěšně přidán.\n";
    } else {
        echo "Sloupec 'stock_state' už existuje.\n";
    }

    $pdo->exec("UPDATE materials SET shopping_qty = 1 WHERE shopping_qty IS NULL OR shopping_qty < 1");
    $pdo->exec("UPDATE materials SET stock_state = 'none' WHERE stock_state IS NULL OR stock_state = ''");

    $clientColumns = $pdo->query("DESCRIBE clients")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_active', $clientColumns)) {
        echo "Sloupec 'is_active' u klientů chybí. Přidávám ho...\n";
        $pdo->exec("ALTER TABLE clients ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        echo "Sloupec pro neaktivní klienty byl úspěšně přidán.\n";
    } else {
        echo "Sloupec 'is_active' u klientů už existuje.\n";
    }

    if (!in_array('client_tags', $clientColumns)) {
        echo "Sloupec 'client_tags' u klientů chybí. Přidávám ho...\n";
        $pdo->exec("ALTER TABLE clients ADD COLUMN client_tags VARCHAR(255) DEFAULT NULL");
        echo "Sloupec pro interní štítky byl úspěšně přidán.\n";
    } else {
        echo "Sloupec 'client_tags' u klientů už existuje.\n";
    }

    if (!in_array('is_favorite', $clientColumns)) {
        echo "Sloupec 'is_favorite' u klientů chybí. Přidávám ho...\n";
        $pdo->exec("ALTER TABLE clients ADD COLUMN is_favorite TINYINT(1) DEFAULT 0");
        echo "Sloupec pro oblíbené klienty byl úspěšně přidán.\n";
    } else {
        echo "Sloupec 'is_favorite' u klientů už existuje.\n";
    }

    echo "Kontroluji tabulku direct_sales...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS direct_sales (
        id INT PRIMARY KEY AUTO_INCREMENT,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_price INT NOT NULL DEFAULT 0,
        sold_at DATE NOT NULL,
        note VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Tabulka pro rychlý prodej bez klienta je připravena.\n";
    
    echo "Povedlo se! Aplikace by měla být zase funkční.\n";
} catch (PDOException $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}
?>
