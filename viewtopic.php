<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('WITT_ENABLE', 300);
define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view'], false, '403 Forbidden');


$action = isset($_GET['action']) ? $_GET['action'] : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
if ($id < 1 && $pid < 1)
	message($lang_common['Bad request'], false, '404 Not Found');

// Load the viewtopic.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/topic.php';


// If a post ID is specified we determine topic ID and page number so we can redirect to the correct message
if ($pid)
{
	$result = $db->query('SELECT topic_id FROM '.$db->prefix.'posts WHERE id='.$pid) or error('Unable to fetch topic ID', __FILE__, __LINE__, $db->error());
	$id = $db->result($result);

	if (!$id)
		message($lang_common['Bad request'], false, '404 Not Found');

	// Determine on which page the post is located (depending on $forum_user['disp_posts'])
	$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'posts WHERE topic_id='.$id.' AND id<'.$pid) or error('Unable to count previous posts', __FILE__, __LINE__, $db->error());
	$num_posts = $db->result($result) + 1;

	$_GET['p'] = ceil($num_posts / $pun_user['disp_posts']);
}
else
{
	// If action=new, we redirect to the first new post (if any)
	if ($action == 'new')
	{
		if (!$pun_user['is_guest'])
		{
			// We need to check if this topic has been viewed recently by the user
			$tracked_topics = get_tracked_topics();
			$last_viewed = isset($tracked_topics['topics'][$id]) ? $tracked_topics['topics'][$id] : $pun_user['last_visit'];

			$result = $db->query('SELECT MIN(id) FROM '.$db->prefix.'posts WHERE topic_id='.$id.' AND posted>'.$last_viewed) or error('Unable to fetch first new post info', __FILE__, __LINE__, $db->error());
			$first_new_post_id = $db->result($result);

			if ($first_new_post_id)
			{
				header('Location: viewtopic.php?pid='.$first_new_post_id.'#p'.$first_new_post_id);
				exit;
			}
		}

		// If there is no new post, we go to the last post
		$action = 'last';
	}

	// If action=last, we redirect to the last post
	if ($action == 'last')
	{
		$result = $db->query('SELECT MAX(id) FROM '.$db->prefix.'posts WHERE topic_id='.$id) or error('Unable to fetch last post info', __FILE__, __LINE__, $db->error());
		$last_post_id = $db->result($result);

		if ($last_post_id)
		{
			header('Location: viewtopic.php?pid='.$last_post_id.'#p'.$last_post_id);
			exit;
		}
	}
}

require PUN_ROOT.'include/user_agent.php'; // MOD user agent - Visman
require PUN_ROOT.'include/poll.php';

if (!is_null(poll_post('poll_submit')))
{
	poll_vote($id, $pun_user['id']);

	redirect('viewtopic.php?id='.$id.((isset($_GET['p']) && $_GET['p'] > 1) ? '&p='.intval($_GET['p']) : ''), $lang_poll['M0']);
}

// search HL - Visman
$url_shl = '';
if (isset($_GET['search_hl']))
{
	$search_hl = intval($_GET['search_hl']);
	if ($search_hl < 1)
		message($lang_common['Bad request'], false, '404 Not Found');

	$ident = ($pun_user['is_guest']) ? get_remote_address() : $pun_user['username'];

	$result = $db->query('SELECT search_data FROM '.$db->prefix.'search_cache WHERE id='.$search_hl.' AND ident=\''.$db->escape($ident).'\'') or error('Unable to fetch search results', __FILE__, __LINE__, $db->error());
	if ($row = $db->fetch_assoc($result))
	{
		$temp = unserialize($row['search_data']);
		if (isset($temp['array_shl']))
		{
			$string_shl = implode('|', $temp['array_shl']);

			$url_shl = '&amp;search_hl='.$search_hl;
		}

		unset($temp);
	}
	else // запрос устарел или от другого юзера
	{
		if ($id > 0)
		{
			$p = isset($_GET['p']) && $_GET['p'] > 1 ? '&p='.intval($_GET['p']) : '';

			header('Location: viewtopic.php?id='.$id.$p.($pid > 0 ? '#p'.$pid : ''), true, 301);
		}
		else
			header('Location: viewtopic.php?pid='.$pid.'#p'.$pid, true, 301);

		exit;
	}
}
// search HL - Visman

// StickFP - ADD t.stick_fp, - ADD t.poll_type, t.poll_time, t.poll_term, t.poll_kol, - Visman
// Fetch some info about the topic
if (!$pun_user['is_guest'])
	$result = $db->query('SELECT t.stick_fp, t.subject, t.closed, t.num_replies, t.sticky, t.first_post_id, t.poll_type, t.poll_time, t.poll_term, t.poll_kol, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies, s.user_id AS is_subscribed FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id='.$pun_user['id'].') LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$id.' AND t.moved_to IS NULL') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
else
	$result = $db->query('SELECT t.stick_fp, t.subject, t.closed, t.num_replies, t.sticky, t.first_post_id, t.poll_type, t.poll_time, t.poll_term, t.poll_kol, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies, 0 AS is_subscribed FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$id.' AND t.moved_to IS NULL') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());

$cur_topic = $db->fetch_assoc($result);

if (!$cur_topic)
	message($lang_common['Bad request'], false, '404 Not Found');

// MOD subforums - Visman
if (!isset($sf_array_asc[$cur_topic['forum_id']]))
	message($lang_common['Bad request'], false, '404 Not Found');

// MOD Кто в этой теме - Visman
if (defined('WITT_ENABLE'))
{
	$now = time();

	// подготовка массива посещений
	if (empty($pun_user['witt_data']))
	{
		$witt_ar = array();
		$witt_ar[$id] = $now;
		$pun_user['witt_data'] = serialize($witt_ar);
	}
	else
	{
		$witt_ar = unserialize($pun_user['witt_data']);
		$witt_ar[$id] = $now;
		arsort($witt_ar);
		$witt_du = array();
		$i = 0;
		foreach ($witt_ar as $key => $value)
		{
			if ($i > 8 || $value < $now - WITT_ENABLE) break;
			$witt_du[$key] = $value;
			$i++;
		}
		$pun_user['witt_data'] = serialize($witt_du);

		unset($witt_du);
	}

	unset($witt_ar);

	// Отложенное выполнение запроса обновления таблицы online
	witt_query(array('column' => 'witt_data', 'value' => $pun_user['witt_data']));

	// смотрим кто в online
	$witt_us = array(1 => array());
	update_users_online($id, $witt_us);
}
// MOD Кто в этой теме - Visman

// Sort out who the moderators are and if we are currently a moderator (or an admin)
$mods_array = ($cur_topic['moderators'] != '') ? unserialize($cur_topic['moderators']) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;
if ($is_admmod)
	$admin_ids = get_admin_ids();

if ($pun_user['is_bot']) // запретим ботам постить - Visman
	$pun_config['o_quickpost'] = $cur_topic['post_replies'] = $pun_user['g_post_replies'] = $pun_config['o_topic_views'] = '';

// Can we or can we not post replies?
if ($cur_topic['closed'] == '0')
{
	if (($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1' || $is_admmod)
		$post_link = "\t\t\t".'<p class="postlink conr"><a href="post.php?tid='.$id.'">'.$lang_topic['Post reply'].'</a></p>'."\n";
	else
		$post_link = '';
}
else
{
	$post_link = $lang_topic['Topic closed'];

	if ($is_admmod)
		$post_link .= ' / <a href="post.php?tid='.$id.'">'.$lang_topic['Post reply'].'</a>';

	$post_link = "\t\t\t".'<p class="postlink conr">'.$post_link.'</p>'."\n";
}


// Add/update this topic in our list of tracked topics
if (!$pun_user['is_guest'])
{
	$tracked_topics = get_tracked_topics();
	$tracked_topics['topics'][$id] = time();
	set_tracked_topics($tracked_topics);
}


// Determine the post offset (based on $_GET['p'])
$num_pages = ceil(($cur_topic['num_replies'] + 1) / $pun_user['disp_posts']);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
$start_from = $pun_user['disp_posts'] * ($p - 1);

// Generate paging links
$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'viewtopic.php?id='.$id.$url_shl); // search HL - Visman


if ($pun_config['o_censoring'] == '1')
	$cur_topic['subject'] = censor_words($cur_topic['subject']);


$quickpost = false;
if ($pun_config['o_quickpost'] == '1' &&
	($cur_topic['post_replies'] == '1' || ($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1')) &&
	($cur_topic['closed'] == '0' || $is_admmod))
{
	// Load the post.php language file
	require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';

	$required_fields = array('req_message' => $lang_common['Message']);
	if ($pun_user['is_guest'])
	{
		$required_fields['req_username'] = $lang_post['Guest name'];
		if ($pun_config['p_force_guest_email'] == '1')
			$required_fields['req_email'] = $lang_common['Email'];
	}
	$quickpost = true;
}

if (!$pun_user['is_guest'] && $pun_config['o_topic_subscriptions'] == '1')
{
	if ($cur_topic['is_subscribed'])
		// I apologize for the variable naming here. It's a mix of subscription and action I guess :-)
		$subscraction = "\t\t".'<p class="subscribelink clearb"><span>'.$lang_topic['Is subscribed'].' - </span><a id="unsubscribe" href="misc.php?action=unsubscribe&amp;tid='.$id.'&amp;csrf_hash='.csrf_hash('misc.php').'">'.$lang_topic['Unsubscribe'].'</a></p>'."\n";
	else
		$subscraction = "\t\t".'<p class="subscribelink clearb"><a href="misc.php?action=subscribe&amp;tid='.$id.'&amp;csrf_hash='.csrf_hash('misc.php').'">'.$lang_topic['Subscribe'].'</a></p>'."\n";
}
else
	$subscraction = '';

// Add relationship meta tags
$page_head = array();
$page_head['canonical'] = '<link rel="canonical" href="viewtopic.php?id='.$id.($p == 1 ? '' : '&amp;p='.$p).'" title="'.sprintf($lang_common['Page'], $p).'" />';

if ($num_pages > 1)
{
	if ($p > 1)
		$page_head['prev'] = '<link rel="prev" href="viewtopic.php?id='.$id.($p == 2 ? '' : '&amp;p='.($p - 1)).'" title="'.sprintf($lang_common['Page'], $p - 1).'" />';
	if ($p < $num_pages)
		$page_head['next'] = '<link rel="next" href="viewtopic.php?id='.$id.'&amp;p='.($p + 1).'" title="'.sprintf($lang_common['Page'], $p + 1).'" />';
}

if ($pun_config['o_feed_type'] == '1')
	$page_head['feed'] = '<link rel="alternate" type="application/rss+xml" href="extern.php?action=feed&amp;tid='.$id.'&amp;type=rss" title="'.$lang_common['RSS topic feed'].'" />';
else if ($pun_config['o_feed_type'] == '2')
	$page_head['feed'] = '<link rel="alternate" type="application/atom+xml" href="extern.php?action=feed&amp;tid='.$id.'&amp;type=atom" title="'.$lang_common['Atom topic feed'].'" />';

$page_title = [pun_htmlspecialchars($cur_topic['subject'])];
$cur = $cur_topic['forum_id'];
while (true) {
	$page_title[] = pun_htmlspecialchars($sf_array_desc[$cur]['forum_name']);
	if (empty($sf_array_desc[$cur][0])) {
		break;
	}
	$cur = $sf_array_desc[$cur][0];
}
$page_title[] = pun_htmlspecialchars($pun_config['o_board_title']);
$page_title = array_reverse($page_title);

define('PUN_ALLOW_INDEX', 1);
define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
<?php echo sf_crumbs($cur_topic['forum_id']); // MOD subforums - Visman ?>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_topic['forum_id'] ?>"><?php echo pun_htmlspecialchars($cur_topic['forum_name']) ?></a></li>
			<li><span>»&#160;</span><strong><a href="viewtopic.php?id=<?php echo $id.$url_shl // search HL - Visman ?>"><?php echo pun_htmlspecialchars($cur_topic['subject']) ?></a></strong></li>
		</ul>
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
<?php echo $post_link ?>
		</div>
		<div class="clearer"></div>
	</div>
</div>

<?php


require PUN_ROOT.'include/parser.php';

$post_count = 0; // Keep track of post numbers

// Retrieve a list of post IDs, LIMIT is (really) expensive so we only fetch the IDs here then later fetch the remaining data
$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE topic_id='.$id.' ORDER BY id LIMIT '.$start_from.','.$pun_user['disp_posts']) or error('Unable to fetch post IDs', __FILE__, __LINE__, $db->error());

$post_ids = array();
for ($i = 0;$cur_post_id = $db->result($result, $i);$i++)
	$post_ids[] = $cur_post_id;

if (empty($post_ids))
	error('The post table and topic table seem to be out of sync!', __FILE__, __LINE__);

// StickFP - Visman
$stick_fp_flag = ($cur_topic['stick_fp'] != 0 || $cur_topic['poll_type'] > 0);
$stick_fp_start_from = 0;
if ($stick_fp_flag)
	$post_ids[] = $cur_topic['first_post_id'];
// StickFP - Visman

// MOD warnings - Visman
$result = $db->query('SELECT id, message, poster, posted FROM '.$db->prefix.'warnings WHERE id IN ('.implode(',', $post_ids).')', true) or error('Unable to fetch warnings', __FILE__, __LINE__, $db->error());
$warnings = array();
while ($warning = $db->fetch_assoc($result))
	$warnings[$warning['id']] = '<cite>'.format_time($warning['posted']).' '.pun_htmlspecialchars($warning['poster']).' '.$lang_common['wrote'].'</cite>'.parse_message($warning['message'], false).'';
// MOD warnings - Visman

// Poll MOD - Visman
if (in_array($cur_topic['first_post_id'], $post_ids))
	poll_display_topic($id, $pun_user['id'], $p, true);
// Poll MOD - Visman

// мод пола, добавлен u.gender ; мод ограничения времени, добавлен p.edit_post - Visman
// add "g.g_pm, u.messages_enable," - New PMS - "u.warning_all," - Warnings - p.user_agent, - user agent - Visman
// Retrieve the posts (and their respective poster/online status)
$result = $db->query('SELECT u.warning_all, u.gender, u.email, u.title, u.url, u.location, u.signature, u.email_setting, u.num_posts, u.registered, u.admin_note, u.messages_enable, p.id, p.poster AS username, p.poster_id, p.poster_ip, p.poster_email, p.message, p.hide_smilies, p.posted, p.edited, p.edited_by, p.edit_post, p.user_agent, g.g_id, g.g_user_title, g.g_promote_next_group, g.g_pm FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'users AS u ON u.id=p.poster_id INNER JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE p.id IN ('.implode(',', $post_ids).') ORDER BY p.id', true) or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
while ($cur_post = $db->fetch_assoc($result))
{
	// StickFP - Visman
	if ($stick_fp_flag)
	{
		if ($post_count == 0 && $start_from > 0)
		{
			$stick_fp_start_from = $start_from;
			$start_from = 0;
		}
		else if ($start_from == 0 && $stick_fp_start_from > 0)
		{
			$post_count = 0;
			$start_from = $stick_fp_start_from;
?>
<hr />
<?php
		}
	}
	// StickFP - Visman
	$post_count++;
	$user_avatar = '';
	$user_info = array();
	$user_contacts = array();
	$post_actions = array();
	$is_online = '';
	$signature = '';

	// If the poster is a registered user
	if ($cur_post['poster_id'] > 1)
	{
		// мод пола - Visman
		if ($cur_post['gender'] == 1)
			$cur_post['gender'] = 'male';
		else if ($cur_post['gender'] == 2)
			$cur_post['gender'] = 'female';
		else
			$cur_post['gender'] = NULL;

		if ($pun_user['g_view_users'] == '1')
			$username = '<a href="profile.php?id='.$cur_post['poster_id'].'">'.pun_htmlspecialchars($cur_post['username']).'</a>';
		else
			$username = pun_htmlspecialchars($cur_post['username']);

		$user_title = get_title($cur_post);

		// Format the online indicator
		$is_online = (isset($onl_u[$cur_post['poster_id']])) ? '<strong>'.$lang_topic['Online'].'</strong>' : '<span>'.$lang_topic['Offline'].'</span>';

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
			if ((($cur_post['email_setting'] == '0' && !$pun_user['is_guest']) || $pun_user['is_admmod']) && $pun_user['g_send_email'] == '1')
				$user_contacts[] = '<span class="email"><a href="mailto:'.pun_htmlspecialchars($cur_post['email']).'">'.$lang_common['Email'].'</a></span>';
			else if ($cur_post['email_setting'] == '1' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
				$user_contacts[] = '<span class="email"><a href="misc.php?email='.$cur_post['poster_id'].'">'.$lang_common['Email'].'</a></span>';

			if ($cur_post['url'] != '')
			{
				if ($pun_config['o_censoring'] == '1')
						$cur_post['url'] = censor_words($cur_post['url']);

				$user_contacts[] = '<span class="website"><a href="'.pun_htmlspecialchars($cur_post['url']).'" rel="ugc">'.$lang_topic['Website'].'</a></span>';
			}
		}
// New PMS - Visman
		if (!$pun_user['is_guest'] && $pun_config['o_pms_enabled'] == '1' && $pun_user['g_pm'] == 1 && $pun_user['messages_enable'] == 1 && $cur_post['poster_id'] != $pun_user['id'])
			if ($pun_user['g_id'] == PUN_ADMIN || ($cur_post['g_pm'] == 1 && $cur_post['messages_enable'] == 1))
			{
				$user_contacts[] = '<span class="pmsnew"><a href="pmsnew.php?mdl=post&amp;uid='.$cur_post['poster_id'].'">'.$lang_common['PM'].'</a></span>';
			}
// New PMS - Visman

		if ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && $pun_user['g_mod_promote_users'] == '1'))
		{
			if ($cur_post['g_promote_next_group'])
				$user_info[] = '<dd><span><a href="profile.php?action=promote&amp;id='.$cur_post['poster_id'].'&amp;pid='.$cur_post['id'].'&amp;csrf_hash='.csrf_hash().'">'.$lang_topic['Promote user'].'</a></span></dd>';
		}

		if ($pun_user['is_admmod'])
		{
			// IP пользователей видят только админы - MOD warnings - Visman
			$user_info[] = '<dd><span>'.$lang_topic['Warnings'].'<a href="search.php?action=show_user_warn&amp;user_id='.$cur_post['poster_id'].'">&#160;'.$cur_post['warning_all'].'&#160;</a></span></dd>';
			if ($pun_user['g_id'] == PUN_ADMIN)
				$user_info[] = '<dd><span><a href="moderate.php?get_host='.$cur_post['id'].'" title="'.pun_htmlspecialchars($cur_post['poster_ip']).'">'.$lang_topic['IP address logged'].'</a></span></dd>';

			if ($cur_post['admin_note'] != '')
				$user_info[] = '<dd><span>'.$lang_topic['Note'].' <strong>'.pun_htmlspecialchars($cur_post['admin_note']).'</strong></span></dd>';
		}
		// MOD warnings - Visman
		else if ($cur_post['poster_id'] == $pun_user['id'])
		{
			$user_info[] = '<dd><span>'.$lang_topic['Warnings'].'<a href="search.php?action=show_user_warn">&#160;'.$cur_post['warning_all'].'&#160;</a></span></dd>';
		}
	}
	// If the poster is a guest (or a user that has been deleted)
	else
	{
		$username = pun_htmlspecialchars($cur_post['username']);
		$user_title = get_title($cur_post);

		// мод пола - Visman
		$cur_post['gender'] = NULL;

		// IP пользователей видят только админы - Visman
		if ($pun_user['g_id'] == PUN_ADMIN)
			$user_info[] = '<dd><span><a href="moderate.php?get_host='.$cur_post['id'].'" title="'.pun_htmlspecialchars($cur_post['poster_ip']).'">'.$lang_topic['IP address logged'].'</a></span></dd>';

		if ($pun_config['o_show_user_info'] == '1' && $cur_post['poster_email'] != '' && !$pun_user['is_guest'] && $pun_user['g_send_email'] == '1')
			$user_contacts[] = '<span class="email"><a href="mailto:'.pun_htmlspecialchars($cur_post['poster_email']).'">'.$lang_common['Email'].'</a></span>';
	}

	// Generation post action array (quote, edit, delete etc.)
	if (!$is_admmod)
	{
		if (!$pun_user['is_guest'])
			$post_actions[] = '<li class="postreport"><span><a href="misc.php?report='.$cur_post['id'].'">'.$lang_topic['Report'].'</a></span></li>';

		if ($cur_topic['closed'] == '0')
		{
			if ($cur_post['poster_id'] == $pun_user['id'] && ($pun_user['g_deledit_interval'] == 0 || $cur_post['edit_post'] == 1 || time()-$cur_post['posted'] < $pun_user['g_deledit_interval'])) // ограничение времени редактирования - Visman
			{
				if ((($start_from + $post_count) == 1 && $pun_user['g_delete_topics'] == '1') || (($start_from + $post_count) > 1 && $pun_user['g_delete_posts'] == '1'))
					$post_actions[] = '<li class="postdelete"><span><a href="delete.php?id='.$cur_post['id'].'">'.$lang_topic['Delete'].'</a></span></li>';
				if ($pun_user['g_edit_posts'] == '1')
					$post_actions[] = '<li class="postedit"><span><a href="edit.php?id='.$cur_post['id'].'">'.$lang_topic['Edit'].'</a></span></li>';
			}

			if (($cur_topic['post_replies'] == '' && $pun_user['g_post_replies'] == '1') || $cur_topic['post_replies'] == '1')
				$post_actions[] = '<li class="postquote"><span><a href="post.php?tid='.$id.'&amp;qid='.$cur_post['id'].'">'.$lang_topic['Reply'].'</a></span></li>';
		}
	}
	else
	{
		if ($pun_user['g_id'] != PUN_ADMIN) // Visman
			$post_actions[] = '<li class="postreport"><span><a href="misc.php?report='.$cur_post['id'].'">'.$lang_topic['Report'].'</a></span></li>';
		if ($pun_user['g_id'] == PUN_ADMIN || !in_array($cur_post['poster_id'], $admin_ids))
		{
			$post_actions[] = '<li class="postdelete"><span><a href="delete.php?id='.$cur_post['id'].'">'.$lang_topic['Delete'].'</a></span></li>';
			$post_actions[] = '<li class="postedit"><span><a href="edit.php?id='.$cur_post['id'].'">'.$lang_topic['Edit'].'</a></span></li>';
		}
		$post_actions[] = '<li class="postquote"><span><a href="post.php?tid='.$id.'&amp;qid='.$cur_post['id'].'">'.$lang_topic['Reply'].'</a></span></li>';
	}

	// Perform the main parsing of the message (BBCode, smilies, censor words etc)
	$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

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

?>
<div id="p<?php echo $cur_post['id'] ?>" class="blockpost<?php echo ($post_count % 2 == 0) ? ' roweven' : ' rowodd' ?><?php if ($cur_post['id'] == $cur_topic['first_post_id']) echo ' firstpost'; ?><?php if ($post_count == 1) echo ' blockpost1'; ?>">
	<h2><span><span class="conr">#<?php echo ($start_from + $post_count) ?></span> <a href="viewtopic.php?pid=<?php echo $cur_post['id'].'#p'.$cur_post['id'] ?>"><?php echo format_time($cur_post['posted']) ?></a></span></h2>
	<div class="box">
		<div class="inbox">
			<div class="postbody">
				<div class="postleft">
					<dl>
						<dt><strong<?php echo(is_null($cur_post['gender']) ? '' : ' class="gender '.$cur_post['gender'].'"'); ?>><?php echo $username ?></strong></dt>
						<dd class="usertitle"><strong><?php echo $user_title ?></strong></dd>
<?php if ($user_avatar != '') echo "\t\t\t\t\t\t".'<dd class="postavatar">'.$user_avatar.'</dd>'."\n"; ?>
<?php if (count($user_info)) echo "\t\t\t\t\t\t".implode("\n\t\t\t\t\t\t", $user_info)."\n"; ?>
<?php if (count($user_contacts)) echo "\t\t\t\t\t\t".'<dd class="usercontacts">'.implode(' ', $user_contacts).'</dd>'."\n"; ?>
<?php if (!defined('FORUM_UA_OFF')) echo get_useragent_icons($cur_post['user_agent']); ?>
					</dl>
				</div>
				<div class="postright">
					<h3><?php if ($cur_post['id'] != $cur_topic['first_post_id']) echo $lang_topic['Re'].' '; ?><?php echo pun_htmlspecialchars($cur_topic['subject']) ?></h3>
					<div class="postmsg">
						<?php echo $cur_post['message']."\n" ?>
<?php if ($cur_post['id'] == $cur_topic['first_post_id']) poll_display_topic($id, $pun_user['id'], $p); ?>
<?php if ($cur_post['edited'] != '') echo "\t\t\t\t\t\t".'<p class="postedit"><em>'.$lang_topic['Last edit'].' '.pun_htmlspecialchars($cur_post['edited_by']).' ('.format_time($cur_post['edited']).')</em></p>'."\n"; ?>
					</div>
<?php if (isset($warnings[$cur_post['id']])): ?>
					<div class="postwarn">
						<?php echo $warnings[$cur_post['id']]."\n" ?>
					</div>
<?php endif; ?>
<?php if ($signature != '') echo "\t\t\t\t\t".'<div class="postsignature postmsg"><hr />'.$signature.'</div>'."\n"; ?>
				</div>
			</div>
		</div>
		<div class="inbox">
			<div class="postfoot clearb">
				<div class="postfootleft"><?php if ($cur_post['poster_id'] > 1) echo '<p>'.$is_online.'</p>'; ?></div>
<?php if (count($post_actions)) echo "\t\t\t\t".'<div class="postfootright">'."\n\t\t\t\t\t".'<ul>'."\n\t\t\t\t\t\t".implode("\n\t\t\t\t\t\t", $post_actions)."\n\t\t\t\t\t".'</ul>'."\n\t\t\t\t".'</div>'."\n" ?>
			</div>
		</div>
	</div>
</div>

<?php

}

?>
<div class="postlinksb">
	<div class="inbox crumbsplus">
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
<?php echo $post_link ?>
		</div>
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
<?php echo sf_crumbs($cur_topic['forum_id']); // MOD subforums - Visman ?>
			<li><span>»&#160;</span><a href="viewforum.php?id=<?php echo $cur_topic['forum_id'] ?>"><?php echo pun_htmlspecialchars($cur_topic['forum_name']) ?></a></li>
			<li><span>»&#160;</span><strong><a href="viewtopic.php?id=<?php echo $id.$url_shl // search HL - Visman ?>"><?php echo pun_htmlspecialchars($cur_topic['subject']) ?></a></strong></li>
		</ul>
<?php echo $subscraction ?>
		<div class="clearer"></div>
	</div>
</div>

<?php

// *****************************************************************************
// Кто в этой теме - Visman
if (defined('WITT_ENABLE'))
{
?>
<div id="brdstats" class="block">
	<div class="box">
		<div class="inbox">
			<dl class="conl">
<?php

	$num_guests = count($witt_us[1]);
	$num_bots = 0;
	$num_users = count($witt_us) - 1;
	$users = $bots = array();
	$witt_bt = $witt_us[1];
	unset($witt_us[1]);
	unset($witt_us[1]);

	foreach ($witt_us as $online_id => $online_name)
	{
		if ($pun_user['g_view_users'] == '1')
			$users[] = "\n\t\t\t\t".'<dd><a href="profile.php?id='.$online_id.'">'.pun_htmlspecialchars($online_name).'</a>';
		else
			$users[] = "\n\t\t\t\t".'<dd>'.pun_htmlspecialchars($online_name);
	}
	foreach ($witt_bt as $online_name)
	{
		if (strpos($online_name, '[Bot]') !== false)
		{
			++$num_bots;
			$arr_o_name = explode('[Bot]', $online_name);
			if (empty($bots[$arr_o_name[1]]))
				$bots[$arr_o_name[1]] = 1;
			else
				++$bots[$arr_o_name[1]];
		}
	}
	foreach ($bots as $online_name => $online_id)
	{
		$users[] = "\n\t\t\t\t".'<dd>[Bot] '.pun_htmlspecialchars($online_name.($online_id > 1 ? ' ('.$online_id.')' : ''));
	}
	echo "\t\t\t\t".'<dd><span>'.sprintf($lang_topic['Users online'], '<strong>'.forum_number_format($num_users).'</strong>').', '.sprintf($lang_topic['Guests online'], '<strong>'.forum_number_format($num_guests).'</strong>').'</span></dd>'."\n\t\t\t".'</dl>'."\n";;

 	if ($num_users + $num_bots > 0)
		echo "\t\t\t".'<dl id="onlinelist" class="clearb">'.implode(',</dd> ', $users).'</dd>'."\n\t\t\t".'</dl>'."\n";
	else
		echo "\t\t\t".'<div class="clearer"></div>'."\n";

?>
		</div>
	</div>
</div>
<?php
}
// Кто в этой теме - Visman
// *****************************************************************************

// Display quick post if enabled
if ($quickpost)
{

$cur_index = 1;

?>
<div id="quickpost" class="blockform">
	<h2><span><?php echo $lang_topic['Quick post'] ?></span></h2>
	<div class="box">
		<form id="quickpostform" method="post" action="post.php?tid=<?php echo $id ?>" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_common['Write message legend'] ?></legend>
					<div class="infldset txtarea">
						<input type="hidden" name="csrf_hash" value="<?php echo csrf_hash('post.php') ?>" />
						<input type="hidden" name="form_sent" value="1" />
<?php if ($pun_config['o_topic_subscriptions'] == '1' && ($pun_user['auto_notify'] == '1' || $cur_topic['is_subscribed'])): ?>						<input type="hidden" name="subscribe" value="1" />
<?php endif; ?>
<?php

if ($pun_user['is_guest'])
{
	$email_label = ($pun_config['p_force_guest_email'] == '1') ? '<strong>'.$lang_common['Email'].' <span>'.$lang_common['Required'].'</span></strong>' : $lang_common['Email'];
	$email_form_name = ($pun_config['p_force_guest_email'] == '1') ? 'req_email' : 'email';

?>
						<label class="conl required"><strong><?php echo $lang_post['Guest name'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="text" name="req_username" size="25" maxlength="25" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<label class="conl<?php echo ($pun_config['p_force_guest_email'] == '1') ? ' required' : '' ?>"><?php echo $email_label ?><br /><input type="text" name="<?php echo $email_form_name ?>" size="50" maxlength="80" tabindex="<?php echo $cur_index++ ?>" /><br /></label>
						<div class="clearer"></div>
<?php

	echo "\t\t\t\t\t\t".'<label class="required"><strong>'.$lang_common['Message'].' <span>'.$lang_common['Required'].'</span></strong><br />';
}
else
	echo "\t\t\t\t\t\t".'<label>';

?>

						<textarea class="for-emoji-autocomplete" name="req_message" rows="7" cols="75" tabindex="<?php echo $cur_index++ ?>"></textarea></label>
						<ul class="bblinks">
							<li><span><a href="help.php#bbcode" onclick="window.open(this.href); return false;"><?php echo $lang_common['BBCode'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#url" onclick="window.open(this.href); return false;"><?php echo $lang_common['url tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_user['g_post_links'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#img" onclick="window.open(this.href); return false;"><?php echo $lang_common['img tag'] ?></a> <?php echo ($pun_config['p_message_bbcode'] == '1' && $pun_config['p_message_img_tag'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
							<li><span><a href="help.php#smilies" onclick="window.open(this.href); return false;"><?php echo $lang_common['Smilies'] ?></a> <?php echo ($pun_config['o_smilies'] == '1') ? $lang_common['on'] : $lang_common['off']; ?></span></li>
						</ul>
					</div>
				</fieldset>
			</div>
<?php
// START Merge mod
	$checkboxes = array();
	if ($is_admmod && isset($pun_config['o_merge_timeout']) && $pun_config['o_merge_timeout']>0)
		$checkboxes[] = '<label><input type="checkbox" name="merge" value="1" tabindex="'.($cur_index++).'" checked="checked" />'.$lang_post['Merge posts'].'<br /></label>';

	if (!empty($checkboxes))
	{

?>
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_common['Options'] ?></legend>
					<div class="infldset">
						<div class="rbox">
							<?php echo implode("\n\t\t\t\t\t\t\t", $checkboxes)."\n" ?>
						</div>
					</div>
				</fieldset>
			</div>
<?php

	}
// End Merge mod
?>
<?php flux_hook('quickpost_before_submit') ?>
			<p class="buttons"><input type="submit" name="submit" tabindex="<?php echo $cur_index++ ?>" value="<?php echo $lang_common['Submit'] ?>" accesskey="s" /> <input type="submit" name="preview" value="<?php echo $lang_topic['Preview'] ?>" tabindex="<?php echo $cur_index++ ?>" accesskey="p" /></p>
		</form>
	</div>
</div>
<?php
require PUN_ROOT.'include/bbcode.inc.php';

}

// Increment "num_views" for topic
if ($pun_config['o_topic_views'] == '1')
	$db->query('UPDATE '.$db->prefix.'topics SET num_views=num_views+1 WHERE id='.$id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());

$forum_id = $cur_topic['forum_id'];
$footer_style = 'viewtopic';
require PUN_ROOT.'footer.php';
