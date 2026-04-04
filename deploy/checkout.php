<?php require_once 'auth.php';
 
// checkout.php

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visit_id  = (int)$_POST['visit_id'];
    $client_id = (int)$_POST['client_id'];
    $price     = (int)$_POST['price'];
    $note      = trim($_POST['note']);
    $next_visit = !empty($_POST['next_visit_date']) ? $_POST['next_visit_date'] : null;

    if ($visit_id > 0 && $client_id > 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE visits SET price = ?, note = ? WHERE id = ?");
            $stmt->execute([$price, $note, $visit_id]);

            // Uloz datum pristi navstevy do profilu klientky
            $stmt2 = $pdo->prepare("UPDATE clients SET next_visit_date = ? WHERE id = ?");
            $stmt2->execute([$next_visit, $client_id]);

            $pdo->commit();
            $_SESSION['msg'] = "Návštěva byla úspěšně vyúčtována.";
        } catch(Exception $e) {
            $pdo->rollBack();
            $_SESSION['msg'] = "Chyba při vyúčtování: " . $e->getMessage();
        }
    }
    
    header("Location: index.php?client_id=" . $client_id);
    exit;
}
header("Location: index.php");
exit;
