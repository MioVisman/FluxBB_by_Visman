<?php

/**
 * Copyright (C) 2013-2018 Visman (mio.visman@yandex.ru)
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (!defined('PUN'))
	exit;

function sf_status_new($cur_forum)
{
	global $new_topics;

	return isset($new_topics[$cur_forum['fid']]);
}

function sf_data_forum($result, &$forum, $fid = 0)
{
	if (!$fid)
		$fid = $forum['fid'];

	if (!isset($forum['status_new']))
		$forum['status_new'] = sf_status_new($forum);

	if (empty($result[$fid])) return;

	foreach ($result[$fid] as $cur)
	{
		$forum['status_new'] = $forum['status_new'] ? true : sf_status_new($cur);

		$forum['num_topics']+= $cur['num_topics'];
		$forum['num_posts']+= $cur['num_posts'];
		if ($cur['last_post'] > $forum['last_post'])
		{
			$forum['last_post'] = $cur['last_post'];
			$forum['last_post_id'] = $cur['last_post_id'];
			$forum['last_poster'] = $cur['last_poster'];
			$forum['last_topic'] = $cur['last_topic'];
		}
		sf_data_forum($result, $forum, $cur['fid']);
	}
}

// Load language file
if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/subforums.php'))
	require PUN_ROOT.'lang/'.$pun_user['language'].'/subforums.php';
else
	require PUN_ROOT.'lang/English/subforums.php';

$sf_cur_forum = (isset($cur_forum) && $id > 0) ? (int)$id : 0;

if (!isset($lang_index))
	require PUN_ROOT.'lang/'.$pun_user['language'].'/index.php';

if (!$pun_user['is_guest'])
{
//	$result = $db->query('SELECT f.id, f.last_post FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.last_post>'.$pun_user['last_visit']) or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());
	$result = $db->query('SELECT f.id, f.last_post FROM '.$db->prefix.'forums AS f WHERE f.last_post>'.$pun_user['last_visit'].' AND f.id IN ('.implode(',', $sf_array_asc[$sf_cur_forum]).')') or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());
	$cur_forum_ = $db->fetch_assoc($result);

	if (is_array($cur_forum_))
	{
		$forums = $new_topics = array();
		if (!isset($tracked_topics))
			$tracked_topics = get_tracked_topics();

		do
		{
			if (!isset($tracked_topics['forums'][$cur_forum_['id']]) || $tracked_topics['forums'][$cur_forum_['id']] < $cur_forum_['last_post'])
				$forums[$cur_forum_['id']] = $cur_forum_['last_post'];
		}
		while ($cur_forum_ = $db->fetch_assoc($result));

		if (!empty($forums))
		{
			if (empty($tracked_topics['topics']))
				$new_topics = $forums;
			else
			{
				$result = $db->query('SELECT forum_id, id, last_post FROM '.$db->prefix.'topics WHERE forum_id IN('.implode(',', array_keys($forums)).') AND last_post>'.$pun_user['last_visit'].' AND moved_to IS NULL') or error('Unable to fetch new topics', __FILE__, __LINE__, $db->error());

				while ($cur_topic = $db->fetch_assoc($result))
				{
					if (!isset($new_topics[$cur_topic['forum_id']]) && (!isset($tracked_topics['forums'][$cur_topic['forum_id']]) || $tracked_topics['forums'][$cur_topic['forum_id']] < $forums[$cur_topic['forum_id']]) && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']))
						$new_topics[$cur_topic['forum_id']] = $forums[$cur_topic['forum_id']];
				}
			}
		}
	}
}

//$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.last_topic, f.parent_forum_id FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());
$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.last_topic, f.parent_forum_id FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id WHERE f.id IN ('.implode(',', $sf_array_asc[$sf_cur_forum]).') ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

// Generate array of forums/subforums for this group
$sf_array = array();
while ($cur_subforum = $db->fetch_assoc($result))
{
	$sf_array[$cur_subforum['parent_forum_id']][$cur_subforum['fid']] = $cur_subforum;
}

if (!empty($sf_cur_forum)) echo "\n".'<div id="punindex" class="subforumlist">'."\n";

if (empty($sf_array[$sf_cur_forum])) $sf_array[$sf_cur_forum] = array();

$cur_category = 0;
$cat_count = 0;
$forum_count = 0;
foreach ($sf_array[$sf_cur_forum] as $cur_subforum)
{
	$moderators = '';

	$sf_list = array();
	if (!empty($sf_array[$cur_subforum['fid']]))
	{
		foreach ($sf_array[$cur_subforum['fid']] as $cur)
		{
			$sf_list[] = '<a class="subforum_name" href="viewforum.php?id='.$cur['fid'].'">'.pun_htmlspecialchars($cur['forum_name']).'</a>';
		}
	}
	sf_data_forum($sf_array, $cur_subforum);

	if ($cur_subforum['cid'] != $cur_category) // A new category since last iteration?
	{
		if ($cur_category != 0)
			echo "\t\t\t".'</tbody>'."\n\t\t\t".'</table>'."\n\t\t".'</div>'."\n\t".'</div>'."\n".'</div>'."\n\n";

		++$cat_count;
		$forum_count = 0;

?>
<div id="idx<?php echo $cat_count ?>" class="blocktable">
	<h2><span><?php echo pun_htmlspecialchars(empty($sf_cur_forum) ? $cur_subforum['cat_name'] : (count($sf_array[$sf_cur_forum]) == 1 ? $lang_subforums['Sub forum'] : $lang_subforums['Sub forums'])) ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table>
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_common['Forum'] ?></th>
					<th class="tc2" scope="col"><?php echo $lang_index['Topics'] ?></th>
					<th class="tc3" scope="col"><?php echo $lang_common['Posts'] ?></th>
					<th class="tcr" scope="col"><?php echo $lang_common['Last post'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

		$cur_category = $cur_subforum['cid'];
	}

	++$forum_count;
	$item_status = ($forum_count % 2 == 0) ? 'roweven' : 'rowodd';
	$forum_field_new = '';
	$icon_type = 'icon';

	if (!empty($cur_subforum['status_new']))
	{
		$item_status .= ' inew';
		$forum_field_new = '<span class="newtext">[ <a href="search.php?action=show_new&amp;fid='.$cur_subforum['fid'].'">'.$lang_common['New posts'].'</a> ]</span>';
		$icon_type = 'icon icon-new';
	}

	// Is this a redirect forum?
	if ($cur_subforum['redirect_url'] != '')
	{
		$forum_field = '<h3><span class="redirtext">'.$lang_index['Link to'].'</span> <a href="'.pun_htmlspecialchars($cur_subforum['redirect_url']).'" title="'.$lang_index['Link to'].' '.pun_htmlspecialchars($cur_subforum['redirect_url']).'">'.pun_htmlspecialchars($cur_subforum['forum_name']).'</a></h3>';
		$num_topics = $num_posts = '-';
		$item_status .= ' iredirect';
		$icon_type = 'icon';
	}
	else
	{
		$forum_field = '<h3><a href="viewforum.php?id='.$cur_subforum['fid'].'">'.pun_htmlspecialchars($cur_subforum['forum_name']).'</a>'.(!empty($forum_field_new) ? ' '.$forum_field_new : '').'</h3>';
		$num_topics = $cur_subforum['num_topics'];
		$num_posts = $cur_subforum['num_posts'];
	}

	if ($cur_subforum['forum_desc'] != '')
		$forum_field .= "\n\t\t\t\t\t\t\t\t".'<div class="forumdesc">'.$cur_subforum['forum_desc'].'</div>';

	// If there is a last_post/last_poster
	if ($cur_subforum['last_post'] != '')
	{
		if ($pun_config['o_censoring'] == '1')
			$cur_subforum['last_topic'] = censor_words($cur_subforum['last_topic']);

		$last_post = '<a href="viewtopic.php?pid='.$cur_subforum['last_post_id'].'#p'.$cur_subforum['last_post_id'].'">'.pun_htmlspecialchars(pun_strlen($cur_subforum['last_topic']) > 30 ? utf8_substr($cur_subforum['last_topic'], 0, 30).'…' : $cur_subforum['last_topic']).'</a> <span class="byuser">'.format_time($cur_subforum['last_post']).' '.$lang_common['by'].' '.pun_htmlspecialchars($cur_subforum['last_poster']).'</span>'; // last topic on index - Visman
	}
	else if ($cur_subforum['redirect_url'] != '')
		$last_post = '- - -';
	else
		$last_post = $lang_common['Never'];

	if ($cur_subforum['moderators'] != '')
	{
		$mods_array = unserialize($cur_subforum['moderators']);
		$moderators = array();

		foreach ($mods_array as $mod_username => $mod_id)
		{
			if ($pun_user['g_view_users'] == '1')
				$moderators[] = '<a href="profile.php?id='.$mod_id.'">'.pun_htmlspecialchars($mod_username).'</a>';
			else
				$moderators[] = pun_htmlspecialchars($mod_username);
		}

		$moderators = "\t\t\t\t\t\t\t\t".'<p class="modlist">(<em>'.$lang_common['Moderated by'].'</em> '.implode(', ', $moderators).')</p>'."\n";
	}

?>
				<tr class="<?php echo $item_status ?>" id="forum<?php echo $cur_subforum['fid'] ?>">
					<td class="tcl">
						<div class="<?php echo $icon_type ?>"><div class="nosize"><?php echo forum_number_format($forum_count) ?></div></div>
						<div class="tclcon">
							<div>
								<?php echo $forum_field."\n".$moderators ?>
								<?php if(!empty($sf_list)) echo '<span class="subforum">'.(count($sf_list) == 1 ? $lang_subforums['Sub forum'] : $lang_subforums['Sub forums']).':</span><br />&#160;--&#160;'.implode('<br />&#160;--&#160;', $sf_list)."\n" ?>
<?php //if(!empty($sf_list)) echo '<span class="subforum">'.(count($sf_list) == 1 ? $lang_subforums['Sub forum'] : $lang_subforums['Sub forums']).':</span> '.implode(', ', $sf_list)."\n" ?>
							</div>
						</div>
					</td>
					<td class="tc2"><?php echo forum_number_format($num_topics) ?></td>
					<td class="tc3"><?php echo forum_number_format($num_posts) ?></td>
					<td class="tcr"><?php echo $last_post ?></td>
				</tr>
<?php

}

// Did we output any categories and forums?
if ($cur_category > 0)
	echo "\t\t\t".'</tbody>'."\n\t\t\t".'</table>'."\n\t\t".'</div>'."\n\t".'</div>'."\n".'</div>'."\n\n";
else
	echo '<div id="idx0" class="block"><div class="box"><div class="inbox"><p>'.$lang_index['Empty board'].'</p></div></div></div>';

if (!empty($sf_cur_forum)) echo "\n".'</div>'."\n";
