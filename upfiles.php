<?php

/**
 * Copyright (C) 2011-2019 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

function upf_return_json($data)
{
	global $db;

	$db->end_transaction();
	$db->close();

	if (function_exists('forum_http_headers')) {
		forum_http_headers('application/json');
	} else {
		header('Content-type: application/json; charset=utf-8');
		header('Cache-Control: no-cache, no-store, must-revalidate');
	}

	exit(json_encode($data));
}

function upf_get_pg($key, $default = null)
{
	if (isset($_POST[$key])) {
		return $_POST[$key];
	} else if (isset($_GET[$key])) {
		return $_GET[$key];
	} else {
		return $default;
	}
}

function upf_message($message, $no_back_link = false, $http_status = null)
{
	global $upf_ajax;

	if ($upf_ajax) {
		upf_return_json(['error' => $message]);
	} else {
		message($message, $no_back_link, $http_status);
	}
}

function upf_redirect($destination_url, $message)
{
	global $upf_ajax, $lang_up;

	if ($upf_ajax) {
		upf_return_json(['error' => $message]);
	} else {
		redirect($destination_url, $lang_up['Error'] . $message);
	}
}

define('PUN_ROOT', dirname(__FILE__) . '/');
require PUN_ROOT . 'include/common.php';

define('PLUGIN_REF', pun_htmlspecialchars('upfiles.php'));
define('PLUGIN_NF', 25);

$upf_ajax = ('1' == upf_get_pg('ajx'));
$upf_action = upf_get_pg('action');
$upf_page = (int) upf_get_pg('p', 1);

if ($pun_user['g_read_board'] == '0') {
	upf_message($lang_common['No view'], false, '403 Forbidden');
}

if ($pun_user['is_guest'] || empty($pun_user['g_up_ext']) || empty($pun_config['o_upload_config']) || $upf_page < 1) {
	upf_message($lang_common['Bad request'], false, '404 Not Found');
}

// Any action must be confirmed by token
if (null !== $upf_action) {
	if (function_exists('csrf_hash')) {
		if ($upf_ajax) {
			$errors = [];
		}
		confirm_referrer(PLUGIN_REF);
		if ($upf_ajax) {
			if (! empty($errors)) {
				upf_return_json(['error' => array_pop($errors)]);
			}
			unset($errors);
		}
	} else {
		check_csrf(upf_get_pg('csrf_hash'));
	}
}

require PUN_ROOT . 'include/upload.php';

if (! isset($_GET['id'])) {
	$id = $pun_user['id'];

	define('PUN_HELP', 1);
	define('PLUGIN_URL', PLUGIN_REF);
	define('PLUGIN_URLD', PLUGIN_URL.'?');
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_up['popup_title']);
	$fpr = false;
	$upf_exts = $pun_user['g_up_ext'];
	$upf_limit = $pun_user['g_up_limit'];
	$upf_max_size = $pun_user['g_up_max'];
	$upf_dir_size = $pun_user['upload_size'];
} else {
	$id = intval($_GET['id']);
	if ($id < 2 || ($pun_user['g_id'] != PUN_ADMIN && $id != $pun_user['id'])) {
		upf_message($lang_common['Bad request'], false, '404 Not Found');
	}

	$result = $db->query('SELECT u.username, u.upload_size, g.g_up_ext, g.g_up_max, g.g_up_limit FROM ' . $db->prefix . 'users AS u INNER JOIN '.$db->prefix.'groups AS g ON u.group_id=g.g_id WHERE u.id=' . $id) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
	$user_info = $db->fetch_row($result);

	if (! $user_info) {
		upf_message($lang_common['Bad request'], false, '404 Not Found');
	}

	list($usname, $upf_dir_size, $upf_exts, $upf_max_size, $upf_limit) = $user_info;

	define('PLUGIN_URL', PLUGIN_REF . '?id=' . $id);
	define('PLUGIN_URLD', PLUGIN_URL . '&amp;');
	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Profile'], $lang_up['popup_title']);
	$fpr = true;
}

$upf_limit *= 1048576;
$upf_max_size = (int) min(10485.76 * $upf_max_size, $upf_class->size(ini_get('upload_max_filesize')), $upf_class->size(ini_get('post_max_size')));
$upf_dir_size *= 10485.76;

if ($pun_user['g_id'] != PUN_ADMIN && $upf_limit * $upf_max_size == 0) {
	upf_message($lang_common['Bad request'], false, '404 Not Found');
}

$upf_percent = min(100, empty($upf_limit) ? 100 : ceil($upf_dir_size * 100 / $upf_limit));

$upf_dir = 'img/members/' . $id . '/';
$upf_conf = unserialize($pun_config['o_upload_config']);
$upf_exts = explode(',', $upf_exts . ',' . strtoupper($upf_exts));
$upf_new_files = [];
$upf_token = function_exists('csrf_hash') ? csrf_hash() : pun_csrf_token();

// #############################################################################

// Удаление файла
if ('delete' === $upf_action) {
	$error = false;
	$count = null;
	$confirm = upf_get_pg('confirm');

	// наличие файла
	if (
		is_dir(PUN_ROOT . $upf_dir)
		&& preg_match('%^([\w-]+)\.(\w+)$%', pun_trim(upf_get_pg('file')), $matches)
		&& false === $upf_class->inBlackList($matches[2])
		&& 'mini_' !== substr($matches[1], 0, 5)
		&& is_file(PUN_ROOT . $upf_dir . $matches[1] . '.' . $matches[2])
	) {
		$fileName = $matches[1] . '.' . $matches[2];
		$filePath = PUN_ROOT . $upf_dir . $matches[1] . '.' . $matches[2];
	} else {
		$error = $lang_up['Error delete'];
		$confirm = null;
	}

	// проверка подтверждения
	if (
		false === $error
		&& null !== $confirm
	) {
		if (! hash_equals(pun_hash($filePath), (string) $confirm)) {
			$error = $lang_up['Error delete'];
			$confirm = null;
		}
	}

	// проверка для удаления
	if (
		false === $error
		&& null === $confirm
	) {
		include PUN_ROOT . 'include/search_idx.php';
		$like = '/' . $upf_dir . $fileName;
		$words = split_words(utf8_strtolower($like), true);

		if (count($words) > 2) {
			$words = array_diff($words, ['img', 'members']);
		}
		if (count($words) > 2) {
			$words = array_diff($words, ['jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', 'webp']);
		}

		$count = count($words);

		if ($count > 0) {
			if (1 == $count) {
				$query = 'SELECT COUNT(m.post_id) AS numposts FROM ' . $db->prefix . 'search_words AS w INNER JOIN ' . $db->prefix . 'search_matches AS m ON m.word_id = w.id INNER JOIN ' . $db->prefix . 'posts AS p ON p.id=m.post_id WHERE w.word=\'' . $db->escape(array_pop($words)) . '\' AND p.message LIKE \'%' . $db->escape($like) . '%\'';
			} else {
				$query = 'SELECT COUNT(p.id) AS numposts FROM ' . $db->prefix . 'posts AS p WHERE p.id IN (SELECT m.post_id FROM ' . $db->prefix . 'search_words AS w INNER JOIN ' . $db->prefix . 'search_matches AS m ON m.word_id = w.id WHERE w.word IN (\'' . implode('\',\'', array_map([$db, 'escape'], $words)) . '\') GROUP BY m.post_id HAVING COUNT(m.post_id)=' . $count . ') AND p.message LIKE \'%' . $db->escape($like) . '%\'';
			}

			$result = $db->query($query) or error('Unable to fetch search information', __FILE__, __LINE__, $db->error());
			$count = $db->result($result);
		}

		if ($count > 0) {
			$error = sprintf($lang_up['Error usage'], $count);

			if (
				isset($pun_user['g_up_perm_del'])
				&& 1 == $pun_user['g_up_perm_del']
			) {
				$confirm = pun_hash($filePath);
			}
		}
	}

	// удаление
	if (false === $error) {
		$confirm = null;

		if (unlink($filePath)) {
			if (is_file(PUN_ROOT . $upf_dir . 'mini_' . $fileName)) {
				unlink(PUN_ROOT . $upf_dir . 'mini_' . $fileName);
			}

			$upf_dir_size = $upf_class->dirSize(PUN_ROOT . $upf_dir);
			$upf_percent = min(100, empty($upf_limit) ? 100 : ceil($upf_dir_size * 100 / $upf_limit));

			$db->query('UPDATE ' . $db->prefix . 'users SET upload_size=' . ((int) ($upf_dir_size / 10485.76)) . ' WHERE id=' . $id) or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());
		} else {
			$error = $lang_up['Error delete'];
		}
	}

	// запрос подтверждения
	if (
		null !== $confirm
		&& null !== $count
	) {
		if ($upf_ajax) {
			upf_return_json(['error' => $error, 'confirm' => $confirm]);
		} else {
			if (file_exists(PUN_ROOT . 'style/' . $pun_user['style'] . '/upfiles.css')) {
				$page_head['pmsnewstyle'] = '<link rel="stylesheet" type="text/css" href="style/' . $pun_user['style'] . '/upfiles.css" />';
			} else {
				$page_head['pmsnewstyle'] = '<link rel="stylesheet" type="text/css" href="style/imports/upfiles.css" />';
			}

			define('PUN_ACTIVE_PAGE', 'profile');
			require PUN_ROOT . 'header.php';
			$tpl_main = str_replace('id="punhelp"', 'id="punupfiles"', $tpl_main);

			$tabindex = 1;

			if ($fpr) {
				// Load the profile.php language file
				require PUN_ROOT . 'lang/' . $pun_user['language'] . '/profile.php';

				generate_profile_menu('upload');
			}

?>
<div class="blockform">
	<h2><span><?php echo $lang_up['Deleting file'] ?></span></h2>
	<div class="box">
		<form method="post" action="<?= PLUGIN_URL ?>">
			<div class="inform">
				<input type="hidden" name="csrf_hash" value="<?= $upf_token ?>" />
				<input type="hidden" name="action" value="delete" />
				<input type="hidden" name="confirm" value="<?= $confirm ?>" />
				<input type="hidden" name="file" value="<?= pun_htmlspecialchars($fileName) ?>" />
				<input type="hidden" name="p" value="<?= $upf_page ?>" />
				<div class="forminfo">
					<h3><span><?= sprintf($lang_up['%s file'], pun_htmlspecialchars($fileName)) ?></span></h3>
					<p><?= $error ?></p>
				</div>
			</div>
			<p class="buttons"><input type="submit" name="delete" value="<?= $lang_up['delete'] ?>" /> <a href="javascript:history.go(-1)"><?= $lang_common['Go back'] ?></a></p>
		</form>
	</div>
</div>
<?php

			require PUN_ROOT . 'footer.php';
		}

	// вывод ошибки
	} else if (false !== $error) {
		if ($pun_config['o_redirect_delay'] < 5) {
			$pun_config['o_redirect_delay'] = 5;
		}
		upf_redirect($upf_page < 2 ? PLUGIN_URL : PLUGIN_URLD . 'p=' . $upf_page, $error);

	// все ок для не ajax
	} else if (! $upf_ajax) {
		redirect($upf_page < 2 ? PLUGIN_URL : PLUGIN_URLD . 'p=' . $upf_page, $lang_up['Redirect delete']);
	}
}

// Загрузка файла
else if ('upload' === $upf_action && isset($_FILES['upfile']) && $id == $pun_user['id']) {
	$upf_redir_delay = $pun_config['o_redirect_delay'];
	if ($upf_redir_delay < 5) {
		$pun_config['o_redirect_delay'] = 5;
	}

	// Ошибка при загрузке
	if (! empty($_FILES['upfile']['error'])) {
		switch($_FILES['upfile']['error']) {
			case UPLOAD_ERR_INI_SIZE:
				upf_redirect(PLUGIN_URL, $lang_up['UPLOAD_ERR_INI_SIZE']);
				break;
			case UPLOAD_ERR_FORM_SIZE:
				upf_redirect(PLUGIN_URL, $lang_up['UPLOAD_ERR_FORM_SIZE']);
				break;
			case UPLOAD_ERR_PARTIAL:
				upf_redirect(PLUGIN_URL, $lang_up['UPLOAD_ERR_PARTIAL']);
				break;
			case UPLOAD_ERR_NO_FILE:
				upf_redirect(PLUGIN_URL, $lang_up['UPLOAD_ERR_NO_FILE']);
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
				upf_redirect(PLUGIN_URL, $lang_up['UPLOAD_ERR_NO_TMP_DIR']);
				break;
			case UPLOAD_ERR_CANT_WRITE:
				upf_redirect(PLUGIN_URL, $lang_up['UPLOAD_ERR_CANT_WRITE']);
				break;
			case UPLOAD_ERR_EXTENSION:
				upf_redirect(PLUGIN_URL, $lang_up['UPLOAD_ERR_EXTENSION']);
				break;
			default:
				upf_redirect(PLUGIN_URL, $lang_up['UPLOAD_ERR_UNKNOWN']);
				break;
		}
	}

	if (false === $upf_class->loadFile($_FILES['upfile']['tmp_name'], $_FILES['upfile']['name'])) {
		upf_redirect(PLUGIN_URL, $lang_up['Unknown failure'] . ' (' . pun_htmlspecialchars($upf_class->getError()) . ')');
	}

	// расширение
	if (! in_array($upf_class->getFileExt(), $upf_exts)) {
		upf_redirect(PLUGIN_URL, $lang_up['Bad type']);
	}

	// максимальный размер файла
	if ($_FILES['upfile']['size'] > $upf_max_size) {
		upf_redirect(PLUGIN_URL, $lang_up['Too large'] . ' (' . pun_htmlspecialchars(file_size($upf_max_size)) . ').');
	}

	// допустимое пространство
	if ($_FILES['upfile']['size'] + $upf_dir_size > $upf_limit) {
		upf_redirect(PLUGIN_URL, $lang_up['Error space']);
	}

	// подозрительное содержимое
	if (false !== $upf_class->isUnsafeContent()) {
		upf_redirect(PLUGIN_URL, $lang_up['Error inject']);
	}

	$upf_class->prepFileName();

	if (! is_dir(PUN_ROOT . 'img/members/')) {
		mkdir(PUN_ROOT . 'img/members', 0755);
	}
	if (! is_dir(PUN_ROOT . $upf_dir)) {
		mkdir(PUN_ROOT . $upf_dir, 0755);
	}

	$saveImage = false;
	$fileinfo = false;

	// сохранение картинки
	if (true === $upf_class->isImage()) {
		$upf_class->setImageQuality($upf_conf['pic_perc']);

		if (false === $upf_class->loadImage()) {
			upf_redirect(PLUGIN_URL, $lang_up['Error img'] . ' (' . pun_htmlspecialchars($upf_class->getError()) . ')');
		}

		if ($_FILES['upfile']['size'] > 1024 * $upf_conf['pic_mass'] && $upf_class->isResize()) {
			if (false === $upf_class->resizeImage($upf_conf['pic_w'], $upf_conf['pic_h'])) {
				upf_redirect(PLUGIN_URL, $lang_up['Error no mod img']);
			}

			$saveImage = true;
			$fileinfo = $upf_class->saveImage(PUN_ROOT . $upf_dir . $upf_class->getFileName() . '.' . $upf_class->getFileExt(), false);

			if (false === $fileinfo) {
				upf_redirect(PLUGIN_URL, $lang_up['Move failed'] . ' (' . pun_htmlspecialchars($upf_class->getError()) . ')'); //????
			}

			// картика стала больше после ресайза
			if (filesize($fileinfo['path']) > $_FILES['upfile']['size']) {
				$saveImage = false;
				unlink($fileinfo['path']);
			}
		}
	}

	// сохранение файла
	if (false === $saveImage) {
		if (is_array($fileinfo)) {
			$fileinfo = $upf_class->saveFile($fileinfo['path'], true);
		} else {
			$fileinfo = $upf_class->saveFile(PUN_ROOT . $upf_dir . $upf_class->getFileName() . '.' . $upf_class->getFileExt(), false);
		}

		if (false === $fileinfo) {
			upf_redirect(PLUGIN_URL, $lang_up['Move failed'] . ' (' . pun_htmlspecialchars($upf_class->getError()) . ')'); //????
		}
	}

	// превью
	if (true === $upf_class->isImage() && 1 == $upf_conf['thumb'] && $upf_class->isResize()) {
		$upf_class->setImageQuality($upf_conf['thumb_perc']);

		$scaleResize = $upf_class->resizeImage(null, $upf_conf['thumb_size']);
		if (false !== $scaleResize) {
			$path = PUN_ROOT . $upf_dir . 'mini_' . $fileinfo['filename'] . '.' . $fileinfo['extension'];

			if ($scaleResize < 1) {
				$upf_class->saveImage($path, true);
			} else {
				copy($fileinfo['path'], $path);
				chmod($path, 0644);
			}
		}
	}

	$upf_dir_size = $upf_class->dirSize(PUN_ROOT . $upf_dir);
	$upf_percent = min(100, empty($upf_limit) ? 100 : ceil($upf_dir_size * 100 / $upf_limit));
	$db->query('UPDATE ' . $db->prefix . 'users SET upload_size=' . ((int) ($upf_dir_size / 10485.76)) . ' WHERE id=' . $id) or error($lang_up['Error DB ins-up'], __FILE__, __LINE__, $db->error());

	if ($upf_ajax) {
		$upf_page = 1;
		$upf_new_files[$fileinfo['filename'] . '.' . $fileinfo['extension']] = true;
	} else {
		$pun_config['o_redirect_delay'] = $upf_redir_delay;
		redirect(PLUGIN_URL, $lang_up['Redirect upload']);
	}
}

// Unknown failure
else if (($upf_ajax && 'view' !== $upf_action) || (! $upf_ajax && ! empty($_POST))) {
	upf_redirect(PLUGIN_URL, $lang_up['Unknown failure']);
}

// #############################################################################

$files = [];
$count = 0;
$num_pages = 1;
if (is_dir(PUN_ROOT . $upf_dir)) {
	$tmp = get_base_url(true) . '/' . $upf_dir;
	foreach (new DirectoryIterator(PUN_ROOT . $upf_dir) as $file) {
		if (! $file->isFile() || true === $upf_class->inBlackList($file->getExtension())) {
			continue;
		}

		$filename = $file->getFilename();
		if ('#' === $filename[0] || 'mini_' === substr($filename, 0, 5)) {
			continue;
		}

		++$count;
		if (empty($upf_new_files) || isset($upf_new_files[$filename])) {
			$files[$file->getMTime() . $filename] = [
				'filename' => $filename,
				'ext' => $file->getExtension(),
				'alt' => pun_strlen($filename) > 18 ? utf8_substr($filename, 0, 16) . '…' : $filename,
				'size' => file_size($file->getSize()),
				'url' => $tmp . $filename,
				'mini' => is_file(PUN_ROOT . $upf_dir . 'mini_' . $filename) ? $tmp . 'mini_' . $filename : null,
			];
		}
	}
	if (! empty($files)) {
		$num_pages = ceil($count / PLUGIN_NF);
		if ($upf_page > $num_pages && ! $upf_ajax) {
			header('Location: ' . str_replace('&amp;', '&', PLUGIN_URLD) . 'p=' . $num_pages . '#gofile');
			exit;
		}

		krsort($files);

		if (empty($upf_new_files)) {
			$start_from = PLUGIN_NF * ($upf_page - 1);
			$files = array_slice($files, $start_from, PLUGIN_NF);
		}
	}
}

if ($upf_ajax) {
	upf_return_json([
		'size' => file_size($upf_dir_size),
		'percent' => $upf_percent,
		'pages' => $num_pages,
		'files' => $files,
	]);
}

if (! isset($page_head)) {
	$page_head = [];
}

if (file_exists(PUN_ROOT . 'style/' . $pun_user['style'] . '/upfiles.css')) {
	$page_head['pmsnewstyle'] = '<link rel="stylesheet" type="text/css" href="style/' . $pun_user['style'] . '/upfiles.css" />';
} else {
	$page_head['pmsnewstyle'] = '<link rel="stylesheet" type="text/css" href="style/imports/upfiles.css" />';
}

define('PUN_ACTIVE_PAGE', 'profile');
require PUN_ROOT . 'header.php';
$tpl_main = str_replace('id="punhelp"', 'id="punupfiles"', $tpl_main);

$tabindex = 1;

if ($fpr) {
	// Load the profile.php language file
	require PUN_ROOT . 'lang/' . $pun_user['language'] . '/profile.php';

	generate_profile_menu('upload');
}

if ($id == $pun_user['id']) {

?>
	<div class="blockform">
		<h2><span><?= $lang_up['titre_2'] ?></span></h2>
		<div class="box">
			<form method="post" action="<?= PLUGIN_URL ?>" enctype="multipart/form-data">
				<div class="inform">
					<fieldset>
						<legend><?= $lang_up['legend'] ?></legend>
						<div class="infldset">
							<input type="hidden" name="csrf_hash" value="<?= $upf_token ?>" />
							<input type="hidden" name="action" value="upload" />
							<input type="hidden" name="MAX_FILE_SIZE" value="<?= $upf_max_size ?>" />
							<p><?= $lang_up['fichier'] ?></p>
							<input type="file" id="upfile" name="upfile" tabindex="<?= $tabindex++ ?>" />
							<p><?= sprintf($lang_up['info_2'], pun_htmlspecialchars(str_replace([' ', ','], ['', ', '], $pun_user['g_up_ext'])), pun_htmlspecialchars(file_size($upf_max_size))) ?></p>
						</div>
					</fieldset>
				</div>
				<p class="buttons"><input type="submit" name="submit" value="<?= $lang_up['Upload'] ?>" tabindex="<?= $tabindex++ ?>" /></p>
			</form>
		</div>
	</div>
<?php

	$tit = $lang_up['titre_4'];
} else {
	$tit = pun_htmlspecialchars($usname) . ' - ' . $lang_up['upfiles'];
}

?>
	<div id="upf-block" class="block">
		<h2 id="gofile" class="block2"><span><?= $tit ?></span></h2>
		<div class="box">
<?php

if (empty($files)) {

?>
			<div class="inbox"><p><span><?= $lang_up['No upfiles'] ?></span></p></div>
<?php

} else {
	// Generate paging links
	$paging_links = '<span class="pages-label">' . $lang_common['Pages'] . ' </span>' . paginate($num_pages, $upf_page, PLUGIN_URL);
	$paging_links = str_replace(PLUGIN_REF . '&amp;', PLUGIN_REF . '?', $paging_links);
	$paging_links = preg_replace('%href="([^">]+)"%', 'href="$1#gofile"', $paging_links);

?>
			<div class="inbox">
				<div id="upf-legend">
					<div style="<?= 'background-color: rgb(' . ceil(($upf_percent > 50 ? 50 : $upf_percent) * 255 / 50) . ', ' . ceil(($upf_percent < 50 ? 50 : 100 - $upf_percent) * 255 / 50) . ', 0); width:' . $upf_percent . '%;' ?>"><span><?= $upf_percent ?>%</span></div>
				</div>
				<p id="upf-legend-p"><?= sprintf($lang_up['info_4'], pun_htmlspecialchars(file_size($upf_dir_size)), pun_htmlspecialchars(file_size($upf_limit))) ?></p>
			</div>
			<div class="inbox">
				<div class="pagepost">
					<p class="pagelink conl"><?= $paging_links ?></p>
				</div>
			</div>
			<div class="inbox">
				<div id="upf-container">
					<ul id="upf-list">
<?php

	$upf_img_exts = ['jpg', 'jpeg', 'gif', 'png', 'bmp', 'webp'];
	foreach ($files as $file) {
		$fb = in_array($file['ext'], $upf_img_exts) ? '" class="fancy_zoom" rel="vi001' : '';

?>
						<li>
							<div class="upf-name" title="<?= pun_htmlspecialchars($file['filename']) ?>"><span><?= pun_htmlspecialchars($file['alt']) ?></span></div>
							<div class="upf-file" style="height:<?= max(intval($upf_conf['thumb_size']), 100) ?>px;">
								<a href="<?= pun_htmlspecialchars($file['url']) . $fb ?>">
<?php if (isset($file['mini'])): ?>									<img src="<?= pun_htmlspecialchars($file['mini']) ?>" alt="<?= pun_htmlspecialchars($file['alt']) ?>" />
<?php else: ?>									<span><?= pun_htmlspecialchars($file['alt']) ?></span>
<?php endif; ?>
								</a>
							</div>
							<div class="upf-size"><span><?= pun_htmlspecialchars($file['size']) ?></span></div>
							<div class="upf-but upf-delete"><a title="<?= $lang_up['delete'] ?>" href="<?= PLUGIN_URLD . 'csrf_hash=' . $upf_token . ($upf_page < 2 ? '' : '&amp;p=' . $upf_page) . '&amp;action=delete&amp;file=' . pun_htmlspecialchars($file['filename']) ?>" onclick="return FluxBB.upfile.del(this);"><span></span></a></div>
						</li>
<?php

	} // end foreach

?>
					</ul>
				</div>
			</div>
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

if ($fpr) {

?>
	<div class="clearer"></div>
</div>
<?php

}

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
		return /.+\.(jpg|jpeg|png|gif|bmp|webp)$/i.test(a);
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
			div.innerHTML = '<a title="<?= $lang_up['insert'] ?>" href="#" onclick="return FluxBB.upfile.ins(this);"><span></span></a>';
			li.appendChild(div);

			if (is_img(src) && src != url) {
				div = createElement('div');
				div.className = 'upf-but upf-insert-t';
				div.innerHTML = '<a title="<?= $lang_up['insert_thumb'] ?>" href="#" onclick="return FluxBB.upfile.ins(this, 1);"><span></span></a>';
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
		if (req.readyState == 4) {
			ref.className = '';
			var error = true;

			if (req.status == 200) {
				var data = req.responseText;
				if (typeof data === 'string') {
					try {
						data = JSON.parse(data);
					} catch (e) {}
				}
				if (typeof data === 'string') {
					if ('{' === data.substr(0, 1) && !/"error"/.test(data)) {
						error = false;
					}
				} else {
					if ('error' in data) {
						if ('confirm' in data) {
							if (confirm(data.error + ' <?= addslashes($lang_up['delete file']) ?>')) {
								var req2 = cr_req();
								if (req2) {
									req2.onreadystatechange = function() {
										orsc(req2, ref);
									};
									req2.open('GET', ref.href + '&ajx=1&confirm=' + data.confirm, true);
									req2.send();
								}
							}
						} else {
							alert(data.error);
						}
					} else {
						error = false;
					}
				}
			}

			if (!error) {
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
			if (!confirm('<?= addslashes($lang_up['delete file']) ?>')) return !1;

			ref.className = 'upf-loading';

			var req = cr_req();
			if (req) {
				req.onreadystatechange = function() {
					orsc(req, ref);
				};
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
				else f = '<?= $lang_up['texte'] ?>';

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
			} else doc.addEventListener('DOMContentLoaded', FluxBB.upfile.run, false);
		}
	};
}(document, window));

FluxBB.upfile.init();
/* ]]> */
</script>
<?php

require PUN_ROOT . 'footer.php';
