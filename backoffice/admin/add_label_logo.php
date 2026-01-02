<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('template_top.php');
include ('connect_to_database.php');	

$id = $_POST['id'];
$filename = $_FILES['label_logo']['tmp_name'];

print ('Tymczsowa nazwa pliku: ' . $filename . '<BR><BR>');

$sql_query = 'SELECT name FROM labels WHERE id=' . $id;
$result = mysqli_query($sql, $sql_query);
$labelrow = mysqli_fetch_array($result);
$name = $labelrow['name'];
  
$newfilename = strtolower($name);
$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '\'', '.', ',') ;
$newfilename = str_replace($toreplace, '-', $newfilename);

$path = '/home/hhbd/www/php/app/s.hhbd.pl/hhbdcontent/l/';
$newname = $path . $newfilename . '-hhbdpl.jpg';
$newcover = $newfilename . '-hhbdpl.jpg';
  
// DODANIE NAZWY OKLADKI DO TABELI ALBUM�W
$sql_query = 'UPDATE labels SET logo="' . $newcover . '" WHERE id=' . $id;

if (mysqli_query($sql, $sql_query)) {
    $albumid = mysqli_insert_id($sql);
	print ("<BR><B>logo zostalo dodane do bazy!</B><br>");		

    if (move_uploaded_file($filename,$newname)) {
		print ('skopiowano plik na serwer!<BR><BR><BR>');
		print ('<img src="http://www.hhbd.pl/' . 'imgs/database/labels/' . $newcover . '"><BR><BR>');
		}
	else {
		print ('Nie skopiowano pliku tam gdzie trzeba...<BR><BR>');
		}	
	
	}
else {
	echo('<P>Nie dodano loga do bazy! (' . mysqli_error($sql) . ')<br>');
	}



include('template_bottom.php');	
?>