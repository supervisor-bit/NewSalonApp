<?php require_once 'auth.php';
 
// save_visit.php

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)$_POST['client_id'];
    $visit_date = $_POST['visit_date'];
    $note = '';
    $price = 0;
    
    // Služby (přidáno)
    $s_metal_detox = isset($_POST['s_metal_detox']) ? 1 : 0;
    $s_trim = isset($_POST['s_trim']) ? 1 : 0;
    $s_blow = isset($_POST['s_blow']) ? 1 : 0;
    $s_curl = isset($_POST['s_curl']) ? 1 : 0;
    $s_iron = isset($_POST['s_iron']) ? 1 : 0;

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO visits (client_id, visit_date, note, price, s_metal_detox, s_trim, s_blow, s_curl, s_iron) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $visit_date, $note, $price, $s_metal_detox, $s_trim, $s_blow, $s_curl, $s_iron]);
        $visit_id = $pdo->lastInsertId();
        
        $bowl_names = $_POST['bowl_names'] ?? [];
        $f_stmt = $pdo->prepare("INSERT INTO formulas (visit_id, material_id, amount_g, bowl_name) VALUES (?, ?, ?, ?)");
        
        foreach ($bowl_names as $bIndex => $bName) {
            if (empty($bName)) continue;
            
            $materials = $_POST['material_id'][$bIndex] ?? [];
            $amounts = $_POST['amount_g'][$bIndex] ?? [];
            
            for ($i = 0; $i < count($materials); $i++) {
                if (!empty($materials[$i]) && (!empty($amounts[$i]) || $amounts[$i] === '0')) {
                    $f_stmt->execute([$visit_id, $materials[$i], $amounts[$i], $bName]);
                }
            }
        }

        // Homecare products
        $product_ids = $_POST['product_ids'] ?? [];
        $product_prices = $_POST['product_prices'] ?? [];
        $product_amounts = $_POST['product_amounts'] ?? [];
        $p_stmt = $pdo->prepare("INSERT INTO visit_products (visit_id, product_id, price_sold, amount) VALUES (?, ?, ?, ?)");
        
        for ($i = 0; $i < count($product_ids); $i++) {
            if (!empty($product_ids[$i])) {
                $p_stmt->execute([
                    $visit_id, 
                    $product_ids[$i], 
                    (int)$product_prices[$i],
                    (int)($product_amounts[$i] ?? 1)
                ]);
            }
        }
        
        $pdo->commit();
        $_SESSION['msg'] = "Návštěva i s produkty byla bezpečně uložena.";
    } catch(Exception $e) {
        $pdo->rollBack();
        $_SESSION['msg'] = "Chyba při ukládání: " . $e->getMessage();
    }

    header("Location: index.php?client_id=" . $client_id);
    exit;
}
header("Location: index.php");
exit;
