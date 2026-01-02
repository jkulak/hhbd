<?php

include ('connect_to_database.php');

$id = $_GET['id'];
$action = $_GET['action'];

if ($action == 'accept') {
    $status = 1;
	}
else if ($action == 'reject') {
    $status = 9;
	}
else header ( 'Location: admin.php?s=urlhack');


$sql_query = 'UPDATE album_reviews SET status="' . $status . '" WHERE id="' . $id . '"';
$result = mysqli_query($sql, $sql_query);

header ( 'Location: index.php');


	
?>