<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('connect_to_database.php');

  include ('template_top.php');

$sql_query = 'SELECT t1.id, t1.songid, t1.sample, t1.addedby, t1.added, t2.title, t2.urlname, t3.login, t3.urlname AS userurlname ' .
	   'FROM song_samples AS t1, songs AS t2, users AS t3 ' .
	   'WHERE (t1.songid=t2.id AND t1.status=0 AND t1.addedby=t3.id)';
$result = mysqli_query($sql, $sql_query);
while ($row = mysqli_fetch_array($result)) {
	print ('<form method="post" action="mod_samples.php" style="background-color: #999999; padding: 4px;">');
    print ('Dodany ' . $row['added'] . ' przez: <a href="http://www.hhbd.pl/u/' . $row['userurlname'] . '" target="_blank">' . $row['login'] . '</a><BR><BR>');
	print ('Do piosenki: <strong><a href="http://www.hhbd.pl/s/' . $row['urlname'] . '" target="_blank">' . $row['title'] . '</a></strong><BR>');
	print ('Sampel z: <input type="text" name="sample" value="' . $row['sample'] . '" style="width: 350px"><BR><BR>');
	//print ('[<a href="mod_samples.php?id=' . $row['id'] . '&action=accept">+</a>]&nbsp;');
	print ('<input type="hidden" name="sid" value="' . $row['id'] . '">[<a href="mod_samples.php?id=' . $row['id'] . '&action=reject">ODRZUC</a>]&nbsp;<input type="submit" value="AKCEPTUJ">');
	
    print ('</form>');	
	}
	
 include ("template_bottom.php");
?>