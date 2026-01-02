<?php

session_start();
if (!isset($_SESSION['username'])) {
	header("Location: index.php");
	exit();
}

include 'include/functions.php';
include 'template_top.php';
include 'connect_to_database.php';
include 'functions.php';
include 'database-functions.php';

function goback() {
	print('<a href="add_album_form.php?artistid=' . $_POST['artistid'] . "&labelid=$_POST[labelid]&title=$_POST[title]&" .
		"date=$_POST[date]&ep=$_POST[ep]&lp=$_POST[lp]&mc=$_POST[mc]&cd=$_POST[cd]&epforid=$_POST[epforid]&cdcatalog=$_POST[cdcatalog]&lpcatalog=$_POST[lpcatalog]&mccatalog=$_POST[mccatalog]&description=$_POST[description]\">Popraw</a>");
}

$description = $_POST['description'];

//sprawdzanie poprawnosci danych
if ($_POST[title] == "") {
	print("<b>Brak nazwy albumu!</b><br>");
	goback();
	include 'template_bottom.php';
	exit();
}

// wykonawca
$artistid = $_POST['artistid'];
$artist = $_POST['artist'];

if (!($artistid)) {
	print("<b>Brak wykonawcy! Wybierz chociaz jednego!</b><br>");
	goback();
	include 'template_bottom.php';
	exit();
}

// wytwornia
$labelid = $_POST['labelid'];
if (!($labelid)) {
	print("<b>Brak wytwórni!</b><br>");
	goback();
	include 'template_bottom.php';
	exit();
}

// data premiery
if ($_POST[date] == "") {
	print("<b>Brak daty premiery!</b><br>");
	goback();
	include 'template_bottom.php';
	exit();
}

// singiel
$ep = ($_POST['ep']) ? 1 : 0;

// nosniki
$cd = ($_POST['cd']) ? 1 : 0;
$mc = ($_POST['mc']) ? 1 : 0;
$lp = ($_POST['lp']) ? 1 : 0;

if ($lp == 0 AND $mc == 0 AND $cd == 0) {
	$cd = 1;
}

$data_dodania = date('YmdHis');

print("Tytuł: '$_POST[title]'<BR>");
$artist = get_artist_name($artistid);
print('Wykonawca: <strong>' . $artist . '</strong> (id: ' . $artistid . ')<BR>');

$label = get_label_name($labelid);
print("Wytwórnia: '$label' (id: $labelid)<BR>");
print("Data premiery: $_POST[date]<BR>");
print("Na kasecie: " . ($mc == 1 ? 'Tak' : 'Nie') . "<BR>");
print("Na CD: " . ($cd == 1 ? 'Tak' : 'Nie') . "<BR>");
print("Na winylu: " . ($lp == 1 ? 'Tak' : 'Nie') . "<BR>");
print("Singiel: " . ($ep == 1 ? 'Tak' : 'Nie') . "<Br>");
print('Numer katalogowy CD: ' . $_POST['cdcatalog'] . '<br>');
print('Numer katalogowy LP: ' . $_POST['lpcatalog'] . '<br>');
print('Numer katalogowy MC: ' . $_POST['mccatalog'] . '<br>');

print('Opis produkcji:<BR>' . $description . '<br><BR>');
print("Dodany przez: " . $_SESSION['userid'] . "<BR>");
print("Dodany do katalogu: $data_dodania<br>");

$urlname = create_urlname($_POST['title'], 1, 1);
$basename = $urlname;
$inum = 1;
$sql_query = 'SELECT title, urlname FROM albums WHERE urlname="' . $urlname . '"';
$res = mysqli_query($sql, $sql_query);
while (mysqli_num_rows($res)) {
	$inum++;
	$urlname = $basename . '_' . $inum;
	$sql_query = 'SELECT title, urlname FROM albums WHERE urlname="' . $urlname . '"';
	$res = mysqli_query($sql, $sql_query);
}

$sql_query = "INSERT INTO albums (title, urlname, labelid, year, media_mc, catalog_mc, media_cd, catalog_cd, " .
"media_lp, catalog_lp, epfor, singiel, addedby, added, cover, description, premier) VALUES ( " .
"'$_POST[title]', '$urlname', $labelid, '$_POST[date]', " .
"'$mc', '$_POST[mccatalog]', '$cd', '$_POST[cdcatalog]', '$lp', '$_POST[lpcatalog]', '$_POST[epforid]', '$ep', '" . $_SESSION['userid'] . "', '$data_dodania', '$newname'" . ',"' . $description . '", "' . $_POST['premier'] . '")';

if (mysqli_query($sql, $sql_query)) {
	$albumid = mysqli_insert_id($sql);
	print("<BR><BR><B>Album '$_POST[title]' zostal dodany, id: $albumid </B><br><br>");

} else {
	echo ("<P>Nie dodano albumu '$_POST[title]' (" . mysqli_error($sql) . ")<br>");
}

// dodanie powiazania album - artist 1
if ($artistid) {
	$sql_query = 'INSERT INTO album_artist_lookup (artistid, albumid) VALUES ("' . $artistid . '", "' . $albumid . '")';
	if (mysqli_query($sql, $sql_query)) {
		print("Dodane powiazanie: artist: $artistid, albumid: $albumid<br>");
	} else {
		echo ("<P>Nie dodano powiazania!' (" . mysqli_error($sql) . ")<br>");
	}
}

print("<a href=\"add_song_form.php?addedby=$_POST[addedby]&artist1id=$artist1id&track=1&albumid=" . $albumid . "\">Dodaj piosenki</a><br>");

include 'template_bottom.php';
?>
