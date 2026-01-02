<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('functions.php');
include ('template_top.php');
include ('connect_to_database.php');	  
  
  function goback(){ 
   print ("<a href=\"add_video_label_form.php?name=$_GET[name]&website=$_GET[website]&addedby=$_GET[addedby]" .
   "\">Popraw</a>");
   }   
 
  //sprawdzanie poprawnosci danych
  if ($_GET[name] == "") {
    print ("<b>Brak nazwy wytw�rni video!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
	

  if ($_GET[website] == "") {
    print ("<b>Brak adresu http!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
	
$cityid = $_GET['cityid'];
  if ($cityid == '') {
    if ($_GET['city'] != '') {
      $cityid = GetCityID($_GET['city']);
      }  
    } // mamy albo '' - co znaczy, ze nie ma w ogole miasta, albo -1 jezeli dodac trzeba.
  	
	
  
    $data_dodania = date("YmdHis");  	
  
  //jezeli tutaj doszlimy, to znaczy ze wszystkie dane w porzadku

  print ("Wytw�rnia: $_GET[name]<BR>");
  print ("Website: $_GET[website]<BR>");
  
  $sql_query = "INSERT INTO video_labels (name, website, email, addres, added, addedby) " .
	"VALUES ('$_GET[name]', '$_GET[website]', '$_GET[email]', '$_GET[addres]', '$data_dodania', '$_GET[addedby]')";
			 
      if (mysqli_query($sql, $sql_query)) {
	    $insertID = mysqli_insert_id($sql);
        print ("<BR><BR><B>Wytw�rnia '$_GET[name]' zostala dodana, ID: $insertID </B><br><br>");		
      } else {
        echo("<P>Nie dodano wytw�rni '$_GET[name]' (" . mysqli_error($sql) . ")<br>");
      }
	  
// *****************
// dodawanie nowego miasta do bazy miast
// *****************
  if ($cityid == -1) {
    $sql_query = "INSERT INTO cities (name, added, addedby) " .
      "VALUES ('$_GET[city]', '$data_dodania', '" . $_SESSION['userid'] . "')";
	
	if (mysqli_query($sql, $sql_query)) {
	  $cityinsertid = mysqli_insert_id($sql);
      print ("Dodano miasto '$_GET[city]' do bazy miast!<br>");		
      }
	else {
      echo("<P>Nie dodano miasta: '$_GET[city]'! (" . mysqli_error($sql) . ")<br>");
	  }
	}
	
	
  if ($cityinsertid != 0) {
    $sql_query = "INSERT INTO city_video_label_lookup (cityid, videolabelid) " .
      "VALUES ('$cityinsertid', '$nameid')";
	
	if (mysqli_query($sql, $sql_query)) {
	  $insertID = mysqli_insert_id($sql);
      print ("Dodano miasto $cityinsertid dla wytworni video: " . GetArtistName($nameid) . "<br>");		
      }
	else {
      echo("<P>Nie dodano miasta $cityinsertid (" . mysqli_error($sql) . ")<br>");
	  }
	} 	  
	
		  
  print ("<a href=\"add_video_label_form.php?addedby=$_GET[addedby]\">Dodaj nast�pn� wytw�rni� VIDEO.</a><br>");
  
  include('template_bottom.php');	  
?>