<?php require_once 'auth.php';

require_once 'db.php';

function resolveImportCsv(array $preferredNames, array $patterns): ?string {
    foreach ($preferredNames as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '') {
            continue;
        }

        $pathsToTry = [$candidate, __DIR__ . DIRECTORY_SEPARATOR . $candidate];
        foreach ($pathsToTry as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }
    }

    foreach ($patterns as $pattern) {
        $matches = glob(__DIR__ . DIRECTORY_SEPARATOR . $pattern) ?: [];
        sort($matches, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($matches as $match) {
            if (is_file($match) && is_readable($match)) {
                return $match;
            }
        }
    }

    return null;
}

$file = resolveImportCsv(
    [$_GET['file'] ?? '', getenv('PRODUCTS_CSV') ?: '', 'produkty.csv', 'products.csv'],
    ['produkty*.csv', 'products*.csv']
);

if (!$file) {
    die("CSV soubor s produkty nebyl nalezen.");
}

$handle = fopen($file, 'r');
if ($handle !== FALSE) {
    fgetcsv($handle, 1000, ';');

    $imported = 0;
    while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
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
    echo "Uspesne naimportovano $imported produktu ze souboru " . basename($file) . "!";
} else {
    echo "Nelze otevrit soubor.";
}
?>
