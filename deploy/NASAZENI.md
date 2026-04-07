# NASAZENÍ NA SYNOLOGY / WEBHOSTING

## 📦 Co se nasazuje
Na server se nahrává **obsah složky `deploy/`**.
Tato složka je připravená jako produkční balík pro NAS / hosting.

---

## 🚀 1. První instalace
Pokud aplikaci spouštíte poprvé nebo chcete čistý reset:

1. nahrajte obsah složky `deploy/` do webové složky na serveru,
2. nastavte připojení k databázi v `db.php`,
3. otevřete v prohlížeči:

```text
https://vasedomena.cz/install.php?run=1
```

Tento krok automaticky:
- vytvoří tabulky,
- připraví základ aplikace,
- **nevytváří demo klienta**,
- **číselníky importuje jen na vyžádání**.

Pokud chcete při první instalaci rovnou nahrát číselníky materiálů a produktů, použijte:

```text
https://vasedomena.cz/install.php?run=1&catalog=1
```

Demo klient se vytvoří jen při explicitním parametru:

```text
https://vasedomena.cz/install.php?run=1&catalog=1&demo=1
```

---

## 🔄 2. Aktualizace existující instalace
Pokud už systém běží a jen nahráváte novou verzi:

1. přepište server obsahem `deploy/`,
2. pak spusťte:

```text
https://vasedomena.cz/migrate.php
```

To doplní nové sloupce a úpravy DB bez nutnosti čisté reinstalace.

> Po této verzi se tímto krokem doplní i novější pole jako např. `shopping_qty` a `stock_state`.

---

## 🗄️ 3. Databáze
Upravte soubor `db.php` podle NAS/serveru:
- `host` → většinou `127.0.0.1` nebo `localhost`
- `dbname` → název databáze
- `username` / `password`
- případně port MariaDB (`3306` nebo `3307` podle Synology)

Pokud `install.php` nepoužijete, je možné ručně importovat `schema.sql` přes phpMyAdmin/Adminer.

---

## 🏗️ 4. Doporučený postup pro Synology NAS

### A) Příprava balíčků
V **Centru balíčků** nainstalujte:
- `Web Station`
- `PHP 8.2` (nebo dostupnou 8.x)
- `MariaDB 10`
- volitelně `phpMyAdmin` / `Adminer`

### B) PHP profil ve Web Station
Ve **Web Station** zapněte alespoň tato rozšíření:
- `pdo_mysql`
- `mysqli`
- `mbstring`
- `json`
- `openssl`
- `curl`

### C) Databáze
1. vytvořte databázi, např. `karta`,
2. vytvořte uživatele s právem k této DB,
3. údaje zapište do `db.php`.

> Pokud nejde otevřít `phpMyAdmin` přes QuickConnect, bývá potřeba databázi vytvořit z lokální sítě, přes VPN nebo přímo v DSM / Admineru.

### D) Upload aplikace
Do webové složky NASu nahrajte obsah `deploy/`, typicky do:

```text
/web/karta/
```

Pak by aplikace běžela např. na:

```text
https://vas-nas.cz/karta/
```

---

## ✅ 5. Kontrolní checklist po nasazení
Po uploadu ověřte:
- jde otevřít `login.php`,
- funguje přihlášení,
- funguje `index.php`,
- ve financích se načítá nákupní seznam,
- rychlý prodej funguje i s našeptávačem,
- po update byl spuštěný `migrate.php`.

---

## 📱 6. PWA / cache po aktualizaci
Aplikace používá service worker.
Po nasazení nové verze doporučeno:
- udělat tvrdý refresh: `Ctrl/Cmd + Shift + R`,
- případně zavřít a znovu otevřít aplikaci z plochy.

To je důležité hlavně po změnách v `app.js`, `style.css` a PWA chování.

---

## ⚠️ Praktická poznámka
- `install.php` **ponecháváme v projektu** pro první instalaci a rychlé obnovení.
- výchozí `install.php?run=1` udělá čistou instalaci bez demo klienta; číselníky se přidávají jen přes `&catalog=1`.
- Pro běžný update produkce stačí většinou:
  1. nahrát nový `deploy/`
  2. spustit `migrate.php`
  3. udělat tvrdý refresh

---

*Nasazovací plán pro Profi Kadeřnickou Kartu – optimalizováno pro Synology NAS.*
