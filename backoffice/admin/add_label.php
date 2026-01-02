<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }
  
include ('include/functions.php');
include ('functions.php');
include ('template_top.php');
include ('connect_to_database.php');	
include ('database-functions.php');	    
  
  function goback(){ 
   print ("<a href=\"add_label_form.php?name=$_POST[name]&website=$_POST[website]" .
   "\">Popraw</a>");
   }  
  
$description = $_POST['description'];
   

 
  //sprawdzanie poprawnosci danych
  if ($_POST[name] == "") {
    print ("<b>Brak nazwy wytw�rni!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
	

  if ($_POST[website] == "") {
    print ("<b>Brak adresu http!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
	
$cityid = $_POST['cityid'];
if ($cityid == '') {
  if ($_POST['city'] != '') {
    $cityid = GetCityid($_POST['city']);
    }  
  } // mamy albo '' - co znaczy, ze nie ma w ogole miasta, albo -1 jezeli dodac trzeba.
  	
  
    $data_dodania = date("YmdHis");  
  
  //jezeli tutaj doszlimy, to znaczy ze wszystkie dane w porzadku

  print ("Wytw�rnia: $_POST[name]<BR>");
  print ("Website: $_POST[website]<BR>");
  
  $urlname = create_urlname($_POST['name']);
  
  $sql_query = "INSERT INTO labels (name, urlname, website, email, profile, addres, added, addedby) " .
	"VALUES ('$_POST[name]', '$urlname', '$_POST[website]', '$_POST[email]', '$description', '$_POST[addres]', '$data_dodania', '" . $_SESSION['userid'] . "')";
			 
      if (mysqli_query($sql, $sql_query)) {
	    $insertid = mysqli_insert_id($sql);
        print ("<BR><BR><B>Wytw�rnia '$_POST[name]' zostala dodana, id: $insertid </B><br><br>");		
      } else {
        echo("<P>Nie dodano wytw�rni '$_POST[name]' (" . mysqli_error($sql) . ")<br>");
      }
	  
// *****************
// dodawanie nowego miasta do bazy miast
// *****************
  if ($cityid == -1) {
    $sql_query = "INSERT INTO cities (name, added, addedby) " .
      "VALUES ('$_POST[city]', '$data_dodania', '" . $_SESSION['userid'] . "')";
	
	if (mysqli_query($sql, $sql_query)) {
	  $cityid = mysqli_insert_id($sql);
      print ("Dodano miasto '$_POST[city]' do bazy miast!<br>");		
      }
	else {
      echo("<P>Nie dodano miasta: '$_POST[city]'! (" . mysqli_error($sql) . ")<br>");
	  }
	}
	
	
  if ($cityid != '') {
    $sql_query = "INSERT INTO city_label_lookup (cityid, labelid) " .
      "VALUES ('$cityid', '$insertid')";
	
	if (mysqli_query($sql, $sql_query)) {
      print ('Dodano miasto \'' . GetCityName($cityid) . "' dla wytworni: $insertid <br>");		
      }
	else {
      echo("<P>Nie powiazania dla miasta $cityid (" . mysqli_error($sql) . ")<br>");
	  }
	}  			  
	
		  
  print ("<a href=\"add_label_form.php?\">Dodaj nast�pn� wytw�rni�.</a><br>");
  
  include('template_bottom.php');	  
?>