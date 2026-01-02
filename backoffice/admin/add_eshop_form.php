<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }
	
  include ('template_top.php');
  include ('connect_to_database.php');
?> 
<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td> 
      <div align="left"><b><font size="3">DODAJ SKLEP INTERNETOWY</font></b></div>
    </td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td align="left"> 
      <form action="add_eshop.php" method="post" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0" align="left">
          <tr> 
            <td height="65" valign="top"> 
              <p><b>NAZWA SKLEPU</b><br>
                <input name="name" type="text" size="20" maxlength="255" value="<?php print("$_GET[url]"); ?>" >
                <br>
                <font size="1">nazwa która bêdzie wy¶wietalana<br>
                na stronach albumów</font></p>
            </td>
          </tr>
          <tr> 
            <td height="65" valign="top"> 
              <p><b>STRONA SKLEPU</b><br>
                <input name="website" type="text" size="20" maxlength="255"  value="<?php print("$_GET[artist]"); ?>">
                <font size="1"><br>
                strona www sklepu internetowego,<br>
                w postaci http://www.stronasklepu.pl</font></p>
            </td>
          </tr>
          <tr> 
            <td height="65" valign="top" align="left"> <b>WY¦WIETLAJ NA STRONIE</b><br>
              <input type="checkbox" name="enable" value="y" checked>
              <br>
              <font size="1">zaznacz, je¿eli linki do p³yt w tym<br>
              sklepie maj± byæ wy¶wietlane na stronie</font></td>
          </tr>
          <tr> 
            <td height="0" valign="top" align="left"> 
              <input type="submit" value="DODAJ  SKLEP" style="width: 120px">
            </td>
          </tr>
        </table>
      </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
