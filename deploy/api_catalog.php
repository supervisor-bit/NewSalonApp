<?php
require_once 'auth.php';
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

require_csrf_token();

$action = trim((string)($_POST['action'] ?? ''));
$itemType = trim((string)($_POST['item_type'] ?? ''));
$itemId = (int)($_POST['item_id'] ?? 0);
$ean = preg_replace('/[^0-9A-Za-z]/', '', trim((string)($_POST['ean'] ?? '')));
$quantity = max(1, min(999, (int)($_POST['quantity'] ?? 1)));
$note = trim((string)($_POST['note'] ?? ''));
$note = function_exists('mb_substr') ? mb_substr($note, 0, 255) : substr($note, 0, 255);
$scannedEan = preg_replace('/[^0-9A-Za-z]/', '', trim((string)($_POST['scanned_ean'] ?? '')));
$batchCode = preg_replace('/[^0-9A-Za-z_-]/', '', trim((string)($_POST['batch_code'] ?? '')));
$itemsRaw = $_POST['items_json'] ?? '[]';

try {
    $materialColumns = $pdo->query("SHOW COLUMNS FROM materials")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('needs_buying', $materialColumns, true)) {
        $pdo->exec("ALTER TABLE materials ADD COLUMN needs_buying TINYINT(1) DEFAULT 0");
    }
    if (!in_array('shopping_qty', $materialColumns, true)) {
        $pdo->exec("ALTER TABLE materials ADD COLUMN shopping_qty INT NOT NULL DEFAULT 1");
    }
    if (!in_array('stock_state', $materialColumns, true)) {
        $pdo->exec("ALTER TABLE materials ADD COLUMN stock_state VARCHAR(20) NOT NULL DEFAULT 'none'");
    }
    if (!in_array('ean', $materialColumns, true)) {
        $pdo->exec("ALTER TABLE materials ADD COLUMN ean VARCHAR(64) DEFAULT NULL");
    }

    $productColumns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('ean', $productColumns, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN ean VARCHAR(64) DEFAULT NULL");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_receipts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        batch_code VARCHAR(64) DEFAULT NULL,
        item_type VARCHAR(20) NOT NULL,
        item_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        scanned_ean VARCHAR(64) DEFAULT NULL,
        note VARCHAR(255) DEFAULT NULL,
        received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_receipts_batch_code (batch_code),
        INDEX idx_receipts_type_item (item_type, item_id),
        INDEX idx_receipts_received_at (received_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $receiptColumns = $pdo->query("SHOW COLUMNS FROM stock_receipts")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('batch_code', $receiptColumns, true)) {
        $pdo->exec("ALTER TABLE stock_receipts ADD COLUMN batch_code VARCHAR(64) DEFAULT NULL AFTER id");
    }

    if (!in_array($action, ['save_ean', 'receive_item', 'receive_batch'], true)) {
        echo json_encode(['success' => false, 'error' => 'Unsupported action']);
        exit;
    }

    if ($action === 'receive_batch') {
        $decodedItems = json_decode((string)$itemsRaw, true);
        if (!is_array($decodedItems) || !$decodedItems) {
            echo json_encode(['success' => false, 'error' => 'Dávková příjemka je prázdná.']);
            exit;
        }

        if ($batchCode === '') {
            $batchCode = 'PRJ-' . date('Ymd-His');
        }

        $receipts = [];
        $pdo->beginTransaction();

        foreach ($decodedItems as $row) {
            $rowType = trim((string)($row['item_type'] ?? ''));
            $rowId = (int)($row['item_id'] ?? 0);
            $rowQty = max(1, min(999, (int)($row['qty'] ?? 1)));
            $rowEan = preg_replace('/[^0-9A-Za-z]/', '', trim((string)($row['scanned_ean'] ?? '')));
            $rowNote = trim((string)($row['note'] ?? ''));
            $rowNote = function_exists('mb_substr') ? mb_substr($rowNote, 0, 255) : substr($rowNote, 0, 255);

            if ($rowId <= 0 || !in_array($rowType, ['material', 'product'], true)) {
                throw new RuntimeException('Jedna z položek v dávce není platná.');
            }

            $rowTable = $rowType === 'material' ? 'materials' : 'products';
            $rowLabelSql = $rowType === 'material'
                ? "TRIM(CONCAT(COALESCE(brand, ''), ' ', COALESCE(category, ''), ' ', COALESCE(name, ''))) AS label"
                : "TRIM(CONCAT(COALESCE(brand, ''), ' ', COALESCE(name, ''))) AS label";

            $rowStmt = $pdo->prepare("SELECT {$rowLabelSql}, COALESCE(NULLIF(ean, ''), '') AS ean FROM {$rowTable} WHERE id = ? LIMIT 1");
            $rowStmt->execute([$rowId]);
            $rowItem = $rowStmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowItem) {
                throw new RuntimeException('Položka v dávce nebyla nalezena.');
            }

            if ($rowType === 'material') {
                $reset = $pdo->prepare("UPDATE materials SET needs_buying = 0, stock_state = 'none' WHERE id = ?");
                $reset->execute([$rowId]);
            }

            $receiptEanValue = $rowEan !== '' ? $rowEan : ((string)($rowItem['ean'] ?? '') !== '' ? (string)$rowItem['ean'] : null);
            $insert = $pdo->prepare("INSERT INTO stock_receipts (batch_code, item_type, item_id, quantity, scanned_ean, note, received_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $insert->execute([$batchCode, $rowType, $rowId, $rowQty, $receiptEanValue, $rowNote !== '' ? $rowNote : null]);
            $receiptId = (int)$pdo->lastInsertId();

            $timeStmt = $pdo->prepare("SELECT DATE_FORMAT(received_at, '%Y-%m-%d %H:%i:%s') AS received_at FROM stock_receipts WHERE id = ? LIMIT 1");
            $timeStmt->execute([$receiptId]);
            $receiptTime = (string)($timeStmt->fetchColumn() ?: date('Y-m-d H:i:s'));

            $receipts[] = [
                'id' => $receiptId,
                'batch_code' => $batchCode,
                'item_type' => $rowType,
                'item_id' => $rowId,
                'item_label' => trim((string)($rowItem['label'] ?? '')),
                'qty' => $rowQty,
                'note' => $rowNote,
                'scanned_ean' => $receiptEanValue ?? '',
                'received_at' => $receiptTime
            ];
        }

        $countStmt = $pdo->query("SELECT COUNT(*) AS list_count, COALESCE(SUM(GREATEST(COALESCE(shopping_qty, 1), 1)), 0) AS total_qty FROM materials WHERE needs_buying = 1 OR COALESCE(NULLIF(stock_state, ''), 'none') = 'low'");
        $counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: ['list_count' => 0, 'total_qty' => 0];

        $stateCountsStmt = $pdo->query("SELECT COALESCE(NULLIF(stock_state, ''), 'none') AS stock_state, COUNT(*) AS cnt FROM materials WHERE COALESCE(NULLIF(stock_state, ''), 'none') IN ('opened', 'low') GROUP BY stock_state");
        $openedCount = 0;
        $lowCount = 0;
        foreach ($stateCountsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['stock_state'] ?? '') === 'opened') $openedCount = (int)$row['cnt'];
            if (($row['stock_state'] ?? '') === 'low') $lowCount = (int)$row['cnt'];
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'batch_code' => $batchCode,
            'receipts' => $receipts,
            'list_count' => (int)($counts['list_count'] ?? 0),
            'total_qty' => (int)($counts['total_qty'] ?? 0),
            'opened_count' => $openedCount,
            'low_count' => $lowCount
        ]);
        exit;
    }

    if ($itemId <= 0 || !in_array($itemType, ['material', 'product'], true)) {
        echo json_encode(['success' => false, 'error' => 'Missing item identification']);
        exit;
    }

    $table = $itemType === 'material' ? 'materials' : 'products';
    $labelSql = $itemType === 'material'
        ? "TRIM(CONCAT(COALESCE(brand, ''), ' ', COALESCE(category, ''), ' ', COALESCE(name, ''))) AS label"
        : "TRIM(CONCAT(COALESCE(brand, ''), ' ', COALESCE(name, ''))) AS label";

    if ($action === 'save_ean') {
        if ($ean !== '' && strlen($ean) < 6) {
            echo json_encode(['success' => false, 'error' => 'EAN je příliš krátký.']);
            exit;
        }

        if ($ean !== '') {
            $dup = $pdo->prepare("SELECT id, {$labelSql} FROM {$table} WHERE ean = ? AND id <> ? LIMIT 1");
            $dup->execute([$ean, $itemId]);
            $dupRow = $dup->fetch(PDO::FETCH_ASSOC);
            if ($dupRow) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Tento EAN už je přiřazený k položce „' . trim((string)($dupRow['label'] ?? '')) . '“.'
                ]);
                exit;
            }
        }

        $update = $pdo->prepare("UPDATE {$table} SET ean = ? WHERE id = ?");
        $update->execute([$ean !== '' ? $ean : null, $itemId]);

        $itemStmt = $pdo->prepare("SELECT {$labelSql} FROM {$table} WHERE id = ? LIMIT 1");
        $itemStmt->execute([$itemId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'ean' => $ean,
            'label' => trim((string)($item['label'] ?? ''))
        ]);
        exit;
    }

    $itemStmt = $pdo->prepare("SELECT {$labelSql}, COALESCE(NULLIF(ean, ''), '') AS ean FROM {$table} WHERE id = ? LIMIT 1");
    $itemStmt->execute([$itemId]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Položka nebyla nalezena.']);
        exit;
    }

    $label = trim((string)($item['label'] ?? ''));
    $receiptEan = $scannedEan !== '' ? $scannedEan : ((string)($item['ean'] ?? '') !== '' ? (string)$item['ean'] : null);

    $pdo->beginTransaction();

    if ($itemType === 'material') {
        $reset = $pdo->prepare("UPDATE materials SET needs_buying = 0, stock_state = 'none' WHERE id = ?");
        $reset->execute([$itemId]);
    }

    $insert = $pdo->prepare("INSERT INTO stock_receipts (batch_code, item_type, item_id, quantity, scanned_ean, note, received_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $insert->execute([$batchCode !== '' ? $batchCode : null, $itemType, $itemId, $quantity, $receiptEan, $note !== '' ? $note : null]);
    $receiptId = (int)$pdo->lastInsertId();

    $countStmt = $pdo->query("SELECT COUNT(*) AS list_count, COALESCE(SUM(GREATEST(COALESCE(shopping_qty, 1), 1)), 0) AS total_qty FROM materials WHERE needs_buying = 1 OR COALESCE(NULLIF(stock_state, ''), 'none') = 'low'");
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: ['list_count' => 0, 'total_qty' => 0];

    $stateCountsStmt = $pdo->query("SELECT COALESCE(NULLIF(stock_state, ''), 'none') AS stock_state, COUNT(*) AS cnt FROM materials WHERE COALESCE(NULLIF(stock_state, ''), 'none') IN ('opened', 'low') GROUP BY stock_state");
    $openedCount = 0;
    $lowCount = 0;
    foreach ($stateCountsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (($row['stock_state'] ?? '') === 'opened') $openedCount = (int)$row['cnt'];
        if (($row['stock_state'] ?? '') === 'low') $lowCount = (int)$row['cnt'];
    }

    $receiptTimeStmt = $pdo->prepare("SELECT DATE_FORMAT(received_at, '%Y-%m-%d %H:%i:%s') AS received_at FROM stock_receipts WHERE id = ? LIMIT 1");
    $receiptTimeStmt->execute([$receiptId]);
    $receiptTime = (string)($receiptTimeStmt->fetchColumn() ?: date('Y-m-d H:i:s'));

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'item_type' => $itemType,
        'item_id' => $itemId,
        'label' => $label,
        'qty' => $quantity,
        'note' => $note,
        'stock_state' => $itemType === 'material' ? 'none' : null,
        'new_status' => 0,
        'list_count' => (int)($counts['list_count'] ?? 0),
        'total_qty' => (int)($counts['total_qty'] ?? 0),
        'opened_count' => $openedCount,
        'low_count' => $lowCount,
        'receipt' => [
            'id' => $receiptId,
            'batch_code' => $batchCode,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'item_label' => $label,
            'qty' => $quantity,
            'note' => $note,
            'scanned_ean' => $receiptEan ?? '',
            'received_at' => $receiptTime
        ]
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
