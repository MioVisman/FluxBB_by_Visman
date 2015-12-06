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

$uid = intval(pmsn_get_var('uid', 0));
if ($uid < 2)
	message($lang_common['Bad request'], false, '404 Not Found');

$csrf_token = pmsn_csrf_token($uid);
if (!pun_hash_equals($csrf_token, pmsn_get_var('csrf_token', '')))
	message($lang_common['Bad request'], false, '404 Not Found');

$result = $db->query('SELECT id, group_id, username FROM '.$db->prefix.'users WHERE id='.$uid) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
$cur_user = $db->fetch_assoc($result);

if (!isset($cur_user['id']))
	message($lang_common['Bad request'], false, '404 Not Found');
else if ($cur_user['id'] == $pun_user['id'])
	message($lang_pmsn['No block itself']);
else if ($cur_user['group_id'] == PUN_ADMIN)
	message($lang_pmsn['No block admin']);

$result = $db->query('SELECT bl_id FROM '.$db->prefix.'pms_new_block WHERE bl_id='.$pun_user['id'].' AND bl_user_id='.$uid) or error('Unable to fetch block information', __FILE__, __LINE__, $db->error());
if (!$db->num_rows($result))
{
	$mh2 = $lang_pmsn['InfoBlocking'].' '.pun_htmlspecialchars($cur_user['username']);
	$mhm = $lang_pmsn['InfoBlockingm'];
	$mfl = true;
	$mbm = $lang_pmsn['Blocking redirect'];
}
else
{
	$mh2 = $lang_pmsn['InfoReBlocking'].' '.pun_htmlspecialchars($cur_user['username']);
	$mhm = $lang_pmsn['InfoReBlockingm'];
	$mfl = false;
	$mbm = $lang_pmsn['ReBlocking redirect'];
}

if (isset($_POST['action2']))
{
	if (!defined('PUN_PMS_NEW_CONFIRM'))
		message($lang_common['Bad referrer']);

	if ($mfl)
	{
		$db->query('INSERT INTO '.$db->prefix.'pms_new_block (bl_id, bl_user_id) VALUES('.$pun_user['id'].', '.$uid.')') or error('Unable to create line in pms_new_block', __FILE__, __LINE__, $db->error());

		if (isset($_POST['delete_dlg'])) // удаление диалогов
		{
			$result = $db->query('SELECT id FROM '.$db->prefix.'pms_new_topics WHERE (starter_id = '.$pun_user['id'].' AND topic_st < 2 AND to_id='.$uid.') OR (to_id = '.$pun_user['id'].' AND topic_to < 2 AND starter_id='.$uid.')') or error('Unable to fetch pms topics IDs', __FILE__, __LINE__, $db->error());
			if ($db->num_rows($result))
			{
				$ts = array();
				for ($i = 0;$ctid = $db->result($result, $i);$i++)
					$ts[] = $ctid;

				pmsn_user_delete($pun_user['id'], 2, $ts);
			}
		}
	}
	else
		$db->query('DELETE FROM '.$db->prefix.'pms_new_block WHERE bl_id='.$pun_user['id'].' AND bl_user_id='.$uid) or error('Unable to remove line in pms_new_block', __FILE__, __LINE__, $db->error());;

	redirect('pmsnew.php', $mbm); // ???
}

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
		<h2><span><?php echo $mh2 ?></span></h2>
		<div class="box">
			<form method="post" action="pmsnew.php?mdl=blocking">
				<div class="inform">
					<input type="hidden" name="csrf_hash" value="<?php echo $pmsn_csrf_hash ?>" />
					<input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?>" />
					<input type="hidden" name="uid" value="<?php echo $uid ?>" />
					<fieldset>
						<legend><?php echo $lang_pmsn['Attention'] ?></legend>
						<div class="infldset">
							<p><?php echo $mhm ?></p>
<?php if ($mfl): ?>
							<div class="rbox">
								<label><input type="checkbox" name="delete_dlg" value="1" /><?php echo $lang_pmsn['Delete dialog'] ?><br /></label>
							</div>
<?php endif; ?>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="action2" value="<?php echo $lang_pmsn['Yes'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
<?php

