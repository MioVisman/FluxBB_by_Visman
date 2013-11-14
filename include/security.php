<?php
/**
 * Copyright (C) 2013 Visman (visman@inbox.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

require PUN_ROOT.'lang/'.$pun_user['language'].'/security.php';


// type 1:register, 2:login, 3:post
function vsecurity_get ($type, $f = false)
{
	global $db, $pun_user, $pun_config, $lang_sec;
	static $block_kolvo;
	
	if (!$pun_user['is_guest'] || $pun_config['o_blocking_time'] == '0')
	  return;

	if ($f)
	{
		$db->query('INSERT INTO '.$db->prefix.'blocking (block_ip, block_log, block_type) VALUES(\''.$db->escape(get_remote_address()).'\', '.time().', '.$type.')') or error('Unable to create blocking', __FILE__, __LINE__, $db->error());
		$block_kolvo++;
	}
	else
	{
		$db->query('DELETE FROM '.$db->prefix.'blocking WHERE block_log < '.(time() - 60 * $pun_config['o_blocking_time'])) or error('Unable to delete from blocking list', __FILE__, __LINE__, $db->error());
	
		if ($type == 3)
			$block_q = ($pun_config['o_blocking_guest'] == '1') ? '' : ' AND block_type=3';
		else
			$block_q = ($pun_config['o_blocking_reglog'] == '1') ? ' AND (block_type=1 OR block_type=2)' : ' AND block_type='.$type;

		$result = $db->query('SELECT block_ip FROM '.$db->prefix.'blocking WHERE block_ip=\''.$db->escape(get_remote_address()).'\''.$block_q) or error('Unable to fetch blocking info', __FILE__, __LINE__, $db->error());
		$block_kolvo = $db->num_rows($result);
	}

	if ($block_kolvo > $pun_config['o_blocking_kolvo'])
	{
		if ($f && ($type == 2 || $pun_config['o_blocking_user'] != '1'))
		{
			$reason = '[MOD] Automatic Lock. Error in '.($type == 1 ? 'register.php' : ($type == 2 ? 'login.php' : 'post.php'))."\n\n".'IP = '.get_remote_address()."\n".$f;
			// Should we use the internal report handling?
			if ($pun_config['o_report_method'] == '0' || $pun_config['o_report_method'] == '2')
				$db->query('INSERT INTO '.$db->prefix.'reports (post_id, topic_id, forum_id, reported_by, created, message) VALUES(0, 0, 0, '.$pun_user['id'].', '.time().', \''.$db->escape($reason).'\')' ) or error('Unable to create report', __FILE__, __LINE__, $db->error());
		}
		message($lang_sec['Limit of errors']);
	}
}