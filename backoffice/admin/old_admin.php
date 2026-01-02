<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>Panel administracyjny</title>
<style type="text/css">
<!--
body,td,th {
	font-family: Tahoma;
	font-size: 10px;
	color: #FFFFFF;
}
body {
	background-color: #5F889E;
	margin-left: 0px;
	margin-top: 0px;
	margin-right: 0px;
	margin-bottom: 0px;
}
-->
</style></head>

<body>
<table width="100%" height="100%"  border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td width="15%" valign="top"><br>
      <table width="90%"  border="0" align="center" cellpadding="0" cellspacing="0">
        <tr>
          <td>
		<?php
			include ('menu.php');
		?>		
		  </td>
        </tr>
    </table></td>
    <td width="85%" valign="top"><br>
      <table width="90%"  border="0" align="center" cellpadding="0" cellspacing="0">
        <tr>
          <td>
		  <?php
  			// If the user wants to add a joke
  			if ($_GET['action'] == '') {
			  print ('Panel administracyjny');
			  }
			else {
  	  	  	 //$actions_arr = array('add'=>'add.php', 'edit'=>'e
		  	 @ include($_GET['action'] . '_form.php');		  
			 }
		  ?>
		  
		  </td>
        </tr>
    </table></td></tr>
</table>
</body>
</html>
