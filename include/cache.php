<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


//
// Generate the config cache PHP script
//
function generate_config_cache()
{
	global $db;

	// Get the forum config from the DB
	$result = $db->query('SELECT * FROM '.$db->prefix.'config', true) or error('Unable to fetch forum config', __FILE__, __LINE__, $db->error());

	$output = array();
	while ($cur_config_item = $db->fetch_row($result))
		$output[$cur_config_item[0]] = $cur_config_item[1];

	// Output config as PHP code
	$content = '<?php'."\n\n".'define(\'PUN_CONFIG_LOADED\', 1);'."\n\n".'$pun_config = '.var_export($output, true).';'."\n\n".'?>';
	fluxbb_write_cache_file('cache_config.php', $content);
}


//
// Generate the bans cache PHP script
//
function generate_bans_cache()
{
	global $db;

	// Get the ban list from the DB
	$result = $db->query('SELECT * FROM '.$db->prefix.'bans', true) or error('Unable to fetch ban list', __FILE__, __LINE__, $db->error());

	$output = array();
	while ($cur_ban = $db->fetch_assoc($result))
		$output[] = $cur_ban;

	// Output ban list as PHP code
	$content = '<?php'."\n\n".'define(\'PUN_BANS_LOADED\', 1);'."\n\n".'$pun_bans = '.var_export($output, true).';'."\n\n".'?>';
	fluxbb_write_cache_file('cache_bans.php', $content);
}


//
// Generate quick jump cache PHP scripts
//
function generate_quickjump_cache($group_id = false)
{
	global $db, $lang_common;

	$groups = array();

	// If a group_id was supplied, we generate the quick jump cache for that group only
	if ($group_id !== false)
	{
		// Is this group even allowed to read forums?
		$result = $db->query('SELECT g_read_board FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch user group read permission', __FILE__, __LINE__, $db->error());
		$read_board = $db->result($result);

		$groups[$group_id] = $read_board;
	}
	else
	{
		// A group_id was not supplied, so we generate the quick jump cache for all groups
		$result = $db->query('SELECT g_id, g_read_board FROM '.$db->prefix.'groups') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

		while ($row = $db->fetch_row($result))
			$groups[$row[0]] = $row[1];
	}

	// MOD subforums - Visman
	function generate_quickjump_sf_list($sf_array_tree, $id = 0, $space = '')
	{
		if (empty($sf_array_tree[$id])) return '';

		$output = '';
		if (!$id)
			$output .= "\t\t\t\t".'<form id="qjump" method="get" action="viewforum.php">'."\n\t\t\t\t\t".'<div><label><span><?php echo $lang_common[\'Jump to\'] ?>'.'<br /></span>'."\n\t\t\t\t\t".'<select name="id" onchange="window.location=(\'viewforum.php?id=\'+this.options[this.selectedIndex].value)">'."\n";

		$cur_category = 0;
		foreach ($sf_array_tree[$id] as $cur_forum)
		{
			if ($id == 0 && $cur_forum['cid'] != $cur_category) // A new category since last iteration?
			{
				if ($cur_category)
					$output .= "\t\t\t\t\t\t".'</optgroup>'."\n";

				$output .= "\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($cur_forum['cat_name']).'">'."\n";
				$cur_category = $cur_forum['cid'];
			}

			$redirect_tag = ($cur_forum['redirect_url'] != '') ? ' &gt;&gt;&gt;' : '';
			$output .= "\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'"<?php echo ($forum_id == '.$cur_forum['fid'].') ? \' selected="selected"\' : \'\' ?>>'.$space.pun_htmlspecialchars($cur_forum['forum_name']).$redirect_tag.'</option>'."\n";

			$output .= generate_quickjump_sf_list($sf_array_tree, $cur_forum['fid'], $space.'&#160;&#160;&#160;');
		}

		if (!$id)
			$output .= "\t\t\t\t\t\t".'</optgroup>'."\n\t\t\t\t\t".'</select></label>'."\n\t\t\t\t\t".'<input type="submit" value="<?php echo $lang_common[\'Go\'] ?>" accesskey="g" />'."\n\t\t\t\t\t".'</div>'."\n\t\t\t\t".'</form>'."\n";

		return $output;
	}

	// Loop through the groups in $groups and output the cache for each of them
	foreach ($groups as $group_id => $read_board)
	{
		// Output quick jump as PHP code
		$output = '<?php'."\n\n".'if (!defined(\'PUN\')) exit;'."\n".'define(\'PUN_QJ_LOADED\', 1);'."\n".'$forum_id = isset($forum_id) ? $forum_id : 0;'."\n\n".'?>';

		if ($read_board == '1')
		{
			// Load cached subforums - Visman
			if (file_exists(FORUM_CACHE_DIR.'cache_subforums_'.$group_id.'.php'))
				include FORUM_CACHE_DIR.'cache_subforums_'.$group_id.'.php';
			else
			{
				generate_subforums_cache($group_id);
				require FORUM_CACHE_DIR.'cache_subforums_'.$group_id.'.php';
			}

			$output .= generate_quickjump_sf_list($sf_array_tree);

		}

		fluxbb_write_cache_file('cache_quickjump_'.$group_id.'.php', $output);
	}
}


//
// Generate the censoring cache PHP script
//
function generate_censoring_cache()
{
	global $db;

	$result = $db->query('SELECT search_for, replace_with FROM '.$db->prefix.'censoring') or error('Unable to fetch censoring list', __FILE__, __LINE__, $db->error());

	$search_for = $replace_with = array();
	for ($i = 0; $row = $db->fetch_row($result); $i++)
	{
		list($search_for[$i], $replace_with[$i]) = $row;
		$search_for[$i] = '%(?<=[^\p{L}\p{N}])('.str_replace('\*', '[\p{L}\p{N}]*?', preg_quote($search_for[$i], '%')).')(?=[^\p{L}\p{N}])%iu';
	}

	// Output censored words as PHP code
	$content = '<?php'."\n\n".'define(\'PUN_CENSOR_LOADED\', 1);'."\n\n".'$search_for = '.var_export($search_for, true).';'."\n\n".'$replace_with = '.var_export($replace_with, true).';'."\n\n".'?>';
	fluxbb_write_cache_file('cache_censoring.php', $content);
}


//
// Generate the stopwords cache PHP script
//
function generate_stopwords_cache()
{
	$stopwords = array();

	$d = dir(PUN_ROOT.'lang');
	while (($entry = $d->read()) !== false)
	{
		if ($entry[0] == '.')
			continue;

		if (is_dir(PUN_ROOT.'lang/'.$entry) && file_exists(PUN_ROOT.'lang/'.$entry.'/stopwords.txt'))
			$stopwords = array_merge($stopwords, file(PUN_ROOT.'lang/'.$entry.'/stopwords.txt'));
	}
	$d->close();

	// Tidy up and filter the stopwords
	$stopwords = array_map('pun_trim', $stopwords);
	$stopwords = array_filter($stopwords);

	// Output stopwords as PHP code
	$content = '<?php'."\n\n".'$cache_id = \''.generate_stopwords_cache_id().'\';'."\n".'if ($cache_id != generate_stopwords_cache_id()) return;'."\n\n".'define(\'PUN_STOPWORDS_LOADED\', 1);'."\n\n".'$stopwords = '.var_export($stopwords, true).';'."\n\n".'?>';
	fluxbb_write_cache_file('cache_stopwords.php', $content);
}


//
// Load some information about the latest registered users
//
function generate_users_info_cache()
{
	global $db;

	$stats = array();

	$result = $db->query('SELECT COUNT(id)-1 FROM '.$db->prefix.'users WHERE group_id!='.PUN_UNVERIFIED) or error('Unable to fetch total user count', __FILE__, __LINE__, $db->error());
	$stats['total_users'] = $db->result($result);

	$result = $db->query('SELECT id, username FROM '.$db->prefix.'users WHERE group_id!='.PUN_UNVERIFIED.' ORDER BY registered DESC LIMIT 1') or error('Unable to fetch newest registered user', __FILE__, __LINE__, $db->error());
	$stats['last_user'] = $db->fetch_assoc($result);

	// Output users info as PHP code
	$content = '<?php'."\n\n".'define(\'PUN_USERS_INFO_LOADED\', 1);'."\n\n".'$stats = '.var_export($stats, true).';'."\n\n".'?>';
	fluxbb_write_cache_file('cache_users_info.php', $content);
}


//
// Generate the admins cache PHP script
//
function generate_admins_cache()
{
	global $db;

	// Get admins from the DB
	$result = $db->query('SELECT id FROM '.$db->prefix.'users WHERE group_id='.PUN_ADMIN) or error('Unable to fetch users info', __FILE__, __LINE__, $db->error());

	$output = array();
	while ($row = $db->fetch_row($result))
		$output[] = $row[0];

	// Output admin list as PHP code
	$content = '<?php'."\n\n".'define(\'PUN_ADMINS_LOADED\', 1);'."\n\n".'$pun_admins = '.var_export($output, true).';'."\n\n".'?>';
	fluxbb_write_cache_file('cache_admins.php', $content);
}


//
// Safely write out a cache file.
//
function fluxbb_write_cache_file($file, $content)
{
	$fh = @fopen(FORUM_CACHE_DIR.$file, 'wb');
	if (!$fh)
		error('Unable to write cache file '.pun_htmlspecialchars($file).' to cache directory. Please make sure PHP has write access to the directory \''.pun_htmlspecialchars(FORUM_CACHE_DIR).'\'', __FILE__, __LINE__);

	flock($fh, LOCK_EX);
	ftruncate($fh, 0);

	fwrite($fh, $content);

	flock($fh, LOCK_UN);
	fclose($fh);

	fluxbb_invalidate_cached_file(FORUM_CACHE_DIR.$file);
}


//
// Delete all feed caches
//
function clear_feed_cache()
{
	$d = dir(FORUM_CACHE_DIR);
	while (($entry = $d->read()) !== false)
	{
		if (substr($entry, 0, 10) == 'cache_feed' && substr($entry, -4) == '.php')
		{
			@unlink(FORUM_CACHE_DIR.$entry);
			fluxbb_invalidate_cached_file(FORUM_CACHE_DIR.$entry);
		}
	}
	$d->close();
}


//
// Generate smiley cache PHP array
//
function generate_smiley_cache()
{
	global $db;

	$str = '<?php'."\n".'$smilies = array('."\n";

	$result = $db->query('SELECT image, text FROM '.$db->prefix.'smilies ORDER BY disp_position') or error('Unable to retrieve smilies', __FILE__, __LINE__, $db->error());
	while ($s = $db->fetch_assoc($result))
	{
		$str .= "'".addslashes(pun_htmlspecialchars($s['text']))."' => '".$s['image']."',"."\n";
	}

	$str .= ');'."\n".'?>';

	fluxbb_write_cache_file('cache_smilies.php', $str);
}


//
// Generate the subforums cache - Visman
//
function generate_subforums_desc(&$list, $tree, $node = 0)
{
	if (!empty($tree[$node]))
	{
		foreach ($tree[$node] as $forum_id => $forum)
		{
			$list[$forum_id] = $node ? array_merge(array($node), $list[$node]) : array();
			$list[$forum_id]['forum_name'] = $forum['forum_name'];
			generate_subforums_desc($list, $tree, $forum_id);
		}
	}
}

function generate_subforums_asc(&$list, $tree, $node = array(0))
{
	$list[$node[0]][] = $node[0];

	if (empty($tree[$node[0]])) return;
	foreach ($tree[$node[0]] as $forum_id => $forum)
	{
		$temp = array($forum_id);
		foreach ($node as $i)
		{
			$list[$i][] = $forum_id;
			$temp[] = $i;
		}
		generate_subforums_asc($list, $tree, $temp);
	}
}

function generate_subforums_cache($group_id = false)
{
	global $db;

	$groups = array();

	// If a group_id was supplied, we generate the quick jump cache for that group only
	if ($group_id !== false)
	{
		// Is this group even allowed to read forums?
		$result = $db->query('SELECT g_read_board FROM '.$db->prefix.'groups WHERE g_id='.$group_id) or error('Unable to fetch user group read permission', __FILE__, __LINE__, $db->error());
		$read_board = $db->result($result);

		$groups[$group_id] = $read_board;
	}
	else
	{
		// A group_id was not supplied, so we generate the quick jump cache for all groups
		$result = $db->query('SELECT g_id, g_read_board FROM '.$db->prefix.'groups') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

		while ($row = $db->fetch_row($result))
			$groups[$row[0]] = $row[1];
	}

	// Loop through the groups in $groups and output the cache for each of them
	foreach ($groups as $group_id => $read_board)
	{
		$str = '<?php'."\n\n";

		if ($read_board == '1')
		{
			$tree = $desc = $asc = array();

			$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url, f.parent_forum_id, f.disp_position FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$group_id.') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

			// Generate array of forums/subforums for this group
			while ($f = $db->fetch_assoc($result))
				$tree[$f['parent_forum_id']][$f['fid']] = $f;

			generate_subforums_desc($desc, $tree);
			generate_subforums_asc($asc, $tree);
			$str.= '$sf_array_tree = '.var_export($tree, true).';'."\n\n".'$sf_array_desc = '.var_export($desc, true).';'."\n\n".'$sf_array_asc = '.var_export($asc, true).';';
		}
		else
			$str.= '$sf_array_tree = $sf_array_desc = $sf_array_asc = array();';

		fluxbb_write_cache_file('cache_subforums_'.$group_id.'.php', $str."\n\n".'?>');
	}
}


//
// Invalidate updated php files that are cached by an opcache
//
function fluxbb_invalidate_cached_file($file)
{
	if (function_exists('opcache_invalidate'))
		opcache_invalidate($file, true);
	elseif (function_exists('apc_delete_file'))
		@apc_delete_file($file);
}


define('FORUM_CACHE_FUNCTIONS_LOADED', true);
