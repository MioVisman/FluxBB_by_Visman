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
if ($tid < 0)
	message($lang_common['Bad request'], false, '404 Not Found');

// Проверка на минимум сообщений
if ($pun_user['g_id'] != PUN_ADMIN && $pun_config['o_pms_min_kolvo'] > $pun_user['num_posts'])
	message(sprintf($lang_pmsn['Min post'], $pun_config['o_pms_min_kolvo']));

$to_user = array();

if ($tid > 0)
{
	if (!in_array($tid, $pmsn_arr_list) && !in_array($tid, $pmsn_arr_save))
		message($lang_common['Bad request'], false, '404 Not Found');
	else
	{
		$result = $db->query('SELECT * FROM '.$db->prefix.'pms_new_topics WHERE id='.$tid) or error('Unable to fetch pmsn topic info', __FILE__, __LINE__, $db->error());
		$cur_topic = $db->fetch_assoc($result);

		if (!$cur_topic)
			message($lang_common['Bad request'], false, '404 Not Found');

		if ($pun_config['o_censoring'] == '1')
				$cur_topic['topic'] = censor_words($cur_topic['topic']);

		if (in_array($tid, $pmsn_arr_list))
			$mbutsubmit = 1;
		else
			$mbutsave = 1;

		if ($pun_user['id'] == $cur_topic['starter_id'])
		{
			if ($cur_topic['topic_st'] < 2 && $cur_topic['topic_to'] > 1)
				message($lang_pmsn['No new post']);

			$to_user['id'] = $cur_topic['to_id'];
			$to_user['username'] = $cur_topic['to_user'];
		}
		else
		{
			if ($cur_topic['topic_to'] < 2 && $cur_topic['topic_st'] > 1)
				message($lang_pmsn['No new post']);

			$to_user['id'] = $cur_topic['starter_id'];
			$to_user['username'] = $cur_topic['starter'];
		}
	}

	if (in_array($tid, $pmsn_arr_list))
		$mmodul = 'list';
	else
		$mmodul = 'save';
}
else
{
	if ($pun_user['g_pm_limit'] != 0 && $pmsn_kol_list >= $pun_user['g_pm_limit'] && $pmsn_kol_save >= $pun_user['g_pm_limit'] )
		message($lang_pmsn['Full folders']);

	if ($pun_user['g_pm_limit'] == 0 || $pmsn_kol_list < $pun_user['g_pm_limit'])
		$mbutsubmit = 1;

	if ($pun_user['g_pm_limit'] == 0 || $pmsn_kol_save < $pun_user['g_pm_limit'])
		$mbutsave = 1;

	$mmodul = 'list';
}

if (!isset($_POST['req_addressee']) && (isset($_GET['uid']) || $sid))
{
	if ($sid)
		$uid = $sid;
	else
		$uid = intval($_GET['uid']);
	if ($uid < 2)
		message($lang_common['Bad request'], false, '404 Not Found');

	$result = $db->query('SELECT u.*, g.* FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON u.group_id=g.g_id WHERE id='.$uid) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
	$cur_user = $db->fetch_assoc($result);

	if (!isset($cur_user['id']))
		message($lang_pmsn['No addressee']);
	else if ($cur_user['id'] == $pun_user['id'])
		message($lang_pmsn['No for itself']);
	if ($pun_user['g_id'] != PUN_ADMIN)
	{
		if ($cur_user['messages_enable'] == 0 || $cur_user['g_pm'] == 0)
			message($lang_pmsn['Off messages']);
		else if ($cur_user['messages_all'] >= $cur_user['g_pm_limit'] && $cur_user['g_pm_limit'] != 0)
			message($lang_pmsn['More maximum']);
	}

	$result = $db->query('SELECT bl_id FROM '.$db->prefix.'pms_new_block WHERE (bl_id='.$pun_user['id'].' AND bl_user_id='.$cur_user['id'].') OR (bl_id='.$cur_user['id'].' AND bl_user_id='.$pun_user['id'].') LIMIT 1') or error('Unable to fetch pms_new_block', __FILE__, __LINE__, $db->error());
	$tmp_bl = $db->result($result);

	if ($tmp_bl == $pun_user['id'])
		message($lang_pmsn['You block addr']);
	else if ($pun_user['g_id'] != PUN_ADMIN && $tmp_bl == $cur_user['id'])
		message($lang_pmsn['Addr block you']);

	$addressee = $cur_user['username'];

	$to_user['id'] = $cur_user['id'];
	$to_user['username'] = $cur_user['username'];

}

// Load the post.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';

// Start with a clean slate
$errors = array();

// Did someone just hit "Submit" or "Preview" or ""Save?
if (isset($_POST['csrf_hash']))
{
	if(!defined('PUN_PMS_NEW_CONFIRM'))
		message($lang_common['Bad referrer']);

	$now = time();

	// Flood protection
	if (!isset($_POST['preview']) && $pun_user['pmsn_last_post'] != '' && ($now - $pun_user['pmsn_last_post']) < $pun_user['g_post_flood'])
		$errors[] = sprintf($lang_post['Flood start'], $pun_user['g_post_flood'], $pun_user['g_post_flood'] - ($now - $pun_user['pmsn_last_post']));

	if ($tid == 0)
	{
		$subject = pun_trim($_POST['req_subject'] ?? '');
		$addressee = pun_trim($_POST['req_addressee'] ?? '');

		if ($subject == '')
			$errors[] = $lang_pmsn['No subject'];
		else if (pun_strlen($subject) > 70)
			$errors[] = $lang_post['Too long subject'];
		else if ($pun_config['p_subject_all_caps'] == '0' && is_all_uppercase($subject) && !$pun_user['is_admmod'])
			$errors[] = $lang_post['All caps subject'];

		$result = $db->query('SELECT u.*, g.* FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON u.group_id=g.g_id WHERE u.username=\''.$db->escape($addressee).'\'') or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
		$cur_addressee = $db->fetch_assoc($result);

		if (empty($cur_addressee['id']) || $cur_addressee['id'] < 2)
			$errors[] = $lang_pmsn['No addressee'];
		else if ($cur_addressee['id'] == $pun_user['id'])
			$errors[] = $lang_pmsn['No for itself'];
		else
		{
			$to_user['id'] = $cur_addressee['id'];
			$to_user['username'] = $cur_addressee['username'];

			if ($pun_user['g_id'] != PUN_ADMIN && !isset($_POST['preview']))
			{
				if (isset($_POST['save']))
				{
					if ($pmsn_kol_save >= $pun_user['g_pm_limit'] && $pun_user['g_pm_limit'] != 0)
						$errors[] = $lang_pmsn['More maximum user'];
				}
				else
				{
					if ($cur_addressee['messages_enable'] == 0 || $cur_addressee['g_pm'] == 0)
						$errors[] = $lang_pmsn['Off messages'];
					else if ($cur_addressee['messages_all'] >= $cur_addressee['g_pm_limit'] && $cur_addressee['g_pm_limit'] > 0)
						$errors[] = $lang_pmsn['More maximum'];
				}
			}
		}
	}
	else if (!isset($_POST['preview']))
	{
		if ($pun_user['id'] == $cur_topic['starter_id'])
			$mid = $cur_topic['to_id'];
		else
			$mid = $cur_topic['starter_id'];

		$result = $db->query('SELECT u.*, g.* FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON u.group_id=g.g_id WHERE u.id='.$mid) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
		$cur_addressee = $db->fetch_assoc($result);

		if (empty($cur_addressee['id']) || $cur_addressee['id'] < 2)
			$errors[] = $lang_pmsn['No addressee'];
		else if ($pun_user['g_id'] != PUN_ADMIN && !isset($_POST['save']) && ($cur_addressee['messages_enable'] == 0 || $cur_addressee['g_pm'] == 0))
			$errors[] = $lang_pmsn['Off messages'];
 	}

	if (empty($errors) && !empty($cur_addressee['id']))
	{
		$result = $db->query('SELECT bl_id FROM '.$db->prefix.'pms_new_block WHERE (bl_id='.$pun_user['id'].' AND bl_user_id='.$cur_addressee['id'].') OR (bl_id='.$cur_addressee['id'].' AND bl_user_id='.$pun_user['id'].') LIMIT 1') or error('Unable to fetch pms_new_block', __FILE__, __LINE__, $db->error());
		$tmp_bl = $db->result($result);

		if ($tmp_bl == $pun_user['id'])
			$errors[] = $lang_pmsn['You block addr'];
		else if ($pun_user['g_id'] != PUN_ADMIN && $tmp_bl == $cur_addressee['id'])
			$errors[] = $lang_pmsn['Addr block you'];
	}

	$message = pun_linebreaks(pun_trim($_POST['req_message'] ?? ''));

	if (strlen($message) > 65535)
		$errors[] = $lang_pmsn['Too long message'];
	else if ($pun_config['p_message_all_caps'] == '0' && is_all_uppercase($message) && !$pun_user['is_admmod'])
		$errors[] = $lang_post['All caps message'];

	// Validate BBCode syntax
	if ($pun_config['p_message_bbcode'] == '1')
	{
		require PUN_ROOT.'include/parser.php';
		$message = preparse_bbcode($message, $errors);
	}

	if ($message == '')
		$errors[] = $lang_post['No message'];

	$hide_smilies = isset($_POST['hide_smilies']) ? '1' : '0';

	// posting
	if (empty($errors) && !isset($_POST['preview']))
	{
		$flag2 = 0;

		if ($tid) // new post
		{
			// создаем новое сообщение
			$db->query('INSERT INTO '.$db->prefix.'pms_new_posts (poster, poster_id, poster_ip, message, hide_smilies, posted, post_new, topic_id) VALUES (\''.$db->escape($pun_user['username']).'\', '.$pun_user['id'].', \''.$db->escape(get_remote_address()).'\', \''.$db->escape($message).'\', '.$hide_smilies.', '.$now.', 1, '.$tid.')') or error('Unable to create pms_new_posts', __FILE__, __LINE__, $db->error());
			$new_pid = $db->insert_id();

			// обновляем тему
			if ($cur_topic['starter_id'] == $pun_user['id'])
			{
				$uaddr = $cur_topic['to_id'];

				if ($cur_topic['topic_to'] == 0)
					$flag0 = 1;
				else
					$flag0 = $cur_topic['topic_to'];

				$db->query('UPDATE '.$db->prefix.'pms_new_topics SET replies=replies+1, last_posted='.$now.', last_poster=0, topic_to='.$flag0.' WHERE id='.$tid) or error('Unable to update pms_new_topics', __FILE__, __LINE__, $db->error());
			}
			else
			{
				$uaddr = $cur_topic['starter_id'];

				if ($cur_topic['topic_st'] == 0)
					$flag0 = 1;
				else
					$flag0 = $cur_topic['topic_st'];

				$db->query('UPDATE '.$db->prefix.'pms_new_topics SET replies=replies+1, last_posted='.$now.', last_poster=1, topic_st='.$flag0.' WHERE id='.$tid) or error('Unable to update pms_new_topics', __FILE__, __LINE__, $db->error());
			}

			// update users
			$db->query('UPDATE '.$db->prefix.'users SET pmsn_last_post='.$now.' WHERE id='.$pun_user['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());

			// обновляем информацию у получателя
			if ($flag0 == 1)
				pmsn_user_update($uaddr, true);
		}
		else // new dialog
		{
			if (isset($_POST['save']))
			{
				$flag1 = 3;
				$flag2 = 2;
				$m_all = $pmsn_kol_list;
			}
			else
			{
				$flag1 = 0;
				$flag2 = 1;
				$m_all = $pmsn_kol_list+1;
			}
			// создаем новую тему
			$db->query('INSERT INTO '.$db->prefix.'pms_new_topics (topic, starter, starter_id, to_user, to_id, replies, last_posted, last_poster, see_st, see_to, topic_st, topic_to) VALUES (\''.$db->escape($subject).'\', \''.$db->escape($pun_user['username']).'\', '.$pun_user['id'].', \''.$db->escape($cur_addressee['username']).'\', '.$cur_addressee['id'].', 0, '.$now.', 0, '.$now.', 0, '.$flag1.', '.$flag2.')') or error('Unable to create pms_new_topics', __FILE__, __LINE__, $db->error());
			$new_tid = $db->insert_id();

			// создаем новое сообщение
			$db->query('INSERT INTO '.$db->prefix.'pms_new_posts (poster, poster_id, poster_ip, message, hide_smilies, posted, post_new, topic_id) VALUES (\''.$db->escape($pun_user['username']).'\', '.$pun_user['id'].', \''.$db->escape(get_remote_address()).'\', \''.$db->escape($message).'\', '.$hide_smilies.', '.$now.', 1, '.$new_tid.')') or error('Unable to create pms_new_posts', __FILE__, __LINE__, $db->error());
			$new_pid = $db->insert_id();

			// update users
			$db->query('UPDATE '.$db->prefix.'users SET messages_new='.$pmsn_kol_new.', messages_all='.$m_all.', pmsn_last_post='.$now.' WHERE id='.$pun_user['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());

			// обновляем информацию у получателя
			if ($flag2 != 2)
				pmsn_user_update($cur_addressee['id'], true);
		}

		if ($cur_addressee['messages_email'] == 1 && isset($mbutsubmit) && $flag2 != 2)
		{
			$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_addressee['language'].'/mail_templates/form_pmsn.tpl'));

			$first_crlf = strpos($mail_tpl, "\n");
			$mail_subject = pun_trim(substr($mail_tpl, 8, $first_crlf-8));
			$mail_message = pun_trim(substr($mail_tpl, $first_crlf));

			if (isset($subject))
				$mail_subject = str_replace('<mail_subject>', $subject, $mail_subject);
			else
				$mail_subject = str_replace('<mail_subject>', $cur_topic['topic'], $mail_subject);
			$mail_message = str_replace('<sender>', $pun_user['username'], $mail_message);
			$mail_message = str_replace('<user>', $cur_addressee['username'], $mail_message);
			$mail_message = str_replace('<board_title>', $pun_config['o_board_title'], $mail_message);
			$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);
			$mail_message = str_replace('<message_url>', get_base_url().'/pmsnew.php'.( $new_pid ? '?mdl=topic&pid='.$new_pid.'#p'.$new_pid : ''), $mail_message);

			require_once PUN_ROOT.'include/email.php';

			pun_mail($cur_addressee['email'], $mail_subject, $mail_message); // , $pun_user['email'], $pun_user['username']);
		}


		redirect('pmsnew.php?mdl=topic'.$sidamp.'&amp;pid='.$new_pid.'#p'.$new_pid, $lang_post['Post redirect']);
	}
}

$required_fields = array('req_addressee' => $lang_pmsn['Addressee'], 'req_subject' => $lang_pmsn['Dialog head'], 'req_message' => $lang_common['Message']);
$focus_element = array('post');

// If a topic ID was specified in the url (it's a reply)
if ($tid)
{
	$action1 = $lang_post['Post a reply'];
	$action0 = $lang_pmsn[$pmsn_modul];
	if (isset($to_user['id']) && $to_user['id'] != $sid)
		$form = '<form id="post" method="post" action="pmsnew.php?mdl=post&amp;tid='.$tid.'" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">'."\n";
	else
		$form = '<form id="post" method="post" action="pmsnew.php?mdl=post&amp;tid='.$tid.$sidamp.'" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">'."\n";

	// If a quote ID was specified in the url
	if (isset($_GET['qid']))
	{
		$qid = intval($_GET['qid']);
		if ($qid < 1)
			message($lang_common['Bad request'], false, '404 Not Found');

		$result = $db->query('SELECT poster, message FROM '.$db->prefix.'pms_new_posts WHERE id='.$qid.' AND topic_id='.$tid) or error('Unable to fetch quote info', __FILE__, __LINE__, $db->error());
		$post_info = $db->fetch_row($result);

		if (!$post_info)
			message($lang_common['Bad request'], false, '404 Not Found');

		list($q_poster, $q_message) = $post_info;

		if ($pun_config['o_censoring'] == '1')
			$q_message = censor_words($q_message);

		$q_message = pun_htmlspecialchars($q_message);

		if ($pun_config['p_message_bbcode'] == '1')
		{
			// If username contains a square bracket, we add "" or '' around it (so we know when it starts and ends)
			if (strpos($q_poster, '[') !== false || strpos($q_poster, ']') !== false)
			{
				if (strpos($q_poster, '\'') !== false)
					$q_poster = '"'.$q_poster.'"';
				else
					$q_poster = '\''.$q_poster.'\'';
			}
			else
			{
				// Get the characters at the start and end of $q_poster
				$ends = substr($q_poster, 0, 1).substr($q_poster, -1, 1);

				// Deal with quoting "Username" or 'Username' (becomes '"Username"' or "'Username'")
				if ($ends == '\'\'')
					$q_poster = '"'.$q_poster.'"';
				else if ($ends == '""')
					$q_poster = '\''.$q_poster.'\'';
			}

			$quote = '[quote='.$q_poster.']'.$q_message.'[/quote]'."\n";
		}
		else
			$quote = '> '.$q_poster.' '.$lang_common['wrote']."\n\n".'> '.$q_message."\n";
	}
	$focus_element[] = 'req_message';
}
else
{
	$action1 = $lang_pmsn['Post new topic'];
	$action0 = $lang_pmsn['New dialog'];
	if (isset($to_user['id']) && $to_user['id'] != $sid)
		$form = '<form id="post" method="post" action="pmsnew.php?mdl=post" onsubmit="return process_form(this)">'."\n";
	else
		$form = '<form id="post" method="post" action="pmsnew.php?mdl=post'.$sidamp.'" onsubmit="return process_form(this)">'."\n";

	if (!isset($addressee))
		$focus_element[] = 'req_addressee';
	else if (!isset($subject))
		$focus_element[] = 'req_subject';
	else
		$focus_element[] = 'req_message';
}

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
if ($tid > 0)
{
?>
				<li><span>»&#160;</span><a href="pmsnew.php?mdl=topic&amp;tid=<?php echo $tid.$sidamp ?>"><?php echo pun_htmlspecialchars($cur_topic['topic']) ?></a></li>
<?php
}
?>
				<li><span>»&#160;</span><strong><?php echo $action0 ?></strong></li>
			</ul>
			<div class="pagepost"></div>
			<div class="clearer"></div>
		</div>
	</div>

<?php

generate_pmsn_menu($pmsn_modul);

// If there are errors, we display them
if (!empty($errors))
{
?>

	<div id="posterror" class="block">
		<h2><span><?php echo $lang_post['Post errors'] ?></span></h2>
		<div class="box">
			<div class="inbox error-info">
				<p><?php echo $lang_post['Post errors info'] ?></p>
				<ul class="error-list">
<?php

	foreach ($errors as $cur_error)
		echo "\t\t\t\t\t".'<li><strong>'.$cur_error.'</strong></li>'."\n";
?>
				</ul>
			</div>
		</div>
	</div>

<?php
}
else if (isset($_POST['preview']))
{
	require_once PUN_ROOT.'include/parser.php';
	$preview_message = parse_message($message, $hide_smilies);
?>

	<div class="block">
		<div id="postpreview" class="blockpost">
			<h2><span><?php echo $lang_post['Post preview'] ?></span></h2>
			<div class="box">
				<div class="inbox">
					<div class="postbody">
						<div class="postright">
							<div class="postmsg">
								<?php echo $preview_message."\n" ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

<?php
}

// форма ввода
$cur_index = 1;

?>

	<div id="postform" class="blockform">
		<h2><span><?php echo $action1 ?></span></h2>
		<div class="box">
			<?php echo $form ?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_common['Write message legend'] ?></legend>
						<div class="infldset txtarea">
							<input type="hidden" name="csrf_hash" value="<?php echo $pmsn_csrf_hash ?>" />
<?php
if ($tid==0)
{
?>
							<label class="conl required"><strong><?php echo $lang_pmsn['Addressee'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="text" name="req_addressee" value="<?php if (isset($addressee)) echo pun_htmlspecialchars($addressee); ?>" size="25" maxlength="25" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
							<div class="clearer"></div>
							<label class="required"><strong><?php echo $lang_pmsn['Dialog head'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input class="longinput" type="text" name="req_subject" value="<?php if (isset($subject)) echo pun_htmlspecialchars($subject); ?>" size="80" maxlength="70" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
<?php
}
?>
							<label class="required"><strong><?php echo $lang_common['Message'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
							<textarea name="req_message" rows="20" cols="95" tabindex="<?php echo $cur_index++ ?>"><?php echo isset($_POST['req_message']) ? pun_htmlspecialchars($message) : (isset($quote) ? $quote : ''); ?></textarea><br /></label>
							<ul class="bblinks">
								<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang_common['BBCode'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
								<li><span><a href="help.php#url" onclick="window.open(this.href); return false;"><?php echo $lang_common['url tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_user['g_post_links'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
								<li><span><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang_common['img tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
								<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang_common['Smilies'] ?></a> <?php echo ($pun_config['o_smilies'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							</ul>
						</div>
					</fieldset>
<?php

$checkboxes = array();
if ($pun_config['o_smilies'] == '1')
	$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'"'.(isset($_POST['hide_smilies']) ? ' checked="checked"' : '').' />'.$lang_post['Hide smilies'].'<br /></label>';
if (!empty($checkboxes))
{
?>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_common['Options'] ?></legend>
						<div class="infldset">
							<div class="rbox">
								<?php echo implode("\n\t\t\t\t\t\t\t\t", $checkboxes)."\n" ?>
							</div>
						</div>
					</fieldset>
<?php
}
?>
				</div>
				<p class="buttons"><?php echo ((isset($mbutsubmit)) ? '<input type="submit" name="submit" value="'.$lang_common['Submit'].'" tabindex="'.($cur_index++).'" accesskey="s" /> ' : ''); ?><?php echo ((isset($mbutsave)) ? ' <input type="submit" name="save" value="'.$lang_pmsn['Save_Later'].'" tabindex="'.($cur_index++).'" accesskey="a" />' : ''); ?><input type="submit" name="preview" value="<?php echo $lang_post['Preview'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="p" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
<?php

require PUN_ROOT.'include/bbcode.inc.php';

// Check to see if the topic review is to be displayed
if ($tid && $pun_config['o_topic_review'] != '0')
{
	require_once PUN_ROOT.'include/parser.php';

	$result = $db->query('SELECT poster, message, hide_smilies, posted FROM '.$db->prefix.'pms_new_posts WHERE topic_id='.$tid.' ORDER BY id DESC LIMIT '.$pun_config['o_topic_review']) or error('Unable to fetch pms topic review', __FILE__, __LINE__, $db->error());

?>
	<div id="postreview">
		<h2><span><?php echo $lang_pmsn['Topic review'] ?></span></h2>
<?php

	// Set background switching on
	$post_count = 0;

	while ($cur_post = $db->fetch_assoc($result))
	{
		$post_count++;

		$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

?>
		<div class="blockpost">
			<div class="box<?php echo ($post_count % 2 == 0) ? ' roweven' : ' rowodd' ?>">
				<div class="inbox">
					<div class="postbody">
						<div class="postleft">
							<dl>
								<dt><strong><?php echo pun_htmlspecialchars($cur_post['poster']) ?></strong></dt>
								<dd><span><?php echo format_time($cur_post['posted']) ?></span></dd>
							</dl>
						</div>
						<div class="postright">
							<div class="postmsg">
								<?php echo $cur_post['message']."\n" ?>
							</div>
						</div>
					</div>
					<div class="clearer"></div>
				</div>
			</div>
		</div>
<?php
	}

?>
	</div>
<?php

}
