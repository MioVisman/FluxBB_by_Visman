<?php

/**
 * Copyright (C) 2011-2023 Visman (mio.visman@yandex.ru)
 * Copyright (C) 2007 BN (bnmaster@la-bnbox.info)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (! defined('PUN')) {
	exit;
}

// Tell admin_loader.php that this is indeed a plugin and that it is loaded
define('PUN_PLUGIN_LOADED', 1);
define('PLUGIN_VERSION', '3.3.1');
define('PLUGIN_URL', pun_htmlspecialchars('admin_loader.php?plugin=' . $plugin));
define('PLUGIN_EXTS', 'webp,jpg,jpeg,png,gif,mp3,zip,rar,7z');
define('PLUGIN_NF', 25);

require PUN_ROOT . 'include/upload.php';

// Any action must be confirmed by token
if (! empty($_POST)) {
	if (function_exists('csrf_hash')) {
		confirm_referrer('AP_Upload.php');
	} else {
		check_csrf($_POST['csrf_hash'] ?? '');
	}
}

$sconf = [
	'thumb' => true === $upf_class->isResize() ? 1 : 0,
	'thumb_size' => 100,
	'thumb_perc' => 75,
	'pic_mass' => 300, //килобайт
	'pic_perc' => 75,
	'pic_w' => 1920,
	'pic_h' => 1200,
];

// обновление до версии 2.3.0
if (isset($pun_config['o_uploadile_other'])) {
	if (! isset($pun_config['o_upload_config'])) {
		$aconf = unserialize($pun_config['o_uploadile_other']);
		$aconf['pic_mass'] = (int) ($aconf['pic_mass'] / 1024);
		$pun_config['o_upload_config'] = serialize($aconf);

		$db->query('INSERT INTO ' . $db->prefix . 'config (conf_name, conf_value) VALUES (\'o_upload_config\', \'' . $db->escape($pun_config['o_upload_config']) . '\')') or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());
	}

	$db->query('DELETE FROM ' . $db->prefix . 'config WHERE conf_name=\'o_uploadile_other\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;

	if (! defined('FORUM_CACHE_FUNCTIONS_LOADED')) {
		require PUN_ROOT . 'include/cache.php';
	}

	generate_config_cache();

	$data_grs = [];
	if (isset($pun_user['g_up_ext'], $pun_user['g_up_limit'], $pun_user['g_up_max'])) {
		$result = $db->query('SELECT * FROM ' . $db->prefix . 'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

		while ($cur_group = $db->fetch_assoc($result)) {
			if ($cur_group['g_id'] == PUN_GUEST) {
				continue;
			}
			$data_grs[$cur_group['g_id']] = [
				'g_up_ext' => $cur_group['g_up_ext'],
				'g_up_max' => (int) ($cur_group['g_up_max'] / 10485.76),
				'g_up_limit' => (int) ($cur_group['g_up_limit'] / 1048576),
			];
		}
	}

	$db->drop_field('groups', 'g_up_ext') or error('Unable to drop g_up_ext field', __FILE__, __LINE__, $db->error());
	$db->drop_field('groups', 'g_up_max') or error('Unable to drop g_up_max field', __FILE__, __LINE__, $db->error());
	$db->drop_field('groups', 'g_up_limit') or error('Unable to drop g_up_limit field', __FILE__, __LINE__, $db->error());

	$db->add_field('groups', 'g_up_ext', 'VARCHAR(255)', false, PLUGIN_EXTS) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
	$db->add_field('groups', 'g_up_max', 'INT(10)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
	$db->add_field('groups', 'g_up_limit', 'INT(10)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());

	foreach ($data_grs as $g_id => $cur_group) {
		$db->query('UPDATE ' . $db->prefix . 'groups SET g_up_ext=\'' . $db->escape($cur_group['g_up_ext']) . '\', g_up_limit=' . $cur_group['g_up_limit'] . ', g_up_max=' . $cur_group['g_up_max'] . ' WHERE g_id=' . $g_id) or error('Unable to update user group list', __FILE__, __LINE__, $db->error());
	}

	$db->add_field('users', 'upload_size', 'INT(10)', false, 0) or error(sprintf($lang_up['Error DB'], 'users'), __FILE__, __LINE__, $db->error());

	if (isset($pun_user['upload'])) {
		$db->query('UPDATE ' . $db->prefix . 'users SET upload_size=ROUND(upload/10485.76)') or error('Unable to update upload size of users', __FILE__, __LINE__, $db->error());
	}

	$db->drop_field('users', 'upload') or error('Unable to drop upload field', __FILE__, __LINE__, $db->error());
}

// обновление до версии 3.1.0
if (isset($pun_config['o_upload_config']) && !isset($pun_user['g_up_perm_del'])) {
	$db->add_field('groups', 'g_up_perm_del', 'TINYINT(1)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
}

// Установка плагина/мода
if (isset($_POST['installation'])) {
	$db->add_field('users', 'upload_size', 'INT(10)', false, 0) or error(sprintf($lang_up['Error DB'], 'users'), __FILE__, __LINE__, $db->error());
	$db->add_field('groups', 'g_up_ext', 'VARCHAR(255)', false, PLUGIN_EXTS) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
	$db->add_field('groups', 'g_up_max', 'INT(10)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
	$db->add_field('groups', 'g_up_limit', 'INT(10)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());
	$db->add_field('groups', 'g_up_perm_del', 'TINYINT(1)', false, 0) or error(sprintf($lang_up['Error DB'], 'groups'), __FILE__, __LINE__, $db->error());

	$adm_max = (int) (min($upf_class->size(ini_get('upload_max_filesize')), $upf_class->size(ini_get('post_max_size'))) / 10485.76);
	$db->query('UPDATE ' . $db->prefix . 'groups SET g_up_ext=\'' . $db->escape(PLUGIN_EXTS) . '\', g_up_limit=1024, g_up_max=' . $adm_max . ' WHERE g_id=' . PUN_ADMIN) or error('Unable to update user group list', __FILE__, __LINE__, $db->error());

	$db->query('DELETE FROM ' . $db->prefix . 'config WHERE conf_name=\'o_upload_config\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('INSERT INTO ' . $db->prefix . 'config (conf_name, conf_value) VALUES (\'o_upload_config\', \'' . $db->escape(serialize($sconf)) . '\')') or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());

	if (! defined('FORUM_CACHE_FUNCTIONS_LOADED')) {
		require PUN_ROOT . 'include/cache.php';
	}

	generate_config_cache();

	redirect(PLUGIN_URL, $lang_up['Redirect']);
}

// Обновления параметров
else if (isset($_POST['update'])) {
	$g_up_ext = $_POST['g_up_ext'] ?? null;
	$g_up_ext = is_array($g_up_ext) ? array_map('pun_trim', $g_up_ext) : [];
	$g_up_max = $_POST['g_up_max'] ?? null;
	$g_up_max = is_array($g_up_max) ? array_map('floatval', $g_up_max) : [];
	$g_up_limit = $_POST['g_up_limit'] ?? null;
	$g_up_limit = is_array($g_up_limit) ? array_map('intval', $g_up_limit) : [];
	$g_up_perm_del = $_POST['g_up_perm_del'] ?? null;
	$g_up_perm_del = is_array($g_up_perm_del) ? array_map('intval', $g_up_perm_del) : [];

	if (empty($g_up_limit)) {
		$g_up_limit[PUN_ADMIN] = 1024;
		$g_up_max[PUN_ADMIN] = 1024;
	}

	$ext_bad = [];
	$result = $db->query('SELECT g_id FROM ' . $db->prefix . 'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());
	while ($cur_group = $db->fetch_assoc($result)) {
		$gid = $cur_group['g_id'];

		if ($gid == PUN_GUEST) {
			continue;
		}

		if (isset($g_up_ext[$gid])) {
			$arr = explode(',', strtolower($g_up_ext[$gid]));
			$arr = array_map('pun_trim', $arr);
			$g_ext = [];

			foreach ($arr as $ext)
			{
				if (preg_match('%[^0-9a-z]%', $ext) || $upf_class->inBlackList($ext))
					$ext_bad[] = $ext;
				else
					$g_ext[] = $ext;
			}

			$g_ext = implode(',', $g_ext);
		} else {
			$g_ext = PLUGIN_EXTS;
		}

		$g_max = ! isset($g_up_max[$gid]) || $g_up_max[$gid] < 0 ? 0 : $g_up_max[$gid];
		$g_max = (int) (100 * min($g_max, $upf_class->size(ini_get('upload_max_filesize')) / 1048576, $upf_class->size(ini_get('post_max_size')) / 1048576));
		$g_lim = ! isset($g_up_limit[$gid]) || $g_up_limit[$gid] < 0 ? 0 : $g_up_limit[$gid];
		$g_lim = min($g_lim, 20971520);

		$g_perm_del = ! isset($g_up_perm_del[$gid]) || $g_up_perm_del[$gid] < 1 ? 0 : 1;

		$db->query('UPDATE ' . $db->prefix . 'groups SET g_up_ext=\'' . $db->escape($g_ext) . '\', g_up_limit=' . $g_lim . ', g_up_max=' . $g_max . ', g_up_perm_del=' . $g_perm_del . ' WHERE g_id=' . $gid) or error('Unable to update user group list', __FILE__, __LINE__, $db->error());
	}

	if (isset($_POST['thumb'])) {
		$sconf['thumb'] = $_POST['thumb'] == '1' ? 1 : 0;
	}

	$v = $_POST['thumb_size'] ?? null;
	if (is_string($v) && $_POST['thumb_size'] > 0) {
		$sconf['thumb_size'] = (int) $v;
	}

	$v = $_POST['thumb_perc'] ?? null;
	if (is_string($v) && $v > 0 && $v <= 100) {
		$sconf['thumb_perc'] = (int) $v;
	}

	$v = $_POST['pic_mass'] ?? null;
	if (is_string($v) && $v >= 0) {
		$sconf['pic_mass'] = (int) $v;
	}

	$v = $_POST['pic_perc'] ?? null;
	if (is_string($v) && $v > 0 && $v <= 100) {
		$sconf['pic_perc'] = (int) $v;
	}

	$v = $_POST['pic_w'] ?? null;
	if (is_string($v) && $v >= 100) {
		$sconf['pic_w'] = (int) $v;
	}

	$v = $_POST['pic_h'] ?? null;
	if (is_string($v) && $v >= 100) {
		$sconf['pic_h'] = (int) $v;
	}

	$db->query('DELETE FROM ' . $db->prefix . 'config WHERE conf_name=\'o_upload_config\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;
	$db->query('INSERT INTO ' . $db->prefix . 'config (conf_name, conf_value) VALUES (\'o_upload_config\', \'' . $db->escape(serialize($sconf)) . '\')') or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());

	if (! defined('FORUM_CACHE_FUNCTIONS_LOADED')) {
		require PUN_ROOT . 'include/cache.php';
	}

	generate_config_cache();

	$mess = $lang_up['Redirect'];

	if (! empty($ext_bad))
	{
		$mess = sprintf($lang_up['Bad file extensions'], pun_htmlspecialchars(implode(', ', $ext_bad))).$mess;

		if ($pun_config['o_redirect_delay'] < 5)
			$pun_config['o_redirect_delay'] = 5;
	}

	redirect(PLUGIN_URL, $mess);
}

// Удаление мода
else if (isset($_POST['restore'])) {
	$db->drop_field('users', 'upload_size') or error('Unable to drop upload field', __FILE__, __LINE__, $db->error());
	$db->drop_field('groups', 'g_up_ext') or error('Unable to drop g_up_ext field', __FILE__, __LINE__, $db->error());
	$db->drop_field('groups', 'g_up_max') or error('Unable to drop g_up_max field', __FILE__, __LINE__, $db->error());
	$db->drop_field('groups', 'g_up_limit') or error('Unable to drop g_up_limit field', __FILE__, __LINE__, $db->error());
	$db->drop_field('groups', 'g_up_perm_del') or error('Unable to drop g_up_perm_del field', __FILE__, __LINE__, $db->error());

	$db->query('DELETE FROM ' . $db->prefix . 'config WHERE conf_name=\'o_upload_config\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;

	if (! defined('FORUM_CACHE_FUNCTIONS_LOADED')) {
		require PUN_ROOT . 'include/cache.php';
	}

	generate_config_cache();

	redirect(PLUGIN_URL, $lang_up['Redirect']);
}

if (isset($pun_config['o_upload_config'])) {
	$aconf = unserialize($pun_config['o_upload_config']);
} else {
	$aconf = $sconf;
	$aconf['thumb'] = 0;
	define('PLUGIN_OFF', 1);
}

$upf_mem = 'img/members/';
$upf_regx = '%^img/members/(\d+)/([\w-]+)\.(\w+)$%iD';

// #############################################################################

// Удаление файлов
if (isset($_POST['delete'], $_POST['delete_f']) && is_array($_POST['delete_f'])) {
	$error = false;

	if (is_dir(PUN_ROOT . $upf_mem)) {
		$au = [];
		foreach ($_POST['delete_f'] as $file) {
			if (
				is_string($file)
				&& preg_match($upf_regx, $file, $matches)
				&& false === $upf_class->inBlackList($matches[3])
				&& 'mini_' !== substr($matches[2], 0, 5)
				&& is_file(PUN_ROOT . $file)
			) {
				if (unlink(PUN_ROOT . $file)) {
					$id = (int) $matches[1];
					$au[$id] = $id;
					if (is_file(PUN_ROOT . $upf_mem . $matches[1] . '/mini_' . $matches[2] . '.' . $matches[3])) {
						unlink(PUN_ROOT . $upf_mem . $matches[1] . '/mini_' . $matches[2] . '.' . $matches[3]);
					}
				} else {
					$error = true;
				}
			} else {
				$error = true;
			}
		}

		if (! defined('PLUGIN_OFF')) {
			foreach ($au as $user) {
				// Считаем общий размер файлов юзера
				$upload = (int) ($upf_class->dirSize(PUN_ROOT . $upf_mem . $user . '/') / 10485.76);
				$db->query('UPDATE ' . $db->prefix . 'users SET upload_size=\'' . $upload . '\' WHERE id=' . $user) or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());
			}
		}
	}

	$p = empty($_GET['p']) || $_GET['p'] < 1 ? 1 : intval($_GET['p']);

	if ($error) {
		if ($pun_config['o_redirect_delay'] < 5) {
			$pun_config['o_redirect_delay'] = 5;
		}
		redirect(PLUGIN_URL . ($p > 1 ? '&amp;p=' . $p : ''), $lang_up['Error'] . $lang_up['Error delete']);
	} else {
		redirect(PLUGIN_URL . ($p > 1 ? '&amp;p=' . $p : ''), $lang_up['Redirect delete']);
	}
}

if (file_exists(PUN_ROOT . 'style/' . $pun_user['style'] . '/upfiles.css')) {
	$s = '<link rel="stylesheet" type="text/css" href="style/' . $pun_user['style'] . '/upfiles.css" />';
} else {
	$s = '<link rel="stylesheet" type="text/css" href="style/imports/upfiles.css" />';
}
$tpl_main = str_replace('</head>', $s . "\n</head>", $tpl_main);

// Display the admin navigation menu
generate_admin_menu($plugin);

$tabindex = 1;
$upf_token = function_exists('csrf_hash') ? csrf_hash('AP_Upload.php') : pun_csrf_token();

?>
	<div id="upf-block" class="plugin blockform">
		<h2><span>Plugin Upload Files v<?= PLUGIN_VERSION ?></span></h2>
		<div class="box">
			<div class="inbox">
				<p><?= $lang_up['plugin_desc'] ?></p>
				<form action="<?= PLUGIN_URL ?>" method="post">
					<p>
						<input type="hidden" name="csrf_hash" value="<?= $upf_token ?>" />
<?php

$disbl = true === $upf_class->isResize() ? '' : '" disabled="disabled';
$stthumb = '' === $disbl && 1 == $aconf['thumb'] ? '' : '" disabled="disabled';

if (defined('PLUGIN_OFF')) {

?>
						<input type="submit" name="installation" value="<?= $lang_up['Install'] ?>" />&#160;<?= $lang_up['Install info'] ?><br />
					</p>
				</form>
			</div>
		</div>
<?php

} else {

?>
						<input type="submit" name="update" value="<?= $lang_up['Update'] ?>" />&#160;<?= $lang_up['Update info'] ?><br />
						<input type="submit" name="restore" value="<?= $lang_up['Uninstall'] ?>" />&#160;<?= $lang_up['Uninstall info'] ?><br /><br />
					</p>
				</form>
			</div>
		</div>
		<h2 class="block2"><span><?= $lang_up['configuration'] ?></span></h2>
		<div class="box">
			<form method="post" action="<?= PLUGIN_URL ?>">
				<p class="submittop"><input type="submit" name="update" value="<?= $lang_up['Update'] ?>" tabindex="<?= $tabindex++ ?>" /></p>
				<div class="inform">
					<fieldset>
						<legend><?= $lang_up['legend_2'] ?></legend>
						<div class="infldset">
						<table>
							<tr>
								<th scope="row"><label><?= $upf_class->getLibName() ?></label></th>
								<td><?= pun_htmlspecialchars((string) $upf_class->getLibVersion()) ?></td>
							</tr>
							<tr>
								<th scope="row"><label><?= $lang_up['pictures'] ?></label></th>
								<td>
									<?= $lang_up['for pictures'] . "\n" ?>
									<input type="text" name="pic_mass" size="8" maxlength="8" tabindex="<?= $tabindex++ ?>" value="<?= pun_htmlspecialchars((string) $aconf['pic_mass']) . $disbl ?>" />&#160;<?= $lang_up['kbytes'] . ":\n" ?><br />
									&#160;*&#160;<?= $lang_up['Install quality'] . "\n" ?>
									<input type="text" name="pic_perc" size="4" maxlength="3" tabindex="<?= $tabindex++ ?>" value="<?= pun_htmlspecialchars((string) $aconf['pic_perc']) . $disbl ?>" />&#160;%<br />
									&#160;*&#160;<?= $lang_up['Size not more'] . "\n" ?>
									<input type="text" name="pic_w" size="4" maxlength="4" tabindex="<?= $tabindex++ ?>" value="<?= pun_htmlspecialchars((string) $aconf['pic_w']) . $disbl ?>" />&#160;x
									<input type="text" name="pic_h" size="4" maxlength="4" tabindex="<?= $tabindex++ ?>" value="<?= pun_htmlspecialchars((string) $aconf['pic_h']) . $disbl ?>" />&#160;<?= $lang_up['px'] . "\n" ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?= $lang_up['thumb'] ?></label></th>
								<td>
									<input type="radio" tabindex="<?= ($tabindex++) . $disbl ?>" name="thumb" value="1"<?= $aconf['thumb'] == 1 ? ' checked="checked"' : '' ?> /> <strong><?= $lang_admin_common['Yes'] ?></strong>
									&#160;&#160;&#160;
									<input type="radio" tabindex="<?= ($tabindex++) . $disbl ?>" name="thumb" value="0"<?= $aconf['thumb'] == 0 ? ' checked="checked"' : '' ?> /> <strong><?= $lang_admin_common['No'] ?></strong>
									<br />
									&#160;*&#160;<?= $lang_up['thumb_size'] . "\n" ?>
									<input type="text" name="thumb_size" size="4" maxlength="4" tabindex="<?= $tabindex++ ?>" value="<?= pun_htmlspecialchars((string) $aconf['thumb_size']) . $disbl ?>" />&#160;<?= $lang_up['px'] . "\n" ?><br />
									&#160;*&#160;<?= $lang_up['quality'] . "\n" ?>
									<input type="text" name="thumb_perc" size="4" maxlength="3" tabindex="<?= $tabindex++ ?>" value="<?= pun_htmlspecialchars((string) $aconf['thumb_perc']) . $disbl ?>" />&#160;%
								</td>
							</tr>
						</table>
						</div>
					</fieldset>
				</div>

				<div class="inform upf-gr-set">
					<fieldset>
						<legend><?= $lang_up['groups'] ?></legend>
						<div class="infldset">
							<div class="inbox">
								<p>1* - <?= $lang_up['laws'] ?><br />
								2* - <?= $lang_up['maxsize_member'] ?><br />
								3* - <?= $lang_up['limit_member'] ?><br />
								4* - <?= $lang_up['allow_delete'] ?></p>
							</div>
							<table class="aligntop">
							<thead>
								<tr>
									<th class="tcl" scope="col"><?= $lang_up['group'] ?></th>
									<th class="tc2" scope="col">1*</th>
									<th class="tcr" scope="col">2*</th>
									<th class="tcr" scope="col">3*</th>
									<th class="tc3" scope="col">4*</th>
								</tr>
							</thead>
							<tbody>
<?php

	$result = $db->query('SELECT * FROM ' . $db->prefix . 'groups ORDER BY g_id') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());

	while ($cur_group = $db->fetch_assoc($result)) {
		if ($cur_group['g_id'] != PUN_GUEST) {
			if (! isset($cur_group['g_up_ext'])) {
				$cur_group['g_up_max'] = $cur_group['g_up_limit'] = $cur_group['g_up_perm_del'] = 0;
				$cur_group['g_up_ext'] = '';
			}

?>
								<tr>
									<td class="tcl"><?= pun_htmlspecialchars($cur_group['g_title']) ?></td>
									<td class="tc2"><input type="text" name="g_up_ext[<?= $cur_group['g_id'] ?>]" value="<?= pun_htmlspecialchars($cur_group['g_up_ext']) ?>" tabindex="<?= $tabindex++ ?>" size="40" maxlength="255" /></td>
									<td class="tcr"><input type="text" name="g_up_max[<?= $cur_group['g_id'] ?>]" value="<?= $cur_group['g_up_max'] / 100 ?>" tabindex="<?= $tabindex++ ?>" size="10" maxlength="10" /></td>
									<td class="tcr"><input type="text" name="g_up_limit[<?= $cur_group['g_id'] ?>]" value="<?= $cur_group['g_up_limit'] ?>" tabindex="<?= $tabindex++ ?>" size="10" maxlength="10" /></td>
									<td class="tc3"><input type="checkbox" name="g_up_perm_del[<?= $cur_group['g_id'] ?>]" value="1" tabindex="<?= $tabindex++ ?>"<?= ($cur_group['g_up_perm_del'] == 1 ? ' checked="checked"' : '') ?>></td>
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

				<p class="submitend">
					<input type="hidden" name="csrf_hash" value="<?= $upf_token ?>" />
					<input type="submit" name="update" value="<?= $lang_up['Update'] ?>" tabindex="<?= $tabindex++ ?>" />
				</p>
				<div class="inform">
					<fieldset>
						<legend><?= $lang_up['legend_1'] ?></legend>
						<div class="infldset">
							<label for="mo"><?= $lang_up['mo'] ?></label> <input type="text" name="mo" id="mo" size="15" tabindex="<?= $tabindex++ ?>" /> <input type="button" value="<?= $lang_up['convert'] ?>" tabindex="<?= $tabindex++ ?>" onclick="javascript:document.getElementById('ko').value=document.getElementById('mo').value*1024; document.getElementById('o').value=document.getElementById('mo').value*1048576;" />
							<label for="ko"><?= $lang_up['ko'] ?></label> <input type="text" name="ko" id="ko" size="15" tabindex="<?= $tabindex++ ?>" /> <input type="button" value="<?= $lang_up['convert'] ?>" tabindex="<?= $tabindex++ ?>" onclick="javascript:document.getElementById('mo').value=document.getElementById('ko').value/1024; document.getElementById('o').value=document.getElementById('ko').value*1024;"/>
							<label for="o"><?= $lang_up['o'] ?></label> <input type="text" name="o" id="o" size="15" tabindex="<?= $tabindex++ ?>" /> <input type="button" value="<?= $lang_up['convert'] ?>" tabindex="<?= $tabindex++ ?>" onclick="javascript:document.getElementById('mo').value=document.getElementById('o').value/1048576; document.getElementById('ko').value=(document.getElementById('o').value*1024)/1048576;"/>
							</div>
					</fieldset>
				</div>
			</form>
		</div>
<?php

}

// #############################################################################

$files = [];
if (is_dir(PUN_ROOT . $upf_mem)) {
	$af = [];
	$ad = scandir(PUN_ROOT . $upf_mem);

	foreach ($ad as $f) {
		if ('.' === $f[0] || ! is_dir(PUN_ROOT . $upf_mem . $f)) {
			continue;
		}

		$dir = $upf_mem . $f . '/';
		$open = opendir(PUN_ROOT . $dir);
		while (false !== ($file = readdir($open))) {
			if (
				'.' === $file[0]
				|| '#' === $file[0]
				|| 'mini_' === substr($file, 0, 5)
				|| true === $upf_class->inBlackList(substr(strrchr($file, '.'), 1))
				|| ! is_file(PUN_ROOT . $dir . $file)
			) {
				continue;
			}

			$time = filemtime(PUN_ROOT . $dir . $file) . $file . $f;
			$af[$time] = $dir . $file;
		}
		closedir($open);
	}

	unset($ad);

	if (! empty($af)) {
		$num_pages = ceil(count($af) / PLUGIN_NF);
		$p = empty($_GET['p']) || $_GET['p'] < 1 ? 1 : intval($_GET['p']);
		if ($p > $num_pages) {
			header('Location: ' . PLUGIN_URL . '&p=' . $num_pages . '#gofile');
			exit;
		}

		$start_from = PLUGIN_NF * ($p - 1);

		// Generate paging links
		$paging_links = '<span class="pages-label">' . $lang_common['Pages'] . ' </span>' . paginate($num_pages, $p, PLUGIN_URL);
		$paging_links = preg_replace('%href="([^">]+)"%', 'href="$1#gofile"', $paging_links);

		krsort($af);
		$files = array_slice($af, $start_from, PLUGIN_NF);
		unset($af);
	}
}

?>
		<h2 id="gofile" class="block2"><span><?= $lang_up['Member files'] ?></span></h2>
		<div class="box">
<?php

if (empty($files)) {

?>
			<div class="inbox">
				<p><?= $lang_up['No upfiles'] ?></p>
			</div>
<?php

} else {

?>

			<div class="inbox">
				<div class="pagepost">
					<p class="pagelink conl"><?= $paging_links ?></p>
				</div>
			</div>

			<form method="post" action="<?= PLUGIN_URL . ($p > 1 ? '&amp;p=' . $p : '') . '#gofile' ?>">
				<div class="inform">
					<p class="submittop">
						<input type="hidden" name="csrf_hash" value="<?= $upf_token ?>" />
						<input type="submit" name="update_thumb" value="<?= $lang_up['update_thumb'] . $stthumb ?>" />
					</p>
					<div class="infldset">
						<table id="upf-table" class="aligntop">
							<thead>
								<tr>
									<th class="upf-c1" scope="col"><?= $lang_up['th0'] ?></th>
									<th class="upf-c2" scope="col"><?= $lang_up['th1'] ?></th>
									<th class="upf-c3" scope="col"><?= $lang_up['th2'] ?></th>
									<th class="upf-c4" scope="col"><input type="submit" value="<?= $lang_up['delete'] ?>" name="delete" tabindex="<?= $tabindex++ ?>" /></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th class="upf-c1"><?= $lang_up['th0'] ?></th>
									<th class="upf-c2"><?= $lang_up['th1'] ?></th>
									<th class="upf-c3"><?= $lang_up['th2'] ?></th>
									<th class="upf-c4"><input type="submit" value="<?= $lang_up['delete'] ?>" name="delete" tabindex="<?= $tabindex++ ?>" /></th>
								</tr>
							</tfoot>
							<tbody>
<?php

	// данные по юзерам
	$au = [];
	foreach ($files as $file) {
		if (preg_match($upf_regx, $file, $fi)) {
			$id = (int) $fi[1];
			$au[$id] = $id;
		}
	}
	$result = $db->query('SELECT id, username, group_id FROM ' . $db->prefix . 'users WHERE id IN (' . implode(',', $au) . ')') or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
	$au = $ag = [];
	while ($u = $db->fetch_assoc($result)) {
		$au[$u['id']] = $u['username'];
		$ag[$u['id']] = $u['group_id'];
	}
	// данные по группам
	$extsup = [];
	$result = $db->query('SELECT * FROM ' . $db->prefix . 'groups') or error('Unable to fetch user group list', __FILE__, __LINE__, $db->error());
	while ($g = $db->fetch_assoc($result)) {
		if (isset($g['g_up_ext'])) {
			$extsup[$g['g_id']] = explode(',', $g['g_up_ext'] . ',' . strtoupper($g['g_up_ext']));
		} else {
			$extsup[$g['g_id']] = [];
		}
	}

	$upf_img_exts = ['jpg', 'jpeg', 'gif', 'png', 'bmp', 'webp'];
	foreach ($files as $file) {
		if (! preg_match($upf_regx, $file, $fi)) {
			continue;
		}

		$fancybox = in_array(strtolower($fi[3]), $upf_img_exts) ? '" class="fancy_zoom" rel="vi001' : '';
		$dir = $upf_mem . $fi[1] . '/';
		$size_file = file_size(filesize(PUN_ROOT . $file));
		$miniature = $dir . 'mini_' . $fi[2] . '.' . $fi[3];

		if (
			isset($_POST['update_thumb'])
			&& 1 == $aconf['thumb']
			&& true === $upf_class->loadFile(PUN_ROOT . $file)
			&& true === $upf_class->isImage()
			&& false !== $upf_class->loadImage()
		) {
			$upf_class->setImageQuality($aconf['thumb_perc']);
			$scaleResize = $upf_class->resizeImage(0, $aconf['thumb_size']);

			if (false !== $scaleResize) {
				if ($scaleResize < 1) {
					$upf_class->saveImage(PUN_ROOT . $miniature, true);
				} else {
					copy(PUN_ROOT . $file, PUN_ROOT . $miniature);
					chmod(PUN_ROOT . $miniature, 0644);
				}
			}
		}

?>
								<tr>
									<td class="upf-c1"><?= (isset($au[$fi[1]]) ? pun_htmlspecialchars($au[$fi[1]]) : '&#160;') ?></td>
									<td class="upf-c2"><a href="<?= pun_htmlspecialchars($file) ?>"><?= pun_htmlspecialchars($fi[2]) ?></a> [<?= pun_htmlspecialchars($size_file) ?>].[<?= (isset($ag[$fi[1]]) && in_array($fi[3], $extsup[$ag[$fi[1]]]) ? pun_htmlspecialchars($fi[3]) : '<span style="color: #ff0000"><strong>' . pun_htmlspecialchars($fi[3]) . '</strong></span>') ?>]</td>
<?php

		if (is_file(PUN_ROOT . $miniature)) {

?>
									<td class="upf-c3">
										<a href="<?= pun_htmlspecialchars($file) . $fancybox ?>">
											<img src="<?= pun_htmlspecialchars($miniature) ?>" alt="<?= pun_htmlspecialchars($fi[2]) ?>" />
										</a>
									</td>
<?php

		} else {

?>
									<td class="upf-c3"><?= $lang_up['no_preview'] ?></td>
<?php

		}

?>
									<td class="upf-c4"><input type="checkbox" name="delete_f[]" value="<?= pun_htmlspecialchars($file) ?>" tabindex="<?= $tabindex++ ?>" /></td>
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
					<p class="pagelink conl"><?= $paging_links ?></p>
				</div>
			</div>

<?php

} // end if

?>
		</div>
	</div>
<?php
