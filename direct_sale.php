<?php require_once 'auth.php';
require_csrf_token();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?view=sales');
    exit;
}

$product_ids = $_POST['product_ids'] ?? [];
$quantities = $_POST['product_amounts'] ?? [];
$unit_prices = $_POST['product_prices'] ?? [];

if (!is_array($product_ids) || empty($product_ids)) {
    $fallback_product_id = (int)($_POST['product_id'] ?? 0);
    if ($fallback_product_id > 0) {
        $product_ids = [$fallback_product_id];
        $quantities = [$_POST['quantity'] ?? 1];
        $unit_prices = [$_POST['unit_price'] ?? 0];
    }
}

$sold_at = trim($_POST['sold_at'] ?? date('Y-m-d'));
$note = trim($_POST['note'] ?? '');
$note = mb_substr($note, 0, 255);

$valid_product_ids = array_values(array_filter(array_map('intval', (array)$product_ids)));
if (empty($valid_product_ids)) {
    $_SESSION['msg'] = 'Vyberte alespoň jeden produkt pro rychlý prodej.';
    header('Location: index.php?view=sales');
    exit;
}

try {
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

    $productStmt = $pdo->prepare("SELECT brand, name, price FROM products WHERE id = ? AND is_active = 1");
    $insert = $pdo->prepare("INSERT INTO direct_sales (product_id, quantity, unit_price, sold_at, note) VALUES (?, ?, ?, ?, ?)");

    $pdo->beginTransaction();
    $saved_count = 0;
    $total = 0;

    foreach ($product_ids as $idx => $raw_product_id) {
        $product_id = (int)$raw_product_id;
        if ($product_id <= 0) {
            continue;
        }

        $quantity = max(1, (int)($quantities[$idx] ?? 1));
        $unit_price = max(0, (int)($unit_prices[$idx] ?? 0));

        $productStmt->execute([$product_id]);
        $product = $productStmt->fetch();

        if (!$product) {
            continue;
        }

        if ($unit_price <= 0) {
            $unit_price = (int)($product['price'] ?? 0);
        }

        $insert->execute([$product_id, $quantity, $unit_price, $sold_at, $note !== '' ? $note : null]);
        $saved_count++;
        $total += $unit_price * $quantity;
    }

    if ($saved_count === 0) {
        throw new RuntimeException('Nepodařilo se uložit žádný platný produkt.');
    }

    $pdo->commit();
    $_SESSION['msg'] = 'Rychlý prodej byl uložen do tržeb (' . $saved_count . ' polož' . ($saved_count === 1 ? 'ka' : 'ek') . ', ' . $total . ' Kč).';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['msg'] = 'Chyba při ukládání rychlého prodeje: ' . $e->getMessage();
}

header('Location: index.php?view=sales');
exit;
