<?php require_once 'auth.php';
 
// delete_client.php

require 'db.php';

if (isset($_GET['client_id'])) {
    require_csrf_token();
    $client_id = (int)$_GET['client_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $_SESSION['msg'] = "Klient a veškerá jeho historie byla úspěšně smazána.";
    } catch(Exception $e) {
        $_SESSION['msg'] = "Chyba při mazání: " . $e->getMessage();
    }
}

header("Location: index.php");
exit;
