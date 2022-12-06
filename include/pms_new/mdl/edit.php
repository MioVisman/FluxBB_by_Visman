<?php

/**
 * Copyright (C) 2010-2018 Visman (mio.visman@yandex.ru)
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (!defined('PUN') || !defined('PUN_PMS_NEW'))
	exit;

define('PUN_PMS_LOADED', 1);

$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
if ($pid < 1)
	message($lang_common['Bad request'], false, '404 Not Found');

$result = $db->query('SELECT t.id AS tid, t.topic, t.starter, t.starter_id, t.to_user, t.to_id, t.see_to, t.topic_st, t.topic_to, p.poster, p.poster_id, p.message, p.hide_smilies, p.post_new FROM '.$db->prefix.'pms_new_posts AS p INNER JOIN '.$db->prefix.'pms_new_topics AS t ON t.id=p.topic_id WHERE p.id='.$pid) or error('Unable to fetch pms_new_posts info', __FILE__, __LINE__, $db->error());
$cur_post = $db->fetch_assoc($result);

if (!$cur_post)
	message($lang_common['Bad request'], false, '404 Not Found');

if ($cur_post['poster_id'] != $pun_user['id'])
	message($lang_common['No permission'], false, '403 Forbidden');

if ($cur_post['post_new'] != 1)
	message($lang_pmsn['No edit post']);

if (in_array($cur_post['tid'], $pmsn_arr_new))
	$mmodul = 'new';
else if (in_array($cur_post['tid'], $pmsn_arr_list))
	$mmodul = 'list';
else if (in_array($cur_post['tid'], $pmsn_arr_save))
	$mmodul = 'save';
else
	message($lang_common['Bad request'], false, '404 Not Found');


if ($pun_config['o_censoring'] == '1')
{
	$cur_post['topic'] = censor_words($cur_post['topic']);
	$cur_post['message'] = censor_words($cur_post['message']);
}

// 	if (($cur_topic['topic_st'] < 2 && $cur_topic['topic_to'] < 2) || ($pun_user['id'] == $cur_topic['starter_id'] && $cur_topic['see_to'] == 0))

$to_user = array();
if ($pun_user['id'] == $cur_post['starter_id'])
{
	$to_user['id'] = $cur_post['to_id'];
	$to_user['username'] = $cur_post['to_user'];
}
else
{
	$to_user['id'] = $cur_post['starter_id'];
	$to_user['username'] = $cur_post['starter'];
}

// Load the post.php/edit.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';

// Start with a clean slate
$errors = array();

if (isset($_POST['csrf_hash']))
{

	// Clean up message from POST
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

	// Did everything go according to plan?
	if (empty($errors) && !isset($_POST['preview']))
	{
		// Update the post
		$db->query('UPDATE '.$db->prefix.'pms_new_posts SET message=\''.$db->escape($message).'\', hide_smilies='.$hide_smilies.', edited='.time().', edited_by=\''.$db->escape($pun_user['username']).'\' WHERE id='.$pid) or error('Unable to update pms_new_posts', __FILE__, __LINE__, $db->error());

		redirect('pmsnew.php?mdl=topic'.$sidamp.'&amp;pid='.$pid.'#p'.$pid, $lang_post['Edit redirect']);
	}
}

$required_fields = array('req_message' => $lang_common['Message']);
$focus_element = array('edit','req_message');
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
				<li><span>»&#160;</span><a href="pmsnew.php?mdl=topic&amp;tid=<?php echo $cur_post['tid'].$sidamp ?>"><?php echo pun_htmlspecialchars($cur_post['topic']) ?></a></li>
				<li><span>»&#160;</span><strong><?php echo $lang_pmsn['edit'] ?></strong></li>
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

	<div id="editform" class="blockform">
		<h2><span><?php echo $lang_post['Edit post'] ?></span></h2>
		<div class="box">
			<form id="edit" method="post" action="pmsnew.php?mdl=edit&amp;pid=<?php echo $pid.$sidamp ?>&amp;action=edit" onsubmit="return process_form(this)">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_post['Edit post legend'] ?></legend>
						<div class="infldset txtarea">
							<input type="hidden" name="csrf_hash" value="<?php echo $pmsn_csrf_hash ?>" />
							<label class="required"><strong><?php echo $lang_common['Message'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br />
							<textarea name="req_message" rows="20" cols="95" tabindex="<?php echo $cur_index++ ?>"><?php echo pun_htmlspecialchars(isset($_POST['req_message']) ? $message : $cur_post['message']) ?></textarea><br /></label>
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
	$checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.($cur_index++).'"'.((isset($hide_smilies) && $hide_smilies || !isset($hide_smilies) && $cur_post['hide_smilies']) ? ' checked="checked"' : '').' />'.$lang_post['Hide smilies'].'<br /></label>';
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
				<p class="buttons"><input type="submit" name="submit" value="<?php echo $lang_common['Submit'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="s" /> <input type="submit" name="preview" value="<?php echo $lang_post['Preview'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="p" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
<?php

require PUN_ROOT.'include/bbcode.inc.php';
