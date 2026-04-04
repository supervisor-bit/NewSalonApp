<?php require_once 'auth.php'; ?>
<?php
require_once 'db.php';
// Načteme klienty seřazené podle příjmení a jména
$stmt = $pdo->query("SELECT id, first_name, last_name, last_visit_date, phone FROM clients ORDER BY last_name ASC, first_name ASC");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KARTA - Mobilní Míchárna</title>
    <link rel="stylesheet" href="m-style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <header class="m-header">
        <h1>KARTA <span style="font-weight:300;">MOBILE</span></h1>
        <a href="index.php" style="font-size:12px; font-weight:600; background:#334155; padding:6px 12px; border-radius:20px;">Zpět na PC verzi</a>
    </header>

    <div class="m-search-wrap">
        <input type="text" id="m-search" class="m-search-input" placeholder="Hledat klientku (např. 'Nováková')...">
    </div>

    <ul class="m-client-list" id="client-list">
        <?php foreach ($clients as $c): ?>
            <a href="m-builder.php?client_id=<?= $c['id'] ?>" class="m-client-item" data-name="<?= mb_strtolower($c['last_name'].' '.$c['first_name']) ?>">
                <div>
                    <div class="m-client-name"><?= htmlspecialchars($c['last_name'] . ' ' . $c['first_name']) ?></div>
                    <div class="m-client-date">
                        <i data-lucide="calendar" style="width:12px;height:12px;vertical-align:middle;"></i> 
                        <?= $c['last_visit_date'] ? date('d.m.Y', strtotime($c['last_visit_date'])) : 'Nová klientka' ?>
                    </div>
                </div>
                <div class="m-client-arrow">
                    <i data-lucide="chevron-right"></i>
                </div>
            </a>
        <?php endforeach; ?>
        <div id="no-results" style="display:none; text-align:center; padding:30px; color:#94a3b8; font-weight:600;">Žádná klientka nenalezena.</div>
    </ul>

    <script>
        lucide.createIcons();
        
        // Jednoduché fulltext hledání
        document.getElementById('m-search').addEventListener('input', function(e) {
            let val = e.target.value.toLowerCase();
            let items = document.querySelectorAll('.m-client-item');
            let found = 0;
            items.forEach(item => {
                if(item.getAttribute('data-name').indexOf(val) > -1) {
                    item.style.display = 'flex';
                    found++;
                } else {
                    item.style.display = 'none';
                }
            });
            document.getElementById('no-results').style.display = found === 0 ? 'block' : 'none';
        });
        
        // Alert o uložení
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            alert('✅ Receptura i návštěva byla bezpečně uložena!');
        }
    </script>
</body>
</html>
