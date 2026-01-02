<?php
  
  include ('template_top.php');
  include ('connect_to_database.php');
  include ('functions.php');	  
  
  function goback(){ 
   print ("<a href=\"remove_artist_form.php?nameid=$_GET[nameid]&removealbums=$_GET[removealbums]" .
   "\">Popraw</a>");
   }
 
  //sprawdzanie poprawnosci danych
  if ($_GET[nameid] == "") {
    print ('Nie wybrales wykonawcy do usuniecia!<br>');
	goback();
	exit;      
    }
  
  
  
  // WYSWIETLANIE DANYCH
  print ('Wykonawca do usuniecia: ' . GetArtistName($_GET[nameid]) . '<BR>');
  if ($_GET[removealbums] == 1) {
      
  }
  print ('Usunac albumy wykonawcy: ' . ($_GET['removealbums'] == '1' ? 'Tak' : 'Nie'));
  print ('<br><br>');
  
  
  $artistname = GetArtistName($_GET[nameid]);

  // USUWANIE Z BAZY artists
  $sql_query ='DELETE FROM artists WHERE id=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Wykonawca: $artistname (ID: $_GET[nameid]) zostal usuniety.</b>");
    }
  print ('<br>'); 		

  // USUWANIE NICKOW
  $sql_query ='DELETE FROM altnames_lookup WHERE artistid=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ('<b>Usunieto ' . mysqli_affected_rows($sql) . " alternatywne nazwy dla: $artistname (ID: $_GET[nameid])</b>");
    }
  print ('<br>'); 		
	
  // USUWANIE Z ZESPOLOW
  $sql_query ='DELETE FROM band_lookup WHERE artistid=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto wykonawce: $artistname (ID: $_GET[nameid]) z " . mysqli_affected_rows($sql) . ' zespolow.</b>');
    }
  print ('<br>'); 	
	
  // USUWANIE CZLONKOW Z ZESPOLU
  $sql_query ='DELETE FROM band_lookup WHERE bandid=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ('<b>Usunieto ' . mysqli_affected_rows($sql) . " wykonawcow z zespolu: $artistname (ID: $_GET[nameid])</b>");
    }
  print ('<br>'); 		
	
  // USUWANIE Z LISTY REMIXOWCOW
  $sql_query ='DELETE FROM remix_lookup WHERE artistid=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto wykonawce: $artistname (ID: $_GET[nameid]) z " . mysqli_affected_rows($sql) . ' remixow.</b>');
    }
  print ('<br>'); 			
	
  if ($_GET[removealbums]) {
    $sql_query ='SELECT albumid FROM album_artist_lookup WHERE artistid=' . $_GET[nameid];
    $result = mysqli_query($sql, $sql_query);
    while ( $row = mysqli_fetch_array($result) ) { 
      $ids = $ids . $row[albumid] . ',';
	  }
    $ids = substr($ids, 0, strlen($ids) - 1);
  
    $sql_query ="DELETE FROM albums WHERE id IN ($ids)";
	$result = mysqli_query($sql, $sql_query);
	if (!$result) {
      print (mysqli_error($sql));
	  }
    else {
      print ('<b>Usunieto: ' . mysqli_affected_rows($sql) . " albumow autorstwa wykonawcy: $artistname .</b>");
      }	
    print ('<br>'); 
	}

	


  // USUWANIE Z LISTY ALBUMOW
  $sql_query ='DELETE FROM album_artist_lookup WHERE artistid=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto wykonawce: $artistname (ID: $_GET[nameid]) z " . mysqli_affected_rows($sql) . ' albumow.</b>');
    }
  print ('<br>'); 		
	
  // USUWANIE Z LISTY SKRECZOW
  $sql_query ='DELETE FROM scratch_lookup WHERE artistid=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto wykonawce: $artistname (ID: $_GET[nameid]) z " . mysqli_affected_rows($sql) . ' skreczow.</b>');
    }
  print ('<br>'); 	
	
  // USUWANIE Z LISTY MUZYKI
  $sql_query ='DELETE FROM music_lookup WHERE artistid=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto wykonawce: $artistname (ID: $_GET[nameid]) jako autora muzyki w " . mysqli_affected_rows($sql) . ' utworach.</b>');
    }
  print ('<br>'); 		
	
  // USUWANIE Z LISTY WOKALISTOW
  $sql_query ='DELETE FROM artist_lookup WHERE artistid=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto wykonawce: $artistname (ID: $_GET[nameid]) jako wokaliste w " . mysqli_affected_rows($sql) . ' utworach.</b>');
    }
  print ('<br>'); 			
	
  // USUWANIE Z LISTY FEATURINGOW
  $sql_query ='DELETE FROM feature_lookup WHERE artistid=' . $_GET[nameid];
  $result = mysqli_query($sql, $sql_query);
  if (!$result) {
    print (mysqli_error($sql));
	}
  else {
    print ("<b>Usunieto wykonawce: $artistname (ID: $_GET[nameid]) z " . mysqli_affected_rows($sql) . ' featuringow.</b>');
    }
  print ('<br>'); 	
 	
  print ('<br><br>');  	

  print ('<a href="remove_artist_form.php?">Usun nastepnego wykonawce.</a><br>');  
  include('template_bottom.php');	  
?>