
<table width="140" border="0" cellspacing="0" cellpadding="0" class="plaintext">
  <tr>
    <td height="15">
      <div align="center"><font size="3"></font></div>
    </td>
  </tr>
  <tr>
    <td height="0">
      <div align="center"><font size="3"><b>MENU</b></font></div>
    </td>
  </tr>
  <tr>
    <td height="15">&nbsp;</td>
  </tr>
  <tr>
    <td bgcolor="#7C514A"> <b><font color="#FFFFFF"> DODAJ</font></b></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="add_album_form.php" class="adminmenulink">album</a>
      (<a href="add_cover_form.php" class="adminmenulink">okładkę</a>) </td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="add_album_from_file_form.php" class="adminmenulink">Album
      z pliku</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="add_song_form.php" class="adminmenulink">utwór</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="add_artist_form.php" class="adminmenulink">wykonawcę</a>
      (<a href="add_artist_photo_form.php" class="adminmenulink">fote</a>) </td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="add_label_form.php" class="adminmenulink">wytwórnię</a>
      (<a href="add_label_logo_form.php" class="adminmenulink">logo</a>) </td>
  </tr>
  <tr>
    <td>&nbsp; </td>
  </tr>
  <tr>
    <td bgcolor="#7C514A"> <b><font color="#FFFFFF"> EDYTUJ</font></b></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="edit_album_select.php" class="adminmenulink">album</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="edit_song_select.php" class="adminmenulink">utwór</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="edit_artist_select.php" class="adminmenulink">wykonawcę</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="edit_label_select.php" class="adminmenulink">wytwórnię</a></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td bgcolor="#7C514A"><b><font color="#FFFFFF">INNE</font></b></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3"> <a href="add_artist_to_band_form.php" class="adminmenulink">powiąż
      wykonawców</a><br>
      <a href="add_price_form.php" class="adminmenulink">dodaj cene albumu</a>
    </td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="add_concert_form.php" class="adminmenulink">dodaj
      koncert</a></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td bgcolor="#7C514A"> <b><font color="#FFFFFF"> USUŃ !!!</font></b></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="remove_album_form.php" class="adminmenulink">album</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="remove_song_form.php" class="adminmenulink">utwór</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="remove_artist_form.php" class="adminmenulink">wykonawcę</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="remove_label_form.php" class="adminmenulink">wytwórnię
      (nie dziala)</a></td>
  </tr>
  <tr>
    <td>&nbsp; </td>
  </tr>
  <?php
  if ($_SESSION['username'] == 'fee') {
    include ('menumegaadmin.php');
    }
  ?>
  <tr>
    <td bgcolor="#7C514A"><font color="#FFFFFF"><b>MODERUJ</b></font></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"><a href="mod_album_reviews_form.php" class="adminmenulink">recenzje</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"><a href="mod_artists_websites_form.php" class="adminmenulink">nowe
      strony</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"><a href="mod_samples_form.php" class="adminmenulink">sample</a></td>
  </tr>
  <tr>
    <td >&nbsp;</td>
  </tr>
  <tr>

  </tr>

 <tr>
    <td bgcolor="#E6D9B3" height="17">

	<a href="add_eshop_form.php" class="adminmenulink">dodaj sklep</a>

	</td>
  </tr>


  <tr>
    <td>
      <?php //print ('<a href="logout.php" class="adminmenulink">wyloguj: <b>' . $_SESSION['username'] . '</b></a>' . 'userid: ' .  $_SESSION['userid']); ?>
    </td>
  </tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td> </td>
  </tr>
  <tr>
    <td>&nbsp;</td>
  </tr>
</table>
