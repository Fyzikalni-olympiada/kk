<?php
/**
 * Kontrola obsahu — běží v CI i lokálně (make check).
 *
 * Tvrdé chyby končí nenulově a shodí build: rozbitá hlavička, neznámý typ,
 * <h1> v těle (nadpis vypisuje šablona), <script>, file:/// odkaz,
 * http:// na vlastní doménu, mrtvý interní odkaz.
 * Ostatní se jen vypíše, aby bylo vidět, co ještě nese stopy WYSIWYG editoru.
 */
require_once dirname(__DIR__) . '/src/init.php';

$chyby = [];
$varovani = [];
$pocty = ['stránek' => 0];

foreach (array_keys(site_definice()) as $slug) {
    site_use($slug);
    $cile = obsah_routy();

    foreach (obsah_vsechny() as $pathname => $uzel) {
        $pocty['stránek']++;
        $kde = str_replace(ROOT_DIR, '', $uzel['soubor']);
        $telo = $uzel['telo'];

        if ($uzel['title'] === '' && $uzel['nadpis'] === '') {
            $chyby[] = "$kde: chybí title i heading";
        }
        if (preg_match('~<h1[\s>]~i', $telo)) {
            $chyby[] = "$kde: <h1> v těle — nadpis vypisuje šablona z heading";
        }
        if (preg_match('~<script~i', $telo)) {
            $chyby[] = "$kde: <script> v obsahu";
        }
        if (preg_match('~file:///~i', $telo)) {
            $chyby[] = "$kde: odkaz file:///";
        }
        if (preg_match('~http://[a-z0-9.-]*fyzikalniolympiada\.cz~i', $telo, $m)) {
            $chyby[] = "$kde: $m[0] — vlastní doména musí být přes https";
        }
        if ($uzel['typ'] === 'redirect' && $uzel['cil'] === '') {
            $varovani[] = "$kde: redirect bez cíle";
        }

        /* soubor ke stažení je v hlavičce, ne v těle — kontrola odkazů
           v těle by ho minula */
        if ($uzel['file'] !== '' && !isset($cile[$uzel['file']])) {
            $chyby[] = "$kde: soubor: {$uzel['file']} neexistuje";
        }

        /* interní odkazy */
        if (preg_match_all('~\b(?:href|src)\s*=\s*["\']?(/[^"\'>\s#?]*)~i', $telo, $mm)) {
            foreach ($mm[1] as $cil) {
                if (!isset($cile[$cil])) {
                    $varovani[] = "$kde: mrtvý odkaz $cil";
                }
            }
        }

        /* stopy po WYSIWYG editoru — jen informativně */
        foreach (['<font' => '~<font~i', 'align=' => '~\balign\s*=~i',
                  'width=' => '~\bwidth\s*=~i', 'bgcolor=' => '~\bbgcolor\s*=~i',
                  'moduletable' => '~moduletable~i',
                  'tabulka bez třídy' => '~<table(?![^>]*class)~i'] as $co => $re) {
            $n = preg_match_all($re, $telo);
            if ($n) {
                $pocty[$co] = ($pocty[$co] ?? 0) + $n;
            }
        }
    }
}

foreach ($pocty as $co => $n) {
    printf("  %-22s %d\n", $co, $n);
}
if ($varovani) {
    echo "\nvarování (" . count($varovani) . "):\n";
    foreach (array_slice($varovani, 0, 30) as $v) {
        echo "  $v\n";
    }
}
if ($chyby) {
    echo "\nCHYBY (" . count($chyby) . "):\n";
    foreach ($chyby as $c) {
        echo "  $c\n";
    }
    exit(1);
}
echo "\nobsah v pořádku\n";
