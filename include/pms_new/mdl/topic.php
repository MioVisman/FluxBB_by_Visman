<?php

/**
 * Copyright (C) 2010-2022 Visman (mio.visman@yandex.ru)
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (!defined('PUN') || !defined('PUN_PMS_NEW'))
	exit;

define('PUN_PMS_LOADED', 1);

$tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
if ($tid < 1 && $pid < 1)
	message($lang_common['Bad request'], false, '404 Not Found');

if ($pid)
{
	$result = $db->query('SELECT topic_id FROM '.$db->prefix.'pms_new_posts WHERE id='.$pid) or error('Unable to fetch pms_new_posts info', __FILE__, __LINE__, $db->error());
	$tid = $db->result($result);

	if (!$tid)
		message($lang_common['Bad request'], false, '404 Not Found');

	$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'pms_new_posts WHERE topic_id='.$tid.' AND id<'.$pid) or error('Unable to fetch pms_new_posts info', __FILE__, __LINE__, $db->error());
	$i = $db->result($result) + 1;
	$_GET['p'] = ceil($i / $pun_user['disp_posts']);
}
else if ($action === 'new')
{
	$result = $db->query('SELECT MIN(id) FROM '.$db->prefix.'pms_new_posts WHERE poster_id!='.$pun_user['id'].' AND topic_id='.$tid.' AND post_new=1') or error('Unable to fetch pms_new_posts info', __FILE__, __LINE__, $db->error());
	$first_new_post_id = $db->result($result);
	if ($first_new_post_id)
	{
		header('Location: pmsnew.php?mdl=topic&pid='.$first_new_post_id.'#p'.$first_new_post_id);
		exit;
	}
}

if (in_array($tid, $pmsn_arr_new))
	$mmodul = 'new';
else if (in_array($tid, $pmsn_arr_list))
	$mmodul = 'list';
else if (in_array($tid, $pmsn_arr_save))
	$mmodul = 'save';
else
	message($lang_common['Bad request'], false, '404 Not Found');

$result = $db->query('SELECT t.*, u.num_posts, u.id AS userid, u.group_id FROM '.$db->prefix.'pms_new_topics AS t LEFT JOIN '.$db->prefix.'users AS u ON (u.id!='.$pun_user['id'].' AND (u.id=t.starter_id OR u.id=t.to_id)) WHERE t.id='.$tid) or error('Unable to fetch pms_new_topics info', __FILE__, __LINE__, $db->error());
$cur_topic = $db->fetch_assoc($result);

if (!$cur_topic)
	message($lang_common['Bad request'], false, '404 Not Found');

$to_user = array();

if ($cur_topic['starter_id'] == $pun_user['id'])
{
	$to_user['id'] = $cur_topic['to_id'];
	$to_user['username'] = $cur_topic['to_user'];

	if ($cur_topic['topic_st'] < 2)
	{
		$query_end = 'see_st='.time();
		$query_end2 = ', topic_st=0';
		$status = true;
	}
	else
		$status = false;
}
else
{
	$to_user['id'] = $cur_topic['starter_id'];
	$to_user['username'] = $cur_topic['starter'];

	if ($cur_topic['topic_to'] < 2)
	{
		$query_end = 'see_to='.time();
		$query_end2 = ', topic_to=0';
		$status = true;
	}
	else
		$status = false;
}

$newpost = false;
$quickpost = false;
if ($pun_user['messages_enable'] == 1 && $pun_user['g_pm'] == 1)
{
	if (($cur_topic['topic_st'] < 2 && $cur_topic['topic_to'] < 2) || ($pun_user['id'] == $cur_topic['starter_id'] && $cur_topic['topic_st'] == 3 && $cur_topic['topic_to'] == 2))
	{
		$pmsn_f_cnt = '<span><a href="pmsnew.php?mdl=post&amp;tid='.$tid.$sidamp.'">'.$lang_pmsn['Add Reply'].'</a></span>'.$pmsn_f_cnt;
		$newpost = true;

		if ($pun_config['o_quickpost'] == '1' && ($pun_config['o_pms_min_kolvo'] <= $pun_user['num_posts'] || $pun_user['g_id'] == PUN_ADMIN))
		{
			$quickpost = true;
			$required_fields = array('req_message' => $lang_common['Message']);
		}
		else
			$quickpost = false;
	}
	$pmsn_f_cnt = '<span><a href="pmsnew.php?mdl=del&amp;tid='.$tid.$sidamp.'">'.$lang_pmsn['Delete'].'</a></span>'.$pmsn_f_cnt;

	if ($mmodul == 'save' && $cur_topic['starter_id'] == $pun_user['id'] && $cur_topic['see_to'] == 0)
		if ($pun_user['g_pm_limit'] == 0 || $pmsn_kol_list < $pun_user['g_pm_limit'])
			$pmsn_f_cnt = '<span><a href="pmsnew.php?mdl=send&amp;tid='.$tid.$sidamp.'">'.$lang_pmsn['Send d'].'</a></span>'.$pmsn_f_cnt;
}

if ($cur_topic['num_posts'] < $pun_config['o_pms_min_kolvo'] && $cur_topic['group_id'] != PUN_ADMIN && $cur_topic['userid'] > 1 && $cur_topic['topic_st'] < 2 && $cur_topic['topic_to'] < 2)
	$psmnwarn = "\t\t\t\t\t\t\t".'<div class="psmnwarn">'."\n\t\t\t\t\t\t\t\t".sprintf($lang_pmsn['Warn'], $pun_config['o_pms_min_kolvo'])."\n\t\t\t\t\t\t\t".'</div>'."\n";
else
	$psmnwarn = '';

require PUN_ROOT.'lang/'.$pun_user['language'].'/topic.php';

// Determine the post offset (based on $_GET['p'])
$num_pages = ceil(($cur_topic['replies'] + 1) / $pun_user['disp_posts']);

$p = ! is_numeric($_GET['p'] ?? null) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages ? 1 : intval($_GET['p']);
$start_from = $pun_user['disp_posts'] * ($p - 1);

// Generate paging links
$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'pmsnew.php?mdl=topic&amp;tid='.$tid.$sidamp);

if ($pun_config['o_censoring'] == '1')
	$cur_topic['topic'] = censor_words($cur_topic['topic']);

define('PUN_ACTIVE_PAGE', 'pms_new');
require PUN_ROOT.'header.php';
?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="pmsnew.php"><?php echo $lang_pmsn['PM'] ?></a></li>
			<li><span>»&#160;</span><a href="pmsnew.php?mdl=<?php echo $mmodul.$sidamp ?>"><?php echo $lang_pmsn[$mmodul].($sid ? $lang_pmsn['With'].$siduser : '') ?></a></li>
<?php
if (isset($to_user['id']) && $to_user['id'] != $sid)
{
?>
			<li><span>»&#160;</span><a href="pmsnew.php?mdl=<?php echo $mmodul.'&amp;sid='.$to_user['id'] ?>"><?php echo pun_htmlspecialchars($to_user['username']) ?></a></li>
<?php
}
?>
			<li><span>»&#160;</span><strong><?php echo pun_htmlspecialchars($cur_topic['topic']) ?></strong></li>
		</ul>
		<div class="pagepost"></div>
		<div class="clearer"></div>
	</div>
</div>
<?php

generate_pmsn_menu($pmsn_modul);

/*	<div class="blockform">
*/
?>
	<div class="block">
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
			<p class="postlink actions conr"><?php echo $pmsn_f_cnt ?></p>
		</div>
<?php

require PUN_ROOT.'include/parser.php';

$post_count = 0; // Keep track of post numbers

// Retrieve a list of post IDs, LIMIT is (really) expensive so we only fetch the IDs here then later fetch the remaining data
$result = $db->query('SELECT id FROM '.$db->prefix.'pms_new_posts WHERE topic_id='.$tid.' ORDER BY id LIMIT '.$start_from.','.$pun_user['disp_posts']) or error('Unable to fetch pms_new_posts IDs', __FILE__, __LINE__, $db->error());

$post_ids = array();
while ($row = $db->fetch_row($result)) {
	$post_ids[] = $row[0];
}

$post_view_new = array();

// мод пола, добавлен u.gender
// убран запрос к таблице online
$result = $db->query('SELECT u.gender, u.email, u.title, u.url, u.location, u.signature, u.email_setting, u.num_posts, u.registered, u.admin_note, p.id, p.poster AS username, p.poster_id, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by, p.post_new, g.g_id, g.g_user_title FROM '.$db->prefix.'pms_new_posts AS p LEFT JOIN '.$db->prefix.'users AS u ON u.id=p.poster_id LEFT JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE p.id IN ('.implode(',', $post_ids).') ORDER BY p.id', true) or error('Unable to fetch pms_new_posts info', __FILE__, __LINE__, $db->error());
while ($cur_post = $db->fetch_assoc($result))
{
	$post_count++;
	$user_avatar = '';
	$user_info = array();
	$user_contacts = array();
	$post_actions = array();
	$is_online = '';
	$signature = '';

	if (!$cur_post['g_id'])
	{
		$cur_post['g_id'] = PUN_GUEST;
		// мод пола - Visman
		$cur_post['gender'] = null;
	}

	// мод пола - Visman
	if ($cur_post['gender'] == 1)
		$cur_post['gender'] = 'male';
	else if ($cur_post['gender'] == 2)
		$cur_post['gender'] = 'female';
	else
		$cur_post['gender'] = null;

	if ($pun_user['id'] != $cur_post['poster_id'])
	{
		if ($cur_post['post_new'] == 1)
			$post_view_new[] = $cur_post['id'];

		if ($cur_post['g_id'] != PUN_GUEST && $cur_post['g_id'] != PUN_ADMIN)
			$post_actions[] = '<li class="postreport"><span><a href="pmsnew.php?mdl=blocking&amp;uid='.$cur_post['poster_id'].'&amp;csrf_token='.pmsn_csrf_token($cur_post['poster_id']).'">'.$lang_pmsn['Block'].'</a></span></li>';
	}
	else if ($cur_post['post_new'] == 1 && $newpost)
	{
		if ($pun_user['g_delete_posts'] == '1')
			$post_actions[] = '<li class="postdelete"><span><a href="pmsnew.php?mdl=del&amp;pid='.$cur_post['id'].$sidamp.'">'.$lang_topic['Delete'].'</a></span></li>';
		if ($pun_user['g_edit_posts'] == '1')
			$post_actions[] = '<li class="postedit"><span><a href="pmsnew.php?mdl=edit&amp;pid='.$cur_post['id'].$sidamp.'">'.$lang_topic['Edit'].'</a></span></li>';
	}

	if ($newpost)
		$post_actions[] = '<li class="postquote"><span><a href="pmsnew.php?mdl=post&amp;tid='.$tid.'&amp;qid='.$cur_post['id'].$sidamp.'">'.$lang_topic['Reply'].'</a></span></li>';

	if ($pun_user['g_view_users'] == '1' && $cur_post['g_id'] != PUN_GUEST)
		$username = '<a href="profile.php?id='.$cur_post['poster_id'].'">'.pun_htmlspecialchars($cur_post['username']).'</a>';
	else
		$username = pun_htmlspecialchars($cur_post['username']);

	if ($cur_post['g_id'] == PUN_GUEST)
	{
		$is_online = '&#160;';
	}
	else
	{
		// Format the online indicator
		$is_online = isset($onl_u[$cur_post['poster_id']]) ? '<strong>'.$lang_topic['Online'].'</strong>' : '<span>'.$lang_topic['Offline'].'</span>';

		if ($pun_config['o_avatars'] == '1' && $pun_user['show_avatars'] != '0')
		{
			if (isset($user_avatar_cache[$cur_post['poster_id']]))
				$user_avatar = $user_avatar_cache[$cur_post['poster_id']];
			else
				$user_avatar = $user_avatar_cache[$cur_post['poster_id']] = generate_avatar_markup($cur_post['poster_id']);
		}

		// We only show location, register date, post count and the contact links if "Show user info" is enabled
		if ($pun_config['o_show_user_info'] == '1')
		{
			if ($cur_post['location'] != '')
			{
				if ($pun_config['o_censoring'] == '1')
					$cur_post['location'] = censor_words($cur_post['location']);

				$user_info[] = '<dd><span>'.$lang_topic['From'].' '.pun_htmlspecialchars($cur_post['location']).'</span></dd>';
			}

			$user_info[] = '<dd><span>'.$lang_topic['Registered'].' '.format_time($cur_post['registered'], true).'</span></dd>';

			if ($pun_config['o_show_post_count'] == '1' || $pun_user['is_admmod'])
				$user_info[] = '<dd><span>'.$lang_topic['Posts'].' '.forum_number_format($cur_post['num_posts']).'</span></dd>';

			// Now let's deal with the contact links (Email and URL)
			if ($pun_user['id'] != $cur_post['poster_id'])
			{
				if (($cur_post['email_setting'] == '0' || $pun_user['is_admmod']) && $pun_user['g_send_email'] == '1')
					$user_contacts[] = '<span class="email"><a href="mailto:'.pun_htmlspecialchars($cur_post['email']).'">'.$lang_common['Email'].'</a></span>';
				else if ($cur_post['email_setting'] == '1' && $pun_user['g_send_email'] == '1')
					$user_contacts[] = '<span class="email"><a href="misc.php?email='.$cur_post['poster_id'].'">'.$lang_common['Email'].'</a></span>';
			}

			if ($cur_post['url'] != '')
			{
				if ($pun_config['o_censoring'] == '1')
						$cur_post['url'] = censor_words($cur_post['url']);

				$user_contacts[] = '<span class="website"><a href="'.pun_htmlspecialchars($cur_post['url']).'">'.$lang_topic['Website'].'</a></span>';
			}
		}

		// Do signature parsing/caching
		if ($pun_config['o_signatures'] == '1' && $cur_post['signature'] != '' && $pun_user['show_sig'] != '0')
		{
			if (isset($signature_cache[$cur_post['poster_id']]))
				$signature = $signature_cache[$cur_post['poster_id']];
			else
			{
				$signature = parse_signature($cur_post['signature']);
				$signature_cache[$cur_post['poster_id']] = $signature;
			}
		}
	}

	// Perform the main parsing of the message (BBCode, smilies, censor words etc)
	$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

?>
		<div id="p<?php echo $cur_post['id'] ?>" class="blockpost<?php echo ($post_count % 2 == 0) ? ' roweven' : ' rowodd' ?><?php if ($post_count == 1) echo ' blockpost1'; ?>">
			<h2><span><span class="conr">#<?php echo ($start_from + $post_count) ?></span> <a href="pmsnew.php?mdl=topic&amp;pid=<?php echo $cur_post['id'].'#p'.$cur_post['id'] ?>"><?php echo format_time($cur_post['posted']) ?></a></span></h2>
			<div class="box">
				<div class="inbox">
					<div class="postbody">
						<div class="postleft">
							<dl>
								<dt><strong<?php echo(is_null($cur_post['gender']) ? '' : ' class="gender '.$cur_post['gender'].'"'); ?>><?php echo $username ?></strong></dt>
								<dd class="usertitle"><strong><?php echo get_title($cur_post) ?></strong></dd>
<?php if ($user_avatar != '') echo "\t\t\t\t\t\t\t\t".'<dd class="postavatar">'.$user_avatar.'</dd>'."\n"; ?>
<?php if (count($user_info)) echo "\t\t\t\t\t\t\t\t".implode("\n\t\t\t\t\t\t\t\t", $user_info)."\n"; ?>
<?php if (count($user_contacts)) echo "\t\t\t\t\t\t\t\t".'<dd class="usercontacts">'.implode(' ', $user_contacts).'</dd>'."\n"; ?>
							</dl>
						</div>
						<div class="postright">
							<div class="postmsg">
								<?php echo $cur_post['message']."\n" ?>
<?php if ($cur_post['edited'] != '') echo "\t\t\t\t\t\t\t\t".'<p class="postedit"><em>'.$lang_topic['Last edit'].' '.pun_htmlspecialchars($cur_post['edited_by']).' ('.format_time($cur_post['edited']).')</em></p>'."\n"; ?>
							</div>
<?php if ($cur_post['poster_id'] == $pun_user['id']) echo $psmnwarn; ?>
<?php if ($signature != '') echo "\t\t\t\t\t\t\t".'<div class="postsignature postmsg"><hr />'.$signature.'</div>'."\n"; ?>
						</div>
					</div>
				</div>
				<div class="inbox">
					<div class="postfoot clearb">
						<div class="postfootleft"><p><?php echo $is_online; ?></p></div>
<?php if (count($post_actions)) echo "\t\t\t\t\t\t".'<div class="postfootright">'."\n\t\t\t\t\t\t\t".'<ul>'."\n\t\t\t\t\t\t\t\t".implode("\n\t\t\t\t\t\t\t\t", $post_actions)."\n\t\t\t\t\t\t\t".'</ul>'."\n\t\t\t\t\t\t".'</div>'."\n" ?>
					</div>
				</div>
			</div>
		</div>
<?php

} // while

?>
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
			<p class="postlink actions conr"><?php echo $pmsn_f_cnt ?></p>
		</div>
	</div>
<?php

if ($status)
{
	if (count($post_view_new) > 0 )
	{
		$db->query('UPDATE '.$db->prefix.'pms_new_posts SET post_new=0 WHERE id IN ('.implode(',', $post_view_new).')') or error('Unable to update pms_new_posts', __FILE__, __LINE__, $db->error());

		$result = $db->query('SELECT MIN(id) FROM '.$db->prefix.'pms_new_posts WHERE poster_id!='.$pun_user['id'].' AND topic_id='.$tid.' AND post_new=1') or error('Unable to fetch pms_new_posts info', __FILE__, __LINE__, $db->error());
		$first_new_post_id = $db->result($result);
		if (!$first_new_post_id)
		$query_end .= $query_end2;
	}

	$db->query('UPDATE '.$db->prefix.'pms_new_topics SET '.$query_end.' WHERE id='.$tid) or error('Unable to update pms_new_topics', __FILE__, __LINE__, $db->error());

	if (count($post_view_new) > 0 )
		pmsn_user_update($pun_user['id']);
}

// Display quick post if enabled
if ($quickpost)
{
	$cur_index = 1;

?>

	<div id="quickpost" class="blockform">
		<h2><span><?php echo $lang_topic['Quick post'] ?></span></h2>
		<div class="box">
			<form id="quickpostform" method="post" action="pmsnew.php?mdl=post&amp;tid=<?php echo $tid.$sidamp ?>" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_common['Write message legend'] ?></legend>
						<div class="infldset txtarea">
							<input type="hidden" name="csrf_hash" value="<?php echo $pmsn_csrf_hash ?>" />
							<label><textarea name="req_message" rows="7" cols="75" tabindex="<?php echo $cur_index++ ?>"></textarea></label>
							<ul class="bblinks">
								<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang_common['BBCode'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
								<li><span><a href="help.php#url" onclick="window.open(this.href); return false;"><?php echo $lang_common['url tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_user['g_post_links'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
								<li><span><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang_common['img tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
								<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang_common['Smilies'] ?></a> <?php echo ($pun_config['o_smilies'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							</ul>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="submit" tabindex="<?php echo $cur_index++ ?>" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" /> <input type="submit" name="preview" tabindex="<?php echo $cur_index++ ?>" value="<?php echo $lang_topic['Preview'] ?>" accesskey="p" /></p>
			</form>
		</div>
	</div>
<?php

	require PUN_ROOT.'include/bbcode.inc.php';
}
