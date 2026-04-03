<?php require_once 'auth.php';
 
// delete_visit.php

require 'db.php';

if (isset($_GET['id']) && isset($_GET['client_id'])) {
    $visit_id = (int)$_GET['id'];
    $client_id = (int)$_GET['client_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM visits WHERE id = ?");
        $stmt->execute([$visit_id]);
        $_SESSION['msg'] = "Návštěva byla úspěšně smazána.";
    } catch(Exception $e) {
        $_SESSION['msg'] = "Chyba při mazání: " . $e->getMessage();
    }

    header("Location: index.php?client_id=" . $client_id);
    exit;
}
header("Location: index.php");
exit;
