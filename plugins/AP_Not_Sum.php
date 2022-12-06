<?php

/**
 * Copyright (C) 2011-2022 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_URL', pun_htmlspecialchars('admin_loader.php?plugin='.$plugin));

// Load the language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_forums.php';
if (file_exists(PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_not_sum.php'))
	require PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_not_sum.php';
else
	require PUN_ROOT.'lang/English/admin_plugin_not_sum.php';

// If the "Show text" button was clicked
if (isset($_POST['show_text']))
{
	$result = $db->query('SELECT id FROM '.$db->prefix.'forums ORDER BY id') or error('Unable to fetch forums', __FILE__, __LINE__, $db->error());

	while ($cur_forum = $db->fetch_assoc($result))
	{
		$nosu = isset($_POST['no_sum_mess'][$cur_forum['id']]) ? '1' : '0';
		$db->query('UPDATE '.$db->prefix.'forums SET no_sum_mess='.$nosu.' WHERE id='.$cur_forum['id']) or error('Unable to update forums', __FILE__, __LINE__, $db->error());
	}

	// Synchronize user post counts
	$db->query('CREATE TEMPORARY TABLE IF NOT EXISTS '.$db->prefix.'post_counts SELECT poster_id, COUNT(*) AS new_num FROM '.$db->prefix.'posts AS p, '.$db->prefix.'topics AS t, '.$db->prefix.'forums AS f WHERE f.no_sum_mess=0 AND f.id=t.forum_id AND p.topic_id=t.id GROUP BY p.poster_id') or error('Creating temporary table failed', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'users SET num_posts=0') or error('Could not reset post counts', __FILE__, __LINE__, $db->error()); // Zero posts
	$db->query('UPDATE '.$db->prefix.'users, '.$db->prefix.'post_counts SET num_posts=new_num WHERE id=poster_id') or error('Could not update post counts', __FILE__, __LINE__, $db->error());

	redirect(PLUGIN_URL, $lang_admin_forums['Forums updated redirect']);
}
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="plugin blockform">
		<h2><span><?php echo $lang_admin_plugin_not_sum['Plugin title'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_admin_plugin_not_sum['Explanation 1'] ?></p>
			</div>
		</div>

<?php

// Display all the categories and forums
$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.no_sum_mess FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id ORDER BY c.disp_position, c.id, f.disp_position') or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());
$cur_forum = $db->fetch_assoc($result);

if (is_array($cur_forum))
{

?>
		<h2 class="block2"><span><?php echo $lang_admin_forums['Edit forums head'] ?></span></h2>
		<div class="box">
			<form id="edforum" method="post" action="<?php echo PLUGIN_URL ?>">
				<input type="hidden" name="csrf_hash" value="<?php echo csrf_hash() ?>" />
				<p class="submittop"><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_not_sum['Show text button'] ?>" tabindex="1" /></p>
<?php

$tabindex = 2;

$cur_category = 0;
$vcsrf_hash = csrf_hash();
do
{
	if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
	{
		if ($cur_category != 0)
			echo "\t\t\t\t\t\t\t".'</tbody>'."\n\t\t\t\t\t\t\t".'</table>'."\n\t\t\t\t\t\t".'</div>'."\n\t\t\t\t\t".'</fieldset>'."\n\t\t\t\t".'</div>'."\n";

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_forums['Category subhead'] ?> <?php echo pun_htmlspecialchars($cur_forum['cat_name']) ?></legend>
						<div class="infldset">
							<table>
							<thead>
								<tr>
									<th class="tcl"><?php echo $lang_admin_plugin_not_sum['Not Sum'] ?></th>
									<th class="tcr"><?php echo $lang_admin_forums['Forum label'] ?></th>
								</tr>
							</thead>
							<tbody>
<?php

		$cur_category = $cur_forum['cid'];
	}

?>
								<tr>
									<td class="tcl"><input type="checkbox" name="no_sum_mess[<?php echo $cur_forum['fid'] ?>]" value="1" tabindex="<?php echo ($tabindex++) ?>"<?php echo ($cur_forum['no_sum_mess'] == 1 ? ' checked="checked"' : '') ?> /></td>
									<td class="tcr"><strong><?php echo pun_htmlspecialchars($cur_forum['forum_name']) ?></strong></td>
								</tr>
<?php

}
while ($cur_forum = $db->fetch_assoc($result))

?>
							</tbody>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_not_sum['Show text button'] ?>" tabindex="<?php echo $tabindex ?>" /></p>
			</form>
		</div>
<?php

}

?>

	</div>
<?php
}
