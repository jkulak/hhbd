<?php
  
   // Connect to the database server
  print ("test");
  $dbcnx = @mysqli_pconnect("localhost", "fee", "f33del");
    if (!$dbcnx) {
      print ("Nie mozna sie polaczyc z baza: " . mysqli_error($sql) . "<br>");
      exit();
      }
	else {
	  print ('Polaczono z baza danych.<br>');
	  }

  $nazwa_bazy = 'katalog';
  
  
  if (! @mysqli_select_db($nazwa_bazy) ) {
      print ("Nie mozna odnalezc bazy: $nazwa_bazy (" . mysqli_error($sql) . ")<br>");
      exit();
	  }
	else {
	  print ("Otworzono baze: '$nazwa_bazy' do edycji<br>");
	  }
  
  
  
  // Request the text of all the jokes
  $result = mysqli_query($sql,
            "SELECT name FROM media ORDER BY name DESC");
  if (!$result) {
    echo("<P>Error performing query: " .
         mysqli_error($sql) . "</P>");
    exit();
  }

  
  print ('          <select name="select2">');
  while ( $row = mysqli_fetch_array($result) ) {
    echo("<option>" . $row["name"] . "</option>");
  }
  
  print ('</select>');


?>
