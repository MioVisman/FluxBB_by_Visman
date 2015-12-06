<?php

/**
 * Copyright (C) 2010-2015 Visman (mio.visman@yandex.ru)
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (!defined('PUN') || !defined('PUN_PMS_NEW'))
	exit;

define('PUN_PMS_LOADED', 1);

if (defined('PUN_PMS_NEW_CONFIRM'))
{
	if (!isset($_POST['delete']))
		message($lang_common['Bad request'], false, '404 Not Found');

	if (isset($_POST['action2']))
	{
		if (!isset($_POST['user_numb']))
			message($lang_common['Bad request'], false, '404 Not Found');

		if (@preg_match('/[^0-9,]/', $_POST['user_numb']))
			message($lang_common['Bad request'], false, '404 Not Found');

		$unumbs = explode(',', $_POST['user_numb']);
	}
	else
	{
		if (!isset($_POST['user_numb']))
			message($lang_common['Bad request'], false, '404 Not Found');

		$unumbs = array_map('intval', array_keys($_POST['user_numb']));
	}

	if (count($unumbs) < 1)
		message($lang_common['Bad request'], false, '404 Not Found');

	// действуем
	if (isset($_POST['action2']))
	{
		$db->query('DELETE FROM '.$db->prefix.'pms_new_block WHERE bl_id='.$pun_user['id'].' AND bl_user_id IN ('.implode(',', $unumbs).')') or error('Unable to remove line in pms_new_block', __FILE__, __LINE__, $db->error());

		$mred = '';
		if (isset($_POST['p']))
		{
			$p = intval($_POST['p']);
			if ($p > 1)
				$mred = '&amp;p='.$p;
		}
		redirect('pmsnew.php?mdl=blocked'.$mred, $lang_pmsn['ReBlocking redirect']);
	}
}
else
	message($lang_common['Bad referrer']);

define('PUN_ACTIVE_PAGE', 'pms_new');
require PUN_ROOT.'header.php';
?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="pmsnew.php"><?php echo $lang_pmsn['PM'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_pmsn[$pmsn_modul] ?></strong></li>
		</ul>
		<div class="pagepost"></div>
		<div class="clearer"></div>
	</div>
</div>
<?php

generate_pmsn_menu($pmsn_modul);

?>
	<div class="blockform">
		<h2><span><?php echo $lang_pmsn['InfoReBlockingS'] ?></span></h2>
		<div class="box">
			<form method="post" action="pmsnew.php?mdl=blockedq">
				<div class="inform">
					<input type="hidden" name="csrf_hash" value="<?php echo $pmsn_csrf_hash; ?>" />
					<input type="hidden" name="user_numb" value="<?php echo implode(',', $unumbs) ?>" />
					<input type="hidden" name="delete" value="1" />
					<input type="hidden" name="p" value="<?php echo intval($_POST['p']); ?>" />
					<fieldset>
						<legend><?php echo $lang_pmsn['Attention'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_pmsn['InfoReBlockingSQ'] ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="action2" value="<?php echo $lang_pmsn['Yes'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
<?php
