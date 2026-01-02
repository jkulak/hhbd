<form action="?s=edycjakoncert" method="POST" enctype="multipart/form-data">
<select name="id" style="width: 532px;">
{section name=id loop=$titles}
<option value="{$ids[id]}">{$titles[id]}</option>
{/section}
</select>
<input type="submit" value="WYBIERZ" class="btn" style="float: right;">
</form>