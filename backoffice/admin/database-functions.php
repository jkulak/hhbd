<?php

/**
 * 
 *
 * @version $Id$
 * @copyright 2005 
 **/
 
include ('connect_to_database.php'); 

// **************************************************************************************************
// * FUNKCJE POBIERAJ�CE Z BAZY                                                                     *
// **************************************************************************************************
function get_artist_id($name){
	$sql_query = 'SELECT id FROM artists WHERE name="' . $name . '"';
	$result = @mysqli_query($sql, $sql_query);
	$row = @mysqli_fetch_array($result);
	$id = $row['id'];
	if (!isset($id)) $id = 0;
	return $id;
	}

function get_artist_name($artistid){
	$sql_query = 'SELECT name FROM artists WHERE id="' . $artistid . '"';
	$result = @mysqli_query($sql, $sql_query);
	$row = @mysqli_fetch_array($result);
	$name = $row['name'];
	return $name;
	}
	
function get_label_name($labelid){
	$sql_query = 'SELECT name FROM labels WHERE id="' . $labelid . '"';
	$result = @mysqli_query($sql, $sql_query);
	$row = @mysqli_fetch_array($result);
	$name = $row['name'];
	return $name;
	}
	

	
// **************************************************************************************************
// * FUNKCJE DODAJ�CE DO BAZY                                                                       *
// **************************************************************************************************
 
function add_artist($name, $addedby) {
	$urlname = create_urlname($name);
	$basename = $urlname;
	$inum = 1;		

	$sql_query = 'SELECT name FROM artists WHERE urlname="' . $urlname . '"';
	$res = mysqli_query($sql, $sql_query);	
	while (mysqli_num_rows($res)) {		
		$inum++;
		$urlname = $basename . '_' . $inum;		
		$sql_query = 'SELECT name FROM artists WHERE urlname="' . $urlname . '"';
		$res = mysqli_query($sql, $sql_query);
		}
	$sql_query = 'INSERT INTO artists (name, urlname, added, addedby) VALUES ("' . $name . '", "' . $urlname . '", "' . date('YmdHis') . '", "' . $addedby . '")';
    $result = mysqli_query($sql, $sql_query);
	$return = mysqli_insert_id($sql);
	return($return);
	}		
	
function add_artist_ex() {
	
	}
?>