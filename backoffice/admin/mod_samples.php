<?php

include ('connect_to_database.php');

$id = $_GET['id'];

if (!isset($id)) $id = $_POST['sid'];

$action = $_GET['action'];

$status = 1;

if ($action == 'reject') {
    $status = 9;
	}
	
	
$sample = $_POST['sample'];
if (!isset($sample)) $sample = $_GET['sample'];

$sql_query = 'UPDATE song_samples SET sample="' . $sample . '", status="' . $status . '" WHERE id="' . $id . '"';
$result = mysql_query($sql, $sql_query);

header ( 'Location: index.php');


	
?>