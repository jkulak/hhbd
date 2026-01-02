<?php

if (isset($_SESSION['count'])) {
    $_SESSION['count']++;
} else {
    $_SESSION['count'] = 1;
}

$smarty->assign('ctitle', 'Strona główna');

$smarty->assign('who', $_SESSION['adminusername'] . ' (id: ' . $_SESSION['adminuserid'] . ')');
$smarty->assign('body_template', 'site/main.tpl');
