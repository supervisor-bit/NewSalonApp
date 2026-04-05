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
    http_response_code(404);
    exit("Nebyl nalezen žádný CSV soubor s produkty. Očekává se např. produkty.csv nebo libovolné produkty*.csv.\n");
}

$handle = fopen($file, 'r');
if ($handle === false) {
    exit("Nelze otevřít soubor: " . basename($file) . "\n");
}

fgetcsv($handle, 0, ';');
$imported = 0;
$skipped = 0;

$pdo->beginTransaction();

try {
    $pdo->query("DELETE FROM products");
    $pdo->query("ALTER TABLE products AUTO_INCREMENT = 1");

    while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
        if (count($data) < 5) continue;

        $brand = trim($data[0]);
        $rada = trim($data[1]);
        $product = trim($data[2]);
        $volume = trim($data[3]);
        $type = trim($data[4]);

        if ($type === 'Salon') {
            $isNapln = false;

            if (stripos($product, 'Šampon') !== false && in_array($volume, ['500 ml', '1000 ml'], true)) {
                $isNapln = true;
            }

            if ($rada === 'Vitamino Color' && $product === 'Péče' && $volume === '500 ml') {
                $isNapln = true;
            }

            if ($isNapln) {
                $type = 'Retail (Náplň)';
            }
        }

        if ($type === 'Salon') {
            $skipped++;
            continue;
        }

        $fullName = "$rada $product $volume";
        if ($type && $type !== 'Retail') {
            $fullName .= " ($type)";
        }

        $stmt = $pdo->prepare("INSERT INTO products (brand, name, price, is_active) VALUES (?, ?, 0, 1)");
        $stmt->execute([$brand, $fullName]);
        $imported++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fclose($handle);
    http_response_code(500);
    exit("Import selhal: " . $e->getMessage() . "\n");
}

fclose($handle);

echo "Zdroj: " . basename($file) . "\n";
echo "Uspesne naimportovano $imported Retail/Napln produktu!\n";
echo "Preskoceno $skipped ciste Salon produktů.\n";
?>
