<?php

/**
 * Copyright (C) 2011-2017 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (isset($_GET['delete']))
	define('PUN_QUIET_VISIT', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view'], false, '403 Forbidden');

if ($pun_user['is_guest'] || !isset($pun_user['g_up_ext']) || empty($pun_config['o_uploadile_other']))
	message($lang_common['Bad request'], false, '404 Not Found');

require PUN_ROOT.'include/upload.php';

define('PLUGIN_REF', pun_htmlspecialchars('upfiles.php'));
define('PLUGIN_NF', 25);

if (!isset($_GET['id']))
{
	$id = $pun_user['id'];

	define('PUN_HELP', 1);
	define('PLUGIN_URL', PLUGIN_REF);
	define('PLUGIN_URLD', PLUGIN_URL.'?');
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_up['popup_title']);
	$fpr = false;
	$extsup = $pun_user['g_up_ext'];
	$limit = $pun_user['g_up_limit'];
	$maxsize = $pun_user['g_up_max'];
	$upload = $pun_user['upload'];
}
else
{
	$id = intval($_GET['id']);
	if ($id < 2 || ($pun_user['g_id'] != PUN_ADMIN && $id != $pun_user['id']))
		message($lang_common['Bad request'], false, '404 Not Found');

	$result = $db->query('SELECT u.username, u.upload, g.g_up_ext, g.g_up_max, g.g_up_limit FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON u.group_id=g.g_id WHERE u.id='.$id) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
	$user_info = $db->fetch_row($result);

	if (!$user_info)
		message($lang_common['Bad request'], false, '404 Not Found');

	list($usname, $upload, $extsup, $maxsize, $limit) = $user_info;

	define('PLUGIN_URL', PLUGIN_REF.'?id='.$id);
	define('PLUGIN_URLD', PLUGIN_URL.'&amp;');
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_up['popup_title']);
	$fpr = true;
}

if ($pun_user['g_id'] != PUN_ADMIN && $limit*$maxsize == 0)
	message($lang_common['Bad request'], false, '404 Not Found');

$prcent = ($limit == 0) ? 100 : ceil($upload*100/$limit);
$prcent = min(100, $prcent);

$dir = 'img/members/'.$id.'/';
$aconf = unserialize($pun_config['o_uploadile_other']);
$extsup = explode(',', $extsup.','.strtoupper($extsup));

// #############################################################################

// Удаление файлов
if (isset($_GET['delete']))
{
	confirm_referrer(PLUGIN_REF);

	$error = 0;

	if (is_dir(PUN_ROOT.$dir))
	{
		$file = parse_file(pun_trim($_GET['delete']));
		$ext = strtolower(substr(strrchr($file, '.'), 1)); // берем расширение файла
		if ($file[0] != '.' && $ext != '' && !in_array($ext, $extforno) && is_file(PUN_ROOT.$dir.$file))
		{
			if (unlink(PUN_ROOT.$dir.$file))
			{
				if (is_file(PUN_ROOT.$dir.'mini_'.$file))
					unlink(PUN_ROOT.$dir.'mini_'.$file);
			}
			else
				$error++;
		}
		else
			$error++;

		// Считаем общий размер файлов юзера
		$upload = dir_size($dir);
		$db->query('UPDATE '.$db->prefix.'users SET upload='.$upload.' WHERE id='.$id) or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());
	}
	else
		$error++;

	if (isset($_GET['ajx']))
	{
		$db->end_transaction();
		$db->close();

		header('Content-type: text/html; charset=utf-8');

		if ($error)
			exit('not ok');

		exit('ok');
	}

	$s = $lang_up['Redirect delete'];
	if ($error)
	{
		$pun_config['o_redirect_delay'] = 5;
		$s = $lang_up['Error'].$lang_up['Error delete'];
	}
	redirect(empty($_GET['p']) || $_GET['p'] < 2 ? PLUGIN_URL : PLUGIN_URLD.'p='.intval($_GET['p']).'#gofile', $s);
}

// Загрузка файла
else if (isset($_FILES['upfile']) && $id == $pun_user['id'])
{
	$pun_config['o_redirect_delay'] = 5;

	// Ошибка при загрузке
	if (!empty($_FILES['upfile']['error']))
	{
		switch($_FILES['upfile']['error'])
		{
			case 1: // UPLOAD_ERR_INI_SIZE
			case 2: // UPLOAD_ERR_FORM_SIZE
				redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Too large ini']);
				break;

			case 3: // UPLOAD_ERR_PARTIAL
				redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Partial upload']);
				break;

			case 4: // UPLOAD_ERR_NO_FILE
				redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['No file']);
				break;

			case 6: // UPLOAD_ERR_NO_TMP_DIR
				redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['No tmp directory']);
				break;

			default:
				// No error occured, but was something actually uploaded?
				if ($uploaded_file['size'] == 0)
					redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['No file']);
				break;
		}
	}

	if (is_uploaded_file($_FILES['upfile']['tmp_name']))
	{
		confirm_referrer(PLUGIN_REF);

		$f = pathinfo(parse_file($_FILES['upfile']['name']));
		if (empty($f['extension']))
			redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Bad type']);

		// Проверяем расширение
		$ext = strtolower($f['extension']);
		if (in_array($ext, $extforno) || !in_array($ext, $extsup))
			redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Bad type']);

		// Проверяется максимальный размер файла
		if ($_FILES['upfile']['size'] > $maxsize)
			redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Too large'].' '.pun_htmlspecialchars(file_size($maxsize)).'.');

		// Проверяем допустимое пространство
		if ($_FILES['upfile']['size'] + $upload > $limit)
			redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Error space']);

		// Проверяем картинку (флэш) на правильность
		$isimg2 = (in_array($ext, $extimage));
		$size = @getimagesize($_FILES['upfile']['tmp_name']);
		if (($size === false && $isimg2) || ($size !== false && !$isimg2))
			redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Error img']);
		if ($isimg2)
		{
			$isimge = false;

			if (empty($size[0]) || empty($size[1]) || empty($size[2]))
				$isimge = true;
			else if (!isset($extimage2[$size[2]]) || !in_array($ext, $extimage2[$size[2]]))
				$isimge = true;
			if ($isimge)
				redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Error img']);
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

		if ($_FILES['upfile']['size'] > $aconf['pic_mass'] && $isimg2 && $gd && array_key_exists($ext,$extimageGD))
		{
			$ext_ml = img_resize($_FILES['upfile']['tmp_name'], $dir, $name, $ext, $aconf['pic_w'], $aconf['pic_h'], $aconf['pic_perc'], true);
			if ($ext_ml === false)
				redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Error no mod img']);

			list($name, $ext) = $ext_ml;
		}
		else
		{
			$error = isXSSattack($_FILES['upfile']['tmp_name']);
			if ($error !== false)
				redirect(PLUGIN_URL, $lang_up['Error'].$error);

			if (!@move_uploaded_file($_FILES['upfile']['tmp_name'], PUN_ROOT.$dir.$name.'.'.$ext))
				redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Move failed']);
			@chmod(PUN_ROOT.$dir.$name.'.'.$ext, 0644);
		}

		// Создание привьюшки (только для поддерживаемых GD форматов)
		if ($aconf['thumb'] == 1 && $isimg2 && $gd && array_key_exists($ext,$extimageGD))
			img_resize(PUN_ROOT.$dir.$name.'.'.$ext, $dir, 'mini_'.$name, $ext, 0, $aconf['thumb_size'], $aconf['thumb_perc']);

		// Считаем общий размер файлов юзера
		$upload = dir_size($dir);
		$db->query('UPDATE '.$db->prefix.'users SET upload=\''.$upload.'\' WHERE id='.$id) or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());

		$pun_config['o_redirect_delay'] = '1';
		redirect(PLUGIN_URL, $lang_up['Redirect upload']);
	}
	else
		redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Unknown failure']);
}

// Unknown failure
else if (!empty($_POST))
	redirect(PLUGIN_URL, $lang_up['Error'].$lang_up['Unknown failure']);

// #############################################################################

if (!isset($page_head))
	$page_head = array();

if (file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/upfiles.css'))
	$page_head['pmsnewstyle'] = '<link rel="stylesheet" type="text/css" href="style/'.$pun_user['style'].'/upfiles.css" />';
else
	$page_head['pmsnewstyle'] = '<link rel="stylesheet" type="text/css" href="style/imports/upfiles.css" />';

define('PUN_ACTIVE_PAGE', 'profile');
require PUN_ROOT.'header.php';
$tpl_main = str_replace('id="punhelp"', 'id="punupfiles"', $tpl_main);

$tabi = 0;

$vcsrf = (function_exists('csrf_hash')) ? csrf_hash() : '1';

if ($fpr)
{
	// Load the profile.php language file
	require PUN_ROOT.'lang/'.$pun_user['language'].'/profile.php';

	generate_profile_menu('upload');
}

if ($id == $pun_user['id'])
{

?>
	<div class="blockform">
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
							<input type="file" id="upfile" name="upfile" tabindex="<?php echo $tabi++ ?>" />
							<p><?php	printf($lang_up['info_2'], pun_htmlspecialchars(file_size($maxsize)), pun_htmlspecialchars(str_replace(',', ', ', $pun_user['g_up_ext']))) ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="submit" value="<?php echo $lang_up['Upload'] ?>" tabindex="<?php echo $tabi++ ?>" /></p>
			</form>
		</div>
	</div>
<?php

	$tit = $lang_up['titre_4'];
}
else
{
	$tit = pun_htmlspecialchars($usname).' - '.$lang_up['upfiles'];
}

$files = $filesvar = array();
if (is_dir(PUN_ROOT.$dir))
{
	$open = opendir(PUN_ROOT.$dir);
	while (($file = readdir($open)) !== false)
	{
		if (is_file(PUN_ROOT.$dir.$file))
		{
			$ext = strtolower(substr(strrchr($file, '.'), 1));
			if (!in_array($ext, $extforno) && $file[0] != '#' && substr($file, 0, 5) != 'mini_')
			{
				$time = filemtime(PUN_ROOT.$dir.$file).$file;
				$filesvar[$time] = $dir.$file;
			}
		}
	}
	closedir($open);
	if (!empty($filesvar))
	{
		$num_pages = ceil(sizeof($filesvar) / PLUGIN_NF);
		$p = (!isset($_GET['p']) || $_GET['p'] <= 1) ? 1 : intval($_GET['p']);
		if ($p > $num_pages)
		{
			header('Location: '.str_replace('&amp;', '&', PLUGIN_URLD).'p='.$num_pages.'#gofile');
			exit;
		}

		$start_from = PLUGIN_NF * ($p - 1);

		// Generate paging links
		$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, PLUGIN_URL);
		$paging_links = str_replace(PLUGIN_REF.'&amp;', PLUGIN_REF.'?', $paging_links);
		$paging_links = preg_replace('%href="([^">]+)"%', 'href="$1#gofile"', $paging_links);

		krsort($filesvar);
		$files = array_slice($filesvar, $start_from, PLUGIN_NF);
		unset($filesvar);
	}
}

?>
	<div id="upf-block" class="block">
		<h2 id="gofile" class="block2"><span><?php echo $tit ?></span></h2>
		<div class="box">
<?php

if (empty($files))
{
	echo "\t\t\t".'<div class="inbox"><p><span>'.$lang_up['No upfiles'].'</span></p></div>'."\n";
}
else
{

?>
			<div class="inbox">
				<div id="upf-legend">
					<div style="<?php echo 'background-color: rgb('.ceil(($prcent > 50 ? 50 : $prcent)*255/50).', '.ceil(($prcent < 50 ? 50 : 100 - $prcent)*255/50).', 0); width:'.$prcent.'%;' ?>"><?php echo $prcent.'%' ?></div>
				</div>
				<p id="upf-legend-p"><?php echo sprintf($lang_up['info_4'], pun_htmlspecialchars(file_size($upload)),pun_htmlspecialchars(file_size($limit))) ?></p>
			</div>
			<div class="inbox">
				<div class="pagepost">
					<p class="pagelink conl"><?php echo $paging_links ?></p>
				</div>
			</div>
			<div class="inbox">
				<div id="upf-container">
					<ul id="upf-list">
<?php

	$height = max(intval($aconf['thumb_size']), 100);
	$regx = '%^img/members/'.$id.'/(.+)\.([0-9a-zA-Z]+)$%i';
	foreach($files as $file)
	{
		preg_match($regx, $file, $fi);
		if (!isset($fi[1]) || !isset($fi[2]) || in_array(strtolower($fi[2]), $extforno))
			continue;

		$fb = in_array(strtolower($fi[2]), array('jpg', 'jpeg', 'gif', 'png', 'bmp')) ? '" class="fancy_zoom" rel="vi001' : '';
		$size_file = file_size(filesize(PUN_ROOT.$file));
		$f = $fi[1].'.'.$fi[2];
		$m = 'mini_'.$f;
		$mini = $dir.$m;
		$fmini = (is_file(PUN_ROOT.$mini));

?>
						<li>
							<div class="upf-name" title="<?php echo pun_htmlspecialchars($f) ?>"><span><?php echo pun_htmlspecialchars(pun_strlen($f) > 20 ? utf8_substr($f, 0, 18).'…' : $f) ?></span></div>
							<div class="upf-file" style="height:<?php echo $height ?>px;">
								<a href="<?php echo pun_htmlspecialchars(get_base_url(true).'/'.$file).$fb ?>">
<?php if ($fmini || $fb): ?>									<img src="<?php echo pun_htmlspecialchars($fmini ? get_base_url(true).'/'.$mini : get_base_url(true).'/'.$file) ?>" alt="<?php echo pun_htmlspecialchars((pun_strlen($fi[1]) > 15 ? utf8_substr($fi[1], 0, 10).'… ' : $fi[1]).'.'.$fi[2]) ?>" />
<?php else: ?>									<span><?php echo pun_htmlspecialchars((pun_strlen($fi[1]) > 15 ? utf8_substr($fi[1], 0, 10).'… ' : $fi[1]).'.'.$fi[2]) ?></span>
<?php endif; ?>
								</a>
							</div>
							<div class="upf-size"><span><?php echo pun_htmlspecialchars($size_file) ?></span></div>
							<div class="upf-but upf-delete"><a title="<?php echo $lang_up['delete'] ?>" href="<?php echo PLUGIN_URLD.'csrf_hash='.$vcsrf.(empty($_GET['p']) || $_GET['p'] < 2 ? '' : '&amp;p='.intval($_GET['p'])).'&amp;delete='.$f ?>" onclick="return FluxBB.upfile.del(this);"><span></span></a></div>
						</li>
<?php

	} // end foreach

?>
					</ul>
				</div>
			</div>
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

if ($fpr)
	echo "\t".'<div class="clearer"></div>'."\n".'</div>'."\n";

?>
<script type="text/javascript">
/* <![CDATA[ */
if (typeof FluxBB === 'undefined' || !FluxBB) {var FluxBB = {};}

FluxBB.upfile = (function (doc, win) {
	'use strict';

	var url, src, par, area;

	function get(elem) {
		return doc.getElementById(elem);
	}

	function createElement(elem) {
		return (doc.createElementNS) ? doc.createElementNS('http://www.w3.org/1999/xhtml', elem) : doc.createElement(elem);
	}

	function is_img(a) {
		return /.+\.(jpg|jpeg|png|gif|bmp)$/.test(a);
	}

	function get_us(li) {
		url = '';
		src = '';
		var div = li.getElementsByTagName('div')[1];
		if (!!div) {
			var a = div.getElementsByTagName('a')[0];
			if (!!a) {
				url = a.href;
				var	img = a.getElementsByTagName('img')[0];
				if (!!img) src = img.src;
			}
		}
	}

	function set_button(li) {
		get_us(li);

		if (!!url) {
			var div = createElement('div');
			div.className = 'upf-but upf-insert';
			div.innerHTML = '<a title="<?php echo $lang_up['insert'] ?>" href="#" onclick="return FluxBB.upfile.ins(this);"><span></span></a>';
			li.appendChild(div);

			if (is_img(src) && src != url) {
				div = createElement('div');
				div.className = 'upf-but upf-insert-t';
				div.innerHTML = '<a title="<?php echo $lang_up['insert_thumb'] ?>" href="#" onclick="return FluxBB.upfile.ins(this, 1);"><span></span></a>';
				li.appendChild(div);
			}
		}
	}

	function insr(s, e, t)
	{
		area.focus();
		if ('selectionStart' in area) { // all new
			var len = area.value.length,
				sp = Math.min(area.selectionStart, len), // IE bug
				ep = Math.min(area.selectionEnd, len); // IE bug
			area.value = area.value.substring(0, sp) + s + (sp == ep ? t : area.value.substring(sp, ep)) + e + area.value.substring(ep);
			area.selectionStart = ep + e.length + s.length + (sp == ep ? t.length : 0);
			area.selectionEnd = area.selectionStart;
		} else if (par.selection && par.selection.createRange) { // IE
			var sel = par.selection.createRange();
			sel.text = s + (!sel.text ? t : sel.text) + e;
			sel.select();
		}
		win.focus();
	}

	function cr_req() {
		if (win.XMLHttpRequest) {
			return new XMLHttpRequest();
		} else {
			try {
				return new ActiveXObject('Microsoft.XMLHTTP');
			} catch (e){}
		}
		return !1;
	}

	function orsc(req, ref) {
		if (req.readyState == 4)
		{
			ref.className = '';

			if (req.status == 200 && req.responseText == 'ok') {
				ref.parentNode.parentNode.parentNode.removeChild(ref.parentNode.parentNode);
				if (get('upf-list').getElementsByTagName('li').length == 0) {
					win.location.reload(true);
				}
			}
		}
	}

	return {

		del : function (ref) {
			if (ref.className) return !1;
			if (!confirm('<?php echo addslashes($lang_up['delete file']) ?>')) return !1;

			ref.className = 'upf-loading';

			var req = cr_req();
			if (req) {
				req.onreadystatechange=function(){orsc(req, ref);};
				req.open('GET', ref.href + '&ajx=1', true);
				req.send();

				return !1;
			} else
				return !0;
		},

		ins : function (ref, f) {

			f = f || !1;
			get_us(ref.parentNode.parentNode);

			if (f && is_img(src) && src != url) {
				insr('', '[url=' + url + '][img]' + src + '[/img][/url]', '');
			} else if (is_img(url)) {
				insr('', '[img]' + url + '[/img]', '');
			} else {
				if (f = url.match(/.*\/img\/members\/\d+\/(.+)$/)) f = f[1];
				else f = '<?php echo $lang_up['texte'] ?>';

				insr('[url=' + url + ']', '[/url]', f);
			}
			return !1;
		},

		run : function () {
			if (!win.opener) return;

			par = win.opener.document;
			area = par.getElementsByName('req_message')[0];
			if (!area) return;

			var li = get('upf-list').getElementsByTagName('li');
			for (var i in li) {
				if (!!li[i].getElementsByTagName) set_button(li[i]);
			}
		},

		init : function () {
			if (!doc.addEventListener) {
				/in/.test(doc.readyState) ? setTimeout(FluxBB.upfile.init, 100) : FluxBB.upfile.run();
			} else doc.addEventListener('DOMContentLoaded', FluxBB.upfile.run(), false);
		}
	};
}(document, window));

FluxBB.upfile.init();
/* ]]> */
</script>
<?php

require PUN_ROOT.'footer.php';
