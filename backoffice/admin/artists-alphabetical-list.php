<?php

/**
 *
 *
 * @version $Id$
 * @copyright 2005
 **/

require_once __DIR__ . '/connect_to_database.php';

$sql_query = 'SELECT id, name FROM artists ORDER BY name';
$result = mysqli_query($sql, $sql_query);

print ('<strong>LISTA WYKONAWCOW:</strong><ul class="smallindent">');
while ($row = mysqli_fetch_array($result)) {
	print ('<li><strong>' . $row['id'] . '</strong>' . ' - ' . htmlspecialchars($row['name']));
}
print ('</ul>');
?>
