<?php

session_start();
if (!isset($_SESSION['username'])) {
	header("Location: index.php");
	exit();
}

include 'include/functions.php';
include 'functions.php';
include 'template_top.php';
include 'connect_to_database.php';
include 'database-functions.php';

function goback() {
	print("<a href=\"add_artist_form.php?name=$_POST[name]&website=$_POST[website]&" .
		"nameid=$_POST[nameid]&altname1=$_POST[altname1]&altname2=$_POST[altname2]&altname3=$_POST[altname3]" .
		"\">Popraw</a>");
}

function changename($oldname) {
	$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '&', '!', '-', '+', '%', '^', '(', ')', '#', ';', '~', '`', '[', ']', '{', '}', ',');
	$name = str_replace($toreplace, '_', $oldname);
	// ZMIANA POLSKICH LITEREK!
	$toreplace = array('@', '$', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�');
	$replaceto = array('a', 's', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'S', 'L', 'Z', 'N', 'E', 'C', 'A', 'O', 'Z');
	$name = str_replace($toreplace, $replaceto, $name);
	$name = str_replace('___', '_', $name);
	$name = str_replace('__', '_', $name);
	$name = str_replace(array('\'', '.'), '', $name);
	if (substr($name, strlen($name) - 1, 1) == '_') {$name = substr($name, 0, strlen($name) - 1);}
	return substr($name, 0, 40);
}

$profil = $_POST['profil'];
$concertinfo = $_POST['concertinfo'];

//sprawdzanie poprawnosci danych

$name = $_POST['name'];

get_artist_id($name);

if ((!$name) OR (get_artist_id($name))) {
	print("<b>Brak wykonawcy, lub wykonawca istnieje w bazie!</b><br>");
	goback();
	include 'template_bottom.php';
	exit();
}

$cityid = $_POST['cityid'];

$data_dodania = date('YmdHis');

$addtype = $_POST['nametype'];

//jezeli tutaj doszlimy, to znaczy ze wszystkie dane w porzadku

print("Wykonawca: $name<BR>");
if ($_POST[altname1] != "") {
	print("znany tez jako: $_POST[altname1]<BR>");
}
if ($_POST[altname2] != "") {
	print("znany tez jako: $_POST[altname2]<BR>");
}
if ($_POST[altname3] != "") {
	print("znany tez jako: $_POST[altname3]<BR>");
}

print("Website: $_POST[website]<BR>");
print("Imie i nazwisko: $_POST[realname]<BR>");

$newname = create_urlname($_POST['name'], 1, 1);
$basename = $newname;
$inum = 1;
$sql_query = 'SELECT name, urlname FROM artists WHERE urlname="' . $newname . '"';
$res = mysqli_query($sql, $sql_query);
while (mysqli_num_rows($res)) {
	$inum++;
	$newname = $basename . '_' . $inum;
	$sql = 'SELECT name, urlname FROM artists WHERE urlname="' . $newname . '"';
	$res = mysqli_query($sql);
}

$sql_query = "INSERT INTO artists (website, name, urlname, realname, since, till, type, added, addedby, profile, concertinfo) " .
"VALUES ('$_POST[website]', '$name', '$newname', '$_POST[realname]', '$_POST[date]', '$_POST[till]', '$addtype', '$data_dodania', '" . $_SESSION['userid'] . "'" . ',"' . $profil . '","' . $concertinfo . '")';
if (mysqli_query($sql, $sql_query)) {
	$nameid = mysqli_insert_id($sql);
	print("<BR><BR><B>Wykonawca '$_POST[name]' zostal dodany, ID: $nameid </B><br><br>");
} else {
	echo ("<P>Nie dodano wykonawcy '$_POST[name]' (" . mysqli_error($sql) . ")<br>");
}

// dodanie pierwszego nicku
if ($_POST[altname1] != "") {
	$sql_query = "INSERT INTO altnames_lookup (artistid, altname) " .
	"VALUES ('$nameid', '$_POST[altname1]')";

	if (mysqli_query($sql, $sql_query)) {
		$insertID = mysqli_insert_id($sql);
		print("Dodano ksywe '$_POST[altname1]' dla wykonawcy: " . GetArtistName($nameid) . "<br>");
	} else {
		echo ("<P>Nie dodano ksywy '$_POST[altname1]' (" . mysqli_error($sql) . ")<br>");
	}
}

// dodanie drugiego nicku
if ($_POST[altname2] != "") {
	$sql_query = "INSERT INTO altnames_lookup (artistid, altname) " .
	"VALUES ('$nameid', '$_POST[altname2]')";

	if (mysqli_query($sql, $sql_query)) {
		$insertID = mysqli_insert_id();
		print("Dodano ksywe '$_POST[altname2]' dla wykonawcy: " . GetArtistName($nameid) . "<br>");
	} else {
		echo ("<P>Nie dodano ksywy '$_POST[altname2]' (" . mysqli_error($sql) . ")<br>");
	}
}

// dodanie trzeciego nicku
if ($_POST[altname3] != "") {
	$sql_query = "INSERT INTO altnames_lookup (artistid, altname) " .
	"VALUES ('$nameid', '$_POST[altname3]')";

	if (mysqli_query($sql, $sql_query)) {
		$insertID = mysqli_insert_id();
		print("Dodano ksywe '$_POST[altname3]' dla wykonawcy: " . GetArtistName($nameid) . "<br>");
	} else {
		echo ("<P>Nie dodano ksywy '$_POST[altname3]' (" . mysqli_error($sql) . ")<br>");
	}
}

// *****************
// dodawanie nowego miasta do bazy miast
// *****************
if ($cityid == -1) {
	$sql_query = "INSERT INTO cities (name, added, addedby) " .
	"VALUES ('$_POST[city]', '$data_dodania', '" . $_SESSION['userid'] . "')";

	if (mysqli_query($sql, $sql_query)) {
		$cityid = mysqli_insert_id($sql);
		print("Dodano miasto '$_POST[city]' do bazy miast!<br>");
	} else {
		echo ("<P>Nie dodano miasta: '$_POST[city]'! (" . mysqli_error($sql) . ")<br>");
	}
}

if ($cityid != '') {
	$sql_query = "INSERT INTO city_artist_lookup (cityid, artistid) " .
	"VALUES ('$cityid', '$nameid')";

	if (mysqli_query($sql, $sql_query)) {
		$insertID = mysqli_insert_id($sql);
		print('Dodano miasto \'' . GetCityName($cityid) . '\'dla wykonawcy: ' . GetArtistName($nameid) . '<br>');
	} else {
		echo ('<P>Nie dodano miasta \'' . GetCityName($cityid) . '\' (' . mysqli_error($sql) . ')<br>');
	}
}

print("<a href=\"add_artist_form.php?\">Dodaj nast�pnego wykonawc�.</a><br>");

include 'template_bottom.php';
?>
