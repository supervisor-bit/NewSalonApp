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
$requested_state = trim((string)($_POST['state'] ?? 'none'));

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
    if (!in_array('stock_state', $columns, true)) {
        $pdo->exec("ALTER TABLE materials ADD COLUMN stock_state VARCHAR(20) NOT NULL DEFAULT 'none'");
    }
    $pdo->exec("UPDATE materials SET shopping_qty = 1 WHERE shopping_qty IS NULL OR shopping_qty < 1");
    $pdo->exec("UPDATE materials SET stock_state = 'none' WHERE stock_state IS NULL OR stock_state = '' OR stock_state NOT IN ('none', 'opened', 'low', 'ordered')");

    $stmt = $pdo->prepare("SELECT needs_buying, COALESCE(shopping_qty, 1) AS shopping_qty, COALESCE(NULLIF(stock_state, ''), 'none') AS stock_state FROM materials WHERE id = ?");
    $stmt->execute([$material_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['success' => false, 'error' => 'Material not found']);
        exit;
    }

    $current_qty = max(1, (int)($current['shopping_qty'] ?? 1));
    $current_state = (string)($current['stock_state'] ?? 'none');

    if ($mode === 'set_qty') {
        $new_status = 1;
        $new_qty = $requested_qty;
        $new_state = $current_state;
        $upd = $pdo->prepare("UPDATE materials SET needs_buying = 1, shopping_qty = ?, stock_state = ? WHERE id = ?");
        $upd->execute([$new_qty, $new_state, $material_id]);
    } elseif ($mode === 'set_state') {
        $allowedStates = ['none', 'opened', 'low', 'ordered'];
        $new_state = in_array($requested_state, $allowedStates, true) ? $requested_state : 'none';
        if (in_array($new_state, ['low', 'ordered'], true)) {
            $new_status = 1;
        } elseif (in_array($current_state, ['low', 'ordered'], true)) {
            $new_status = 0;
        } else {
            $new_status = (int)$current['needs_buying'];
        }
        $new_qty = $current_qty;
        $upd = $pdo->prepare("UPDATE materials SET stock_state = ?, needs_buying = ?, shopping_qty = ? WHERE id = ?");
        $upd->execute([$new_state, $new_status, $new_qty, $material_id]);
    } else {
        $new_status = ((int)$current['needs_buying']) ? 0 : 1;
        $new_qty = $current_qty;
        $new_state = ($new_status === 0 && in_array($current_state, ['low', 'ordered'], true)) ? 'none' : $current_state;
        $upd = $pdo->prepare("UPDATE materials SET needs_buying = ?, shopping_qty = ?, stock_state = ? WHERE id = ?");
        $upd->execute([$new_status, $new_qty, $new_state, $material_id]);
    }

    $countStmt = $pdo->query("SELECT COUNT(*) AS list_count, COALESCE(SUM(GREATEST(COALESCE(shopping_qty, 1), 1)), 0) AS total_qty FROM materials WHERE needs_buying = 1 OR COALESCE(NULLIF(stock_state, ''), 'none') IN ('low', 'ordered')");
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: ['list_count' => 0, 'total_qty' => 0];

    $stateCountsStmt = $pdo->query("SELECT COALESCE(NULLIF(stock_state, ''), 'none') AS stock_state, COUNT(*) AS cnt FROM materials WHERE COALESCE(NULLIF(stock_state, ''), 'none') IN ('opened', 'low', 'ordered') GROUP BY stock_state");
    $openedCount = 0;
    $lowCount = 0;
    $orderedCount = 0;
    foreach ($stateCountsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (($row['stock_state'] ?? '') === 'opened') $openedCount = (int)$row['cnt'];
        if (($row['stock_state'] ?? '') === 'low') $lowCount = (int)$row['cnt'];
        if (($row['stock_state'] ?? '') === 'ordered') $orderedCount = (int)$row['cnt'];
    }

    echo json_encode([
        'success' => true,
        'new_status' => (int)$new_status,
        'shopping_qty' => (int)$new_qty,
        'stock_state' => $new_state,
        'list_count' => (int)($counts['list_count'] ?? 0),
        'total_qty' => (int)($counts['total_qty'] ?? 0),
        'opened_count' => $openedCount,
        'low_count' => $lowCount,
        'ordered_count' => $orderedCount
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
