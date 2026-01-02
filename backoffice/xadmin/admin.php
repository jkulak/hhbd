<?php

session_start();

if (!isset($_SESSION['adminusername'])) { header('Location: index.php?er=666'); exit; }

require_once('smarty/SmartyDir.class.php');
$smarty = new SmartyDir();
$smarty->caching = 0;

include ('include/connect-to-database.php');
include ('include/functions.php');

// USTAWIENIA
$fileprefix = 'www_hhbd_pl_polski_hip_hop_';
$s = $_GET['s'];

$smarty->assign('news_priv', $_SESSION['news_priv']);
$smarty->assign('conc_priv', $_SESSION['conc_priv']);
$smarty->assign('week_priv', $_SESSION['week_priv']);
$smarty->assign('lala_priv', $_SESSION['lala_priv']);

include ('load-site.php');

$smarty->display('admin.tpl');
