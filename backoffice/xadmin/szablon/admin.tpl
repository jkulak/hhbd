<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title>HHBD.PL | XADMIN</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf8">
<link rel="stylesheet" href="css/xadmin.css" type="text/css">
<!-- Latest compiled and minified CSS -->
<!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css"> -->
</head>
<body>

<h1>HHBD.PL XADMIN -> {$mainsection}</h1>
<div class="container">

<div class="leftsite">


<div class="menubox">
<div class="menuboxh">Panel administracyjny</div>
<a href="?s=glowna">strona główna</a>
<a href="logout.php">wyloguj</a>
</div>

<div class="menubox">
<div class="menuboxh">DODAJ</div>
<a href="?s=news_dodaj_form">NEWS</a>
<a href="?s=concert_dodaj_form">KONCERT</a>
<a href="?s=week_dodaj_form">POSTAĆ TYGODNIA</a>
</div>

<div class="menubox">
<div class="menuboxh">EDYTUJ</div>
{if $conc_priv eq "y"}<a href="?s=edycjakoncertw">KONCERT</a>{/if}
</div>

<div class="menubox">
<div class="menuboxh">linki</div>
<a href="http://www.hhbd.pl" target="_blank">www.hhbd.pl</a>

</div>


</div>

<div class="rightsite">
<p class="info">{$info}</p>

<div class="rightsiteh">{$ctitle}</div>
{include file="$body_template"}

</div>



</div>


</body>
</html>
