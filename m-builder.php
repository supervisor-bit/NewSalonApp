<?php require_once 'auth.php'; ?>
<?php
require_once 'db.php';
$client_id = (int)($_GET['client_id'] ?? 0);
if (!$client_id) { header("Location: m-index.php"); exit; }

$stmt = $pdo->prepare("SELECT first_name, last_name, hair_texture, hair_condition, base_tone, gray_percentage, allergy_note FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Načtení materiálů
$materials = $pdo->query("SELECT id, brand, category, name, needs_buying FROM materials WHERE is_active = 1 ORDER BY brand, category, name")->fetchAll(PDO::FETCH_ASSOC);
$materialsData = $materials;

// Načtení dat k předvyplnění / editaci
$prefill_bowls = [];
$prefill_services = ['s_trim'=>0, 's_blow'=>0, 's_metal_detox'=>0, 's_curl'=>0, 's_iron'=>0];
$cv_id = (int)($_GET['cv_id'] ?? 0);
$edit_id = (int)($_GET['edit_id'] ?? 0);
$source_id = $edit_id ?: $cv_id;

if ($source_id > 0) {
    // 1. Receptury
    $cv_stmt = $pdo->prepare("
        SELECT f.bowl_name, f.material_id, f.amount_g, m.category as m_cat, m.name as m_name 
        FROM formulas f
        LEFT JOIN materials m ON f.material_id = m.id
        WHERE f.visit_id = ?
    ");
    $cv_stmt->execute([$source_id]);
    foreach ($cv_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bn = $row['bowl_name'] ?: 'Miska';
        if (!isset($prefill_bowls[$bn])) $prefill_bowls[$bn] = [];
        $prefill_bowls[$bn][] = [
            'id' => (int)$row['material_id'],
            'name' => ($row['m_cat'] ? $row['m_cat'].' ' : '') . ($row['m_name'] ?: 'Neznámý materiál'),
            'amt' => $row['amount_g']
        ];
    }

    // 2. Služby
    $s_stmt = $pdo->prepare("SELECT s_trim, s_blow, s_metal_detox, s_curl, s_iron FROM visits WHERE id = ?");
    $s_stmt->execute([$source_id]);
    $srv = $s_stmt->fetch(PDO::FETCH_ASSOC);
    if ($srv) $prefill_services = $srv;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Skládač Receptur</title>
    <link rel="stylesheet" href="m-style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="icon.png">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <header class="m-header">
        <a href="m-history.php?client_id=<?= $client_id ?>"><i data-lucide="arrow-left"></i></a>
        <div style="font-size:16px; font-weight:800; font-family:'Outfit';">MÍCHÁRNA</div>
        <div style="width:24px;"></div>
    </header>

    <div class="m-sticky-top">
        <div class="m-client-banner">
            <div><?= htmlspecialchars($client['first_name'].' '.$client['last_name']) ?></div>
            <div style="font-size:11px; font-weight:400; opacity:0.8;"><?= htmlspecialchars($client['base_tone'] ?: 'Bez tónu') ?></div>
        </div>
        <?php if (!empty($client['allergy_note'])): ?>
        <div class="m-allergy-banner">
            <i data-lucide="alert-triangle" style="color:#ef4444; flex-shrink:0;"></i>
            <div>
                <span class="m-allergy-title">POZOR: ALERGIE</span>
                <div class="m-allergy-text"><?= nl2br(htmlspecialchars($client['allergy_note'])) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <form id="m-form" action="save_visit.php" method="POST">
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
        <input type="hidden" name="mobile" value="1">
        <input type="hidden" name="visit_date" value="<?= date('Y-m-d') ?>">
        
        <div class="m-section-title">RECEPTURA (Misky s barvou)</div>
        <div id="m-bowls"></div>
        <button type="button" class="m-add-bowl-btn" onclick="addBowl()">+ PŘIDAT NOVOU MISKU</button>
        
        <!-- Služby, co kadeřník udělal -->
        <div style="background:#fff; border-top:1px solid var(--border); border-bottom:1px solid var(--border); padding:0 16px;">
            <label style="display:flex; justify-content:space-between; align-items:center; padding:16px 0; border-bottom:1px solid #f1f5f9;">
                <span style="font-weight:600;">Střih (Trim)</span>
                <input type="checkbox" name="s_trim" style="transform: scale(1.5);" <?= $prefill_services['s_trim'] ? 'checked' : '' ?>>
            </label>
            <label style="display:flex; justify-content:space-between; align-items:center; padding:16px 0; border-bottom:1px solid #f1f5f9;">
                <span style="font-weight:600;">Foukaná (Blow-dry)</span>
                <input type="checkbox" name="s_blow" style="transform: scale(1.5);" <?= $prefill_services['s_blow'] ? 'checked' : '' ?>>
            </label>
            <label style="display:flex; justify-content:space-between; align-items:center; padding:16px 0; border-bottom:1px solid #f1f5f9;">
                <span style="font-weight:600;">Kúry / Metal Detox</span>
                <input type="checkbox" name="s_metal_detox" style="transform: scale(1.5);" <?= $prefill_services['s_metal_detox'] ? 'checked' : '' ?>>
            </label>
            <label style="display:flex; justify-content:space-between; align-items:center; padding:16px 0; border-bottom:1px solid #f1f5f9;">
                <span style="font-weight:600;">Žehlení / Vlny</span>
                <input type="checkbox" name="s_iron" style="transform: scale(1.5);" <?= $prefill_services['s_iron'] ? 'checked' : '' ?>>
            </label>
            <label style="display:flex; justify-content:space-between; align-items:center; padding:16px 0;">
                <span style="font-weight:600;">Kulmování / Lokny</span>
                <input type="checkbox" name="s_curl" style="transform: scale(1.5);" <?= $prefill_services['s_curl'] ? 'checked' : '' ?>>
            </label>
        </div>

        <div class="m-bottom-bar">
            <button type="submit" class="btn-save-mobile">ULOŽIT DO KARTY ZÁKAZNICE</button>
        </div>
    </form>

    <script>
        lucide.createIcons();
        const materialsData = <?= json_encode($materialsData) ?>;
        
        document.getElementById('m-form').onsubmit = async function(e) {
            e.preventDefault();
            
            // Pokud je otevřený našeptávač, napřed ho zavřeme
            let activeDrop = document.querySelector('.ac-list[style*="display: block"]');
            if(activeDrop) {
                activeDrop.style.display = 'none';
                return false;
            }

            // Vizuální zpětná vazba na tlačítku
            const btn = document.querySelector('.btn-save-mobile');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader-2" style="width:18px;height:18px;animation:spin 1s linear infinite;"></i> Odesílám...';
            btn.disabled = true;
            lucide.createIcons();

            try {
                const formData = new FormData(this);
                const response = await fetch('save_visit.php', {
                    method: 'POST',
                    body: formData
                });
                
                // I když save_visit.php vrací redirect, fetch ho "skousne" a my se prostě jen přesměrujeme v okně
                window.location.href = 'm-history.php?client_id=<?= $client_id ?>&success=1';
            } catch (err) {
                alert('Chyba při odesílání: ' + err);
                btn.innerHTML = originalText;
                btn.disabled = false;
                lucide.createIcons();
            }
            return false;
        };

        let bowlCounter = 0;

        function addBowl() {
            const wrap = document.getElementById('m-bowls');
            const count = wrap.querySelectorAll('.m-bowl').length + 1;
            const bName = "Miska " + count;
            
            const bowlDiv = document.createElement('div');
            bowlDiv.className = 'm-bowl';
            
            // Jednoduchý a spolehlivý index pro doručení dat na server
            const bIdx = bowlCounter++;
            bowlDiv.dataset.index = bIdx;
            
            bowlDiv.innerHTML = `
                <input type="hidden" name="bowl_index[]" value="${bIdx}">
                <div class="m-bowl-header">
                    <input type="text" class="m-bowl-title" name="bowl_names[${bIdx}]" value="${bName}" onclick="this.select()">
                    <button type="button" class="m-bowl-del" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
                <div class="m-bowl-rows"></div>
                <button type="button" class="m-add-row-btn" onclick="addRow(this.previousElementSibling, true)">+ Přidat barvu / oxidant</button>
            `;
            wrap.appendChild(bowlDiv);
            addRow(bowlDiv.querySelector('.m-bowl-rows'), false);
            
            // Plynulé odscrollování na novou misku
            setTimeout(() => {
                bowlDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 50);
        }

        // Generate a random ID for the radio buttons per bowl
        function generateId() { return Math.random().toString(36).substr(2, 9); }

        function addRow(container, focus = false) {
            let bIdx = container.parentElement.dataset.index;
            const rowDiv = document.createElement('div');
            rowDiv.className = 'm-row';
            rowDiv.innerHTML = `
                <div class="m-material-wrap">
                    <input type="hidden" name="material_id[${bIdx}][]">
                    <input type="text" class="m-material-input" placeholder="Hledat odstín (vyberte)..." autocomplete="off">
                    <div class="ac-list"></div>
                </div>
                <div class="m-shop-toggle"></div>
                <input type="number" name="amount_g[${bIdx}][]" class="m-amount-input" placeholder="g">
                <button type="button" class="m-row-del" onclick="if(this.parentElement.parentElement.children.length > 1) this.parentElement.remove()">×</button>
            `;
            container.appendChild(rowDiv);
            setupAutocomplete(rowDiv.querySelector('.m-material-input'));
            if(focus) {
                rowDiv.querySelector('.m-material-input').focus();
                // Na mobilu odscrollovat na nový řádek, aby nebyl schovaný pod klávesnicí
                setTimeout(() => {
                    rowDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
        }

        function setupAutocomplete(input) {
            const wrap = input.parentElement;
            const hidden = wrap.querySelector('input[type="hidden"]');
            const list = wrap.querySelector('.ac-list');

            input.addEventListener('input', function() {
                const val = this.value.toLowerCase().trim();
                list.innerHTML = '';
                if(!val) { list.style.display = 'none'; return; }
                
                let matches = materialsData.filter(m => {
                    let text = ` ${m.brand} ${m.category} ${m.name}`.toLowerCase();
                    let terms = val.split(" ");
                    return terms.every(t => text.includes(t));
                }).slice(0, 15);
                
                if(matches.length > 0) {
                    matches.forEach(m => {
                        let div = document.createElement('div');
                        div.className = 'ac-item';
                        div.innerHTML = `<strong style="color:var(--primary);">${m.category}</strong> ${m.name}`;
                        div.onmousedown = function(e) {
                            e.preventDefault();
                            hidden.value = m.id;
                            input.value = m.category + ' ' + m.name;
                            list.style.display = 'none';
                            updateShopIcon(wrap.nextElementSibling, m.id, m.needs_buying);
                            let amountInput = wrap.nextElementSibling.nextElementSibling;
                            if(amountInput) amountInput.focus();
                        };
                        list.appendChild(div);
                    });
                    list.style.display = 'block';
                } else {
                    list.style.display = 'none';
                }
            });

            input.addEventListener('blur', () => { setTimeout(() => { list.style.display = 'none'; }, 350); });
            input.addEventListener('focus', () => { if(input.value) input.dispatchEvent(new Event('input')); });
        }

        function applyPastRecipe(pastBowls, confirmNeeded = true) {
            if(confirmNeeded && !confirm('Opravdu chcete přepsat rozdělaný recept touto historií?')) return;
            
            const wrap = document.getElementById('m-bowls');
            wrap.innerHTML = ''; 
            let localBowlCount = 0;
            
            for (const [bName, mats] of Object.entries(pastBowls)) {
                // Přidat misku
                const bIdx = bowlCounter++;
                const bowlDiv = document.createElement('div');
                bowlDiv.className = 'm-bowl';
                bowlDiv.dataset.index = bIdx;
                bowlDiv.innerHTML = `
                    <input type="hidden" name="bowl_index[]" value="${bIdx}">
                    <div class="m-bowl-header">
                        <input type="text" class="m-bowl-title" name="bowl_names[${bIdx}]" value="${bName}" onclick="this.select()">
                        <button type="button" class="m-bowl-del" onclick="this.parentElement.parentElement.remove()">×</button>
                    </div>
                    <div class="m-bowl-rows"></div>
                    <button type="button" class="m-add-row-btn" onclick="addRow(this.previousElementSibling, true)">+ Přidat barvu / oxidant</button>
                `;
                wrap.appendChild(bowlDiv);
                
                const rowsCont = bowlDiv.querySelector('.m-bowl-rows');
                mats.forEach(m => {
                    // Přidat řádek
                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'm-row';
                    rowDiv.innerHTML = `
                        <div class="m-material-wrap">
                            <input type="hidden" name="material_id[${bIdx}][]" value="${m.id}">
                            <input type="text" class="m-material-input" placeholder="Hledat..." autocomplete="off" value="${m.name}">
                            <div class="ac-list"></div>
                        </div>
                        <div class="m-shop-toggle"></div>
                        <input type="number" name="amount_g[${bIdx}][]" class="m-amount-input" placeholder="g" value="${m.amt}">
                        <button type="button" class="m-row-del" onclick="if(this.parentElement.parentElement.children.length > 1) this.parentElement.remove()">×</button>
                    `;
                    rowsCont.appendChild(rowDiv);
                    setupAutocomplete(rowDiv.querySelector('.m-material-input'));
                    // Zjistit aktuální stav needs_buying z dat
                    let matFull = materialsData.find(md => md.id == m.id);
                    updateShopIcon(rowDiv.querySelector('.m-shop-toggle'), m.id, matFull ? matFull.needs_buying : 0);
                });
            }
            
            lucide.createIcons();
            
            if(confirmNeeded) {
                const stickyEl = document.querySelector('.m-sticky-top');
                const offset = (stickyEl ? stickyEl.offsetHeight : 0) + 70;
                window.scrollTo({top: offset, behavior: 'smooth'});
            }
        }

        function updateShopIcon(container, materialId, needsBuying) {
            if(!container) return;
            container.innerHTML = `
                <button type="button" class="m-btn-shop ${needsBuying ? 'active' : ''}" onclick="toggleShopping(${materialId}, this)">
                    <i data-lucide="shopping-cart"></i>
                </button>
            `;
            lucide.createIcons();
        }

        async function toggleShopping(id, btn) {
            try {
                const formData = new FormData();
                formData.append('material_id', id);
                const resp = await fetch('api_shopping.php', { method: 'POST', body: formData });
                const json = await resp.json();
                if(json.success) {
                    if(json.new_status) btn.classList.add('active');
                    else btn.classList.remove('active');
                    
                    // Aktualizujeme i lokální data, aby se to projevilo u dalších řádků
                    let mat = materialsData.find(m => m.id == id);
                    if(mat) mat.needs_buying = json.new_status;
                }
            } catch(e) { console.error(e); }
        }

        // Initialize data
        <?php if (!empty($prefill_bowls)): ?>
            setTimeout(() => {
                applyPastRecipe(<?= json_encode($prefill_bowls) ?>, false);
            }, 100);
        <?php else: ?>
            addBowl();
        <?php endif; ?>
    </script>
</body>
</html>
