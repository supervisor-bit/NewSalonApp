    setTimeout(function() {
        let msgBox = document.getElementById('toast-msg');
        if(msgBox) {
            msgBox.style.opacity = '0';
            setTimeout(() => msgBox.style.display = 'none', 500);
        }
    }, 5000);

    function showModalFlex(id) { document.getElementById(id).style.display = 'flex'; }
    function hideModal(id) { document.getElementById(id).style.display = 'none'; }
    function getCsrfToken() {
        if (typeof window !== 'undefined' && window.CSRF_TOKEN) return window.CSRF_TOKEN;
        if (typeof CSRF_TOKEN !== 'undefined') return CSRF_TOKEN;
        return '';
    }
    function withCsrf(url) {
        const token = getCsrfToken();
        if (!token) return url;
        return url + (url.includes('?') ? '&' : '?') + 'csrf_token=' + encodeURIComponent(token);
    }

    let deferredInstallPrompt = null;
    function isStandaloneDisplayMode() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
            || window.navigator.standalone === true;
    }

    function updateDesktopInstallUi(message) {
        const installBtn = document.getElementById('install-desktop-app-btn');
        const statusEl = document.getElementById('desktop-install-status');
        if (!installBtn || !statusEl) return;

        if (isStandaloneDisplayMode()) {
            installBtn.style.display = 'none';
            statusEl.innerHTML = message || 'Aplikace už je na tomto zařízení nainstalovaná a běží samostatně.';
            return;
        }

        if (deferredInstallPrompt) {
            installBtn.style.display = 'inline-flex';
            statusEl.innerHTML = message || 'KARTU si můžete připnout do počítače a otevírat ji bez klasického panelu prohlížeče.';
            return;
        }

        installBtn.style.display = 'none';
        statusEl.innerHTML = message || 'V Chrome nebo Edge otevřete menu <b>⋮</b> a zvolte <b>Instalovat aplikaci</b>.';
    }

    async function installDesktopApp() {
        if (!deferredInstallPrompt) {
            updateDesktopInstallUi();
            return;
        }

        deferredInstallPrompt.prompt();
        const choice = await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;

        if (choice && choice.outcome === 'accepted') {
            updateDesktopInstallUi('Instalace byla potvrzena. KARTA se objeví jako samostatná aplikace.');
        } else {
            updateDesktopInstallUi('Instalaci můžete kdykoli spustit později z menu prohlížeče.');
        }
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        updateDesktopInstallUi();
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        updateDesktopInstallUi('Aplikace byla právě nainstalována na toto zařízení.');
    });

    document.addEventListener('DOMContentLoaded', () => {
        updateDesktopInstallUi();
    });

    let actionDialogResolver = null;
    function openActionDialog(options = {}) {
        const modal = document.getElementById('action-dialog-modal');
        if (!modal) return Promise.resolve(false);

        const {
            title = 'Potvrzení',
            message = '',
            confirmText = 'Pokračovat',
            cancelText = 'Zrušit',
            variant = 'danger',
            showCancel = true
        } = options;

        const titleEl = document.getElementById('action-dialog-title');
        const textEl = document.getElementById('action-dialog-text');
        const confirmBtn = document.getElementById('action-dialog-confirm');
        const cancelBtn = document.getElementById('action-dialog-cancel');
        const iconWrap = document.getElementById('action-dialog-icon');

        if (titleEl) titleEl.textContent = title;
        if (textEl) textEl.textContent = message;
        if (confirmBtn) {
            confirmBtn.textContent = confirmText;
            confirmBtn.style.background = variant === 'danger' ? '#ef4444' : 'var(--primary)';
            confirmBtn.style.boxShadow = variant === 'danger'
                ? '0 4px 6px -1px rgba(239, 68, 68, 0.2)'
                : '0 4px 10px rgba(197, 160, 89, 0.25)';
        }
        if (cancelBtn) {
            cancelBtn.textContent = cancelText;
            cancelBtn.style.display = showCancel ? 'inline-flex' : 'none';
        }
        if (iconWrap) {
            iconWrap.style.background = variant === 'danger' ? '#fee2e2' : '#fff8e6';
            iconWrap.style.color = variant === 'danger' ? '#ef4444' : 'var(--primary-dark)';
        }

        showModalFlex('action-dialog-modal');
        return new Promise(resolve => { actionDialogResolver = resolve; });
    }

    function closeActionDialog(result = false) {
        hideModal('action-dialog-modal');
        if (actionDialogResolver) {
            actionDialogResolver(result);
            actionDialogResolver = null;
        }
    }

    function skryjVsechnyPohledy() {
        // Schovat všechny hlavní boxy - přidána chybějící ID pro vzájemné vyloučení
        const boxes = [
            'history-box', 
            'new-visit-box', 
            'edit-visit-box', 
            'accounting-box', 
            'settings-dashboard-box', 
            'client-karta-box'
        ];
        boxes.forEach(id => {
            let el = document.getElementById(id);
            if(el) el.style.display = 'none';
        });
        
        // Resetujeme aktivní stavy v Nav Rail
        document.querySelectorAll('.rail-item').forEach(i => i.classList.remove('active'));
    }

    function filtrHistorii(dotaz) {
        let emptyMsg = document.getElementById('history-empty-filter');
        if (!dotaz) {
            document.querySelectorAll('#history-box .visit-card').forEach(c => c.style.display = 'block');
            if(emptyMsg) emptyMsg.style.display = 'none';
            return;
        }
        
        let cards = document.querySelectorAll('#history-box .visit-card');
        let d = dotaz.toLowerCase().replace(/\s+/g, '').replace(/\.0/g, '.').replace(/^0+/, '').replace(/\.$/, ''); 
        
        let visibleCount = 0;
        cards.forEach(c => {
            let sDateRaw = c.getAttribute('data-search-date');
            if (!sDateRaw) return;
            
            sDateRaw = sDateRaw.toLowerCase();
            let sDateNorm = sDateRaw.replace(/\s+/g, '').replace(/\.0/g, '.').replace(/^0+/, '');
            
            if(sDateNorm.includes(d) || sDateRaw.includes(dotaz.toLowerCase())) {
                c.style.display = 'block';
                visibleCount++;
            } else {
                c.style.display = 'none';
            }
        });

        if(emptyMsg) {
            emptyMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }

    function ukazStatistiky() {
        skryjVsechnyPohledy();
        document.getElementById('stats-dashboard-box').style.display = 'flex';
        document.getElementById('nav-stats').classList.add('active');
        document.querySelector('.sidebar').style.display = 'none'; // Schovat seznam klientek v grafech
        window.history.pushState({}, '', 'index.php');
    }

    function ukazUcetnictvi() {
        skryjVsechnyPohledy();
        document.getElementById('accounting-box').style.display = 'block';
        document.getElementById('nav-accounting').classList.add('active');
        document.querySelector('.sidebar').style.display = 'none';
        window.history.pushState({}, '', 'index.php?view=accounting');
    }

    function prepniAccounting(pohled) {
        document.querySelectorAll('#accounting-box .acc-tab-btn-v2').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('#accounting-box .acc-view').forEach(v => v.style.display = 'none');
        
        if(pohled === 'dnes') {
            document.getElementById('acc-btn-dnes').classList.add('active');
            document.getElementById('acc-view-dnes').style.display = 'block';
        } else if(pohled === 'nakup') {
            document.getElementById('acc-btn-nakup').classList.add('active');
            document.getElementById('acc-view-nakup').style.display = 'block';
        } else {
            document.getElementById('acc-btn-mesic').classList.add('active');
            document.getElementById('acc-view-mesic').style.display = 'block';
        }
        lucide.createIcons();
    }

    async function odebratZNakupu(id, btn) {
        try {
            const formData = new FormData();
            formData.append('material_id', id);
            formData.append('csrf_token', getCsrfToken());
            const resp = await fetch('api_shopping.php', { method: 'POST', body: formData });
            const json = await resp.json();
            if(json.success && !json.new_status) {
                // Položka byla odebrána
                const row = btn.closest('.acc-row-v2');
                row.style.opacity = '0.3';
                row.style.pointerEvents = 'none';
                
                // Okamžitá aktualizace badge v UI (bez refreshe)
                const badge = document.getElementById('shopping-badge-count');
                if(badge) {
                    let currentCount = parseInt(badge.textContent);
                    if(currentCount > 1) {
                        badge.textContent = currentCount - 1;
                    } else {
                        badge.remove();
                    }
                }
                
                setTimeout(() => row.remove(), 400);
            }
        } catch(e) { console.error(e); }
    }

    function prepniSettings(tabId) {
        // Schovat všechny pohledy v nastavení
        document.querySelectorAll('#settings-dashboard-box .acc-view').forEach(v => v.style.display = 'none');
        // Deaktivovat všechna tlačítka
        document.querySelectorAll('#settings-dashboard-box .acc-tab-btn-v2').forEach(b => b.classList.remove('active'));
        
        // Aktivovat vybraný
        const view = document.getElementById('set-view-' + tabId);
        const btn = document.getElementById('set-tab-btn-' + tabId);
        
        if(view) view.style.display = 'block';
        if(btn) btn.classList.add('active');
        
        lucide.createIcons();
    }

    function ukazSeznamKlientek() {
        skryjVsechnyPohledy();
        const sidebar = document.querySelector('.sidebar');
        if(sidebar) sidebar.style.display = 'flex';
        
        const navClients = document.getElementById('nav-clients');
        if(navClients) navClients.classList.add('active');
        
        const kartaBox = document.getElementById('client-karta-box');
        if(kartaBox) kartaBox.style.display = 'flex';
        
        const historyBox = document.getElementById('history-box');
        if(historyBox) historyBox.style.display = 'block';
    }

    function ukazHistorii() {
        skryjVsechnyPohledy();
        const sidebar = document.querySelector('.sidebar');
        if(sidebar) sidebar.style.display = 'flex';
        
        const kartaBox = document.getElementById('client-karta-box');
        if(kartaBox) kartaBox.style.display = 'flex';
        
        const historyBox = document.getElementById('history-box');
        if(historyBox) historyBox.style.display = 'block';
        
        const navClients = document.getElementById('nav-clients');
        if(navClients) navClients.classList.add('active');
    }
    
    function ukazCheckout(vid, pPrice, cNote, summaryHtml, currentServicePrice = 0) {
        document.getElementById('checkout-visit-id').value = vid;
        document.getElementById('checkout-products-price').value = pPrice || 0;
        document.getElementById('checkout-products-display').innerText = (pPrice || 0) + ' Kč';
        
        // If visit is done, currentServicePrice is passed. If new, it's 0.
        document.getElementById('checkout-service-price').value = currentServicePrice || '';
        document.getElementById('checkout-note').value = cNote || '';
        document.getElementById('checkout-summary').innerHTML = summaryHtml || '';
        document.getElementById('checkout-given').value = '';
        document.getElementById('checkout-return').innerText = '0 Kč';
        document.getElementById('checkout-next-visit').value = '';
        
        kalkulackaCheckout();
        showModalFlex('checkout-modal');
        lucide.createIcons();

        setTimeout(() => {
            const servicePriceInput = document.getElementById('checkout-service-price');
            if (servicePriceInput) {
                servicePriceInput.focus();
                servicePriceInput.select();
            }
        }, 30);
    }
    function schovCheckout() { hideModal('checkout-modal'); }

    function setNextVisit(weeks) {
        let d = new Date();
        d.setDate(d.getDate() + weeks * 7);
        let iso = d.toISOString().split('T')[0];
        document.getElementById('checkout-next-visit').value = iso;
    }
    
    function kalkulackaCheckout() {
        let pPrice = parseInt(document.getElementById('checkout-products-price').value) || 0;
        let sPrice = parseInt(document.getElementById('checkout-service-price').value) || 0;
        let totalPrice = pPrice + sPrice;
        
        document.getElementById('checkout-total-price').value = totalPrice;
        document.getElementById('checkout-total-display').innerText = totalPrice + ' Kč';
        
        let given = parseInt(document.getElementById('checkout-given').value) || 0;
        let diff = given - totalPrice;
        let retEl = document.getElementById('checkout-return');
        
        if(given === 0) {
            retEl.innerText = '0 Kč';
            retEl.style.color = '#ef4444';
        } else if (diff >= 0) {
            retEl.innerText = diff + ' Kč';
            retEl.style.color = '#16a34a';
        } else {
            retEl.innerText = 'Chybí ' + Math.abs(diff) + ' Kč';
            retEl.style.color = '#ef4444';
        }
    }
    
    function resetNovaNavstevaForm() {
        const form = document.getElementById('new-visit-box');
        if(form) form.reset();

        let container = document.getElementById('bowls-wrapper-new');
        if(container) container.innerHTML = '';

        const prodWrapperNew = document.getElementById('products-wrapper-new');
        if(prodWrapperNew) prodWrapperNew.innerHTML = '';
    }

    function ukazNovaNavsteva() { 
        skryjVsechnyPohledy(); 
        resetNovaNavstevaForm();
        pridatMisku('bowls-wrapper-new', 'Miska 1 (např. Odrosty)');
        
        const kartaBox = document.getElementById('client-karta-box');
        if(kartaBox) kartaBox.style.display = 'flex'; 
        
        const newVisitBox = document.getElementById('new-visit-box');
        if(newVisitBox) newVisitBox.style.display = 'block'; 
    }

    // PŘIDÁVÁNÍ MISEK (BOWLS) A ŘÁDKŮ
    function pridatMisku(wrapperId, bowlName = '', mixRatio = '') {
        const wrapper = document.getElementById(wrapperId);
        let count = wrapper.querySelectorAll('.bowl-container').length + 1;
        let bName = bowlName || ('Miska ' + count);
        
        const tpl = document.getElementById('bowl-template').content.cloneNode(true);
        const container = tpl.querySelector('.bowl-container');
        const inputName = tpl.querySelector('.bowl-name-input');
        const mixInput = tpl.querySelector('.bowl-mix-input');
        inputName.value = bName;
        if (mixInput) mixInput.value = mixRatio || '';
        
        // Jedinečný index pre misku, spolehlivě zabrání kolizím při posílaní PHP pole
        let bIndex = new Date().getTime() + count; 
        inputName.name = `bowl_names[${bIndex}]`;
        if (mixInput) mixInput.name = `bowl_ratio[${bIndex}]`;
        container.dataset.index = bIndex;

        // PŘIDÁNO: Skrytý input pro pole bowl_index[], aby server věděl o všech miskách
        const hiddenIndex = document.createElement('input');
        hiddenIndex.type = 'hidden';
        hiddenIndex.name = 'bowl_index[]';
        hiddenIndex.value = bIndex;
        container.appendChild(hiddenIndex);
        
        wrapper.appendChild(tpl);
        
        const finalContainer = wrapper.lastElementChild;
        const finalMixInput = finalContainer.querySelector('.bowl-mix-input');
        if (finalMixInput) setupMixRatioAutocomplete(finalMixInput, finalContainer);
        pridatRadekKMisaceBtn(finalContainer.querySelector('button[onclick^="pridatRadek"]'));
        updateBowlMixInfo(finalContainer);
        
        if (!bowlName) {
            setTimeout(() => {
                finalContainer.scrollIntoView({ behavior: "smooth", block: "center" });
                let s = finalContainer.querySelector('.material-search'); 
                if(s) s.focus({preventScroll: true});
            }, 100);
        }
        
        return { containerUl: finalContainer.querySelector('.bowl-rows-container'), index: bIndex, bowlEl: finalContainer };
    }

    function escapeRegExp(str) {
        return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightAutocompleteText(text, query) {
        let result = String(text ?? '');
        const parts = String(query ?? '').trim().split(/\s+/).filter(part => part.length > 1);
        parts.forEach(part => {
            const reg = new RegExp(`(${escapeRegExp(part)})`, 'gi');
            result = result.replace(reg, '<strong style="color:var(--primary)">$1</strong>');
        });
        return result;
    }

    function getMaterialMeta(material) {
        const rawCategory = String(material.category || '').trim();
        const rawName = String(material.name || material.label || '').trim();
        const match = rawCategory.match(/^(.*?)\s*\(([^)]+)\)\s*$/);
        const family = match ? match[1].trim() : rawCategory;
        const type = match ? match[2].trim() : '';
        let cleanName = rawName;

        [rawCategory, family].filter(Boolean).forEach(part => {
            cleanName = cleanName.replace(new RegExp(`^${escapeRegExp(part)}\\s*[-–—:/]*\\s*`, 'i'), '');
        });

        if (type) {
            cleanName = cleanName.replace(new RegExp(`^\\(?${escapeRegExp(type)}\\)?\\s*[-–—:/]*\\s*`, 'i'), '');
        }

        cleanName = cleanName.trim() || rawName || 'Bez názvu';
        const prefix = [family || rawCategory, type].filter(Boolean).join(' · ');

        return {
            family: family || rawCategory || 'Materiál',
            type,
            cleanName,
            inputValue: prefix ? `${prefix} – ${cleanName}` : cleanName
        };
    }

    function getProductMeta(product) {
        const brand = String(product.brand || '').trim();
        const rawName = String(product.name || product.label || '').trim();
        let cleanName = rawName;

        if (brand) {
            cleanName = cleanName.replace(new RegExp(`^${escapeRegExp(brand)}\\s*[-–—:/]*\\s*`, 'i'), '');
        }

        cleanName = cleanName.trim() || rawName || 'Bez názvu';

        return {
            brand: brand || 'Produkt',
            cleanName,
            inputValue: brand ? `${brand} – ${cleanName}` : cleanName
        };
    }

    const MIX_RATIO_SUGGESTIONS = [
        { value: '1:1', note: 'stejný díl barvy a oxidantu' },
        { value: '1:1,5', note: 'nejčastější krémové barvy' },
        { value: '1:2', note: 'tonery a lehčí míchání' },
        { value: '1:3', note: 'více naředěná směs' }
    ];

    function normalizeRatioValue(value) {
        return String(value || '').trim().replace(/\s+/g, '').replace(',', '.');
    }

    function formatMixNumber(value) {
        const rounded = Math.round(Number(value || 0) * 10) / 10;
        if (!Number.isFinite(rounded)) return '0';
        return Number.isInteger(rounded) ? String(rounded) : String(rounded).replace('.', ',');
    }

    function parseRatioMultiplier(value) {
        const normalized = normalizeRatioValue(value);
        const match = normalized.match(/^(\d+(?:\.\d+)?)[:/](\d+(?:\.\d+)?)$/);
        if (!match) return null;
        const colorPart = parseFloat(match[1]);
        const oxidantPart = parseFloat(match[2]);
        if (!colorPart || !oxidantPart) return null;
        return oxidantPart / colorPart;
    }

    function isOxidantMaterial(materialId) {
        const material = MATERIALS_DATA.find(m => String(m.id) === String(materialId));
        if (!material) return false;
        const haystack = `${material.brand || ''} ${material.category || ''} ${material.name || ''}`.toLowerCase();
        return haystack.includes('oxid') || haystack.includes('oxyd');
    }

    function setupMixRatioAutocomplete(input, bowlContainer) {
        const listEl = input.parentElement.querySelector('.ac-list');
        if (!listEl) return;

        function renderRatioList(query = '') {
            const normalized = normalizeRatioValue(query);
            listEl.innerHTML = '';

            const matches = MIX_RATIO_SUGGESTIONS.filter(item => {
                const note = item.note.toLowerCase();
                return !normalized || normalizeRatioValue(item.value).includes(normalized) || note.includes(String(query || '').toLowerCase());
            });

            if (matches.length === 0) {
                listEl.style.display = 'none';
                return;
            }

            matches.forEach((item, idx) => {
                const div = document.createElement('div');
                div.className = 'ac-item' + (idx === 0 ? ' ac-active' : '');
                div.innerHTML = `
                    <div style="display:flex; flex-direction:column; gap:2px;">
                        <span style="font-weight:800; color:var(--primary);">${item.value}</span>
                        <span style="font-size:11px; color:#64748b;">${item.note}</span>
                    </div>
                `;
                div.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    input.value = item.value;
                    listEl.style.display = 'none';
                    updateBowlMixInfo(bowlContainer);
                });
                listEl.appendChild(div);
            });

            listEl.style.display = 'block';
        }

        input.addEventListener('input', function() {
            renderRatioList(this.value);
            updateBowlMixInfo(bowlContainer);
        });
        input.addEventListener('focus', function() {
            renderRatioList(this.value);
            this.select();
        });
        input.addEventListener('blur', function() {
            setTimeout(() => { listEl.style.display = 'none'; }, 200);
        });
        input.addEventListener('keydown', function(e) {
            const items = listEl.querySelectorAll('.ac-item');
            if (e.key === 'Escape') {
                listEl.style.display = 'none';
                return;
            }
            if (e.key === 'Enter' && items.length > 0) {
                e.preventDefault();
                const activeIdx = Array.from(items).findIndex(i => i.classList.contains('ac-active'));
                (activeIdx > -1 ? items[activeIdx] : items[0]).dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                return;
            }
            if (items.length === 0) return;
            let activeIdx = Array.from(items).findIndex(i => i.classList.contains('ac-active'));
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (activeIdx > -1) items[activeIdx].classList.remove('ac-active');
                let next = (activeIdx + 1) % items.length;
                items[next].classList.add('ac-active');
                items[next].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (activeIdx > -1) items[activeIdx].classList.remove('ac-active');
                let prev = activeIdx - 1 < 0 ? items.length - 1 : activeIdx - 1;
                items[prev].classList.add('ac-active');
                items[prev].scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function updateBowlMixInfo(bowlContainer) {
        if (!bowlContainer) return;
        const summaryEl = bowlContainer.querySelector('.bowl-mix-summary');
        const ratioInput = bowlContainer.querySelector('.bowl-mix-input');
        if (!summaryEl || !ratioInput) return;

        let colorTotal = 0;
        let oxidantTotal = 0;
        bowlContainer.querySelectorAll('.recept-row').forEach(row => {
            const hiddenEl = row.querySelector('.material-hidden');
            const amountEl = row.querySelector('.amount-input');
            const grams = parseFloat(String(amountEl?.value || '').replace(',', '.')) || 0;
            if (grams <= 0) return;
            if (isOxidantMaterial(hiddenEl?.value)) oxidantTotal += grams;
            else colorTotal += grams;
        });

        const ratioValue = String(ratioInput.value || '').trim();
        const multiplier = parseRatioMultiplier(ratioValue);
        const parts = [];

        if (colorTotal > 0) parts.push(`Barva ${formatMixNumber(colorTotal)} g`);
        if (ratioValue) parts.push(`Poměr ${ratioValue}`);
        if (multiplier !== null && colorTotal > 0) parts.push(`Oxidant cca ${formatMixNumber(colorTotal * multiplier)} g`);
        if (oxidantTotal > 0) parts.push(`Zadáno oxidantu ${formatMixNumber(oxidantTotal)} g`);

        summaryEl.textContent = parts.length > 0
            ? parts.join(' • ')
            : 'Tip: napiš třeba 1:1 nebo 1:1,5 a hned uvidíš doporučené gramy oxidantu.';
    }

    function removeRecipeRow(btn) {
        const row = btn.closest('.recept-row');
        const rowsWrap = row?.parentElement;
        if (row && rowsWrap && rowsWrap.children.length > 1) {
            row.remove();
            updateBowlMixInfo(rowsWrap.closest('.bowl-container'));
        }
    }

    function getNormalizedFormulaGroups(formulasJson) {
        const raw = JSON.parse(formulasJson || '{}');
        const normalized = {};

        Object.entries(raw).forEach(([bowlName, bowlData]) => {
            if (Array.isArray(bowlData)) {
                normalized[bowlName] = { ratio: '', items: bowlData };
            } else {
                normalized[bowlName] = {
                    ratio: bowlData?.ratio || '',
                    items: Array.isArray(bowlData?.items) ? bowlData.items : []
                };
            }
        });

        return normalized;
    }

    function odeslatDoNaseptavace(searchEl, hiddenEl, listEl) {
        function updateList(val = '') {
            val = val.toLowerCase().trim();
            listEl.innerHTML = '';
            
            let matches = [];
            if (!val) {
                // Top 5 nejpoužívanějších pokud je prázdné
                matches = MATERIALS_DATA.slice(0, 5);
                if (matches.length > 0) {
                    let head = document.createElement('div');
                    head.style.padding = '4px 8px'; head.style.fontSize = '10px'; head.style.color = 'var(--primary)'; head.style.fontWeight = '700'; head.style.textTransform = 'uppercase'; head.style.borderBottom = '1px solid rgba(212,175,55,0.1)';
                    head.innerText = 'Často používané';
                    listEl.appendChild(head);
                }
            } else {
                // Inteligentní víceslovné hledání
                let queryParts = val.split(/\s+/);
                matches = MATERIALS_DATA.filter(m => {
                    let target = (m.category + ' ' + m.name).toLowerCase();
                    return queryParts.every(part => target.includes(part));
                });
            }

            if(matches.length === 0) { listEl.style.display = 'none'; return; }
            
            matches.slice(0, 30).forEach((m, idx) => {
                let div = document.createElement('div');
                const meta = getMaterialMeta(m);
                const displayName = highlightAutocompleteText(meta.cleanName, val);
                const familyLabel = highlightAutocompleteText(meta.family, val);
                const mainLine = meta.cleanName && meta.cleanName.toLowerCase() !== meta.family.toLowerCase()
                    ? `<span style="font-weight:700; color:var(--text);">${familyLabel}</span><span style="color:#94a3b8;"> — </span><span style="color:#475569;">${displayName}</span>`
                    : `<span style="font-weight:700; color:var(--text);">${familyLabel}</span>`;
                const typeBadge = meta.type
                    ? `<span style="font-size:10px; background:rgba(212,175,55,0.12); color:var(--primary); padding:2px 7px; border-radius:999px; font-weight:700; letter-spacing:0.3px;">${highlightAutocompleteText(meta.type, val)}</span>`
                    : '';

                div.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; width:100%;">
                        <div style="display:flex; flex-direction:column; min-width:0;">
                            <span class="m-name">${mainLine}</span>
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:4px;">
                                ${typeBadge}
                            </div>
                        </div>
                        ${m.use_count > 0 ? `<span style="font-size:10px; background:rgba(212,175,55,0.1); color:var(--primary); padding:2px 5px; border-radius:4px;" title="Počet použití">${m.use_count}×</span>` : ''}
                    </div>
                `;
                div.className = 'ac-item' + (idx === 0 ? ' ac-active' : '');
                
                div.addEventListener('click', function() {
                    hiddenEl.value = m.id;
                    searchEl.value = meta.inputValue;
                    listEl.style.display = 'none';

                    // PŘIDÁNO: Aktualizace košíku v míchárně
                    const row = searchEl.closest('.recept-row');
                    if(row) {
                        const shopCont = row.querySelector('.shop-toggle-pc');
                        if(shopCont) {
                            let matFull = MATERIALS_DATA.find(mat => mat.id == m.id);
                            updateShopIconPC(shopCont, m.id, matFull ? matFull.needs_buying : 0);
                        }
                        updateBowlMixInfo(row.closest('.bowl-container'));
                    }

                    let amountBox = searchEl.closest('.recept-row').querySelector('.amount-input');
                    if(amountBox) {
                        amountBox.focus();
                        amountBox.select();
                    }
                });
                listEl.appendChild(div);
            });
            listEl.style.display = 'block';
        }

        searchEl.addEventListener('input', function() { updateList(this.value); });
        searchEl.addEventListener('focus', function() { updateList(this.value); this.select(); });

        searchEl.addEventListener('keydown', function(e) {
            let items = listEl.querySelectorAll('.ac-item');
            if (e.key === 'Escape') { listEl.style.display = 'none'; return; }

            if (e.key === 'Enter') {
                e.preventDefault();
                if (e.ctrlKey || e.metaKey) {
                    listEl.style.display = 'none';
                    let b = searchEl.closest('.bowl-container');
                    if(b && b.parentElement.id) pridatMisku(b.parentElement.id);
                    return;
                }
                let activeIdx = Array.from(items).findIndex(i => i.classList.contains('ac-active'));
                if(activeIdx > -1) {
                    items[activeIdx].click();
                } else if (items.length > 0) {
                    items[0].click();
                }
                return;
            }

            if(items.length === 0) return;
            let activeIdx = Array.from(items).findIndex(i => i.classList.contains('ac-active'));
            
            if(e.key === 'ArrowDown') {
                e.preventDefault();
                if(activeIdx > -1) items[activeIdx].classList.remove('ac-active');
                let next = (activeIdx + 1) % items.length;
                items[next].classList.add('ac-active');
                items[next].scrollIntoView({block: 'nearest'});
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if(activeIdx > -1) items[activeIdx].classList.remove('ac-active');
                let prev = activeIdx - 1 < 0 ? items.length - 1 : activeIdx - 1;
                items[prev].classList.add('ac-active');
                items[prev].scrollIntoView({block: 'nearest'});
            } else if (e.key === 'Tab') {
                // If there's a selected item, apply it on tab too? 
                // Mostly just hide list
                listEl.style.display = 'none';
            }
        });
        
        document.addEventListener('click', function(e) {
            if(e.target !== searchEl && !listEl.contains(e.target)) listEl.style.display = 'none';
        });
    }

    function odeslatDoNaseptavaceProduktu(searchEl, hiddenEl, listEl, priceEl, amountEl) {
        function updateList(val = '') {
            val = val.toLowerCase().trim();
            listEl.innerHTML = '';
            
            let matches = [];
            if (!val) {
                matches = PRODUCTS_DATA.slice(0, 5);
                if (matches.length > 0) {
                    let head = document.createElement('div');
                    head.style.padding = '4px 8px'; head.style.fontSize = '10px'; head.style.color = 'var(--primary)'; head.style.fontWeight = '700'; head.style.textTransform = 'uppercase'; head.style.borderBottom = '1px solid rgba(212,175,55,0.1)';
                    head.innerText = 'Často prodávané';
                    listEl.appendChild(head);
                }
            } else {
                let queryParts = val.split(/\s+/);
                matches = PRODUCTS_DATA.filter(p => {
                    let target = (p.brand + ' ' + p.name).toLowerCase();
                    return queryParts.every(part => target.includes(part));
                });
            }

            if(matches.length === 0) { listEl.style.display = 'none'; return; }
            
            matches.slice(0, 30).forEach((p, idx) => {
                let div = document.createElement('div');
                const meta = getProductMeta(p);
                const displayName = highlightAutocompleteText(meta.cleanName, val);
                const brandLabel = highlightAutocompleteText(meta.brand, val);

                div.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; width:100%;">
                        <div style="display:flex; flex-direction:column; min-width:0;">
                            <span style="font-size:10px; color:#64748b; text-transform:uppercase; font-weight:700; letter-spacing:0.6px;">${brandLabel}</span>
                            <span>${displayName}</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            ${p.use_count > 0 ? `<span style="font-size:10px; background:rgba(212,175,55,0.1); color:var(--primary); padding:2px 5px; border-radius:4px;">${p.use_count}×</span>` : ''}
                            <span style="font-size:12px; font-weight:700; color:var(--primary); white-space:nowrap;">${p.price} Kč</span>
                        </div>
                    </div>
                `;
                div.className = 'ac-item' + (idx === 0 ? ' ac-active' : '');
                div.addEventListener('click', function() {
                    hiddenEl.value = p.id;
                    searchEl.value = meta.inputValue;
                    priceEl.value = p.price;
                    listEl.style.display = 'none';
                    if(amountEl) {
                        amountEl.focus();
                        amountEl.select();
                    }
                });
                listEl.appendChild(div);
            });
            listEl.style.display = 'block';
        }

        searchEl.addEventListener('input', function() { updateList(this.value); });
        searchEl.addEventListener('focus', function() { updateList(this.value); this.select(); });

        searchEl.addEventListener('keydown', function(e) {
            let items = listEl.querySelectorAll('.ac-item');
            if (e.key === 'Escape') { listEl.style.display = 'none'; return; }
            if (e.key === 'Enter') {
                e.preventDefault();
                let activeIdx = Array.from(items).findIndex(i => i.classList.contains('ac-active'));
                if(activeIdx > -1) {
                    items[activeIdx].click();
                } else if (items.length > 0) {
                    items[0].click();
                }
                return;
            }
            if(items.length === 0) return;
            let activeIdx = Array.from(items).findIndex(i => i.classList.contains('ac-active'));
            if(e.key === 'ArrowDown') {
                e.preventDefault();
                if(activeIdx > -1) items[activeIdx].classList.remove('ac-active');
                let next = (activeIdx + 1) % items.length;
                items[next].classList.add('ac-active');
                items[next].scrollIntoView({block: 'nearest'});
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if(activeIdx > -1) items[activeIdx].classList.remove('ac-active');
                let prev = activeIdx - 1 < 0 ? items.length - 1 : activeIdx - 1;
                items[prev].classList.add('ac-active');
                items[prev].scrollIntoView({block: 'nearest'});
            }
        });
        document.addEventListener('click', function(e) {
            if(e.target !== searchEl && !listEl.contains(e.target)) listEl.style.display = 'none';
        });
    }

    function pridatRadekKMisaceBtn(btn, matId = '', gramy = '') {
        const bowlContainer = btn.closest('.bowl-container');
        const bIndex = bowlContainer.dataset.index;
        const rowsWrap = bowlContainer.querySelector('.bowl-rows-container');
        
        const rTpl = document.getElementById('receptura-template').content.cloneNode(true);
        const hiddenEl = rTpl.querySelector('.material-hidden');
        hiddenEl.name = `material_id[${bIndex}][]`;
        
        const searchEl = rTpl.querySelector('.material-search');
        const listEl = rTpl.querySelector('.ac-list');
        const inputEl = rTpl.querySelector('.amount-input');
        
        inputEl.name = `amount_g[${bIndex}][]`;
        if(gramy) inputEl.value = gramy;
        
        if (matId) {
            hiddenEl.value = matId;
            let matMatch = MATERIALS_DATA.find(m => m.id == matId);
            if(matMatch) {
                searchEl.value = matMatch.name;
                // Zobrazit košík i pro načtené materiály
                const shopCont = rTpl.querySelector('.shop-toggle-pc');
                if(shopCont) {
                    updateShopIconPC(shopCont, matId, matMatch.needs_buying);
                }
            }
        }
        
        odeslatDoNaseptavace(searchEl, hiddenEl, listEl);
        inputEl.addEventListener('input', function() {
            updateBowlMixInfo(bowlContainer);
        });
        
        inputEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (e.ctrlKey || e.metaKey) {
                    let pw = bowlContainer.parentElement.id;
                    if (pw) pridatMisku(pw);
                } else {
                    pridatRadekKMisaceBtn(btn, '', '', true);
                }
            }
        });

        rowsWrap.appendChild(rTpl);
        updateBowlMixInfo(bowlContainer);

        // Plynulé odscrollování u nového řádku (pokud nevkládáme z historie)
        if (!matId || arguments[3] === true) {
            setTimeout(() => {
                const last = rowsWrap.lastElementChild;
                if(last) {
                    last.scrollIntoView({ behavior: "smooth", block: "center" });
                    const sInput = last.querySelector('.material-search');
                    if(sInput) sInput.focus({preventScroll: true});
                }
            }, 50);
        }
    }

    // ÚPRAVA NÁVŠTĚVY - RECEPTURY A PRODUKTY
    function ukazUpravitNavstevu(id, datum, formulasJson, productsJson, sMetalDetox, sTrim, sBlow, sCurl, sIron) {
        skryjVsechnyPohledy();
        
        const visitId = document.getElementById('edit-visit-id');
        if(visitId) visitId.value = id;
        
        const visitDate = document.getElementById('edit-visit-date');
        if(visitDate) visitDate.value = datum;
        
        const metalChk = document.getElementById('edit-s-metal-detox');
        if(metalChk) metalChk.checked = (sMetalDetox == 1);
        
        const trimChk = document.getElementById('edit-s-trim');
        if(trimChk) trimChk.checked = (sTrim == 1);
        
        const blowChk = document.getElementById('edit-s-blow');
        if(blowChk) blowChk.checked = (sBlow == 1);
        
        const curlChk = document.getElementById('edit-s-curl');
        if(curlChk) curlChk.checked = (sCurl == 1);
        
        const ironChk = document.getElementById('edit-s-iron');
        if(ironChk) ironChk.checked = (sIron == 1);
        
        // Receptury
        let container = document.getElementById('bowls-wrapper-edit');
        if(container) {
            container.innerHTML = '';
            let dict = getNormalizedFormulaGroups(formulasJson);
            let names = Object.keys(dict);
            if (names.length === 0) {
                pridatMisku('bowls-wrapper-edit', 'Miska 1');
            } else {
                names.forEach(bName => {
                    const bowlData = dict[bName] || { ratio: '', items: [] };
                    let bowlSetup = pridatMisku('bowls-wrapper-edit', bName, bowlData.ratio || '');
                    if(bowlSetup && bowlSetup.containerUl) {
                        bowlSetup.containerUl.innerHTML = '';
                        (bowlData.items || []).forEach(f => {
                            let tempBtn = bowlSetup.containerUl.parentElement.querySelector('button[onclick^="pridatRadek"]');
                            pridatRadekKMisaceBtn(tempBtn, f.mat_id, f.g);
                        });
                        updateBowlMixInfo(bowlSetup.bowlEl);
                    }
                });
            }
        }

        // Produkty domů
        let prodWrapper = document.getElementById('products-wrapper-edit');
        if(prodWrapper) {
            prodWrapper.innerHTML = '';
            let products = JSON.parse(productsJson || '[]');
            products.forEach(p => {
                pridatProduktRow('products-wrapper-edit', p.product_id, p.price_sold, p.amount || 1);
            });
        }
        
        const kartaBox = document.getElementById('client-karta-box');
        if(kartaBox) kartaBox.style.display = 'flex';
        
        const editVisitBox = document.getElementById('edit-visit-box');
        if(editVisitBox) editVisitBox.style.display = 'block';
        
        window.scrollTo({top: 0, behavior: 'smooth'});
        lucide.createIcons();
    }
    
    // NÁSTROJE METADAT KLIENTA
    function ukazNovaKlientka() { showModalFlex('nova-klientka-modal'); }
    function schovNovaKlientka() { hideModal('nova-klientka-modal'); }

    function toggleClientTagInput(inputId, tag) {
        const input = document.getElementById(inputId);
        if (!input) return;

        let tags = String(input.value || '')
            .split(',')
            .map(item => item.trim())
            .filter(Boolean);

        const normalizedTag = normalizeSearchText(tag);
        const existingIndex = tags.findIndex(item => normalizeSearchText(item) === normalizedTag);

        if (existingIndex >= 0) {
            tags.splice(existingIndex, 1);
        } else {
            tags.push(tag);
        }

        input.value = tags.join(', ');
        input.focus();
    }
    
    function ukazUpravuVarovani() { showModalFlex('edit-allergy-modal'); }
    function schovUpravuVarovani() { hideModal('edit-allergy-modal'); }
    
    function ukazUpravuProfilu(event, id, radekFirst, radekLast, radekPhone, radekInterval, radekTags) {
        if(event) { event.preventDefault(); event.stopPropagation(); }
        document.getElementById('edit-client-profile-id').value = id;
        document.getElementById('edit-client-profile-first').value = radekFirst;
        document.getElementById('edit-client-profile-last').value = radekLast;
        document.getElementById('edit-client-profile-phone').value = radekPhone;
        document.getElementById('edit-client-profile-interval').value = radekInterval || '';
        document.getElementById('edit-client-profile-tags').value = radekTags || '';
        showModalFlex('edit-client-profile-modal');
    }
    function schovUpravuProfilu() { hideModal('edit-client-profile-modal'); }

    function ukazUpravuDiagnostiky() { showModalFlex('edit-diagnostics-modal'); }
    function schovUpravuDiagnostiky() { hideModal('edit-diagnostics-modal'); }

    function pouzijSablonu(formulasJson, servicesJson) {
        skryjVsechnyPohledy();
        resetNovaNavstevaForm();
        
        // Populate Formulas
        let dict = getNormalizedFormulaGroups(formulasJson);
        let names = Object.keys(dict);
        if (names.length === 0) {
            pridatMisku('bowls-wrapper-new', 'Miska 1');
        } else {
            names.forEach(bName => {
                const bowlData = dict[bName] || { ratio: '', items: [] };
                let bowlSetup = pridatMisku('bowls-wrapper-new', bName, bowlData.ratio || '');
                if(bowlSetup && bowlSetup.containerUl) {
                    bowlSetup.containerUl.innerHTML = '';
                    (bowlData.items || []).forEach(f => {
                        let tempBtn = bowlSetup.containerUl.parentElement.querySelector('button[onclick^="pridatRadek"]');
                        pridatRadekKMisaceBtn(tempBtn, f.mat_id, f.g);
                    });
                    updateBowlMixInfo(bowlSetup.bowlEl);
                }
            });
        }

        // Populate Services
        let services = JSON.parse(servicesJson || '{}');
        for (let key in services) {
            let chk = document.querySelector(`#new-visit-box input[name="${key}"]`);
            if (chk) chk.checked = (services[key] == 1);
        }

        const kartaBox = document.getElementById('client-karta-box');
        if(kartaBox) kartaBox.style.display = 'flex';
        
        const newVisitBox = document.getElementById('new-visit-box');
        if(newVisitBox) newVisitBox.style.display = 'block';
        
        const historyBox = document.getElementById('history-box');
        if(historyBox) historyBox.style.display = 'none';
        
        // Scroll to form
        if(newVisitBox) newVisitBox.scrollIntoView({ behavior: 'smooth' });
    }

    function pridatProduktRow(wrapperId, productId = '', price = '', amount = 1) {
        const wrapper = document.getElementById(wrapperId);
        const tpl = document.getElementById('product-row-template').content.cloneNode(true);
        
        const hiddenEl = tpl.querySelector('.product-hidden');
        const searchEl = tpl.querySelector('.product-search');
        const priceEl = tpl.querySelector('.product-price');
        const amountEl = tpl.querySelector('.product-amount');
        const listEl = tpl.querySelector('.ac-list');
        
        if (productId) {
            hiddenEl.value = productId;
            let pMatch = PRODUCTS_DATA.find(p => p.id == productId);
            if(pMatch) searchEl.value = pMatch.name;
            priceEl.value = price;
            amountEl.value = amount;
        }
        
        odeslatDoNaseptavaceProduktu(searchEl, hiddenEl, listEl, priceEl, amountEl);
        
        // Enter v poli Množství přidá další řádek produktu
        amountEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                pridatProduktRow(wrapperId);
                setTimeout(() => {
                    let last = wrapper.lastElementChild;
                    if(last) {
                        last.scrollIntoView({behavior: "smooth", block: "center"});
                        let s = last.querySelector('.product-search');
                        if(s) s.focus();
                    }
                }, 50);
            }
        });

        wrapper.appendChild(tpl);
        
        // Plynulé odscrollování na nový produkt
        if (!productId) {
            setTimeout(() => {
                wrapper.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'center' });
                let s = wrapper.lastElementChild.querySelector('.product-search');
                if(s) s.focus({preventScroll: true});
            }, 100);
        }
        
        lucide.createIcons();
    }
    
    function ukazSmazatModal(url) {
        document.getElementById('potvrdit-smazani-btn').href = withCsrf(url);
        showModalFlex('smazat-modal');
    }
    function schovSmazatModal() { hideModal('smazat-modal'); }

    // ČÍSELNÍK (Nyní součástí nastavení)
    function ukazMaterials() { 
        skryjVsechnyPohledy();
        document.getElementById('settings-dashboard-box').style.display = 'flex';
        document.getElementById('nav-settings').classList.add('active');
        document.querySelector('.sidebar').style.display = 'none';
        prepniSettings('materials');
        window.history.pushState({}, '', 'index.php?view=settings&tab=materials');
    }

    function ukazProducts() { 
        skryjVsechnyPohledy();
        document.getElementById('settings-dashboard-box').style.display = 'flex';
        document.getElementById('nav-settings').classList.add('active');
        document.querySelector('.sidebar').style.display = 'none';
        prepniSettings('products');
        window.history.pushState({}, '', 'index.php?view=settings&tab=products');
    }
    
    function editProd(id, brand, name, price) {
        document.getElementById('edit-prod-id').value = id;
        document.getElementById('edit-prod-brand').value = brand;
        document.getElementById('edit-prod-name').value = name;
        document.getElementById('edit-prod-price').value = price;
        document.getElementById('btn-prod-submit').querySelector('span').innerText = 'Uložit úpravu';
        document.getElementById('btn-prod-cancel').style.display = 'inline-block';
    }
    
    function cancelProdEdit() {
        document.getElementById('edit-prod-id').value = '';
        document.getElementById('edit-prod-brand').value = '';
        document.getElementById('edit-prod-name').value = '';
        document.getElementById('edit-prod-price').value = '';
        document.getElementById('btn-prod-submit').querySelector('span').innerText = 'Přidat produkt';
        document.getElementById('btn-prod-cancel').style.display = 'none';
    }

    function toggleProdAjax(id, btn) {
        let row = btn.closest('.prod-item');
        fetch(withCsrf('toggle_home_product.php?id=' + id + '&ajax=1'))
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                if (data.is_active) {
                    btn.style.color = '#94a3b8';
                    btn.title = 'Skrýt';
                    row.style.opacity = '1';
                    row.style.filter = 'none';
                } else {
                    btn.style.color = '#10b981';
                    btn.title = 'Zobrazit';
                    row.style.opacity = '0.5';
                    row.style.filter = 'grayscale(1)';
                }
                // Refresh icon appearance since we toggle titles/colors but lucide handles <i>
                let icon = btn.querySelector('i');
                icon.setAttribute('data-lucide', data.is_active ? 'eye-off' : 'eye');
                lucide.createIcons();
            }
        })
        .catch(console.error);
    }
    
    function editMat(id, cat, name) {
        document.getElementById('edit-mat-id').value = id;
        document.getElementById('edit-mat-cat').value = cat;
        document.getElementById('edit-mat-name').value = name;
        document.getElementById('btn-mat-submit').innerText = 'Uložit úpravu';
        document.getElementById('btn-mat-cancel').style.display = 'inline-block';
    }
    
    function cancelMatEdit() {
        document.getElementById('edit-mat-id').value = '';
        document.getElementById('edit-mat-cat').value = '';
        document.getElementById('edit-mat-name').value = '';
        document.getElementById('btn-mat-submit').innerText = '+ Přidat';
        document.getElementById('btn-mat-cancel').style.display = 'none';
    }

    function toggleMatAjax(id, btn) {
        let row = btn.closest('.mat-item');
        fetch(withCsrf('toggle_material.php?id=' + id + '&ajax=1'))
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                if (data.is_active) {
                    btn.style.color = '#94a3b8';
                    btn.title = 'Skrýt';
                    row.style.opacity = '1';
                    row.style.filter = 'none';
                } else {
                    btn.style.color = '#10b981';
                    btn.title = 'Zobrazit';
                    row.style.opacity = '0.5';
                    row.style.filter = 'grayscale(1)';
                }
                let icon = btn.querySelector('i');
                icon.setAttribute('data-lucide', data.is_active ? 'eye-off' : 'eye');
                lucide.createIcons();
            }
        })
        .catch(console.error);
    }

    async function toggleFavoriteClient(clientId, isCurrentlyFavorite) {
        try {
            const formData = new FormData();
            formData.append('client_id', String(clientId));
            formData.append('csrf_token', getCsrfToken());

            const response = await fetch('toggle_favorite_client.php?ajax=1', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Oblíbenou klientku se nepodařilo upravit.');
            }

            window.location.href = 'index.php?client_id=' + clientId;
        } catch (err) {
            console.error(err);
            await openActionDialog({
                title: 'Změna se nepodařila',
                message: err && err.message ? err.message : 'Oblíbenou klientku se nepodařilo upravit.',
                confirmText: 'Rozumím',
                variant: 'danger',
                showCancel: false
            });
        }
    }

    async function toggleClientAjax(clientId, isCurrentlyActive) {
        const confirmed = await openActionDialog({
            title: isCurrentlyActive ? 'Přesunout do neaktivních?' : 'Vrátit do hlavního seznamu?',
            message: isCurrentlyActive
                ? 'Klientka se skryje z běžného seznamu, ale zůstane dostupná ve filtru Neaktivní.'
                : 'Klientka se znovu ukáže v hlavním seznamu a bude běžně dostupná.',
            confirmText: isCurrentlyActive ? 'Přesunout' : 'Vrátit zpět',
            cancelText: 'Zrušit',
            variant: isCurrentlyActive ? 'danger' : 'primary',
            showCancel: true
        });

        if (!confirmed) return;

        try {
            const formData = new FormData();
            formData.append('client_id', String(clientId));
            formData.append('csrf_token', getCsrfToken());

            const response = await fetch('toggle_client.php?ajax=1', {
                method: 'POST',
                body: formData
            });

            const raw = await response.text();
            let data = null;

            try {
                data = JSON.parse(raw);
            } catch (e) {
                throw new Error('Server vrátil nečekanou odpověď. Obnov stránku a zkus to znovu.');
            }

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Změna se nepodařila.');
            }

            if (data.is_active) {
                window.location.href = 'index.php?client_id=' + clientId;
            } else {
                window.location.href = 'index.php';
            }
        } catch (err) {
            console.error(err);
            await openActionDialog({
                title: 'Přesun se nepodařil',
                message: err && err.message ? err.message : 'Změna stavu klientky se nepodařila.',
                confirmText: 'Rozumím',
                variant: 'danger',
                showCancel: false
            });
        }
    }

    // DROPDOWN MENU - GLOBÁLNÍ
    let activeDropdownId = null;
    function toggleMenu(event, clientId, cFirst, cLast, cPhone, cInterval, cTags, cIsFavorite, cIsActive) {
        event.preventDefault(); event.stopPropagation();
        let menu = document.getElementById('global-dropdown');
        if (activeDropdownId === clientId && menu.style.display === 'block') {
            menu.style.display = 'none';
            activeDropdownId = null;
            return;
        }
        activeDropdownId = clientId;
        let rect = event.target.getBoundingClientRect();
        menu.style.left = rect.left + 'px';
        menu.style.top = (rect.bottom + 5) + 'px';
        
        document.getElementById('menu-global-edit').onclick = function(e) { 
            e.preventDefault(); e.stopPropagation(); menu.style.display = 'none'; 
            ukazUpravuProfilu(e, clientId, cFirst, cLast, cPhone, cInterval, cTags); 
        };

        const favoriteLink = document.getElementById('menu-global-toggle-favorite');
        if (favoriteLink) {
            favoriteLink.innerHTML = cIsFavorite
                ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>Odepnout z oblíbených'
                : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>Připnout nahoru';
            favoriteLink.onclick = function(e) {
                e.preventDefault(); e.stopPropagation(); menu.style.display = 'none';
                toggleFavoriteClient(clientId, !!cIsFavorite);
            };
        }

        const toggleLink = document.getElementById('menu-global-toggle-status');
        if (toggleLink) {
            toggleLink.innerHTML = cIsActive
                ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>Přesunout do neaktivních'
                : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>Vrátit do seznamu';
            toggleLink.onclick = function(e) {
                e.preventDefault(); e.stopPropagation(); menu.style.display = 'none';
                toggleClientAjax(clientId, !!cIsActive);
            };
        }

        document.getElementById('menu-global-delete').onclick = function(e) { 
            e.preventDefault(); e.stopPropagation(); menu.style.display = 'none'; 
            ukazSmazatModal('delete_client.php?client_id=' + clientId); 
        };
        menu.style.display = 'block';
    }
    document.addEventListener('click', function(e) {
        if (activeDropdownId && !e.target.closest('.btn-menu') && !e.target.closest('#global-dropdown')) {
            document.getElementById('global-dropdown').style.display = 'none';
            activeDropdownId = null;
        }
    });

    // NAŠEPTÁVAČ
    let activeClientGroup = 'all';

    function normalizeSearchText(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function matchesClientGroup(row) {
        const isActive = (row.getAttribute('data-is-active') || '1') !== '0';

        if (activeClientGroup === 'inactive') {
            return !isActive;
        }

        if (!isActive) {
            return false;
        }

        if (activeClientGroup === 'all') return true;
        return (row.getAttribute('data-group') || 'all') === activeClientGroup;
    }

    function updateVisibleClientCount(visibleCount, totalCount) {
        const countEl = document.getElementById('clients-visible-count');
        if (countEl) countEl.textContent = String(visibleCount ?? totalCount ?? 0);
    }

    function setClientGroupFilter(group, btn) {
        activeClientGroup = group || 'all';
        document.querySelectorAll('.sidebar-filter-chip').forEach(chip => chip.classList.remove('active'));
        if (btn) btn.classList.add('active');
        hledejKlientku();
    }

    function hledejKlientku() {
        let filter = document.getElementById('hledani').value.trim();
        let normalizedFilter = normalizeSearchText(filter);
        let compactFilter = normalizedFilter.replace(/\s+/g, '');
        let rows = document.querySelectorAll('.client-row');
        let visibleCount = 0;

        rows.forEach(function(row) {
            let name = normalizeSearchText(row.querySelector('h3').innerText);
            let phone = normalizeSearchText(row.getAttribute('data-phone') || '');
            let normalizedPhone = phone.replace(/\s+/g, '');
            let matchesText = !normalizedFilter || name.includes(normalizedFilter) || normalizedPhone.includes(compactFilter);
            let shouldShow = matchesText && matchesClientGroup(row);
            row.style.display = shouldShow ? '' : 'none';
            if (shouldShow) visibleCount++;
        });

        updateVisibleClientCount(visibleCount, rows.length);
    }

    // --- PREMIUM ACCORDION LOGIC ---
    function toggleAccordion(header) {
        header.classList.toggle('active');
    }

    function toggleVisitCard(header) {
        const card = header?.closest('.visit-card');
        if (!card) return;
        card.classList.toggle('is-collapsed');
    }

    function hledejMaterial() {
        let filter = document.getElementById('mat-hledani').value.toLowerCase().trim();
        let contents = document.querySelectorAll('#set-view-materials .acc-content');
        
        contents.forEach(function(content) {
            let header = content.previousElementSibling;
            let items = content.querySelectorAll('.mat-item');
            let hasVisible = false;
            
            items.forEach(function(item) {
                let cat = item.getAttribute('data-category');
                let name = item.querySelector('span').innerText.toLowerCase();
                
                if (!filter || cat.includes(filter) || name.includes(filter)) {
                    item.style.display = "flex";
                    hasVisible = true;
                } else {
                    item.style.display = "none";
                }
            });
            
            if (hasVisible) {
                header.style.display = "flex";
                content.style.display = "block";
                // Pokud hledáme, automaticky rozbalíme
                if (filter) {
                    header.classList.add('active');
                } else {
                    // Pokud je pole hledání prázdné a uživatel explicitně nezmáčkl, můžeme nechat jak je 
                    // (nebo vše sbalit - uživatel chtěl standardně sbalené)
                }
            } else {
                header.style.display = "none";
                content.style.display = "none";
            }
        });
    }

    function hledejProdukt() {
        let filter = document.getElementById('prod-hledani').value.toLowerCase().trim();
        let contents = document.querySelectorAll('#set-view-products .acc-content');
        
        contents.forEach(function(content) {
            let header = content.previousElementSibling;
            let items = content.querySelectorAll('.prod-item');
            let hasVisible = false;
            
            items.forEach(function(item) {
                let brand = item.getAttribute('data-category');
                let name = item.querySelector('span').innerText.toLowerCase();
                
                if (!filter || brand.includes(filter) || name.includes(filter)) {
                    item.style.display = "flex";
                    hasVisible = true;
                } else {
                    item.style.display = "none";
                }
            });
            
            if (hasVisible) {
                header.style.display = "flex";
                content.style.display = "block";
                if (filter) {
                    header.classList.add('active');
                }
            } else {
                header.style.display = "none";
                content.style.display = "none";
            }
        });
    }


    function updateShopIconPC(container, materialId, needsBuying) {
        if(!container) return;
        // Styl košíku pro PC míchárnu (šedý = ok, zlatý = koupit)
        container.innerHTML = `
            <button type="button" class="btn-shop-pc ${needsBuying ? 'active' : ''}" 
                    style="background:none; border:none; border-radius:5px; padding:5px; cursor:pointer; color:${needsBuying ? 'var(--gold)' : '#cbd5e1'}; display:flex; align-items:center; justify-content:center; transition: all 0.2s;"
                    onclick="toggleShoppingPC(${materialId}, this)" 
                    title="${needsBuying ? 'V nákupním seznamu' : 'Přidat na nákupní seznam'}">
                <i data-lucide="shopping-cart" style="width:18px;height:18px;"></i>
            </button>
        `;
        lucide.createIcons();
    }

    async function toggleShoppingPC(id, btn, isAccountingView = false) {
        try {
            const formData = new FormData();
            formData.append('material_id', id);
            formData.append('csrf_token', getCsrfToken());
            const resp = await fetch('api_shopping.php', { method: 'POST', body: formData });
            const json = await resp.json();
            if(json.success) {
                // Změna barvy ikonky a titulku v míchárně
                if(json.new_status) {
                    btn.classList.add('active');
                    btn.style.color = 'var(--gold)';
                    btn.title = 'V nákupním seznamu';
                } else {
                    btn.classList.remove('active');
                    btn.style.color = '#cbd5e1';
                    btn.title = 'Přidat na nákupní seznam';
                }
                
                // Aktualizace badge v reálném čase (horní navigace)
                const badge = document.getElementById('shopping-badge-count');
                const navTab = document.getElementById('acc-btn-nakup');
                
                if(json.new_status) {
                    if(badge) {
                        badge.textContent = parseInt(badge.textContent) + 1;
                    } else if(navTab) {
                        const newBadge = document.createElement('span');
                        newBadge.id = 'shopping-badge-count';
                        newBadge.style = 'background:#ef4444; color:#fff; font-size:10px; padding:2px 6px; border-radius:10px; margin-left:5px;';
                        newBadge.textContent = '1';
                        navTab.appendChild(newBadge);
                    }
                } else {
                    if(badge) {
                        let currentCount = parseInt(badge.textContent);
                        if(currentCount > 1) badge.textContent = currentCount - 1;
                        else badge.remove();
                    }
                }

                // AKTUALIZACE PANELU FINANCÍ (Nákupní seznam v reálném čase)
                const counterVal = document.getElementById('shopping-counter-val');
                const counterBox = document.getElementById('shopping-counter-box');
                const emptyState = document.getElementById('shopping-empty-state');
                
                if(counterVal) {
                    let current = parseInt(counterVal.textContent);
                    if(!json.new_status && current > 0) {
                        // Naskladněno (odebráno ze seznamu)
                        let nextCount = current - 1;
                        counterVal.textContent = nextCount;
                        if(nextCount === 0) {
                            if(counterBox) counterBox.style.display = 'none';
                            if(emptyState) emptyState.style.display = 'block';
                        }
                    } else if(json.new_status) {
                        // Přidáno do seznamu
                        counterVal.textContent = current + 1;
                        if(counterBox) counterBox.style.display = 'flex';
                        if(emptyState) emptyState.style.display = 'none';
                    }
                }

                // Pokud jsme přímo v nákupním seznamu, řádek plynule schováme
                if(isAccountingView && !json.new_status) {
                    const row = btn.closest('.shopping-row');
                    if(row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => row.remove(), 300);
                    }
                }

                // Aktualizujeme i lokální data materiálů
                let mat = MATERIALS_DATA.find(m => m.id == id);
                if(mat) mat.needs_buying = json.new_status;
            }
        } catch(e) { console.error(e); }
    }
