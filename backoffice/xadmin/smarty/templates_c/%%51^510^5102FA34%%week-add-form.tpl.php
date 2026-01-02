<?php /* Smarty version 2.6.9, created on 2022-05-26 21:03:51
         compiled from site/week-add-form.tpl */ ?>
<form action="?s=week_dodaj" method="POST" enctype="multipart/form-data">

<div class="rbox"><strong>POSTAæ *</strong> -> <a href="#" onClick="window.open('artists-alphabetical-list.php?id=arti','_blank','scrollbars=yes,width=200,height=500');">DODAJ</a><BR>
<input id="arti" name="artist" type="text" maxlength="255" value="<?php echo $this->_tpl_vars['artists']; ?>
" class="maxlen"><BR>
id najnowszej postaci tygodnia
</div>

<input type="submit" name="action" value="DODAJ" class="btn" style="float: right; display: inline; width: 200px; color: green;">
<input type="reset" name="action" value="RESET" class="btn" style="float: left; display: inline; width: 100px;">


</form>