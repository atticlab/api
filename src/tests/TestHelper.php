<?php

use Phalcon\Di;
use Phalcon\Di\FactoryDefault;
use Phalcon\Loader;

ini_set("display_errors", 1);
error_reporting(E_ALL);

define("ROOT_PATH", __DIR__);

define('APP_PATH', realpath(__DIR__ . '/..') . '/');
define('SERVICE_PATH', APP_PATH . 'common/services/');
define('CONFIG_PATH', APP_PATH . 'common/config/');

set_include_path(
    ROOT_PATH . PATH_SEPARATOR . get_include_path()
);

// Required for phalcon/incubator
include __DIR__ . "/../vendor/autoload.php";

// Use the application autoloader to autoload the classes
// Autoload the dependencies found in composer
$loader = new Loader();

$loader->registerDirs(
    [
        ROOT_PATH,
    ]
);

# Register common namespaces
$loader->registerNamespaces([
    'App\Models'      => APP_PATH . 'common/models/',
    'App\Lib'         => APP_PATH . 'common/lib/',
    'SWP\Services'    => SERVICE_PATH,
]);

$loader->register();

//TODO: read from another place