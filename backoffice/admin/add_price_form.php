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
      <div align="left"><b><font size="3">DODAJ CENE ALBUMU</font></b></div>
    </td>
  </tr>
  <tr>
    <td height="15">&nbsp;
	<?php if ($_GET['msg'] == '1') print ('<font color="red"><strong>INFO O ALBUMIE DODANE POPRAWNIE!</strong></font>');?>
	</td>
  </tr>
  <tr>
    <td align="left"> 
      <form action="add_price.php" method="post" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0" align="left">
          <tr> 
            <td height="65" valign="top"> 
              <p><b>ALBUM</b><br>
                
				 <select name="albumid">
                <?php
	

	$result = mysqli_query($sql, "SELECT id, title FROM albums ORDER BY title");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($_GET['albumid'] == $row["id"] ? 'selected' : '') . ">" . $row["title"] . "</option>");
  	  }
  ?>
              </select>
                <br>
                <font size="1">album - po prostu : )</font></p>
            </td>
          </tr>
          <tr> 
            <td height="65" valign="top"> 
              <p><b>LINK DO P�YTY W TYM SKLEPIE</b><br>
                <input name="link" type="text" style="width: 380px" maxlength="255"  value="<?php print("$_GET[artist]"); ?>">
                <font size="1"><br>
                link do albumu w wybranym sklepie</font></p>
            </td>
          </tr>
          <tr> 
            <td height="65" valign="top" align="left"> <b>SKLEP INTERNETOWY</b><br>
              <select name="shopid">

<?php
$result = mysqli_query($sql, "SELECT id,name FROM eshops ORDER BY name");
if (!$result) {
	echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	exit();
 	}
while ( $row = mysqli_fetch_array($result) ) {
 print ('<option value="' . $row['id'] . '"' . ($_GET['shopid'] == $row['id'] ? 'selected' : '') . '>' . $row['name'] . '</option>');
 }
 ?>

              </select>
              <br>
              <font size="1">wybierz sklep z kt�rego dodajesz link</font></td>
          </tr>
          <tr> 
            <td height="0" valign="top" align="left"> 
              <input type="submit" value="DODAJ CENE" name="submit" style="width: 120px">
            </td>
          </tr>
        </table>
      </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
