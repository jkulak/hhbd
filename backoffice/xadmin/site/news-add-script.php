<?php

require_once 'init.php';

use PHPImageWorkshop\ImageWorkshop as ImageWorkshop;
use Stringy\StaticStringy;

$userid = $_SESSION['adminuserid'];

$news = isset($_POST['news']) ? $_POST['news'] : '';
$title = isset($_POST['title']) ? $_POST['title'] : '';

$artists = isset($_POST['artists']) ? $_POST['artists'] : '';
$labels = isset($_POST['labels']) ? $_POST['labels'] : '';
$albums = isset($_POST['albums']) ? $_POST['albums'] : '';
$added = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d H:i:s');

$tmpFile = isset($_FILES['graph']['tmp_name']) ? $_FILES['graph']['tmp_name'] : '';

// Generate image filename
$coverFileName = '';
if ('' != $tmpFile) {
    $coverFileName = date('Y-m-d') . '-' . $title;
    $coverFileName = StaticStringy::safeTruncate($coverFileName, SLUG_LENGTH);
    $coverFileName = StaticStringy::slugify($coverFileName) . '.jpg';
}

// Add to the database
$sql = 'INSERT INTO news (title, news, addedby, added, graph) VALUES ' . '("' . mysql_real_escape_string($title) . '", "' . mysql_real_escape_string($news) . '", "' . $userid . '", "' . $added . '", "' . $coverFileName . '")';
$result = mysql_query($sql);
if ($result) {
    $smarty->assign('info', 'News został dodany do bazy.');
    $newsid = mysql_insert_id();

    // Upload image and generate thumbnails
    if ('' != $tmpFile) {
        $path = $config['image_upload'];
        $original = $path . '/news-original/' . $coverFileName;

        if (move_uploaded_file($tmpFile, $original)) {
            $smarty->assign('fileinfo', 'Skopiowano grafikę na serwer.');

            // Saving the result
            $createFolders = true;
            $backgroundColor = null; // transparent, only for PNG (otherwise it will be white if set null)
            $imageQuality = 95;
            $conserveProportion = true;
            $positionX = 0; // px
            $positionY = 0; // px
            $position = 'MM';

            $image = ImageWorkshop::initFromPath($original);
            $image->resizeInPixel(800, 800, $conserveProportion, $positionX, $positionY, $position);
            $image->save($path, 'news/' . $coverFileName, $createFolders, $backgroundColor, $imageQuality); //350

            $image = ImageWorkshop::initFromPath($original);
            $image->resizeInPixel(150, 150, $conserveProportion, $positionX, $positionY, $position);
            $image->save($path, 'news-thumbs/' . $coverFileName, $createFolders, $backgroundColor, $imageQuality); //148

            $image = ImageWorkshop::initFromPath($original);
            $image->resizeInPixel(50, 50, $conserveProportion, $positionX, $positionY, $position);
            $image->save($path, 'news-glyphs/' . $coverFileName, $createFolders, $backgroundColor, $imageQuality); //35

        } else {
            $smarty->assign('fileinfo', '<font color=red>Nie skopiowano grafiki na serwer.</font>');
        }
    }

    // Assign artists, albums and labels with the news
    $artistsarray = explode(',', $artists);
    foreach ($artistsarray as $artist) {
        if ($artist != '') {
            $sql = 'INSERT INTO news_artist_lookup (artistid, newsid) ' . 'VALUES ("' . $artist . '", "' . $newsid . '")';
            $resutl = mysql_query($sql);
        }
    }

    $labelsarray = explode(',', $labels);
    foreach ($labelsarray as $label) {
        if ($label != '') {
            $sql = 'INSERT INTO news_label_lookup (labelid, newsid) ' . 'VALUES ("' . $label . '", "' . $newsid . '")';
            $resutl = mysql_query($sql);
        }
    }

    $albumsarray = explode(',', $albums);
    foreach ($albumsarray as $album) {
        if ($album != '') {
            $sql = 'INSERT INTO news_album_lookup (albumid, newsid) ' . 'VALUES ("' . $album . '", "' . $newsid . '")';
            $resutl = mysql_query($sql);
        }
    }

} else {
    $smarty->assign('info', '<font color=red>News NIE został dodany do bazy.</font> (' . mysql_error() . ')');
}

$smarty->assign('ctitle', 'DODAWANIE NEWSA');
$smarty->assign('mainsection', '<font color="#6f91ac">DODAWANIE NEWSA</font>');
$smarty->assign('body_template', 'site/news-add-form.tpl');
$smarty->assign('data_dodania', $added);
