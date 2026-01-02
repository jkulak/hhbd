<?php

session_start();

$_SESSION['adminusername'] = null;
$_SESSION['adminuserid'] = null;

$_SESSION['conc_priv'] = null;
$_SESSION['news_priv'] = null;

header("Location: index.php?er=4"); 

?>