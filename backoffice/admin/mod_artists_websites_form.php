<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('connect_to_database.php');

  include ('template_top.php');

$sql_query = 'SELECT t1.id, t1.name, t1.url, t1.artistid, t1.added, t2.login AS user, t2.id AS userid ' . 
	   'FROM artists_websites AS t1, users AS t2 ' .
	   'WHERE (t1.status=0 AND t1.addedby=t2.id)';
$result = mysqli_query($sql, $sql_query);
while ($row = mysqli_fetch_array($result)) {
	print ('<div id="reviews">');
    print ('Dodana ' . $row['added'] . ' przez: <a href="http://www.hhbd.6r.pl/?s=user&id=' . $row['userid'] . '">' . $row['user'] . '</a><BR>');
	print ('<strong>' . $row['name'] . '</strong><BR>');
	print ('<a href="' . $row['url'] . '" target="_blank">' . $row['url'] . '</a><BR><BR>');
	print ('[<a href="mod_artists_websites.php?id=' . $row['id'] . '&action=accept">+</a>]&nbsp;');
	print ('[<a href="mod_artists_websites.php?id=' . $row['id'] . '&action=reject">x</a>]&nbsp;');
    print ('</div>');	
	}
	
 include ("template_bottom.php");
?>