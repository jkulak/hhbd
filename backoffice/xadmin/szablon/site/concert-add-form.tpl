
<form action="?s=concert_dodaj" method="POST" enctype="multipart/form-data">

<div class="rbox"><strong>TYTU£*</strong><BR>
<input name="title" type="text" maxlength="255" class="maxlen"><BR>
tytul koncertu, naglowek opisujacy koncert, gora jedno zdanie pierwsza litera wielka, pozostale male (chyba ze nazwa wlasna)
</div>

<div class="rbox"><strong>WYKONAWCY</strong> -> <a href="#" onClick="window.open('artists-alphabetical-list.php?id=artists','hhh','scrollbars=yes,width=200,height=500');">DODAJ</a><BR>
<input id="artists" name="artists" type="text" maxlength="255" class="maxlen"><BR>
id wykonawcow, oddzielone przecinkami (bez zadnych spacji i innych znakow)<br>
np: 24,12,477,432<BR>


</div>

<div class="rbox"><strong>MIASTO</strong><BR>
<select name="cityid">
{section name=id loop=$cities}
{$cities[id]}
{/section}
</select><BR>
jezeli nie ma miasta na liscie, nalezy je najpierw dodac, a pozniej dopiero edytowac koncert
</div>

<div class="rbox"><strong>OPIS</strong><BR>
<textarea name="description" rows="10" class="maxlen"></textarea><BR>
kilka zdan opisujacych koncert, 'nastepna linia' bedzie automatycznie zamieniana w znaczniki P. nie uzywamy BR i innych.
</div>

<div class="rbox"><strong>DATA*</strong><BR>
<input type="text" name="date" class="maxlen"><BR>
data koncertu w formacie rrrr-mm-dd
</div>

<div class="rbox"><strong>START</strong><BR>
<input type="text" name="start" class="maxlen"><BR>
godzina rozpoczecia, dowolny ciag znakow<BR>
np: 'pokazy tanca od 20:00, rozpoczecia koncertu 22:30'
</div>

<div class="rbox"><strong>CENA</strong><BR>
<input type="text" name="cost" class="maxlen"><BR>
cena biletow na koncert, dowolny ciag znakow<br>
np.: 'przedsprzedaz: 15pln, w dniu koncertu 20pln'
</div>

<div class="rbox"><strong>MIEJSCE</strong><BR>
<input type="text" name="place" class="maxlen"><BR>
nazwa klubu, w ktorym odbywa sie koncert, dowolny ciag znakow<BR>
np.: 'Klub studencki Herkulesy'
</div>

<div class="rbox"><strong>WEBSITE</strong><BR>
<input type="text" name="website" class="maxlen"><BR>
strona internetowa dotyczaca koncertu w formacie: http://tutaj_adres_strony<BR>
np.: '<strong><font color=red>http://</font></strong>www.slizgery2005.pl'
</div>

<div class="rbox"><strong>PLAKAT</strong><BR>
<select name="poster" class="maxlen">
<option value=""></option>
{section name=id loop=$posters}
{$posters[id]}
{/section}
</select>
<input type="file" class="maxlen" name="newposter"><BR>
wybierz plik plakatu z dysku, <b>ALBO</b> wybierz jeden z dostepnych juz na serwerze (np dla trasy koncertowej) maksymalna szerokosc plakatu 250px!!! wysokosc bez ograniczen, format JPG, skompresowane za pomoca ACDSee, jakosc 50
</div>

<input type="submit" name="action" value="DODAJ" class="btn" style="float: right; display: inline; width: 200px; color: green;">
<input type="reset" name="action" value="RESET" class="btn" style="float: left; display: inline; width: 100px;">


</form>