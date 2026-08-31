<?php
/**
 * Sestaví oba weby do dist/<web>/.
 *
 * Postup pro každý web: nejdřív velký obsah z files/<web>/ (publikuje se
 * bez prefixu), pak vykreslené stránky, assety, _redirects a sitemap.
 */
require_once dirname(__DIR__) . '/src/init.php';

$DIST = ROOT_DIR . 'dist/';
$ASSET_ADRESARE = ['css', 'js', 'fonts', 'pic'];
$ASSET_SOUBORY  = ['favicon.ico', 'favicon.svg', 'favicon-16x16.png', 'favicon-32x32.png',
                   'apple-touch-icon.png', 'icon-192.png', 'icon-512.png',
                   '_headers'];   /* site.webmanifest je per-web */

/* Každý zápis zakládá adresáře — ../fo tady má chybu (build.php:118 volá
   holé copy() do neexistujícího dist/data/news, všech 9 obrázků je 404). */
function zapis(string $cesta, string $obsah): void
{
    @mkdir(dirname($cesta), 0777, true);
    file_put_contents($cesta, $obsah);
}

function zkopiruj(string $z, string $do): void
{
    @mkdir(dirname($do), 0777, true);
    copy($z, $do);
}

function vykresli(string $pathname): string
{
    $_SERVER['PATH_INFO'] = $pathname;
    ob_start();
    include ROOT_DIR . 'src/index.php';
    return ob_get_clean();
}

/** Obsah _redirects — pravidla dodává src/functions/obsah.php. */
function presmerovani_soubor(): string
{
    $radky = ['# Generováno buildem; needitovat.',
              '# Dokumenty typu redirect + assets/<web>/_redirects-rucni.'];
    foreach (obsah_presmerovani() as [$cesta, $cil, $kod]) {
        $radky[] = "$cesta  $cil  $kod";
    }
    return implode("\n", $radky) . "\n";
}

function sitemap_xml(array $stranky): string
{
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($stranky as $pathname) {
        $out .= '  <url><loc>' . htmlspecialchars(site_url($pathname), ENT_XML1)
              . "</loc></url>\n";
    }
    return $out . "</urlset>\n";
}


$t0 = microtime(true);
exec('rm -rf ' . escapeshellarg($DIST));
$celkem = 0;

foreach (array_keys(site_definice()) as $slug) {
    site_use($slug);
    $out = $DIST . $slug . '/';
    @mkdir($out, 0777, true);

    /* 1. velký obsah — files/<web>/X se publikuje jako /X */
    $files = ROOT_DIR . 'files/' . $slug;
    if (is_dir($files)) {
        exec('cp -aT ' . escapeshellarg($files) . ' ' . escapeshellarg($out));
    }

    /* 2. stránky */
    $stranky = [];
    foreach (obsah_vsechny() as $pathname => $uzel) {
        if (!typ_ma($uzel, 'stranka')) {
            continue;
        }
        $jmeno = $pathname === '/' ? 'index' : ltrim($pathname, '/');
        /* Past /404: soubor 404.html uvnitř adresáře berou Pages jako
           chybovou stránku celé sekce. */
        $cil = basename($jmeno) === '404' ? "$jmeno/index.html" : "$jmeno.html";
        if (is_file($out . $cil)) {
            fwrite(STDERR, "KOLIZE: stránka $pathname přepisuje files/$slug/$cil\n");
        }
        zapis($out . $cil, vykresli($pathname));
        $stranky[] = $pathname;
    }
    zapis($out . '404.html', vykresli('/tato-stranka-neexistuje'));

    /* 3. assety: sdílené, pak per-web (favicon apod. smí přebít) */
    foreach ($ASSET_ADRESARE as $dir) {
        $zdroj = ROOT_DIR . 'assets/' . $dir;
        if (is_dir($zdroj)) {
            exec('cp -aT ' . escapeshellarg($zdroj) . ' ' . escapeshellarg($out . $dir));
        }
    }
    foreach ($ASSET_SOUBORY as $f) {
        if (is_file(ROOT_DIR . 'assets/' . $f)) {
            zkopiruj(ROOT_DIR . 'assets/' . $f, $out . $f);
        }
    }
    foreach (glob(ROOT_DIR . 'assets/' . $slug . '/*') as $f) {
        if (is_file($f) && !str_starts_with(basename($f), '_redirects')) {
            zkopiruj($f, $out . basename($f));
        }
    }

    /* 4. mapa s vybarveným krajem (přepisuje zdrojovou kopii z assets/) */
    zapis($out . 'pic/mapa-kraju.svg', mapa_kraje_svg());

    /* 5. přesměrování a sitemapa */
    zapis($out . '_redirects', presmerovani_soubor());
    sort($stranky);
    zapis($out . 'sitemap.xml', sitemap_xml($stranky));
    zapis($out . 'robots.txt',
          "User-agent: *\nAllow: /\n\nSitemap: " . site_url('/sitemap.xml') . "\n");

    $celkem += count($stranky);
    printf("%-6s %3d stránek\n", $slug, count($stranky));
}

printf("celkem %d stránek za %.1f s\n", $celkem, microtime(true) - $t0);
