<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';


if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view'], false, '403 Forbidden');


// Load the index.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/index.php';

// Get list of forums and topics with new posts since last visit
//if (!$pun_user['is_guest'])
//{
//	$result = $db->query('SELECT t.forum_id, t.id, t.last_post FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.last_post>'.$pun_user['last_visit'].' AND t.moved_to IS NULL') or error('Unable to fetch new topics', __FILE__, __LINE__, $db->error());
//
//	$new_topics = array();
//	while ($cur_topic = $db->fetch_assoc($result))
//		$new_topics[$cur_topic['forum_id']][$cur_topic['id']] = $cur_topic['last_post'];
//
//	$tracked_topics = get_tracked_topics();
//}

if ($pun_config['o_feed_type'] == '1')
	$page_head = array('feed' => '<link rel="alternate" type="application/rss+xml" href="extern.php?action=feed&amp;type=rss" title="'.$lang_common['RSS active topics feed'].'" />');
else if ($pun_config['o_feed_type'] == '2')
	$page_head = array('feed' => '<link rel="alternate" type="application/atom+xml" href="extern.php?action=feed&amp;type=atom" title="'.$lang_common['Atom active topics feed'].'" />');

$forum_actions = array();

// Display a "mark all as read" link
if (!$pun_user['is_guest'])
	$forum_actions[] = '<a href="misc.php?action=markread&amp;csrf_hash='.csrf_hash('misc.php').'">'.$lang_common['Mark all as read'].'</a>';

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']));
define('PUN_ALLOW_INDEX', 1);
define('PUN_ACTIVE_PAGE', 'index');
require PUN_ROOT.'header.php';

require PUN_ROOT.'include/subforums_view.php'; // MOD subforums - Visman

// Collect some statistics from the database
if (file_exists(FORUM_CACHE_DIR.'cache_users_info.php'))
	include FORUM_CACHE_DIR.'cache_users_info.php';

if (!defined('PUN_USERS_INFO_LOADED'))
{
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_users_info_cache();
	require FORUM_CACHE_DIR.'cache_users_info.php';
}

$result = $db->query('SELECT SUM(num_topics), SUM(num_posts) FROM '.$db->prefix.'forums') or error('Unable to fetch topic/post count', __FILE__, __LINE__, $db->error());
list($stats['total_topics'], $stats['total_posts']) = array_map('intval', $db->fetch_row($result));

if ($pun_user['g_view_users'] == '1')
	$stats['newest_user'] = '<a href="profile.php?id='.$stats['last_user']['id'].'">'.pun_htmlspecialchars($stats['last_user']['username']).'</a>';
else
	$stats['newest_user'] = pun_htmlspecialchars($stats['last_user']['username']);

if (!empty($forum_actions))
{

?>
<div class="linksb">
	<div class="inbox crumbsplus">
		<p class="subscribelink clearb"><?php echo implode(' - ', $forum_actions); ?></p>
	</div>
</div>
<?php

}

?>
<div id="brdstats" class="block">
	<h2><span><?php echo $lang_index['Board info'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<dl class="conr">
				<dt><strong><?php echo $lang_index['Board stats'] ?></strong></dt>
				<dd><span><?php printf($lang_index['No of users'], '<strong>'.forum_number_format($stats['total_users']).'</strong>') ?></span></dd>
				<dd><span><?php printf($lang_index['No of topics'], '<strong>'.forum_number_format($stats['total_topics']).'</strong>') ?></span></dd>
				<dd><span><?php printf($lang_index['No of posts'], '<strong>'.forum_number_format($stats['total_posts']).'</strong>') ?></span></dd>
			</dl>
			<dl class="conl">
				<dt><strong><?php echo $lang_index['User info'] ?></strong></dt>
				<dd><span><?php printf($lang_index['Newest user'], $stats['newest_user']) ?></span></dd>
<?php

if ($pun_config['o_users_online'] == '1')
{
	// Fetch users online info and generate strings for output
	$num_guests = count($onl_g);
	$num_bots = 0;
	$num_users = count($onl_u);
	$users = $bots = array();

	foreach ($onl_u as $online_id => $online_name)
	{
		if ($pun_user['g_view_users'] == '1')
			$users[] = "\n\t\t\t\t".'<dd><a href="profile.php?id='.$online_id.'">'.pun_htmlspecialchars($online_name).'</a>';
		else
			$users[] = "\n\t\t\t\t".'<dd>'.pun_htmlspecialchars($online_name);
	}
	foreach ($onl_g as $online_name)
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
	echo "\t\t\t\t".'<dd><span>'.sprintf($lang_index['Users online'], '<strong>'.forum_number_format($num_users).'</strong>').', '.sprintf($lang_index['Guests online'], '<strong>'.forum_number_format($num_guests).'</strong>').'</span></dd>'."\n";

	// max users - Visman
	echo "\t\t\t\t".'<dd><span>'. $lang_index['Most online1'].' ('.$pun_config['st_max_users'].') '.$lang_index['Most online2'].' '.format_time($pun_config['st_max_users_time']).'</span></dd>'."\n\t\t\t".'</dl>'."\n";

	if ($num_users + $num_bots > 0)
		echo "\t\t\t".'<dl id="onlinelist" class="clearb">'."\n\t\t\t\t".'<dt><strong>'.$lang_index['Online'].' </strong></dt>'."\t\t\t\t".implode(',</dd> ', $users).'</dd>'."\n\t\t\t".'</dl>'."\n";
	else
		echo "\t\t\t".'<div class="clearer"></div>'."\n";

}
else
	echo "\t\t\t".'</dl>'."\n\t\t\t".'<div class="clearer"></div>'."\n";


?>
		</div>
	</div>
</div>
<?php

// Mod collapse - Visman
$page_js['f']['collapse'] = 'js/collapse.js';
$page_js['c'][] = 'if (typeof FluxBB === \'undefined\' || !FluxBB) {var FluxBB = {};}
FluxBB.vars = {
	collapse_cookieid: "'.$cookie_name.'_",
	collapse_folder: "'.(file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/exp_down.png') ? 'style/'.$pun_user['style'].'/' : 'img/').'"
};
FluxBB.collapse.init();';
// Mod collapse - Visman

$footer_style = 'index';
require PUN_ROOT.'footer.php';
