<?php

session_start();
if (!isset($_SESSION['username'])) {
	header("Location: index.php");
	exit();
}

include 'template_top.php';
include 'connect_to_database.php';
include 'functions.php';

include 'include/urlname-functions.php';

function goback() {
	echo '<a href="add_price_form.php?album-data=' . $_GET['album-data'] . '&albumid=' . $_GET['albumid'] . '&' . '">Popraw</a>';
}

// $file = $_FILES['myfile']['tmp_name'];
$albumid = $_GET['albumid'];
echo '<p>Album ID: ' . $albumid . '</p>';

$songs = explode("\n", $_GET['album-data']);
foreach ($songs as $line_num => $song) {
	$song = rtrim($song);

	$pos = strpos($song, '.');
	$track = substr($song, 0, $pos);
	print('track#: ' . $track . ' ');
	$song = substr($song, $pos + 2, strlen($song) - $pos - 2);

	$pos = strpos($song, '(');
	$title = substr($song, 0, $pos - 1);
	$toreplace = array('[', ']');
	$replaceto = array('(', ')');
	$title = str_replace($toreplace, $replaceto, $title);

	print('tytuł: ' . $title . ' ');
	$length = substr($song, $pos + 1, strlen($song) - $pos);
	print('czas: ' . $length . '<BR>');
	$length = substr($length, 2, 2) + substr($length, 0, strpos($length, ':')) * 60;
	print('sekund: ' . $length . '<BR>');

	// po co jest poniższa zamiana???
	$toreplace = array('π', 'ú', 'ü', '•', 'å', 'è');
	$replaceto = array('±', '∂', 'º', '°', '¶', '¨');
	$title = str_replace($toreplace, $replaceto, $title);

	$data_dodania = date("YmdHis");
	$sql_query = 'INSERT INTO songs (title, urlname, length, added, addedby) VALUES ("' . $title . '","' . create_urlname($title, 1, 1) . '", ' . $length .
	', \'' . $data_dodania . '\', ' . $_SESSION['userid'] . ')';

	if ($result = mysqli_query($sql, $sql_query)) {
		print('track: ' . $track . ' dodany<BR>');
		$songid = mysqli_insert_id($sql);
		$sql_query = "INSERT INTO album_lookup (albumid, songid, track) VALUES ('$albumid', '$songid', '$track')";

		if (mysqli_query($sql, $sql_query)) {
			print("<BR>Dodane powiazanie: albumid: $albumid, songid: $songid <br>");
		} else {
			echo ("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
		}
	}
}

include 'template_bottom.php';

?>
