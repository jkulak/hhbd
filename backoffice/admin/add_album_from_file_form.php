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
    <td><font size="3"><b>DODAJ ALBUM Z PLIKU</b></font></td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td valign="top"> 
      <form action="add_album_from_file.php" method="get" name="add_album" target="_self" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0">
          <tr align="left"> 
            <td width="240" height="90" valign="top"> 
              <p>Wklej to co miałeś kiedyś w pliku<br>
                <!-- <input type="file" name="myfile" style="width: 200px"> -->
                <textarea name="album-data" rows="8" cols="40"></textarea>
              </p>
            </td>
            <td height="90" valign="top" width="131"> 
              <p>album:<br>
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
              </p>
            </td>
          </tr>
          <tr align="left"> 
            <td width="240" valign="top"> 
              <input type="submit" value="Dodaj album z pliku">
            </td>
            <td valign="top" width="131"></td>
          </tr>
        </table>
        </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
