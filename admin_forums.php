<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] != PUN_ADMIN)
	message($lang_common['No permission'], false, '403 Forbidden');

// Load the admin_forums.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_forums.php';

// Add a "default" forum
if (isset($_POST['add_forum']))
{
	confirm_referrer('admin_forums.php');

	$add_to_cat = intval($_POST['add_to_cat'] ?? 0);
	if ($add_to_cat < 1)
		message($lang_common['Bad request'], false, '404 Not Found');

	$db->query('INSERT INTO '.$db->prefix.'forums (forum_name, cat_id) VALUES (\''.$db->escape($lang_admin_forums['New forum']).'\', '.$add_to_cat.')') or error('Unable to create forum', __FILE__, __LINE__, $db->error());
	$new_fid = $db->insert_id();

	// Regenerate the quick jump cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_subforums_cache(); // MOD subforums - Visman
	generate_quickjump_cache();

	redirect('admin_forums.php?edit_forum='.$new_fid, $lang_admin_forums['Forum added redirect']);
}

// Delete a forum
else if (isset($_GET['del_forum']))
{
	confirm_referrer('admin_forums.php');

	$forum_id = intval($_GET['del_forum'] ?? 0);
	if ($forum_id < 1)
		message($lang_common['Bad request'], false, '404 Not Found');

	if (isset($_POST['del_forum_comply'])) // Delete a forum with all posts
	{
		@set_time_limit(0);

		// Prune all posts and topics
		prune($forum_id, 1, -1);

		// Locate any "orphaned redirect topics" and delete them
		$result = $db->query('SELECT t1.id FROM '.$db->prefix.'topics AS t1 LEFT JOIN '.$db->prefix.'topics AS t2 ON t1.moved_to=t2.id WHERE t2.id IS NULL AND t1.moved_to IS NOT NULL') or error('Unable to fetch redirect topics', __FILE__, __LINE__, $db->error());
		$orphans = [];

		while ($row = $db->fetch_row($result))
			$orphans[] = $row[0];

		if (!empty($orphans))
			$db->query('DELETE FROM '.$db->prefix.'topics WHERE id IN ('.implode(',', $orphans).')') or error('Unable to delete redirect topics', __FILE__, __LINE__, $db->error());

		// Delete the forum and any forum specific group permissions
		$db->query('DELETE FROM '.$db->prefix.'forums WHERE id='.$forum_id) or error('Unable to delete forum', __FILE__, __LINE__, $db->error());
		$db->query('DELETE FROM '.$db->prefix.'forum_perms WHERE forum_id='.$forum_id) or error('Unable to delete group forum permissions', __FILE__, __LINE__, $db->error());

		// Delete any subscriptions for this forum
		$db->query('DELETE FROM '.$db->prefix.'forum_subscriptions WHERE forum_id='.$forum_id) or error('Unable to delete subscriptions', __FILE__, __LINE__, $db->error());

		// Regenerate the quick jump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_subforums_cache(); // MOD subforums - Visman
		generate_quickjump_cache();

		redirect('admin_forums.php', $lang_admin_forums['Forum deleted redirect']);
	}
	else // If the user hasn't confirmed the delete
	{
		$result = $db->query('SELECT forum_name FROM '.$db->prefix.'forums WHERE id='.$forum_id) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());
		$forum_name = pun_htmlspecialchars($db->result($result));

		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Forums']);
		define('PUN_ACTIVE_PAGE', 'admin');
		require PUN_ROOT.'header.php';

		generate_admin_menu('forums');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_forums['Confirm delete head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_forums.php?del_forum=<?php echo $forum_id ?>">
				<div class="inform">
					<input type="hidden" name="csrf_hash" value="<?php echo csrf_hash() ?>" />
					<fieldset>
						<legend><?php echo $lang_admin_forums['Confirm delete subhead'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_admin_forums['Confirm delete info'], $forum_name) ?></p>
							<p class="warntext"><?php echo $lang_admin_forums['Confirm delete warn'] ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="del_forum_comply" value="<?php echo $lang_admin_common['Delete'] ?>" /><a href="javascript:history.go(-1)"><?php echo $lang_admin_common['Go back'] ?></a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

		require PUN_ROOT.'footer.php';
	}
}

// Update forum positions
else if (isset($_POST['update_positions']))
{
	confirm_referrer('admin_forums.php');

	if (! is_array($_POST['position'] ?? null))
		message($lang_common['Bad request'], false, '404 Not Found');

	foreach ($_POST['position'] as $forum_id => $disp_position)
	{
		$disp_position = pun_trim($disp_position);
		if ($disp_position == '' || preg_match('%[^0-9]%', $disp_position))
			message($lang_admin_forums['Must be integer message']);

		$db->query('UPDATE '.$db->prefix.'forums SET disp_position='.intval($disp_position).' WHERE id='.intval($forum_id)) or error('Unable to update forum', __FILE__, __LINE__, $db->error());
	}

	// Regenerate the quick jump cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_subforums_cache(); // MOD subforums - Visman
	generate_quickjump_cache();

	redirect('admin_forums.php', $lang_admin_forums['Forums updated redirect']);
}

else if (isset($_GET['edit_forum']))
{
	$forum_id = intval($_GET['edit_forum']);
	if ($forum_id < 1)
		message($lang_common['Bad request'], false, '404 Not Found');

	// Update group permissions for $forum_id
	if (isset($_POST['save']))
	{
		confirm_referrer('admin_forums.php');

		// Start with the forum details
		$forum_name = pun_trim($_POST['forum_name'] ?? '');
		$forum_desc = pun_linebreaks(pun_trim($_POST['forum_desc'] ?? ''));
		$cat_id = intval($_POST['cat_id'] ?? 0);
		$sort_by = intval($_POST['sort_by'] ?? 0);
		$redirect_url = isset($_POST['redirect_url']) ? pun_trim($_POST['redirect_url']) : null;

		// MOD subforums - Visman
		$parent_forum_id = $i = intval($_POST['parent_forum'] ?? 0);
		while (isset($sf_array_desc[$i][0]))
			$i = $sf_array_desc[$i][0];

		if ($i > 0 && (!isset($sf_array_tree[0][$i]) || $sf_array_tree[0][$i]['cid'] != $cat_id))
			message($lang_common['Bad request'], false, '404 Not Found');

		if ($forum_name == '')
			message($lang_admin_forums['Must enter name message']);

		if ($cat_id < 1)
			message($lang_common['Bad request'], false, '404 Not Found');

		$forum_desc = $forum_desc != '' ? '\''.$db->escape($forum_desc).'\'' : 'NULL';
		$redirect_url = $redirect_url != '' ? '\''.$db->escape($redirect_url).'\'' : 'NULL';

		$db->query('UPDATE '.$db->prefix.'forums SET forum_name=\''.$db->escape($forum_name).'\', forum_desc='.$forum_desc.', redirect_url='.$redirect_url.', sort_by='.$sort_by.', cat_id='.$cat_id.', parent_forum_id='.$parent_forum_id.' WHERE id='.$forum_id) or error('Unable to update forum', __FILE__, __LINE__, $db->error()); // MOD subforums - Visman

		// Now let's deal with the permissions
		if (isset($_POST['read_forum_old']))
		{
			$result = $db->query('SELECT g_id, g_read_board, g_post_replies, g_post_topics FROM '.$db->prefix.'groups WHERE g_id!='.PUN_ADMIN) or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());
			while ($cur_group = $db->fetch_assoc($result))
			{
				$read_forum_new = $cur_group['g_read_board'] == '1' ? (isset($_POST['read_forum_new'][$cur_group['g_id']]) ? '1' : '0') : intval($_POST['read_forum_old'][$cur_group['g_id']]);
				$post_replies_new = isset($_POST['post_replies_new'][$cur_group['g_id']]) ? '1' : '0';
				$post_topics_new = isset($_POST['post_topics_new'][$cur_group['g_id']]) ? '1' : '0';

				// Check if the new settings differ from the old
				if ($read_forum_new != $_POST['read_forum_old'][$cur_group['g_id']] || $post_replies_new != $_POST['post_replies_old'][$cur_group['g_id']] || $post_topics_new != $_POST['post_topics_old'][$cur_group['g_id']])
				{
					// If the new settings are identical to the default settings for this group, delete its row in forum_perms
					if ($read_forum_new == '1' && $post_replies_new == $cur_group['g_post_replies'] && $post_topics_new == $cur_group['g_post_topics'])
						$db->query('DELETE FROM '.$db->prefix.'forum_perms WHERE group_id='.$cur_group['g_id'].' AND forum_id='.$forum_id) or error('Unable to delete group forum permissions', __FILE__, __LINE__, $db->error());
					else
					{
						// Run an UPDATE and see if it affected a row, if not, INSERT
						$db->query('UPDATE '.$db->prefix.'forum_perms SET read_forum='.$read_forum_new.', post_replies='.$post_replies_new.', post_topics='.$post_topics_new.' WHERE group_id='.$cur_group['g_id'].' AND forum_id='.$forum_id) or error('Unable to insert group forum permissions', __FILE__, __LINE__, $db->error());
						if (!$db->affected_rows())
							$db->query('INSERT INTO '.$db->prefix.'forum_perms (group_id, forum_id, read_forum, post_replies, post_topics) VALUES ('.$cur_group['g_id'].', '.$forum_id.', '.$read_forum_new.', '.$post_replies_new.', '.$post_topics_new.')') or error('Unable to insert group forum permissions', __FILE__, __LINE__, $db->error());
					}
				}
			}
		}

		// Regenerate the quick jump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_subforums_cache(); // MOD subforums - Visman
		generate_quickjump_cache();

		redirect('admin_forums.php', $lang_admin_forums['Forum updated redirect']);
	}
	else if (isset($_POST['revert_perms']))
	{
		confirm_referrer('admin_forums.php');

		$db->query('DELETE FROM '.$db->prefix.'forum_perms WHERE forum_id='.$forum_id) or error('Unable to delete group forum permissions', __FILE__, __LINE__, $db->error());

		// Regenerate the quick jump cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_subforums_cache(); // MOD subforums - Visman
		generate_quickjump_cache();

		redirect('admin_forums.php?edit_forum='.$forum_id, $lang_admin_forums['Perms reverted redirect']);
	}

	// Fetch forum info
	$result = $db->query('SELECT id, forum_name, forum_desc, redirect_url, num_topics, sort_by, cat_id, parent_forum_id FROM '.$db->prefix.'forums WHERE id='.$forum_id) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error()); // MOD subforums - Visman
	$cur_forum = $db->fetch_assoc($result);

	if (!$cur_forum)
		message($lang_common['Bad request'], false, '404 Not Found');


	// MOD subforums - Visman
	if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/subforums.php'))
		require PUN_ROOT.'lang/'.$pun_user['language'].'/subforums.php';
	else
		require PUN_ROOT.'lang/English/subforums.php';

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Forums']);
	define('PUN_ACTIVE_PAGE', 'admin');
	require PUN_ROOT.'header.php';

	generate_admin_menu('forums');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_forums['Edit forum head'] ?></span></h2>
		<div class="box">
			<form id="edit_forum" method="post" action="admin_forums.php?edit_forum=<?php echo $forum_id ?>">
				<p class="submittop"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" tabindex="6" /></p>
				<div class="inform">
					<input type="hidden" name="csrf_hash" value="<?php echo csrf_hash() ?>" />
					<fieldset>
						<legend><?php echo $lang_admin_forums['Edit details subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
								<tr>
									<th scope="row"><?php echo $lang_admin_forums['Forum name label'] ?></th>
									<td><input type="text" name="forum_name" size="35" maxlength="80" value="<?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?>" tabindex="1" /></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_forums['Forum description label'] ?></th>
									<td><textarea name="forum_desc" rows="3" cols="50" tabindex="2"><?php echo pun_htmlspecialchars($cur_forum['forum_desc']) ?></textarea></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_forums['Category label'] ?></th>
									<td>
										<select name="cat_id" tabindex="3">
<?php

	$result = $db->query('SELECT id, cat_name FROM '.$db->prefix.'categories ORDER BY disp_position') or error('Unable to fetch category list', __FILE__, __LINE__, $db->error());
	while ($cur_cat = $db->fetch_assoc($result))
	{
		$selected = $cur_cat['id'] == $cur_forum['cat_id'] ? ' selected="selected"' : '';
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'"'.$selected.'>'.pun_htmlspecialchars($cur_cat['cat_name']).'</option>'."\n";
	}

?>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_forums['Sort by label'] ?></th>
									<td>
										<select name="sort_by" tabindex="4">
											<option value="0"<?php if ($cur_forum['sort_by'] == '0') echo ' selected="selected"' ?>><?php echo $lang_admin_forums['Last post'] ?></option>
											<option value="1"<?php if ($cur_forum['sort_by'] == '1') echo ' selected="selected"' ?>><?php echo $lang_admin_forums['Topic start'] ?></option>
											<option value="2"<?php if ($cur_forum['sort_by'] == '2') echo ' selected="selected"' ?>><?php echo $lang_admin_forums['Subject'] ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_forums['Redirect label'] ?></th>
									<td><?php echo ($cur_forum['num_topics']) ? $lang_admin_forums['Redirect help'] : '<input type="text" name="redirect_url" size="45" maxlength="100" value="'.pun_htmlspecialchars($cur_forum['redirect_url']).'" tabindex="5" />'; ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_subforums['Parent forum'] ?></th>
									<td>
										<select name="parent_forum">
											<option value="0"><?php echo $lang_subforums['No parent forum'] ?></option>
<?php
	// MOD subforums - Visman
	function sf_select_view(int $id, array $cur_forum, string $space = '')
	{
		global $sf_array_tree, $sf_array_asc;

		if (empty($sf_array_tree[$id])) return;
		$cur_category = 0;
		foreach ($sf_array_tree[$id] as $forum_list)
		{
			if ($id == 0 && $forum_list['cid'] != $cur_category)
			{
				if ($cur_category)
					echo "\t\t\t\t\t\t\t\t\t\t\t".'</optgroup>'."\n";

				echo "\t\t\t\t\t\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($forum_list['cat_name']).'">'."\n";
				$cur_category = $forum_list['cid'];
			}

			$selected = $forum_list['fid'] == $cur_forum['parent_forum_id'] ? ' selected="selected"' : '';
			$disabled = $forum_list['fid'] == $cur_forum['id'] || (isset($sf_array_asc[$cur_forum['id']]) && in_array($forum_list['fid'], $sf_array_asc[$cur_forum['id']])) ? ' disabled="disabled"' : '';

			echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$forum_list['fid'].'"'.$selected.$disabled.'>'.$space.pun_htmlspecialchars($forum_list['forum_name']).'</option>'."\n";
			sf_select_view($forum_list['fid'], $cur_forum, $space.'&#160;&#160;');
		}
	}

	sf_select_view(0, $cur_forum);
?>
											</optgroup>
										</select>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_forums['Group permissions subhead'] ?></legend>
						<div class="infldset">
							<p><?php printf($lang_admin_forums['Group permissions info'], '<a href="admin_groups.php">'.$lang_admin_common['User groups'].'</a>') ?></p>
							<table id="forumperms">
							<thead>
								<tr>
									<th class="atcl">&#160;</th>
									<th><?php echo $lang_admin_forums['Read forum label'] ?></th>
									<th><?php echo $lang_admin_forums['Post replies label'] ?></th>
									<th><?php echo $lang_admin_forums['Post topics label'] ?></th>
								</tr>
							</thead>
							<tbody>
<?php

	$result = $db->query('SELECT g.g_id, g.g_title, g.g_read_board, g.g_post_replies, g.g_post_topics, fp.read_forum, fp.post_replies, fp.post_topics FROM '.$db->prefix.'groups AS g LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (g.g_id=fp.group_id AND fp.forum_id='.$forum_id.') WHERE g.g_id!='.PUN_ADMIN.' ORDER BY g.g_id') or error('Unable to fetch group forum permission list', __FILE__, __LINE__, $db->error());

	$cur_index = 7;

	while ($cur_perm = $db->fetch_assoc($result))
	{
		$read_forum = $cur_perm['read_forum'] != '0' ? true : false;
		$post_replies = ($cur_perm['g_post_replies'] == '0' && $cur_perm['post_replies'] == '1') || ($cur_perm['g_post_replies'] == '1' && $cur_perm['post_replies'] != '0') ? true : false;
		$post_topics = ($cur_perm['g_post_topics'] == '0' && $cur_perm['post_topics'] == '1') || ($cur_perm['g_post_topics'] == '1' && $cur_perm['post_topics'] != '0') ? true : false;

		// Determine if the current settings differ from the default or not
		$read_forum_def = $cur_perm['read_forum'] == '0' ? false : true;
		$post_replies_def = ($post_replies && $cur_perm['g_post_replies'] == '0') || (!$post_replies && ($cur_perm['g_post_replies'] == '' || $cur_perm['g_post_replies'] == '1')) ? false : true;
		$post_topics_def = ($post_topics && $cur_perm['g_post_topics'] == '0') || (!$post_topics && ($cur_perm['g_post_topics'] == '' || $cur_perm['g_post_topics'] == '1')) ? false : true;

?>
								<tr>
									<th class="atcl"><?php echo pun_htmlspecialchars($cur_perm['g_title']) ?></th>
									<td<?php if (!$read_forum_def) echo ' class="nodefault"'; ?>>
										<input type="hidden" name="read_forum_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($read_forum) ? '1' : '0'; ?>" />
										<input type="checkbox" name="read_forum_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php echo ($read_forum) ? ' checked="checked"' : ''; ?><?php echo ($cur_perm['g_read_board'] == '0') ? ' disabled="disabled"' : ''; ?> tabindex="<?php echo $cur_index++ ?>" />
									</td>
									<td<?php if (!$post_replies_def && $cur_forum['redirect_url'] == '') echo ' class="nodefault"'; ?>>
										<input type="hidden" name="post_replies_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_replies) ? '1' : '0'; ?>" />
										<input type="checkbox" name="post_replies_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php echo ($post_replies) ? ' checked="checked"' : ''; ?><?php echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> tabindex="<?php echo $cur_index++ ?>" />
									</td>
									<td<?php if (!$post_topics_def && $cur_forum['redirect_url'] == '') echo ' class="nodefault"'; ?>>
										<input type="hidden" name="post_topics_old[<?php echo $cur_perm['g_id'] ?>]" value="<?php echo ($post_topics) ? '1' : '0'; ?>" />
										<input type="checkbox" name="post_topics_new[<?php echo $cur_perm['g_id'] ?>]" value="1"<?php echo ($post_topics) ? ' checked="checked"' : ''; ?><?php echo ($cur_forum['redirect_url'] != '') ? ' disabled="disabled"' : ''; ?> tabindex="<?php echo $cur_index++ ?>" />
									</td>
								</tr>
<?php

	}

?>
							</tbody>
							</table>
							<div class="fsetsubmit"><input type="submit" name="revert_perms" value="<?php echo $lang_admin_forums['Revert to default'] ?>" tabindex="<?php echo $cur_index++ ?>" /></div>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" tabindex="<?php echo $cur_index++ ?>" /></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>

<?php

	require PUN_ROOT.'footer.php';
}

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Forums']);
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('forums');

?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_forums['Add forum head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_forums.php?action=adddel">
<?php

$result = $db->query('SELECT id, cat_name FROM '.$db->prefix.'categories ORDER BY disp_position') or error('Unable to fetch category list', __FILE__, __LINE__, $db->error());
$cur_cat = $db->fetch_assoc($result);

if (is_array($cur_cat))
{

?>
				<div class="inform">
					<input type="hidden" name="csrf_hash" value="<?php echo csrf_hash() ?>" />
					<fieldset>
						<legend><?php echo $lang_admin_forums['Create new subhead'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
								<tr>
									<th scope="row"><?php echo $lang_admin_forums['Add forum label'] ?><div><input type="submit" name="add_forum" value="<?php echo $lang_admin_forums['Add forum'] ?>" tabindex="2" /></div></th>
									<td>
										<select name="add_to_cat" tabindex="1">
<?php

	do
	{
		echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_cat['id'].'">'.pun_htmlspecialchars($cur_cat['cat_name']).'</option>'."\n";
	}
	while ($cur_cat = $db->fetch_assoc($result))

?>
										</select>
										<span><?php echo $lang_admin_forums['Add forum help'] ?></span>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
<?php

}
else
{

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_common['None'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_admin_forums['No categories exist'] ?></p>
						</div>
					</fieldset>
				</div>
<?php

}

?>
			</form>
		</div>
<?php

// Display all the categories and forums
//$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.disp_position FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id ORDER BY c.disp_position, c.id, f.disp_position') or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

//if ($db->num_rows($result) > 0)
if (!empty($sf_array_tree[0])) // MOD subforums - Visman
{

?>
		<h2 class="block2"><span><?php echo $lang_admin_forums['Edit forums head'] ?></span></h2>
		<div class="box">
			<form id="edforum" method="post" action="admin_forums.php?action=edit">
				<p class="submittop">
					<input type="hidden" name="csrf_hash" value="<?php echo csrf_hash() ?>" />
					<input type="submit" name="update_positions" value="<?php echo $lang_admin_forums['Update positions'] ?>" tabindex="3" />
				</p>
<?php

$cur_index = 4;

// MOD subforum - Visman
function sf_list_view(int $id, string $space = '')
{
	global $sf_array_tree, $cur_index, $lang_admin_common, $lang_admin_forums;

	if (empty($sf_array_tree[$id])) return;
	$cur_category = 0;
	foreach ($sf_array_tree[$id] as $cur_forum)
	{
		if ($id == 0 && $cur_forum['cid'] != $cur_category)
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t".'</tbody>'."\n\t\t\t\t\t\t\t".'</table>'."\n\t\t\t\t\t\t".'</div>'."\n\t\t\t\t\t".'</fieldset>'."\n\t\t\t\t".'</div>'."\n";

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_forums['Category subhead'] ?> <?php echo pun_htmlspecialchars($cur_forum['cat_name']) ?></legend>
						<div class="infldset">
							<table>
							<thead>
								<tr>
									<th class="tcl"><?php echo $lang_admin_common['Action'] ?></th>
									<th class="tc2"><?php echo $lang_admin_forums['Position label'] ?></th>
									<th class="tcr"><?php echo $lang_admin_forums['Forum label'] ?></th>
								</tr>
							</thead>
							<tbody>
<?php

			$cur_category = $cur_forum['cid'];
		}

?>
								<tr>
									<td class="tcl"><a href="admin_forums.php?edit_forum=<?php echo $cur_forum['fid'] ?>&amp;csrf_hash=<?php echo csrf_hash() ?>" tabindex="<?php echo $cur_index++ ?>"><?php echo $lang_admin_forums['Edit link'] ?></a> | <a href="admin_forums.php?del_forum=<?php echo $cur_forum['fid'] ?>&amp;csrf_hash=<?php echo csrf_hash() ?>" tabindex="<?php echo $cur_index++ ?>"><?php echo $lang_admin_forums['Delete link'] ?></a></td>
									<td class="tc2"><input type="text" name="position[<?php echo $cur_forum['fid'] ?>]" size="3" maxlength="3" value="<?php echo $cur_forum['disp_position'] ?>" tabindex="<?php echo $cur_index++ ?>" /></td>
									<td class="tcr"><strong><?php echo $space.pun_htmlspecialchars($cur_forum['forum_name']) ?></strong></td>
								</tr>
<?php

		sf_list_view($cur_forum['fid'], $space.'&#160;&#160;&#160;');
	}
}

sf_list_view(0);

?>
							</tbody>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="update_positions" value="<?php echo $lang_admin_forums['Update positions'] ?>" tabindex="<?php echo $cur_index++ ?>" /></p>
			</form>
		</div>
<?php

}

?>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
