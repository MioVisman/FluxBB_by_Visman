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

	$mflag = 2;

	if (isset($_POST['action2']))
	{
		if (!isset($_POST['topics']))
			message($lang_common['Bad request'], false, '404 Not Found');

		if (@preg_match('/[^0-9,]/', $_POST['topics']))
			message($lang_common['Bad request'], false, '404 Not Found');

		$topics = explode(',', $_POST['topics']);
	}
	else
	{
		if (!isset($_POST['post_topic']))
			message($lang_common['Bad request'], false, '404 Not Found');

		$topics = array_map('intval', array_keys($_POST['post_topic']));
	}

	$kolvo = count($topics);

	if ($kolvo == 0)
		message($lang_pmsn['No dialogs']);
	if (count(array_diff($topics, $pmsn_arr_save)) > 0)
		message($lang_pmsn['Err1']);
	if ($mflag == 3 && $pun_user['g_pm_limit'] != 0 && $pmsn_kol_save+$kolvo > $pun_user['g_pm_limit'])
		message($lang_pmsn['Err2']);

	// действуем
	if (isset($_POST['action2']))
	{
		pmsn_user_delete($pun_user['id'], $mflag, $topics);

		$mred = '';
		if (isset($_POST['p']))
		{
			$p = intval($_POST['p']);
			if ($p > 1)
				$mred = '&amp;p='.$p;
		}
		redirect('pmsnew.php?mdl=save'.$mred, $lang_pmsn['List redirect']);
	}
}
else
	message($lang_common['Bad referrer']);

$mh2 = $lang_pmsn['InfoDeleteQ'];
$mhm = $lang_pmsn['InfoDeleteQm'];
$mfm = 'delete';

define('PUN_ACTIVE_PAGE', 'pms_new');
require PUN_ROOT.'header.php';
?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="pmsnew.php"><?php echo $lang_pmsn['PM'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_pmsn[$pmsn_modul].($sid ? $lang_pmsn['With'].$siduser : '') ?></strong></li>
		</ul>
		<div class="pagepost"></div>
		<div class="clearer"></div>
	</div>
</div>
<?php

generate_pmsn_menu($pmsn_modul);

?>
	<div class="blockform">
		<h2><span><?php echo $mh2 ?></span></h2>
		<div class="box">
			<form method="post" action="pmsnew.php?mdl=saveq<?php echo $sidamp ?>">
				<div class="inform">
					<input type="hidden" name="csrf_hash" value="<?php echo $pmsn_csrf_hash ?>" />
					<input type="hidden" name="topics" value="<?php echo implode(',', $topics) ?>" />
					<input type="hidden" name="<?php echo $mfm ?>" value="1" />
					<input type="hidden" name="p" value="<?php echo intval($_POST['p']) ?>" />
					<fieldset>
						<legend><?php echo $lang_pmsn['Attention'] ?></legend>
						<div class="infldset">
							<p><?php echo $mhm ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="action2" value="<?php echo $lang_pmsn['Yes'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
<?php

