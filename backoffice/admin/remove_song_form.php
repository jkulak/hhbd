<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }  

include ('template_top.php');
include ('connect_to_database.php');

?>
<form action="remove_song.php" method="get" name="add_album" target="_self" enctype="multipart/form-data">
  <font size="5"><b>USUWANIE PIOSENKI!!!</b></font> 
  <table width="90%" border="0" cellspacing="0" cellpadding="0">
    <tr> 
      <td height="60" valign="top"> 
        <p>Wybierz piosenke do usiniecia:<br>
          <br>
          <select name="id">
            <option> </option>
            <?php	
	$result = mysqli_query($sql, 'SELECT id, title FROM songs ORDER BY title');
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($_GET['nameid'] == $row["id"] ? 'selected' : '') . ">" . $row["title"] . "</option>");
  	  }
  ?>
          </select>
        </p>
        
      </td>
    </tr>
  </table>
  <p>
    <input type="submit" value="Usun piosenke">
  </p>
</form>
<?php include ('template_bottom.php'); ?>