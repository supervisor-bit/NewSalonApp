<?php require_once 'auth.php';

require_once 'db.php';

$file = 'produkty_loreal.csv';
if (!file_exists($file)) {
    die("CSV soubor nebyl nalezen.");
}

$handle = fopen($file, "r");
if ($handle !== FALSE) {
    // Přeskočit hlavičku
    fgetcsv($handle, 1000, ";");
    
    $imported = 0;
    while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        if (count($data) < 6) continue;
        
        $brand = trim($data[0]);
        $line = trim($data[1]);
        $product = trim($data[2]);
        $volume = trim($data[3]);
        $type = trim($data[4]);
        $price = (int)trim($data[5]);
        
        $fullName = "$line $product $volume";
        if ($type) {
            $fullName .= " ($type)";
        }
        
        $stmt = $pdo->prepare("INSERT INTO products (brand, name, price, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$brand, $fullName, $price]);
        $imported++;
    }
    fclose($handle);
    echo "Uspesne naimportovano $imported produktu!";
} else {
    echo "Nelze otevrit soubor.";
}
?>
