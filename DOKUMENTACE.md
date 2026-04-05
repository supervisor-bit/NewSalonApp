# Aura – Dokumentace & Workflow

Aura je moderní webová aplikace navržená pro správu kadeřnického salonu, která kombinuje evidenci klientů, chytrou míchárnu receptur a finanční přehled s hlídačem skladu.

---

## 🚀 Hlavní moduly systému

### 1. Karta Klienta (Digitální evidence)
- **Kompletní historie**: Každá návštěva je zaznamenána včetně použité receptury a poznámky.
- **Diagnostika vlasů**: Evidujeme strukturu, stav, výchozí tón a procento šedin.
- **Alergie & Bezpečnost**: Červený banner „POZOR: ALERGIE“ se automaticky zobrazuje u rizikových klientů na všech zařízeních.
- **Rychlé opakování**: Možnost jedním kliknutím zkopírovat recepturu z minulé návštěvy do nové míchárny.

### 2. Chytrá Míchárna (PC & Mobil)
- **Podpora více misek**: Jedna návštěva může obsahovat neomezený počet misek (např. odrost, délky, tónování).
- **Inteligentní našeptávač**: Napovídá formátem „Značka - Odstín“ a umožňuje vyhledávat i podle názvu řady.
- **Skladová integrace**: Přímo z míchárny lze označit materiál, který dochází (ikona košíku), což ho okamžitě přidá na nákupní seznam.

### 3. Hlídač Materiálu (Nákupní seznam)
- **Reálný čas**: Odškrtnutí naskladněné položky se okamžitě projeví na počítadle chybějících kusů bez nutnosti obnovovat stránku.
- **Dostupnost**: Synchronizováno mezi PC a mobilní verzí (v mobilu ideální pro rychlé objednávání).
- **Přehlednost**: Pokud je vše naskladněno, systém zobrazuje potvrzení „Všechno máme!“.

### 4. Panel Financí & Statistiky
- **Denní závěrky**: Přehled tržeb za konkrétní den (včetně počtu vyúčtovaných návštěv).
- **Měsíční statistiky**: Roční přehled tržeb v přehledném grafu/seznamu.
- **Exporty**: Možnost exportovat data do CSV (pro Excel) nebo PDF.

---

## 🔄 Typické Workflow (Pracovní postup)

1. **Příprava**: Stylista na tabletu nebo PC vyhledá klienta a zkontroluje historii receptur a diagnostiku.
2. **Míchání (Míchárna)**: 
   - Vybere klienta a zvolí „Nová receptura“.
   - Přidá misky a vybere odstíny. Systém automaticky zvyšuje „počet použití“ u daného materiálu (pomáhá určit nejoblíbenější barvy).
   - Pokud barva v tubě dochází, klikne na ikonu košíku u daného řádku v míchárně.
3. **Realizace**: Stylista aplikuje barvu. Pokud potřebuje míchat znovu, mobilní verze mu umožní nahlédnout do historie přímo u křesla.
4. **Vyúčtování**:
   - Po skončení práce stylista (nebo recepční) v PC verzi návštěvu uzavře.
   - Přidají se prodejní produkty (šampony, kondicionéry) a zadá se výsledná cena.
   - Návštěva se přesune z rozpracovaných do historie.
5. **Inventura (Konec dne/týdne)**:
   - V Panelu financí se otevře záložka „Nákupní seznam“.
   - Stylista vidí, co všechno během dne/týdne označil jako „docházející“.
   - Po naskladnění stačí jedno kliknutí a barva ze seznamu plynule zmizí.

---

## 🛠️ Technické vychytávky
- **Dual Interface**: Aplikace automaticky detekuje zařízení. Na mobilu nabízí kompaktní ovládání jedním palcem, na PC/Tabletu plný přehled.
- **Cache-Busting**: Systém se automaticky aktualizuje po každé změně kódu (není třeba mazat mezipaměť prohlížeče).
- **Turbo Scanner**: Podpora pro USB čtečky čárových kódů pro bleskové naskladňování produktů.

---
*Dokumentace vygenerována pro Profi Kadeřnickou Kartu (v. 2.0)*
