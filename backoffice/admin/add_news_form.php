<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include 'template_top.php';
include 'connect_to_database.php';?>

<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td><font size="3"><b>DODAJ NEWSA</b></font></td>
  </tr>
  <tr>
    <td height="30"><b><font size="2">* - POLA OBOWIAZKOWE</font></b></td>
  </tr>
  <tr>
    <td valign="top">
      <form action="add_news.php" method="post" name="add_news" target="_self" enctype="multipart/form-data">
        <table width="540" border="0" cellspacing="0" cellpadding="0">
          <tr align="left">
            <td height="55" valign="top">
              <p><b>NAGLOWEK</b> *<br>
                <input name="title" type="text" size="20" maxlength="255" style="width: 200px">
                <br>
                <font size="1">krotki, (kilka wyrazow) naglowek newsa, zachecajacy
                do czytania</font><br>
              </p>
            </td>
          </tr>
          <tr align="left">
            <td height="170" valign="top"> <b>TRESC</b> *<br>
              <textarea name="news" rows="8" style="width: 400px"></textarea>
              <br>
              <font size="1">cala tresc newsa</font></td>
          </tr>
          <tr align="left">
            <td height="80" valign="top">
              <p><b>DATA WAZNOSCI</b><br>
                <input type="text" name="expires">
                <font size="1"><br>
                data waznosci newsa, data, po ktorej news przestanie byc wyswietlany,
                format rrrr-mm-dd,<br>
                na razie nie bedzie newsow, ktorym trzeba to wpisywac, takze zostawiamy
                puste</font></p>
            </td>
          </tr>
          <tr align="left">
            <td height="80" valign="top">
              <p><b>DATA DODANIA</b><br>
                <input type="text" name="date">
                <br>
                <font size="1">wypelnij tylko, jezeli dodajesz zaleglego newsa,
                sprzed wiecej niz tygodnia<br>
                format rrrr-mm-dd gg:mm:ss</font></p>
              </td>
          </tr>
          <tr align="left">
            <td height="90" valign="top">
              <p><b>GRAFIKA</b><br>
                <input type="file" name="graph" style="width: 340px">
                <br>
                <font size="1">zdjecie, lub obrazek, ktory wyswietli sie obok
                newsa,<br>
                podczas przegladania, zostanie stworzona miniaturka,<br>
                a po kliknieciu w nia, pojawi sie w nowym oknie normalny obrazek<br>
                max wymiary 600px x 800px, skompresowane ACDSee. JPG@50</font></p>
            </td>
          </tr>
          <tr align="left">
            <td height="80" valign="top"><b>GLYPH</b> *<br>
              <select name="glyph">
                <option value=""></option>
                <?php
$path = dirname($_SERVER['PATH_TRANSLATED']);
$path = substr($path, 0, -5);
$mydir = $path . 'imgs/database/news-glyphs';
$dir = opendir($mydir);
while ($file = readdir($dir)) {
    if (($file != '.') AND ($file != '..')) {
        print('<option value="' . $file . '">' . $file . '</option>' . "\n");
    }

}
closedir($dir);
?>
              </select>
              <br>
              <font size="1">maly obrazeczek, pojawiajacy sie na liscie skrotow
              newsow. symbolizuje<br>
              zasadniczo strone internetowa, z ktorej pochodzi news. nalezy wybrac
              jeden z listy<br>
              jezeli nie ma odpowiedniego (news ze strony, z ktorej do tej pory
              nie dodawalismy - piszcie do mnie)</font></td>
          </tr>
          <tr align="left">
            <td height="70" valign="top">
              <p><b>POWIAZANE KONCERTY</b><br>
                <input type="text" name="concerts">
                <br>
                <font size="1">id koncertow powiazanych z newsem, oddzielone przeciankami<br>
                (bez zadnych spacji i innych znakow) np: 2,24,12,47</font></p>
            </td>
          </tr>
          <tr align="left">
            <td height="70" valign="top">
              <p><b>POWIAZANI WYKONAWCY</b><br>
                <input type="text" name="artists">
                <br>
                id wykonawcow powiazanych z newsem, oddzielone przecinkami<br>
                (bez zadnych innych znakow) np.: 24,6,240,277</p>
            </td>
          </tr>
          <tr align="left">
            <td height="70" valign="top">
              <p><b>POWIAZANE WYTWORNIE</b><br>
                <input type="text" name="labels">
                <br>
                id wytworni powiazanych z newsem, oddzielone przecinkami<br>
                (bez zadnych innych znakow) np.: 4,1,27</p>
            </td>
          </tr>
          <tr align="left">
            <td height="70" valign="top"><b>POWIAZANE ALBUMY</b><br>
              <input type="text" name="albums">
              <br>
              id albumow powiazanych z newsem, oddzielone przecinkami<br>
              (bez zadnych innych znakow) np.: 34,2,189,98</td>
          </tr>
          <tr align="left">
            <td height="70" valign="top"><b>POWIAZANE MIASTA</b><br>
              <input type="text" name="cities">
              <br>
              idmiast powiazanych z newsem, oddzielone przecinkami<br>
              (bez zadnych innych znakow) np.: 1,2,23,5</td>
          </tr>
          <tr align="left">
            <td valign="top">
              <input type="submit" value="DODAJ NEWS">
            </td>
          </tr>
        </table>
        </form>
    </td>
  </tr>
</table>
<?php include 'template_bottom.php';?>
