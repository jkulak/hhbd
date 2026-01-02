<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('template_top.php');
include ('connect_to_database.php');

$sql_query = 'SELECT title, length, bpm, acapella, radio, instrumental FROM songs WHERE id=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);
$songrow = mysqli_fetch_array($result);

$sql_query = 'SELECT albumid, track FROM album_lookup WHERE songid=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);  
while( $temprow = mysqli_fetch_array($result) ){
  $album[] = $temprow['albumid'];
  $track[] = $temprow['track'];
  } // while      
  
$sql_query = 'SELECT artistid FROM artist_lookup WHERE songid=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);  
while( $temprow = mysqli_fetch_array($result) ){
  $artists[] = $temprow['artistid'];
  } // while
  
$sql_query = 'SELECT artistid, feattype FROM feature_lookup WHERE songid=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);  
while( $temprow = mysqli_fetch_array($result) ){
  $features[] = $temprow['artistid'];
  $featuretypes[] = $temprow['feattype'];
  } // while    
  
$sql_query = 'SELECT artistid FROM music_lookup WHERE songid=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);  
while( $temprow = mysqli_fetch_array($result) ){
  $music[] = $temprow['artistid'];
  } // while    
  
$sql_query = 'SELECT artistid FROM scratch_lookup WHERE songid=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);  
while( $temprow = mysqli_fetch_array($result) ){
  $scratch[] = $temprow['artistid'];
  } // while      

$sql_query = 'SELECT artistid, name FROM remix_lookup WHERE songid=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);  
while( $temprow = mysqli_fetch_array($result) ){
  $remix[] = $temprow['artistid'];
  $remixname[] = $temprow['name'];
  } // while  
  
$sql_query = 'SELECT labelid FROM video_lookup WHERE songid=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);  
while( $temprow = mysqli_fetch_array($result) ){
  $video[] = $temprow['labelid'];
  } // while    

  

?> 
<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td><font size="3"><b>EDYTUJ PIOSENKE: </b></font></td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td valign="top" align="left"> 
      <form action="edit_song.php" method="get" name="add_song" target="_self" enctype="multipart/form-data">
        <table width="720" border="0" cellspacing="0" cellpadding="0">
          <tr valign="top" align="left"> 
            <td width="180" height="90"> 
              <p>tytu&#322; piosenki: <br>
                <input name="title" type="text" size="20" maxlength="255" value="<?php print($songrow['title']); ?>" >
              </p>
            </td>
            <td height="90" width="75"> 
              <p>czas trwania:<br>
                <input name="length" type="text" style="width: 50px" maxlength="255" value="<?php print($songrow['length']); ?>" >
              </p>
              
            </td>
            <td height="90" width="105">numer na plycie:<br>
              <input name="track" type="text"  style="width: 50px" value="<?php print($track[0]); ?>">
            </td>
            <td height="90" width="180">Pr&ecirc;dko&#156;&aelig;(BPM)::<br>
              <input type="text" name="bpm" size="11" maxlength="10" value="<?php print($songrow['bpm']); ?>">
            </td>
            <td height="90" width="180">album:<br>
              <select name="albumid">
                <?php
	

	$result = mysqli_query($sql, "SELECT id, title FROM albums ORDER BY title");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row['id'] . "\"" . ($album[0] == $row['id'] ? 'selected' : '') . ">" . $row["title"] . "</option>");
  	  }
  ?>
              </select>
            </td>
          </tr>
          <tr valign="top" align="left"> 
            <td width="180" height="90">wykonawca 1 (rozni: V/A):<br>
              <input name="artist1" type="text" size="12" maxlength="255">
              <br>
              <select name="artist_list1">
                <option> </option>
                <?php
	

	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($artists[0] == $row['id'] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td height="90" width="180" colspan="2"> 
              <p>wykonawca 2 (rozni: V/A):<br>
                <input name="artist2" type="text" size="12" maxlength="255">
                <br>
                <select name="artist_list2">
                  <option> </option>
                  <?php
	

	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($artists[1] == $row['id'] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
                </select>
              </p>
            </td>
            <td height="90" width="180">wykonawca 3 (rozni: V/A):<br>
              <input name="artist3" type="text" size="12" maxlength="255">
              <br>
              <select name="artist_list3">
                <option> </option>
                <?php
	

	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($artists[2] == $row['id'] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td height="90" width="180">wykonawca 4(rozni: V/A):<br>
              <input name="artist4" type="text" size="12" maxlength="255">
              <br>
              <select name="artist_list4">
                <option> </option>
                <?php
	

	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($artists[3] == $row['id'] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
          </tr>
          <tr valign="top" align="left"> 
            <td width="180" height="65">featuring 1:<br>
              <input name="feat1" type="text" size="12" maxlength="255">
              <br>
              <select name="feat1id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row['id'] . "\"" . ($features[0] == $row['id'] ? 'selected' : ''). ">" . $row['name'] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="180" height="65" colspan="2">featuring 2:<br>
              <input name="feat2" type="text" size="12" maxlength="255">
              <br>
              <select name="feat2id">
                <option> </option>
                <?php
	

	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($features[1] == $row['id'] ? 'selected' : ''). ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td height="65" width="180">featuring 3:<br>
              <input name="feat3" type="text" size="12" maxlength="255">
              <br>
              <select name="feat3id">
                <option> </option>
                <?php
	

	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($features[2] == $row['id'] ? 'selected' : ''). ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="180" height="65">featuring 4:<br>
              <input name="feat4" type="text" size="12" maxlength="255">
              <br>
              <select name="feat4id">
                <option> </option>
                <?php
	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\""  . ($features[3] == $row['id'] ? 'selected' : ''). ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
          </tr>
          <tr valign="top" align="left"> 
            <td width="180" height="90">rodzaj featuringu1:<br>
              <input name="feattype1" type="text" size="12" maxlength="255">
              <br>
              <select name="feattype1id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, feattype FROM feattypes ORDER BY feattype");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($featuretypes[0] == $row['id'] ? 'selected' : ''). ">" . $row["feattype"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="180" height="90" colspan="2">rodzaj featuringu2:<br>
              <input name="feattype2" type="text" size="12" maxlength="255">
              <br>
              <select name="feattype2id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, feattype FROM feattypes ORDER BY feattype");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($featuretypes[1] == $row['id'] ? 'selected' : ''). ">" . $row["feattype"] . "</option>");
  	  }
  ?>
              </select>
              <br>
            </td>
            <td height="90" width="180">rodzaj featuringu3:<br>
              <input name="feattype3" type="text" size="12" maxlength="255">
              <br>
              <select name="feattype3id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, feattype FROM feattypes ORDER BY feattype");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($featuretypes[2] == $row['id'] ? 'selected' : ''). ">" . $row["feattype"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="180" height="90">rodzaj featuringu4:<br>
              <input name="feattype4" type="text" size="12" maxlength="255">
              <br>
              <select name="feattype4id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, feattype FROM feattypes ORDER BY feattype");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($featuretypes[3] == $row['id'] ? 'selected' : ''). ">" . $row["feattype"] . "</option>");
  	  }
  ?>
              </select>
            </td>
          </tr>
          <tr valign="top" align="left"> 
            <td width="180" height="90"> muzyka 1:<br>
              <input name="music1" type="text" size="12" maxlength="255">
              <br>
              <select name="music1id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row['id'] . "\"" . ($music[0] == $row['id'] ? 'selected' : ''). ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="180" height="90" colspan="2">muzyka 2:<br>
              <input name="music2" type="text" size="12" maxlength="255">
              <br>
              <select name="music2id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($music[1] == $row['id'] ? 'selected' : ''). ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td height="90" width="180">skrecze 1:<br>
              <input name="scratch1" type="text" size="12" maxlength="255">
              <br>
              <select name="scratch1id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($scratch[0] == $row['id'] ? 'selected' : ''). ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td width="180" height="90">skrecze 2:<br>
              <input name="scratch2" type="text" size="12" maxlength="255">
              <br>
              <select name="scratch2id">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($scratch[1] == $row['id'] ? 'selected' : ''). ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
          </tr>
          <tr valign="top" align="left"> 
            <td width="180" height="90">remix by:<br>
              <input name="remix" type="text" size="12" maxlength="255">
              <br>
              <select name="remixid">
                <option> </option>
                <?php
	
	
	$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($remix[0] == $row['id'] ? 'selected' : ''). ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
            </td>
            <td height="90" width="180" colspan="2">&nbsp;</td>
            <td height="90" width="180">&nbsp;</td>
            <td height="90" width="180"> 
              <input type="checkbox" style="width: 20px" name="acapella" value="1" <?php print($songrow['acapella'] == '1' ? 'checked' : ''); ?>>
              acapella<br>
              <input type="checkbox" style="width: 20px" name="instrumental" value="1" <?php print($songrow['instrumental'] == '1' ? 'checked' : ''); ?>>
              instrumental <br>
              <input type="checkbox" style="width: 20px" name="radio" value="1" <?php print($songrow['radio'] == '1' ? 'checked' : ''); ?>>
              radio ver (cenz)</td>
          </tr>
          <tr valign="top" align="left"> 
            <td width="180"> 
              <p> 
                <input type="submit"  style="width: 120px" value="ZAPISZ ZMIANY">
              </p>
            </td>
            <td width="180" colspan="2">
              <input type="reset"  style="width: 90px" value="RESET">
            </td>
            <td width="180">
              <input type="hidden" name="id" value="<?php print($_GET['id']); ?>">
              <input type="hidden" name="prevalbumid" value="<?php print($album[0]) ?>">
            </td>
            <td width="180">&nbsp;</td>
          </tr>
        </table>
        </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
