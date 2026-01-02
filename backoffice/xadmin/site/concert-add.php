<?php

$smarty->assign('ctitle', 'DODAWANIE KONCERTU');
	
	//function createthumbnail($dstfilename, $srcfilename, $frmcolor){
	//	$image_p = imagecreatetruecolor(53, 75);
	//	imagefilledrectangle($image_p, 0, 0, 53, 75, $frmcolor);
	//	$image = imagecreatefromjpeg($srcfilename);
	//	$height = imagesy($image); //pobranie wysokosci oryginalu
	//	imagecopyresampled($image_p, $image, 0, 0, 0, 0, 53, 75, 250, $height);
	//	imagejpeg($image_p, $dstfilename);
	//	//print ('thumb' . $dstsize . ': ' . $dstfilename . '<BR>');
	//	}
	
	function changename($oldname, $date) {
		$name = $date . '_' . $oldname;
		$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '.', '&', '!', '-', '+', '%', '^', '(', ')', '#', ';', '~', '`', '[', ']', '{', '}', ',') ;
		$name = str_replace($toreplace, '_', $name);
		// ZMIANA POLSKICH LITEREK!
		$toreplace = array('@', '$', '±', 'æ', 'ê', '³', 'ñ', 'ó', '¶', '¼', '¿', '¦', '£', '¯', 'Ñ', 'Ê', 'Æ', '¡', 'Ó', '¬');
		$replaceto = array('a', 's', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'S', 'L', 'Z', 'N', 'E', 'C', 'A', 'O', 'Z');   
		$name = str_replace($toreplace, $replaceto, $name);
		$name = str_replace('___', '_', $name);
		$name = str_replace('__', '_', $name);
		$name = str_replace(array('\'', '.'), '', $name);	
		$name = substr($name, 0, 40);
		if (substr($name, strlen($name) - 1, 1) == '_') { $name = substr($name, 0, strlen($name) - 1);}
		return $name ;
		}

	$userid = $_SESSION['adminuserid'];
	
	$description = $_POST['description'];
	$title = $_POST['title'];
	$date = $_POST['date'];
	$cityid = $_POST['cityid'];


	$cost = $_POST['cost'];
	$place = $_POST['place'];
	$website = $_POST['website'];
	$start = $_POST['start'];
	$artists = $_POST['artists'];	
	
	// SPRAWY PLAKATU
	
	$newposterfile = $_FILES['newposter']['tmp_name'];	
	
	if ($newposterfile != '') {
		//DODANIE PLAKATU UPLOADOWANEGO
		$newfilename = strtolower($date . '-' . substr($title, 0, 10) . '-' . $cityid);
		$newfilename = strtolower($newfilename);
		$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '\'', '.', ',') ;
		$newfilename = str_replace($toreplace, '_', $newfilename);

		// ZMIANA POLSKICH LITEREK!
		$toreplace = array('@', '$', '±', 'æ', 'ê', '³', 'ñ', 'ó', '¶', '¼', '¿', '¦', '£', '¯', 'Ñ', 'Ê', 'Æ', '¡', 'Ó', '¬');
		$replaceto = array('a', 's', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'S', 'L', 'Z', 'N', 'E', 'C', 'A', 'O', 'Z');    
		$newfilename = str_replace($toreplace, $replaceto, $newfilename);

		$path = dirname( $_SERVER['PATH_TRANSLATED'] );
		//print ('<BR><BR>path: ' . $path .'<BR><BR>');
		$path = substr($path, 0, -6); // 6 bo XADMIN
		$newname = $path . 'imgs/database/posters/www_hhbd_pl-' . $newfilename . '.jpg';
	
		$newposterthumbname = $path . 'imgs/database/posters-thumbs/www_hhbd_pl-' . $newfilename . '_thumb.jpg';
	
		$newposter = 'www_hhbd_pl-' . $newfilename . '.jpg';

		move_uploaded_file($newposterfile,$newname);
	
		createthumbnail($newposterthumbname, $newname, 0, 53, 75);	
	
		$poster = $newposter;
		}
	else {
		$poster = $_POST['poster'];
		}

	// KONIEC SPRAW PLAKATU
			
		
	// NADPISANIE KONCERTU
	
	//if (mysql_query('UPDATE concerts SET title="' . $title . '", urlname="' . changename($title, $date) . 
	//'", description="' . $description . '", date="' . $date . '", cityid="' . $cityid . '", start="' . $start . '"
	//, cost="' . $cost . '", website="' . $website . '", place="' . $place . '", poster="' . $poster . '
	//" WHERE id="' . $id . '"')) {
	
	if (mysql_query('INSERT INTO concerts (title, urlname, description, date, cityid, start, cost, website, place, poster, addedby) VALUES ("' . $title . '", "' . changename($title, $date) . '", "' . $description . '", "' . $date . '", "' . $cityid . '", "' . $start . '", "' . $cost . '", "' . $website . '", "' . $place . '", "' . $poster . '", "' . $userid . '")')) {
		$id = mysql_insert_id();		
		// ZAPISANIE POWIAZAN Z WYKONAWCAMI
		$artistsarray = explode(',', $artists);
		foreach ($artistsarray as $artist) if ($artist!= '') {
			mysql_query('INSERT INTO artist_concert_lookup (artistid, concertid) VALUES 
			("' . $artist . '", "' . $id . '")');
			}
		$smarty->assign('info', 'Koncert <strong>' . $title . '</strong> zosta³ pomy¶lnie dodany!');
		}
	else {
		$smarty->assign('info', 'Koncert <strong>' . $title . '</strong> <font color=red>NIE</font> zosta³ dodany!');
		}
	
	
	
//*****************************************************************	
$smarty->assign('mainsection', '<font color="#6f91ac">DODAWANIE KONCERTU</font>');
$smarty->assign('body_template', 'site/final-info.tpl');
//*****************************************************************
?>