<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$material_id = $_POST['material_id'] ?? null;

if (!$material_id) {
    echo json_encode(['success' => false, 'error' => 'Missing material_id']);
    exit;
}

try {
    // Nejdřív zjistíme aktuální stav
    $stmt = $pdo->prepare("SELECT needs_buying FROM materials WHERE id = ?");
    $stmt->execute([$material_id]);
    $current = $stmt->fetch();
    
    if (!$current) {
        echo json_encode(['success' => false, 'error' => 'Material not found']);
        exit;
    }
    
    $new_status = $current['needs_buying'] ? 0 : 1;
    
    // Aktualizujeme
    $upd = $pdo->prepare("UPDATE materials SET needs_buying = ? WHERE id = ?");
    $upd->execute([$new_status, $material_id]);
    
    echo json_encode(['success' => true, 'new_status' => $new_status]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
