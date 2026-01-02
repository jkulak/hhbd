<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-2">
</head>
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
 
include ('include/connect-to-database.php');
 
 
 
$sql = 'SELECT t1.title AS at, t2.title AS st, t2.id FROM albums t1, songs t2, album_lookup t3 WHERE (t3.songid=t2.id AND t3.albumid=t1.id) ORDER BY t2.title, t1.title';
$result = mysql_query($sql);

print ('<strong>LISTA UTWORÓW:</strong><ul class="smallindent" style="list-style-type: none;">');
while ($row = mysql_fetch_array($result)) {
	print ('<a href="#" onClick="a(\'' . $row['id'] . '\', \'' . $id . '\')"><li>' . $row['st'] . ' (' . strtoupper($row['at']) . ')</li>');
	}
print ('</ul>');
?>
</body>
</html> 