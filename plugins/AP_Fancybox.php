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
define('PLUGIN_VERSION', '1.3.5');
define('PLUGIN_REVISION', 4);
define('PLUGIN_NAME', 'Fancybox for FluxBB');
define('PLUGIN_URL', pun_htmlspecialchars('admin_loader.php?plugin='.$plugin));
define('PLUGIN_FILES', 'viewtopic.php,search.php,pmsnew.php,upfiles.php,AP_Upload.php');

// Load language file
if (file_exists(PUN_ROOT.'lang/'.$admin_language.'/fancybox.php'))
	require PUN_ROOT.'lang/'.$admin_language.'/fancybox.php';
else
	require PUN_ROOT.'lang/English/fancybox.php';

$fd_str = 'require PUN_ROOT.\'include/fancybox.php\';';
$prefhf = PUN_ROOT.'header.php';

$arr_files = array(
	$prefhf,
);
$arr_search = array(
	'if (!empty($page_head))',
);
$arr_new = array(
	$fd_str."\n\n".'%search%',
);

// установка изменений в файлы
function InstallModInFiles ()
{
	global $arr_files, $arr_search, $arr_new, $lang_fb;

	$max = count($arr_files);
	$errors = array();

	for ($i=0; $i < $max; $i++)
	{
		$file_content = file_get_contents($arr_files[$i]);
		if ($file_content === false)
		{
			$errors[] = $arr_files[$i].$lang_fb['Error open file'];
			continue;
		}
		$search = str_replace('%search%', $arr_search[$i], $arr_new[$i]);
		if (strpos($file_content, $search) !== false)
		{
			continue;
		}
		if (strpos($file_content, $arr_search[$i]) === false)
		{
			$errors[] = $arr_files[$i].$lang_fb['Error search'];
			continue;
		}
		$file_content = str_replace($arr_search[$i], $search, $file_content);
		$fp = fopen($arr_files[$i], 'wb');
		if ($fp === false)
		{
			$errors[] = $arr_files[$i].$lang_fb['Error save file'];
			continue;
		}
		fwrite ($fp, $file_content);
		fclose ($fp);
	}

	return $errors;
}

// удаление изменений в файлы
function DeleteModInFiles ()
{
	global $arr_files, $arr_search, $arr_new, $lang_fb;

	$max = count($arr_files);
	$errors = array();

	for ($i=0; $i < $max; $i++)
	{
		$file_content = file_get_contents($arr_files[$i]);
		if ($file_content === false)
		{
			$errors[] = $arr_files[$i].$lang_fb['Error open file'];
			continue;
		}
		$search = str_replace('%search%', '', $arr_new[$i]);
		if (strpos($file_content, $search) === false)
		{
			$errors[] = $arr_files[$i].$lang_fb['Error delete'];
			continue;
		}
		$file_content = str_replace($search, '', $file_content);
		$fp = fopen($arr_files[$i], 'wb');
		if ($fp === false)
		{
			$errors[] = $arr_files[$i].$lang_fb['Error save file'];
			continue;
		}
		fwrite ($fp, $file_content);
		fclose ($fp);
	}

	return $errors;
}

// Установка плагина/мода
if (isset($_POST['installation']))
{
	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name=\'o_fbox_guest\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name=\'o_fbox_files\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_fbox_guest\', \'0\')') or error('Unable to insert into table config.', __FILE__, __LINE__, $db->error());
	$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_fbox_files\', \''.$db->escape(PLUGIN_FILES).'\')') or error('Unable to insert into table config.', __FILE__, __LINE__, $db->error());

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	$err = InstallModInFiles();
	if (empty($err))
		redirect(PLUGIN_URL, $lang_fb['Red installation']);

	$pun_config['o_redirect_delay'] = 30;
	redirect(PLUGIN_URL, implode('<br />', $err));
}

// Обновления параметров
else if (isset($_POST['update']))
{
	$gst = isset($_POST['guest_on']) ? 1 : 0;
	$files = isset($_POST['files']) ? array_map('pun_trim', $_POST['files']) : array();
	$fls = array();
	foreach ($files as $file)
	{
		$file = str_replace(array('/','\\','\'','`','"'), array('','','','',''), $file);
		if ((substr($file, -4) == '.php' && file_exists(PUN_ROOT.$file)) || ($file == 'AP_Upload.php') && file_exists(PUN_ROOT.'plugins/'.$file))
			$fls[] = $file;
	}

	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name=\'o_fbox_guest\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name=\'o_fbox_files\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_fbox_guest\', \''.$gst.'\')') or error('Unable to insert into table config.', __FILE__, __LINE__, $db->error());
	$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_fbox_files\', \''.$db->escape(implode(',', $fls)).'\')') or error('Unable to insert into table config.', __FILE__, __LINE__, $db->error());

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(PLUGIN_URL, $lang_fb['Reg update']);
}

// Удаление мода
else if (isset($_POST['delete']))
{
	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name=\'o_fbox_guest\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name=\'o_fbox_files\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	$err = DeleteModInFiles();
	if (empty($err))
		redirect(PLUGIN_URL, $lang_fb['Red delete']);

	$pun_config['o_redirect_delay'] = 30;
	redirect(PLUGIN_URL, implode('<br />', $err));
}

$file_content = file_get_contents($prefhf);
if ($file_content === false)
	message(pun_htmlspecialchars($prefhf.$lang_fb['Error open file']));
$f_inst = (strpos($file_content, $fd_str) !== false);
if ($f_inst && !isset($pun_config['o_fbox_files'])) // непредвиденная ситуация при обновлении
{
	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name=\'o_fbox_guest\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name=\'o_fbox_files\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_fbox_guest\', \'0\')') or error('Unable to insert into table config.', __FILE__, __LINE__, $db->error());
	$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_fbox_files\', \''.$db->escape(PLUGIN_FILES).'\')') or error('Unable to insert into table config.', __FILE__, __LINE__, $db->error());

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(PLUGIN_URL, 'Synchronization of settings this plugin.');
}

// Display the admin navigation menu
generate_admin_menu($plugin);

$tabindex = 1;

?>
	<div id="loginza" class="plugin blockform">
		<h2><span><?php echo PLUGIN_NAME.' v.'.PLUGIN_VERSION ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_fb['plugin_desc'] ?></p>
				<form action="<?php echo PLUGIN_URL ?>" method="post">
					<p>
<?php

if (!$f_inst)
{

?>
						<input type="submit" name="installation" value="<?php echo $lang_fb['installation'] ?>" />&#160;<?php echo $lang_fb['installation_info'] ?><br />
					</p>
				</form>
			</div>
		</div>
<?php

}
else
{

?>
						<input type="submit" name="delete" value="<?php echo $lang_fb['delete'] ?>" />&#160;<?php echo $lang_fb['delete_info'] ?><br /><br />
					</p>
				</form>
			</div>
		</div>

		<h2 class="block2"><span><?php echo $lang_fb['configuration'] ?></span></h2>
		<div class="box">
			<form method="post" action="<?php echo PLUGIN_URL ?>">
				<p class="submittop"><input type="submit" name="update" value="<?php echo $lang_fb['update'] ?>" tabindex="<?php echo $tabindex++ ?>" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_fb['legend'] ?></legend>
						<div class="infldset">
						<table>
							<tr>
								<td>
									<label><input type="checkbox" name="guest_on" value="1" tabindex="<?php echo $tabindex++ ?>"<?php echo (empty($pun_config['o_fbox_guest'])) ? '' : ' checked="checked"' ?> />&#160;&#160;<?php echo $lang_fb['guest info'] ?></label>
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_fb['legend2'] ?></legend>
						<div class="infldset">
						<table>
<?php

	$d = dir(PUN_ROOT);
	$ar_file = array();
	while (($entry = $d->read()) !== false)
	{
		if (substr($entry, -4) == '.php' && substr($entry, 0, 5) != 'admin' && !in_array($entry, array('db_update.php', 'install.php', 'extern.php', 'pjq.php', 're.php', 'footer.php', 'header.php', 'help.php', 'login.php', 'misc.php', 'register.php', 'reglog.php', 'userlist.php')))
			$ar_file[] = $entry;
	}
	$d->close();
	if (file_exists(PUN_ROOT.'plugins/AP_Upload.php'))
		$ar_file[] = 'AP_Upload.php';

	natcasesort($ar_file);

	foreach ($ar_file as $id => $file)
	{

?>
							<tr>
								<td>
									<label><input type="checkbox" name="files[<?php echo $id ?>]" value="<?php echo pun_htmlspecialchars($file) ?>" tabindex="<?php echo $tabindex++ ?>"<?php echo (strpos(','.$pun_config['o_fbox_files'], ','.$file) !== false) ? ' checked="checked"' : '' ?> />&#160;&#160;<?php echo pun_htmlspecialchars($file) ?></label>
								</td>
							</tr>
<?php

	}

?>
						</table>
						</div>
					</fieldset>
				</div>
				<p class="submitend"><input type="submit" name="update" value="<?php echo $lang_fb['update'] ?>" tabindex="<?php echo $tabindex++ ?>" /></p>
			</form>
		</div>
<?php

}

?>
	</div>
<?php
