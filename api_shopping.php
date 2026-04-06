<?php
require_once 'auth.php';
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

require_csrf_token();

$material_id = (int)($_POST['material_id'] ?? 0);
$mode = $_POST['mode'] ?? 'toggle';
$requested_qty = max(1, (int)($_POST['quantity'] ?? 1));

if ($material_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing material_id']);
    exit;
}

try {
    $columns = $pdo->query("DESCRIBE materials")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('needs_buying', $columns, true)) {
        $pdo->exec("ALTER TABLE materials ADD COLUMN needs_buying TINYINT(1) DEFAULT 0");
    }
    if (!in_array('shopping_qty', $columns, true)) {
        $pdo->exec("ALTER TABLE materials ADD COLUMN shopping_qty INT NOT NULL DEFAULT 1");
    }
    $pdo->exec("UPDATE materials SET shopping_qty = 1 WHERE shopping_qty IS NULL OR shopping_qty < 1");

    $stmt = $pdo->prepare("SELECT needs_buying, COALESCE(shopping_qty, 1) AS shopping_qty FROM materials WHERE id = ?");
    $stmt->execute([$material_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['success' => false, 'error' => 'Material not found']);
        exit;
    }

    $current_qty = max(1, (int)($current['shopping_qty'] ?? 1));

    if ($mode === 'set_qty') {
        $new_status = 1;
        $new_qty = $requested_qty;
        $upd = $pdo->prepare("UPDATE materials SET needs_buying = 1, shopping_qty = ? WHERE id = ?");
        $upd->execute([$new_qty, $material_id]);
    } else {
        $new_status = ((int)$current['needs_buying']) ? 0 : 1;
        $new_qty = $current_qty;
        $upd = $pdo->prepare("UPDATE materials SET needs_buying = ?, shopping_qty = ? WHERE id = ?");
        $upd->execute([$new_status, $new_qty, $material_id]);
    }

    $countStmt = $pdo->query("SELECT COUNT(*) AS list_count, COALESCE(SUM(GREATEST(COALESCE(shopping_qty, 1), 1)), 0) AS total_qty FROM materials WHERE needs_buying = 1");
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: ['list_count' => 0, 'total_qty' => 0];

    echo json_encode([
        'success' => true,
        'new_status' => (int)$new_status,
        'shopping_qty' => (int)$new_qty,
        'list_count' => (int)($counts['list_count'] ?? 0),
        'total_qty' => (int)($counts['total_qty'] ?? 0)
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
