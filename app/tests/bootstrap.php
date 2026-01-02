<?php
/**
 * PHPUnit Test Bootstrap
 * Sets up the environment for running unit tests
 */

// Composer autoloader
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// Define application environment
define('APPLICATION_ENV', 'testing');

// Define path to application directory
define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Set up include path for Zend Framework and library classes
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));

// Register Zend autoloader for legacy classes
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('Jkl_');

// Set up module autoloader for Model_ classes
$moduleLoader = new Zend_Application_Module_Autoloader(array(
    'namespace' => '',
    'basePath'  => APPLICATION_PATH,
));

// Also register Model_ namespace
$autoloader->registerNamespace('Model_');

// Manually require custom view helpers (they don't follow Zend autoload path)
require_once APPLICATION_PATH . '/views/helpers/LoggedIn.php';
require_once APPLICATION_PATH . '/views/helpers/RestUrl.php';

// Set up timezone
date_default_timezone_set('Europe/Warsaw');

// Clean up Mockery after each test
if (class_exists('Mockery')) {
    register_shutdown_function(function() {
        \Mockery::close();
    });
}
