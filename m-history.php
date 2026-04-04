<?php require_once 'auth.php'; ?>
<?php
require_once 'db.php';
$client_id = (int)($_GET['client_id'] ?? 0);
if (!$client_id) { header("Location: m-index.php"); exit; }

// 1. Základní info o klientce
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Kompletní historie receptur
$past_stmt = $pdo->prepare("
    SELECT v.id, v.visit_date, v.note, f.bowl_name, f.material_id, f.amount_g, m.category as m_cat, m.name as m_name 
    FROM visits v 
    LEFT JOIN formulas f ON v.id = f.visit_id 
    LEFT JOIN materials m ON f.material_id = m.id 
    WHERE v.client_id = ? 
    ORDER BY v.visit_date DESC, v.id DESC
");
$past_stmt->execute([$client_id]);
$raw_past = $past_stmt->fetchAll(PDO::FETCH_ASSOC);

$past_visits = [];
foreach($raw_past as $rp) {
    $vid = $rp['id'];
    if(!isset($past_visits[$vid])) {
        $past_visits[$vid] = [
            'id' => $vid,
            'date' => date('d.m.Y', strtotime($rp['visit_date'])),
            'note' => $rp['note'],
            'bowls' => []
        ];
    }
    if($rp['bowl_name']) {
        $bn = $rp['bowl_name'];
        if(!isset($past_visits[$vid]['bowls'][$bn])) $past_visits[$vid]['bowls'][$bn] = [];
        $past_visits[$vid]['bowls'][$bn][] = [
            'name' => $rp['m_cat'].' '.$rp['m_name'], 
            'amt' => $rp['amount_g']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Historie - <?= htmlspecialchars($client['first_name'].' '.$client['last_name']) ?></title>
    <link rel="stylesheet" href="m-style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .m-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 16px; background: #fff; border-bottom: 1px solid var(--border); }
        .m-info-item { display: flex; flex-direction: column; gap: 4px; }
        .m-info-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700; }
        .m-info-val { font-size: 14px; font-weight: 600; color: var(--primary); }
        
        /* Accordion Styles */
        .m-acc-item { border-bottom: 1px solid var(--border); background: #fff; }
        .m-acc-header { 
            padding: 16px; display: flex; justify-content: space-between; align-items: center; 
            cursor: pointer; transition: background 0.2s;
        }
        .m-acc-header:active { background: #f8fafc; }
        .m-acc-date { font-weight: 700; font-family: 'Outfit'; font-size: 16px; color: var(--primary); }
        .m-acc-content { display: none; padding: 0 16px 16px; }
        .m-acc-content.active { display: block; }
        
        .m-acc-note { 
            background: #f1f5f9; padding: 10px; border-radius: 8px; font-size: 13px; 
            color: #475569; font-style: italic; margin-bottom: 12px; border-left: 3px solid var(--gold);
        }
        .m-acc-bowl { margin-bottom: 10px; }
        .m-acc-bowl-title { font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
        .m-acc-mat { font-size: 14px; color: var(--primary); margin-bottom: 2px; }
        
        /* Fixed Action Button */
        .m-fab-container {
            position: fixed; bottom: 20px; left: 20px; right: 20px; z-index: 100;
        }
        .btn-new-recipe {
            width: 100%; background: var(--primary); color: #fff; border: none; 
            border-radius: 16px; padding: 18px; font-size: 16px; font-weight: 800;
            font-family: 'Outfit'; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.3);
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
    </style>
</head>
<body style="padding-bottom: 100px;">
    <header class="m-header">
        <a href="m-index.php"><i data-lucide="arrow-left"></i></a>
        <div style="font-size:16px; font-weight:800; font-family:'Outfit';">HISTORIE KLIENTKY</div>
        <div style="width:24px;"></div>
    </header>

    <div style="background:var(--primary); color:#fff; padding:20px 16px; text-align:center;">
        <h2 style="font-size:24px; margin-bottom:4px;"><?= htmlspecialchars($client['last_name'].' '.$client['first_name']) ?></h2>
        <div style="font-size:13px; opacity:0.7;"><?= htmlspecialchars($client['phone'] ?: 'Bez telefonu') ?></div>
    </div>

    <!-- Rychlá diagnostika -->
    <div class="m-info-grid">
        <div class="m-info-item">
            <span class="m-info-label">Struktura</span>
            <span class="m-info-val"><?= htmlspecialchars($client['hair_texture'] ?: '—') ?></span>
        </div>
        <div class="m-info-item">
            <span class="m-info-label">Stav</span>
            <span class="m-info-val"><?= htmlspecialchars($client['hair_condition'] ?: '—') ?></span>
        </div>
        <div class="m-info-item">
            <span class="m-info-label">Výchozí tón</span>
            <span class="m-info-val"><?= htmlspecialchars($client['base_tone'] ?: '—') ?></span>
        </div>
        <div class="m-info-item">
            <span class="m-info-label">Šediny</span>
            <span class="m-info-val"><?= htmlspecialchars($client['gray_percentage'] ?: '—') ?></span>
        </div>
    </div>

    <div class="m-section-title">Minulé návštěvy (receptury)</div>
    
    <div class="m-history-list">
        <?php if (empty($past_visits)): ?>
            <div style="text-align:center; padding:40px; color:#94a3b8;">Zatím žádná historie.</div>
        <?php else: ?>
            <?php foreach ($past_visits as $v): ?>
                <div class="m-acc-item">
                    <div class="m-acc-header" onclick="toggleAcc(this)">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="m-acc-date"><?= $v['date'] ?></div>
                            <span style="font-size:11px; background:#f1f5f9; color:#64748b; padding:2px 8px; border-radius:10px; font-weight:700;">
                                <?= count($v['bowls']) ?> <?= count($v['bowls']) == 1 ? 'miska' : (count($v['bowls']) < 5 ? 'misky' : 'misek') ?>
                            </span>
                        </div>
                        <i data-lucide="chevron-down" class="acc-icon"></i>
                    </div>
                    <div class="m-acc-content">
                        <?php if ($v['note']): ?>
                            <div class="m-acc-note"><?= nl2br(htmlspecialchars($v['note'])) ?></div>
                        <?php endif; ?>
                        
                        <?php foreach ($v['bowls'] as $bName => $mats): ?>
                            <div class="m-acc-bowl">
                                <div class="m-acc-bowl-title"><?= htmlspecialchars($bName) ?></div>
                                <?php foreach ($mats as $m): ?>
                                    <div class="m-acc-mat">• <?= htmlspecialchars($m['name']) ?> <strong><?= $m['amt'] ?>g</strong></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <a href="m-builder.php?client_id=<?= $client_id ?>&copy_visit_id=<?= $v['id'] ?>" class="m-btn-copy" style="margin-top:10px;">
                            <i data-lucide="copy" style="width:14px;height:14px;"></i> Zopakovat do nové míchárny
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-fab-container">
        <a href="m-builder.php?client_id=<?= $client_id ?>" class="btn-new-recipe">
            <i data-lucide="flask-conical"></i> NOVÁ RECEPTURA
        </a>
    </div>

    <script>
        lucide.createIcons();
        function toggleAcc(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.acc-icon');
            const isActive = content.classList.contains('active');
            
            // Zavřít ostatní (přehlednější)
            document.querySelectorAll('.m-acc-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.acc-icon').forEach(i => i.style.transform = 'rotate(0deg)');
            
            if (!isActive) {
                content.classList.add('active');
                icon.style.transform = 'rotate(180deg)';
                
                // Počkáme chvilku na vykreslení a pak odskrolujeme
                setTimeout(() => {
                    const headerOffset = 70; // výška horní lišty + malá rezerva
                    const elementPosition = header.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }, 50);
            }
        }
    </script>
</body>
</html>
