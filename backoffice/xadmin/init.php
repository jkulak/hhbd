<?php

if (!isset($_SESSION['adminusername'])) {
    header('Location: /');
    exit();
}

define('SLUG_LENGTH', 35);

// Read configuration file
$config = parse_ini_file('config/app.ini');

require 'vendor/autoload.php';
