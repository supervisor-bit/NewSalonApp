## 🚀 1. Rychlá instalace (DOPORUČENO)
Pokud instalujete aplikaci poprvé nebo chcete čistý reset, nahrajte soubory na server a spusťte v prohlížeči:
`https://vasedomena.cz/install.php?run=1`

**Tento skript automaticky:**
- Vytvoří/Obnoví všechny databázové tabulky.
- Naimportuje barvy a oxydanty ze souboru `barvy_loreal.csv`.
- Naimportuje produkty na doma ze souboru `produkty_loreal.csv`.

## 📥 2. Nahrání souborů
Zkopírujte veškerý obsah složky `deploy/` do kořenového adresáře vašeho webu (např. přes FTP).

## 🗄️ 3. Nastavení Databáze (MySQL)
1. Otevřete soubor `db.php` a upravte přihlašovací údaje k vaší databázi (host, jméno, uživatel, heslo).
2. Pokud nepoužijete automatický `install.php`, musíte ručně importovat `schema.sql` přes phpMyAdmin.

## 🛠️ 4. Aktualizace stávající verze
Pokud už systém máte a chcete jen přidat nové funkce (např. „Hlídač materiálu“), spusťte:
`https://vasedomena.cz/migrate.php`

## 📱 4. Nastavení PWA (Ikona na plochu)
Aplikace je připravena jako PWA. Pokud chcete změnit ikonu, nahraďte soubor `icon.png` (512x512px) a aktualizujte `manifest.json`, pokud se mění název salonu.

## 🏗️ 5. Instalace na Synology NAS (DS716+)
Aplikace na vašem NASu poběží výborně. Postup:
1.  V **Centru balíčků** nainstalujte: `Web Station`, `PHP 8.2` a `MariaDB 10`.
2.  Ve **Web Station** vytvořte skriptovací profil pro PHP 8.2 a v sekci **Rozšíření** zaškrtněte: `pdo_mysql`, `mysqli`, `openssl`, `curl` a `mbstring`.
3.  V **MariaDB 10** si poznamenejte port (standardně `3306` nebo `3307`).
4.  Pomocí **File Station** nahrajte obsah složky `deploy/` do složky `web/karta/`.
5.  V souboru `db.php` nastavte `host` (obvykle `127.0.0.1` nebo `localhost`) a správný port.

## ✅ Kontrolní seznam
- Verze PHP: Doporučeno 7.4 nebo 8.x.
- Povolená rozšíření: `pdo_mysql`, `json`, `mbstring`.
- Práva k zápisu: Ujistěte se, že PHP může číst soubory `.csv` pro importy.

---
*Vytvořeno automaticky pro Profi Kadeřnickou Kartu (Optimalizováno pro Synology NAS)*
