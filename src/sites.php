<?php
/**
 * Definice webů.  Jeden kód obsluhuje dva weby, proto se nic, co na webu
 * závisí, nesmí uložit do konstanty — konstanty se nedají předefinovat
 * a smyčka přes weby v jednom procesu by nešla.  Proto site_url() místo
 * BASE_URL, kterou má sesterský projekt ../fo.
 */

function site_definice(): array
{
    return [
        'praha' => [
            'nazev'         => 'Praha',
            'domena'        => 'praha.fyzikalniolympiada.cz',
            'email'         => 'praha@fyzikalniolympiada.cz',
            'akcent'        => '#AA3939',
            'akcent_odkaz'  => '#801515',
            'akcent_svetla' => '#FFAAAA',
        ],
        'kvary' => [
            'nazev'         => 'Karlovy Vary',
            'domena'        => 'kvary.fyzikalniolympiada.cz',
            'email'         => 'kvary@fyzikalniolympiada.cz',
            'akcent'        => '#BA7129',
            'akcent_odkaz'  => '#9F5205',
            'akcent_svetla' => '#F8D0A8',
        ],
    ];
}

/** Přepne aktuální web; volá build.php ve smyčce i router.php podle FO_SITE. */
function site_use(string $slug): void
{
    if (!isset(site_definice()[$slug])) {
        throw new InvalidArgumentException("neznámý web: $slug");
    }
    $GLOBALS['FO_SITE'] = $slug;
}

function site_slug(): string
{
    if (empty($GLOBALS['FO_SITE'])) {
        throw new RuntimeException('web není nastaven, chybí site_use()');
    }
    return $GLOBALS['FO_SITE'];
}

function site(): array
{
    return site_definice()[site_slug()];
}

/** Absolutní URL; při buildu produkční doména, ve vývoji aktuální host. */
function site_url(string $cesta = '/'): string
{
    $zaklad = PHP_SAPI === 'cli'
        ? 'https://' . site()['domena']
        : 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');
    return $zaklad . '/' . ltrim($cesta, '/');
}
