# Shop plugin (v2)

## Frontend
Použij shortcode **[shop]** na stránce obchodu (např. `/cs/obchod`).

Plugin generuje čisté URL:
- `/cs/obchod` – výpis kategorií + produktů
- `/cs/obchod/kategorie/{slug}` – produkty v kategorii
- `/cs/obchod/produkt/{slug}` – detail produktu
- `/cs/obchod/kosik` – košík + pokladna
- `/cs/obchod/objednavky` – moje objednávky (vyžaduje přihlášení)

Base path nastavíš v adminu: **Nastavení → Základní URL shopu**.

## Admin
- Produkty (hlavní foto + galerie)
- Kategorie
- Doprava (dopravci)
- Účty (pro QR platbu)
- Objednávky
- Nastavení (Fio token + default účet + base URL + dobírka poplatek)

## Cron Fio
`php /cesta/k/webroot/plugins/shop/cron/fio_check_payments.php`
