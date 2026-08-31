<?php
/** Prezentační pomocníky: navigace, boční menu, odkazy, formátování. */

const MESICE = ['', 'ledna', 'února', 'března', 'dubna', 'května', 'června',
                'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];

/** Cesta požadavku z PATH_INFO. */
function cesta_requestu(): string
{
    $p = $_SERVER['PATH_INFO'] ?? '/';
    $p = '/' . ltrim($p, '/');
    return $p !== '/' ? rtrim($p, '/') : '/';
}

/**
 * Adresa souboru v assets/ s otiskem obsahu (Cloudflare query string
 * v klíči cache ignoruje).  Memoizováno — bez toho se každý z pěti
 * stylů a fo.js hashoval znovu na každé z 499 vykreslených stránek.
 */
function asset(string $cesta): string
{
    static $memo = [];
    if (!isset($memo[$cesta])) {
        $soubor = ROOT_DIR . 'assets/' . ltrim($cesta, '/');
        $memo[$cesta] = is_file($soubor)
            ? $cesta . '?v=' . substr(md5_file($soubor), 0, 8)
            : $cesta;
    }
    return $memo[$cesta];
}

/** Cíl odkazu: typy s vlastním cílem podle registru, ostatní vlastní adresa. */
function odkaz_cil(array $uzel): string
{
    $pole = typ_odkaz($uzel);
    if ($pole !== null) {
        return $uzel[$pole];        /* prázdné = uzel se vypíše jako text */
    }
    return $uzel['pathname'];
}

/** <a> na uzel; redirect bez cíle se vypíše jako prostý text (viz d4f94da). */
function odkaz_uzlu(array $uzel): string
{
    $cil = odkaz_cil($uzel);
    $text = htmlspecialchars($uzel['title'], ENT_QUOTES);
    if ($cil === '') {
        return $text;
    }
    $extern = str_starts_with($cil, 'http');
    return sprintf('<a href="%s"%s>%s</a>',
        htmlspecialchars($cil, ENT_QUOTES),
        $extern ? ' target="_blank" rel="noopener"' : '',
        $text);
}

/** Je uzel na cestě k aktuální stránce? */
function menu_obsahuje(string $pathname, string $aktualni): bool
{
    return $pathname === $aktualni || str_starts_with($aktualni, $pathname . '/');
}

/** Horní navigace: sekce prvni úrovně. */
function navigace(string $aktualni): string
{
    $out = '<ul class="tabbed">';
    foreach (obsah_deti('/') as $uzel) {
        $tridy = menu_obsahuje($uzel['pathname'], $aktualni) ? ' class="current-tab"' : '';
        $out .= "<li$tridy>" . odkaz_uzlu($uzel) . '</li>';
    }
    return $out . '</ul>';
}

/**
 * Boční menu: potomci sekce, do níž stránka patří, s rozbalenou aktuální
 * větví.  Reprodukuje Base::beforeRender() staré aplikace (Base.php:34-42).
 */
function bocni_menu(string $aktualni): string
{
    $sekce = obsah_sekce($aktualni);
    if (!$sekce) {
        return '';
    }
    $polozky = obsah_deti($sekce['pathname']);
    if (!$polozky) {
        return '';
    }
    $out = '<div class="blok"><h3>' . htmlspecialchars($sekce['title'], ENT_QUOTES)
         . '</h3><ul class="menu">';
    foreach ($polozky as $uzel) {
        $akt = menu_obsahuje($uzel['pathname'], $aktualni);
        $out .= '<li' . ($akt ? ' class="current"' : '') . '>' . odkaz_uzlu($uzel);
        if ($akt) {
            $vnorene = obsah_deti($uzel['pathname']);
            if ($vnorene) {
                $out .= '<ul>';
                foreach ($vnorene as $d) {
                    $out .= '<li' . (menu_obsahuje($d['pathname'], $aktualni) ? ' class="current"' : '')
                          . '>' . odkaz_uzlu($d) . '</li>';
                }
                $out .= '</ul>';
            }
        }
        $out .= '</li>';
    }
    return $out . '</ul></div>';
}

/** Seznam potomků — náhrada za {control overview}. */
function seznam_deti(string $pathname): string
{
    $deti = obsah_deti($pathname);
    if (!$deti) {
        return '';
    }
    $out = '<ul class="rozcestnik">';
    foreach ($deti as $uzel) {
        $out .= '<li>' . odkaz_uzlu($uzel) . '</li>';
    }
    return $out . '</ul>';
}

/**
 * Mapa krajů s vybarveným krajem aktuálního webu.
 *
 * Vkládat 22 kB SVG do každé z 497 stránek by dist zdvojnásobilo, a <img>
 * zase nedědí barvy stránky. Proto se z jednoho zdroje vyrábí soubor na web:
 * build ho zapíše do dist/, vývojový server ho servíruje z téže funkce, takže
 * se nemají jak rozejít.
 */
function mapa_kraje_svg(): string
{
    static $memo = [];
    $slug = site_slug();
    if (!isset($memo[$slug])) {
        $svg = file_get_contents(ROOT_DIR . 'assets/pic/mapa-kraju.svg');
        /* inline styl, ne atribut fill — <style> uvnitř SVG by atribut přebil */
        $memo[$slug] = str_replace(
            '<path data-kraj="' . $slug . '"',
            '<path style="fill:' . site()['akcent'] . '" data-kraj="' . $slug . '"',
            $svg);
    }
    return $memo[$slug];
}


/** Adresa mapy s otiskem skutečně vydaného obsahu (barva je v něm zapečená). */
function mapa_kraje_url(): string
{
    return '/pic/mapa-kraju.svg?v=' . substr(md5(mapa_kraje_svg()), 0, 8);
}


/**
 * Ročník FO, do kterého datum spadá.  Ročník běží od září do června,
 * takže 67. ročník = školní rok 2025/2026: prosinec 2025 i květen 2026
 * patří do téhož ročníku.
 */
function rocnik_z_data(?string $datum): ?int
{
    if (!$datum) {
        return null;
    }
    $ts = strtotime($datum);
    if ($ts === false) {
        return null;
    }
    $rok = (int) date('Y', $ts);
    return (int) date('n', $ts) >= 9 ? $rok - 1958 : $rok - 1959;
}


/**
 * Novinky nejnovějšího ročníku, který nějaké má.
 *
 * Ročník se bere z novinek, ne z dnešního data — jinak by weby po prázdninách
 * zůstaly bez novinek a musely by se přestavovat jen proto, že plyne čas.
 */
function novinky_aktualniho_rocniku(array $items): array
{
    $rocniky = array_filter(array_map(fn($i) => rocnik_z_data($i['datum']), $items));
    if (!$rocniky) {
        return $items;
    }
    $aktualni = max($rocniky);
    return array_values(array_filter(
        $items, fn($i) => rocnik_z_data($i['datum']) === $aktualni));
}


/**
 * Ročník, kterým se web hlásí v hlavičce.
 *
 * Bere se z obsahu, ne z dnešního data: novinky jsou přesnější (mění se
 * v průběhu roku), ale Karlovy Vary žádnou stránku novinek nemají, takže
 * se sáhne po nejvyšším ročníku ve výsledkových listinách. Kdyby se ročník
 * počítal ze dneška, ukazoval by web po prázdninách ročník, o kterém nic
 * nemá — a musel by se přestavovat jen proto, že plyne čas.
 */
function aktualni_rocnik(): ?int
{
    static $memo = [];
    $slug = site_slug();
    if (isset($memo[$slug])) {
        return $memo[$slug];
    }
    $rocniky = [];
    foreach (obsah_vsechny() as $pathname => $uzel) {
        if ($uzel['typ'] === 'news') {
            $novinky = novinky_aktualniho_rocniku(
                obsah_deti($uzel['novinky'] ?: $pathname, false));
            if ($novinky) {
                $rocniky[] = rocnik_z_data($novinky[0]['datum']);
            }
        }
        /* /vysledky/67-rocnik i /vysledky/64 — obě konvence se používají */
        if (preg_match('~^/vysledky/(\d{2})(-|$)~', $pathname, $m)) {
            $rocniky[] = (int) $m[1];
        }
    }
    $rocniky = array_filter($rocniky);
    return $memo[$slug] = $rocniky ? max($rocniky) : null;
}


/** Školní rok ročníku: 67 => '2025/2026'. */
function skolni_rok(int $rocnik): string
{
    return ($rocnik + 1958) . '/' . ($rocnik + 1959);
}


/** '2019-06-13 15:33' => '13. června 2019' */
function datum_cesky(?string $datum): string
{
    if (!$datum) {
        return '';
    }
    $ts = strtotime($datum);
    if ($ts === false) {
        return '';
    }
    return (int) date('j', $ts) . '. ' . MESICE[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/** Zkrácený popis pro <meta description>, převzato z ../fo folib.php:354. */
function zkrat_popis(string $html, int $limit = 155): string
{
    $text = trim(preg_replace('/\s+/u', ' ',
        html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    $kratky = mb_substr($text, 0, $limit);
    $mezera = mb_strrpos($kratky, ' ');
    return rtrim($mezera ? mb_substr($kratky, 0, $mezera) : $kratky, ',.;:') . '…';
}
