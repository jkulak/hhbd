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
      <div align="left"><b><font size="3">DODAJ ZDJECIE DO POSTACI</font></b></div>
    </td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td align="left">
      <form action="add_artist_photo.php" method="post" name="add_album" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0" align="left">
          <tr>
            <td height="25" valign="top" colspan="2">
              <p> wykonawca: <br>
                <select name="id">
                  <option> </option>
                  <?php
  $result = mysqli_query($sql, 'SELECT id, name FROM artists ORDER BY name');
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }

    while ( $row = mysqli_fetch_array($result) ) {
      print ("<option value=\"" . $row["id"] . "\">" . $row["name"] . "</option>");
  	  }
  ?>
                </select>
                <br>
                &nbsp; </p>
            </td>
          </tr>
          <tr>
            <td height="25" valign="top" colspan="2">
              <input type="checkbox" name="main" checked="checked" value="1" style="width: 23px">
              ustaw jako główne zdjęcie<br>
            </td>
          </tr>
          <tr>
            <td height="25" valign="top" colspan="2">
              <input type="file" name="artist_photo" style="width: 320px">
              <br>
              &nbsp; </td>
          </tr>
          <tr>
            <td height="12" valign="top" colspan="2">opis zdjêcia:<br>
              <textarea name="description" rows="4" style="width: 320px"></textarea>
              <br>
              &nbsp; </td>
          </tr>
          <tr>
            <td height="13" valign="top" width="255">¼ród³o:<br>
              <input type="text" name="source">
              <br>
              &nbsp; </td>
            <td height="13" valign="top" width="285">e-mail ¼ród³a:<br>
              <input type="text" name="sourceurl">
            </td>
          </tr>
          <tr>
            <td height="45" valign="top" colspan="2">
              <input type="submit" value="DODAJ FOTE" name="submit" style="width: 120px">
            </td>
          </tr>
        </table>
      </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
