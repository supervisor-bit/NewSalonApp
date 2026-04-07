<?php require_once 'auth.php'; ?>
<?php
require_once 'db.php';

function formatBytesHuman($bytes) {
    $bytes = (float)$bytes;
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = (int)floor(log($bytes, 1024));
    $power = max(0, min($power, count($units) - 1));
    $value = $bytes / pow(1024, $power);

    return number_format($value, $power === 0 ? 0 : 2, ',', ' ') . ' ' . $units[$power];
}

$dbName = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '-');
$tables = [];
$totalRows = 0;
$totalSize = 0;
$errorMessage = '';

try {
    $statusRows = $pdo->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC);

    foreach ($statusRows as $row) {
        $tableName = (string)($row['Name'] ?? '');
        if ($tableName === '') {
            continue;
        }

        $safeTableName = str_replace('`', '``', $tableName);
        $exactCount = null;

        try {
            $exactCount = (int)$pdo->query("SELECT COUNT(*) FROM `{$safeTableName}`")->fetchColumn();
        } catch (Throwable $e) {
            $exactCount = null;
        }

        $sizeBytes = (int)($row['Data_length'] ?? 0) + (int)($row['Index_length'] ?? 0);
        $totalRows += (int)($exactCount ?? 0);
        $totalSize += $sizeBytes;

        $tables[] = [
            'name' => $tableName,
            'rows' => $exactCount,
            'engine' => (string)($row['Engine'] ?? '-'),
            'collation' => (string)($row['Collation'] ?? '-'),
            'size' => $sizeBytes,
            'updated' => (string)($row['Update_time'] ?? $row['Create_time'] ?? '-'),
        ];
    }

    usort($tables, static function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status DB | Aura</title>
    <style>
        body {
            font-family: Inter, Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 24px;
        }
        .wrap {
            max-width: 1100px;
            margin: 0 auto;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            padding: 24px;
            margin-bottom: 18px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        .meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 18px;
        }
        .pill {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 12px 14px;
        }
        .pill strong {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
        }
        .muted {
            color: #64748b;
        }
        .ok {
            color: #059669;
            font-weight: 700;
        }
        .warn {
            color: #b45309;
            background: #fffbeb;
            border: 1px solid #fde68a;
            padding: 12px 14px;
            border-radius: 12px;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }
        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            background: #0f172a;
            color: #fff;
            font-weight: 600;
        }
        .btn.secondary {
            background: #475569;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>🗄️ Status databáze</h1>
        <p class="muted">Přehled tabulek a počtu záznamů. Stránka je chráněná přihlášením a hodí se hlavně pro servis / kontrolu nasazení.</p>

        <div class="meta">
            <div class="pill"><strong>Databáze</strong><?= htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="pill"><strong>Tabulek</strong><?= count($tables) ?></div>
            <div class="pill"><strong>Celkem záznamů</strong><?= number_format($totalRows, 0, ',', ' ') ?></div>
            <div class="pill"><strong>Velikost</strong><?= htmlspecialchars(formatBytesHuman($totalSize), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="pill"><strong>Kontrola</strong><span class="ok">OK</span></div>
            <div class="pill"><strong>Čas</strong><?= htmlspecialchars(date('d.m.Y H:i:s'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="actions">
            <a class="btn" href="index.php">Otevřít desktop</a>
            <a class="btn secondary" href="m-index.php">Otevřít mobile</a>
            <a class="btn secondary" href="migrate.php">Spustit migraci</a>
        </div>
    </div>

    <div class="card">
        <?php if ($errorMessage !== ''): ?>
            <div class="warn">Nepodařilo se načíst přehled tabulek: <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Tabulka</th>
                        <th>Počet záznamů</th>
                        <th>Engine</th>
                        <th>Collation</th>
                        <th>Velikost</th>
                        <th>Naposledy změněno</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $table): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($table['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= $table['rows'] === null ? '—' : number_format((int)$table['rows'], 0, ',', ' ') ?></td>
                            <td><?= htmlspecialchars($table['engine'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($table['collation'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(formatBytesHuman($table['size']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($table['updated'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
