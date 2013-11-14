<?php

/**
 * Copyright (C) 2010-2013 Visman (visman@inbox.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Load language file
if (file_exists(PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_security.php'))
	require PUN_ROOT.'lang/'.$admin_language.'/admin_plugin_security.php';
else
	require PUN_ROOT.'lang/English/admin_plugin_security.php';

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_URL', pun_htmlspecialchars('admin_loader.php?plugin='.$plugin));

// If the "Show text" button was clicked
if (isset($_POST['show_text']))
{
  $b_kolvo = isset($_POST['blocking_kolvo']) ? intval($_POST['blocking_kolvo']) : 0;
  $b_time = isset($_POST['blocking_time']) ? intval($_POST['blocking_time']) : 0;
  $b_kolvo = ($b_kolvo < 0) ? 0 : $b_kolvo;
  $b_time = ($b_time < 0) ? 0 : $b_time;
  $b_reglog = isset($_POST['blocking_reglog']) ? intval($_POST['blocking_reglog']) : 0;
  $b_guest = isset($_POST['blocking_guest']) ? intval($_POST['blocking_guest']) : 0;
  $b_user = isset($_POST['blocking_user']) ? intval($_POST['blocking_user']) : 0;
  $b_coding_forms = isset($_POST['coding_forms']) ? intval($_POST['coding_forms']) : 0;
  $b_check_ip = isset($_POST['check_ip']) ? intval($_POST['check_ip']) : 0;
  $b_redirect = isset($_POST['board_redirect']) ? pun_trim($_POST['board_redirect']) : '';
  $b_redirectg = isset($_POST['board_redirectg']) ? intval($_POST['board_redirectg']) : 0;
  $b_crypto = isset($_POST['crypto_enable']) ? intval($_POST['crypto_enable']) : 0;

	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_kolvo.'\' WHERE conf_name=\'o_blocking_kolvo\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_time.'\' WHERE conf_name=\'o_blocking_time\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_reglog.'\' WHERE conf_name=\'o_blocking_reglog\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_guest.'\' WHERE conf_name=\'o_blocking_guest\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_user.'\' WHERE conf_name=\'o_blocking_user\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_coding_forms.'\' WHERE conf_name=\'o_coding_forms\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_check_ip.'\' WHERE conf_name=\'o_check_ip\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$db->escape($b_redirect).'\' WHERE conf_name=\'o_board_redirect\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_redirectg.'\' WHERE conf_name=\'o_board_redirectg\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$b_crypto.'\' WHERE conf_name=\'o_crypto_enable\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(PLUGIN_URL, $lang_admin_plugin_security['Plugin redirect']);

}
else
{
	// Display the admin navigation menu
	generate_admin_menu($plugin);

	$cur_index = 1;
	
?>
	<div class="plugin blockform">
		<h2><span><?php echo $lang_admin_plugin_security['Plugin title'] ?></span></h2>
		<div class="box">
			<form id="example" method="post" action="<?php echo PLUGIN_URL.'&amp;'.time() ?>">
				<p class="submitend"><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_security['Show text button'] ?>" tabindex="<?php echo ($cur_index++) ?>" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_plugin_security['Form title'] ?></legend>
						<div class="infldset">
						<p><?php echo $lang_admin_plugin_security['Explanation 1'] ?></p>
						<p><?php echo $lang_admin_plugin_security['Explanation 2'] ?></p>
							<table class="aligntop" cellspacing="10">
								<tr>
									<td>
										<span><?php echo $lang_admin_plugin_security['Allow'] ?>&#160;<input type="text" name="blocking_kolvo" size="5" maxlength="5" tabindex="<?php echo ($cur_index++) ?>" value="<?php echo pun_htmlspecialchars($pun_config['o_blocking_kolvo']) ?>"/>&#160;<?php echo $lang_admin_plugin_security['Errors'] ?>&#160;
										<input type="text" name="blocking_time" size="8" maxlength="8" tabindex="<?php echo ($cur_index++) ?>" value="<?php echo pun_htmlspecialchars($pun_config['o_blocking_time']) ?>"/>&#160;<?php echo $lang_admin_plugin_security['Minute'] ?></span>
									</td>
								</tr>
								<tr>
									<td>
										<label><input type="checkbox" name="blocking_reglog" value="1" tabindex="<?php echo ($cur_index++) ?>"<?php echo ($pun_config['o_blocking_reglog'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Flag Reglog'] ?></label>
									</td>
								</tr>
								<tr>
									<td>
										<label><input type="checkbox" name="blocking_guest" value="1" tabindex="<?php echo ($cur_index++) ?>"<?php echo ($pun_config['o_blocking_guest'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Flag Guest'] ?></label>
									</td>
								</tr>
								<tr>
									<td>
										<label><input type="checkbox" name="blocking_user" value="1" tabindex="<?php echo ($cur_index++) ?>"<?php echo ($pun_config['o_blocking_user'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Flag User'] ?></label>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
				
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_plugin_security['Form title2'] ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="10">
								<tr>
									<td>
										<label><input type="checkbox" name="coding_forms" value="1" tabindex="<?php echo ($cur_index++) ?>"<?php echo ($pun_config['o_coding_forms'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Coding forms'] ?></label>
									</td>
								</tr>
								<tr>
									<td>
										<label><input type="checkbox" name="crypto_enable" value="1" tabindex="<?php echo ($cur_index++) ?>"<?php echo ($pun_config['o_crypto_enable'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Crypto enable'] ?></label>
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
							<table class="aligntop" cellspacing="10">
								<tr>
									<td>
										<label><input type="checkbox" name="check_ip" value="1" tabindex="<?php echo ($cur_index++) ?>"<?php echo ($pun_config['o_check_ip'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Check IP'] ?></label>
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
							<table class="aligntop" cellspacing="10">
								<tr>
									<td>
										<span><?php echo $lang_admin_plugin_security['Board red help'] ?></span>
										<input type="text" name="board_redirect" size="50" maxlength="255" value="<?php echo pun_htmlspecialchars($pun_config['o_board_redirect']) ?>"  tabindex="<?php echo ($cur_index++) ?>"/>
									</td>
								</tr>
								<tr>
									<td>
										<label><input type="checkbox" name="board_redirectg" value="1" tabindex="<?php echo ($cur_index++) ?>"<?php echo ($pun_config['o_board_redirectg'] == '1') ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo $lang_admin_plugin_security['Board red only guest'] ?></label>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>

				<p class="submitend"><input type="submit" name="show_text" value="<?php echo $lang_admin_plugin_security['Show text button'] ?>" tabindex="<?php echo ($cur_index++) ?>" /></p>
			</form>
		</div>
	</div>
<?php
}