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
            updateDesktopInstallUi('Instalace byla potvrzena. Aura se objeví jako samostatná aplikace.');
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
        initDirectSaleAutocomplete();
        initDirectSaleFormGuards();
        initCatalogManager();
        if (window.location.search.includes('view=settings')) {
            prepniSettings(window.ACTIVE_SETTINGS_TAB || 'profile');
        }
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
            'direct-sales-box',
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
        const safeTab = tabId || 'profile';
        document.querySelectorAll('#settings-dashboard-box .acc-view').forEach(v => v.style.display = 'none');
        document.querySelectorAll('#settings-dashboard-box .acc-tab-btn-v2').forEach(b => b.classList.remove('active'));

        const view = document.getElementById('set-view-' + safeTab);
        const btn = document.getElementById('set-tab-btn-' + safeTab);

        if (view) view.style.display = 'block';
        if (btn) btn.classList.add('active');

        const settingsBox = document.getElementById('settings-dashboard-box');
        if (settingsBox && settingsBox.style.display !== 'none') {
            window.history.replaceState({}, '', 'index.php?view=settings&tab=' + safeTab);
        }

        if (safeTab === 'catalog') {
            document.querySelectorAll('.catalog-filter-btn').forEach(chip => chip.classList.remove('active'));
            const activeCatalogBtn = document.querySelector(`.catalog-filter-btn[data-filter="${catalogFilterMode}"]`);
            if (activeCatalogBtn) activeCatalogBtn.classList.add('active');
            renderCatalogList();
            updateCatalogActiveTargetLabel();
            setTimeout(() => focusCatalogScannerCapture(true), 60);
        }

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
                            updateShopIconPC(shopCont, m.id, matFull ? matFull.needs_buying : 0, matFull ? matFull.stock_state : 'none');
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
                    items[activeIdx].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                } else if (items.length > 0) {
                    items[0].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
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
        function findProductMatch(rawValue) {
            const query = normalizeSearchText(rawValue)
                .replace(/[–—-]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            if (!query) return null;

            return PRODUCTS_DATA.find(p => {
                const meta = getProductMeta(p);
                const candidates = [
                    meta.inputValue,
                    `${p.brand || ''} - ${p.name || ''}`,
                    `${p.brand || ''} ${p.name || ''}`,
                    p.name || ''
                ].map(val => normalizeSearchText(val).replace(/[–—-]/g, ' ').replace(/\s+/g, ' ').trim());

                return candidates.includes(query);
            }) || null;
        }

        function applyProductSelection(product, moveFocus = true) {
            const meta = getProductMeta(product);
            const normalizedPrice = typeof parseDirectSaleNumber === 'function'
                ? parseDirectSaleNumber(product.price)
                : (parseFloat(product.price) || 0);

            hiddenEl.value = product.id;
            searchEl.value = meta.inputValue;

            if (!priceEl.value || priceEl.dataset.autofill !== 'manual') {
                priceEl.value = normalizedPrice > 0 ? String(normalizedPrice) : '';
                priceEl.dataset.autofill = normalizedPrice > 0 ? 'auto' : 'needs-price';
            }

            searchEl.setCustomValidity('');
            listEl.style.display = 'none';

            if (typeof scheduleDirectSaleSummaryRefresh === 'function') {
                scheduleDirectSaleSummaryRefresh();
            } else if (typeof updateDirectSaleSummary === 'function') {
                updateDirectSaleSummary();
            }

            if (moveFocus) {
                if (priceEl && normalizedPrice <= 0) {
                    priceEl.focus();
                    priceEl.select();
                } else if (amountEl) {
                    amountEl.focus();
                    amountEl.select();
                }
            }
        }

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
                div.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    applyProductSelection(p, true);
                });
                listEl.appendChild(div);
            });
            listEl.style.display = 'block';
        }

        searchEl.addEventListener('input', function() {
            hiddenEl.value = '';
            searchEl.setCustomValidity('');
            updateList(this.value);
            if (typeof updateDirectSaleSummary === 'function') updateDirectSaleSummary();
        });
        searchEl.addEventListener('focus', function() { updateList(this.value); this.select(); });
        searchEl.addEventListener('change', function() {
            const match = findProductMatch(this.value);
            if (match) applyProductSelection(match, false);
            if (typeof updateDirectSaleSummary === 'function') updateDirectSaleSummary();
        });
        searchEl.addEventListener('blur', function() {
            const match = findProductMatch(this.value);
            if (match) {
                applyProductSelection(match, false);
            } else if (this.value.trim()) {
                searchEl.setCustomValidity('Vyberte produkt z nabídky.');
            }
            if (typeof updateDirectSaleSummary === 'function') updateDirectSaleSummary();
            setTimeout(() => { listEl.style.display = 'none'; }, 120);
        });

        priceEl.addEventListener('input', function() {
            this.dataset.autofill = this.value ? 'manual' : 'auto';
            if (typeof updateDirectSaleSummary === 'function') updateDirectSaleSummary();
        });

        searchEl.addEventListener('keydown', function(e) {
            let items = listEl.querySelectorAll('.ac-item');
            if (e.key === 'Escape') { listEl.style.display = 'none'; return; }
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                let activeIdx = Array.from(items).findIndex(i => i.classList.contains('ac-active'));
                if(activeIdx > -1) {
                    items[activeIdx].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                } else if (items.length > 0) {
                    items[0].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                } else {
                    const match = findProductMatch(this.value);
                    if (match) applyProductSelection(match, true);
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
                    updateShopIconPC(shopCont, matId, matMatch.needs_buying, matMatch.stock_state || 'none');
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

    function parseDirectSaleNumber(rawValue) {
        if (rawValue === null || rawValue === undefined) return 0;

        const normalized = String(rawValue)
            .replace(/\s+/g, '')
            .replace(/kč/gi, '')
            .replace(',', '.')
            .replace(/[^0-9.-]/g, '');

        const parsed = parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function scheduleDirectSaleSummaryRefresh() {
        updateDirectSaleSummary();

        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(() => updateDirectSaleSummary());
        }

        [80, 260, 800, 1600].forEach(delay => {
            setTimeout(() => updateDirectSaleSummary(), delay);
        });
    }

    function updateDirectSaleSummary() {
        const wrapper = document.getElementById('direct-sale-products-wrapper');
        const subtotalEl = document.getElementById('direct-sale-subtotal');
        const qtyEl = document.getElementById('direct-sale-qty-total');
        const lineEl = document.getElementById('direct-sale-line-count');
        const warningEl = document.getElementById('direct-sale-summary-warning');

        if (!wrapper || !subtotalEl || !qtyEl || !lineEl) return;

        const rows = wrapper.querySelectorAll('.product-row');
        let activeLines = 0;
        let totalQty = 0;
        let subtotal = 0;
        let missingSelection = 0;
        let zeroPrice = 0;

        rows.forEach(row => {
            const hidden = row.querySelector('.product-hidden');
            const search = row.querySelector('.product-search');
            const qtyInput = row.querySelector('.product-amount');
            const priceInput = row.querySelector('.product-price');
            const qtyRaw = String(qtyInput?.value ?? '').trim();
            const qty = Math.max(1, Math.round(parseDirectSaleNumber(qtyRaw || '1') || 1));
            const price = Math.max(0, parseDirectSaleNumber(priceInput?.value || '0'));
            const hasAnyValue = Boolean((search?.value || '').trim()) || Boolean(hidden?.value) || price > 0 || (qtyRaw !== '' && qtyRaw !== '1');

            if (!hasAnyValue) return;

            activeLines++;
            totalQty += qty;
            subtotal += qty * price;

            if (!hidden?.value) missingSelection++;
            if (price <= 0) zeroPrice++;
        });

        lineEl.textContent = String(activeLines);
        qtyEl.textContent = String(totalQty);
        subtotalEl.textContent = `${new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 2 }).format(subtotal)} Kč`;

        if (warningEl) {
            const warnings = [];
            if (missingSelection > 0) warnings.push('Některý řádek ještě nemá vybraný produkt z našeptávače.');
            if (zeroPrice > 0 && activeLines > 0) warnings.push('Vyplňte cenu produktu – až pak se dopočítá mezisoučet.');
            warningEl.style.display = warnings.length ? 'block' : 'none';
            warningEl.textContent = warnings.join(' ');
        }
    }

    function initDirectSaleAutocomplete() {
        const wrapper = document.getElementById('direct-sale-products-wrapper');
        if (!wrapper) return;

        if (wrapper.dataset.summaryBound !== '1') {
            ['input', 'change', 'keyup'].forEach(eventName => {
                wrapper.addEventListener(eventName, function(e) {
                    if (e.target && e.target.closest('.product-row')) {
                        scheduleDirectSaleSummaryRefresh();
                    }
                });
            });

            wrapper.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.btn-remove');
                if (removeBtn && removeBtn.closest('.product-row')) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeProductRow(removeBtn);
                }
            });

            if (!wrapper._summaryObserver && typeof MutationObserver !== 'undefined') {
                const observer = new MutationObserver(() => scheduleDirectSaleSummaryRefresh());
                observer.observe(wrapper, { childList: true, subtree: true });
                wrapper._summaryObserver = observer;
            }

            if (!wrapper._summaryInterval && typeof window !== 'undefined') {
                wrapper._summaryInterval = window.setInterval(() => {
                    if (document.body.contains(wrapper)) updateDirectSaleSummary();
                }, 1000);
            }

            window.addEventListener('pageshow', scheduleDirectSaleSummaryRefresh);
            window.addEventListener('load', scheduleDirectSaleSummaryRefresh);
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) scheduleDirectSaleSummaryRefresh();
            });

            wrapper.dataset.summaryBound = '1';
        }

        const rows = wrapper.querySelectorAll('.product-row');
        rows.forEach(row => setupProductRow(row, 'direct-sale-products-wrapper'));

        if (rows.length === 0) {
            setTimeout(() => {
                pridatProduktRow('direct-sale-products-wrapper');
                scheduleDirectSaleSummaryRefresh();
            }, 0);
            return;
        }

        [0, 120, 400, 900, 1800].forEach(delay => {
            setTimeout(() => scheduleDirectSaleSummaryRefresh(), delay);
        });
    }

    function initDirectSaleFormGuards() {
        const form = document.getElementById('direct-sale-form');
        if (!form || form.dataset.guardsBound === '1') return;

        form.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;

            const target = e.target;
            const tagName = target?.tagName?.toLowerCase();
            const inputType = target?.type?.toLowerCase();
            const isProductSearch = target?.classList?.contains('product-search');
            const isProductAmount = target?.classList?.contains('product-amount');

            if (tagName === 'textarea' || tagName === 'button' || inputType === 'submit' || isProductSearch || isProductAmount) {
                return;
            }

            e.preventDefault();
        });

        form.addEventListener('submit', async function(e) {
            if (form.dataset.confirmedSubmit === '1') {
                form.dataset.confirmedSubmit = '0';
                return;
            }

            e.preventDefault();

            if (typeof window.refreshDirectSaleBoxes === 'function') {
                window.refreshDirectSaleBoxes();
            } else if (typeof scheduleDirectSaleSummaryRefresh === 'function') {
                scheduleDirectSaleSummaryRefresh();
            }

            const items = parseInt(document.getElementById('direct-sale-line-count')?.textContent || '0', 10) || 0;
            const qty = parseInt(document.getElementById('direct-sale-qty-total')?.textContent || '0', 10) || 0;
            const subtotal = (document.getElementById('direct-sale-subtotal')?.textContent || '0 Kč').trim();

            const confirmed = await openActionDialog({
                title: 'Uložit rychlý prodej?',
                message: items > 0
                    ? `Do tržeb se uloží ${items} položek, ${qty} ks za ${subtotal}.`
                    : 'Opravdu chcete uložit rychlý prodej do tržeb?',
                confirmText: 'Uložit prodej',
                cancelText: 'Zpět',
                variant: 'primary',
                showCancel: true
            });

            if (!confirmed) return;

            form.dataset.confirmedSubmit = '1';
            form.submit();
        });

        form.dataset.guardsBound = '1';
    }

    function scrollDirectSaleRowIntoView(rowEl) {
        if (!rowEl) return;

        const salesBox = document.getElementById('direct-sales-box');
        const contentEl = salesBox?.querySelector('.karta-content');
        const stickyStatsEl = salesBox?.querySelector('.direct-sales-stats-grid');
        const scrollHost = contentEl || salesBox;

        if (scrollHost && typeof scrollHost.scrollTo === 'function') {
            const hostRect = scrollHost.getBoundingClientRect();
            const rowRect = rowEl.getBoundingClientRect();
            const stickyOffset = Math.min((stickyStatsEl?.offsetHeight || 0), 110) + 16;
            const nextTop = scrollHost.scrollTop + (rowRect.top - hostRect.top) - stickyOffset;

            scrollHost.scrollTo({
                top: Math.max(0, nextTop),
                behavior: 'smooth'
            });
        }

        rowEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }

    function removeProductRow(btn) {
        const row = btn?.closest('.product-row');
        const wrapper = row?.parentElement;
        if (!row || !wrapper) return;

        const wasOnlyRow = wrapper.id === 'direct-sale-products-wrapper' && wrapper.children.length === 1;
        row.remove();

        if (wasOnlyRow) {
            pridatProduktRow('direct-sale-products-wrapper');
            return;
        }

        scheduleDirectSaleSummaryRefresh();
    }

    function setupProductRow(rowEl, wrapperId, productId = '', price = '', amount = 1) {
        if (!rowEl) return null;

        rowEl.style.display = 'flex';
        rowEl.style.alignItems = 'center';
        rowEl.style.gap = '10px';
        rowEl.style.flexWrap = 'nowrap';
        rowEl.style.marginBottom = '10px';

        const hiddenEl = rowEl.querySelector('.product-hidden');
        const searchEl = rowEl.querySelector('.product-search');
        const priceEl = rowEl.querySelector('.product-price');
        const amountEl = rowEl.querySelector('.product-amount');
        const listEl = rowEl.querySelector('.ac-list');
        const removeBtn = rowEl.querySelector('.btn-remove');

        if (!hiddenEl || !searchEl || !priceEl || !amountEl || !listEl) return rowEl;

        if (productId) {
            hiddenEl.value = productId;
            const pMatch = PRODUCTS_DATA.find(p => p.id == productId);
            if (pMatch) searchEl.value = getProductMeta(pMatch).inputValue;
            priceEl.value = price;
            amountEl.value = amount;
        }

        if (rowEl.dataset.initialized !== '1') {
            odeslatDoNaseptavaceProduktu(searchEl, hiddenEl, listEl, priceEl, amountEl);
            amountEl.addEventListener('input', scheduleDirectSaleSummaryRefresh);
            amountEl.addEventListener('change', scheduleDirectSaleSummaryRefresh);
            priceEl.addEventListener('input', scheduleDirectSaleSummaryRefresh);
            priceEl.addEventListener('change', scheduleDirectSaleSummaryRefresh);

            amountEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    pridatProduktRow(wrapperId);
                    setTimeout(() => {
                        const last = document.getElementById(wrapperId)?.lastElementChild;
                        if (last) {
                            scrollDirectSaleRowIntoView(last);
                            const s = last.querySelector('.product-search');
                            if (s) s.focus({ preventScroll: true });
                        }
                    }, 50);
                }
            });

            if (removeBtn) {
                removeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeProductRow(this);
                });
            }

            rowEl.dataset.initialized = '1';
        }

        return rowEl;
    }

    function pridatProduktRow(wrapperId, productId = '', price = '', amount = 1) {
        const wrapper = document.getElementById(wrapperId);
        if (!wrapper) return;

        const tpl = document.getElementById('product-row-template').content.cloneNode(true);
        wrapper.appendChild(tpl);

        const lastRow = wrapper.lastElementChild;
        setupProductRow(lastRow, wrapperId, productId, price, amount);
        scheduleDirectSaleSummaryRefresh();

        if (!productId) {
            setTimeout(() => {
                scrollDirectSaleRowIntoView(lastRow);
                const s = lastRow?.querySelector('.product-search');
                if (s) s.focus({preventScroll: true});
            }, 100);
        }

        lucide.createIcons();
    }
    
    if (typeof window !== 'undefined') {
        window.updateDirectSaleSummary = updateDirectSaleSummary;
        window.scheduleDirectSaleSummaryRefresh = scheduleDirectSaleSummaryRefresh;
        window.removeProductRow = removeProductRow;
        window.pridatProduktRow = pridatProduktRow;
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
                throw new Error(data.error || 'Oblíbeného klienta se nepodařilo upravit.');
            }

            window.location.href = 'index.php?client_id=' + clientId;
        } catch (err) {
            console.error(err);
            await openActionDialog({
                title: 'Změna se nepodařila',
                message: err && err.message ? err.message : 'Oblíbeného klienta se nepodařilo upravit.',
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
                ? 'Klient se skryje z běžného seznamu, ale zůstane dostupný ve filtru Neaktivní.'
                : 'Klient se znovu ukáže v hlavním seznamu a bude běžně dostupný.',
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
                message: err && err.message ? err.message : 'Změna stavu klienta se nepodařila.',
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

    let catalogFilterMode = 'all';
    let catalogScanTarget = null;
    let catalogScannerInitialized = false;
    let catalogScanBuffer = '';
    let catalogScanTimer = null;
    let catalogLastKeyTime = 0;
    let catalogReceiveMode = false;
    let catalogBatchMode = false;
    let catalogBatchQueue = [];
    let catalogReceiptLog = Array.isArray(window.RECENT_RECEIPTS_DATA) ? [...window.RECENT_RECEIPTS_DATA] : [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeCatalogEan(value = '') {
        return String(value || '').replace(/[^0-9A-Za-z]/g, '').trim();
    }

    function getCatalogItems() {
        return Array.isArray(window.CATALOG_DATA) ? window.CATALOG_DATA : [];
    }

    function formatCatalogReceiptTime(value = '') {
        if (!value) return '';
        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return String(value);
        return parsed.toLocaleString('cs-CZ', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function getCatalogDefaultStatusMessage() {
        if (catalogBatchMode) {
            return 'Dávková příjemka je <b>aktivní</b>. Pípejte více položek a pak klikněte na <b>Uložit příjemku</b>.';
        }
        if (catalogReceiveMode) {
            return 'Režim příjmu je <b>zapnutý</b>. Stačí pípat známé EAN a příjem se rovnou uloží.';
        }
        return 'Čtečka je připravená. Vyberte položku a klikněte na <b>Načíst EAN</b>.';
    }

    function updateCatalogScanStatus(message, type = 'neutral') {
        const el = document.getElementById('catalog-scan-status');
        if (!el) return;

        const palette = {
            neutral: { bg: '#f8fafc', border: '#e2e8f0', color: '#334155' },
            success: { bg: '#ecfdf5', border: '#a7f3d0', color: '#065f46' },
            warning: { bg: '#fff7ed', border: '#fdba74', color: '#9a3412' },
            danger: { bg: '#fff1f2', border: '#fecdd3', color: '#be123c' }
        };
        const current = palette[type] || palette.neutral;
        el.style.background = current.bg;
        el.style.border = `1px solid ${current.border}`;
        el.style.color = current.color;
        el.innerHTML = message;
    }

    function updateCatalogActiveTargetLabel() {
        const el = document.getElementById('catalog-active-target');
        if (!el) return;

        if (!catalogScanTarget) {
            el.innerHTML = catalogReceiveMode
                ? 'Režim příjmu je <b>aktivní</b>. Napípejte známý EAN nebo klikněte u položky na <b>Příjem</b>.'
                : 'Není vybraná žádná položka.';
            return;
        }

        el.innerHTML = `Aktivní párování: <b>${escapeHtml(catalogScanTarget.label || 'Vybraná položka')}</b>. Stačí teď rovnou napípat EAN.`;
    }

    function updateCatalogReceiveModeUi() {
        const statusEl = document.getElementById('catalog-receive-mode-status');
        const toggleBtn = document.getElementById('catalog-receive-toggle-btn');
        const batchBtn = document.getElementById('catalog-batch-toggle-btn');
        const batchPanel = document.getElementById('catalog-batch-panel');

        if (statusEl) {
            const isAnyModeActive = catalogReceiveMode || catalogBatchMode;
            statusEl.style.background = isAnyModeActive ? '#ecfdf5' : '#f8fafc';
            statusEl.style.border = `1px solid ${isAnyModeActive ? '#a7f3d0' : '#e2e8f0'}`;
            statusEl.style.color = isAnyModeActive ? '#065f46' : '#334155';
            statusEl.innerHTML = catalogBatchMode
                ? 'Dávková příjemka je <b>zapnutá</b>. Pípejte položky do jednoho seznamu.'
                : (catalogReceiveMode
                    ? 'Příjem je <b>zapnutý</b>. Napípáním známého EAN se rovnou uloží přijaté množství.'
                    : 'Režim příjmu je vypnutý.');
        }

        if (toggleBtn) {
            toggleBtn.textContent = catalogReceiveMode ? 'Příjem zapnutý' : 'Zapnout příjem';
            toggleBtn.style.background = catalogReceiveMode ? '#0f766e' : '';
            toggleBtn.style.borderColor = catalogReceiveMode ? '#0f766e' : '';
        }

        if (batchBtn) {
            batchBtn.textContent = catalogBatchMode ? 'Dávka aktivní' : 'Dávková příjemka';
            batchBtn.style.background = catalogBatchMode ? '#2563eb' : '';
            batchBtn.style.borderColor = catalogBatchMode ? '#2563eb' : '';
            batchBtn.style.color = catalogBatchMode ? '#fff' : '';
        }

        if (batchPanel) {
            batchPanel.style.display = catalogBatchMode ? 'block' : 'none';
        }
    }

    function renderCatalogBatchQueue() {
        const listEl = document.getElementById('catalog-batch-list');
        const statusEl = document.getElementById('catalog-batch-status');
        if (!listEl || !statusEl) return;

        if (!catalogBatchMode) {
            statusEl.innerHTML = 'Dávkový režim je vypnutý.';
            listEl.innerHTML = '';
            return;
        }

        if (!catalogBatchQueue.length) {
            statusEl.innerHTML = 'Dávková příjemka je aktivní. Začněte pípat položky.';
            listEl.innerHTML = '<div style="padding:10px 12px; border-radius:12px; background:#f8fafc; border:1px dashed #cbd5e1; color:#64748b; font-size:12px;">Seznam je zatím prázdný.</div>';
            return;
        }

        const totalQty = catalogBatchQueue.reduce((sum, row) => sum + Number(row.qty || 0), 0);
        statusEl.innerHTML = `V dávce je <b>${catalogBatchQueue.length}</b> položek / <b>${totalQty}</b> ks.`;
        listEl.innerHTML = catalogBatchQueue.map((row, index) => `
            <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 12px; border-radius:12px; background:#f8fafc; border:1px solid #dbeafe;">
                <div style="min-width:0;">
                    <div style="font-size:12px; font-weight:800; color:#0f172a;">${escapeHtml(row.item_label || 'Položka')}</div>
                    <div style="font-size:11px; color:#64748b; margin-top:2px;">${escapeHtml(row.item_type === 'material' ? 'Materiál' : 'Produkt')} • ${escapeHtml(row.scanned_ean || '')}</div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:12px; font-weight:800; color:#2563eb;">× ${escapeHtml(String(row.qty || 1))}</span>
                    <button type="button" class="btn-menu" onclick="removeCatalogBatchItem(${index})" style="padding:8px 10px; border-radius:10px;" title="Odebrat"><i data-lucide="x" style="width:14px; height:14px;"></i></button>
                </div>
            </div>
        `).join('');
        lucide.createIcons();
    }

    function clearCatalogBatchQueue() {
        catalogBatchQueue = [];
        renderCatalogBatchQueue();
        updateCatalogScanStatus(getCatalogDefaultStatusMessage(), catalogBatchMode ? 'success' : 'neutral');
    }

    function removeCatalogBatchItem(index) {
        catalogBatchQueue.splice(index, 1);
        renderCatalogBatchQueue();
    }

    function addCatalogBatchItem(item, options = {}) {
        if (!item) return;
        const payload = {
            ...getCatalogReceivePayload(),
            ...options
        };
        const qty = Math.max(1, Math.min(99, parseInt(payload.qty ?? 1, 10) || 1));
        const note = String(payload.note || '').trim().slice(0, 255);
        const scannedEan = normalizeCatalogEan(payload.scannedEan || item.ean || '');
        const existing = catalogBatchQueue.find(row => row.item_type === item.type && String(row.item_id) === String(item.id) && String(row.note || '') === note);

        if (existing) {
            existing.qty += qty;
            if (scannedEan) existing.scanned_ean = scannedEan;
        } else {
            catalogBatchQueue.push({
                item_type: item.type,
                item_id: item.id,
                item_label: `${item.brand || ''} ${item.group ? item.group + ' ' : ''}${item.name || ''}`.trim(),
                qty,
                note,
                scanned_ean: scannedEan
            });
        }

        renderCatalogBatchQueue();
        updateCatalogScanStatus(`Přidáno do dávky: <b>${escapeHtml(item.name || '')}</b> × ${escapeHtml(String(qty))}.`, 'success');
        focusCatalogRow(item.type, item.id);
        focusCatalogScannerCapture(true);
    }

    async function saveCatalogBatchReceipt() {
        if (!catalogBatchQueue.length) {
            updateCatalogScanStatus('Dávková příjemka je zatím prázdná.', 'warning');
            return;
        }

        const batchCode = `PRJ-${new Date().toISOString().slice(0,19).replace(/[-:T]/g, '').slice(0,14)}`;
        try {
            const formData = new FormData();
            formData.append('action', 'receive_batch');
            formData.append('batch_code', batchCode);
            formData.append('items_json', JSON.stringify(catalogBatchQueue));
            formData.append('csrf_token', getCsrfToken());

            const resp = await fetch('api_catalog.php', { method: 'POST', body: formData });
            const json = await resp.json();
            if (!json.success) throw new Error(json.error || 'Dávkovou příjemku se nepodařilo uložit.');

            catalogBatchQueue.forEach(row => {
                if (row.item_type === 'material') {
                    const item = getCatalogItems().find(entry => entry.type === 'material' && String(entry.id) === String(row.item_id));
                    if (item) {
                        item.stock_state = 'none';
                        item.needs_buying = 0;
                    }
                    const mat = MATERIALS_DATA.find(entry => String(entry.id) === String(row.item_id));
                    if (mat) {
                        mat.stock_state = 'none';
                        mat.needs_buying = 0;
                    }
                    removeShoppingRow(row.item_id);
                }
            });

            if (Array.isArray(json.receipts)) {
                catalogReceiptLog = [...json.receipts.slice().reverse(), ...catalogReceiptLog].slice(0, 8);
                renderCatalogReceiptLog();
            }

            refreshShoppingUi(json.list_count, json.total_qty);
            refreshMaterialStateUi(json.opened_count, json.low_count);
            renderCatalogList();
            catalogBatchQueue = [];
            renderCatalogBatchQueue();
            updateCatalogScanStatus(`Dávková příjemka <b>${escapeHtml(json.batch_code || batchCode)}</b> byla uložena.`, 'success');
            focusCatalogScannerCapture(true);
        } catch (err) {
            console.error(err);
            updateCatalogScanStatus(err?.message || 'Dávkovou příjemku se nepodařilo uložit.', 'danger');
        }
    }

    function toggleCatalogBatchMode(forceState) {
        const nextState = typeof forceState === 'boolean' ? forceState : !catalogBatchMode;
        catalogBatchMode = nextState;
        if (catalogBatchMode) {
            catalogReceiveMode = false;
            catalogScanTarget = null;
        }
        updateCatalogActiveTargetLabel();
        updateCatalogReceiveModeUi();
        renderCatalogBatchQueue();
        updateCatalogScanStatus(getCatalogDefaultStatusMessage(), catalogBatchMode ? 'success' : (catalogReceiveMode ? 'success' : 'neutral'));
        focusCatalogScannerCapture(true);
    }

    function focusCatalogScannerCapture(force = false) {
        const input = document.getElementById('catalog-scanner-capture');
        const view = document.getElementById('set-view-catalog');
        if (!input || !view || view.style.display === 'none') return;

        const active = document.activeElement;
        const isTypingElsewhere = active
            && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)
            && active !== input
            && active.id !== 'catalog-search-input';

        if (isTypingElsewhere && !force) return;
        input.focus({ preventScroll: true });
    }

    function setCatalogFilter(filter, btn) {
        catalogFilterMode = filter || 'all';
        document.querySelectorAll('.catalog-filter-btn').forEach(chip => chip.classList.remove('active'));
        if (btn) btn.classList.add('active');
        renderCatalogList();
        focusCatalogScannerCapture();
    }

    function renderCatalogReceiptLog() {
        const logEl = document.getElementById('catalog-receipt-log');
        if (!logEl) return;

        const entries = Array.isArray(catalogReceiptLog) ? catalogReceiptLog.slice(0, 8) : [];
        if (!entries.length) {
            logEl.innerHTML = '<div style="padding:12px 14px; border-radius:12px; background:#f8fafc; border:1px dashed #e2e8f0; color:#64748b; font-size:12px;">Zatím tu není žádný zaznamenaný příjem.</div>';
            return;
        }

        logEl.innerHTML = entries.map(entry => {
            const isMaterial = entry.item_type === 'material';
            const typeBadge = isMaterial
                ? '<span style="background:#fff8e6; border:1px solid rgba(212,175,55,0.28); color:#8a6b15; padding:3px 7px; border-radius:999px; font-size:10px; font-weight:800; text-transform:uppercase;">Materiál</span>'
                : '<span style="background:#eef2ff; border:1px solid #c7d2fe; color:#4338ca; padding:3px 7px; border-radius:999px; font-size:10px; font-weight:800; text-transform:uppercase;">Produkt</span>';
            const batchBadge = entry.batch_code
                ? `<span style="font-size:10px; color:#2563eb; font-weight:800;">${escapeHtml(entry.batch_code)}</span>`
                : '';
            const note = entry.note
                ? `<div style="font-size:11px; color:#475569; margin-top:4px;">${escapeHtml(entry.note)}</div>`
                : '';

            return `
                <div style="padding:10px 12px; border-radius:12px; background:#fff; border:1px solid #e2e8f0;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                        <div style="min-width:0;">
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-bottom:4px;">
                                ${typeBadge}
                                ${batchBadge}
                                <span style="font-size:11px; color:#64748b; font-weight:700;">× ${escapeHtml(String(entry.qty || 1))}</span>
                            </div>
                            <div style="font-size:12px; font-weight:800; color:#0f172a; line-height:1.4;">${escapeHtml(entry.item_label || 'Položka')}</div>
                            ${note}
                        </div>
                        <div style="font-size:11px; color:#64748b; white-space:nowrap;">${escapeHtml(formatCatalogReceiptTime(entry.received_at || ''))}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function toggleCatalogReceiveMode(forceState) {
        const nextState = typeof forceState === 'boolean' ? forceState : !catalogReceiveMode;
        catalogReceiveMode = nextState;

        if (catalogReceiveMode) {
            catalogBatchMode = false;
            catalogScanTarget = null;
            catalogScanBuffer = '';
            clearTimeout(catalogScanTimer);
        }

        updateCatalogActiveTargetLabel();
        updateCatalogReceiveModeUi();
        renderCatalogBatchQueue();
        updateCatalogScanStatus(getCatalogDefaultStatusMessage(), (catalogReceiveMode || catalogBatchMode) ? 'success' : 'neutral');
        focusCatalogScannerCapture(true);
    }

    function getCatalogReceivePayload() {
        const qtyInput = document.getElementById('catalog-receive-qty');
        const noteInput = document.getElementById('catalog-receive-note');
        const qty = Math.max(1, Math.min(99, parseInt(qtyInput?.value || '1', 10) || 1));
        if (qtyInput) qtyInput.value = String(qty);
        return {
            qty,
            note: String(noteInput?.value || '').trim()
        };
    }

    function armCatalogScan(itemType, itemId) {
        const item = getCatalogItems().find(entry => entry.type === itemType && String(entry.id) === String(itemId));
        if (!item) return;

        catalogReceiveMode = false;
        updateCatalogReceiveModeUi();
        catalogScanBuffer = '';
        clearTimeout(catalogScanTimer);
        catalogScanTarget = {
            type: itemType,
            id: itemId,
            label: `${item.brand || ''} ${item.name || ''}`.trim()
        };
        updateCatalogActiveTargetLabel();
        updateCatalogScanStatus(`Čtečka je připravená pro položku <b>${escapeHtml(catalogScanTarget.label)}</b>. Napípejte EAN.`, 'warning');
        focusCatalogScannerCapture(true);
    }

    function clearCatalogScanTarget() {
        catalogScanBuffer = '';
        clearTimeout(catalogScanTimer);
        catalogScanTarget = null;
        updateCatalogActiveTargetLabel();
        updateCatalogScanStatus(getCatalogDefaultStatusMessage(), catalogReceiveMode ? 'success' : 'neutral');
        focusCatalogScannerCapture(true);
    }

    function focusCatalogRow(itemType, itemId) {
        const row = document.querySelector(`.catalog-row[data-item-type="${itemType}"][data-item-id="${itemId}"]`);
        if (!row) return;
        row.style.boxShadow = '0 0 0 2px rgba(212, 175, 55, 0.35)';
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => { row.style.boxShadow = ''; }, 1600);
    }

    async function saveCatalogEan(itemType, itemId, ean = '') {
        const normalized = normalizeCatalogEan(ean);

        try {
            const formData = new FormData();
            formData.append('action', 'save_ean');
            formData.append('item_type', itemType);
            formData.append('item_id', String(itemId));
            formData.append('ean', normalized);
            formData.append('csrf_token', getCsrfToken());

            const resp = await fetch('api_catalog.php', { method: 'POST', body: formData });
            const json = await resp.json();
            if (!json.success) throw new Error(json.error || 'EAN se nepodařilo uložit.');

            const item = getCatalogItems().find(entry => entry.type === itemType && String(entry.id) === String(itemId));
            if (item) item.ean = json.ean || '';

            catalogScanTarget = null;
            updateCatalogActiveTargetLabel();
            renderCatalogList();
            focusCatalogRow(itemType, itemId);
            updateCatalogScanStatus(
                json.ean
                    ? `EAN <b>${escapeHtml(json.ean)}</b> byl uložen k položce <b>${escapeHtml(json.label || '')}</b>.`
                    : `EAN byl u položky <b>${escapeHtml(json.label || '')}</b> vymazán.`,
                'success'
            );
            focusCatalogScannerCapture(true);
        } catch (err) {
            console.error(err);
            updateCatalogScanStatus(err?.message || 'Uložení EAN se nepodařilo.', 'danger');
        }
    }

    async function receiveCatalogItem(itemType, itemId, options = {}) {
        const payload = {
            ...getCatalogReceivePayload(),
            ...options
        };
        const qty = Math.max(1, Math.min(99, parseInt(payload.qty ?? 1, 10) || 1));
        const note = String(payload.note || '').trim().slice(0, 255);
        const scannedEan = normalizeCatalogEan(payload.scannedEan || '');

        try {
            const formData = new FormData();
            formData.append('action', 'receive_item');
            formData.append('item_type', itemType);
            formData.append('item_id', String(itemId));
            formData.append('quantity', String(qty));
            formData.append('note', note);
            formData.append('scanned_ean', scannedEan);
            formData.append('csrf_token', getCsrfToken());

            const resp = await fetch('api_catalog.php', { method: 'POST', body: formData });
            const json = await resp.json();
            if (!json.success) throw new Error(json.error || 'Příjem se nepodařilo uložit.');

            const item = getCatalogItems().find(entry => entry.type === itemType && String(entry.id) === String(itemId));
            if (item && itemType === 'material') {
                item.stock_state = json.stock_state || 'none';
                item.needs_buying = Number(json.new_status || 0);
            }

            const mat = itemType === 'material' ? MATERIALS_DATA.find(entry => String(entry.id) === String(itemId)) : null;
            if (mat) {
                mat.stock_state = json.stock_state || 'none';
                mat.needs_buying = Number(json.new_status || 0);
            }

            if (itemType === 'material') {
                removeShoppingRow(itemId);
            }

            if (typeof json.list_count !== 'undefined' && typeof json.total_qty !== 'undefined') {
                refreshShoppingUi(json.list_count, json.total_qty);
            }
            if (typeof json.opened_count !== 'undefined' && typeof json.low_count !== 'undefined') {
                refreshMaterialStateUi(json.opened_count, json.low_count);
            }

            if (json.receipt) {
                catalogReceiptLog.unshift(json.receipt);
                catalogReceiptLog = catalogReceiptLog.slice(0, 8);
                renderCatalogReceiptLog();
            }

            renderCatalogList();
            focusCatalogRow(itemType, itemId);
            updateCatalogScanStatus(
                `Příjem uložen: <b>${escapeHtml(json.label || '')}</b> × ${escapeHtml(String(json.qty || qty))}.${itemType === 'material' ? ' Materiál byl zároveň stažený z nákupu.' : ''}`,
                'success'
            );
            focusCatalogScannerCapture(true);
        } catch (err) {
            console.error(err);
            updateCatalogScanStatus(err?.message || 'Příjem se nepodařilo uložit.', 'danger');
        }
    }

    function handleCatalogScanValue(rawValue) {
        const normalized = normalizeCatalogEan(rawValue);
        if (normalized.length < 6) return;

        if (catalogScanTarget) {
            saveCatalogEan(catalogScanTarget.type, catalogScanTarget.id, normalized);
            return;
        }

        const found = getCatalogItems().find(item => normalizeCatalogEan(item.ean) === normalized);

        if (catalogBatchMode) {
            if (found) {
                const payload = getCatalogReceivePayload();
                addCatalogBatchItem(found, {
                    qty: payload.qty,
                    note: payload.note,
                    scannedEan: normalized
                });
            } else {
                updateCatalogScanStatus(`EAN <b>${escapeHtml(normalized)}</b> v katalogu ještě není. Nejdřív ho prosím spárujte přes <b>Načíst EAN</b>.`, 'warning');
            }
            return;
        }

        if (catalogReceiveMode) {
            if (found) {
                const payload = getCatalogReceivePayload();
                receiveCatalogItem(found.type, found.id, {
                    qty: payload.qty,
                    note: payload.note,
                    scannedEan: normalized
                });
            } else {
                updateCatalogScanStatus(`EAN <b>${escapeHtml(normalized)}</b> v katalogu ještě není. Nejdřív ho prosím spárujte přes <b>Načíst EAN</b>.`, 'warning');
            }
            return;
        }

        const searchInput = document.getElementById('catalog-search-input');
        if (searchInput) {
            searchInput.value = normalized;
        }
        renderCatalogList();

        if (found) {
            focusCatalogRow(found.type, found.id);
            updateCatalogScanStatus(`EAN <b>${escapeHtml(normalized)}</b> už patří k položce <b>${escapeHtml((found.brand || '') + ' ' + (found.name || ''))}</b>.`, 'success');
        } else {
            updateCatalogScanStatus(`EAN <b>${escapeHtml(normalized)}</b> zatím není přiřazený. Klikněte u správné položky na <b>Načíst EAN</b> a napípejte ho znovu.`, 'warning');
        }
    }

    function renderCatalogList() {
        const listEl = document.getElementById('catalog-item-list');
        const badgesEl = document.getElementById('catalog-summary-badges');
        if (!listEl || !badgesEl) return;

        const allItems = getCatalogItems();
        const query = normalizeSearchText(document.getElementById('catalog-search-input')?.value || '');
        const totals = {
            all: allItems.length,
            materials: allItems.filter(item => item.type === 'material').length,
            products: allItems.filter(item => item.type === 'product').length,
            missing: allItems.filter(item => !normalizeCatalogEan(item.ean)).length,
            needsBuying: allItems.filter(item => item.type === 'material' && Number(item.needs_buying) === 1).length
        };

        badgesEl.innerHTML = `
            <span style="background:#f8fafc; border:1px solid #e2e8f0; color:#334155; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700;">Celkem ${totals.all}</span>
            <span style="background:#fff8e6; border:1px solid rgba(212,175,55,0.28); color:#8a6b15; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700;">Materiály ${totals.materials}</span>
            <span style="background:#eef2ff; border:1px solid #c7d2fe; color:#4338ca; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700;">Produkty ${totals.products}</span>
            <span style="background:#fff7ed; border:1px solid #fdba74; color:#9a3412; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700;">K nákupu ${totals.needsBuying}</span>
            <span style="background:#fff1f2; border:1px solid #fecdd3; color:#be123c; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700;">Bez EAN ${totals.missing}</span>
        `;

        let items = allItems.filter(item => {
            const haystack = normalizeSearchText([item.brand, item.group, item.name, item.ean].join(' '));
            const matchesQuery = !query || haystack.includes(query);
            if (!matchesQuery) return false;

            if (catalogFilterMode === 'material') return item.type === 'material';
            if (catalogFilterMode === 'product') return item.type === 'product';
            if (catalogFilterMode === 'missing-ean') return !normalizeCatalogEan(item.ean);
            if (catalogFilterMode === 'needs-buying') return item.type === 'material' && Number(item.needs_buying) === 1;
            if (catalogFilterMode === 'low') return item.type === 'material' && (item.stock_state || 'none') === 'low';
            return true;
        });

        items.sort((a, b) => {
            const aMissing = normalizeCatalogEan(a.ean) ? 1 : 0;
            const bMissing = normalizeCatalogEan(b.ean) ? 1 : 0;
            if (aMissing !== bMissing) return aMissing - bMissing;

            const aBuying = a.type === 'material' && Number(a.needs_buying) === 1 ? 0 : 1;
            const bBuying = b.type === 'material' && Number(b.needs_buying) === 1 ? 0 : 1;
            if (aBuying !== bBuying) return aBuying - bBuying;

            if (a.type !== b.type) return a.type === 'material' ? -1 : 1;
            return `${a.brand} ${a.group} ${a.name}`.localeCompare(`${b.brand} ${b.group} ${b.name}`, 'cs');
        });

        if (items.length === 0) {
            listEl.innerHTML = '<div style="padding:28px; text-align:center; color:#64748b; background:#f8fafc; border:1px dashed #e2e8f0; border-radius:16px;">Nic neodpovídá aktuálnímu filtru.</div>';
            return;
        }

        listEl.innerHTML = items.map(item => {
            const ean = normalizeCatalogEan(item.ean);
            const isMaterial = item.type === 'material';
            const needsBuying = isMaterial && Number(item.needs_buying) === 1;
            const meta = isMaterial ? getMaterialStateMeta(item.stock_state || 'none') : null;
            const typeBadge = isMaterial
                ? '<span style="background:#fff8e6; border:1px solid rgba(212,175,55,0.28); color:#8a6b15; padding:4px 8px; border-radius:999px; font-size:10px; font-weight:800; text-transform:uppercase;">Materiál</span>'
                : '<span style="background:#eef2ff; border:1px solid #c7d2fe; color:#4338ca; padding:4px 8px; border-radius:999px; font-size:10px; font-weight:800; text-transform:uppercase;">Produkt</span>';
            const inactiveBadge = Number(item.is_active) === 0
                ? '<span style="background:#f1f5f9; border:1px solid #cbd5e1; color:#64748b; padding:4px 8px; border-radius:999px; font-size:10px; font-weight:800; text-transform:uppercase;">Skryté</span>'
                : '';
            const shoppingBadge = needsBuying
                ? '<span style="background:#fff7ed; border:1px solid #fdba74; color:#9a3412; padding:4px 8px; border-radius:999px; font-size:10px; font-weight:800; text-transform:uppercase;">K nákupu</span>'
                : '';
            const eanBadge = ean
                ? `<span style="display:inline-flex; align-items:center; gap:6px; padding:8px 10px; border-radius:10px; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; font-size:12px; font-weight:800;">${escapeHtml(ean)}</span>`
                : '<span style="display:inline-flex; align-items:center; gap:6px; padding:8px 10px; border-radius:10px; background:#fff1f2; border:1px solid #fecdd3; color:#be123c; font-size:12px; font-weight:800;">Bez EAN</span>';
            const stateButton = isMaterial
                ? `<button type="button" class="btn-state-pc state-${meta.key}" data-material-id="${item.id}" data-material-state="${meta.key}" onclick="cycleMaterialState(${item.id}, this)" title="${meta.title}">${meta.short}</button>`
                : '';
            const secondaryLine = isMaterial
                ? `${escapeHtml(item.brand || '')} • ${escapeHtml(item.group || '')}${item.use_count ? ` • použito ${item.use_count}×` : ''}`
                : `${escapeHtml(item.brand || '')}${item.price ? ` • ${escapeHtml(String(item.price))} Kč` : ''}${item.use_count ? ` • prodáno ${item.use_count}×` : ''}`;
            const receiveButton = `<button type="button" class="btn-menu" onclick="receiveCatalogItem('${item.type}', ${item.id})" style="padding:10px 12px; border-radius:10px; display:inline-flex; align-items:center; gap:6px; color:#0f766e; border-color:#99f6e4; background:#f0fdfa;" title="Zapsat příjem"><i data-lucide="package-check" style="width:16px; height:16px;"></i> Příjem</button>`;

            return `
                <div class="catalog-row" data-item-type="${item.type}" data-item-id="${item.id}" style="display:flex; justify-content:space-between; align-items:center; gap:16px; padding:14px 16px; border-radius:16px; background:${!ean ? '#fffaf5' : (needsBuying ? '#fffbeb' : '#ffffff')}; border:1px solid ${!ean ? '#fed7aa' : (needsBuying ? '#fde68a' : '#e2e8f0')}; transition:0.2s;">
                    <div style="min-width:0; display:flex; flex-direction:column; gap:6px;">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            ${typeBadge}
                            ${inactiveBadge}
                            ${shoppingBadge}
                            ${stateButton}
                        </div>
                        <div style="font-size:15px; font-weight:800; color:#0f172a;">${escapeHtml(item.name || '')}</div>
                        <div style="font-size:12px; color:#64748b;">${secondaryLine}</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
                        ${eanBadge}
                        ${receiveButton}
                        <button type="button" class="btn-ulozit" onclick="armCatalogScan('${item.type}', ${item.id})" style="margin:0; width:auto; padding:10px 14px;">
                            <i data-lucide="scan-line" style="width:16px; height:16px;"></i> Načíst EAN
                        </button>
                        ${ean ? `<button type="button" class="btn-menu" onclick="saveCatalogEan('${item.type}', ${item.id}, '')" style="padding:10px 12px; border-radius:10px;" title="Vymazat EAN"><i data-lucide="x" style="width:14px; height:14px;"></i></button>` : ''}
                    </div>
                </div>
            `;
        }).join('');

        lucide.createIcons();
    }

    function initCatalogManager() {
        if (catalogScannerInitialized) return;
        catalogScannerInitialized = true;
        updateCatalogActiveTargetLabel();
        updateCatalogReceiveModeUi();
        renderCatalogReceiptLog();
        updateCatalogScanStatus(getCatalogDefaultStatusMessage(), 'neutral');

        document.addEventListener('click', function(e) {
            const view = document.getElementById('set-view-catalog');
            if (!view || view.style.display === 'none') return;

            const clickedTextField = e.target.closest('input, textarea, select');
            if (!clickedTextField) {
                setTimeout(() => focusCatalogScannerCapture(), 40);
            }
        });

        document.addEventListener('keydown', function(e) {
            const view = document.getElementById('set-view-catalog');
            if (!view || view.style.display === 'none') return;
            if (e.ctrlKey || e.metaKey || e.altKey) return;

            if (e.key === 'Escape') {
                clearCatalogScanTarget();
                return;
            }

            if (e.key === 'Enter') {
                const scanValue = catalogScanBuffer;
                catalogScanBuffer = '';
                clearTimeout(catalogScanTimer);
                if (normalizeCatalogEan(scanValue).length >= 6) {
                    e.preventDefault();
                    handleCatalogScanValue(scanValue);
                }
                return;
            }

            if (e.key.length !== 1) return;

            const now = Date.now();
            if ((now - catalogLastKeyTime) > 120) {
                catalogScanBuffer = '';
            }
            catalogLastKeyTime = now;
            catalogScanBuffer += e.key;

            if (catalogScanTarget) {
                e.preventDefault();
            }

            clearTimeout(catalogScanTimer);
            catalogScanTimer = setTimeout(() => {
                const scanValue = catalogScanBuffer;
                catalogScanBuffer = '';
                if (normalizeCatalogEan(scanValue).length >= 8) {
                    handleCatalogScanValue(scanValue);
                }
            }, 90);
        }, true);
    }


    function refreshShoppingUi(listCount = 0, totalQty = 0) {
        const safeCount = Math.max(0, parseInt(listCount, 10) || 0);
        const safeQty = Math.max(0, parseInt(totalQty, 10) || 0);
        const navTab = document.getElementById('acc-btn-nakup');
        const counterVal = document.getElementById('shopping-counter-val');
        const counterQty = document.getElementById('shopping-counter-qty');
        const counterBox = document.getElementById('shopping-counter-box');
        const emptyState = document.getElementById('shopping-empty-state');
        let badge = document.getElementById('shopping-badge-count');

        if (safeCount > 0) {
            if (badge) {
                badge.textContent = String(safeCount);
            } else if (navTab) {
                badge = document.createElement('span');
                badge.id = 'shopping-badge-count';
                badge.style = 'background:#ef4444; color:#fff; font-size:10px; padding:2px 6px; border-radius:10px; margin-left:5px;';
                badge.textContent = String(safeCount);
                navTab.appendChild(badge);
            }
        } else if (badge) {
            badge.remove();
        }

        if (counterVal) counterVal.textContent = String(safeCount);
        if (counterQty) counterQty.textContent = String(safeQty);
        if (counterBox) counterBox.style.display = safeCount > 0 ? 'flex' : 'none';
        if (emptyState) emptyState.style.display = safeCount > 0 ? 'none' : 'block';
    }

    function refreshMaterialStateUi(openedCount = 0, lowCount = 0) {
        const safeOpened = Math.max(0, parseInt(openedCount, 10) || 0);
        const safeLow = Math.max(0, parseInt(lowCount, 10) || 0);
        const openedEl = document.getElementById('material-opened-count');
        const lowEl = document.getElementById('material-low-count');
        const stateNote = document.getElementById('material-state-note');
        const emptyTitle = document.getElementById('shopping-empty-title');
        const emptyText = document.getElementById('shopping-empty-text');
        const emptyIconWrap = document.getElementById('shopping-empty-icon-wrap');
        const emptyIcon = document.getElementById('shopping-empty-icon');
        const hasStateAlert = safeOpened > 0 || safeLow > 0;

        if (openedEl) openedEl.textContent = String(safeOpened);
        if (lowEl) lowEl.textContent = String(safeLow);
        if (stateNote) stateNote.style.display = hasStateAlert ? 'block' : 'none';

        if (emptyTitle) {
            emptyTitle.textContent = hasStateAlert ? 'Košík je prázdný' : 'Všechno máme!';
        }
        if (emptyText) {
            emptyText.textContent = hasStateAlert
                ? 'Pokud něco označíš jako „Dochází“, objeví se to tady automaticky.'
                : 'Nákupní lístek je momentálně prázdný.';
        }
        if (emptyIconWrap) {
            emptyIconWrap.style.background = hasStateAlert ? '#fff7ed' : '#f1f5f9';
        }
        if (emptyIcon) {
            emptyIcon.style.color = hasStateAlert ? '#f59e0b' : '#10b981';
        }
    }

    function upsertShoppingRow(id, shoppingQty = 1) {
        const rowsWrap = document.getElementById('shopping-list-rows');
        if (!rowsWrap || !Array.isArray(MATERIALS_DATA)) return;

        const mat = MATERIALS_DATA.find(m => m.id == id);
        if (!mat) return;

        const safeQty = Math.max(1, parseInt(shoppingQty, 10) || 1);
        const rowHtml = `
            <div class="acc-row-v2 shopping-row" data-id="${id}" data-qty="${safeQty}" style="padding:18px 25px;">
                <div class="row-avatar" style="background:#fff7ed; color:#f97316;">
                    <i data-lucide="package" style="width:18px;height:18px;"></i>
                </div>
                <div class="row-info">
                    <div class="name" style="font-size:18px;">${mat.name || ''}</div>
                    <div class="note" style="font-size:12px; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">${mat.category || ''} (${mat.brand || 'L\'Oréal'}) • objednat <span class="shopping-qty-inline">${safeQty}</span> ks</div>
                </div>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; justify-content:flex-end;">
                    <div style="display:flex; align-items:center; gap:8px; background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:6px 8px;">
                        <button type="button" onclick="changeShoppingQty(${id}, -1, this)" style="border:none; background:#fff; color:#9a3412; width:24px; height:24px; border-radius:7px; cursor:pointer; font-weight:800; font-size:15px; line-height:1;">−</button>
                        <span class="shopping-qty-value" style="min-width:22px; text-align:center; font-weight:800; color:#9a3412;">${safeQty}</span>
                        <button type="button" onclick="changeShoppingQty(${id}, 1, this)" style="border:none; background:#fff; color:#9a3412; width:24px; height:24px; border-radius:7px; cursor:pointer; font-weight:800; font-size:15px; line-height:1;">+</button>
                        <span style="font-size:11px; font-weight:700; color:#9a3412; text-transform:uppercase;">ks</span>
                    </div>
                    <button type="button" class="btn-ulozit" onclick="toggleShoppingPC(${id}, this, true)" style="background:#f1f5f9; color:var(--primary); border:none; padding:10px 20px; font-size:13px; font-weight:700; border-radius:10px; display:flex; align-items:center; gap:8px; margin:0;">
                        <i data-lucide="check" style="width:16px;height:16px;color:#10b981;"></i> Označit jako koupené
                    </button>
                </div>
            </div>
        `;

        const existing = rowsWrap.querySelector(`.shopping-row[data-id="${id}"]`);
        if (existing) {
            existing.outerHTML = rowHtml;
        } else {
            rowsWrap.insertAdjacentHTML('afterbegin', rowHtml);
        }
        lucide.createIcons();
    }

    function removeShoppingRow(id) {
        const row = document.querySelector(`#shopping-list-rows .shopping-row[data-id="${id}"]`);
        if (row) row.remove();
    }

    function getMaterialStateMeta(stockState = 'none') {
        switch (stockState) {
            case 'opened':
                return { key: 'opened', short: 'ROZ', label: 'Rozdělané', title: 'Stav: Rozdělané. Kliknutím změníš.' };
            case 'low':
                return { key: 'low', short: 'DOCH', label: 'Dochází. Automaticky přidáno i do nákupního seznamu.', title: 'Stav: Dochází. Kliknutím změníš.' };
            default:
                return { key: 'none', short: '—', label: 'Bez stavu', title: 'Stav: Bez stavu. Kliknutím změníš.' };
        }
    }

    async function cycleMaterialState(id, btn) {
        const currentState = btn?.dataset.materialState || (MATERIALS_DATA.find(m => m.id == id)?.stock_state ?? 'none');
        const stateOrder = ['none', 'opened', 'low'];
        const currentIndex = Math.max(0, stateOrder.indexOf(currentState));
        const nextState = stateOrder[(currentIndex + 1) % stateOrder.length];

        try {
            const formData = new FormData();
            formData.append('material_id', id);
            formData.append('mode', 'set_state');
            formData.append('state', nextState);
            formData.append('csrf_token', getCsrfToken());
            const resp = await fetch('api_shopping.php', { method: 'POST', body: formData });
            const json = await resp.json();
            if (!json.success) throw new Error(json.error || 'Nepodařilo se upravit stav.');

            document.querySelectorAll(`.btn-state-pc[data-material-id="${id}"]`).forEach(stateBtn => {
                const meta = getMaterialStateMeta(json.stock_state);
                stateBtn.dataset.materialState = meta.key;
                stateBtn.className = `btn-state-pc state-${meta.key}`;
                stateBtn.textContent = meta.short;
                stateBtn.title = meta.title;
            });

            document.querySelectorAll(`.btn-shop-pc[data-material-id="${id}"]`).forEach(shopBtn => {
                if (json.new_status) {
                    shopBtn.classList.add('active');
                    shopBtn.style.color = 'var(--gold)';
                    shopBtn.title = 'V nákupním seznamu';
                } else {
                    shopBtn.classList.remove('active');
                    shopBtn.style.color = '#cbd5e1';
                    shopBtn.title = 'Přidat na nákupní seznam';
                }
            });

            if (json.new_status) upsertShoppingRow(id, json.shopping_qty);
            else removeShoppingRow(id);

            refreshShoppingUi(json.list_count, json.total_qty);
            refreshMaterialStateUi(json.opened_count, json.low_count);

            const mat = MATERIALS_DATA.find(m => m.id == id);
            if (mat) {
                mat.stock_state = json.stock_state;
                mat.needs_buying = json.new_status;
                mat.shopping_qty = json.shopping_qty || mat.shopping_qty || 1;
            }
            const catalogItem = Array.isArray(window.CATALOG_DATA)
                ? window.CATALOG_DATA.find(entry => entry.type === 'material' && entry.id == id)
                : null;
            if (catalogItem) {
                catalogItem.stock_state = json.stock_state;
                catalogItem.needs_buying = json.new_status;
            }
            renderCatalogList();
        } catch (e) {
            console.error(e);
        }
    }

    async function changeShoppingQty(id, delta, btn) {
        const row = btn?.closest('.shopping-row');
        const qtyEl = row?.querySelector('.shopping-qty-value');
        const currentQty = Math.max(1, parseInt(qtyEl?.textContent || row?.dataset.qty || '1', 10) || 1);
        const nextQty = Math.max(1, currentQty + delta);

        if (nextQty === currentQty) return;

        try {
            const formData = new FormData();
            formData.append('material_id', id);
            formData.append('mode', 'set_qty');
            formData.append('quantity', String(nextQty));
            formData.append('csrf_token', getCsrfToken());

            const resp = await fetch('api_shopping.php', { method: 'POST', body: formData });
            const json = await resp.json();
            if (!json.success) throw new Error(json.error || 'Nepodařilo se upravit počet.');

            if (row) {
                row.dataset.qty = String(json.shopping_qty);
                row.querySelectorAll('.shopping-qty-value, .shopping-qty-inline').forEach(el => {
                    el.textContent = String(json.shopping_qty);
                });
            }

            refreshShoppingUi(json.list_count, json.total_qty);
            refreshMaterialStateUi(json.opened_count, json.low_count);

            const mat = MATERIALS_DATA.find(m => m.id == id);
            if (mat) {
                mat.needs_buying = json.new_status;
                mat.shopping_qty = json.shopping_qty;
                mat.stock_state = json.stock_state || mat.stock_state || 'none';
            }
        } catch (e) {
            console.error(e);
        }
    }

    function updateShopIconPC(container, materialId, needsBuying, stockState = 'none') {
        if(!container) return;
        const stateMeta = getMaterialStateMeta(stockState);
        container.innerHTML = `
            <button type="button" class="btn-state-pc state-${stateMeta.key}" data-material-id="${materialId}" data-material-state="${stateMeta.key}" onclick="cycleMaterialState(${materialId}, this)" title="${stateMeta.title}">${stateMeta.short}</button>
            <button type="button" class="btn-shop-pc ${needsBuying ? 'active' : ''}" data-material-id="${materialId}"
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
                
                if (json.new_status) {
                    upsertShoppingRow(id, json.shopping_qty);
                } else if (!isAccountingView) {
                    removeShoppingRow(id);
                }

                refreshShoppingUi(json.list_count, json.total_qty);
                refreshMaterialStateUi(json.opened_count, json.low_count);

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
                if(mat) {
                    mat.needs_buying = json.new_status;
                    mat.shopping_qty = json.shopping_qty;
                    mat.stock_state = json.stock_state || mat.stock_state || 'none';
                }
            }
        } catch(e) { console.error(e); }
    }
