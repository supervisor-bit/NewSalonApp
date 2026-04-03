<?php require_once 'auth.php';
 
// update_diagnostics.php

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)$_POST['client_id'];
    $hair_texture = $_POST['hair_texture'] ?? 'Neřešeno';
    $hair_condition = $_POST['hair_condition'] ?? 'Neřešeno';
    $base_tone = trim($_POST['base_tone'] ?? '');
    $gray_percentage = trim($_POST['gray_percentage'] ?? '');

    if ($client_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE clients SET hair_texture = ?, hair_condition = ?, base_tone = ?, gray_percentage = ? WHERE id = ?");
            $stmt->execute([$hair_texture, $hair_condition, $base_tone, $gray_percentage, $client_id]);
            $_SESSION['msg'] = "Kompletní vlasová diagnostika byla úspěšně uložena/aktualizována.";
        } catch(Exception $e) {
            $_SESSION['msg'] = "Chyba při aktualizaci diagnostiky: " . $e->getMessage();
        }
    }
    
    header("Location: index.php?client_id=" . $client_id);
    exit;
}
header("Location: index.php");
exit;
