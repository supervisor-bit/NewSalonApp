<?php require_once 'auth.php'; ?>
<?php
// delete_visit.php
require 'db.php';

$visit_id = (int)($_GET['id'] ?? 0);
$client_id = (int)($_GET['client_id'] ?? 0);

if (!$visit_id || !$client_id) {
    header("Location: m-index.php");
    exit;
}

require_csrf_token();

try {
    $pdo->beginTransaction();

    // 1. Smazat receptury (formulas)
    $stmt1 = $pdo->prepare("DELETE FROM formulas WHERE visit_id = ?");
    $stmt1->execute([$visit_id]);

    // 2. Smazat prodané produkty (visit_products)
    $stmt2 = $pdo->prepare("DELETE FROM visit_products WHERE visit_id = ?");
    $stmt2->execute([$visit_id]);

    // 3. Smazat samotnou návštěvu (visits)
    $stmt3 = $pdo->prepare("DELETE FROM visits WHERE id = ?");
    $stmt3->execute([$visit_id]);

    $pdo->commit();
    $_SESSION['msg'] = "Návštěva byla úspěšně smazána.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['msg'] = "Chyba při mazání: " . $e->getMessage();
}

$source = $_GET['source'] ?? 'mobile';
if ($source === 'pc') {
    header("Location: index.php?client_id=" . $client_id);
} else {
    header("Location: m-history.php?client_id=" . $client_id);
}
exit;
