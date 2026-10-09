<?php

// Composer autoloader
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// PHP's errors, uncaught exceptions and fatal errors as JSON lines (#101), from the first line
// of the request on.
Jkl_Log::installHandlers('php');

// Define application name
defined('APPLICATION_NAME')
    || define('APPLICATION_NAME', $_SERVER['SERVER_NAME']);

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Keep library/ on include_path for legacy compatibility
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

$application->bootstrap()->run();
