<?php

/**
 * Copyright (C) 2010-2015 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_VERSION', '1.8.0');
define('PLUGIN_URL', pun_htmlspecialchars('admin_loader.php?plugin='.$plugin));

// Load language file
if (file_exists(PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_pms_new.php'))
	require PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_pms_new.php';
else
	require PUN_ROOT.'lang/English/admin_plugin_pms_new.php';

// If the "Show text" button was clicked
if (isset($_POST['show_text']))
{
	$en_pms = isset($_POST['enable_pms']) ? 1 : 0;
	$g_limit = isset($_POST['g_limit']) ? array_map('pun_trim', $_POST['g_limit']) : array();
	$g_pm = isset($_POST['g_pm']) ? array_map('pun_trim', $_POST['g_pm']) : array();
	$min_kolvo = isset($_POST['min_kolvo']) ? intval($_POST['min_kolvo']) : 0;
	$flash_pms = isset($_POST['flasher_pms']) ? 1 : 0;

	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$en_pms.'\' WHERE conf_name=\'o_pms_enabled\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$min_kolvo.'\' WHERE conf_name=\'o_pms_min_kolvo\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	if (isset($pun_config['o_pms_flasher']))
		$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$flash_pms.'\' WHERE conf_name=\'o_pms_flasher\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	else
		$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_pms_flasher\', \'0\')') or error('Unable to insert into table '.$db->prefix.'config. Please check your configuration and try again.');

	$result = $db->query('SELECT g_id FROM '.$db->prefix.'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

	while ($cur_group = $db->fetch_assoc($result))
		if ($cur_group['g_id'] > PUN_ADMIN && $cur_group['g_id'] != PUN_GUEST)
			if (isset($g_limit[$cur_group['g_id']]))
			{
				$g_lim = isset($g_limit[$cur_group['g_id']]) ? intval($g_limit[$cur_group['g_id']]) : 0;
				$g_p = (isset($g_pm[$cur_group['g_id']]) || $cur_group['g_id'] == PUN_ADMIN) ? 1 : 0;

				$db->query('UPDATE '.$db->prefix.'groups SET g_pm='.$g_p.', g_pm_limit='.$g_lim.' WHERE g_id='.$cur_group['g_id']) or error('Unable to update user group list', __FILE__, __LINE__, $db->error());
			}

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(PLUGIN_URL, $lang_apmsn['Plugin redirect']);
}
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

	$tabindex = 1;

?>
	<div class="plugin blockform">
		<h2><span><?php echo $lang_apmsn['Plugin title'].' v.'.PLUGIN_VERSION ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_apmsn['Explanation 1'] ?></p>
				<p><?php echo $lang_apmsn['Explanation 2'] ?></p>
			</div>
		</div>

		<h2 class="block2"><span><?php echo $lang_apmsn['Form title'] ?></span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo PLUGIN_URL ?>">
				<p class="submittop"><input type="submit" name="show_text" value="<?php echo $lang_apmsn['Show text button'] ?>" tabindex="<?php echo ($tabindex++) ?>" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_apmsn['Legend1'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
								<tr>
									<td>
										<label><input type="checkbox" name="enable_pms" value="1" tabindex="<?php echo ($tabindex++) ?>"<?php echo ($pun_config['o_pms_enabled'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_apmsn['Q1'] ?></label>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
<?php

if ($pun_config['o_pms_enabled'] == '1')
{

?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_apmsn['Legend3'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
								<tr>
									<td>
										<span><input type="text" name="min_kolvo" value="<?php echo pun_htmlspecialchars($pun_config['o_pms_min_kolvo']) ?>" tabindex="<?php echo ($tabindex++) ?>" size="10" maxlength="10" />&#160;&#160;<?php echo $lang_apmsn['Q3'] ?></span>
									</td>
								</tr>
								<tr>
									<td>
										<label><input type="checkbox" name="flasher_pms" value="1" tabindex="<?php echo ($tabindex++) ?>"<?php echo (!empty($pun_config['o_pms_flasher'])) ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_apmsn['Q2'] ?></label>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_apmsn['Legend2'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
							<thead>
								<tr>
									<th class="tcl" scope="col"><?php echo $lang_apmsn['Group'] ?></th>
									<th class="tc2" scope="col"><?php echo $lang_apmsn['Allow'] ?></th>
									<th scope="tcr"><?php echo $lang_apmsn['Kolvo'] ?></th>
								</tr>
							</thead>
							<tbody>
<?php

	$result = $db->query('SELECT g_id, g_title, g_pm, g_pm_limit FROM '.$db->prefix.'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

	while ($cur_group = $db->fetch_assoc($result))
		if ($cur_group['g_id'] > PUN_ADMIN && $cur_group['g_id'] != PUN_GUEST)
		{

?>
								<tr>
									<td class="tcl"><?php echo pun_htmlspecialchars($cur_group['g_title']) ?></td>
									<td class="tc2"><input type="checkbox" name="g_pm[<?php echo $cur_group['g_id'] ?>]" value="1" tabindex="<?php echo ($tabindex++) ?>"<?php echo ($cur_group['g_pm'] == 1 ? ' checked="checked"' : '')?> /></td>
									<td class="tcr"><input type="text" name="g_limit[<?php echo $cur_group['g_id'] ?>]" value="<?php echo $cur_group['g_pm_limit'] ?>" tabindex="<?php echo ($tabindex++) ?>" size="10" maxlength="10" /></td>
								</tr>
<?php

		}

?>
							</tbody>
							</table>
						</div>
					</fieldset>
				</div>
<?php

}

?>
				<p class="submitend"><input type="submit" name="show_text" value="<?php echo $lang_apmsn['Show text button'] ?>" tabindex="<?php echo ($tabindex++) ?>" /></p>
			</form>
		</div>
	</div>
<?php
}
