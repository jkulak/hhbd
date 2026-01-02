<?php

function create_urlname($oldname) {
	$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '&', '!', '-', '+', '%', '^', '(', ')', '#', ';', '~', '`', '[', ']', '{', '}', ',') ;
	$name = str_replace($toreplace, '_', $oldname);

	var_dump($oldname);

	// ZMIANA POLSKICH LITEREK!
	$toreplace = array('@', '$', '±', 'æ', 'ê', '³', 'ñ', 'ó', '¶', '¼', '¿', '¦', '£', '¯', 'Ñ', 'Ê', 'Æ', '¡', 'Ó', '¬');
	$toreplace = array('ą', 'ś', 'ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ż', 'ź', 'S', 'Ł', 'Ż', 'Ń', 'Ę', 'Ć', 'Ą', 'Ó', 'Ź');
	
	print_r($toreplace);

	$replaceto = array('a', 's', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'S', 'L', 'Z', 'N', 'E', 'C', 'A', 'O', 'Z');  
	$name = str_replace($toreplace, $replaceto, $name);
	
	$name = str_replace('___', '_', $name);
	$name = str_replace('__', '_', $name);
	
	$name = str_replace(array('\'', '.'), '', $name);
	
	if ($name[strlen($name) - 1] == '_') $name = substr($name, 0, strlen($name) - 1);
	
	if ($name == '') $name = '_';
	
	return strtolower(substr($name, 0, 40));
	}
?>
