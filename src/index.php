<?php
/**
 * Jediná šablona webu.  Řídí se PATH_INFO — stejně ji volá build.php ve
 * smyčce i router.php pro `php -S`, takže se vývoj a produkce nemůžou rozejít.
 */
require_once __DIR__ . '/init.php';

$pathname = cesta_requestu();
$uzel     = obsah_uzel($pathname);
$web      = site();

/* Typy bez vlastní stránky (redirect, dir, site, link) se nevykreslují. */
if ($uzel && !typ_ma($uzel, 'stranka')) {
    $uzel = null;
}
if ($uzel === null && PHP_SAPI !== 'cli') {
    http_response_code(404);   // pro dev server; v produkci řeší 404.html
}

$siroka = $uzel && typ_ma($uzel, 'siroka');

/* Ročník se nikde nenastavuje ručně, plyne z obsahu — viz aktualni_rocnik(). */
$rocnik = aktualni_rocnik();
$nadpis = $uzel['nadpis'] ?? 'Tato stránka neexistuje';
/* Nadpisy nesou HTML entity (&nbsp; kvůli zalomení), stará šablona je
   vypisovala neescapované ({!$heading}).  Do <title> patří text, ne značky. */
$nadpis_text = html_entity_decode(strip_tags($nadpis), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$titulek = $pathname === '/'
    ? 'Fyzikální olympiáda ' . $web['nazev']
    : $nadpis_text . ' – Fyzikální olympiáda ' . $web['nazev'];
$popis = $uzel['popis'] ?? '';
if ($popis === '' && $uzel) {
    $popis = zkrat_popis($uzel['telo']);
}
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="index,follow">
<meta name="keywords" content="fyzika, fyzikální olympiáda, fyzikalni olympiada, soutěž, <?= htmlspecialchars($web['nazev']) ?>">
<?php if ($popis !== ''): ?>
<meta name="description" content="<?= htmlspecialchars($popis, ENT_QUOTES) ?>">
<?php endif; ?>
<title><?= htmlspecialchars($titulek) ?></title>

<link rel="icon" href="/favicon.ico?v=3" sizes="48x48">
<link rel="icon" type="image/svg+xml" href="/favicon.svg?v=3">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="home" href="/">

<link rel="preload" href="/fonts/space-grotesk-700-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/space-grotesk-700-latin-ext.woff2" as="font" type="font/woff2" crossorigin>

<link rel="stylesheet" href="<?= asset('/css/bootstrap.css') ?>">
<link rel="stylesheet" href="<?= asset('/css/layout.css') ?>">
<link rel="stylesheet" href="<?= asset('/css/content.css') ?>">
<link rel="stylesheet" href="<?= asset('/css/obsah-legacy.css') ?>">
<link rel="stylesheet" media="print" href="<?= asset('/css/print.css') ?>">

<!-- barva webu; jediné, čím se oba weby vizuálně liší -->
<style>:root{--akcent:<?= $web['akcent'] ?>;--akcent-odkaz:<?= $web['akcent_odkaz'] ?>;--akcent-svetla:<?= $web['akcent_svetla'] ?>}</style>
</head>

<body>
<div class="container">
    <div id="header" class="row">
        <div class="col-xs-12 col-lg-5" id="logo">
            <a href="/"><img src="/pic/logo-fo.svg" alt=""></a>
            <a href="/"><span>FYZIKÁLNÍ OLYMPIÁDA</span></a>
            <div class="kraj-blok">
                <img class="mapka" src="<?= mapa_kraje_url() ?>" width="470" height="269"
                     alt="Poloha kraje na mapě České republiky">
                <div>
                    <span class="kraj"><?= htmlspecialchars($web['nazev']) ?></span>
<?php if ($rocnik): ?>
                    <span class="rocnik"><b><?= $rocnik ?>. ročník</b> &middot; <?= skolni_rok($rocnik) ?></span>
<?php endif; ?>
                </div>
            </div>
            <!-- tlačítko i #main-nav musí být na každé stránce: fo.js na ně
                 věší posluchač bez kontroly na null -->
            <button type="button" id="menu-tlacitko" aria-expanded="false"><span class="linky" aria-hidden="true"><span></span><span></span><span></span></span>Menu</button>
        </div>

        <div class="col-xs-12 col-lg-7 navigation" id="main-nav">
            <?= navigace($pathname) ?>
        </div>

        <div class="clearfix"></div>
    </div>

    <hr>

    <div id="main-two-columns" class="row">
        <div id="main-content" class="<?= $siroka ? 'col-xs-12' : 'col-xs-12 col-md-9' ?>">
<?php if ($uzel === null): ?>
            <h1>Tato stránka neexistuje</h1>
            <p>Zkuste <a href="/">titulní stránku</a> nebo <a href="/kontakty">kontakty</a>.</p>
<?php else: ?>
            <h1><?= $nadpis ?></h1>
            <?php include ROOT_DIR . 'src/stranky/' . $uzel['typ'] . '.php'; ?>
<?php endif; ?>
        </div>
<?php if (!$siroka): ?>
        <div id="bocni-menu" class="col-xs-12 col-md-3">
            <?= bocni_menu($pathname) ?>
            <div class="blok">
                <h3>Odkazy</h3>
                <ul class="menu">
                    <li><a href="https://fyzikalniolympiada.cz/">Fyzikální olympiáda</a></li>
                    <li><a href="https://osmo.fyzikalniolympiada.cz/">Odevzdávací systém Osmo</a></li>
                </ul>
            </div>
        </div>
<?php endif; ?>
        <div class="clearer">&nbsp;</div>
    </div>

    <div id="footer">
        <p>Krajská komise Fyzikální olympiády <?= htmlspecialchars($web['nazev']) ?> &middot;
           <a href="mailto:<?= $web['email'] ?>"><?= $web['email'] ?></a></p>
    </div>
</div>
<script src="<?= asset('/js/fo.js') ?>"></script>
</body>
</html>
