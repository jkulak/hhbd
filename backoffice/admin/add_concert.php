<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('functions.php');
include ('template_top.php');
include ('connect_to_database.php');


function createthumbnail($dstfilename, $srcfilename, $frmcolor){

//53x75 


	$image_p = imagecreatetruecolor(53, 75);
	imagefilledrectangle($image_p, 0, 0, 53, 75, $frmcolor);
	$image = imagecreatefromjpeg($srcfilename);
	$height = imagesy($image); //pobranie wysokosci oryginalu
	imagecopyresampled($image_p, $image, 0, 0, 0, 0, 53, 75, 250, $height);
	imagejpeg($image_p, $dstfilename);
	print ('thumb' . $dstsize . ': ' . $dstfilename . '<BR>');
	
	//print ('<img src="http://www.hhbd.pl' . substr($dstfilename, 32, strlen($dstfilename) - 32) . '"><BR><BR>');
	}



function changename($oldname, $date) {
	$name = $date . '_' . $oldname;
	$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '.', '&', '!', '-', '+', '%', '^', '(', ')', '#', ';', '~', '`', '[', ']', '{', '}', ',') ;
	$name = str_replace($toreplace, '_', $name);

	// ZMIANA POLSKICH LITEREK!
	$toreplace = array('@', '$', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�');
	$replaceto = array('a', 's', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'S', 'L', 'Z', 'N', 'E', 'C', 'A', 'O', 'Z');   
	$name = str_replace($toreplace, $replaceto, $name);
	
	$name = str_replace('___', '_', $name);
	$name = str_replace('__', '_', $name);
	$name = str_replace(array('\'', '.'), '', $name);	

	$name = substr($name, 0, 40);
		
	if (substr($name, strlen($name) - 1, 1) == '_') { $name = substr($name, 0, strlen($name) - 1);}
	
	return $name ;
	}

$description = $_POST['description'];
$title = $_POST['title'];
$date = $_POST['date'];
$userid = $_SESSION['userid'];
$cityid = $_POST['cityid'];
$newcity = $_POST['newcity'];

$cost = nl2br($_POST['cost']);
$place = nl2br($_POST['place']);
$website = nl2br($_POST['website']);
$start = nl2br($_POST['start']);

$artists = $_POST['artists'];

$newposterfile = $_FILES['newposter']['tmp_name'];

 $data_dodania = date("YmdHis"); 	



print ('<B>TITLE:</B>&nbsp;' . $title . '<BR>');
print ('<B>DESCRIPTION:</B>&nbsp;' . nl2br($description) . '<BR>');
print ('<B>DATE:</B>&nbsp;' . $date . '<BR>');
print ('<B>ADDEDBYID:</B>&nbsp;' . $userid . '<BR>');

print ('<B>CITYID:</B>&nbsp;' . $cityid . '<BR>');

$added = date('Y-m-d H:i:s');


// DODANIE MIASTA
if ($newcity != '') {
	$sql_query = 'INSERT INTO cities (name, added, addedby) VALUES ' .
		   '("' . $newcity . '", "' . $added . '", "' . $userid  . '")';
//	print ('<BR><BR>' . $sql . '<BR><BR>');
	$result = mysqli_query($sql, $sql_query);
	$cityid = mysqli_insert_id($sql);
	}
	
	
if ($newposterfile != '') {
	//DODANIE PLAKATU UPLOADOWANEGO
	$newfilename = strtolower($date . '-' . substr($title, 0, 10) . '-' . $cityid);
	$newfilename = strtolower($newfilename);
	$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '\'', '.', ',') ;
	$newfilename = str_replace($toreplace, '_', $newfilename);

	// ZMIANA POLSKICH LITEREK!
	$toreplace = array('�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�');
	$replaceto = array('a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 's', 'l', 'z');  
	$newfilename = str_replace($toreplace, $replaceto, $newfilename);

	$path = dirname( $_SERVER['PATH_TRANSLATED'] );
	print ('<BR><BR>path: ' . $path .'<BR><BR>');
	$path = substr($path, 0, -5);
	$newname = $path . 'imgs/database/posters/www_hhbd_pl_polski_hip_hop_' . $newfilename . '.jpg';
	
	$newposterthumbname = $path . 'imgs/database/posters-thumbs/www_hhbd_pl_polski_hip_hop_' . $newfilename . '_thumb.jpg';
	
	$newposter = 'www_hhbd_pl_polski_hip_hop_' . $newfilename . '.jpg';

	move_uploaded_file($newposterfile,$newname);
	
	createthumbnail($newposterthumbname, $newname, 0);
	
	
	$poster = $newposter;
	}
else {
	$poster = $_POST['poster'];
	}

	print ('<img src="' . $poster . '"><BR><BR>');


	
	
$sql_query = 'INSERT INTO concerts (title, urlname, description, date, cityid, addedby, start, cost, website, place, poster, added) VALUES ' .
       '("' . $title . '", "' . changename($title, $date) . '", "' . $description . '", "' . $date . '", "' . $cityid . '", "' . $userid . '", "' . $start . '", "' . $cost . '", "' . $website . '", "' . $place . '", "' . $poster . '", "' . $data_dodania . '")';

if (mysqli_query($sql, $sql_query)) {
    print ("<BR><BR><B>Koncert zostal dodany!</B><br><br>");	
	
	$concertid = mysqli_insert_id($sql_query);
} else {
    print ("<P>Nie dodano koncertu bo:  ' (" . mysqli_error($sql) . ")<br>");
    }

	
$artistsarray = explode(',', $artists);

foreach ($artistsarray as $artist) 
	if ($artist!= '') {
		$sql_query = 'INSERT INTO artist_concert_lookup (artistid, concertid) ' .
			   'VALUES ("' . $artist . '", "' . $concertid . '")';
		$resutl = mysqli_query($sql, $sql_query);
		}	
	
include('template_bottom.php');	  
?>