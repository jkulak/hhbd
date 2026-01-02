<?php

session_start();
if (!isset($_SESSION['username'])) {
	header("Location: index.php");
	exit();
}

include 'template_top.php';
include 'connect_to_database.php';
?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td>
      <div align="left"><b><font size="3">DODAJ ALBUM</font></b></div>
    </td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td align="left">
      <form action="add_album.php" method="POST" name="add_album" enctype="multipart/form-data">
        <table width="660" border="0" cellspacing="0" cellpadding="0" align="left">
          <tr>
            <td width="180" height="90" valign="top">
              <p>tytu&#322; albumu: <br>
                <input name="title" type="text" size="20" maxlength="255" value="<?php print("$_GET[title]");?>" >
              </p>
            </td>
            <td width="180" height="90" valign="top">wytw�rnia:<br>
              <select name="labelid">
                <option> </option>
                <?php
$result = mysqli_query($sql, "SELECT id, name FROM labels ORDER BY name");
if (!$result) {
	echo ("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	exit();
}

while ($row = mysqli_fetch_array($result)) {
	print("<option value=\"" . $row["id"] . "\"" . ($_GET['labelid'] == $row["id"] ? 'selected' : '') . ">" . $row["name"] . "</option>");
}
?>
              </select>
            </td>
            <td width="300" height="90" valign="top">data wydania (YYYY-MM-DD):<br>
              <input type="text" name="date" maxlength="10" style="width: 75px" value="<?php print("$_GET[date]");?>">
              <br>
              <font size="1">jezeli wpisujesz planowana premiere, to w dacie wydania,
              wpisz date, ktora odpowiada mniej wiecej planowanej.</font> <br>
              <b>planowana premiera:</b><br>
              <input type="text" name="premier" style="width: 250px">
              <br>
              <font size="1">dowolny ciag znakow,<br>
              np.: 'poczatek wiosny 2005'</font><br>
            </td>
          </tr>
          <tr>
            <td width="180" height="90" valign="top">wykonawca:<br>
              <select name="artistid">
                <option> </option>
                <?php
$result = mysqli_query($sql, "SELECT id, name FROM artists ORDER BY name");
if (!$result) {
	echo ("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	exit();
}

while ($row = mysqli_fetch_array($result)) {

	print("<option value=\"" . $row["id"] . "\"" . ($_GET['artistid'] == $row["id"] ? 'selected' : '') . ">" . $row["name"] . "</option>");
}
?>
              </select>
            </td>
            <td width="180" height="90" valign="top">
            </td>
            <td width="300" height="90" valign="top">
            </td>
          </tr>
          <tr>
            <td width="180" height="45" valign="top">
              <input type="checkbox"  style="width: 20px" name="cd" value="1" <?php print($_GET['cd'] == '1' ? 'checked' : '');?>>
              cd cat#:
              <input name="cdcatalog" type="text"  style="width: 70px"  maxlength="255" value="<?php print($_GET['cdcatalog']);?>" >
            </td>
            <td width="180" height="45" valign="top">
              <input type="checkbox"  style="width: 20px" name="lp" value="1" <?php print($_GET['lp'] == '1' ? 'checked' : '');?>>
              winy cat#:
              <input name="lpcatalog" type="text"  style="width: 70px"  maxlength="255" value="<?php print($_GET['lpcatalog']);?>" >
            </td>
            <td width="300" height="45" valign="top">
              <input type="checkbox"  style="width: 20px" name="mc" value="1" <?php print($_GET['mc'] == '1' ? 'checked' : '');?>>
              kaset cat#:
              <input name="mccatalog" type="text"  style="width: 70px"  maxlength="255" value="<?php print($_GET['mccatalog']);?>" >
            </td>
          </tr>
          <tr>
            <td width="180" height="45" valign="top">
              <input  style="width: 20px" type="checkbox" name="ep" value="1" <?php print(($_GET['ep'] == '1' ? 'checked' : ''));?>>
              singiel</td>
            <td colspan="2" height="45" valign="top">singiel dla:<br>
              <select name="epforid">
                <option value="">longplay</option>
                <?php

$result = mysqli_query($sql, "SELECT id, title FROM albums ORDER BY title");
if (!$result) {
	echo ("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	exit();
}

while ($row = mysqli_fetch_array($result)) {

	print("<option value=\"" . $row['id'] . "\"" . ($_GET['epforid'] == $row['id'] ? 'selected' : '') . ">" . $row["title"] . "</option>");
}
?>
              </select>
            </td>
          </tr>
          <tr>
            <td colspan="3" height="180" valign="top" align="left">
              <p><b>OPIS PRODUKCJI</b><br>
                <textarea name="description" rows="10" style="width: 280px"><?php print($_GET['description']);?></textarea>
                <br>
                <font size="1">opis produkcji, to oficjalna informacja na temat<br>
                albumu, skopiowana ze strony oficjalnej</font></p>
              </td>
          </tr>
          <tr>
            <td colspan="3" height="0" valign="top" align="left">
              <input type="submit" value="DODAJ ALBUM" name="submit" style="width: 120px">
            </td>
          </tr>
        </table>
      </form>
    </td>
  </tr>
</table>
<?php include 'template_bottom.php';?>
