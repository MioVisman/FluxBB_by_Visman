<?php

/**
 * Copyright (C) 2011-2015 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_VERSION', '1.0.6');
define('PLUGIN_URL', pun_htmlspecialchars('admin_loader.php?plugin='.$plugin));

// Load language file
if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/admin_plugin_timelimit.php'))
	require PUN_ROOT.'lang/'.$pun_user['language'].'/admin_plugin_timelimit.php';
else
	require PUN_ROOT.'lang/English/admin_plugin_timelimit.php';

// If the "Show text" button was clicked
if (isset($_POST['show_text']))
{
	$g_order = $_POST['g_order'] ?? null;
	$g_order = is_array($g_order) ? array_map('pun_trim', $g_order) : [];

	$result = $db->query('SELECT g_id, g_title, g_deledit_interval FROM '.$db->prefix.'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

	while ($cur_group = $db->fetch_assoc($result)) {
		if ($cur_group['g_id'] != PUN_ADMIN && $cur_group['g_id'] != PUN_GUEST) {
			$g_time = intval($g_order[$cur_group['g_id']] ?? 0);
			$g_time = max(0, $g_time);

			$db->query('UPDATE '.$db->prefix.'groups SET g_deledit_interval='.$g_time.' WHERE g_id='.$cur_group['g_id']) or error('Unable to update user group list', __FILE__, __LINE__, $db->error());
		}
	}

	redirect(PLUGIN_URL, $lang_admin_plugin_timelimit['Plugin redirect']);
}
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

?>
	<div class="plugin blockform">
		<h2><span><?php echo $lang_admin_plugin_timelimit['Plugin title'].' v'.PLUGIN_VERSION ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_admin_plugin_timelimit['Explanation 1'] ?></p>
				<p><?php echo $lang_admin_plugin_timelimit['Explanation 2'] ?></p>
			</div>
		</div>

		<h2 class="block2"><span><?php echo $lang_admin_plugin_timelimit['Form title'] ?></span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo PLUGIN_URL ?>">
				<p class="submittop"><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_timelimit['Show text button'] ?>" tabindex="1" /></p>
				<div class="inform">
					<fieldset>
						<div class="infldset">
							<table class="aligntop">
							<thead>
								<tr>
									<th class="tcl" scope="col"><?php echo $lang_admin_plugin_timelimit['Group'] ?></th>
									<th scope="col"><?php echo $lang_admin_plugin_timelimit['Time'] ?></th>
								</tr>
							</thead>
							<tbody>
<?php

	$tabindex = 2;
	$result = $db->query('SELECT g_id, g_title, g_deledit_interval FROM '.$db->prefix.'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

	while ($cur_group = $db->fetch_assoc($result)) {
		if ($cur_group['g_id'] != PUN_ADMIN && $cur_group['g_id'] != PUN_GUEST) {

?>
								<tr>
									<th class="tcl" scope="col"><?php echo pun_htmlspecialchars($cur_group['g_title']) ?></th>
									<td><input type="text" name="g_order[<?php echo $cur_group['g_id'] ?>]" value="<?php echo $cur_group['g_deledit_interval'] ?>" tabindex="<?php echo ($tabindex++) ?>" size="10" maxlength="10" /></td>
								</tr>
<?php

		}
	}

?>
							</tbody>
							</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_timelimit['Show text button'] ?>" tabindex="<?php echo ($tabindex++) ?>" /></p>
			</form>
		</div>
	</div>
<?php
}
