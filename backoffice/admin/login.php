<?php

session_start();

if (isset($_SESSION['username'])) {
    header("Location: main.php");
	exit();
    }

  include ('connect_to_database.php');

if(($_POST['login'] == "") OR ($_POST['password']) == "") {
  header("Location: index.php?er=1");
  exit;
  }

$sql_query = "SELECT id, login, name, added, timesloggedin, lastlogin FROM users WHERE (login='" . $_POST['login'] . "' AND pass=('" . md5($_POST['password']) . "'))";
$result = mysqli_query($sql, $sql_query);
$row = mysqli_fetch_array($result);
$num = mysqli_num_rows($result);

if($num == 1) {

  $timeslogedin = $row['timesloggedin'];

  $_SESSION['username'] = $row['login'];
  $_SESSION['userid'] = $row['id'];
  $_SESSION['lastlogin'] = $row['lastlogin'];
  $_SESSION['timesloggedin'] = $timesloggedin;

  $data_logowania = date("YmdHis");
  $timeslogedin ++ ;

  $sql_query = 'UPDATE users SET lastlogin="' . date('YmdHis') . '", timesloggedin=(timesloggedin+1) WHERE id="' . $row['id'] . '"';
  $result = mysqli_query($sql, $sql_query);
  header("Location: main.php");
  exit();
  }
else if($num == 0) {
  header("Location: index.php?er=2");
  exit;
  }
?>
