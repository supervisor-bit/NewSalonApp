<?php require_once 'auth.php';
 
// update_client.php

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['client_id'];
    $allergy_note = $_POST['allergy_note'] ?? null;
    
    try {
        $stmt = $pdo->prepare("UPDATE clients SET allergy_note = ? WHERE id = ?");
        $stmt->execute([$allergy_note, $id]);
        $_SESSION['msg'] = "Varování (Alergie) bylo úspěšně uloženo.";
    } catch(Exception $e) {
        $_SESSION['msg'] = "Chyba při úpravě: " . $e->getMessage();
    }

    header("Location: index.php?client_id=" . $id);
    exit;
}
header("Location: index.php");
exit;
