# Migrační nástroje

Jednorázové skripty, kterými vznikl `content/` a `files/` z původního CMS
(eywa nad CouchDB). Běžný provoz webu je nepotřebuje; jsou tu proto, aby
šlo migraci zopakovat a ověřit.

| Skript | Co dělá |
|---|---|
| `couchdb/` | čtečka souborů `.couch` (bloky, ETF, snappy, B-strom, přílohy) |
| `couch_export.py` | 1. fáze: shardy → `data/export/` (dokumenty + přílohy) |
| `migrate_content.py` | 2. fáze: export → `content/`, `files/`, `_redirects-rucni` |
| `xls2html.php` | převod přílohy `.xls` na HTML tabulku |
| `fosql.py`, `fo_sqlite.py` | čtení `../fo/fo.sql` do `temp/fo.sqlite` |
| `results_db.py` | reprodukce presenteru `ResultsDatabase` nad tím snímkem |
| `legacy_vysledky.py` | listiny z doby před CMS: Praha 45–53, Karlovy Vary 45–55 |
| `kontrola.php` | kontrola obsahu (běží i v CI) |
| `verify.py` | 31 kontrol konzistence migrace |

## Zdroje

- `data/couchdb/shards/` — kopie dat CouchDB (48 MB, mimo git). Autoritativní
  je `shards/`, ne `1.2.0/`; ta je zmrazený snímek z roku 2019 s méně dokumenty.
- `../fo/fo.sql` — databáze soutěže, z níž se berou výsledkové listiny.
- `../fo/kvary/vysledky/` — archiv Karlových Varů před CMS.
- `.recovered/praha-vysledky/` — archiv Prahy před CMS, rozbalený z historie
  `../fo` (`git archive 3af6326^ praha/vysledky`); commit `3af6326` ho smazal.
  V CouchDB tyhle ročníky nejsou a v `fo.sql` k nim nejsou body — tabulka
  `submit` začíná až 56. ročníkem.
- `.recovered/` — soubory stažené ze starého serveru (`web/doc`, `web/i`,
  `libs/eywa`). Mimo git.

## Vendorovaná čtečka Excelu

`tools/vendor/excel_reader2.php` je kopie `libs/php-excel-reader/` se čtyřmi
opravami. První tři jsou nutné pro PHP 8 (jinak se knihovna ani nespustí),
čtvrtá opravuje zaokrouhlování:

| Řádek | Změna |
|---|---|
| 79 | `split()` → `explode()` (odstraněno v PHP 7) |
| 97 | `function OLERead()` → `__construct()` |
| 1021 | `function Spreadsheet_Excel_Reader()` → `__construct()` |
| 1007 | `sprintf($p, $num)` → `sprintf($p, round($num, $des))` |

Poslední: `sprintf("%1.2f", 52.125)` dá v PHP `52.12` (zaokrouhluje na sudou
číslici), Excel ukazuje `52,13` (zaokrouhluje od nuly). Ověřeno proti
LibreOffice na všech 67 sešitech — týkalo se jediné buňky.

Řádek 105 už byl upravený eywou tak, že konstruktor bere surová data
místo cesty k souboru — to je potřeba zachovat.

## Opakování migrace

```sh
python3 tools/couch_export.py          # 1. fáze (potřebuje data/couchdb/)
python3 tools/migrate_content.py           # co by se změnilo (nic nezapíše)
python3 tools/migrate_content.py --write   # zapsat (ruční úpravy nechá být)
python3 tools/legacy_vysledky.py --write
python3 tools/verify.py
```

Bez přepínače se nic nezapisuje.
`--write` porovnává soubor na disku s otiskem v `data/export/manifest.json`:
co někdo upravil ručně, se přeskočí a vypíše. `--force` přepíše všechno.
