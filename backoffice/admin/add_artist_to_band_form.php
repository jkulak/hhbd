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
    <td><font size="3"><b>POWIAZ WYKONAWCOW</b></font></td>
  </tr>
  <tr> 
    <td height="15">&nbsp;</td>
  </tr>
  <tr> 
    <td valign="top">
<form action="add_artist_to_band.php" method="get" name="add_song" target="_self" enctype="multipart/form-data">
        <table width="360" border="0" cellspacing="0" cellpadding="0">
          <tr valign="top" align="left"> 
            <td width="180" height="90">wykonawca 1 (rozni: V/A):<br>
              <input name="artist1" type="text" size="12" maxlength="255"  value="<?php print("$_GET[artist1]"); ?>">
              <br>
              <select name="artist1id">
                <option> </option>
                <?php
	

	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($_GET['artist1id'] == $row["id"] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
              <br>
              od 
              <input name="since1" type="text" style="width: 70px" maxlength="255"  value="<?php print("$_GET[artist1]"); ?>">
              do 
              <input name="till1" type="text" style="width: 70px" maxlength="255"  value="<?php print("$_GET[artist1]"); ?>">
            </td>
            <td height="90" width="180"> 
              <p>wykonawca 2 (rozni: V/A):<br>
                <input name="artist2" type="text" size="12" maxlength="255"  value="<?php print("$_GET[artist2]"); ?>">
                <br>
                <select name="artist2id">
                  <option> </option>
                  <?php
	

	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($_GET['artist2id'] == $row["id"] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
                </select>
                <br>
                od 
                <input name="since2" type="text" style="width: 70px" maxlength="255"  value="<?php print("$_GET[artist1]"); ?>">
                do 
                <input name="tille2" type="text" style="width: 70px" maxlength="255"  value="<?php print("$_GET[till2]"); ?>">
              </p>
            </td>
          </tr>
          <tr valign="top" align="left"> 
            <td width="180" height="90">wykonawca 3 (rozni: V/A):<br>
              <input name="artist3" type="text" size="12" maxlength="255"  value="<?php print("$_GET[artist3]"); ?>">
              <br>
              <select name="artist3id">
                <option> </option>
                <?php
	

	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($_GET['artist3id'] == $row["id"] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
              <br>
              od 
              <input name="since3" type="text" style="width: 70px" maxlength="255"  value="<?php print("$_GET[since3]"); ?>">
              do 
              <input name="till3" type="text" style="width: 70px" maxlength="255"  value="<?php print("$_GET[till3]"); ?>">
            </td>
            <td height="90" width="180">wykonawca 4(rozni: V/A):<br>
              <input name="artist4" type="text" size="12" maxlength="255"  value="<?php print("$_GET[artist4]"); ?>">
              <br>
              <select name="artist4id">
                <option> </option>
                <?php
	

	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($_GET['artist4id'] == $row["id"] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
              <br>
              od 
              <input name="since4" type="text" style="width: 70px" maxlength="255"  value="<?php print("$_GET[since4]"); ?>">
              do 
              <input name="till4" type="text" style="width: 70px" maxlength="255"  value="<?php print("$_GET[till4]"); ?>">
            </td>
          </tr>
          <tr valign="top" align="left"> 
            <td width="180" height="90">tworz&sup1; jako:<br>
              <input name="band1" type="text" size="12" maxlength="255"  value="<?php print("$_GET[band1]"); ?>">
              <br>
              <select name="band1id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($_GET['band1id'] == $row["id"] ? 'selected' : ''). ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td height="90" width="180">&nbsp;</td>
          </tr>
        </table>
        <p> 
          <input type="submit" value="Dodaj powiazanie" name="submit">
          <input type="reset" name="Reset" value="reset">
        </p>
      </form>
    </td>
  </tr>
</table>
<p>
  <?php include ('template_bottom.php'); ?>
</p>
