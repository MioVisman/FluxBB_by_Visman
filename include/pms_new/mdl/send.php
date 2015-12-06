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

$tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;

if ($tid < 1)
	message($lang_common['Bad request'], false, '404 Not Found');

if (!in_array($tid, $pmsn_arr_save))
	message($lang_common['Bad request'], false, '404 Not Found');

$result = $db->query('SELECT * FROM '.$db->prefix.'pms_new_topics WHERE id='.$tid) or error('Unable to fetch pms_new_topics info', __FILE__, __LINE__, $db->error());

if (!$db->num_rows($result))
	message($lang_common['Bad request'], false, '404 Not Found');

$cur_topic = $db->fetch_assoc($result);

if ($pun_user['id'] != $cur_topic['starter_id'] || $cur_topic['see_to'] != 0)
	message($lang_common['Bad request'], false, '404 Not Found');

if ($pun_user['g_pm_limit'] != 0 && $pmsn_kol_list >= $pun_user['g_pm_limit'])
	message($lang_pmsn['More maximum list']);

$result = $db->query('SELECT u.*, g.* FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON u.group_id=g.g_id WHERE id='.$cur_topic['to_id']) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
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

if (isset($_POST['action2']))
{
	if (!defined('PUN_PMS_NEW_CONFIRM'))
		message($lang_common['Bad referrer']);

	$db->query('UPDATE '.$db->prefix.'pms_new_topics SET topic_st=0, topic_to=1 WHERE id='.$tid) or error('Unable to update pms_new_topics', __FILE__, __LINE__, $db->error());
	
	pmsn_user_update($cur_user['id'], true);
	pmsn_user_update($pun_user['id']);

	if ($cur_user['messages_email'] == 1)
	{
		$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_user['language'].'/mail_templates/form_pmsn.tpl'));

		$first_crlf = strpos($mail_tpl, "\n");
		$mail_subject = pun_trim(substr($mail_tpl, 8, $first_crlf-8));
		$mail_message = pun_trim(substr($mail_tpl, $first_crlf));

		$mail_subject = str_replace('<mail_subject>', $cur_topic['topic'], $mail_subject);
		$mail_message = str_replace('<sender>', $pun_user['username'], $mail_message);
		$mail_message = str_replace('<user>', $cur_user['username'], $mail_message);
		$mail_message = str_replace('<board_title>', $pun_config['o_board_title'], $mail_message);
		$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);
		$mail_message = str_replace('<message_url>', get_base_url().'/pmsnew.php?mdl=topic&tid='.$tid, $mail_message);

		require_once PUN_ROOT.'include/email.php';

		pun_mail($cur_user['email'], $mail_subject, $mail_message); // , $pun_user['email'], $pun_user['username']);
	}

	redirect('pmsnew.php?mdl=list'.$sidamp, $lang_pmsn['List redirect']);
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
		<h2><span><?php echo sprintf($lang_pmsn['InfoSend'], pun_htmlspecialchars($cur_topic['topic'])) ?></span></h2>
		<div class="box">
			<form method="post" action="pmsnew.php?mdl=send&amp;tid=<?php echo $tid.$sidamp ?>">
				<div class="inform">
					<input type="hidden" name="csrf_hash" value="<?php echo $pmsn_csrf_hash ?>" />
					<fieldset>
						<legend><?php echo $lang_pmsn['Attention'] ?></legend>
						<div class="infldset">
							<p><?php echo sprintf($lang_pmsn['InfoSendQ'], pun_htmlspecialchars($cur_user['username'])) ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="action2" value="<?php echo $lang_common['Submit'] ?>" /> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
<?php

