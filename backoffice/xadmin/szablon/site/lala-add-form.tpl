<form action="?s=lala_dodaj" method="POST" enctype="multipart/form-data">

<div class="rbox"><strong>UTWÓR *</strong> -> <a href="#" onClick="window.open('alphabetical-list-songs.php?id=songid','_blank','scrollbars=yes,width=400,height=560');">DODAJ</a><BR>
<input id="songid" name="songid" type="text" maxlength="255" value="{$artists}" class="maxlen"><BR>
id dodawanego utworu
</div>

<div class="rbox"><strong>ID DZWONKA Z LALALAB.PL *</strong><BR>
<input name="lalaid" type="text" maxlength="255" value="{$artists}" class="maxlen"><BR>
id dzownka na lalalab.pl
</div>

<input type="submit" name="action" value="DODAJ" class="btn" style="float: right; display: inline; width: 200px; color: green;">
<input type="reset" name="action" value="RESET" class="btn" style="float: left; display: inline; width: 100px;">


</form>
