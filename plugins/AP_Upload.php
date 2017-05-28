<?php

/**
 * Copyright (C) 2011-2017 Visman (mio.visman@yandex.ru)
 * Copyright (C) 2007 BN (bnmaster@la-bnbox.info)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_VERSION', '2.1.0');
define('PLUGIN_URL', pun_htmlspecialchars('admin_loader.php?plugin='.$plugin));
define('PLUGIN_EXTS', 'jpg,jpeg,png,gif,mp3,zip,rar,7z');
define('PLUGIN_NF', 25);

require PUN_ROOT.'include/upload.php';

$sconf = array(
	'thumb' => ($gd ? 1 : 0),
	'thumb_size' => 100,
	'thumb_perc' => 75,
	'pic_mass' => 307200,
	'pic_perc' => 75,
	'pic_w' => 1680,
	'pic_h' => 1050,
	);

// Установка плагина/мода
if (isset($_POST['installation']))
{
	$db->add_field('users', 'upload', 'INT(15)', false, 0) or error(sprintf($lang_up['Error DB'], 'users'), __FILE__, __LINE__, $db->error());
	$db->add_field('groups', 'g_up_ext', 'VARCHAR(255)', false, PLUGIN_EXTS) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
	$db->add_field('groups', 'g_up_max', 'INT(10)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
	$db->add_field('groups', 'g_up_limit', 'INT(10)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());

	$db->query('UPDATE '.$db->prefix.'groups SET g_up_ext=\''.$db->escape(PLUGIN_EXTS).'\', g_up_limit=1073741824, g_up_max='.min(return_bytes(ini_get('upload_max_filesize')), return_bytes(ini_get('post_max_size'))).' WHERE g_id='.PUN_ADMIN) or error('Unable to update user group list', __FILE__, __LINE__, $db->error());

	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name LIKE \'o\_uploadile\_%\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_uploadile_other\', \''.$db->escape(serialize($sconf)).'\')') or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(PLUGIN_URL, $lang_up['Redirect']);
}

// Обновления параметров
else if (isset($_POST['update']))
{
	if (!isset($pun_user['g_up_ext']))
	{
		$db->add_field('groups', 'g_up_ext', 'VARCHAR(255)', false, PLUGIN_EXTS) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
		$db->add_field('groups', 'g_up_max', 'INT(10)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
		$db->add_field('groups', 'g_up_limit', 'INT(15)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
	}

	$g_up_ext = isset($_POST['g_up_ext']) ? array_map('pun_trim', $_POST['g_up_ext']) : array();
	$g_up_limit = isset($_POST['g_up_limit']) ? array_map('intval', $_POST['g_up_limit']) : array();
	$g_up_max = isset($_POST['g_up_max']) ? array_map('intval', $_POST['g_up_max']) : array();

	$result = $db->query('SELECT g_id FROM '.$db->prefix.'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());
	while ($cur_group = $db->fetch_assoc($result))
		if ($cur_group['g_id'] != PUN_GUEST)
		{
			if (isset($g_up_ext[$cur_group['g_id']]))
			{
				$g_ext = str_replace(' ', '', $g_up_ext[$cur_group['g_id']]);
				$g_ext = preg_replace('%[,]+%u', ',', $g_ext);
				if (preg_match('%^[0-9a-zA-Z][0-9a-zA-Z,]*[0-9a-zA-Z]$%uD', $g_ext) == 0)
					$g_ext = PLUGIN_EXTS;
				$g_ext = strtolower($g_ext);
			}
			else
				$g_ext = PLUGIN_EXTS;

			if ($cur_group['g_id'] == PUN_ADMIN)
			{
				$g_lim = 1073741824;
				$g_max = min(return_bytes(ini_get('upload_max_filesize')), return_bytes(ini_get('post_max_size')));
			}
			else
			{
				$g_lim = (!isset($g_up_limit[$cur_group['g_id']]) || $g_up_limit[$cur_group['g_id']] < 0) ? 0 : $g_up_limit[$cur_group['g_id']];
				$g_max = (!isset($g_up_max[$cur_group['g_id']]) || $g_up_max[$cur_group['g_id']] < 0) ? 0 : $g_up_max[$cur_group['g_id']];
				$g_max = min($g_max, return_bytes(ini_get('upload_max_filesize')), return_bytes(ini_get('post_max_size')));
			}

			$db->query('UPDATE '.$db->prefix.'groups SET g_up_ext=\''.$db->escape($g_ext).'\', g_up_limit='.$g_lim.', g_up_max='.$g_max.' WHERE g_id='.$cur_group['g_id']) or error('Unable to update user group list', __FILE__, __LINE__, $db->error());
		}

	if (isset($_POST['thumb']))
		$sconf['thumb'] = ($_POST['thumb'] == '1' ? 1 : 0);
	if (isset($_POST['thumb_size']) && $_POST['thumb_size'] > 0)
		$sconf['thumb_size'] = intval($_POST['thumb_size']);
	if (isset($_POST['thumb_perc']) && $_POST['thumb_perc'] > 0 && $_POST['thumb_perc'] <= 100)
		$sconf['thumb_perc'] = intval($_POST['thumb_perc']);

	if (isset($_POST['pic_mass']) && $_POST['pic_mass'] >= 0)
		$sconf['pic_mass'] = intval($_POST['pic_mass']);
	if (isset($_POST['pic_perc']) && $_POST['pic_perc'] > 0 && $_POST['pic_perc'] <= 100)
		$sconf['pic_perc'] = intval($_POST['pic_perc']);
	if (isset($_POST['pic_w']) && $_POST['pic_w'] >= 100)
		$sconf['pic_w'] = intval($_POST['pic_w']);
	if (isset($_POST['pic_h']) && $_POST['pic_h'] >= 100)
		$sconf['pic_h'] = intval($_POST['pic_h']);

	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name LIKE \'o\_uploadile\_%\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_uploadile_other\', \''.$db->escape(serialize($sconf)).'\')') or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();

	redirect(PLUGIN_URL, $lang_up['Redirect']);
}

// Удаление мода
else if (isset($_POST['restore']))
{
	$db->drop_field('users', 'upload') or error('Unable to drop upload field', __FILE__, __LINE__, $db->error());
	$db->drop_field('groups', 'g_up_ext') or error('Unable to drop g_up_ext field', __FILE__, __LINE__, $db->error());
	$db->drop_field('groups', 'g_up_max') or error('Unable to drop g_up_max field', __FILE__, __LINE__, $db->error());
	$db->drop_field('groups', 'g_up_limit') or error('Unable to drop g_up_limit field', __FILE__, __LINE__, $db->error());

	$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name LIKE \'o\_uploadile\_%\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require PUN_ROOT.'include/cache.php';

	generate_config_cache();
	
	redirect(PLUGIN_URL, $lang_up['Redirect']);
}

if (isset($pun_config['o_uploadile_other']))
	$aconf = unserialize($pun_config['o_uploadile_other']);
else
{
	$aconf = $sconf;
	$aconf['thumb'] = 0;
	define('PLUGIN_OFF', 1);
}

$mem = 'img/members/';
$regx = '%^img/members/(\d+)/(.+)\.([0-9a-zA-Z]+)$%i';
// #############################################################################
// Удаление файлов
if (isset($_POST['delete']) && isset($_POST['delete_f']) && is_array($_POST['delete_f']))
{
	$error = 0;

	if (is_dir(PUN_ROOT.$mem))
	{
		$au = array();
		foreach ($_POST['delete_f'] as $file)
		{
			preg_match($regx, $file, $fi);
			if (!isset($fi[1]) || !isset($fi[2]) || !isset($fi[3])) continue;

			$f = parse_file($fi[2].'.'.$fi[3]);
			$dir = $mem.$fi[1].'/';
			if (is_file(PUN_ROOT.$dir.$f))
			{
				$au[$fi[1]] = $fi[1];
				if (unlink(PUN_ROOT.$dir.$f))
				{
					if (is_file(PUN_ROOT.$dir.'mini_'.$f))
						unlink(PUN_ROOT.$dir.'mini_'.$f);
				}
				else
					$error++;
			}
		}

		if (!defined('PLUGIN_OFF'))
		{
			foreach ($au as $user)
			{
				// Считаем общий размер файлов юзера
				$upload = dir_size($mem.$user.'/');
				$db->query('UPDATE '.$db->prefix.'users SET upload=\''.$upload.'\' WHERE id='.$user) or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());
			}
		}
	}

	$p = (!isset($_GET['p']) || $_GET['p'] <= 1) ? 1 : intval($_GET['p']);

	if ($error == 0)
		redirect(PLUGIN_URL.($p > 1 ? '&amp;p='.$p : ''), $lang_up['Redirect delete']);
	else
	{
		$pun_config['o_redirect_delay'] = 5;
		redirect(PLUGIN_URL.($p > 1 ? '&amp;p='.$p : ''), $lang_up['Error'].$lang_up['Error delete']);
	}
}

if (file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/upfiles.css'))
	$s = '<link rel="stylesheet" type="text/css" href="style/'.$pun_user['style'].'/upfiles.css" />';
else
	$s = '<link rel="stylesheet" type="text/css" href="style/imports/upfiles.css" />';

$tpl_main = str_replace('</head>', $s."\n</head>", $tpl_main);

// Display the admin navigation menu
generate_admin_menu($plugin);

$tabindex = 1;

?>
	<div id="upf-block" class="plugin blockform">
		<h2><span>Plugin Upload Files v.<?php echo PLUGIN_VERSION ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?php echo $lang_up['plugin_desc'] ?></p>
				<form action="<?php echo PLUGIN_URL ?>" method="post">
					<p>
<?php

$stthumb = '" disabled="disabled';
	
if (defined('PLUGIN_OFF'))
{

?>
						<input type="submit" name="installation" value="<?php echo $lang_up['Install'] ?>" />&#160;<?php echo $lang_up['Install info'] ?><br />
					</p>
				</form>
			</div>
		</div>
<?php

}
else
{

	if ($aconf['thumb'] == 1 && $gd)
		$stthumb = '';
	if ($gd)
	{
		$disbl = '';
		$gd_vers = gd_info();
		$gd_vers = $gd_vers['GD Version'];
	}
	else
	{
		$disbl = '" disabled="disabled';
		$gd_vers = '-';
	}

?>
						<input type="submit" name="update" value="<?php echo $lang_up['Update'] ?>" />&#160;<?php echo $lang_up['Update info'] ?><br />
						<input type="submit" name="restore" value="<?php echo $lang_up['Uninstall'] ?>" />&#160;<?php echo $lang_up['Uninstall info'] ?><br /><br />
					</p>
				</form>
			</div>
		</div>
		<h2 class="block2"><span><?php echo $lang_up['configuration'] ?></span></h2>
		<div class="box">
			<form method="post" action="<?php echo PLUGIN_URL ?>">
				<p class="submittop"><input type="submit" name="update" value="<?php echo $lang_up['Update'] ?>" tabindex="<?php echo $tabindex++ ?>" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_up['legend_2'] ?></legend>
						<div class="infldset">
						<table>
							<tr>
								<th scope="row"><label>GD Version</label></th>
								<td><?php echo pun_htmlspecialchars($gd_vers) ?></td>
							</tr>
							<tr>
								<th scope="row"><label for="pic_mass"><?php echo $lang_up['pictures'] ?></label></th>
								<td>
									<?php echo $lang_up['for pictures']."\n" ?>
									<input type="text" name="pic_mass" size="8" maxlength="8" tabindex="<?php echo $tabindex++ ?>" value="<?php echo pun_htmlspecialchars($aconf['pic_mass']).$disbl ?>" />&#160;<?php echo $lang_up['bytes'].":\n" ?><br />
									&#160;*&#160;<?php echo $lang_up['to jpeg'] ?><br />
									&#160;*&#160;<?php echo $lang_up['Install quality']."\n" ?>
									<input type="text" name="pic_perc" size="4" maxlength="3" tabindex="<?php echo $tabindex++ ?>" value="<?php echo pun_htmlspecialchars($aconf['pic_perc']).$disbl ?>" />&#160;%<br />
									&#160;*&#160;<?php echo $lang_up['Size not more']."\n" ?>
									<input type="text" name="pic_w" size="4" maxlength="4" tabindex="<?php echo $tabindex++ ?>" value="<?php echo pun_htmlspecialchars($aconf['pic_w']).$disbl ?>" />&#160;x
									<input type="text" name="pic_h" size="4" maxlength="4" tabindex="<?php echo $tabindex++ ?>" value="<?php echo pun_htmlspecialchars($aconf['pic_h']).$disbl ?>" />&#160;<?php echo $lang_up['px']."\n" ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="thumb"><?php echo $lang_up['thumb'] ?></label></th>
								<td>
									<input type="radio" tabindex="<?php echo ($tabindex++).$disbl ?>" name="thumb" value="1"<?php if ($aconf['thumb'] == 1) echo ' checked="checked"' ?> /> <strong><?php echo $lang_admin_common['Yes'] ?></strong>
									&#160;&#160;&#160;
									<input type="radio" tabindex="<?php echo ($tabindex++).$disbl ?>" name="thumb" value="0"<?php if ($aconf['thumb'] == 0) echo ' checked="checked"' ?> /> <strong><?php echo $lang_admin_common['No'] ?></strong>
									<br />
									&#160;*&#160;<?php echo $lang_up['thumb_size']."\n" ?>
									<input type="text" name="thumb_size" size="4" maxlength="4" tabindex="<?php echo $tabindex++ ?>" value="<?php echo pun_htmlspecialchars($aconf['thumb_size']).$disbl ?>" />&#160;<?php echo $lang_up['px']."\n" ?><br />
									&#160;*&#160;<?php echo $lang_up['quality']."\n" ?>
									<input type="text" name="thumb_perc" size="4" maxlength="3" tabindex="<?php echo $tabindex++ ?>" value="<?php echo pun_htmlspecialchars($aconf['thumb_perc']).$disbl ?>" />&#160;%
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>

				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_up['groups'] ?></legend>
						<div class="infldset">
							<div class="inbox">
								<p>1* - <?php echo $lang_up['laws'] ?></p>
								<p>2* - <?php echo $lang_up['maxsize_member'] ?></p>
								<p>3* - <?php echo $lang_up['limit_member'] ?></p>
							</div>
							<table class="aligntop">
							<thead>
								<tr>
									<th class="tcl" scope="col"><?php echo $lang_up['group'] ?></th>
									<th class="tc2" scope="col">1*</th>
									<th class="tcr" scope="col">2*</th>
									<th class="tcr" scope="col">3*</th>
								</tr>
							</thead>
							<tbody>
<?php

	$result = $db->query('SELECT * FROM '.$db->prefix.'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

	while ($cur_group = $db->fetch_assoc($result))
		if ($cur_group['g_id'] != PUN_GUEST)
		{
			if (!isset($cur_group['g_up_ext']))
			{
				$cur_group['g_up_max'] = $cur_group['g_up_limit'] = 0;
				$cur_group['g_up_ext'] = '';
			}
				
?>
								<tr>
									<td class="tcl"><?php echo pun_htmlspecialchars($cur_group['g_title']) ?></td>
									<td class="tc2"><input type="text" name="g_up_ext[<?php echo $cur_group['g_id'] ?>]" value="<?php echo pun_htmlspecialchars($cur_group['g_up_ext']) ?>" tabindex="<?php echo $tabindex++ ?>" size="40" maxlength="255" /></td>
									<td class="tcr"><input type="text" name="g_up_max[<?php echo $cur_group['g_id'] ?>]" value="<?php echo $cur_group['g_up_max'] ?>" tabindex="<?php echo $tabindex++ ?>" size="10" maxlength="10" <?php echo ($cur_group['g_id'] == PUN_ADMIN ? 'disabled="disabled" ' : '')?>/></td>
									<td class="tcr"><input type="text" name="g_up_limit[<?php echo $cur_group['g_id'] ?>]" value="<?php echo $cur_group['g_up_limit'] ?>" tabindex="<?php echo $tabindex++ ?>" size="10" maxlength="10" <?php echo ($cur_group['g_id'] == PUN_ADMIN ? 'disabled="disabled" ' : '')?>/></td>
								</tr>
<?php

		}

?>
							</tbody>
							</table>
						</div>
					</fieldset>
				</div>

				<p class="submitend"><input type="submit" name="update" value="<?php echo $lang_up['Update'] ?>" tabindex="<?php echo $tabindex++ ?>" /></p>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_up['legend_1'] ?></legend>
						<div class="infldset">
							<label for="mo"><?php echo $lang_up['mo'] ?></label> <input type="text" name="mo" id="mo" size="15" tabindex="<?php echo $tabindex++ ?>" /> <input type="button" value="<?php echo $lang_up['convert'] ?>" tabindex="<?php echo $tabindex++ ?>" onclick="javascript:document.getElementById('ko').value=document.getElementById('mo').value*1024; document.getElementById('o').value=document.getElementById('mo').value*1048576;" />
							<label for="ko"><?php echo $lang_up['ko'] ?></label> <input type="text" name="ko" id="ko" size="15" tabindex="<?php echo $tabindex++ ?>" /> <input type="button" value="<?php echo $lang_up['convert'] ?>" tabindex="<?php echo $tabindex++ ?>" onclick="javascript:document.getElementById('mo').value=document.getElementById('ko').value/1024; document.getElementById('o').value=document.getElementById('ko').value*1024;"/>
							<label for="o"><?php echo $lang_up['o'] ?></label> <input type="text" name="o" id="o" size="15" tabindex="<?php echo $tabindex++ ?>" /> <input type="button" value="<?php echo $lang_up['convert'] ?>" tabindex="<?php echo $tabindex++ ?>" onclick="javascript:document.getElementById('mo').value=document.getElementById('o').value/1048576; document.getElementById('ko').value=(document.getElementById('o').value*1024)/1048576;"/>
							</div>
					</fieldset>
				</div>
			</form>
		</div>
<?php

}
// #############################################################################
$files = array();
if (is_dir(PUN_ROOT.$mem))
{
	$af = array();
	$ad = scandir(PUN_ROOT.$mem);
	foreach($ad as $f)
	{
		if ($f != '.' && $f != '..' && is_dir(PUN_ROOT.$mem.$f))
		{
			$dir = $mem.$f.'/';
			$open = opendir(PUN_ROOT.$dir);
			while(($file = readdir($open)) !== false)
			{
				if (is_file(PUN_ROOT.$dir.$file) && $file[0] != '.' && $file[0] != '#' && substr($file, 0, 5) != 'mini_')
				{
					$ext = strtolower(substr(strrchr($file, '.'), 1)); // берем расширение файла
					if (!in_array($ext, $extforno))
					{
						$time = filemtime(PUN_ROOT.$dir.$file).$file.$f;
						$af[$time] = $dir.$file;
					}
				}
			}
			closedir($open);
		}
	}
	unset($ad);
	if (!empty($af))
	{
		$num_pages = ceil(sizeof($af) / PLUGIN_NF);
		$p = (!isset($_GET['p']) || $_GET['p'] <= 1) ? 1 : intval($_GET['p']);
		if ($p > $num_pages)
		{
			header('Location: '.PLUGIN_URL.'&p='.$num_pages.'#gofile');
			exit;
		}

		$start_from = PLUGIN_NF * ($p - 1);

		// Generate paging links
		$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, PLUGIN_URL);
		$paging_links = preg_replace('%href="([^">]+)"%', 'href="$1#gofile"', $paging_links);

		krsort($af);
		$files = array_slice($af, $start_from, PLUGIN_NF);
		unset($af);
	}
}

?>
		<h2 id="gofile" class="block2"><span><?php echo $lang_up['Member files'] ?></span></h2>
		<div class="box">
<?php

if (empty($files))
{

?>
			<div class="inbox">
				<p><?php echo $lang_up['No upfiles'] ?></p>
			</div>
<?php

}
else
{

?>

			<div class="inbox">
				<div class="pagepost">
					<p class="pagelink conl"><?php echo $paging_links ?></p>
				</div>
			</div>

			<form method="post" action="<?php echo PLUGIN_URL.($p > 1 ? '&amp;p='.$p : '').'#gofile' ?>">
				<div class="inform">
					<p class="submittop"><input type="submit" name="update_thumb" value="<?php echo $lang_up['update_thumb'].$stthumb ?>" /></p>
					<div class="infldset">
						<table id="upf-table" class="aligntop">
							<thead>
								<tr>
									<th class="upf-c1" scope="col"><?php echo $lang_up['th0'] ?></th>
									<th class="upf-c2" scope="col"><?php echo $lang_up['th1'] ?></th>
									<th class="upf-c3" scope="col"><?php echo $lang_up['th2'] ?></th>
									<th class="upf-c4" scope="col"><input type="submit" value="<?php echo $lang_up['delete'] ?>" name="delete" tabindex="<?php echo $tabindex++ ?>" /></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th class="upf-c1"><?php echo $lang_up['th0'] ?></th>
									<th class="upf-c2"><?php echo $lang_up['th1'] ?></th>
									<th class="upf-c3"><?php echo $lang_up['th2'] ?></th>
									<th class="upf-c4"><input type="submit" value="<?php echo $lang_up['delete'] ?>" name="delete" tabindex="<?php echo $tabindex++ ?>" /></th>
								</tr>
							</tfoot>
							<tbody>
<?php

	// данные по юзерам
	$au = $ag = array();
	$result = $db->query('SELECT id, username, group_id FROM '.$db->prefix.'users WHERE group_id!='.PUN_UNVERIFIED) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
	while ($u = $db->fetch_assoc($result))
	{
		$au[$u['id']] = $u['username'];
		$ag[$u['id']] = $u['group_id'];
	}
	$db->free_result($result);
	// данные по группам
	$extsup = array();
	$result = $db->query('SELECT * FROM '.$db->prefix.'groups') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());
	while ($g = $db->fetch_assoc($result))
	{
		if (isset($g['g_up_ext']))
			$extsup[$g['g_id']] = explode(',', $g['g_up_ext'].','.strtoupper($g['g_up_ext']));
		else
			$extsup[$g['g_id']] = array();
	}
	$db->free_result($result);

	foreach ($files as $file)
	{
		preg_match($regx, $file, $fi);
		if (!isset($fi[1]) || !isset($fi[2]) || !isset($fi[3])) continue;
		
		$fb = in_array(strtolower($fi[3]), array('jpg', 'jpeg', 'gif', 'png', 'bmp')) ? '" class="fancy_zoom" rel="vi001' : '';
		$dir = $mem.$fi[1].'/';
		$size_file = file_size(filesize(PUN_ROOT.$file));
		$miniature = $dir.'mini_'.$fi[2].'.'.$fi[3];
		if (isset($_POST['update_thumb']) && $aconf['thumb'] == 1 && array_key_exists(strtolower($fi[3]),$extimageGD))
			img_resize(PUN_ROOT.$file, $dir, 'mini_'.$fi[2], $fi[3], 0, $aconf['thumb_size'], $aconf['thumb_perc']);

?>
								<tr>
									<td class="upf-c1"><?php echo (isset($au[$fi[1]]) ? pun_htmlspecialchars($au[$fi[1]]) : '&#160;') ?></td>
									<td class="upf-c2"><a href="<?php echo pun_htmlspecialchars($file) ?>"><?php echo pun_htmlspecialchars($fi[2]) ?></a> [<?php echo pun_htmlspecialchars($size_file) ?>].[<?php echo (isset($ag[$fi[1]]) && in_array($fi[3], $extsup[$ag[$fi[1]]]) ? pun_htmlspecialchars($fi[3]) : '<span style="color: #ff0000"><strong>'.pun_htmlspecialchars($fi[3]).'</strong></span>') ?>]</td>
<?php

		if (is_file(PUN_ROOT.$miniature) && ($size = getimagesize(PUN_ROOT.$miniature)) !== false)
			echo "\t\t\t\t\t\t\t\t\t".'<td class="upf-c3"><a href="'.pun_htmlspecialchars($file).$fb.'"><img style="width:'.min(150, $size[0]).'px" src="'.pun_htmlspecialchars($miniature).'" alt="'.pun_htmlspecialchars($fi[2]).'" /></a></td>'."\n";
		else
			echo "\t\t\t\t\t\t\t\t\t".'<td class="upf-c3">'.$lang_up['no_preview'].'</td>'."\n";

?>
									<td class="upf-c4"><input type="checkbox" name="delete_f[]" value="<?php echo pun_htmlspecialchars($file) ?>" tabindex="<?php echo $tabindex++ ?>" /></td>
								</tr>
<?php

	} // end foreach
	
?>
							</tbody>
						</table>
					</div>
				</div>
			</form>

			<div class="inbox">
				<div class="pagepost">
					<p class="pagelink conl"><?php echo $paging_links ?></p>
				</div>
			</div>

<?php

} // end if

?>
		</div>
	</div>
<?php
