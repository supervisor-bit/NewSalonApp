<?php require_once 'auth.php';
 
// export.php
include 'db.php';

$range = isset($_GET['range']) ? $_GET['range'] : 'all';
$type  = isset($_GET['type'])  ? $_GET['type']  : 'csv';
$year  = isset($_GET['year'])  ? (int)$_GET['year'] : (int)date('Y');

$cz_months_full = [
    1=>"Leden", 2=>"Únor", 3=>"Březen", 4=>"Duben",
    5=>"Květen", 6=>"Červen", 7=>"Červenec", 8=>"Srpen",
    9=>"Září", 10=>"Říjen", 11=>"Listopad", 12=>"Prosinec"
];

// ============================================================
// NOVÝ REŽIM: Roční přehled PO MĚSÍCÍCH
// ============================================================
if ($range === 'year_monthly') {
    $months_data = [];
    for ($m = 1; $m <= 12; $m++) {
        $ym = sprintf('%04d-%02d', $year, $m);

        $s_stmt = $pdo->prepare("SELECT SUM(price) as sluzby, COUNT(*) as navstevy FROM visits WHERE visit_date LIKE ? AND price IS NOT NULL");
        $s_stmt->execute([$ym . '%']);
        $row = $s_stmt->fetch();

        $p_stmt = $pdo->prepare("SELECT SUM(vp.price_sold * vp.amount) FROM visit_products vp JOIN visits v ON vp.visit_id = v.id WHERE v.visit_date LIKE ?");
        $p_stmt->execute([$ym . '%']);
        $produkty = (int)$p_stmt->fetchColumn();

        $sluzby = (int)($row['sluzby'] ?? 0);
        $months_data[$m] = [
            'mesic'    => $cz_months_full[$m] . ' ' . $year,
            'sluzby'   => $sluzby,
            'produkty' => $produkty,
            'celkem'   => $sluzby + $produkty,
            'navstevy' => (int)($row['navstevy'] ?? 0),
        ];
    }

    $sum_s = array_sum(array_column($months_data, 'sluzby'));
    $sum_p = array_sum(array_column($months_data, 'produkty'));
    $sum_c = $sum_s + $sum_p;
    $sum_v = array_sum(array_column($months_data, 'navstevy'));
    $title = "Roční přehled tržeb " . $year;

    // --- CSV ---
    if ($type === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=rocni-prehled-' . $year . '.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM pro Excel
        fputcsv($output, ['Mesic', 'Sluzby (Kc)', 'Produkty (Kc)', 'Celkem (Kc)', 'Pocet navstev'], ';');
        foreach ($months_data as $md) {
            fputcsv($output, [
                $md['mesic'],
                $md['sluzby'],
                $md['produkty'],
                $md['celkem'],
                $md['navstevy']
            ], ';');
        }
        fputcsv($output, ['CELKEM ' . $year, $sum_s, $sum_p, $sum_c, $sum_v], ';');
        fclose($output);
        exit;
    }

    // --- PDF / Tisk ---
    ?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;800&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; color: #1e293b; background: #f8fafc; }
        .page { max-width: 820px; margin: 0 auto; padding: 40px; }
        .header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 36px 40px; border-radius: 20px; margin-bottom: 28px; display: flex; justify-content: space-between; align-items: flex-end; }
        .header h1 { font-family: 'Outfit'; font-size: 28px; font-weight: 800; margin-bottom: 4px; }
        .header .sub { font-size: 13px; color: rgba(255,255,255,0.45); margin-bottom: 8px; }
        .total-badge .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.35); margin-bottom: 4px; text-align: right; }
        .total-badge .amount { font-family: 'Outfit'; font-size: 34px; font-weight: 800; color: #c5a059; }
        .summary-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 28px; }
        .summary-card { background: #fff; border-radius: 14px; padding: 20px 24px; border: 1px solid #e2e8f0; text-align: center; }
        .summary-card .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 8px; }
        .summary-card .value { font-family: 'Outfit'; font-size: 24px; font-weight: 800; }
        .summary-card.sluzby .value { color: #92733c; }
        .summary-card.produkty .value { color: #10b981; }
        .summary-card.pocet .value { color: #334155; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        thead tr { background: #f8fafc; }
        th { padding: 14px 20px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; }
        th.right, td.right { text-align: right; }
        td { padding: 13px 20px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        tr.empty td { color: #cbd5e1; font-style: italic; font-size: 13px; }
        tr:hover td { background: #f8fafc; }
        tfoot tr { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; }
        tfoot td { padding: 16px 20px; font-weight: 700; font-family: 'Outfit'; font-size: 15px; border: none; }
        tfoot td.right { color: #c5a059; }
        .footer { margin-top: 20px; text-align: center; font-size: 11px; color: #94a3b8; }
        .btn-print { background: #0f172a; color: white; padding: 11px 24px; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; font-family: inherit; }
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .page { padding: 20px; }
            .header { border-radius: 8px; }
            table { border: 1px solid #ddd; }
        }
    </style>
</head>
<body onload="window.print()">
<div class="no-print" style="text-align:right; padding:20px 40px 0;">
    <button class="btn-print" onclick="window.print()">🖨 Vytisknout / Uložit jako PDF</button>
</div>
<div class="page">
    <div class="header">
        <div>
            <div class="sub">Kadeřnická Karta &bull; exportováno <?= date('d.m.Y H:i') ?></div>
            <h1><?= htmlspecialchars($title) ?></h1>
        </div>
        <div class="total-badge">
            <div class="label">Celkový obrat</div>
            <div class="amount"><?= number_format($sum_c, 0, ',', ' ') ?> Kč</div>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card sluzby">
            <div class="label">Služby celkem</div>
            <div class="value"><?= number_format($sum_s, 0, ',', ' ') ?> Kč</div>
        </div>
        <div class="summary-card produkty">
            <div class="label">Produkty celkem</div>
            <div class="value"><?= number_format($sum_p, 0, ',', ' ') ?> Kč</div>
        </div>
        <div class="summary-card pocet">
            <div class="label">Počet návštěv</div>
            <div class="value"><?= $sum_v ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Měsíc</th>
                <th class="right">Služby</th>
                <th class="right">Produkty</th>
                <th class="right">Celkem</th>
                <th class="right">Návštěvy</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($months_data as $md): ?>
                <?php if ($md['navstevy'] === 0): ?>
                    <tr class="empty">
                        <td><?= $md['mesic'] ?></td>
                        <td class="right" colspan="4">— bez záznamu</td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td><strong><?= $md['mesic'] ?></strong></td>
                        <td class="right"><?= number_format($md['sluzby'], 0, ',', ' ') ?> Kč</td>
                        <td class="right" style="color:#10b981;"><?= number_format($md['produkty'], 0, ',', ' ') ?> Kč</td>
                        <td class="right"><strong><?= number_format($md['celkem'], 0, ',', ' ') ?> Kč</strong></td>
                        <td class="right" style="color:#64748b;"><?= $md['navstevy'] ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td>CELKEM <?= $year ?></td>
                <td class="right"><?= number_format($sum_s, 0, ',', ' ') ?> Kč</td>
                <td class="right"><?= number_format($sum_p, 0, ',', ' ') ?> Kč</td>
                <td class="right"><?= number_format($sum_c, 0, ',', ' ') ?> Kč</td>
                <td class="right"><?= $sum_v ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">Profi Kadeřnická Karta &bull; <?= $year ?></div>
</div>
</body>
</html>
    <?php
    exit;
}

// ============================================================
// STANDARDNÍ EXPORT (původní logika — jednotlivé návštěvy)
// ============================================================
$date_clause = " WHERE 1=1 ";
$title = "Kompletní přehled tržeb";

if ($range === 'today') {
    $date_clause = " WHERE v.visit_date = CURDATE() ";
    $title = "Přehled tržeb - Dnes (" . date('d.m.Y') . ")";
} elseif ($range === 'this_month') {
    $date_clause = " WHERE MONTH(v.visit_date) = MONTH(CURDATE()) AND YEAR(v.visit_date) = YEAR(CURDATE()) ";
    $title = "Přehled tržeb - " . date('m / Y');
} elseif ($range === 'this_year') {
    $date_clause = " WHERE YEAR(v.visit_date) = YEAR(CURDATE()) ";
    $title = "Přehled tržeb - Rok " . date('Y');
}

$query = "
    SELECT v.visit_date, c.first_name, c.last_name, v.price as work_price, v.id as visit_id
    FROM visits v
    JOIN clients c ON v.client_id = c.id
    $date_clause
    ORDER BY v.visit_date DESC, v.id DESC
";

$stmt = $pdo->query($query);
$rows = $stmt->fetchAll();

// Fetch product totals for these visits
foreach($rows as $k => $r) {
    $p_stmt = $pdo->prepare("SELECT SUM(price_sold * amount) FROM visit_products WHERE visit_id = ?");
    $p_stmt->execute([$r['visit_id']]);
    $rows[$k]['prod_price'] = (int)$p_stmt->fetchColumn();
}

if ($type === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=export-trzeb-' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Datum', 'Klient', 'Služby (Kč)', 'Produkty (Kč)', 'Celkem (Kč)'], ';');
    
    foreach ($rows as $row) {
        fputcsv($output, [
            date('d.m.Y', strtotime($row['visit_date'])),
            $row['first_name'] . ' ' . $row['last_name'],
            $row['work_price'],
            $row['prod_price'],
            $row['work_price'] + $row['prod_price']
        ], ';');
    }
    fclose($output);
    exit;
} else {
    // PDF / Print Template
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <title><?= $title ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; color: #1e293b; padding: 40px; }
            h1 { font-family: 'Outfit', sans-serif; color: #0f172a; margin-bottom: 5px; }
            .subtitle { color: #64748b; margin-bottom: 30px; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { text-align: left; background: #f8fafc; padding: 12px; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; }
            td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
            .total-row { background: #f8fafc; font-weight: bold; }
            .price { text-align: right; font-family: 'Outfit', sans-serif; }
            @media print {
                .no-print { display: none; }
                body { padding: 0; }
            }
            .btn-print {
                background: #0f172a; color: white; padding: 10px 20px; border: none; border-radius: 8px;
                cursor: pointer; font-family: inherit; font-weight: 600; margin-bottom: 20px;
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print" style="text-align:right;">
            <button class="btn-print" onclick="window.print()">Vytisknout / Uložit jako PDF</button>
        </div>
        
        <h1><?= $title ?></h1>
        <div class="subtitle">Exportováno z Kadeřnické Karty dne <?= date('d.m.Y H:i') ?></div>

        <table>
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Klient</th>
                    <th class="price">Služby</th>
                    <th class="price">Produkty</th>
                    <th class="price">Celkem</th>
                </tr>
            </thead>
            <tbody>
                <?php  
                $sum_w = 0; $sum_p = 0;
                foreach ($rows as $row): 
                    $sum_w += $row['work_price'];
                    $sum_p += $row['prod_price'];
                ?>
                <tr>
                    <td><?= date('d.m.Y', strtotime($row['visit_date'])) ?></td>
                    <td><strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong></td>
                    <td class="price"><?= number_format($row['work_price'], 0, ',', ' ') ?> Kč</td>
                    <td class="price"><?= number_format($row['prod_price'], 0, ',', ' ') ?> Kč</td>
                    <td class="price"><strong><?= number_format($row['work_price'] + $row['prod_price'], 0, ',', ' ') ?> Kč</strong></td>
                </tr>
                <?php  endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2">CELKEM</td>
                    <td class="price"><?= number_format($sum_w, 0, ',', ' ') ?> Kč</td>
                    <td class="price"><?= number_format($sum_p, 0, ',', ' ') ?> Kč</td>
                    <td class="price"><?= number_format($sum_w + $sum_p, 0, ',', ' ') ?> Kč</td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>
    <?php 
}
