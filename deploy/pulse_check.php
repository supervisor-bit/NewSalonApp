<?php
require_once 'auth.php';
require_once 'db.php';
header('Content-Type: application/json');

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$old_snapshot = $_GET['snapshot'] ?? '';

if (!$client_id) {
    echo json_encode(['new_data' => false]);
    exit;
}

// 1. Zjistíme počet a MAX ID návštěv
$stmt_v = $pdo->prepare("SELECT COUNT(id) as cnt, MAX(id) as max_v FROM visits WHERE client_id = ?");
$stmt_v->execute([$client_id]);
$res_v = $stmt_v->fetch();

// 2. Zjistíme MAX ID receptur (to se mění při každé úpravě barvy na mobilu)
$stmt_f = $pdo->prepare("SELECT MAX(id) as max_f FROM formulas WHERE visit_id IN (SELECT id FROM visits WHERE client_id = ?)");
$stmt_f->execute([$client_id]);
$res_f = $stmt_f->fetch();

$current_snapshot = ($res_v['cnt'] ?? 0) . "_" . ($res_v['max_v'] ?? 0) . "_" . ($res_f['max_f'] ?? 0);

echo json_encode([
    'new_data' => ($old_snapshot !== $current_snapshot && $old_snapshot !== ''),
    'snapshot' => $current_snapshot
]);
exit;
