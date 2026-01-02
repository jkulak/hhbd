<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('template_top.php');
include ('connect_to_database.php');?> 

<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td><font size="3"><b>DODAJ WYTWORNIE</b></font></td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td valign="top"> 
      <form action="add_label.php" method="POST" name="add_album" target="_self" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0">
          <tr align="left"> 
            <td width="180" height="90" valign="top"> 
              <p>nazwa wytw&oacute;rni: 
                <input name="name" type="text" size="20" maxlength="255" value="<?php print("$_GET[name]"); ?>" >
              </p>
            </td>
            <td height="90" valign="top" width="180"> 
              <p>strona internetowa wytw&oacute;rni: 
                <input name="website" type="text" size="20" maxlength="255" value="<?php print("$_GET[website]"); ?>" >
              </p>
            </td>
            <td height="90" valign="top" width="180">email wytw&oacute;rni: <br>
              <input name="email" type="text" size="20" maxlength="255" value="<?php print("$_GET[email]"); ?>" >
            </td>
          </tr>
		  <tr> 
            <td colspan="3" height="180" valign="top" align="left"> 
              <p><b>OPIS WYTWORNI</b><br>
                <textarea name="description" rows="10" style="width: 280px"><?php print ($_GET['description']); ?></textarea>
                <br>
                <font size="1">opis wytworni, to oficjalna informacja na temat<br>
                labelu, skopiowana ze strony oficjalnej</font></p>
              </td>
          </tr>
          <tr align="left"> 
            <td width="180" valign="top"> 
              <input type="submit" value="Dodaj wytw&oacute;rni&ecirc;" name="submit">
            </td>
            <td valign="top" width="180"></td>
            <td valign="top" width="180"></td>
          </tr>
        </table>
        </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
