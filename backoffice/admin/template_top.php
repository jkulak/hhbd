<?php
   function getmicrotimeex(){ 
   list($usec, $sec) = explode(" ",microtime()); 
   return ((float)$usec + (float)$sec); 
} 
	$start = getmicrotimeex();
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf8">
<title>Panel administracyjny</title>
<link rel="stylesheet" href="css/hhbd.css">
<link rel="stylesheet" href="css/s.css">
</style></head>


<body>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td width="160" valign="top">
	<?php include ('menu.php'); ?>
	</td>
    <td valign="top">