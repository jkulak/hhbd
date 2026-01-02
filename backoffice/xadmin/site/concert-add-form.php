<?php

$cities = array();
$result = mysql_query('SELECT id, name FROM cities ORDER BY name');
while($row = mysql_fetch_array($result)){
	array_push($cities, '<option value="' . $row['id'] . '">' . $row['name'] . '</option>');
	} // while
$smarty->assign('cities', $cities);
 

$posters = array();
$path = dirname( $_SERVER['PATH_TRANSLATED'] );
$path = substr($path, 0, -6);
$mydir = $path . 'imgs/database/posters';
$dir = opendir($mydir);	  
while ($file = readdir($dir)) {
	if (($file != '.') AND ($file != '..' ))
	array_push($posters, '<option value="' . $file . '" ' . ($posterfile == $file ? 'selected' : '') . '>' . $file . '</option>');
	//
//		print ('<option value="' . $file . '">' . $file . '</option>' . "\n");
   	}
closedir($dir); 
$smarty->assign('posters', $posters);

//*****************************************************************	
$smarty->assign('mainsection', '<font color="#6f91ac">DODAWANIE KONCERTU</font>');
$smarty->assign('ctitle', 'DODAJ KONCERT');
$smarty->assign('body_template', 'site/concert-add-form.tpl');
//*****************************************************************
?>