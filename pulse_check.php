<?php
require_once 'db.php';
header('Content-Type: application/json');

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if (!$client_id) {
    echo json_encode(['new_data' => false]);
    exit;
}

// Zkontrolujeme, zda v databázi existuje ID návštěvy vyšší, než které zná notebook
$stmt = $pdo->prepare("SELECT MAX(id) as max_id FROM visits WHERE client_id = ?");
$stmt->execute([$client_id]);
$res = $stmt->fetch();
$new_max = (int)($res['max_id'] ?? 0);

echo json_encode([
    'new_data' => ($new_max > $last_id),
    'max_id' => $new_max
]);
exit;
