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
   print ("<a href=\"add_label_form.php?name=$_POST[name]&website=$_POST[website]" .
   "\">Popraw</a>");
   }   
   
   
$profile = $_POST['description'];
 
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
  
  $sql_query = "UPDATE labels SET name='$_POST[name]', profile='$profile', website='$_POST[website]', email='$_POST[email]', " .
         "addres='$_POST[addres]', updated='$data_dodania', updatedby='$_SESSION[userid]' " .
		 "WHERE id=$_POST[id]";

			 
      if (mysqli_query($sql, $sql_query)) {
        print ("<BR><BR><B>Wytw�rnia '$_POST[name]' zostala uaktualniona.</B><br><br>");		
      } else {
        echo("<P>Nie uaktualniono wytw�rni '$_POST[name]' (" . mysqli_error($sql) . ")<br>");
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
	
// kasowanie miast z powiazan z wykonawca
  $sql_query = "DELETE FROM city_label_lookup WHERE labelid=$_POST[id]";
  mysqli_query($sql, $sql_query);	
	
	
  if ($cityid != '') {
    $sql_query = "INSERT INTO city_label_lookup (cityid, labelid) " .
      "VALUES ('$cityid', '$_POST[id]')";
	
	if (mysqli_query($sql, $sql_query)) {
      print ('Dodano miasto \'' . GetCityName($cityid) . "' dla wytworni: $_POST[id] <br>");		
      }
	else {
      echo('<P>Nie powiazania dla miasta \'' . GetCityName($cityid) . "'(" . mysqli_error($sql) . ")<br>");
	  }
	}  			  
  
  include('template_bottom.php');	  
?>