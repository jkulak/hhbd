<?php
session_start();

if (isset($_SESSION['username'])) {
    header("Location: main.php");
	exit();
    }
	
?>
<html>
<head>
<title>hhbd.pl | panel administracyjny</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf8">
</head>

<body bgcolor="#F0E9CE" text="#000000" leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">
<table width="90%" border="0" cellspacing="0" cellpadding="0" align="center" height="100%">
  <tr>
    <td align="center"> <b><font face="Tahoma">hhbd.pl</font></b>
      <form name="form1" method="POST" action="login.php" height=100px>
        <table width="130" border="0" cellspacing="0" cellpadding="0">
          <tr>
            <td height="71"> 
              <font size="1" face="Tahoma">login:</font><br>
                <input type="text" name="login" style=" padding: 1px;  color: #000000; background-color: #E6D9B3;
                      border: solid 1px #7C514A; width = 130px;">
                <br>
                <font size="1" face="Tahoma">has³o</font>:<br>
                <input type="password" name="password" style=" padding: 1px;  color: #000000; background-color: #E6D9B3;
                      border: solid 1px #7C514A; width = 130px;">
             
              <p align="right"> 
                <input type="submit" name="submit" value="loguj"style=" padding: 1px;  color: #000000; background-color: #E6D9B3;
                     font-weight: bold; border: solid 1px #7C514A; width = 70px;">
              </p>
            </td>
          </tr>
        </table>
      </form>
    </td>
  </tr>
</table>
</body>
</html>
