<?php
/**
 * Datová vrstva nad content/<web>/ — náhrada za pohledy CouchDB.
 *
 * Všech pět pohledů _design/eywa mělo tvar emit([doc.path.slice(0,-1), klíč]),
 * tedy „potomci této cesty seřazení podle …“.  Přesně to tady dělá jeden
 * průchod adresářem: postaví se plochá mapa cesta→uzel a předpočítaná mapa
 * cesta→[potomci], takže vykreslení jedné stránky je O(1), ne O(N).
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Registr typů stránek — jediné místo, kde se o typu něco ví.
 *
 *   stranka    vykresluje se do dist/ jako HTML
 *   sirokа     jeden sloupec (bez bočního menu)
 *   v_menu     objevuje se v navigaci a ve výpisech
 *   odkaz      pole, ve kterém má uzel cíl odkazu (jinak vlastní adresa)
 *   presmeruj  přispívá pravidlem do _redirects
 *
 * Přidat typ = jeden řádek tady a jeden soubor v src/stranky/.
 */
const TYPY = [
    'page'             => ['stranka' => true,  'v_menu' => true],
    'overview'         => ['stranka' => true,  'v_menu' => true],
    'news'             => ['stranka' => true,  'v_menu' => true],
    'results'          => ['stranka' => true,  'v_menu' => true, 'siroka' => true],
    'results-overview' => ['stranka' => true,  'v_menu' => true],
    'results-index'    => ['stranka' => true,  'v_menu' => true],
    'redirect'         => ['stranka' => false, 'v_menu' => true,
                           'odkaz' => 'cil', 'presmeruj' => true],
    'site'             => ['stranka' => false, 'v_menu' => false,
                           'odkaz' => 'destination', 'presmeruj' => true],
    'link'             => ['stranka' => false, 'v_menu' => true,
                           'odkaz' => 'destination'],
    'dir'              => ['stranka' => false, 'v_menu' => false],
];


/** Vlastnost typu uzlu; neznámé vlastnosti jsou false. */
function typ_ma(array $uzel, string $vlastnost): bool
{
    return (bool) (TYPY[$uzel['typ']][$vlastnost] ?? false);
}


/** Pole s cílem odkazu pro daný typ, nebo null (= vlastní adresa). */
function typ_odkaz(array $uzel): ?string
{
    return TYPY[$uzel['typ']]['odkaz'] ?? null;
}


/** Celý strom aktuálního webu, memoizovaný per web. */
function obsah_strom(): array
{
    static $stromy = [];
    $slug = site_slug();
    if (!isset($stromy[$slug])) {
        $stromy[$slug] = obsah_skenuj(ROOT_DIR . 'content/' . $slug);
    }
    return $stromy[$slug];
}


function obsah_skenuj(string $adresar): array
{
    $uzly = [];
    if (!is_dir($adresar)) {
        return ['uzly' => [], 'deti' => []];
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($adresar, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $soubor) {
        if ($soubor->getExtension() !== 'html') {
            continue;
        }
        $rel = substr($soubor->getPathname(), strlen($adresar) + 1, -strlen('.html'));
        $pathname = $rel === 'index' ? '/' : '/' . $rel;
        $uzly[$pathname] = obsah_uzel_ze_souboru($soubor->getPathname(), $pathname);
    }

    $deti = [];
    foreach ($uzly as $pathname => $uzel) {
        if ($pathname === '/') {
            continue;
        }
        $rodic = substr($pathname, 0, (int) strrpos($pathname, '/'));
        $deti[$rodic === '' ? '/' : $rodic][] = $pathname;
    }
    foreach ($deti as &$seznam) {
        usort($seznam, function ($a, $b) use ($uzly) {
            return [$uzly[$a]['position'], $uzly[$a]['title']]
               <=> [$uzly[$b]['position'], $uzly[$b]['title']];
        });
    }
    unset($seznam);

    return ['uzly' => $uzly, 'deti' => $deti];
}


function obsah_uzel_ze_souboru(string $soubor, string $pathname): array
{
    [$hlavicka, $telo] = obsah_hlavicka($soubor);
    $typ = $hlavicka['type'] ?? 'page';
    if (!isset(TYPY[$typ])) {
        throw new RuntimeException("neznámý typ '$typ' v $soubor");
    }
    return [
        'pathname'    => $pathname,
        'soubor'      => $soubor,
        'typ'         => $typ,
        'title'       => (string) ($hlavicka['title'] ?? ''),
        'nadpis'      => (string) ($hlavicka['heading'] ?? $hlavicka['title'] ?? ''),
        'popis'       => (string) ($hlavicka['description'] ?? ''),
        'position'    => (int) ($hlavicka['position'] ?? 0),
        'skryto'      => (bool) ($hlavicka['hidden'] ?? false),
        'cil'         => (string) ($hlavicka['location'] ?? ''),
        'kod'         => (int) ($hlavicka['code'] ?? 302),
        'destination' => (string) ($hlavicka['destination'] ?? ''),
        'novinky'     => (string) ($hlavicka['novinky'] ?? ''),
        'datum'       => obsah_datum($hlavicka['date'] ?? null),
        'autor'       => (string) ($hlavicka['author'] ?? ''),
        'file'        => (string) ($hlavicka['soubor'] ?? ''),
        'file_nazev'  => (string) ($hlavicka['soubor_nazev'] ?? ''),
        'telo'        => $telo,
    ];
}


/**
 * YAML hlavička + HTML tělo.  Stejný tvar jako ../fo data_parse_front_matter(),
 * ale datum se normalizuje jen když existuje — většina stránek ho nemá.
 */
function obsah_hlavicka(string $soubor): array
{
    $raw = file_get_contents($soubor);
    if (!str_starts_with($raw, "---\n")) {
        throw new RuntimeException("chybí YAML hlavička: $soubor");
    }
    $konec = strpos($raw, "\n---\n", 3);
    if ($konec === false) {
        throw new RuntimeException("neuzavřená YAML hlavička: $soubor");
    }
    $hlavicka = Yaml::parse(substr($raw, 4, $konec - 3)) ?: [];
    return [$hlavicka, substr($raw, $konec + 5)];
}


/** Symfony YAML parsuje nekvótované 2026-01-05 na timestamp; zpět na řetězec. */
function obsah_datum($hodnota): ?string
{
    if ($hodnota === null || $hodnota === '') {
        return null;
    }
    if (is_int($hodnota)) {
        return gmdate('Y-m-d', $hodnota);
    }
    return (string) $hodnota;
}


// ---------------------------------------------------------------- dotazy --

function obsah_uzel(string $pathname): ?array
{
    return obsah_strom()['uzly'][$pathname] ?? null;
}

function obsah_vsechny(): array
{
    return obsah_strom()['uzly'];
}

/** Potomci cesty, seřazení podle position (a názvu při shodě). */
function obsah_deti(string $pathname, bool $viditelne = true): array
{
    $strom = obsah_strom();
    $out = [];
    foreach ($strom['deti'][$pathname] ?? [] as $p) {
        $uzel = $strom['uzly'][$p];
        if ($viditelne && ($uzel['skryto'] || !typ_ma($uzel, 'v_menu'))) {
            continue;
        }
        $out[] = $uzel;
    }
    return $out;
}

/** Nejvyšší předek cesty (sekce), nebo uzel sám, je-li na první úrovni. */
function obsah_sekce(string $pathname): ?array
{
    if ($pathname === '/') {
        return null;
    }
    $prvni = explode('/', ltrim($pathname, '/'))[0];
    return obsah_uzel('/' . $prvni);
}

/**
 * Všechna pravidla přesměrování aktuálního webu: kořen, dokumenty typu
 * redirect a ruční dodatky.  Jediný zdroj pro build (zapisuje _redirects)
 * i pro vývojový server (obsluhuje je), aby se vývoj a produkce nerozešly.
 *
 * @return array<int, array{0:string,1:string,2:int}>  [cesta, cíl, kód]
 */
function obsah_presmerovani(): array
{
    $out = [];

    /* Kořen webu byl dokument třídy Komise s presenterem Link a chodil na
       svůj destination; ostatní přesměrování jsou dokumenty typu redirect.
       Obojí je v registru typů jako 'presmeruj'. */
    foreach (obsah_vsechny() as $pathname => $uzel) {
        if (!typ_ma($uzel, 'presmeruj')) {
            continue;
        }
        $cil = $uzel[typ_odkaz($uzel)] ?? '';
        if ($cil !== '') {
            $out[] = [$pathname, $cil, $uzel['kod']];
        }
    }
    sort($out);   /* _redirects je first-match-wins; pořadí musí být stabilní */

    $rucni = ROOT_DIR . 'assets/' . site_slug() . '/_redirects-rucni';
    if (is_file($rucni)) {
        foreach (file($rucni, FILE_IGNORE_NEW_LINES) as $radek) {
            $radek = trim($radek);
            if ($radek === '' || $radek[0] === '#') {
                continue;
            }
            $casti = preg_split('/\s+/', $radek);
            if (count($casti) >= 2) {
                $out[] = [$casti[0], $casti[1], (int) ($casti[2] ?? 301)];
            }
        }
    }
    return $out;
}


/**
 * Všechny adresy, které web obslouží: vykreslené stránky, soubory z files/
 * a zdroje přesměrování.  Jediná definice — používá ji kontrola obsahu
 * i kontrola kolizí v buildu, takže se linter nemůže rozejít s buildem.
 */
function obsah_routy(): array
{
    $routy = [];
    foreach (obsah_vsechny() as $pathname => $uzel) {
        if (typ_ma($uzel, 'stranka')) {
            $routy[$pathname] = true;
        }
    }
    $files = ROOT_DIR . 'files/' . site_slug();
    if (is_dir($files)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $files, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $routy[substr($f->getPathname(), strlen($files))] = true;
        }
    }
    foreach (obsah_presmerovani() as [$vzor, , ]) {
        $routy[rtrim($vzor, '*')] = true;
        $routy[$vzor] = true;
    }
    return $routy;
}


/**
 * Najde pravidlo pro danou cestu včetně gramatiky `*` / `:splat`, kterou
 * používá Cloudflare _redirects.  Bydlí tady, aby ji vývojový server
 * nemusel implementovat podruhé.
 *
 * @return array{0:string,1:int}|null  [cíl, kód]
 */
function presmerovani_najdi(string $cesta): ?array
{
    foreach (obsah_presmerovani() as [$vzor, $cil, $kod]) {
        if ($vzor === $cesta) {
            return [$cil, $kod];
        }
        if (str_ends_with($vzor, '*')) {
            $prefix = substr($vzor, 0, -1);
            if (str_starts_with($cesta, $prefix)) {
                return [str_replace(':splat', substr($cesta, strlen($prefix)), $cil), $kod];
            }
        }
    }
    return null;
}
