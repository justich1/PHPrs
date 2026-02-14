# PHPrs – rychlý start

Tento projekt je jednoduchý CMS v PHP s podporou šablon a pluginů.

## Požadavky

- PHP **8.0+**
- MySQL / MariaDB
- PHP rozšíření `pdo_mysql`
- Zapisovatelná složka `config/`

---

## Instalace (`install.php`)

> Poznámka: v zadání zmiňuješ `instaal.php`, v projektu je instalační skript **`install.php`**.

1. Nahraj projekt na server.
2. Otevři v prohlížeči:
   - `https://tvoje-domena.cz/install.php`
3. Instalační průvodce má 3 kroky:
   - **Krok 1 – Kontrola prostředí**
     - ověří PHP verzi, `pdo_mysql` a oprávnění složky `config/`.
   - **Krok 2 – Databáze**
     - zadáš host, název DB, uživatele a heslo,
     - instalátor ověří připojení a vytvoří databázi, pokud neexistuje.
   - **Krok 3 – Administrátor**
     - vytvoří se `config/config.php`, databázové tabulky a první admin účet.
4. Po dokončení se skript pokusí sám odstranit (`install.php`).

### Přihlášení do administrace

Po instalaci se přihlas přes:

- `https://tvoje-domena.cz/admin/`

---

## Aktivace pluginů

1. Přihlas se do administrace.
2. Otevři sekci **Pluginy** (`/admin/plugins.php`).
3. U vybraného pluginu klikni na **Aktivovat**.
4. Aktivní plugin je zvýrazněn a může mít odkaz **Nastavení**.

### Deaktivace / smazání pluginu

- **Deaktivovat**: vypne plugin, ale soubory zůstanou.
- **Smazat**: smaže soubory pluginu (nevratné).

### Instalace nového pluginu

V sekci pluginů lze nahrát `.zip` balíček pluginu.

---

## Přehled pluginů

| Plugin | Složka | Popis |
|---|---|---|
| Blog a Články | `plugins/blog` | Kompletní správa blogových článků, kategorií a komentářů. Včetně WYSIWYG editoru. |
| Cookie Bar | `plugins/cookie-bar` | Zobrazuje cookie lištu v patičce webu s možností nastavení v administraci. Po udělení souhlasu/nesouhlasu zobrazí odkaz pro opětovné otevření. |
| Example Plugin | `plugins/example_plugin` | Plugin, který přidává text do patičky. |
| Google Maps Shortcode | `plugins/google_maps` | Umožňuje vložit do stránky responzivní Google Mapu pomocí shortcodu `[mapa adresa="..."]`. |
| Fotogalerie | `plugins/photo_gallery` | Plugin pro vytváření a správu fotogalerií pomocí shortcodu `[gallery id="..."]`. |
| E-shop | `plugins/shop` | Jednoduchý e‑shop plugin: kategorie, produkty, sklad, košík, objednávky, QR platba + dobírka, párování plateb přes Fio. |
| Složka uploads | `plugins/uploads` | Pozor: smazáním pluginu dojde i k odstranění nahraných souborů. |
| Správa uživatelů | `plugins/users` | Kompletní správa uživatelů, registrace, profily a administrace. Shortcody: `[prihlaseni]`, `[registrace]`, `[profil]`, `[reset_hesla]`, `[aktivace]`. |
