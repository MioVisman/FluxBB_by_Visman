<?php

/**
 * Copyright (C) 2011-2013 Visman (visman@inbox.ru)
 * Copyright (C) 2002-2005 Rickard Andersson (rickard@punbb.org)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view'], false, '403 Forbidden');

if ($pun_user['is_guest'] || !isset($pun_user['g_up_ext']) || empty($pun_config['o_uploadile_other']))
	message($lang_common['Bad request'], false, '404 Not Found');

require PUN_ROOT.'include/upload.php';

define('PLUGIN_REF', 'upfiles.php');

if (!isset($_GET['id']))
{
	$id = $pun_user['id'];

	define('PUN_HELP', 1);
	define('PUN_ACTIVE_PAGE', 'upfiles');
	define('PLUGIN_URL', PLUGIN_REF);
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_up['popup_title']);
	$fpr = false;
	$extsup = $pun_user['g_up_ext'];
	$limit = $pun_user['g_up_limit'];
	$maxsize = $pun_user['g_up_max'];
}
else
{
	$id = intval($_GET['id']);
	if ($id < 2 || ($pun_user['g_id'] != PUN_ADMIN && $id != $pun_user['id']))
		message($lang_common['Bad request'], false, '404 Not Found');
		
	$result = $db->query('SELECT u.username, u.upload, g.g_up_ext, g.g_up_max, g.g_up_limit FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON u.group_id=g.g_id WHERE u.id='.$id) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
	if (!$db->num_rows($result))
		message($lang_common['Bad request'], false, '404 Not Found');

	list($usname, $upload, $extsup, $maxsize, $limit) = $db->fetch_row($result);

	define('PUN_ACTIVE_PAGE', 'profile');
	define('PLUGIN_URL', PLUGIN_REF.'?id='.$id);
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_up['popup_title']);
	$fpr = true;
}

if ($pun_user['g_id'] != PUN_ADMIN && $limit*$maxsize == 0)
	message($lang_common['Bad request'], false, '404 Not Found');

$prcent = ($limit == 0) ? 100 : ceil($pun_user['upload']*100/$limit);
$prcent = ($prcent > 100) ? 100 : $prcent;

require PUN_ROOT.'header.php';

$dir = 'img/members/'.$id.'/';
$aconf = unserialize($pun_config['o_uploadile_other']);
$extsup = explode(',', $extsup.','.strtoupper($extsup));
// #############################################################################
// Удаление файлов
if (isset($_POST['delete']) && isset($_POST['max_id']))
{
	confirm_referrer(PLUGIN_REF);

	$error = 0;
	$maxidf = intval($_POST['max_id']);
	
	if (is_dir(PUN_ROOT.$dir))
	{
		for ($u = 1 ; $u < $maxidf ; $u++)
		{
			if (isset($_POST['delete_'.$u]))
			{
				$fichier = parse_file(pun_trim($_POST['delete_'.$u]));
				$ext = strtolower(substr(strrchr($fichier,  "." ), 1)); // берем расширение файла
				if ($fichier[0] != '.' && $ext != '' && !in_array($ext, $extforno) && is_file(PUN_ROOT.$dir.$fichier))
				{
					$d1 = $d2 = unlink(PUN_ROOT.$dir.$fichier);
					if (is_file(PUN_ROOT.$dir.'mini_'.$fichier))
						$d2 = unlink(PUN_ROOT.$dir.'mini_'.$fichier);
					if (!$d1 || !$d2)
						$error++;
				}
			}
		}

		// Считаем общий размер файлов юзера
		$upload = dir_size($dir);
		$db->query('UPDATE '.$db->prefix.'users SET upload=\''.$upload.'\' WHERE id='.$id) or error($lang_up['err_insert'], __FILE__, __LINE__, $db->error());
	}

	if ($error == 0)
		redirect(PLUGIN_URL, $lang_up['delete_success']);
	else
	{
		$pun_config['o_redirect_delay'] = 5;
		redirect(PLUGIN_URL, $lang_up['err_delete']);
	}
}

// Загрузка файла
else if (isset($_FILES['fichier']) && $id == $pun_user['id'] && $_FILES['fichier']['error'] == 0 && is_uploaded_file($_FILES['fichier']['tmp_name']))
{
	confirm_referrer(PLUGIN_REF);

	$pun_config['o_redirect_delay'] = 5;

	$f = pathinfo(parse_file($_FILES['fichier']['name']));
	if (empty($f['extension']))
		redirect(PLUGIN_URL, $lang_up['err_noExtension']);

	// Проверяем расширение
	$ext = strtolower($f['extension']);
	if (in_array($ext, $extforno) || !in_array($ext, $extsup))
		redirect(PLUGIN_URL, $lang_up['err_extension']);

	// Проверяется максимальный размер файла
	if ($_FILES['fichier']['size'] > $maxsize)
		redirect(PLUGIN_URL, $lang_up['err_size']);

	// Проверяем допустимое пространство
	if ($_FILES['fichier']['size']+$pun_user['upload'] > $limit)
		redirect(PLUGIN_URL, $lang_up['err_espace']);

	// Проверяем картинку (флэш) на правильность
	$isimg2 = (in_array($ext, $extimage));
	$size = @getimagesize($_FILES['fichier']['tmp_name']);
	if (($size === false && $isimg2) || ($size !== false && !$isimg2))
		redirect(PLUGIN_URL, $lang_up['err_image']);
	if ($isimg2)
	{
		$isimge = false;
		
		if (empty($size[0]) || empty($size[1]) || empty($size[2]))
			$isimge = true;
		else if (!isset($extimage2[$size[2]]) || !in_array($ext, $extimage2[$size[2]]))
			$isimge = true;
		if ($isimge)
			redirect(PLUGIN_URL, $lang_up['err_image']);
	}

	// обрабатываем имя
	$name = str_replace('.', '_', $f['filename']);
	if (substr($name, 0, 5) == 'mini_')
		$name = substr($name, 5);
	if ($name == '')
		$name = 'none';
	if (strlen($name) > 100)
		$name = substr($name, 0, 100);
	if (is_file(PUN_ROOT.$dir.$name.'.'.$ext) || is_file(PUN_ROOT.$dir.$name.'.jpeg')) // если уже есть, переименуем
		$name = $name.'_'.parse_file(date('Ymd\-Hi', time()));

	if (!is_dir(PUN_ROOT.'img/members/'))
		mkdir(PUN_ROOT.'img/members', 0755);
	if (!is_dir(PUN_ROOT.$dir))
		mkdir(PUN_ROOT.'img/members/'.$id, 0755);

	if ($_FILES['fichier']['size'] > $aconf['pic_mass'] && $isimg2 && $gd && array_key_exists($ext,$extimageGD))
	{
		$ext_ml = img_resize($_FILES['fichier']['tmp_name'], $dir, $name, $ext, $aconf['pic_w'], $aconf['pic_h'], $aconf['pic_perc'], true);
		if ($ext_ml === false)
			redirect(PLUGIN_URL, $lang_up['err_image2']);

		list($name, $ext) = $ext_ml;
	}
	else
	{
		$error = isXSSattack($_FILES['fichier']['tmp_name']);
		if ($error !== false)
			redirect(PLUGIN_URL, $error);

		if (!@move_uploaded_file($_FILES['fichier']['tmp_name'], PUN_ROOT.$dir.$name.'.'.$ext))
			redirect(PLUGIN_URL, $lang_up['err_Move failed']);
		@chmod(PUN_ROOT.$dir.$name.'.'.$ext, 0644);
	}

	// Создание привьюшки (только для поддерживаемых GD форматов)
	if ($aconf['thumb'] == 1 && $isimg2 && $gd && array_key_exists($ext,$extimageGD))
		img_resize(PUN_ROOT.$dir.$name.'.'.$ext, $dir, 'mini_'.$name, $ext, 0, $aconf['thumb_size'], $aconf['thumb_perc']);

	// Считаем общий размер файлов юзера
	$upload = dir_size($dir);
	$db->query('UPDATE '.$db->prefix.'users SET upload=\''.$upload.'\' WHERE id='.$id) or error($lang_up['err_insert'], __FILE__, __LINE__, $db->error());

	$pun_config['o_redirect_delay'] = '1';
	redirect(PLUGIN_URL, $lang_up['modif_success']);
}

// Ошибка при загрузке
else if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] != 0)
{
	switch($_FILES['fichier']['error'])
	{
		case '1':
			$s_erreur = $_FILES['fichier']['name'].': '.$lang_up['err_size'].' ( '.ini_get('upload_max_filesize').' )';
			break;
		case '2':
			$s_erreur = $lang_up['err_size'];
			break;
		case '3':
			$s_erreur = $lang_up['err_4'];
			break;
		case '4':
			$s_erreur = $lang_up['err_1'];
			break;
		default:
			$s_erreur = $lang_up['err_4'];
			break;
	}
	$pun_config['o_redirect_delay'] = '5';
	redirect(PLUGIN_URL, pun_htmlspecialchars($s_erreur));
}

// #############################################################################
$maxidf = 1;
$tabi = 0;

$vcsrf = (function_exists('csrf_hash')) ? csrf_hash() : '1';

if (!$fpr)
{

?>
<script type="text/javascript">
/* <![CDATA[ */
function insert_file(url, mini_url)
{
	url = '<?php echo pun_htmlspecialchars(get_base_url(true).'/'.$dir) ?>' + url;
	if ( (new String(mini_url)).length > 0)
		mini_url = '<?php echo pun_htmlspecialchars(get_base_url(true).'/'.$dir) ?>' + mini_url;
	var arr = url.match(/.*\/img\/members\/\d+\/(.+)$/),
			input = window.opener.document.getElementsByName("req_message").item(0);
	if (arr != null) {var tt = arr[1]} else {var tt = '<?php echo $lang_up['texte']; ?>'}
	input.focus();

	if (typeof document.selection != 'undefined')/* --- Pour IE --- */
	{
		var range = document.selection.createRange();
		var insText = range.text;
		if (mini_url == url)
		{
			input.value += insText + '[img]' + url + '[/img]';
			if (url.length == 0)
			{
				range.move('character', -6);
			}
		}
		else if (mini_url != '' && mini_url != url)
		{
			input.value += insText + '[url=' + url + '][img]' + mini_url + '[/img][/url]';
			if (mini_url.length == 0 && url.length == 0)
			{
				range.move('character', -18);
			}
			else if (mini_url.length == 0 && url.length != 0)
			{
				range.move('character', -18  + url.length);
			}
			else if (mini_url.length != 0 && url.length == 0)
			{
				range.move('character', -17);
			}
		}
		else
		{
			input.value += insText + '[url=' + url + ']' + tt + '[/url]';
			if (url.length == 0)
			{
				range.movestart('character', 5);
			}
			else
			{
				range.movestart('character', 5 + url.length + 1);
			}
		}
		range.select();
	}
	else if (typeof input.selectionStart != 'undefined') /* --- Navigateurs récents (FF) --- */
	{
		var start = input.selectionStart;
		var end = input.selectionEnd;
		var selText = input.value.substring(start, end);
		var pos;

		if (mini_url == url)
		{
			input.value = input.value.substr(0, start) + selText + '[img]' + url + '[/img]' + input.value.substr(end);
			if (url.length == 0)
			{
				pos = start + 5;
			}
			else
			{
				pos = start + 5 + url.length + 6;
			}
		}
		else if (mini_url != '' && mini_url != url)
		{
			input.value = input.value.substr(0, start) + selText + '[url=' + url + '][img]' + mini_url + '[/img][/url]' + input.value.substr(end);
			if (mini_url.length == 0 && url.length == 0)
			{
				pos = start + 5;
			}
			else if (mini_url.length == 0 && url.length != 0)
			{
				pos = start + 5;
			}
			else if (mini_url.length != 0 && url.length == 0)
			{
				pos = start + 5 + mini_url.length + 6;
			}
			else
			{
				pos = start + 5 + mini_url.length + 6 + url.length + 12;
			}
		}
		else
		{
			input.value = input.value.substr(0, start) + selText + '[url=' + url + ']' + tt +'[/url]' + input.value.substr(end);
			if (url.length == 0)
			{
				pos = start + 5;
			}
			else
			{
				pos = start + 5 + url.length + 1;
			}
		}

		input.selectionStart = pos;
		input.selectionEnd = pos;
	}
	else /* --- Autres navigateurs --- */
	{
		var pos;
		var re = new RegExp('^[0-9]{0,3}$');
		while(!re.test(pos))
		{
			pos = prompt("insertion (0.." + input.value.length + "):", "0");
		}
		if (pos > input.value.length)
		{
			pos = input.value.length;
		}
		var insText = prompt(tt);
		input.value = input.value.substr(0, pos) + insText + '[img]' + url + '[/img]' + input.value.substr(pos);
	}
	return false;
}
/* ]]> */
</script>
<?php

}
else
{
	// Load the profile.php language file
	require PUN_ROOT.'lang/'.$pun_user['language'].'/profile.php';

	generate_profile_menu('upload');
}

?>
	<div id="uploadile" class="blockform">
<?php

if ($id == $pun_user['id'])
{
	$tit = $lang_up['titre_4'];
	$legend = sprintf($lang_up['info_4'], $prcent, '%', $prcent, '%', file_size($pun_user['upload']),file_size($limit));

?>
		<h2><span><?php echo $lang_up['titre_2'] ?></span></h2>
		<div class="box">
			<form method="post" action="<?php echo PLUGIN_URL ?>" enctype="multipart/form-data">
				<input type="hidden" name="csrf_hash" value="<?php echo $vcsrf ?>" />
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $maxsize; ?>" />
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_up['legend'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_up['fichier'] ?></p>
							<input type="file" id="fichier" name="fichier" tabindex="<?php echo $tabi++ ?>" />
							<p><?php	printf($lang_up['info_2'], file_size($maxsize), pun_htmlspecialchars(str_replace(',', ', ', $pun_user['g_up_ext']))) ?></p>
							<p><input type="submit" name="submit" value="<?php echo $lang_up['submit'] ?>" tabindex="<?php echo $tabi++ ?>" /></p>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
<?php

}
else
{
	$tit = pun_htmlspecialchars($usname).' - '.$lang_up['upfiles'];
	$legend = sprintf($lang_up['info_4b'], file_size($upload));
}

?>
		<h2><span><?php echo $tit ?></span></h2>
		<div class="box">
			<form method="post" action="<?php echo PLUGIN_URL ?>">
				<div class="inform">
					<fieldset>
					<legend><?php echo $legend ?></legend>
<?php

$files = array();
if (is_dir(PUN_ROOT.$dir))
{
	$open = opendir(PUN_ROOT.$dir);
	while (($file = readdir($open)) !== false)
	{
		if (is_file(PUN_ROOT.$dir.$file))
		{
			$ext = strtolower(substr(strrchr($file, '.'), 1));
			if (!in_array($ext, $extforno) && $file[0] != '#'  && substr($file, 0, 5) != 'mini_')
			{
				$time = filemtime(PUN_ROOT.$dir.$file).$file;
				$filesvar[$time] = $dir.$file;
			}
		}
	}
	closedir($open);
	if (isset($filesvar))
	{
		krsort($filesvar);
		foreach($filesvar as $time => $file)
		{
			$files[] = $file;
		}
	}
}

if (!empty($files))
{
	if ($fpr)
		echo "\t\t\t\t\t".'<div class="infldset" style="overflow: auto; padding: 0;">'."\n";
	else
		echo "\t\t\t\t\t".'<div class="infldset" style="height:385px; overflow: auto; padding: 0;">'."\n";

?>
						<table>
							<thead>
								<tr>
									<th class="tc1" scope="col" style="width:80%;"><?php echo $lang_up['th'] ?></th>
									<th class="tc1" scope="col"><?php echo $lang_up['th2'] ?></th>
									<th><input type="submit" value="<?php echo $lang_up['delete'] ?>" name="delete" tabindex="<?php echo $tabi++ ?>" /></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th class="tc1" style="width:80%;"><?php echo $lang_up['th'] ?></th>
									<th class="tc1"><?php echo $lang_up['th2'] ?></th>
									<th><input type="submit" value="<?php echo $lang_up['delete'] ?>" name="delete" tabindex="<?php echo $tabi++ ?>" /></th>
								</tr>
							</tfoot>
							<tbody>
<?php
	$regx = '%^img/members/'.$id.'/(.+)\.([0-9a-zA-Z]+)$%i';
	foreach($files as $fichier)
	{
		preg_match($regx, $fichier, $fi);
		if (!isset($fi[1]) || !isset($fi[2]) || in_array(strtolower($fi[2]), $extforno))
			continue;

		$fb = in_array(strtolower($fi[2]), array('jpg', 'jpeg', 'gif', 'png', 'bmp')) ? '" class="fancy_zoom" rel="vi001' : '';
		$size_fichier = file_size(filesize(PUN_ROOT.$fichier));
		$f = $fi[1].'.'.$fi[2];
		$m = 'mini_'.$f;
		$mini = $dir.$m;
		$fmini = (is_file(PUN_ROOT.$mini));
?>
								<tr>
<?php
		if (!$fpr) // вслывающее окно
		{
?>
									<td class="tc1">
										<input type="text" size="25" tabindex="<?php echo $tabi++ ?>" value="<?php echo pun_htmlspecialchars(get_base_url(true).'/'.$fichier) ?>" />
										<input type="button" value="<?php echo $lang_up['insert'] ?>" onclick="return insert_file(<?php echo '\''.$f.'\', \''.($fmini ? $f : '').'\'' ?>);" />
<?php
			if ($fmini)
			{
?>
										<br />
										<input type="text" size="25" tabindex="<?php echo $tabi++ ?>" value="<?php echo pun_htmlspecialchars(get_base_url(true).'/'.$mini) ?>" />
										<input type="button" value="<?php echo $lang_up['insert_thumbnail'] ?>" onclick="return insert_file('<?php echo $f ?>','<?php echo $m ?>');" />
<?php
			}
?>
									</td>
<?php
		}
		else // профиль
		{
?>
									<td class="tc1">
										<p>&#160;<a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/'.$fichier) ?>"><?php echo pun_htmlspecialchars($f) ?></a> [<?php echo pun_htmlspecialchars($size_fichier) ?>]</p>
<?php
			if ($fmini)
			{
?>
										<p>&#160;<a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/'.$mini) ?>"><?php echo pun_htmlspecialchars($m) ?></a></p>
<?php
			}
?>
									</td>
<?php
		}
		if ($fmini && !$fpr)
			echo "\t\t\t\t\t\t\t\t\t".'<td class="tc2" style="text-align:center;"><a href="'.$fichier.'" onclick="return insert_file(\''.$f.'\', \''.$m.'\');" title="'.$fi[1].' - '.$size_fichier.'"><img src="'.$mini.'" alt="'.$fi[1].'" /></a></td>'."\n";
		else if ($fmini)
			echo "\t\t\t\t\t\t\t\t\t".'<td class="tc2" style="text-align:center;"><a href="'.$fichier.$fb.'" title="'.$fi[1].' - '.$size_fichier.'"><img src="'.$mini.'" alt="'.$fi[1].'" /></a></td>'."\n";
		else
			echo "\t\t\t\t\t\t\t\t\t".'<td class="tc2" style="text-align:center;">'.$lang_up['no_preview'].'</td>'."\n";
?>
									<td style="text-align:center;"><input type="checkbox" name="delete_<?php echo $maxidf++ ?>" value="<?php echo $f ?>" tabindex="<?php echo $tabi++ ?>" /></td>
								</tr>
<?php
	}
?>
							</tbody>
						</table>
						<input type="hidden" name="max_id" value="<?php echo $maxidf ?>" />
						<input type="hidden" name="csrf_hash" value="<?php echo $vcsrf ?>" />
<?php
}
else
	echo "\t\t\t\t\t".'<div class="infldset">'."\n\t\t\t\t\t\t".'<p><span>'.$lang_up['err_2']."</span></p>\n";

?>
					</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
<?php

if ($fpr)
	echo "\t".'<div class="clearer"></div>'."\n".'</div>'."\n";

require PUN_ROOT.'footer.php';
