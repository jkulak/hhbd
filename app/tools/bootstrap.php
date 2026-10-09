<?php

/**
 * Boots the application for a command-line tool, as public/index.php boots it for a request,
 * without dispatching one: configuration with the environment's overrides, the registry the
 * models read, and the routes the models build page URLs with.
 */

require_once __DIR__ . '/../vendor/autoload.php';

defined('APPLICATION_NAME') || define('APPLICATION_NAME', 'hhbd.pl');
defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));

$application = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap();
