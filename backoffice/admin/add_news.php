<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('functions.php');
include ('template_top.php');
include ('connect_to_database.php');

function createthumbnail($dstfilename, $srcfilename, $frmcolor, $width ){

	//width na 100
	
	//pobieram rozmiary originalnego
	list($width_orig, $height_orig) = getimagesize($srcfilename);
	$height = (int) (($width / $width_orig) * $height_orig);

	//tworze docelowy jpg
	$image_p = imagecreatetruecolor($width, $height);
	// dodaje ramke
	imagefilledrectangle($image_p, 0, 0, $width, $height, $frmcolor);
	
	$image = imagecreatefromjpeg($srcfilename);
	
	imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

	imagejpeg($image_p, $dstfilename);
	
	print ('thumb' . $dstsize . ': ' . $dstfilename . '<BR>');
	
	print ('<img src="http://www.hhbd.pl/' . substr($dstfilename, 32, strlen($dstfilename) - 32) . '"><BR><BR>');
	}
	
$news = $_POST['news'];
$title = $_POST['title'];
$expires = $_POST['expires'];
$userid = $_SESSION['userid'];

$glyph = $_POST['glyph'];

$artists = $_POST['artists'];
$labels = $_POST['labels'];
$concerts = $_POST['concerts'];
$albums = $_POST['albums'];
$cities = $_POST['cities'];
$added = $_POST['date'];

if ($added == '') $added = date('Y-m-d H:i:s');

$filename = $_FILES['graph']['tmp_name'];

$newfilename = strtolower(substr($added, 0, 10) . '-' . substr($title, 0, 10) . '-' . substr($news, 0, 20));
$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '\'', '.', ',') ;
$newfilename = str_replace($toreplace, '_', $newfilename);
$toreplace = array('�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�');
$replaceto = array('a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 's', 'l', 'z');  
$newfilename = str_replace($toreplace, $replaceto, $newfilename);

$path = dirname( $_SERVER['PATH_TRANSLATED'] );
$path = substr($path, 0, -5);
print ('<BR>path:' . $path . '<BR>');


$newname = $path . 'imgs/database/news/www_hhbd_pl_' . $newfilename . '.jpg';
$thumb = $path . 'imgs/database/news-thumbs/www_hhbd_pl_' . $newfilename . '_small.jpg';


if ($filename != '') { $newfilename = 'www_hhbd_pl_' . $newfilename . '.jpg'; } else $newfilename = '';

print ('<BR><BR>filename:' . $newname . '<BR><BR>');
print ('<BR><BR>graph:' . $graph . '<BR><BR>');

print ('<B>TITLE:</B>&nbsp;' . $title . '<BR>');
print ('<B>NEWS:</B>&nbsp;' . nl2br($news) . '<BR><BR>');
print ('<B>EXPIRES:</B>&nbsp;' . $expires . '<BR><BR>');
print ('<B>ADDEDBYID:</B>&nbsp;' . $userid . '<BR><BR>');

$sql_query = 'INSERT INTO news (title, news, expires, addedby, added, glyph, graph) VALUES ' .
       '("' . $title . '", "' . $news . '", "' . $expires . '", "' . $userid . '", "' . $added . '", "' . $glyph . '", "' . $newfilename . '")';

$result = mysqli_query($sql, $sql_query);
if ($result) {
    print ("<BR><BR><B>News zostal dodany!</B><br><br>");	
	$newsid = mysqli_insert_id($sql);
	//upload grafiki
	if (move_uploaded_file($filename,$newname)) {
		print ('skopiowano plik na serwer!<BR><BR><BR>');
		createthumbnail($thumb, $newname, 10979669, 148);

		print ('<img src="http://www.hhbd.pl/' . 'imgs/database/news-thumbs/' . $newname . '"><BR><BR>');
		}
	else {
		print ('Nie skopiowano pliku tam gdzie trzeba...<BR><BR>');
		}		
	
	
	
	//dodanie powiazan	
	
	
$concertsarray = explode(',', $concerts);
foreach ($concertsarray as $concert) 
	if ($concert!= '') {
		$sql_query = 'INSERT INTO news_concert_lookup (concertid, newsid) ' .
			   'VALUES ("' . $concert . '", "' . $newsid . '")';
		$resutl = mysqli_query($sql, $sql_query);
		}	
		
		
		
$artistsarray = explode(',', $artists);
foreach ($artistsarray as $artist) 
	if ($artist!= '') {
		$sql_query = 'INSERT INTO news_artist_lookup (artistid, newsid) ' .
			   'VALUES ("' . $artist . '", "' . $newsid . '")';
		$resutl = mysqli_query($sql, $sql_query);
		}
		
$labelsarray = explode(',', $labels);
foreach ($labelsarray as $label) 
	if ($label != '') {
		$sql_query = 'INSERT INTO news_label_lookup (labelid, newsid) ' .
			   'VALUES ("' . $label . '", "' . $newsid . '")';
		$resutl = mysqli_query($sql, $sql_query);
		}		
		
$albumsarray = explode(',', $albums);
foreach ($albumsarray as $album) 
	if ($album!= '') {
		$sql_query = 'INSERT INTO news_album_lookup (albumid, newsid) ' .
			   'VALUES ("' . $album . '", "' . $newsid . '")';
		$resutl = mysqli_query($sql, $sql_query);
		}			
		
$citiesarray = explode(',', $cities);
foreach ($citiesarray as $city) 
	if ($city!= '') {
		$sql_query = 'INSERT INTO news_city_lookup (cityid, newsid) ' .
			   'VALUES ("' . $city . '", "' . $newsid . '")';
		$resutl = mysqli_query($sql, $sql_query);
		}	
	
		
} else {
    print ("<P>Nie dodano newsa bo:  ' (" . mysqli_error($sql) . ")<br>");
    }


  
  include('template_bottom.php');	  
?>