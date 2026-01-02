<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }

include ('template_top.php');
include ('connect_to_database.php');

$sql_query = 'SELECT name, realname, since, till, type, profile, website, concertinfo FROM artists WHERE id=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);  
$artistrow = mysqli_fetch_array($result);


$sql_query = 'SELECT cityid FROM artist_city_lookup WHERE artistid=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);
$cityrow = mysqli_fetch_array($result);

$sql_query = 'SELECT altname FROM altnames_lookup WHERE artistid=' . $_GET['id'];
$result = mysqli_query($sql, $sql_query);
while( $temprow = mysqli_fetch_array($result) ){
  $altname[] = $temprow['altname'];
  } // while


?> 

<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td><font size="3"><b>EDYTUJ WYKONAWCE</b></font></td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td valign="top"> 
      <form action="edit_artist.php" method="POST" name="add_album" target="_self" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0">
          <tr align="left"> 
            <td width="180" height="90" valign="top"> 
              <p>nazwa wykonawcy:<br>
                <input name="name" type="text" size="20" maxlength="255" value="<?php print($artistrow['name']); ?>" >
              </p>
            </td>
            <td height="90" valign="top" width="180"> 
              <p>strona internetowa: <br>
                <input name="website" type="text" size="20" maxlength="255" value="<?php print($artistrow['website']); ?>" >
              </p>
            </td>
            <td height="90" valign="top" width="180">prawdziwe imie i nazwisko:<br>
              <input name="realname" type="text" size="20" maxlength="255" value="<?php print($artistrow['realname']); ?>" >
            </td>
          </tr>
          <tr> 
            <td width="180" height="90" valign="top">miasto pochodzenia:<br>
              <input name="city" type="text" size="20" maxlength="255">
              <br>
              <select name="cityid">
                <option> </option>
                <?php	
	$result = mysqli_query($sql, "SELECT id, name FROM cities ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error() . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row['id'] . "\"" . ($cityrow['cityid'] == $row['id'] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
              <?php print('mf: ' . $cityrow['cityid']) ?>
            </td>
            <td height="90" valign="top" width="180">urodziny/powstalo: 
              <input name="date" type="text" style="width: 60px" maxlength="255" value="<?php print($artistrow['since']); ?>" >
              <br>
              smierc/zakonczenie: 
              <input name="till" type="text" style="width: 60px" maxlength="255" value="<?php print($artistrow['till']); ?>" >
            </td>
            <td height="90" valign="top" width="180"> typ:<br>
              <select name="nametype">
                <option value="x" ></option>
                <option value="f" <?php print ($artistrow['type']=='f' ? 'selected' : ''); ?>>kobieta</option>
                <option value="m" <?php print ($artistrow['type']=='m' ? 'selected' : ''); ?>>mezczyzna</option>
                <option value="b" <?php print ($artistrow['type']=='b' ? 'selected' : ''); ?>>zespol</option>
              </select>
            </td>
          </tr>
          <tr align="left"> 
            <td width="180" valign="top" height="90">nick1<br>
              <input name="altname1" type="text" size="20" maxlength="255" value="<?php print($altname[0]); ?>" >
            </td>
            <td valign="top" width="180" height="90">nick2:<br>
              <input name="altname2" type="text" size="20" maxlength="255" value="<?php print($altname[1]); ?>" >
            </td>
            <td valign="top" width="180" height="90">nick3:<br>
              <input name="altname3" type="text" size="20" maxlength="255" value="<?php print($altname[2]); ?>" >
            </td>
          </tr>
          <tr> 
            <td colspan="3" height="90" valign="top" align="left"> 
              <p><b>PROFIL</b><br>
                <textarea name="profil" rows="10" style="width: 680px"><?php print($artistrow['profile']); ?></textarea>
                <br>
                <font size="1">profil wykonawcy</font></p>
            </td>
          </tr>
          <tr> 
            <td colspan="3" height="150" valign="top" align="left"><b>INFO KONCERTOWE<br>
              <textarea name="concertinfo" rows="4" style="width: 280px"><?php print($artistrow['concertinfo']); ?></textarea>
              <br>
              </b><font size="1">kontakt koncertowy, numer telefonu, email, dowolny 
              ciag znakow,<br>
              np.: Marcin Kozacki: 509123456, marcink@wykonawca.pl</font></td>
          </tr>
          <tr align="left"> 
            <td width="180" valign="top"> 
              <input type="hidden" name="id" value="<?php print($_GET['id']); ?>">
              <input type="submit" value="Dodaj wykonawce" name="submit">
            </td>
            <td valign="top" width="180">&nbsp; </td>
            <td valign="top" width="180">&nbsp;</td>
          </tr>
        </table>
        </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
