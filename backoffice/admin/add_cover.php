<?php

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include ('connect_to_database.php');

// Read configuration file
$config = parse_ini_file('config/app.ini');
$content_directory = $config['content_directory'];

include ('template_top.php');
echo '<h2>Dodaj okładkę</h2>';

function createthumbnail($dstfilename, $srcfilename, $dstsize, $srcsize, $frmcolor){
    $image_p = imagecreatetruecolor($dstsize, $dstsize);
    imagefilledrectangle($image_p, 0, 0, $dstsize, $dstsize, $frmcolor);
    $image = imagecreatefromjpeg($srcfilename);
    list($width, $height) = getimagesize($srcfilename);
    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $dstsize, $dstsize, $width, $height);
    imagejpeg($image_p, $dstfilename);
    }

$id = $_POST['albumid'];
$filename = $_FILES['coverfile']['tmp_name'];

// Get album data
$sql_query = 'SELECT title, year, singiel FROM albums WHERE id=' . $id;
$result = mysqli_query($sql, $sql_query);
$album = mysqli_fetch_array($result);

// Get artist name
$sql_query = 'SELECT t1.name FROM artists AS t1, album_artist_lookup AS t2 WHERE (t1.id=t2.artistid AND t2.albumid=' . $id . ')';
$result = mysqli_query($sql, $sql_query);
$artist = mysqli_fetch_array($result);

// Build image filename
$newfilename = strtolower(trim($artist[0]) . '-' . trim($album[0]));
$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '\'', '.', ',', '%', '@', '#') ;
$newfilename = str_replace($toreplace, '-', $newfilename);

$newname = $content_directory . '/a/' . $newfilename . '-hhbdpl.jpg';
$thumb75 = $content_directory . '/a/' . 'th/' . $newfilename . '-hhbdpl-th.jpg';

$newcover = $newfilename . '-hhbdpl.jpg';

// DODANIE NAZWY OKLADKI DO TABELI ALBUMÓW
$sql_query = 'UPDATE albums SET cover="' . $newcover . '" WHERE id=' . $id;
if (mysqli_query($sql, $sql_query)) {
    if ( move_uploaded_file($filename, $newname) ) {

        // Causes trouble in case file already exists
        createthumbnail($thumb75, $newname, 75, 250, 0);
        echo '<div class="message good">Okładka dodana, i powinieneś widzieć ją niżej :)</div>';
        echo '<img src="http://www.hhbd.pl/imgs/database/albums/' . $newcover . '">';
    } else {
        echo '<div class="message bad">Wygląda na to, że nie udało się skopiować pliku tam gdzie planowaliśmy :(</div>';
        echo '<p>$filename:<pre>' . $filename . "</pre></p>";
        echo '<p>$newname:<pre>' . $newname . "</pre></p>";
    }
} else {
    echo('<P>Nie dodano okladki do bazy! (' . mysqli_error($sql) . ')<br>');
}

include('template_bottom.php');
