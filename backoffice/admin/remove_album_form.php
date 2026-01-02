<?php include ('template_top.php');
	include ('connect_to_database.php');?>
<form action="remove_album.php" method="get" name="add_album" target="_self" enctype="multipart/form-data">
  <font size="5"><b>USUWANIE ALBUMU!!!</b></font> 
  <table width="90%" border="0" cellspacing="0" cellpadding="0">
    <tr> 
      <td height="60" valign="top"> 
        <p>Wybierz album do usiniecia:<br>
          <br>
          <select name="id">
            <option> </option>
            <?php	
	$result = mysqli_query($sql, "SELECT id, title FROM albums ORDER BY title");
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
    <input type="submit" value="Usun album" name="submit">
  </p>
</form>
<?php include ('template_bottom.php'); ?>