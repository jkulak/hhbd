<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php?er=24");
	exit();
    }

include ('template_top.php');


?>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td>
      <div align="left"><b><font size="3">STRONA GLOWNA</font></b></div>
    </td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td align="left">
	<?php
	if (isset($_SESSION['count'])) {
   $_SESSION['count']++;
  }
else {
  $_SESSION['count'] = 1;
  }

print ('Zalogowany: ' . $_SESSION['username'] . ' (id: ' . $_SESSION['userid'] . ')<br>');
print ('Ogladasz ta strone: ' . $_SESSION['count'] . ' raz.<br>');
	 $date = strtotime($_SESSION['lastlogin']);
	 $date = date('d-m-Y, H:i:s', $date);
print ('Ostatni login: ' . $date . '<br>');
print ('Logowales sie: ' . $_SESSION['timesloggedin'] . ' razy.' . '<br>');
?>
	</td>
  </tr>
</table>

<?php
include ('template_bottom.php');
?>
