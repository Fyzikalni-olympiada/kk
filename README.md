# Krajské weby Fyzikální olympiády (Praha, Karlovy Vary)

Dva statické weby z jednoho repozitáře. Obsah žije v souborech (žádná
databáze), sestavuje se PHP skriptem do `dist/` a nasazuje na Cloudflare
Pages — push do větve `static` spustí GitHub Actions.

| Web | Adresa | Obsah | Cloudflare Pages |
|---|---|---|---|
| Praha | praha.fyzikalniolympiada.cz | `content/praha/` | `fo-praha` |
| Karlovy Vary | kvary.fyzikalniolympiada.cz | `content/kvary/` | `fo-kvary` |

## Struktura repozitáře

| Co | Kde |
|---|---|
| Stránky | `content/<web>/…` — cesta souboru = adresa stránky |
| Velké soubory (xls, pdf) | `files/<web>/…` — publikují se **bez** prefixu |
| Šablona a pomocné funkce | `src/` |
| Build a vývojový server | `build/` |
| CSS, JS, fonty, obrázky | `assets/` (`assets/<web>/` je přebíjí pro jeden web) |
| Migrační nástroje | `tools/` (jednorázové, viz `tools/README.md`) |
| Data z původního CMS | `data/export/` (archiv, needituje se) |

## Jak vypadá stránka

Soubor má YAML hlavičku a pod ní HTML tělo:

```
---
type: results
title: Kategorie A
heading: Výsledky krajského kola 58. ročníku kategorie A
position: 1
---
<p>Úlohy opravili: …</p>
<table class="vysledkovka">…</table>
```

| Položka | K čemu |
|---|---|
| `type` | druh stránky, viz níž (povinné) |
| `title` | popisek v menu a ve výpisech (povinné) |
| `heading` | nadpis `<h1>`; když chybí, použije se `title` |
| `position` | pořadí mezi sourozenci (menší číslo = výš) |
| `hidden: true` | stránka zůstane dostupná, ale zmizí z menu a výpisů |
| `date`, `author` | u novinek |
| `location`, `code` | u `type: redirect` |
| `soubor`, `soubor_nazev` | odkaz ke stažení u výsledkových listin |
| `source` | odkud se stránka při migraci vzala; needituje se |

**Nadpis nepiš do těla.** `<h1>` vypisuje šablona z `heading`.

### Typy stránek

| `type` | Co dělá |
|---|---|
| `page` | běžná textová stránka |
| `overview` | text a pod ním seznam podstránek |
| `news` | rozcestník novinek (vypíše je celé pod sebe) |
| `results` | výsledková listina — tabulka v těle, volitelně soubor ke stažení |
| `results-overview` | rozcestník výsledků: kola a v nich kategorie |
| `redirect` | žádná stránka; jen přesměruje na `location` |
| `dir`, `link`, `site` | jen položka struktury, vlastní stránku nemá |

## Postupy

**Nová novinka.** Přidej `content/praha/aktualni-rocnik/novinky-dir/<nazev>.html`
podle vzoru vedle: `type: page`, `title`, `date`, `author`, `position`.
Novinky se řadí podle `position` vzestupně, nejnovější má nejmenší číslo
(klidně záporné). Commit → do dvou minut je na webu.

Na rozcestníku se **zobrazují jen novinky nejnovějšího ročníku**, který
nějakou má; starší zůstávají dostupné na svých adresách, jen se nevypisují.
Ročník se počítá z data (běží od září do června, 67. ročník = 2025/2026),
takže první novinka nového ročníku sama schová ten předchozí — nikde se
nemusí přepínat žádné číslo a web nezestárne jen tím, že plyne čas.

Trvalý odkaz na novinku visí na datu pod ní.

**Nová výsledková listina.** Založ
`content/<web>/vysledky/<ročník>/<kolo>/<kategorie>.html` s `type: results`
a tabulkou v těle. Původní `.xls` patří do `files/<web>/vysledky/…` a odkážeš
na něj přes `soubor:` a `soubor_nazev:`.

**Nový ročník.** Přidej `content/<web>/vysledky/<ročník>.html`
(`type: results-overview`) a pod ním adresář s koly. `position` u ročníků
klesá směrem k novějším (56 → 100, 57 → 99 …).

**Změna menu.** Žádný seznam menu neexistuje — menu i rozcestníky vznikají
ze stromu adresářů a z `position`.

**Přesměrování.** Buď `type: redirect` s `location:`, nebo (pro pravidla
s hvězdičkou a pro adresy, které v obsahu nemají dokument)
`assets/<web>/_redirects-rucni`.

## Časté chyby

- Hlavička musí začínat `---` na **prvním** řádku a končit řádkem `---`.
- V těle nesmí být `<h1>` — nadpis dělá šablona.
- Odkazy piš od kořene: `/kontakty`, ne `kontakty`.
- Na vlastní weby odkazuj přes `https://`, jinak to kontrola zamítne.

Kontrola `tools/kontrola.php` běží na každý pull request a znovu před
nasazením; když něco nesedí, GitHub ukáže červený křížek a web zůstane
v poslední funkční verzi.

## Pro vývojáře

```sh
make dev         # Praha na http://localhost:8000
make dev-kvary   # Karlovy Vary na http://localhost:8001
make build       # sestaví oba weby do dist/
make check       # kontrola obsahu
```

Potřebné jen jednou: `composer install` (jediná závislost je `symfony/yaml`).

### Nasazení

GitHub Actions po pushi do `static` sestaví weby, nasadí je do dvou projektů
Cloudflare Pages a zneplatní v cache jen změněné adresy. Potřebné secrets:
`CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_ZONE_ID`.

Před přepnutím DNS je nutné přidat vlastní doménu **v projektu Pages**,
jinak CNAME vrací 522. Weby `vysocina` a `plzen` se nesestavují — jejich
adresy řeší Bulk Redirect v Cloudflare.
