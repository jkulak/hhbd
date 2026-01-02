<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include ('connect_to_database.php');
include ('functions.php');

// Read configuration file
$config = parse_ini_file('config/app.ini');
$content_directory = $config['content_directory'];

include ('template_top.php');

$id = $_POST['id'];
$setmain = ($_POST['main'] == 1)?'y':'n';

function goback(){
    print ("<a href=\"add_album_form.php?artist=$_GET[artist]&artist1id=$_GET[artist_list]&" .
    "label=$_GET[label]&labelid=$_GET[label_list]&title=$_GET[title]&songcount=$_GET[songcount]&" .
    "date=$_GET[date]&addedby=$_GET[addedby]&artist2=$_GET[artist2]&artist2id=$_GET[artist_list2]&" .
    "ep=$_GET[ep]&lp=$_GET[lp]&mc=$_GET[mc]&cd=$_GET[cd]&artist3=$_GET[artist3]&artist3id=$_GET[artist_list3]" .
    "\">Popraw</a>");
}

//sprawdzanie poprawnosci danych
if ($id == '') {
    print ('<b>Wybierz jakiegos wykonawce!</b><br>');
    goback();
    include('template_bottom.php');
    exit();
}

$artist_photo = $_FILES['artist_photo']['tmp_name'];

$sql_query = 'SELECT count(id) FROM artists_photos WHERE artistid=' . $id;
$result = mysqli_query($sql, $sql_query);
$numphotos = mysqli_fetch_array($result);
print ('w bazie jest juz ' . $numphotos[0] . ' zdjec tego wykonawcy.<BR><BR>');

$sql_query = 'SELECT name FROM artists WHERE id=' . $id;
$result = mysqli_query($sql, $sql_query);
$artistname = mysqli_fetch_array($result);

$numphotos = $numphotos[0];
$numphotos++;
$newfilename = trim($artistname[0]) . '-' . $numphotos;

$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '\'', '.', ',') ;
$newfilename = str_replace($toreplace, '-', $newfilename);

$path = $content_directory . '/p/';
$newphoto = $newfilename . '-hhbdpl.jpg';
$newname = $path . $newphoto;

if ($setmain) {
    $sql_query = 'UPDATE artists_photos SET main="n" WHERE (main="y" AND artistid="' . $id . '")';
    $result = mysqli_query($sql, $sql_query);
}

// DODANIE ZDJECIA DO BAZY
$sql_query = 'INSERT INTO artists_photos (artistid, main, filename, description, source, sourceurl, addedby) ' .
       'VALUES (' . $id . ', "' . $setmain . '", "' . $newphoto . '", "' . $_POST['description'] . '", "' . $_POST['source'] .
       '", "' . $_POST['sourceurl'] . '", ' . $_SESSION['userid'] . ')';

if ($result = mysqli_query($sql, $sql_query)) {
    print ('<B>Zdjecie zostalo dodane do bazy...</b><BR>');
    if (move_uploaded_file($artist_photo,$newname)) {
        print ('skopiowano plik na serwer!<BR><BR><BR>');
        print ('<img src="http://www.hhbd.pl/' . 'imgs/database/artists/' . $newphoto . '"><BR><BR>');
        }
    else {
        print ('Nie skopiowano pliku tam gdzie trzeba...<BR><BR>');
        }
    } else {
        print ('Nie dodano zdjecia: (' . mysqli_error($sql) . ')<br>');
    }

 include('template_bottom.php');
