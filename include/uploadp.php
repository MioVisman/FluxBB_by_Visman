<?php

/**
 * Copyright (C) 2011-2019 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (! defined('PUN')) {
	exit;
}

if (isset($pun_config['o_upload_config'])) {
	if ($pun_user['g_id'] == PUN_ADMIN || ($id == $pun_user['id'] && $pun_user['g_up_limit'] > 0 && $pun_user['g_up_max'] > 0)) {
		if (file_exists(PUN_ROOT . 'lang/' . $pun_user['language'] . '/upload.php')) {
			require PUN_ROOT . 'lang/' . $pun_user['language'] . '/upload.php';
		} else {
			require PUN_ROOT . 'lang/English/upload.php';
		}

		echo "\t\t\t\t\t" . '<li' . (($page == 'upload') ? ' class="isactive"' : '') . '><a href="upfiles.php?id=' . $id . '">' . $lang_up['upfiles'] . '</a></li>' . "\n";
	}
}
