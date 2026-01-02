<?php
  
  include ('template_top.php');
  include ('connect_to_database.php');
  include ('functions.php');	  
  
  function goback(){ 
   print ("<a href=\"remove_song_form.php?nameid=$_GET[id]" .
   "\">Popraw</a>");
   }
 
  //sprawdzanie poprawnosci danych
  if ($_GET['id'] == "") {
    print ('Nie wybrales utworu do usuniecia!<br>');
	goback();
	exit;      
    }
	
  $sql_query = 'SELECT title FROM songs WHERE id=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  $album = mysqli_fetch_array($result);	
  
  
  
  // WYSWIETLANIE DANYCH
  print ('Utwor do usuniecia: ' . $album[0] . '<BR>');
 
  print ('<br><br>');
  
  
   // USUWANIE Z BAZY artists
  $sql_query = 'DELETE FROM songs WHERE id=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Utwor: $album[0] (ID: $_GET[id]) zostal usuniety.</b>");
    }
  print ('<br>'); 		

 	
  // USUWANIE Z albumow
  $sql_query = 'DELETE FROM album_lookup WHERE songid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto powiazanie dla " . mysqli_affected_rows($sql) . ' albumow.</b>');
    }
  print ('<br>'); 	
  
  // USUWANIE Z feat
  $sql_query = 'DELETE FROM feature_lookup WHERE songid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto powiazanie dla " . mysqli_affected_rows($sql) . ' featuringow.</b>');
    }
  print ('<br>'); 	 
  
  // USUWANIE Z msuic
  $sql_query = 'DELETE FROM music_lookup WHERE songid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto powiazanie dla " . mysqli_affected_rows($sql) . ' music.</b>');
    }
  print ('<br>'); 	  
  
  // USUWANIE Z scratch
  $sql_query = 'DELETE FROM scratch_lookup WHERE songid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto powiazanie dla " . mysqli_affected_rows($sql) . ' scratch.</b>');
    }
  print ('<br>'); 	    
  
  // USUWANIE Z artist
  $sql_query = 'DELETE FROM artist_lookup WHERE songid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto powiazanie dla " . mysqli_affected_rows($sql) . ' artist.</b>');
    }
  print ('<br>'); 	  
  
  // USUWANIE Z remix
  $sql_query = 'DELETE FROM remix_lookup WHERE songid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto powiazanie dla " . mysqli_affected_rows($sql) . ' remix.</b>');
    }
  print ('<br>'); 	  
  
  // USUWANIE Z scratch
  $sql_query = 'DELETE FROM video_lookup WHERE songid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto powiazanie dla " . mysqli_affected_rows($sql) . ' video.</b>');
    }
  print ('<br>'); 	  
	
  

 	
  print ('<br><br>');  	

  print ('<a href="remove_artist_form.php?">Usun nastepnego wykonawce.</a><br>');  
  include('template_bottom.php');	  
?>