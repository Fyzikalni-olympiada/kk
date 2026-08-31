<?php
/**
 * Router pro `php -S` — jeden web na jeden port, vybraný přes FO_SITE,
 * aby byly adresy shodné s produkcí (make dev / make dev-kvary).
 *
 * Vrácené TRUE znamená „obslouženo“, FALSE „ať to vezme vestavěný server“.
 */
require_once dirname(__DIR__) . '/src/init.php';

$cesta = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

/* V produkci žádné PHP není — ať se to pozná i lokálně. */
if (str_ends_with($cesta, '.php')) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

$servuj = function (string $soubor): bool {
    $typy = [
        'css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2',
        'pdf' => 'application/pdf', 'xls' => 'application/vnd.ms-excel',
        'doc' => 'application/msword', 'zip' => 'application/zip',
        'json' => 'application/json', 'webmanifest' => 'application/manifest+json',
        'txt' => 'text/plain', 'html' => 'text/html',
    ];
    $ext = strtolower(pathinfo($soubor, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($typy[$ext] ?? 'application/octet-stream'));
    readfile($soubor);
    return true;
};

/* Mapa se vyrábí, nesmí se vzít zdrojová kopie z assets/. */
if ($cesta === '/pic/mapa-kraju.svg') {
    header('Content-Type: image/svg+xml');
    echo mapa_kraje_svg();
    return true;
}

/* Stejné pořadí jako v buildu: files/<web> a assets se publikují bez prefixu. */
if ($cesta !== '/' && !str_contains($cesta, '..')) {
    foreach (['files/' . site_slug(), 'assets/' . site_slug(), 'assets'] as $koren) {
        $soubor = ROOT_DIR . $koren . $cesta;
        if (is_file($soubor)) {
            return $servuj($soubor);
        }
    }
}

/* Přesměrování ať fungují i lokálně, jinak „u mě to jde, v produkci 404“. */
if ($pravidlo = presmerovani_najdi($cesta)) {
    header('Location: ' . $pravidlo[0], true, $pravidlo[1]);
    return true;
}

$_SERVER['PATH_INFO'] = $cesta;
require ROOT_DIR . 'src/index.php';
return true;
