<?php

  function GetArtistID($artistname){
    $sql_query = "SELECT id FROM artists WHERE name='$artistname'";
	$result = mysqli_query($sql, $sql_query);
    if (!$resutl) {
	  print (mysqli_error($sql));
	  }
	$row = mysqli_fetch_array($result);
	$id = $row["id"];
	if ($id == "") {
	  $id = -1;
	  }
	return $id	;
	}
	
  function GetLabelID($labelname){
    $sql_query = "SELECT id FROM labels WHERE name='$labelname'";
	$result = mysqli_query($sql, $sql_query);
	if (!$resutl) {
	  print (mysqli_error($sql));
	  }
	$row = mysqli_fetch_array($result);
	$id = $row["id"];
	if ($id == "") {
	  $id = -1;
	  }
	return $id	;
	}


  function GetFeattypeID($artistname){
    $sql_query = "SELECT id FROM feattypes WHERE feattype='$artistname'";
	$result = mysqli_query($sql, $sql_query);
    if (!$resutl) {
	  print (mysqli_error($sql));
	  }
	$row = mysqli_fetch_array($result);
	$id = $row["id"];
	if ($id == "") {
	  $id = -1;
	  }
	return $id	;
	}


	
		
  function GetArtistName($id){
    $sql_query = "SELECT name FROM artists WHERE id='$id'";
	$result = mysqli_query($sql, $sql_query);
	$row = mysqli_fetch_array($result);
	$name = $row["name"];
	return $name;
	}	
	
  function GetLabelName($id){
    $sql_query = "SELECT name FROM labels WHERE id='$id'";
	$result = mysqli_query($sql, $sql_query);
	$row = mysqli_fetch_array($result);
	$name = $row["name"];
	return $name;
	}	

  function GetCityName($id){
    $sql_query = "SELECT name FROM cities WHERE id='$id'";
	$result = mysqli_query($sql, $sql_query);
	$row = mysqli_fetch_array($result);
	$name = $row["name"];
	return $name;
	}	
	
  function GetCityID($cityname){
    $sql_query = "SELECT id FROM cities WHERE name='$cityname'";
	$result = mysqli_query($sql, $sql_query);
    if (!$resutl) {
	  print (mysqli_error($sql));
	  }
	$row = mysqli_fetch_array($result);
	$id = $row["id"];
	if ($id == "") {
	  $id = -1;
	  }
	return $id	;
	}	// zwraca -1 jezeli nie ma takiego miasta w bazie lub id jezeli znalazl;		


?>