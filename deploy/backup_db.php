<?php require_once 'auth.php';

require_once 'db.php';

$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$sql = "-- AURA DATABAZE BACKUP\n";
$sql .= "-- Datum: " . date('Y-m-d H:i:s') . "\n\n";

$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Definice tabulky
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $sql .= $row[1] . ";\n\n";
    
    // Obsah tabulky
    $stmt = $pdo->query("SELECT * FROM `$table`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 0) {
        $sql .= "-- Data pro tabulku `$table`\n";
        foreach ($rows as $row) {
            $cols = array_map(function($c) use ($pdo) { 
                return ($c === null) ? 'NULL' : $pdo->quote($c); 
            }, array_values($row));
            
            $sql .= "INSERT INTO `$table` VALUES (" . implode(", ", $cols) . ");\n";
        }
        $sql .= "\n\n";
    }
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Vynutit stažení jako soubor prohlížečem
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="aura_zaloha_' . date('Y-m-d_H-i') . '.sql"');
header('Content-Length: ' . strlen($sql));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $sql;
exit;
?>
