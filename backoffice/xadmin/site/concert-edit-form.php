<?php

$id = $_POST['id'];
$smarty->assign('id', $id);

$result = mysql_query('SELECT id, title, description, date, start, cityid, cost, place, website, poster FROM concerts WHERE id="' . $id . '"');
$row = mysql_fetch_array($result);
$smarty->assign('title', $row['title']);
$cityid = $row['cityid'];
$posterfile = $row['poster'];
$smarty->assign('description', $row['description']);
$smarty->assign('start', $row['start']);
$smarty->assign('date', $row['date']);
$smarty->assign('cost', $row['cost']);
$smarty->assign('place', $row['place']);
$smarty->assign('website', $row['website']);

$result = mysql_query('SELECT artistid FROM artist_concert_lookup WHERE concertid="' . $id . '"');
while($row = mysql_fetch_array($result)){
	$artists .= $row['artistid'] . ',';
	} // while
$smarty->assign('artists', substr($artists, 0, -1));

$cities = array();
$result = mysql_query('SELECT id, name FROM cities ORDER BY name');
while($row = mysql_fetch_array($result)){
	array_push($cities, '<option value="' . $row['id'] . '" ' . ($cityid == $row['id'] ? 'selected' : '') . '>' . $row['name'] . '</option>');
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
$smarty->assign('mainsection', '<font color="#6f91ac">EDYCJA KONCERTU</font>');
$smarty->assign('ctitle', 'EDYTUJ KONCERT');
$smarty->assign('body_template', 'site/concert-edit-form.tpl');
//*****************************************************************
?>