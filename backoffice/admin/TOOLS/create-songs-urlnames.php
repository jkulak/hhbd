<?php

require_once __DIR__ . '/../connect_to_database.php';

function create_urlname($oldname, $cut40, $lower) {
	$toreplace = array(' ', '?', ':', '*', '|', '/', '\\', '"', '<', '>', '&', '!', '-', '+', '%', '^', '(', ')', '#', ';', '~', '`', '[', ']', '{', '}', ',', '=') ;
	$name = str_replace($toreplace, '_', $oldname);

	// ZMIANA POLSKICH LITEREK!
	$toreplace = array('@', '$', 'ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ż', 'ź', 'Ś', 'Ł', 'Ż', 'Ń', 'Ę', 'Ć', 'Ą', 'Ó', 'Ź');
	$replaceto = array('a', 's', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'S', 'L', 'Z', 'N', 'E', 'C', 'A', 'O', 'Z');
	$name = str_replace($toreplace, $replaceto, $name);

	$name = str_replace('___', '_', $name);
	$name = str_replace('__', '_', $name);

	$name = str_replace(array('\'', '.'), '', $name);

	while (strlen($name) > 0 && $name[strlen($name) - 1] == '_') {
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

$sql_query = 'SELECT id, title FROM songs WHERE (urlname="") LIMIT 100';
$result = mysqli_query($sql, $sql_query);
if (!$result) {
	print(mysqli_error($sql));
	exit;
}

while ($row = mysqli_fetch_array($result)) {
	$newname = create_urlname($row['title'], 1, 1);
	$basename = $newname;

	$inum = 1;

	$check_query = 'SELECT title, urlname FROM songs WHERE urlname="' . mysqli_real_escape_string($sql, $newname) . '"';
	$res = mysqli_query($sql, $check_query);

	while (mysqli_num_rows($res)) {
		$inum++;
		$newname = $basename . '_' . $inum;
		$check_query = 'SELECT title, urlname FROM songs WHERE urlname="' . mysqli_real_escape_string($sql, $newname) . '"';
		$res = mysqli_query($sql, $check_query);
	}

	print ('URLNAME: <strong>' . $newname . '</strong><BR>');
	$update_query = 'UPDATE songs SET urlname="' . mysqli_real_escape_string($sql, $newname) . '" WHERE id="' . intval($row['id']) . '"';
	mysqli_query($sql, $update_query);
}

?>
