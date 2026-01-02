<?php
  
  include ('template_top.php');
  include ('connect_to_database.php');
  include ('functions.php');	  
  
  function goback(){ 
   print ("<a href=\"remove_artist_form.php?nameid=$_GET[nameid]&removealbums=$_GET[removealbums]" .
   "\">Popraw</a>");
   }
 
  //sprawdzanie poprawnosci danych
  if ($_GET['id'] == "") {
    print ('Nie wybrales albumu do usuniecia!<br>');
	goback();
	exit;      
    }
	
  $sql_query = 'SELECT title FROM albums WHERE id=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  $album = mysqli_fetch_array($result);	
  
  
  
  // WYSWIETLANIE DANYCH
  print ('Album do usuniecia: ' . $album[0] . '<BR>');
 
  print ('<br><br>');
  
  
   // USUWANIE Z BAZY artists
  $sql_query = 'DELETE FROM albums WHERE id=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Album: $album[0] (ID: $_GET[id]) zostal usuniety.</b>");
    }
  print ('<br>'); 		

 	
  // USUWANIE Z ZESPOLOW
  $sql_query = 'DELETE FROM album_lookup WHERE albumid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto powiazanie dla " . mysqli_affected_rows($sql) . ' piosenek.</b>');
    }
  print ('<br>'); 	
	
  // USUWANIE CZLONKOW Z ZESPOLU
  $sql_query = 'DELETE FROM album_artist_lookup WHERE albumid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ('<b>Usunieto ' . mysqli_affected_rows($sql) . " powiazan wykonawcow z albumem.");
    }
  print ('<br>'); 		

 	
  print ('<br><br>');  	

  print ('<a href="remove_artist_form.php?">Usun nastepnego wykonawce.</a><br>');  
  include('template_bottom.php');	  
?>