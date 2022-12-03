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
if (file_exists(PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_merge_posts.php'))
	require PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_merge_posts.php';
else
	require PUN_ROOT.'lang/English/admin_plugin_merge_posts.php';

// If the "Show text" button was clicked
if (isset($_POST['show_text']))
{
	$t = $_POST['text_to_show'] ?? null;

	if (! is_string($t) || ! preg_match('%^\d+$%', $t))
		message($lang_admin_plugin_merge_posts['No text']);

	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.intval($t).'\' WHERE conf_name=\'o_merge_timeout\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());

	// Regenerate the config cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(PLUGIN_URL, $lang_admin_plugin_merge_posts['Plugin redirect']);
}
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

	if (!isset($pun_config['o_merge_timeout']))
	{
		$result = $db->query('SELECT conf_value FROM '.$db->prefix.'config WHERE conf_name=\'o_merge_timeout\'') or error('Unable to fetch config info', __FILE__, __LINE__, $db->error());
		$row = $db->fetch_row($result);

		if (is_array($row))
		{
			$merge_timeout = $row[0];
		}
		else
		{
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_merge_timeout\', \'86400\')') or error('Unable to insert into table config', __FILE__, __LINE__, $db->error());
			$merge_timeout = '86400';
		}

		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_config_cache();
	}
	else
		$merge_timeout = $pun_config['o_merge_timeout'];

?>
	<div class="plugin blockform">
		<h2><span><?php echo $lang_admin_plugin_merge_posts['Plugin title'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_admin_plugin_merge_posts['Explanation 1'] ?></p>
				<p><?php echo $lang_admin_plugin_merge_posts['Explanation 2'] ?></p>
			</div>
		</div>

		<h2 class="block2"><span><?php echo $lang_admin_plugin_merge_posts['Form title'] ?></span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo PLUGIN_URL ?>">
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_plugin_merge_posts['Legend text'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
								<tr>
									<th scope="row"><?php echo $lang_admin_plugin_merge_posts['Text to show'] ?><div><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_merge_posts['Show text button'] ?>" tabindex="2" /></div></th>
									<td>
										<input type="text" name="text_to_show" size="5" maxlength="5" tabindex="1" value="<?php echo pun_htmlspecialchars($merge_timeout) ?>"/>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
<?php
}
