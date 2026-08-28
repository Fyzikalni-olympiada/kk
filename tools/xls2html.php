<?php
/**
 * Převede jeden .xls (surové bajty přílohy z CouchDB) na HTML tabulku
 * stejným kódem, jakým to dělala živá aplikace.
 *
 *   php tools/xls2html.php <soubor.xls> [list]
 *
 * Na stdout jde tabulka, při chybě se hlásí na stderr a končí nenulově.
 * Knihovna zapisuje dynamické vlastnosti (v PHP 8.2+ deprecated) — tiší se
 * jen E_DEPRECATED, nikdy ne skutečné chyby.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/vendor/excel_reader2.php';

if ($argc < 2) {
    fwrite(STDERR, "použití: php tools/xls2html.php <soubor.xls> [list]\n");
    exit(2);
}
$path = $argv[1];
$sheet = isset($argv[2]) ? (int) $argv[2] : 0;

$data = @file_get_contents($path);
if ($data === false || $data === '') {
    fwrite(STDERR, "nelze číst nebo prázdný soubor: $path\n");
    exit(3);
}
if (substr($data, 0, 8) !== "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1") {
    fwrite(STDERR, "není OLE2 (chybí magic d0cf11e0): $path\n");
    exit(4);
}

/* Konstruktor bere SUROVÁ DATA, ne cestu — patch eywy na řádku 105. */
$xls = new Spreadsheet_Excel_Reader($data, true, 'utf8');

if (!empty($xls->error)) {
    fwrite(STDERR, "chyba čtečky ($xls->error): $path\n");
    exit(5);
}

$html = $xls->render($sheet);

/* Normalizace: cellpadding do CSS nepatří a legacy stránky z ../fo/kvary
   mají stejnou tabulku bez něj — ať jsou obě větve migrace shodné. */
$html = str_replace('<table class="vysledkovka" cellpadding=3>',
                    '<table class="vysledkovka">', $html);

if (substr_count($html, '<tr') < 2) {
    fwrite(STDERR, "podezřele prázdná tabulka (<2 řádky): $path\n");
    exit(6);
}

echo $html;
