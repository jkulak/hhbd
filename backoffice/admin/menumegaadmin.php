<?php

if (session_id() === '') {
    session_start();
}

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}
?>
  <tr>
    <td bgcolor="#7C514A"> <b><font color="#FFFFFF"> MEGA ADMIN</font></b></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="add_admin_form.php" class="adminmenulink">dodaj admina</a></td>
  </tr>
  <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="install_database.php" class="adminmenulink">zainstaluj baze</a></td>
  </tr>
    <tr>
    <td bgcolor="#E6D9B3" height="17"> <a href="add_news_form.php" class="adminmenulink">dodaj newsa</a></td>
  </tr>
  <tr>
  <td height="17">&nbsp;</td>
  </tr>
