<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }
	
  include ('template_top.php');
  include ('connect_to_database.php');
?> 

<p>DODAJ OKLADKE</p>
<form name="form1" method="post" action="add_label_logo.php" enctype="multipart/form-data">
  <p>
    <input type="hidden" name="MAX_FILE_SIZE" value="100000">
    wybierz album:<br>
    <select name="id">
	
	 <option> </option>
                  <?php
  $result = mysqli_query($sql, 'SELECT id, name FROM labels ORDER BY name');
    if (!$result) {
      echo('<P>Error performing query: ' . mysqli_error($sql) . '</P>');
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
      print ('<option value="' . $row['id'] . '">' . $row['name'] . '</option>');
  	  }
  ?>
    
	</select>
  </p>
  <p> <br>
    wybierz logo:<br>
    <input type="file" name="label_logo">
  </p>
  <p>
    <input type="submit" value="Dodaj logo">
  </p>
  </form>
<p>&nbsp; </p>
<p>&nbsp;</p>
</body>
</html>
