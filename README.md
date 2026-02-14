🇬🇧 PHPrs is a lightweight PHP CMS with plugin and template support.

🇨🇿 PHPrs je jednoduchý CMS v PHP s podporou šablon a pluginů.

## Požadavky

- PHP **8.0+**
- MySQL / MariaDB
- PHP rozšíření `pdo_mysql`
- Zapisovatelná složka `config/`

---

## Instalace (`install.php`)

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
| Theme Layout Builder | `plugins/theme_layout_builder` | Nástroj pro vytvoření nové šablony/layoutu přímo z administrace: basic, levý/pravý sidebar i kompletní návrh vzhledu + volba barvy/šířky. |
| Složka uploads | `plugins/uploads` | Pozor: smazáním pluginu dojde i k odstranění nahraných souborů. |
| Správa uživatelů | `plugins/users` | Kompletní správa uživatelů, registrace, profily a administrace. Shortcody: `[prihlaseni]`, `[registrace]`, `[profil]`, `[reset_hesla]`, `[aktivace]`. |

---

☕ Podpora projektu

Pokud se ti projekt líbí a chceš podpořit jeho další vývoj, můžeš přispět dobrovolným darem:

👉 https://paypal.me/justich1

Děkuji za podporu 🙂

---

## License

This project is licensed under the MIT License.

### Third-party libraries

This project includes the **phpqrcode** library, which is licensed under the GNU Lesser General Public License v3.0 (LGPL-3.0).

The full license text is available in the phpqrcode library directory.

