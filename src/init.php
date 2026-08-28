<?php
/** Společný bootstrap pro build i vývojový server. */
date_default_timezone_set('Europe/Prague');
define('ROOT_DIR', dirname(__DIR__) . '/');

require_once ROOT_DIR . 'vendor/autoload.php';
require_once __DIR__ . '/sites.php';
require_once __DIR__ . '/functions/obsah.php';
require_once __DIR__ . '/functions/folib.php';

if (empty($GLOBALS['FO_SITE'])) {
    site_use(getenv('FO_SITE') ?: 'praha');
}
