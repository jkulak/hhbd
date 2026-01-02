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
    <td><b><font size="3">DODAJ WYTW&Oacute;RNIE VIDEO</font></b></td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td valign="top" align="left"> 
      <form action="add_video_label.php" method="get" name="add_video_label" target="_self" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0">
          <tr align="left"> 
            <td width="180" height="90" valign="top"> 
              <p>nazwa wytw&oacute;rni:<br>
                <input name="name" type="text" size="20" maxlength="255" value="<?php print("$_GET[name]"); ?>" >
              </p>
            </td>
            <td height="90" valign="top" width="180"> 
              <p>strona internetowa wytw&oacute;rni: <br>
                <input name="website" type="text" size="20" maxlength="255" value="<?php print("$_GET[website]"); ?>" >
              </p>
            </td>
            <td height="90" valign="top" width="180">email wytw&oacute;rni: <br>
              <input name="email" type="text" size="20" maxlength="255" value="<?php print("$_GET[email]"); ?>" >
            </td>
          </tr>
          <tr align="left"> 
            <td width="180" height="90" valign="top">adres wytw&oacute;rni: <br>
              <input name="addres" type="text" size="20" maxlength="255" value="<?php print("$_GET[addres]"); ?>" >
            </td>
            <td height="90" valign="top" width="180">miasto pochodzenia:<br>
              <input name="city" type="text" size="20" maxlength="255"  value="<?php print("$_GET[city]"); ?>">
              <br>
              <select name="cityid">
                <option> </option>
                <?php	
	$result = mysqli_query($sql, "SELECT id, name FROM cities ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($_GET['cityid'] == $row["id"] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td height="90" valign="top" width="180">&nbsp;</td>
          </tr>
          <tr align="left"> 
            <td width="180" valign="top"> 
              <input type="submit" value="Dodaj wytw&oacute;rni&ecirc; video" name="submit">
            </td>
            <td valign="top" width="180">&nbsp;</td>
            <td valign="top" width="180">&nbsp;</td>
          </tr>
        </table>
        </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
