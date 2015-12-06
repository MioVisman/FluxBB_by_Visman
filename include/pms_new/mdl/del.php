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

$tid = intval(pmsn_get_var('tid', 0));
$pid = intval(pmsn_get_var('pid', 0));
if ($tid < 1 && $pid < 1)
	message($lang_common['Bad request'], false, '404 Not Found');

if ($pid)
	$result = $db->query('SELECT t.id AS tid, t.topic, t.starter_id, t.to_id, t.replies, t.topic_st, t.topic_to FROM '.$db->prefix.'pms_new_posts AS p INNER JOIN '.$db->prefix.'pms_new_topics AS t ON t.id=p.topic_id WHERE p.id='.$pid.' AND p.poster_id='.$pun_user['id'].' AND post_new=1') or error('Unable to fetch pms_new_posts info', __FILE__, __LINE__, $db->error());
else
	$result = $db->query('SELECT id AS tid, topic, starter_id, to_id, replies FROM '.$db->prefix.'pms_new_topics WHERE id='.$tid) or error('Unable to fetch pms_new_topics info', __FILE__, __LINE__, $db->error());

if (!$db->num_rows($result))
	message($lang_common['Bad request'], false, '404 Not Found');

$cur_post = $db->fetch_assoc($result);

if (!in_array($cur_post['tid'], $pmsn_arr_list) && !in_array($cur_post['tid'], $pmsn_arr_save))
	message($lang_common['Bad request'], false, '404 Not Found');

if (isset($_POST['action2']))
{
	if (!defined('PUN_PMS_NEW_CONFIRM'))
		message($lang_common['Bad referrer']);

	if ($pid)
	{
		// Delete the post
		$db->query('DELETE FROM '.$db->prefix.'pms_new_posts WHERE id='.$pid) or error('Unable to delete pms_new_posts', __FILE__, __LINE__, $db->error());

		$result = $db->query('SELECT id, poster_id, posted FROM '.$db->prefix.'pms_new_posts WHERE topic_id='.$cur_post['tid'].' ORDER BY id DESC LIMIT 1') or error('Unable to fetch pms_new_posts info', __FILE__, __LINE__, $db->error());
		list($second_last_id, $second_poster, $second_posted) = $db->fetch_row($result);

		if (!$second_last_id)
			message($lang_common['Bad request'], false, '404 Not Found');

		$mquery = array();
		$muser = 0;
		$mquery[] = 'last_posted='.$second_posted.', last_poster='.(($second_poster == $cur_post['to_id']) ? '1' : '0');

		// Count number of replies in the topic
		$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'pms_new_posts WHERE topic_id='.$cur_post['tid']) or error('Unable to fetch post count', __FILE__, __LINE__, $db->error());
		$num_replies = $db->result($result, 0) - 1;
		$mquery[] = 'replies='.$num_replies;


		if ($pun_user['id'] == $cur_post['starter_id'] && $cur_post['topic_to'] == 1)
		{
			$result = $db->query('SELECT id FROM '.$db->prefix.'pms_new_posts WHERE poster_id='.$pun_user['id'].' AND topic_id='.$cur_post['tid'].' AND post_new=1') or error('Unable to fetch post count', __FILE__, __LINE__, $db->error());
			if (!$db->num_rows($result))
			{
				$mquery[] = 'topic_to=0';
				$muser = $cur_post['to_id'];
			}
		}
		else if ($pun_user['id'] == $cur_post['to_id'] && $cur_post['topic_st'] == 1)
		{
			$result = $db->query('SELECT id FROM '.$db->prefix.'pms_new_posts WHERE poster_id='.$pun_user['id'].' AND topic_id='.$cur_post['tid'].' AND post_new=1') or error('Unable to fetch post count', __FILE__, __LINE__, $db->error());
			if (!$db->num_rows($result))
			{
				$mquery[] = 'topic_st=0';
				$muser = $cur_post['starter_id'];
			}
		}

		$db->query('UPDATE '.$db->prefix.'pms_new_topics SET '.implode(', ', $mquery).' WHERE id='.$cur_post['tid']) or error('Unable to update pms_new_topics', __FILE__, __LINE__, $db->error());

		if ($muser)
			pmsn_user_update($muser);

		redirect('pmsnew.php?mdl=topic&amp;tid='.$cur_post['tid'].$sidamp, $lang_pmsn['DelMes redirect']);
	}
	else
	{
		pmsn_user_delete($pun_user['id'], 2, array($cur_post['tid']));
		
		if (in_array($cur_post['tid'], $pmsn_arr_new))
			redirect('pmsnew.php?mdl=new'.$sidamp, $lang_pmsn['DelTop redirect']);
		else if (in_array($cur_post['tid'], $pmsn_arr_save))
			redirect('pmsnew.php?mdl=save'.$sidamp, $lang_pmsn['DelTop redirect']);
		else
			redirect('pmsnew.php?mdl=list'.$sidamp, $lang_pmsn['DelTop redirect']);
	}
}

if ($pid && $cur_post['replies'] > 0)
{
	$mh2 = $lang_pmsn['InfoDeleteQMes'];
	$mhm = $lang_pmsn['InfoDeleteMes'];
	$mfm = '<input type="hidden" name="pid" value="'.$pid.'" />'."\n";
}
else
{
	$mh2 = $lang_pmsn['InfoDeleteQTop'];
	$mhm = $lang_pmsn['InfoDeleteTop'];
	$mfm = '<input type="hidden" name="tid" value="'.$cur_post['tid'].'" />'."\n";
}

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
			<form method="post" action="pmsnew.php?mdl=del<?php echo $sidamp ?>">
				<div class="inform">
					<input type="hidden" name="csrf_hash" value="<?php echo $pmsn_csrf_hash ?>" />
					<?php echo $mfm ?>
					<fieldset>
						<legend><?php echo $lang_pmsn['Attention'] ?></legend>
						<div class="infldset">
							<p><?php echo sprintf($mhm, pun_htmlspecialchars($cur_post['topic'])) ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="action2" value="<?php echo $lang_pmsn['Delete'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
<?php

