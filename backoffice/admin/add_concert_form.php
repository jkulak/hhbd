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
    <td><font size="3"><b>DODAJ KONCERT</b></font></td>
  </tr>
  <tr>
    <td height="30"><b><font size="2">* - POLA OBOWIAZKOWE</font></b></td>
  </tr>
  <tr>
    <td valign="top"> 
      <form action="add_concert.php" method="POST" name="add_news" target="_self" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0">
          <tr align="left"> 
            <td height="65" valign="top"> 
              <p><b>TYTUL</b> *<br>
                <input name="title" type="text" size="20" maxlength="255" style="width: 200px">
                <br>
                <font size="1">tytul koncertu, naglowek opisujacy koncert, gora 
                jedno zdanie<br>
                pierwsza litera wielka, pozostale male (chyba ze nazwa wlasna)</font></p>
            </td>
          </tr>
          <tr align="left"> 
            <td height="65" valign="top"> 
              <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr> 
                  <td height="70"><b>WYKONAWCY</b> *<br>
                    <input name="artists" type="text" size="20" maxlength="255" style="width: 200px">
                    <br>
                    <font size="1">id wykonawcow, oddzielone przeciankami (bez 
                    zadnych spacji i innych znakow)<br>
                    np: 24,12,477,432</font></td>
                </tr>
              </table>
            </td>
          </tr>
          <tr align="left"> 
            <td height="65" valign="top"> <b>MIASTO</b> *<br>
              <select name="cityid">
                <option> </option>
                <?php	
	$result = mysqli_query($sql, "SELECT id, name FROM cities ORDER BY name");
    if (!$result) {
      echo("<P>Error performing query: " . mysqli_error($sql) . "</P>");
	  exit();
  	  }
	    
    while ( $row = mysqli_fetch_array($result) ) {
 
	  print ("<option value=\"" . $row["id"] . "\"" . ($_GET['cityid'] == $row["id"] ? 'selected' : '') . ">" . $row["name"] . "</option>");
  	  }
  ?>
              </select>
              <input name="newcity" type="text" size="20" maxlength="255" style="width: 200px">
              <br>
              <font size="1">wybierz z listy, <b>ALBO</b> wpisz nowe</font></td>
          </tr>
          <tr align="left"> 
            <td height="140" valign="top"> <b>OPIS</b><br>
              <textarea name="description" rows="6" style="width: 300px"></textarea>
              <br>
              <font size="1">kilka zdan opisujacych koncert</font></td>
          </tr>
          <tr align="left"> 
            <td height="65" valign="top"><b>DATA</b> *<br>
              <input type="text" name="date">
              <br>
              <font size="1">data koncertu w formacie rrrr-mm-dd</font></td>
          </tr>
          <tr align="left"> 
            <td height="65" valign="top"><b>START</b><br>
              <input type="text" name="start">
              <br>
              <font size="1">godzina rozpoczecia, dowolny ciag znakow<br>
              np: 'pokazy tanca od 20:00, rozpoczecia koncertu 22:30'</font></td>
          </tr>
          <tr align="left"> 
            <td height="65" valign="top"><b>CENA</b><br>
              <input type="text" name="cost">
              <br>
              <font size="1">cena biletow na koncert, dowolny ciag znakow<br>
              np.: 'przedsprzedaz: 15pln, w dniu koncertu 20pln'</font></td>
          </tr>
          <tr align="left"> 
            <td height="65" valign="top"> <b>MIEJSCE</b><br>
              <input type="text" name="place">
              <br>
              <font size="1">nazwa klubu, w ktorym odbywa sie koncert, dowolny 
              ciag znakow</font><BR>
              <font size="1">np.: 'Klub studencki Herkulesy'</font></td>
          </tr>
          <tr align="left"> 
            <td height="65" valign="top"><b>WEBSITE</b><br>
              <input type="text" name="website">
              <br>
              <font size="1">strona internetowa dotyczaca koncertu w formacie: 
              http://tutaj_adres_strony<br>
              np.: 'http://www.slizgery2005.pl'</font></td>
          </tr>
          <tr align="left"> 
            <td height="100" valign="top"> 
              <p><b>POSTER</b><br>
                <select name="poster" style="width: 280px" >
                  <option value=""></option>
                  <?php
$path = dirname( $_SERVER['PATH_TRANSLATED'] );
$path = substr($path, 0, -5);
$mydir = $path . 'imgs/database/posters';
$dir = opendir($mydir);	  
while ($file = readdir($dir)) {
	if (($file != '.') AND ($file != '..' ))
		print ('<option value="' . $file . '">' . $file . '</option>' . "\n");
   	}
closedir($dir); 
?>
                </select>
                <input type="file" style="width: 240px" name="newposter">
                <br>
                <font size="1">wybierz plik plakatu z dysku, <b>ALBO</b> wybierz 
                jeden z dostepnych juz<br>
                na serwerze (np. dla trasy koncertowej). maksymalna szerokosc 
                plakatu 250px!!!<br>
                wysokosc bez ograniczen, format JPG, skompresowane za pomoca ACDSee, 
                jakosc 50</font></p>
            </td>
          </tr>
          <tr align="left"> 
            <td valign="top"><b></b></td>
          </tr>
          <tr align="left">
            <td valign="top">
              <input type="submit" value="Dodaj koncert!" name="submit">
            </td>
          </tr>
        </table>
        </form>
    </td>
  </tr>
</table>
<?php include ('template_bottom.php'); ?>
