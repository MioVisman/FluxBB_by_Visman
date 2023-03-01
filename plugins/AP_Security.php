<?php

/**
 * Copyright (C) 2010-2022 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_URL', pun_htmlspecialchars('admin_loader.php?plugin='.$plugin));

// Load language file
if (file_exists(PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_security.php'))
	require PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_security.php';
else
	require PUN_ROOT.'lang/English/admin_plugin_security.php';

// If the "Show text" button was clicked
if (isset($_POST['show_text']))
{
	$b_coding_forms = isset($_POST['coding_forms']) ? 1 : 0;
	$b_check_ip = isset($_POST['check_ip']) ? 1 : 0;
	$b_redirect = isset($_POST['board_redirect']) ? pun_trim($_POST['board_redirect']) : '';
	$b_redirectg = isset($_POST['board_redirectg']) ? 1 : 0;
	$b_crypto = isset($_POST['crypto_enable']) ? 1 : 0;
	$b_enable_acaptcha = isset($_POST['enable_acaptcha']) ? 1 : 0;

	if ($b_redirect != '' && false === @preg_match('/'.$b_redirect.'/i', 'abcdef')) {
		message($lang_admin_plugin_security['Bad regular expression']);
	}

	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_coding_forms.'\' WHERE conf_name=\'o_coding_forms\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_check_ip.'\' WHERE conf_name=\'o_check_ip\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$db->escape($b_redirect).'\' WHERE conf_name=\'o_board_redirect\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_redirectg.'\' WHERE conf_name=\'o_board_redirectg\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_crypto.'\' WHERE conf_name=\'o_crypto_enable\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_enable_acaptcha.'\' WHERE conf_name=\'o_enable_acaptcha\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(PLUGIN_URL, $lang_admin_plugin_security['Plugin redirect']);
}
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

	$tabindex = 1;

?>
	<div class="plugin blockform">
		<h2><span><?php echo $lang_admin_plugin_security['Plugin title'] ?></span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo PLUGIN_URL ?>">
				<p class="submitend"><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_security['Show text button'] ?>" tabindex="<?php echo ($tabindex++) ?>" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_plugin_security['Form title2'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
								<tr>
									<td>
										<label><input type="checkbox" name="coding_forms" value="1" tabindex="<?php echo ($tabindex++) ?>"<?php echo ($pun_config['o_coding_forms'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Coding forms'] ?></label>
									</td>
								</tr>
								<tr>
									<td>
										<label><input type="checkbox" name="crypto_enable" value="1" tabindex="<?php echo ($tabindex++) ?>"<?php echo ($pun_config['o_crypto_enable'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Crypto enable'] ?></label>
									</td>
								</tr>
								<tr>
									<td>
										<label><input type="checkbox" name="enable_acaptcha" value="1" tabindex="<?php echo ($tabindex++) ?>"<?php echo ($pun_config['o_enable_acaptcha'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['ACaptcha enable'] ?></label>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>

				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_plugin_security['Form title3'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
								<tr>
									<td>
										<label><input type="checkbox" name="check_ip" value="1" tabindex="<?php echo ($tabindex++) ?>"<?php echo ($pun_config['o_check_ip'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Check IP'] ?></label>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>

				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_plugin_security['Form title4'] ?></legend>
						<div class="infldset">
							<table class="aligntop">
								<tr>
									<td>
										<span><?php echo $lang_admin_plugin_security['Board red help'] ?></span>
										<input type="text" name="board_redirect" size="50" maxlength="255" value="<?php echo pun_htmlspecialchars($pun_config['o_board_redirect']) ?>" tabindex="<?php echo ($tabindex++) ?>"/>
									</td>
								</tr>
								<tr>
									<td>
										<label><input type="checkbox" name="board_redirectg" value="1" tabindex="<?php echo ($tabindex++) ?>"<?php echo ($pun_config['o_board_redirectg'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Board red only guest'] ?></label>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>

				<p class="submitend"><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_security['Show text button'] ?>" tabindex="<?php echo ($tabindex++) ?>" /></p>
			</form>
		</div>
	</div>
<?php
}
