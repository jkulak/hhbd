<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

  include ('connect_to_database.php');	  
  include ('functions.php');  
   

$albumid = $_POST['albumid'];
$shopid = $_POST['shopid'];
$link = $_POST['link'];
$added = date('Y-m-d H:i:s');
$addedby = $_SESSION['userid'];

//print ('albumid: ' . $albumid . '<BR>');
//print ('shopid: ' . $shopid . '<BR>');
//print ('link: ' . $link . '<BR>');
//print ('dodany: ' . $added . '<BR>');
//print ('dodany przez: ' . $addedby . '<BR>');
	
$sql_query = 'INSERT INTO album_prices (albumid, shopid, link, added, addedby) ' .
		'VALUES ("' . $albumid . '", "' . $shopid . '", "' . $link . '", "' . $added . '", "' . $addedby . '")';
if (mysqli_query($sql, $sql_query)) {
	 header('Location: add_price_form.php?msg=1&shopid=' . $shopid);
	//print ("<BR><BR><B>Cena dodana!</B><br><br>");		
	}
else {
  include ('template_top.php');
	echo("<P>Nie dodano ceny  bo:  ' (" . mysqli_error($sql) . ")<br>");
	}
include('template_bottom.php');	  
?>