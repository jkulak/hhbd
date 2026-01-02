<?php

function create_urlname($oldname, $cut40, $lower) {
	$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '&', '!', '-', '+', '%', '^', '(', ')', '#', ';', '~', '`', '[', ']', '{', '}', ',', '=') ;
	$name = str_replace($toreplace, '_', $oldname);

	// ZMIANA POLSKICH LITEREK!
	$toreplace = array('@', '$', '±', 'æ', 'ê', '³', 'ñ', 'ó', '¶', '¼', '¿', '¦', '£', '¯', 'Ñ', 'Ê', 'Æ', '¡', 'Ó', '¬');
	$replaceto = array('a', 's', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'S', 'L', 'Z', 'N', 'E', 'C', 'A', 'O', 'Z');  
	$name = str_replace($toreplace, $replaceto, $name);
	
	$name = str_replace('___', '_', $name);
	$name = str_replace('__', '_', $name);
	
	$name = str_replace(array('\'', '.'), '', $name);
	
	while ($name[strlen($name) - 1] == '_') {
		$name = substr($name, 0, strlen($name) - 1);
		}
	
	if ($name == '') $name = '_';
	
	if ($cut40) {
		$name = substr($name, 0, 40);
		}
		
	if ($lower) {
		$name = strtolower($name);
		}
		
	return $name;
	}
	
/**
 * Create generate slug
 * 
 * @since 2011-08-25
 */
function generateSlug($string, $max) {
    if (!isset($max)) {
        $max = 60;
    }


        $string = strtolower($string);
        $polskie = array(',', ' - ',' ','ę', 'Ę', 'ó', 'Ó', 'Ą', 'ą', 'Ś', 's', 'ł', 'Ł', 'ż', 'Ż', 'Ź', 'ź', 'ć', 'Ć', 'ń', 'Ń','-',"'","/","?", '"', ":", 'ś', '!','.', '&', '&amp;', '#', ';', '[',']','domena.pl', '(', ')', '`', '%', '”', '„', '…');
        $miedzyn = array('-','-','-','e', 'e', 'o', 'o', 'a', 'a', 's', 's', 'l', 'l', 'z', 'z', 'z', 'z', 'c', 'c', 'n', 'n','-',"","","","","",'s','','', '', '', '', '', '', '', '', '', '', '', '', '');
        $string = str_replace($polskie, $miedzyn, $string);

        // usuń wszytko co jest niedozwolonym znakiem
        $string = preg_replace('/[^0-9a-z\-]+/', '', $string);

        // zredukuj liczbę myślników do jednego obok siebie
        $string = preg_replace('/[\-]+/', '-', $string);

        // usuwamy możliwe myślniki na początku i końcu
        $string = trim($string, '-');

        $string = stripslashes($string);

        // na wszelki wypadek
        $string = urlencode($string);

        return substr($string, 0, $max);
    }
	
	
	
function createthumbnail($dstfilename, $srcfilename, $frmcolor, $width, $height ){
// ****************************************************************************
// *** USTAWIENIE HEIGHT NA 0 POWODUJE PROPORCJONALNE DOPASOWANIE WYSOKOSCI ***
// ****************************************************************************

	//pobieram rozmiary originalnego
	list($width_orig, $height_orig) = getimagesize($srcfilename);

	if (!$height) $height = (int) (($width / $width_orig) * $height_orig);

	//tworze docelowy jpg
	$image_p = imagecreatetruecolor($width, $height);

	$image = imagecreatefromjpeg($srcfilename);
	imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
	imagejpeg($image_p, $dstfilename);
	}	
	
?>