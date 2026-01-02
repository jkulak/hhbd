<?php

$titles = array();
$ids = array();

$result = mysql_query('SELECT id, title, date FROM concerts ORDER BY date DESC');
while ( $row = mysql_fetch_array($result) ) {
	array_push($titles, $row['date'] . ' - ' . $row['title']);
	array_push($ids, $row['id']);
	}
$smarty->assign('titles', $titles);
$smarty->assign('ids', $ids);	  

//*****************************************************************	
$smarty->assign('mainsection', '<font color="#6f91ac">EDYCJA KONCERTU</font>');
$smarty->assign('ctitle', 'WYBIERZ KONCERT DO EDYCJI');
$smarty->assign('body_template', 'site/concert-edit-select.tpl');
//*****************************************************************
?>
               