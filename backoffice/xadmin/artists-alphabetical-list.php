<html>
<body style="font-size: 9px; line-height: 10px; font-family: tahoma; ">
<script type="text/javascript">
	function a(i,id) {
		if (opener.document.getElementById(id).value == '') {
			opener.document.getElementById(id).value = i
		} else {
			opener.document.getElementById(id).value = opener.document.getElementById(id).value + ',' + i
			}
		}
</script>

<?php

$id = $_GET['id'];

/**
 * 
 *
 * @version $Id$
 * @copyright 2005 
 **/
 
include ('include/connect-to-database.php');
 
 
 
$sql_query = 'SELECT id, name AS n FROM artists ORDER BY name';
$result = mysqli_query($sql, $sql_query);

print ('<strong>LISTA WYKONAWCOW:</strong><ul class="smallindent" style="list-style-type: none;">');
while ($row = mysqli_fetch_array($result)) {
	print ('<a href="#" onClick="a(\'' . $row['id'] . '\', \'' . $id . '\')"><li>' . $row['n'] . '</li>');
	}
print ('</ul>');
?>
</body>
</html> 