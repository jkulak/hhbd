<?php

// Database configuration from environment variables
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'hhbd';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME') ?: 'hhbd';
$db_port = getenv('DB_PORT') ?: 3306;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$sql = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
if (!$sql) {
    print ("Nie mozna sie polaczyc z baza: " . mysqli_connect_error() . "<br>");
    exit();
}
mysqli_set_charset($sql, 'utf8');

if (!mysqli_select_db($sql, $db_name)) {
    print ("Nie mozna odnalezc bazy: $db_name (" . mysqli_error($sql) . ")<br>");
    exit();
}
