<?php

if (!isset($_SESSION['adminusername'])) {
    header('Location: /');
	exit();
    }

$songid = $_POST['songid'];
$lalaid = $_POST['lalaid'];

$sql = 'UPDATE lalalab_songs SET ringtoneurl="' . $lalaid . '" WHERE songid="' . $songid . '"';
//print ($sql);
$result = mysql_query($sql);
if (mysql_affected_rows() == 0) {
	$sql = 'INSERT INTO lalalab_songs (songid, ringtoneurl) VALUES ("' . $songid . '", "' . $lalaid . '")';
	$result = mysql_query($sql);	
	}
	
if ($result) {
    $smarty->assign('info', 'Nowa postaæ tygodnia zosta³a dodany do bazy.');
	header('Location: /xadmin/admin.php?s=lala_dodaj_form');
	}
else {
   	$smarty->assign('info', '<font color=red>Nowa postaæ tygodnia NIE zosta³a dodana do bazy.</font> (' . mysql_error() . ')');
   	

	//*****************************************************************	
	$smarty->assign('ctitle', 'DODAWANIE POSTACI TYGODNIA');
	$smarty->assign('mainsection', '<font color="#6f91ac">POSTAÆ TYGODNIA</font>');
	$smarty->assign('body_template', 'site/final-info.tpl');
	//*****************************************************************
	}
?>