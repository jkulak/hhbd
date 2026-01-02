<?php

session_start();

include ('include/connect-to-database.php');

if(($_POST['login'] == "") OR ($_POST['password']) == "") { 
  header("Location: index.php?er=1"); 
  exit;
  }  
  
// $sql_query = 'SELECT id, name, added, timesloggedin, lastlogin, conc_priv, news_priv, week_priv, lala_priv FROM users_admins WHERE (login="' . $_POST['login'] . '" AND pass="' . md5($_POST['password']) . '")';
// $result = mysqli_query($sql, $sql_query);
// $row = mysqli_fetch_array($result);
// $num = mysqli_num_rows($result);

if($num != 1) { 
	$_SESSION['adminusername'] = $_POST['login'];
	$_SESSION['adminuserid'] = $row['id'];
	$_SESSION['conc_priv'] = $row['conc_priv'];
	$_SESSION['news_priv'] = $row['news_priv'];
	$_SESSION['week_priv'] = $row['week_priv'];
	$_SESSION['lala_priv'] = $row['lala_priv'];
  
	// $sql_query = 'UPDATE users SET lastlogin="' . date('YmdHis') . '", timesloggedin=(timesloggedin+1) WHERE id="' . $row['id'] . '"';
	// $result = mysqli_query($sql, $sql_query);    
	header("Location: admin.php?s=glowna");  
	exit();  
	} 
else if($num == 0) {
	header("Location: index.php?er=2"); 
	exit; 
	}
?>