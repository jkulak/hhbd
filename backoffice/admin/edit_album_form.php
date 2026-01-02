<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }
	
  include ('template_top.php');
  include ('connect_to_database.php');
  
  
  $sql_query = 'SELECT title, labelid, year, media_mc, media_cd, media_lp, catalog_mc, catalog_lp, catalog_cd, epfor, singiel, description, premier ' . 
    'FROM albums WHERE id=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);
  $albumrow = mysqli_fetch_array($result);
  
   
  
  $sql_query = 'SELECT artistid FROM album_artist_lookup WHERE albumid=' . $_GET['id'];
  $result = mysqli_query($sql, $sql_query);  
  while( $temprow = mysqli_fetch_array($result) ){
  	$artists[] = $temprow['artistid'];
  } // while
    
  
?> 
<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td> 
      <div align="left"><b><font size="3">EDYTUJ ALBUM</font></b></div>
    </td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td align="left"> 
      <form action="edit_album.php" method="post" name="add_album" target="_self" enctype="multipart/form-data">
        <table width="724" border="0" cellspacing="0" cellpadding="0" align="left">
          <tr> 
            <td width="140" height="90" valign="top"> 
              <p>tytu&#322; albumu: <br>
                <input name="title" type="text" size="20" maxlength="255" value="<?php print($albumrow['title']); ?>" >
              </p>
            </td>
            <td width="140" height="90" valign="top">wytw�rnia:<br>
              <input name="label" type="text" size="20" maxlength="255">
              <br>
              <select name="labelid">
                <option> </option>
                <?php
  $result = mysqli_query($sql, "SELECT id, name FROM labels ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $labelrow = mysqli_fetch_array($result) ) {
      print ("<option value=\"" . $labelrow['id'] . "\"" . ($albumrow['labelid'] == $labelrow['id'] ? 'selected' : '') . ">" . $labelrow['name'] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="444" height="90" valign="top"> 
              <p>data wydania (YYYY-MM-DD):<br>
                <input type="text" name="year" maxlength="10" style="width: 75px" value="<?php print($albumrow['year']); ?>">
                <br>
                <font size="1">jezeli wpisujesz planowana premiere, to w dacie 
                wydania, wpisz date, ktora odpowiada mniej wiecej planowanej.</font> 
                <br>
                <b>planowana premiera:</b><br>
                <input type="text" name="premier" maxlength="255" style="width: 250px" value="<?php print($albumrow['premier']); ?>">
                <br>
                <font size="1">dowolny ciag znakow,<br>
                np.: 'poczatek wiosny 2005'</font></p>
              </td>
          </tr>
          <tr> 
            <td width="140" height="90" valign="top">wykonawca:<br>
              <input name="artist" type="text" size="20" maxlength="255">
              <br>
              <select name="artistid1">
                <option> </option>
                <?php	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $artistrow = mysqli_fetch_array($result) ) {
	  print ("<option value=\"" . $artistrow['id'] . "\"" . ($artists[0] == $artistrow['id'] ? 'selected' : '') . ">" . $artistrow['name'] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="140" height="90" valign="top">wykonawca:<br>
              <input name="artist2" type="text" style="textfieldstyle" maxlength="255"  value="<?php print("$_GET[artist2]"); ?>">
              <br>
              <select name="artistid2">
                <option> </option>
                <?php	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error() . "</P>");
	  exit();
  	  }
	    
    while ( $artistrow = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $artistrow['id'] . "\"" . ($artists[1] == $artistrow['id'] ? 'selected' : '') . ">" . $artistrow['name'] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="444" height="90" valign="top">wykonawca:<br>
              <input name="artist3" type="text" size="20" maxlength="255"  value="<?php print("$_GET[artist3]"); ?>">
              <br>
              <select name="artistid3">
                <option> </option>
                <?php	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error() . "</P>");
	  exit();
  	  }
	    
    while ( $artistrow = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $artistrow['id'] . "\"" . ($artists[2] == $artistrow['id'] ? 'selected' : '') . ">" . $artistrow['name'] . "</option>");
  	  }
  ?>
              </select>
            </td>
          </tr>
          <tr> 
            <td width="140" height="45" valign="top"> 
              <input type="checkbox"  style="width: 20px" name="cd" value="1" <?php print($albumrow['media_cd'] == '1' ? 'checked' : ''); ?>>
              cd cat#: 
              <input name="cdcatalog" type="text"  style="width: 70px"  maxlength="255" value="<?php print($albumrow['catalog_cd']); ?>" >
            </td>
            <td width="140" height="45" valign="top"> 
              <input type="checkbox"  style="width: 20px" name="lp" value="1" <?php print($albumrow['media_lp'] == '1' ? 'checked' : ''); ?>>
              winy cat#: 
              <input name="lpcatalog" type="text"  style="width: 70px"  maxlength="255" value="<?php print($albumrow['catalog_lp']); ?>" >
            </td>
            <td width="444" height="45" valign="top"> 
              <input type="checkbox"  style="width: 20px" name="mc" value="1" <?php print($albumrow['media_mc'] == '1' ? 'checked' : ''); ?>>
              kaset cat#: 
              <input name="mccatalog" type="text" style="width: 70px" maxlength="255" value="<?php print($albumrow['catalog_mc']); ?>" >
            </td>
          </tr>
          <tr> 
            <td width="140" height="45" valign="top"> 
              <input  style="width: 20px" type="checkbox" name="ep" value="1" <?php print(($albumrow['singiel'] == '1' ? 'checked' : '')); ?>>
              singiel</td>
            <td width="140" height="45" valign="top">singiel dla:<br>
              <select name="epforid">
                <option value="">longplay</option>
                <?php
	

	$result = mysqli_query($sql, "SELECT id, title FROM albums ORDER BY title");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error() . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row['id'] . "\"" . ($albumrow['epfor'] == $row['id'] ? 'selected' : '') . ">" . $row["title"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="444" height="45" valign="top">&nbsp;</td>
          </tr>
          <tr> 
            <td colspan="3" height="180" valign="top" align="left"> 
              <p><b>OPIS PRODUKCJI</b><br>
                <textarea name="description" rows="10" style="width: 680px"><?php print($albumrow['description']); ?></textarea>
                <br>
                <font size="1">opis produkcji, to oficjalna informacja na temat<br>
                albumu, skopiowana ze strony oficjalnej</font></p>
            </td>
          </tr>
          <tr> 
            <td width="140" height="0" valign="top" align="left"> 
              <input type="submit" value="ZAPISZ ZMIANY" style="width: 120px" name="submit">
            </td>
            <td width="140" height="0" valign="top" align="left"> 
              <input type="reset" value="RESET" name="reset">
            </td>
            <td width="444" height="0" valign="middle"> 
              <input type="hidden" name="id" value="<?php print ($_GET['id']); ?>">
            </td>
          </tr>
        </table>
      </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
