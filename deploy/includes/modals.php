<!-- includes/modals.php -->

<!-- ŠABLONY -->
<template id="bowl-template">
    <div class="bowl-container">
        <div class="bowl-header">
            <div class="bowl-title-group">
                <i data-lucide="layers" style="width:18px;height:18px;color:#64748b;margin-right:-2px;"></i>
                <input type="text" class="bowl-name-input" value="Miska">
            </div>
            <button type="button" class="btn-remove" onclick="this.parentElement.parentElement.remove()" title="Smazat tuto misku">×</button>
        </div>
        <div class="bowl-meta-row">
            <div class="bowl-mix-autocomplete" style="position:relative;">
                <input type="text" class="bowl-mix-input" placeholder="Poměr míchání, např. 1:1,5" autocomplete="off">
                <div class="ac-list bowl-mix-list"></div>
            </div>
            <div class="bowl-mix-summary">Tip: napiš třeba 1:1 nebo 1:1,5 a hned uvidíš doporučené gramy oxidantu.</div>
        </div>
        <div class="bowl-rows-container"></div>
        <button type="button" style="background:none; border:none; color:var(--primary); font-size:12px; font-weight:700; cursor:pointer; margin-top:10px; padding-left:30px; text-transform:uppercase; letter-spacing:0.5px;" onclick="pridatRadekKMisaceBtn(this)">+ PŘIDAT DALŠÍ BARVU / ODSTÍN</button>
    </div>
</template>

<template id="receptura-template">
    <div class="recept-row">
        <div style="position:relative; flex:1;">
            <input type="hidden" class="material-hidden">
            <input type="text" class="material-search" placeholder="Hledat odstín..." autocomplete="off">
            <div class="ac-list"></div>
        </div>
        <div class="shop-toggle-pc" style="display:flex; align-items:center; justify-content:center; min-width:30px;"></div>
        <input type="number" class="amount-input" placeholder="g">
        <button type="button" class="btn-remove" onclick="removeRecipeRow(this)" tabindex="-1">×</button>
    </div>
</template>

<template id="product-row-template">
    <div class="product-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
        <div style="position:relative; flex:2;">
            <input type="hidden" class="product-hidden" name="product_ids[]">
            <input type="text" class="product-search" placeholder="Hledat produkt..." autocomplete="off" style="width:100%;">
            <div class="ac-list"></div>
        </div>
        <div style="position:relative; flex:1;">
            <input type="number" class="product-amount" name="product_amounts[]" placeholder="Ks" style="width:100%; text-align:center; font-weight:700;" value="1">
        </div>
        <div style="position:relative; flex:1;">
            <input type="number" class="product-price" name="product_prices[]" placeholder="Cena" style="width:100%; padding-right:25px; text-align:right;">
            <span style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:11px;">Kč</span>
        </div>
        <button type="button" class="btn-remove" onclick="this.parentElement.remove()" style="width:34px; height:34px; padding:0; flex-shrink:0;">×</button>
    </div>
</template>

<!-- MENU DROPDOWN -->
<div id="global-dropdown" class="dropdown-content" style="display:none; position:fixed; z-index:9999;">
    <a href="#" id="menu-global-edit">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
        Upravit osobní údaje
    </a>
    <a href="#" id="menu-global-toggle-favorite">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        Připnout nahoru
    </a>
    <a href="#" id="menu-global-toggle-status">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
        Přesunout do neaktivních
    </a>
    <div style="border-top:1px solid #e2e8f0; margin: 4px 0;"></div>
    <a href="#" class="danger" id="menu-global-delete">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
        Smazat klienta
    </a>
</div>

<!-- MODÁLY -->
<div id="nova-klientka-modal" class="modal">
    <div class="modal-content">
        <h3>Nový klient</h3>
        <form action="save_client.php" method="POST" class="modal-form">
            <input type="text" name="first_name" placeholder="Jméno">
            <input type="text" name="last_name" placeholder="Příjmení">
            <input type="tel" name="phone" placeholder="Telefonní číslo">
            <div style="margin-top:10px;">
                <label style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:5px;">Interní štítky</label>
                <input type="text" name="client_tags" id="new-client-tags" placeholder="např. blond, melír, citlivá pokožka">
                <div class="quick-tag-row">
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('new-client-tags', 'Blond')">Blond</button>
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('new-client-tags', 'Melír')">Melír</button>
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('new-client-tags', 'Citlivá pokožka')">Citlivá</button>
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('new-client-tags', 'Šediny')">Šediny</button>
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('new-client-tags', 'Studený tón')">Studený tón</button>
                </div>
            </div>
            <div style="margin-top:10px;">
                <label style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:5px;">Interval návštěv (v týdnech)</label>
                <input type="number" name="preferred_interval" placeholder="Např. 6" min="1" max="52">
            </div>
            <div style="margin-top:10px;">
                <button type="submit" class="btn-ulozit"><i data-lucide="check" style="width:18px;height:18px;"></i> Uložit a pokračovat na diagnostiku</button>
                <button type="button" class="btn-cancel" onclick="schovNovaKlientka()">Zrušit</button>
            </div>
        </form>
    </div>
</div>

<div id="edit-client-profile-modal" class="modal">
    <div class="modal-content">
        <h3>Upravit klienta</h3>
        <form action="update_client_profile.php" method="POST" class="modal-form">
            <input type="hidden" name="client_id" id="edit-client-profile-id">
            <input type="text" name="first_name" id="edit-client-profile-first" placeholder="Jméno">
            <input type="text" name="last_name" id="edit-client-profile-last" placeholder="Příjmení">
            <input type="tel" name="phone" id="edit-client-profile-phone" placeholder="Telefonní číslo">
            <div style="margin-top:10px;">
                <label style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:5px;">Interní štítky</label>
                <input type="text" name="client_tags" id="edit-client-profile-tags" placeholder="např. blond, melír, citlivá pokožka">
                <div class="quick-tag-row">
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('edit-client-profile-tags', 'Blond')">Blond</button>
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('edit-client-profile-tags', 'Melír')">Melír</button>
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('edit-client-profile-tags', 'Citlivá pokožka')">Citlivá</button>
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('edit-client-profile-tags', 'Šediny')">Šediny</button>
                    <button type="button" class="quick-tag-btn" onclick="toggleClientTagInput('edit-client-profile-tags', 'Studený tón')">Studený tón</button>
                </div>
            </div>
            <div style="margin-top:10px;">
                <label style="display:block; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; margin-bottom:5px;">Interval návštěv (v týdnech)</label>
                <input type="number" name="preferred_interval" id="edit-client-profile-interval" placeholder="Např. 6" min="1" max="52">
            </div>
            <div style="margin-top:10px;">
                <button type="submit" class="btn-ulozit"><i data-lucide="save" style="width:18px;height:18px;"></i> Uložit osobní údaje</button>
                <button type="button" class="btn-cancel" onclick="schovUpravuProfilu()">Zrušit</button>
            </div>
        </form>
    </div>
</div>

<div id="edit-diagnostics-modal" class="modal">
    <div class="modal-content">
        <h3>Vlasová diagnostika klienta</h3>
        <form action="update_diagnostics.php" method="POST">
            <input type="hidden" name="client_id" value="<?= $active_client['id'] ?? 0 ?>">
            <span class="diag-label">Struktura a stav vlasu:</span>
            <div class="input-group">
                <select name="hair_texture">
                    <option value="Neřešeno" <?= ($active_client['hair_texture']??'') == 'Neřešeno'?'selected':'' ?>>Typ vlasů: Nevybráno</option>
                    <option value="Jemné" <?= ($active_client['hair_texture']??'') == 'Jemné'?'selected':'' ?>>Jemné</option>
                    <option value="Střední" <?= ($active_client['hair_texture']??'') == 'Střední'?'selected':'' ?>>Střední</option>
                    <option value="Silné/Hrubé" <?= ($active_client['hair_texture']??'') == 'Silné/Hrubé'?'selected':'' ?>>Silné/Hrubé</option>
                </select>
                <select name="hair_condition">
                    <option value="Neřešeno" <?= ($active_client['hair_condition']??'') == 'Neřešeno'?'selected':'' ?>>Kondice: Nevybráno</option>
                    <option value="Zdravé" <?= ($active_client['hair_condition']??'') == 'Zdravé'?'selected':'' ?>>Zdravé</option>
                    <option value="Suché" <?= ($active_client['hair_condition']??'') == 'Suché'?'selected':'' ?>>Suché</option>
                    <option value="Mastné" <?= ($active_client['hair_condition']??'') == 'Mastné'?'selected':'' ?>>Mastné</option>
                    <option value="Poškozené" <?= ($active_client['hair_condition']??'') == 'Poškozené'?'selected':'' ?>>Poškozené</option>
                </select>
            </div>
            <span class="diag-label">Přírodní základ a Šediny:</span>
            <div class="input-group" style="margin-top:10px;">
                <select name="base_tone">
                    <option value="">Výška tónu: Nevybráno</option>
                    <option value="1 (Černá)" <?= ($active_client['base_tone']??'') == '1 (Černá)'?'selected':'' ?>>1 (Černá)</option>
                    <option value="2 (Velmi tmavý hnědý)" <?= ($active_client['base_tone']??'') == '2 (Velmi tmavý hnědý)'?'selected':'' ?>>2 (Velmi tmavý hnědý)</option>
                    <option value="3 (Tmavý hnědý)" <?= ($active_client['base_tone']??'') == '3 (Tmavý hnědý)'?'selected':'' ?>>3 (Tmavý hnědý)</option>
                    <option value="4 (Hnědý)" <?= ($active_client['base_tone']??'') == '4 (Hnědý)'?'selected':'' ?>>4 (Hnědý)</option>
                    <option value="5 (Světlý hnědý)" <?= ($active_client['base_tone']??'') == '5 (Světlý hnědý)'?'selected':'' ?>>5 (Světlý hnědý)</option>
                    <option value="6 (Tmavý blond)" <?= ($active_client['base_tone']??'') == '6 (Tmavý blond)'?'selected':'' ?>>6 (Tmavý blond)</option>
                    <option value="7 (Blond)" <?= ($active_client['base_tone']??'') == '7 (Blond)'?'selected':'' ?>>7 (Blond)</option>
                    <option value="8 (Světlý blond)" <?= ($active_client['base_tone']??'') == '8 (Světlý blond)'?'selected':'' ?>>8 (Světlý blond)</option>
                    <option value="9 (Velmi světlý blond)" <?= ($active_client['base_tone']??'') == '9 (Velmi světlý blond)'?'selected':'' ?>>9 (Velmi světlý blond)</option>
                    <option value="10 (Extra světlý blond)" <?= ($active_client['base_tone']??'') == '10 (Extra světlý blond)'?'selected':'' ?>>10 (Extra světlý blond)</option>
                </select>
                <select name="gray_percentage">
                    <option value="">Šediny: Nevybráno</option>
                    <option value="0%" <?= ($active_client['gray_percentage']??'') == '0%'?'selected':'' ?>>0% (Bez šedin)</option>
                    <option value="1 - 30%" <?= ($active_client['gray_percentage']??'') == '1 - 30%'?'selected':'' ?>>1 - 30%</option>
                    <option value="30 - 50%" <?= ($active_client['gray_percentage']??'') == '30 - 50%'?'selected':'' ?>>30 - 50%</option>
                    <option value="50 - 70%" <?= ($active_client['gray_percentage']??'') == '50 - 70%'?'selected':'' ?>>50 - 70%</option>
                    <option value="70 - 100%" <?= ($active_client['gray_percentage']??'') == '70 - 100%'?'selected':'' ?>>70 - 100%</option>
                </select>
            </div>
            <button type="submit" class="btn-ulozit" style="margin-top:20px;"><i data-lucide="save" style="width:18px;height:18px;"></i> Uložit diagnostiku</button>
            <button type="button" class="btn-cancel" onclick="schovUpravuDiagnostiky()">Zrušit</button>
        </form>
    </div>
</div>

<div id="smazat-modal" class="modal">
    <div class="modal-content" style="text-align:center; max-width: 380px;">
        <div style="background:#fee2e2; width:72px; height:72px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 24px auto;">
            <i data-lucide="alert-circle" style="width:36px; height:36px; color:#ef4444;"></i>
        </div>
        <h3 style="margin-bottom:12px; font-size:22px; color:#1e293b; font-weight:800;">Opravdu smazat?</h3>
        <p style="color:#64748b; font-size:15px; margin-bottom:28px; line-height:1.5;">Tento krok je nevratný. Záznam bude trvale odstraněn z databáze vašeho salonu.</p>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <a href="#" id="potvrdit-smazani-btn" class="btn-danger-new">
                <i data-lucide="trash-2" style="width:18px; height:18px;"></i>
                Ano, trvale smazat
            </a>
            <button type="button" class="btn-cancel-new" onclick="schovSmazatModal()">Zrušit</button>
        </div>
    </div>
</div>

<div id="action-dialog-modal" class="modal">
    <div class="modal-content" style="text-align:center; max-width: 400px;">
        <div id="action-dialog-icon" style="background:#fee2e2; width:72px; height:72px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 24px auto; color:#ef4444;">
            <i data-lucide="shield-alert" style="width:34px; height:34px;"></i>
        </div>
        <h3 id="action-dialog-title" style="margin-bottom:12px; font-size:22px; color:#1e293b; font-weight:800;">Potvrzení</h3>
        <p id="action-dialog-text" style="color:#64748b; font-size:15px; margin-bottom:28px; line-height:1.6;">Opravdu chcete pokračovat?</p>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <button type="button" id="action-dialog-confirm" class="btn-danger-new" onclick="closeActionDialog(true)">Pokračovat</button>
            <button type="button" id="action-dialog-cancel" class="btn-cancel-new" onclick="closeActionDialog(false)">Zrušit</button>
        </div>
    </div>
</div>

<div id="checkout-modal" class="modal">
    <div class="modal-content" style="width: 850px; max-width: 95%;">
        <h3 style="color:#10b981; border-bottom:1px solid #cbd5e1; padding-bottom:12px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
            <i data-lucide="banknote" style="width:24px; height:24px;"></i> Vyúčtování a uzavření návštěvy
        </h3>
        <form action="checkout.php" method="POST">
            <input type="hidden" name="client_id" value="<?= $active_client['id'] ?? 0 ?>">
            <input type="hidden" name="visit_id" id="checkout-visit-id">
            <div style="display: flex; gap: 30px; align-items: flex-start;">
                <div style="flex: 0 0 320px;">
                    <div class="diag-label" style="margin:0 0 10px 0;">Shrnutí dnešní návštěvy:</div>
                    <div id="checkout-summary" style="max-height:420px; overflow-y:auto; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:15px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);"></div>
                </div>
                <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <span class="diag-label" style="margin:0;">Osobní poznámka k návštěvě:</span>
                        <textarea name="note" id="checkout-note" rows="2" placeholder="Dýško, střih, zajímavosti..." style="resize:none; padding:10px; font-size:13px;"></textarea>
                    </div>
                    <div style="background:#f1f5f9; padding:16px; border-radius:14px; display:flex; flex-direction:column; gap:12px; border:1px solid #e2e8f0;">
                        <input type="hidden" id="checkout-products-price" value="0">
                        <input type="hidden" name="price" id="checkout-total-price" value="0">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label style="color:#64748b; font-size:13px;">Produkty celkem:</label>
                            <span id="checkout-products-display" style="font-weight:600; color:#475569;">0 Kč</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label style="font-weight:700; color:#334155; font-size:14px;">Služby (práce):</label>
                            <input type="number" id="checkout-service-price" placeholder="Zadejte Kč" style="width:130px; font-weight:700; font-size:16px; text-align:right;" oninput="kalkulackaCheckout()">
                        </div>
                        <div style="border-top:1px solid #e2e8f0; margin:4px 0;"></div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label style="font-weight:800; color:var(--primary-dark); font-size:16px;">CELKEM K ÚHRADĚ:</label>
                            <span id="checkout-total-display" style="font-weight:800; color:var(--primary-dark); font-size:20px;">0 Kč</span>
                        </div>
                        <div style="border-top:1px dashed #cbd5e1; margin:4px 0;"></div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label style="color:#64748b; font-size:13px;">Hotovost od klienta:</label>
                            <input type="number" id="checkout-given" placeholder="V Kč" style="width:130px; text-align:right;" oninput="kalkulackaCheckout()">
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label style="font-weight:700; color:#166534; font-size:14px;">Vrátit:</label>
                            <span id="checkout-return" style="font-weight:800; color:#ef4444; font-size:16px;">0 Kč</span>
                        </div>
                    </div>
                    <div style="background:#fff; padding:15px; border:1px dashed #cbd5e1; border-radius:14px;">
                        <span class="diag-label" style="font-size:12px; margin:0 0 10px 0;"><i data-lucide="calendar-days" style="width:14px;height:14px;"></i> Objednat na příště:</span>
                        <div style="display:flex; gap:6px; margin-bottom:10px; flex-wrap:wrap;">
                            <button type="button" class="btn-outline" style="padding:6px 10px; font-size:10px;" onclick="setNextVisit(4)">4 týdny</button>
                            <button type="button" class="btn-outline" style="padding:6px 10px; font-size:10px;" onclick="setNextVisit(6)">6 týdnů</button>
                            <button type="button" class="btn-outline" style="padding:6px 10px; font-size:10px;" onclick="setNextVisit(8)">8 týdnů</button>
                            <button type="button" class="btn-outline" style="padding:6px 10px; font-size:10px;" onclick="setNextVisit(12)">3 měsíce</button>
                        </div>
                        <input type="date" name="next_visit_date" id="checkout-next-visit" style="font-size:13px; padding:8px;">
                    </div>
                    <div style="display:flex; gap:10px; margin-top:5px;">
                        <button type="submit" class="btn-ulozit" style="margin:0; flex:2; background:#10b981; height:45px;"><i data-lucide="check-circle"></i> Hotovo, uložit</button>
                        <button type="button" class="btn-cancel" style="margin:0; flex:1; height:45px;" onclick="schovCheckout()">Zrušit</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Materials and Products moved to index.php Settings Tab -->


<div id="edit-allergy-modal" class="modal">
    <div class="modal-content">
        <h3 style="display:flex; align-items:center; gap:8px;"><i data-lucide="alert-triangle" style="width:24px;height:24px;color:#ef4444;"></i> Úprava varování klienta</h3>
        <form action="update_client.php" method="POST" class="modal-form">
            <input type="hidden" name="client_id" value="<?= $active_client['id'] ?? 0 ?>">                    
            <div style="display:flex; flex-direction:column; gap:8px;">
                <label style="font-size:14px;font-weight:bold;">Zdravotní a jiná varování (Alergie):</label>
                <textarea name="allergy_note" rows="3" style="width:100%;" placeholder="Poznámka bude červeně svítit na kartě..."><?= htmlspecialchars($active_client['allergy_note'] ?? '') ?></textarea>
            </div>
            <div style="margin-top:10px;">
                <button type="submit" class="btn-ulozit"><i data-lucide="save" style="width:18px;height:18px;"></i> Uložit varování</button>
                <button type="button" class="btn-cancel" onclick="schovUpravuVarovani()">Zrušit</button>
            </div>
        </form>
    </div>
</div>
