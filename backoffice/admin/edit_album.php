<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

  include ('template_top.php');
  include ('connect_to_database.php');	  
  include ('functions.php');  
  include ('include/functions.php'); 
  
  function goback(){ 
   print ("<a href=\"add_album_form.php?artist=$_POST[artist]&artist1id=$_POST[artist_list]&" . 
   "label=$_POST[label]&labelid=$_POST[label_list]&title=$_POST[title]&songcount=$_POST[songcount]&" .
   "date=$_POST[date]&addedby=$_POST[addedby]&artist2=$_POST[artist2]&artist2id=$_POST[artist_list2]&" . 
   "ep=$_POST[ep]&lp=$_POST[lp]&mc=$_POST[mc]&cd=$_POST[cd]&artist3=$_POST[artist3]&artist3id=$_POST[artist_list3]" .
   "\">Popraw</a>");
   }  
   
  $description = nl2br($_POST['description']);
  $premier = $_POST['premier'];
  
  // die($_POST['description']);
 
  
  //sprawdzanie poprawnosci danych
  if ($_POST[title] == "") {
    print ("<b>Brak nazwy albumu!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
	

  // wykonawca
  $artist1id = $_POST['artistid1']; 
  $artist2id = $_POST['artistid2']; 
  $artist3id = $_POST['artistid3'];   
  
  if (($artist1id == "") && ($artist2id == "") && ($artist3id == "")) {
    if (($_POST[artist] == "") && ($_POST[artist2] == "") && ($_POST[artist3] == "") ) {
      print ("<b>Brak wykonawcy! Wybierz chociaz jednego!</b><br>");
	  goback();
      include('template_bottom.php');
	  exit(); 
      }
	else {
	  $artist1id = GetArtistid($_POST['artist']);
	  $artist2id = GetArtistid($_POST['artist2']);
	  $artist3id = GetArtistid($_POST['artist3']);
	  }
	}
	

  // wytwornia
  $labelid = $_POST['labelid']; 
  if ($labelid == '') {
    if ($_POST['label'] == "") {
      print ("<b>Brak wytwórni!</b><br>");
	  goback();
      include('template_bottom.php');
	  exit(); 
      }
	else {
	  $labelid = Getlabelid($_POST['label']);
	  }
	}
	  
  // data premiery
  if ($_POST['year'] == "") {
    print ("<b>Brak daty premiery!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
   
  
  //ep=1
   if ($_POST[ep] == 1) {
     $ep = 1;
	 }
   else {
     $ep = 0;
	 }
   
  //mc=1
   if ($_POST[mc] == 1) {
     $mc = 1;
	 }
   else {
     $mc = 0;
	 }   

  //cd=1
   if ($_POST[cd] == 1) {
     $cd = 1;
	 }
   else {
     $cd = 0;
	 }   

  //lp=1
   if ($_POST[lp] == 1) {
     $lp = 1;
	 }
   else {
     $lp = 0;
	 }  
	 
  if ($lp == 0 && $mc == 0 && $cd == 0) {
    print ("<b>Zaznacz chocia¿ jeden nosnik na ktorym plyta zostala wydana!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
  
  $data_dodania = date("YmdHis");  
  
  //jezeli tutaj doszlimy, to znaczy ze wszystkie dane w porzadku
  if (($artist1id == -1) && ($_POST[artist] != "")) {
    $sql_query = "INSERT INTO artists (name, added, addedby) VALUES ('$_POST[artist]', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawcê: '$_POST[artist]'<BR><BR>");
      $artist1id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_POST[artist]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}
	
  if (($artist2id == -1) && ($_POST[artist2] != "")) {
    $sql_query = "INSERT INTO artists (name, added, addedby) VALUES ('$_POST[artist2]', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawcê: '$_POST[artist2]'<BR><BR>");
      $artist2id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_POST[artist2]' (" . mysqli_error() . ")<BR>");
	  }
	}
	
  if (($artist3id == -1) && ($_POST[artist3] != "")) {
    $sql_query = "INSERT INTO artists (name, added, addedby) VALUES ('$_POST[artist3]', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawcê: '$_POST[artist3]'<BR><BR>");
      $artist3id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_POST[artist3]' (" . mysqli_error() . ")<BR>");
	  }
	}		
	
	
  if ($labelid == -1) {
    $sql_query = "INSERT INTO labels (name, added, addedby) VALUES ('$_POST[label]', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy now¹ wytwórniê: '$_POST[label]'<BR><BR>");
	  $labelid = mysqli_insert_id($sql);
      }
	else {
	  print ("Nie dodano wytwórni: '$_POST[label]' (" . mysqli_error() . ")<BR>");
	  }
	}	
	

  print ("Tytuł: '$_POST[title]'<BR>");
  $artist = GetArtistName($artist1id); 
  print ("Wykonawca1: '$artist' (id: $artist1id)<BR>");
  $artist2 = GetArtistName($artist2id); 
  print ("Wykonawca2: '$artist2' (id: $artist2id)<BR>");
  $artist3 = GetArtistName($artist3id); 
  print ("Wykonawca3: '$artist3' (id: $artist3id)<BR>");
  $label = GetLabelName($labelid);  
  print ("Wytwórnia: '$label' (id: $labelid)<BR>");  
  print ("Data premiery: $_POST[year]<BR>");
  print ("Na kasecie: " . ($mc == 1 ? 'Tak' : 'Nie') . "<BR>");
  print ("Na CD: " . ($cd == 1 ? 'Tak' : 'Nie') . "<BR>");
  print ("Na winylu: " . ($lp == 1 ? 'Tak' : 'Nie') . "<BR>");
  print ("Singiel: " . ($ep == 1 ? 'Tak' : 'Nie') . "<Br>");
  print ('Numer katalogowy CD: ' . $_POST['cdcatalog'] . '<br>');
  print ('Numer katalogowy LP: ' . $_POST['lpcatalog'] . '<br>');
  print ('Numer katalogowy MC: ' . $_POST['mccatalog'] . '<br>');    
  print ("Edytowany przez: " . $_SESSION['userid'] . "<BR>");
  print ("Edytowany: $data_dodania<br>");

  	  print ('albumid: ' . $_POST[id] . '<br>');
	  
  
  $sql_query = "UPDATE albums SET title='$_POST[title]', urlname='" . create_urlname($_POST[title], 1, 1) . "', labelid='$labelid', year='$_POST[year]', media_mc=$mc, media_cd=$cd, " .
	"media_lp=$lp, singiel=$ep, updatedby=$_SESSION[userid], updated=$data_dodania, catalog_cd='$_POST[cdcatalog]', " .
	"catalog_lp='$_POST[lpcatalog]', catalog_mc='$_POST[mccatalog]', epfor='$_POST[epforid]', description='$description' , premier='$premier'" .
	"WHERE id=$_POST[id]";
			 
      if (mysqli_query($sql, $sql_query)) {
        print ("<BR><BR><B>Album '$_POST[title]' zostal update'owany, id: $_POST[id] </B><br><br>");		
      } else {
        echo("<P>Nie update'owano albumu '$_POST[title]' (" . mysqli_error($sql) . ")<br>");
      }
	  


// kasowanie wszystkich powiazan
  $sql_query = "DELETE FROM album_artist_lookup WHERE albumid=$_POST[id]";
  mysqli_query($sql, $sql_query);
  
  
  // dodanie powiazania album - artist 1
  if (($artist1id != -1) && ($artist1id != '')) {
    $sql_query = "INSERT INTO album_artist_lookup (artistid, albumid) VALUES (" .
          "'$artist1id', '$_POST[id]')";
    if (mysqli_query($sql, $sql_query)) {
        print ("Dodane powiazanie: artist1: $artist1id, albumid: $_POST[id]<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error() . ")<br>");
      }			  
	}	
	
  // dodanie powiazania album - artist 2
  if (($artist2id != -1) && ($artist2id != '')) {
    $sql_query = "INSERT INTO album_artist_lookup (artistid, albumid) VALUES (" .
          "'$artist2id', '$_POST[id]')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: artist2: $artist2id, albumid: $_POST[id]<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error() . ")<br>");
      }			  
	}	
	
  // dodanie powiazania album - artist 3
  if (($artist3id != -1) && ($artist3id != '')) {
    $sql_query = "INSERT INTO album_artist_lookup (artistid, albumid) VALUES (" .
          "'$artist3id', '$_POST[id]')";
    if (mysqli_query($sql,$sql_query)) {
        print ("<BR>Dodane powiazanie: artist3: $artist3id, albumid: $_POST[id]<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			  
	}			

  print ("<a href=\"add_song_form.php?addedby=$_POST[addedby]&artist1id=$artist1id&artist2id=$artist2id&artist3id=$artist3id&track=1&albumid=" . mysqli_insert_id($sql) . "\">Dodaj piosenki</a><br>");
  
  
  include('template_bottom.php');	  
?>