<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('connect_to_database.php');

  include ('template_top.php');

$sql_query = 'SELECT id, review, addedby, albumid, title, added FROM album_reviews WHERE status=0';
$result = mysqli_query($sql, $sql_query);
while ($row = mysqli_fetch_array($result)) {
	print ('<div id="reviews">');
    print ('Dodana ' . $row['added'] . ' przez: ' . $row['addedby'] . '<BR>');
	print ('<strong>' . $row['title'] . '</strong><BR>');
	print ($row['review'] . '<BR><BR>');
	print ('[<a href="mod_album_reviews.php?id=' . $row['id'] . '&action=accept">+</a>]&nbsp;');
	print ('[<a href="mod_album_reviews.php?id=' . $row['id'] . '&action=reject">x</a>]&nbsp;');
    print ('</div>');	
	}
	
 include ("template_bottom.php");
?>