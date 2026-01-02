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
      <div align="left"><b><font size="3">WYBIERZ WYTW&Oacute;RNIE DO EDYCJI</font></b></div>
    </td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td align="left"> 
      <form action="edit_label_form.php" method="get" name="add_album" target="_self" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0" align="left">
          <tr> 
            <td width="180" height="90" valign="top"> 
              <p>wytwornia: <br>
                <select name="id">
                  <option> </option>
                  <?php
  $result = mysqli_query($sql, "SELECT id, name FROM labels ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
      print ("<option value=\"" . $row["id"] . "\">" . $row['name'] . "</option>");
  	  }
  ?>
                </select>
              </p>
            </td>
            <td width="180" height="90" valign="top">&nbsp;</td>
            <td width="180" height="90" valign="top">&nbsp;</td>
          </tr>
          <tr> 
            <td width="180" height="0" valign="top" align="left"> 
              <input type="submit" value="WYBIERZ WYTWORNIE" style="width: 160px">
            </td>
            <td width="180" height="0" valign="top" align="left">&nbsp; </td>
            <td width="180" height="0" valign="middle">&nbsp;</td>
          </tr>
        </table>
      </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
