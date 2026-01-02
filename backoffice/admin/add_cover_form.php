<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
	exit();
    }
	
  include ('template_top.php');
  include ('connect_to_database.php');
?> 

<h1>Dodaj okładkę</h1>
<form name="form1" method="post" action="add_cover.php" enctype="multipart/form-data">
  <p>
    <input type="hidden" name="MAX_FILE_SIZE" value="100000">
    wybierz album:<br>
    <select name="albumid">
	
	 <option> </option>
                  <?php
  $result = mysqli_query($sql, 'SELECT id, title FROM albums ORDER BY title');
    if (!$result) {
      echo('<P>Error performing query: ' . mysqli_error($sql) . '</P>');
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
      print ('<option value="' . $row['id'] . '">' . $row['title'] . '</option>');
  	  }
  ?>
    
	</select>
  </p>
  <p> <br>
    wybierz okladke:<br>
    <input type="file" name="coverfile">
  </p>
  <p>
    <input type="submit" value="Dodaj okladke">
  </p>
  </form>
<p>&nbsp; </p>
<p>&nbsp;</p>
</body>
</html>
