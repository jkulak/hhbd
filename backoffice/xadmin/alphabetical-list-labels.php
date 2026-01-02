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
 
include ('include/connect-to-database.php');
 
 
 
$sql = 'SELECT id, name AS n FROM labels ORDER BY name';
$result = mysql_query($sql);

print ('<strong>LISTA WYTWORNI:</strong><ul class="smallindent" style="list-style-type: none;">');
while ($row = mysql_fetch_array($result)) {
	print ('<a href="#" onClick="a(\'' . $row['id'] . '\', \'' . $id . '\')"><li>' . $row['n'] . '</li>');
	}
print ('</ul>');
?>
</body>
</html> 