<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }
  
include('template_top.php');
include ("connect_to_database.php");	  
include ('functions.php');
include ('include/functions.php');
  
  
  function goback(){ 
   print ("<a href=\"add_song_form.php?artist1=$_GET[artist1]&artist1id=$_GET[artist_list1]" .
   "\">Popraw</a>");
   } 
  
  
  //sprawdzanie poprawnosci danych
  if ($_GET[title] == "") {
    print ("<b>Brak tytu�u piosenki!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
	
  // album
  $albumid = $_GET[albumid];
  if ($albumid == "") {
    print ("<b>Musisz wybrac album!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
 
	
// wykonawca
  $artist1id = $_GET[artist_list1]; 
  $artist2id = $_GET[artist_list2]; 
  $artist3id = $_GET[artist_list3];   
  $artist4id = $_GET[artist_list4];   
  
  // artist 1
  if ($artist1id == "") {
    if ($_GET[artist1] == "") {
	  print ("nie ma artist 1<BR>");
      }
	else {
	  $artist1id = GetArtistid($_GET[artist1]);
	  }
	}  
	
	
  // artist 2
  if ($artist2id == "") {
    if ($_GET[artist2] == "") {
	  print ("nie ma artist 2<BR>");
      }
	else {
	  $artist2id = GetArtistid($_GET[artist2]);
	  }
	}  
	
  // artist 3
  if ($artist3id == "") {
    if ($_GET[artist3] == "") {
	  print ("nie ma artist 3<BR>");
      }
	else {
	  $artist3id = GetArtistid($_GET[artist3]);
	  }
	}  
	
	
  // artist 4
  if ($artist4id == "") {
    if ($_GET[artist4] == "") {
	  print ("nie ma artist 4<BR>");
      }
	else {
	  $artist4id = GetArtistid($_GET[artist4]);
	  }
	}  
	

  // feat 1
  $feat1id = $_GET[feat1id]; 
  if ($feat1id == "") {
    if ($_GET[feat1] == "") {
	  print ("nie ma feat 1<BR>");
      }
	else {
	  $feat1id = GetArtistid($_GET[feat1]);
	  }
	}

  // feattype 1
  $feattype1id = $_GET[feattype1id]; 
  if ($feattype1id == "") {
    if ($_GET[feattype1] == "") {
	  print ("nie ma feattype 1<BR>");
      }
	else {
	  $feattype1id = GetFeattypeid($_GET[feattype1]);
	  }
	}	
	
	 
    // feat 2
  $feat2id = $_GET[feat2id]; 
  if ($feat2id == "") {
    if ($_GET[feat2] == "") {
	  print ("nie ma feat 2<BR>");
      }
	else {
	  $feat2id = GetArtistid($_GET[feat2]);
	  }
	}
	
  // feattype 2
  $feattype2id = $_GET[feattype2id]; 
  if ($feattype2id == "") {
    if ($_GET[feattype2] == "") {
	  print ("nie ma feattype 2<BR>");
      }
	else {
	  $feattype2id = GetFeattypeid($_GET[feattype2]);
	  }
	}		
  
    // feat 3
  $feat3id = $_GET[feat3id]; 
  if ($feat3id == "") {
    if ($_GET[feat3] == "") {
	  print ("nie ma feat 3<BR>");
      }
	else {
	  $feat3id = GetArtistid($_GET[feat3]);
	  }
	}
	
	
  // feattype 3
  $feattype3id = $_GET[feattype3id]; 
  if ($feattype3id == "") {
    if ($_GET[feattype3] == "") {
	  print ("nie ma feattype 3<BR>");
      }
	else {
	  $feattype3id = GetFeattypeid($_GET[feattype3]);
	  }
	}		
	
 
    // feat 4
  $feat4id = $_GET[feat4id]; 
  if ($feat4id == "") {
    if ($_GET[feat4] == "") {
	  print ("nie ma feat 4<BR>");
      }
	else {
	  $feat4id = GetArtistid($_GET[feat4]);
	  }
	}
	
  // feattype 4
  $feattype4id = $_GET[feattype4id]; 
  if ($feattype4id == "") {
    if ($_GET[feattype4] == "") {
	  print ("nie ma feattype 4<BR>");
      }
	else {
	  $feattype4id = GetFeattypeid($_GET[feattype4]);
	  }
	}  
   

	  
  // czas trwania
  if ($_GET[length] == "") {
    print ("<b>Brak czasu trwania!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}
  else {
	if (!strpos($_GET[length], ':')) {
	  $length = substr($_GET[length], 0, strlen($_GET[length]));
	  print ("sa tylko sekundy: $length<br>");
	  }
	else {  
	  print ('miunt: ' . substr($_GET[length], 0, strpos($_GET[length], ':')) . ', sekund: ' . substr($_GET[length], -2) . '<br>');
      $length = substr($_GET[length], -2) + substr($_GET[length], 0, strpos($_GET[length], ':')) * 60;
	  }	
	}

	
  // track
  if ($_GET[track] == "") {
    print ("<b>Brak numeru utworu na plycie!</b><br>");
	goback();
    include('template_bottom.php');
	exit();
	}	
	
	
   if ($_GET[acapella] == 1) {
     $acapella = 1;
	 }
   else {
     $acapella = 0;
	 }
	 
  if ($_GET[instrumental] == 1) {
     $instrumental = 1;
	 }
   else {
     $instrumental = 0;
	 }

  if ($_GET[radio] == 1) {
     $radio = 1;
	 }
   else {
     $radio = 0;
	 }	

  $data_dodania = date("YmdHis");  
  
  
  //jezeli tutaj doszlimy, to znaczy ze wszystkie dane w porzadku
  
  if ($artist1id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[artist1]', '" . create_urlname($_GET[artist1], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc�: '$_GET[artist1]'<BR><BR>");
      $artist1id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[artist1]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}

// ARTIST 2
	
  if ($artist2id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[artist2]', '" . create_urlname($_GET[artist2], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc�: '$_GET[artist2]'<BR><BR>");
      $artist2id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[artist2]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}
	
// ARTIST 3

  if ($artist3id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[artist3]', '" . create_urlname($_GET[artist3], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc�: '$_GET[artist3]'<BR><BR>");
      $artist3id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[artist3]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}	
	
	
// ARTIST 4

  if ($artist4id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[artist4]', '" . create_urlname($_GET[artist4], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc�: '$_GET[artist4]'<BR><BR>");
      $artist4id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[artist4]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}	
		
	
	
  // dodajemy ewentualnie FEAT1 artist
  if ($feat1id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[feat1]', '" . create_urlname($_GET[feat1], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc�: '$_GET[feat1]'<BR><BR>");
      $feat1id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[feat1]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}
	
  // dodajemy ewentualnie feattype1 artist
  if ($feattype1id == -1) {
    $sql_query = "INSERT INTO feattypes (feattype) VALUES ('$_GET[feattype1]')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowy typ ficzuringu: '$_GET[feattype1]'<BR><BR>");
      $feattype1id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[feattype1]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}	
	
  // dodajemy ewentualnie FEAT2 artist	
  if ($feat2id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[feat2]', '" . create_urlname($_GET[feat2], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc�: '$_GET[feat2]'<BR><BR>");
      $feat2id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[feat2]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}
	
  // dodajemy ewentualnie feattype2 artist	
  if ($feattype2id == -1) {
    $sql_query = "INSERT INTO feattypes (feattype) VALUES ('$_GET[feattype2]')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowy typ ficzuringu: '$_GET[feattype2]'<BR><BR>");
      $feattype2id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[feattype2]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}	
	
  // dodajemy ewentualnie FEAT3 artist	
  if ($feat3id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[feat3]', '" . create_urlname($_GET[feat3], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc�: '$_GET[feat3]'<BR><BR>");
      $feat3id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[feat3]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}	
	
	
  // dodajemy ewentualnie feattype3 artist
  if ($feattype3id == -1) {
    $sql_query = "INSERT INTO feattypes (feattype) VALUES ('$_GET[feattype3]')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowy typ ficzuringu: '$_GET[feattype3]'<BR><BR>");
      $feattype3id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[feattype3]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}			

  // dodajemy ewentualnie FEAT4 artist	
  if ($feat4id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[feat4]', '" . create_urlname($_GET[feat4], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc�: '$_GET[feat4]'<BR><BR>");
      $feat4id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykowacy: '$_GET[feat4]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}	


  // dodajemy ewentualnie feattype4 artist
  if ($feattype4id == -1) {
    $sql_query = "INSERT INTO feattypes (feattype) VALUES ('$_GET[feattype4]')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowy typ ficzuringu: '$_GET[feattype4]'<BR><BR>");
      $feattype4id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy: '$_GET[feattype4]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}	
	

	
	//tutaj sprawdzamy muzyke i scratcha, bo moga sie powtarzc
	// ci co spiewaja i graja
	
	 // music 1
  $music1id = $_GET[music1id]; 
  if ($music1id == "") {
    if ($_GET[music1] == "") {
	  print ("nie ma music 1<BR>");
      }
	else {
	  $music1id = GetArtistid($_GET[music1]);
	  }
	}
	
    // music 2
  $music2id = $_GET[music2id]; 
  if ($music2id == "") {
    if ($_GET[music2] == "") {
	  print ("nie ma music 2<BR>");
      }
	else {
	  $music2id = GetArtistid($_GET[music2]);
	  print("<BR>id MUSIC2 : $music2id<BR><BR>");
	  }
	}	
	
    // scratch 1
  $scratch1id = $_GET[scratch1id]; 
  if ($scratch1id == "") {
    if ($_GET[scratch1] == "") {
	  print ("nie ma scratch 1<BR>");
      }
	else {
	  $scratch1id = GetArtistid($_GET[scratch1]);
	  }
	}
	
    // scratch 2
  $scratch2id = $_GET[scratch2id]; 
  if ($scratch2id == "") {
    if ($_GET[scratch2] == "") {
	  print ("nie ma scratch 2<BR>");
      }
	else {
	  $scratch2id = GetArtistid($_GET[scratch2]);
	  }
	}	
	
	
   
	
    // remix
  $remixid = $_GET[remixid]; 
  if ($remixid == "") {
    if ($_GET[remix] == "") {
	  print ("to nie jest remix<BR>");
      }
	else {
	  $remixid = GetArtistid($_GET[remix]);
	  }
	}			
	
	
			
	// i potem ich ewentualnie dodajemyy...	
	
 // dodajemy ewentualnie MUSIC 1 artist	
  if ($music1id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[music1]', '" . create_urlname($_GET[music1], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc� (muzyki): '$_GET[music1]'<BR><BR>");
      $music1id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy (muzyka): '$_GET[music1]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}		
	
 // dodajemy ewentualnie MUSIC 2 artist	
  if ($music2id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[music2]', '" . create_urlname($_GET[music2], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc� (muzyki): '$_GET[music2]'<BR><BR>");
      $music2id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy (muzyka): '$_GET[music2]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}

 // dodajemy ewentualnie scratch 1 artist	
  if ($scratch1id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[scratch1]', '" . create_urlname($_GET[scratch1], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc� (scratch1): '$_GET[scratch1]'<BR><BR>");
      $scratch1id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy (muzyka): '$_GET[scratch1]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}		
	
 // dodajemy ewentualnie scratch 2 artist	
  if ($scratch2id == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[scratch2]', '" . create_urlname($_GET[scratch2], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc� (scratch2): '$_GET[scratch2]'<BR><BR>");
      $scratch2id = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy (muzyka): '$_GET[scratch2]' (" . mysqli_error($sql) . ")<BR>");
	  }  
	}	
	
	
 // dodajemy ewentualnie remix artist	
  if ($remixid == -1) {
    $sql_query = "INSERT INTO artists (name, urlname, added, addedby) VALUES ('$_GET[remix]', '" . create_urlname($_GET[remix], 1, 1) . "', '$data_dodania', '" . $_SESSION['userid'] . "')";
    $result = mysqli_query($sql, $sql_query);
	if ($result) {
	  print ("Dodano do bazy nowego wykonawc� (remix): '$_GET[remix]'<BR><BR>");
      $remixid = mysqli_insert_id($sql);
      }
	else {
      print ("Nie dodano wykonawcy (remix): '$_GET[remix]' (" . mysqli_error($sql) . ")<BR>");
	  }
	}		
	
	
	
	
 		
	
	
	
	// wszystko co bylo ewentualnie do dodania, zostalo dodane					

  print ("Tytu�: '$_GET[title]'<BR>");
  $artist = GetArtistName($artist1id); 
  print ("Wykonawca: '$artist' (id: $artist1id)<BR>");
  $label = GetLabelName($labelid);
  
  print ("Czas trwanai: $_GET[length] ($length s)<BR>");
  print ("Predkosc bitu: $_GET[bpm]<BR>");
  print ("Numer na plycie: $_GET[track]<BR>");  
  print ("Dodany przez: " . $_SESSION['userid'] . "<BR>");
  print ("Dodany do katalogu: $data_dodania<br>");
 
  $sql_query = "UPDATE songs SET title='$_GET[title]', urlname='" . create_urlname($_GET[title], 1, 1)  . "', length='$length', bpm='$_GET[bpm]', acapella='$acapella', instrumental='$instrumental', radio='$radio', updatedby='$_SESSION[userid]', updated='$data_dodania' " .
	  "WHERE id=$_GET[id]";
			 
      if (mysqli_query($sql, $sql_query)) {
		print ("<BR><BR><B>Piosenka '$_GET[title]' zostala update'owana, id:" . mysqli_insert_id($sql) . '</B><br>');		
      } else {
        echo("<P>Nie update'owano piosenki '$_GET[title]' (" . mysqli_error($sql) . ")<br>");
      }
	  
$songid = $_GET['id']; //zeby potem wszedzie nie zmieniac sonind na $_GET[id]	  
	  
  
  // dodaine powiazania song - album
    $sql_query = "UPDATE album_lookup SET albumid='$_GET[albumid]', songid='$_GET[id]', track='$_GET[track]' " .
          "WHERE (albumid=$_GET[prevalbumid] AND songid=$_GET[id])";
   if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Aktualizowano powiazanie: albumid: $_GET[albumid], songid: $songid <br>");		
      } else {
        echo("<P>Nie aktualizowano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }		  

  // dodaine powiazania song - artist
  
// kasowanie wszystkich powiazan wykonawcow
  $sql_query = "DELETE FROM artist_lookup WHERE songid=$_GET[id]";
  mysqli_query($sql, $sql_query);

	if ($artist1id != "") {
	  $sql_query = "INSERT INTO artist_lookup (artistid, songid) VALUES (" .
          "'$artist1id', '$songid')";
	   if (mysqli_query($sql, $sql_query)) {
        print ("Dodane powiazanie: artist1id: $artist1id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			    
	}


  // dodaine powiazania song - artist2
  if ($artist2id != "") {
  $sql_query = "INSERT INTO artist_lookup (artistid, songid) VALUES (" .
          "'$artist2id', '$songid')";
   if (mysqli_query($sql, $sql_query)) {
        print ("Dodane powiazanie: artist2id: $artist2id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }		
	}	  
	  
  // dodaine powiazania song - artist3
	if ($artist3id != "") {  
  $sql_query = "INSERT INTO artist_lookup (artistid, songid) VALUES (" .
          "'$artist3id', '$songid')";
   if (mysqli_query($sql, $sql_query)) {
        print ("Dodane powiazanie: artist3id: $artist3id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }	
	 }		  	  

  // dodaine powiazania song - artist4
	if ($artist4id != "") {  
  $sql_query = "INSERT INTO artist_lookup (artistid, songid) VALUES (" .
          "'$artist4id', '$songid')";
   if (mysqli_query($sql, $sql_query)) {
        print ("Dodane powiazanie: artist4id: $artist4id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			
   }	    

// kasowanie wszystkich powiazan wykonawcow
  $sql_query = "DELETE FROM feature_lookup WHERE songid=$_GET[id]";
  mysqli_query($sql, $sql_query);

  // dodaine powiazania song - feat 1
  if ($feat1id != "") {
    $sql_query = "INSERT INTO feature_lookup (artistid, songid, feattype) VALUES (" .
          "'$feat1id', '$songid', '$feattype1id')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: feat1id: $feat1id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			  
	}

  // dodaine powiazania song - feat 2
  if ($feat2id != "") {
    $sql_query = "INSERT INTO feature_lookup (artistid, songid, feattype) VALUES (" .
          "'$feat2id', '$songid', '$feattype2id')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: feat2id: $feat2id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			  
	}
	
  // dodaine powiazania song - feat 3
  if ($feat3id != "") {
    $sql_query = "INSERT INTO feature_lookup (artistid, songid, feattype) VALUES (" .
          "'$feat3id', '$songid', '$feattype3id')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: feat3id: $feat3id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			  
	}
  // dodaine powiazania song - feat 4
  if ($feat4id != "") {
    $sql_query = "INSERT INTO feature_lookup (artistid, songid, feattype) VALUES (" .
          "'$feat4id', '$songid', '$feattype4id')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: feat4id: $feat4id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			  
	}

// kasowanie wszystkich powiazan wykonawcow
  $sql_query = "DELETE FROM music_lookup WHERE songid=$_GET[id]";
  mysqli_query($sql, $sql_query);	
	
  // dodaine powiazania song - music 1
  if ($music1id != "") {
    $sql_query = "INSERT INTO music_lookup (artistid, songid) VALUES (" .
          "'$music1id', '$songid')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: music1id: $music1id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			  
	}	
	
  // dodaine powiazania song - music 2
  if ($music2id != "") {
    $sql_query = "INSERT INTO music_lookup (artistid, songid) VALUES (" .
          "'$music2id', '$songid')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: music2id: $music2id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			  
	}	
	
// kasowanie wszystkich powiazan wykonawcow
  $sql_query = "DELETE FROM scratch_lookup WHERE songid=$_GET[id]";
  mysqli_query($sql, $sql_query);	
	
  // dodaine powiazania song - scratch 1
  if ($scratch1id != "") {
    $sql_query = "INSERT INTO scratch_lookup (artistid, songid) VALUES (" .
          "'$scratch1id', '$songid')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: scratch2id: $scratch1id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			  
	}		
	
	
  // dodaine powiazania song - scratch 2
  if ($scratch2id != "") {
    $sql_query = "INSERT INTO scratch_lookup (artistid, songid) VALUES (" .
          "'$scratch2id', '$songid')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: scratch2id: $scratch2id, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
      }			  
	}		
	

	

// kasowanie wszystkich powiazan wykonawcow
  $sql_query = "DELETE FROM remix_lookup WHERE songid=$_GET[id]";
  mysqli_query($sql, $sql_query);		
	
  // dodaine powiazania remix artist
  if ($remixid != "") {
    $sql_query = "INSERT INTO remix_lookup (artistid, songid) VALUES (" .
          "'$remixid', '$songid')";
    if (mysqli_query($sql, $sql_query)) {
        print ("<BR>Dodane powiazanie: remixid: $remixid, songid: $songid<br>");		
      } else {
        echo("<P>Nie dodano powiazania! remixid: $remixid, songid: $songid' (" . mysqli_error($sql) . ")<br>");
      }			  
	}		

  include('template_bottom.php');	  
?>