<?php

/**
 * bootstrap file.
 *
 * @copyright  Copyright (c) 2010 Jan Prachař (Intya)
 * @package    Intya www
 */


use Nette\Environment;
use Nette\Application\Route;


require LIBS_DIR . '/eywa/loader.php';

// Configure application
$application = Environment::getApplication();

$application->onStartup[] = function() {
    //dibi::connect(Environment::getConfig('database'));
};

$application->run();