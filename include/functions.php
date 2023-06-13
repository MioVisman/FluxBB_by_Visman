<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */


//
// Cookie stuff!
//
function check_cookie(array &$pun_user)
{
	global $db, $db_type, $pun_config, $cookie_name, $cookie_seed;

	$now = time();

	// If the cookie is set and it matches the correct pattern, then read the values from it
	if (is_string($_COOKIE[$cookie_name] ?? null) && preg_match('%^(\d+)\|([0-9a-fA-F]+)\|(\d+)\|([0-9a-fA-F]+)$%', $_COOKIE[$cookie_name], $matches))
	{
		$cookie = array(
			'user_id'			=> intval($matches[1]),
			'password_hash' 	=> $matches[2],
			'expiration_time'	=> intval($matches[3]),
			'cookie_hash'		=> $matches[4],
		);
	}

	// If it has a non-guest user, and hasn't expired
	// Use WHILE instead of IF to simplify this function - Visman
	while (isset($cookie) && $cookie['user_id'] > 1 && $cookie['expiration_time'] > $now)
	{
		// If the cookie has been tampered with
		if (!hash_equals(forum_hmac($cookie['user_id'].'|'.$cookie['expiration_time'], $cookie_seed.'_cookie_hash'), $cookie['cookie_hash']))
			break; // jump to the end - Visman

		// Кто в этой теме - , o.witt_data - Visman
		// Check if there's a user with the user ID and password hash from the cookie
		$result = $db->query('SELECT u.*, g.*, o.logged, o.idle, o.witt_data FROM '.$db->prefix.'users AS u INNER JOIN `'.$db->prefix.'groups` AS g ON u.group_id=g.g_id LEFT JOIN '.$db->prefix.'online AS o ON o.user_id=u.id WHERE u.id='.$cookie['user_id']) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
		$pun_user = $db->fetch_assoc($result);

		// If user authorisation failed
		if (!isset($pun_user['id']) || !hash_equals(forum_hmac($pun_user['password'], $cookie_seed.'_password_hash'), $cookie['password_hash']))
			break; // jump to the end - Visman

		// проверка ip админа и модератора - Visman
		if ($pun_config['o_check_ip'] == '1' && ($pun_user['g_id'] == PUN_ADMIN || $pun_user['g_moderator'] == '1') && $pun_user['registration_ip'] != get_remote_address())
			break; // jump to the end - Visman

		// Send a new, updated cookie with a new expiration timestamp
		$expire = $cookie['expiration_time'] > $now + $pun_config['o_timeout_visit'] ? $now + 1209600 : $now + $pun_config['o_timeout_visit'];
		pun_setcookie($pun_user['id'], $pun_user['password'], $expire);

		// Set a default language if the user selected language no longer exists
		if (!file_exists(PUN_ROOT.'lang/'.$pun_user['language']))
			$pun_user['language'] = $pun_config['o_default_lang'];

		// Set a default style if the user selected style no longer exists
		if (!file_exists(PUN_ROOT.'style/'.$pun_user['style'].'.css'))
			$pun_user['style'] = $pun_config['o_default_style'];

		if (!$pun_user['disp_topics'])
			$pun_user['disp_topics'] = $pun_config['o_disp_topics_default'];
		if (!$pun_user['disp_posts'])
			$pun_user['disp_posts'] = $pun_config['o_disp_posts_default'];

		// Define this if you want this visit to affect the online list and the users last visit data
		if (!defined('PUN_QUIET_VISIT'))
		{
			// Update the online list
			if (!$pun_user['logged'])
			{
				$pun_user['logged'] = $now;
				$pun_user['new_visit'] = true;

				// With MySQL/MySQLi/SQLite, REPLACE INTO avoids a user having two rows in the online table
				switch ($db_type)
				{
					case 'mysql':
					case 'mysqli':
					case 'mysql_innodb':
					case 'mysqli_innodb':
					case 'sqlite3':
						witt_query('REPLACE INTO '.$db->prefix.'online (user_id, ident, logged:?comma?::?column?:) VALUES ('.$pun_user['id'].', \''.$db->escape($pun_user['username']).'\', '.$pun_user['logged'].':?comma?::?value?:)'); // MOD Кто в этой теме - Visman
						break;

					default:
						witt_query('INSERT INTO '.$db->prefix.'online (user_id, ident, logged:?comma?::?column?:) SELECT '.$pun_user['id'].', \''.$db->escape($pun_user['username']).'\', '.$pun_user['logged'].':?comma?::?value?: WHERE NOT EXISTS (SELECT 1 FROM '.$db->prefix.'online WHERE user_id='.$pun_user['id'].')'); // MOD Кто в этой теме - Visman
						break;
				}

				// Reset tracked topics
				set_tracked_topics(null);
			}
			else
			{
				// Special case: We've timed out, but no other user has browsed the forums since we timed out
				if ($pun_user['logged'] < ($now-$pun_config['o_timeout_visit']))
				{
					$db->query('UPDATE '.$db->prefix.'users SET last_visit='.$pun_user['logged'].' WHERE id='.$pun_user['id']) or error('Unable to update user visit data', __FILE__, __LINE__, $db->error());
					$pun_user['last_visit'] = $pun_user['logged'];
				}

				$idle_sql = $pun_user['idle'] == '1' ? ', idle=0' : '';
				witt_query('UPDATE '.$db->prefix.'online SET logged='.$now.$idle_sql.':?comma?::?column?::?equal?::?value?: WHERE user_id='.$pun_user['id']); // MOD Кто в этой теме - Visman

				// Update tracked topics with the current expire time
				if (is_string($_COOKIE[$cookie_name.'_track'] ?? null))
					forum_setcookie($cookie_name.'_track', $_COOKIE[$cookie_name.'_track'], $now + $pun_config['o_timeout_visit']);
			}
		}
		else
		{
			if (!$pun_user['logged'])
				$pun_user['logged'] = $pun_user['last_visit'];
		}

		$pun_user['is_guest'] = false;
		$pun_user['is_admmod'] = $pun_user['g_id'] == PUN_ADMIN || $pun_user['g_moderator'] == '1';

		$pun_user['is_bot'] = false; // MOD определения ботов - Visman

		return;
	}

	// delete an expired or invalid cookie - Visman
	if (isset($_COOKIE[$cookie_name]))
		forum_setcookie($cookie_name, '', 1);

	set_default_user();
}


//
// Converts the CDATA end sequence ]]> into ]]&gt;
//
function escape_cdata(string $str)
{
	return str_replace(']]>', ']]&gt;', $str);
}


//
// Authenticates the provided username and password against the user database
// $user can be either a user ID (integer) or a username (string)
// $password can be either a plaintext password or a password hash including salt ($password_is_hash must be set accordingly)
//
function authenticate_user($user, string $password, bool $password_is_hash = false)
{
	global $db, $pun_user;

	// Check if there's a user matching $user and $password
	$result = $db->query('SELECT u.*, g.*, o.logged, o.idle FROM '.$db->prefix.'users AS u INNER JOIN `'.$db->prefix.'groups` AS g ON g.g_id=u.group_id LEFT JOIN '.$db->prefix.'online AS o ON o.user_id=u.id WHERE '.(is_int($user) ? 'u.id='.intval($user) : 'u.username=\''.$db->escape($user).'\'')) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	$pun_user = $db->fetch_assoc($result);

	if (
		isset($pun_user['id'])
		&& $pun_user['id'] > 1
		&& (
			($password_is_hash && true === hash_equals($password, $pun_user['password']))
			|| (! $password_is_hash && false !== forum_password_verify($password, $pun_user))
		)
	) {
		$pun_user['is_guest'] = false;
		$pun_user['is_admmod'] = $pun_user['g_id'] == PUN_ADMIN || $pun_user['g_moderator'] == '1';
		$pun_user['is_bot'] = false; // MOD определения ботов - Visman
	} else {
		set_default_user();
	}
}


//
// Try to determine the current URL
//
function get_current_url(int $max_length = 0)
{
	$protocol = get_current_protocol();
	$port = isset($_SERVER['SERVER_PORT']) && (($_SERVER['SERVER_PORT'] != '80' && $protocol == 'http') || ($_SERVER['SERVER_PORT'] != '443' && $protocol == 'https')) && strpos($_SERVER['HTTP_HOST'], ':') === false ? ':'.$_SERVER['SERVER_PORT'] : '';

	$url = urldecode($protocol.'://'.$_SERVER['HTTP_HOST'].$port.$_SERVER['REQUEST_URI']);

	if (strlen($url) <= $max_length || $max_length == 0)
		return $url;

	// We can't find a short enough url
	return null;
}


//
// Fetch the current protocol in use - http or https
//
function get_current_protocol()
{
	$protocol = 'http';

	// Check if the server is claiming to using HTTPS
	if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off')
		$protocol = 'https';

	// If we are behind a reverse proxy try to decide which protocol it is using
	if (defined('FORUM_BEHIND_REVERSE_PROXY'))
	{
		// Check if we are behind a Microsoft based reverse proxy
		if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) != 'off')
			$protocol = 'https';

		// Check if we're behind a "proper" reverse proxy, and what protocol it's using
		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
			$protocol = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);
	}

	return $protocol;
}


//
// Fetch the base_url, optionally support HTTPS and HTTP
//
function get_base_url(bool $support_https = false)
{
	global $pun_config;
	static $base_url;

	if (!$support_https)
		return $pun_config['o_base_url'];

	if (!isset($base_url))
	{
		// Make sure we are using the correct protocol
		$base_url = str_replace(array('http://', 'https://'), get_current_protocol().'://', $pun_config['o_base_url']);
	}

	return $base_url;
}


//
// Fetch admin IDs
//
function get_admin_ids()
{
	if (file_exists(FORUM_CACHE_DIR.'cache_admins.php'))
		include FORUM_CACHE_DIR.'cache_admins.php';

	if (!defined('PUN_ADMINS_LOADED'))
	{
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_admins_cache();
		require FORUM_CACHE_DIR.'cache_admins.php';
	}

	return $pun_admins;
}


//
// Fill $pun_user with default values (for guests)
//
function set_default_user()
{
	global $db, $db_type, $pun_user, $pun_config, $cookie_name;

	$remote_addr = get_remote_address();

	// MOD определения ботов - Visman
	if (!defined('FORUM_BOT_FUNCTIONS_LOADED'))
		include PUN_ROOT.'include/bots.inc.php';
	$remote_addr = ua_isbotex($remote_addr);

	// Кто в этой теме - , o.witt_data - Visman
	// Fetch guest user
	$result = $db->query('SELECT u.*, g.*, o.logged, o.last_post, o.last_search, o.witt_data FROM '.$db->prefix.'users AS u INNER JOIN `'.$db->prefix.'groups` AS g ON u.group_id=g.g_id LEFT JOIN '.$db->prefix.'online AS o ON o.ident=\''.$db->escape($remote_addr).'\' WHERE u.id=1') or error('Unable to fetch guest information', __FILE__, __LINE__, $db->error());
	$pun_user = $db->fetch_assoc($result);

	if (!$pun_user)
		exit('Unable to fetch guest information. Your database must contain both a guest user and a guest user group.');

	// Update online list
	if (!$pun_user['logged'])
	{
		$pun_user['logged'] = time();
		$pun_user['new_visit'] = true;

		// With MySQL/MySQLi/SQLite, REPLACE INTO avoids a user having two rows in the online table
		switch ($db_type)
		{
			case 'mysql':
			case 'mysqli':
			case 'mysql_innodb':
			case 'mysqli_innodb':
			case 'sqlite3':
				witt_query('REPLACE INTO '.$db->prefix.'online (user_id, ident, logged:?comma?::?column?:) VALUES (1, \''.$db->escape($remote_addr).'\', '.$pun_user['logged'].':?comma?::?value?:)'); // MOD Кто в этой теме - Visman
				break;

			default:
				witt_query('INSERT INTO '.$db->prefix.'online (user_id, ident, logged:?comma?::?column?:) SELECT 1, \''.$db->escape($remote_addr).'\', '.$pun_user['logged'].':?comma?::?value?: WHERE NOT EXISTS (SELECT 1 FROM '.$db->prefix.'online WHERE ident=\''.$db->escape($remote_addr).'\')'); // MOD Кто в этой теме - Visman
				break;
		}
	}
	else
		witt_query('UPDATE '.$db->prefix.'online SET logged='.time().':?comma?::?column?::?equal?::?value?: WHERE ident=\''.$db->escape($remote_addr).'\''); // MOD Кто в этой теме - Visman

	$pun_user['disp_topics'] = $pun_config['o_disp_topics_default'];
	$pun_user['disp_posts'] = $pun_config['o_disp_posts_default'];
	$pun_user['timezone'] = $pun_config['o_default_timezone'];
	$pun_user['dst'] = $pun_config['o_default_dst'];
	$pun_user['language'] = $pun_config['o_default_lang'];
	$pun_user['style'] = $pun_config['o_default_style'];
	$pun_user['is_guest'] = true;
	$pun_user['is_admmod'] = false;
	$pun_user['is_bot'] = strpos($remote_addr, '[Bot]') !== false; // MOD определения ботов - Visman
	$pun_user['ident'] = $remote_addr; // Кто в этой теме - Visman

	// быстрое переключение языка - Visman
	$language = $_COOKIE[$cookie_name.'_glang'] ?? null;
	if (is_string($language) && in_array($language, forum_list_langs(), true))
		$pun_user['language'] = $language;
	// быстрое переключение языка - Visman
}


//
// SHA1 HMAC with PHP 4 fallback
//
function forum_hmac(string $data, string $key, bool $raw_output = false)
{
	return hash_hmac('sha1', $data, $key, $raw_output);
}


//
// Set a cookie, FluxBB style!
// Wrapper for forum_setcookie
//
function pun_setcookie(int $user_id, string $password_hash, int $expire)
{
	global $cookie_name, $cookie_seed;

	forum_setcookie($cookie_name, $user_id.'|'.forum_hmac($password_hash, $cookie_seed.'_password_hash').'|'.$expire.'|'.forum_hmac($user_id.'|'.$expire, $cookie_seed.'_cookie_hash'), $expire);
}


//
// Set a cookie, FluxBB style!
//
function forum_setcookie(string $name, string $value, int $expire)
{
	global $cookie_path, $cookie_domain, $cookie_secure, $pun_config, $cookie_samesite;

	if ($expire > 1 && $expire - time() - $pun_config['o_timeout_visit'] < 1)
		$expire = 0;

	if (empty($cookie_samesite))
		$cookie_samesite = 'Lax';
	else if ($cookie_samesite !== 'Strict' && $cookie_samesite !== 'Lax' && $cookie_samesite !== 'None')
		$cookie_samesite = 'Lax';

	// Enable sending of a P3P header
	header('P3P: CP="CUR ADM"');

	if (PHP_VERSION_ID < 70300)
		setcookie($name, $value, $expire, $cookie_path.'; SameSite='.$cookie_samesite, $cookie_domain, $cookie_secure, true);
	else
		setcookie($name, $value, [
			'expires'  => $expire,
			'path'     => $cookie_path,
			'domain'   => $cookie_domain,
			'secure'   => $cookie_secure,
			'httponly' => true,
			'samesite' => $cookie_samesite,
		]);
}


//
// Check whether the connecting user is banned (and delete any expired bans while we're at it)
//
function check_bans()
{
	global $db, $pun_config, $lang_common, $pun_user, $pun_bans;

	// Admins and moderators aren't affected
	if ($pun_user['is_admmod'] || !$pun_bans)
		return;

	// Add a dot or a colon (depending on IPv4/IPv6) at the end of the IP address to prevent banned address
	// 192.168.0.5 from matching e.g. 192.168.0.50
	$user_ip = get_remote_address();
	$add = strpos($user_ip, '.') !== false ? '.' : ':';
	$user_ip .= $add;

	$username = mb_strtolower($pun_user['username']);

	$bans_altered = false;
	$is_banned = false;

	foreach ($pun_bans as $cur_ban)
	{
		// Has this ban expired?
		if ($cur_ban['expire'] != '' && $cur_ban['expire'] <= time())
		{
			$db->query('DELETE FROM '.$db->prefix.'bans WHERE id='.$cur_ban['id']) or error('Unable to delete expired ban', __FILE__, __LINE__, $db->error());
			$bans_altered = true;
			continue;
		}

		if (! $pun_user['is_guest'] && $cur_ban['username'] != '' && $username == $cur_ban['username']) {
			$is_banned = true;
			$suffix = '';
		}

		else if ($cur_ban['ip'] != '')
		{
			$cur_ban_ips = explode(' ', $cur_ban['ip']);

			$num_ips = count($cur_ban_ips);
			for ($i = 0; $i < $num_ips; ++$i)
			{
				// Add the proper ending to the ban
				$cur_ban_ips[$i] .= $add;

				if (substr($user_ip, 0, strlen($cur_ban_ips[$i])) == $cur_ban_ips[$i])
				{
					$is_banned = true;
					$suffix = ' ip';
					break;
				}
			}
		}

		if ($is_banned)
		{
			if (! $pun_user['is_guest']) {
				$db->query('DELETE FROM '.$db->prefix.'online WHERE ident=\''.$db->escape($pun_user['username']).'\'') or error('Unable to delete from online list', __FILE__, __LINE__, $db->error());
			}

			$prefix = $lang_common['Ban message' . $suffix] ?? $lang_common['Ban message'];
			message($prefix.' '.(($cur_ban['expire'] != '') ? $lang_common['Ban message 2'].' '.strtolower(format_time($cur_ban['expire'], true)).'. ' : '').(($cur_ban['message'] != '') ? $lang_common['Ban message 3'].'<br /><br /><strong>'.pun_htmlspecialchars($cur_ban['message']).'</strong><br /><br />' : '<br /><br />').$lang_common['Ban message 4'].' <a href="mailto:'.pun_htmlspecialchars($pun_config['o_admin_email']).'">'.pun_htmlspecialchars($pun_config['o_admin_email']).'</a>.', true);
		}
	}

	// If we removed any expired bans during our run-through, we need to regenerate the bans cache
	if ($bans_altered)
	{
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_bans_cache();
	}
}


//
// Check username
//
function check_username(string $username, int $exclude_id = null)
{
	global $db, $pun_config, $errors, $lang_prof_reg, $lang_register, $lang_common;

	// Convert multiple whitespace characters into one (to prevent people from registering with indistinguishable usernames)
	$username = preg_replace('%\s+%s', ' ', $username);

	// Validate username
	if (pun_strlen($username) < 2)
		$errors[] = $lang_prof_reg['Username too short'];
	else if (pun_strlen($username) > 25) // This usually doesn't happen since the form element only accepts 25 characters
		$errors[] = $lang_prof_reg['Username too long'];
	else if (!preg_match('%^\p{L}[\p{L}\p{N}_ ]+$%uD', $username)) // строгая проверка имени пользователя - Visman
		$errors[] = $lang_prof_reg['Username Error'];
	else if (!strcasecmp($username, 'Guest') || !pun_strcasecmp($username, $lang_common['Guest']))
		$errors[] = $lang_prof_reg['Username guest'];
	else if (preg_match('%[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}%', $username) || preg_match('%((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))%', $username))
		$errors[] = $lang_prof_reg['Username IP'];
	else if ((strpos($username, '[') !== false || strpos($username, ']') !== false) && strpos($username, '\'') !== false && strpos($username, '"') !== false)
		$errors[] = $lang_prof_reg['Username reserved chars'];
	else if (preg_match('%(?:\[/?(?:b|u|s|ins|del|em|i|h|colou?r|quote|code|img|url|email|list|\*|topic|post|forum|user)\]|\[(?:img|url|quote|list)=)%i', $username))
		$errors[] = $lang_prof_reg['Username BBCode'];

	// Check username for any censored words
	if ($pun_config['o_censoring'] == '1' && censor_words($username) != $username)
		$errors[] = $lang_register['Username censor'];

	// Check that the username (or a too similar username) is not already registered
	$query = !is_null($exclude_id) ? ' AND id!='.$exclude_id : '';

	$result = $db->query('SELECT username FROM '.$db->prefix.'users WHERE (UPPER(username)=UPPER(\''.$db->escape($username).'\') OR UPPER(username)=UPPER(\''.$db->escape(preg_replace('%[^\p{L}\p{N}]%u', '', $username)).'\')) AND id>1'.$query) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	$busy = $db->fetch_row($result);

	if (is_array($busy))
	{
		$errors[] = $lang_register['Username dupe 1'].' '.pun_htmlspecialchars($busy[0]).'. '.$lang_register['Username dupe 2'];
	}

	// Check username for any banned usernames
	if (isset(get_usernames_banlist()[mb_strtolower($username)])) {
		$errors[] = $lang_prof_reg['Banned username'];
	}
}


//
// Update "Users online"
// ф-ия переписана - Visman
function update_users_online(int $tid = 0, array &$witt_us = array())
{
	global $db, $pun_config, $onl_u, $onl_g, $onl_s;

	$now = time();
	$nn1 = $now-$pun_config['o_timeout_online'];
	$nn2 = $now-$pun_config['o_timeout_visit'];

	$kol = 0;

	// Fetch all online list entries that are older than "o_timeout_online"
	$result = $db->query('SELECT * FROM '.$db->prefix.'online') or error('Unable to fetch old entries from online list', __FILE__, __LINE__, $db->error());
	while ($cu = $db->fetch_assoc($result))
	{
		if ($cu['logged'] < $nn1)
		{
			// If the entry is a guest, delete it
			if ($cu['user_id'] == 1)
				$db->query('DELETE FROM '.$db->prefix.'online WHERE ident=\''.$db->escape($cu['ident']).'\'') or error('Unable to delete from online list', __FILE__, __LINE__, $db->error());
			else
			{
				// If the entry is older than "o_timeout_visit", update last_visit for the user in question, then delete him/her from the online list
				if ($cu['logged'] < $nn2)
				{
					$db->query('UPDATE '.$db->prefix.'users SET last_visit='.$cu['logged'].' WHERE id='.$cu['user_id']) or error('Unable to update user visit data', __FILE__, __LINE__, $db->error());
					$db->query('DELETE FROM '.$db->prefix.'online WHERE user_id='.$cu['user_id']) or error('Unable to delete from online list', __FILE__, __LINE__, $db->error());
				}
				else if ($cu['idle'] == '0')
				{
					$db->query('UPDATE '.$db->prefix.'online SET idle=1 WHERE user_id='.$cu['user_id']) or error('Unable to insert into online list', __FILE__, __LINE__, $db->error());
					$onl_s[] = $cu['ident'];
				}
				else
					$onl_s[] = $cu['ident'];
			}
		}
		else
		{
			$kol++;
			$onl_s[] = $cu['ident'];
			if ($cu['user_id'] == 1)
				$onl_g[] = $cu['ident'];
			else
				$onl_u[$cu['user_id']] = $cu['ident'];

			if ($tid > 0 && !empty($cu['witt_data']))
			{
				$witt_ar = unserialize($cu['witt_data']);
				if (isset($witt_ar[$tid]) && $witt_ar[$tid] > $now - WITT_ENABLE)
				{
					if ($cu['user_id'] == 1)
						$witt_us[1][] = $cu['ident'];
					else
						$witt_us[$cu['user_id']] = $cu['ident'];
				}
			}
		}
	}

	if ($pun_config['st_max_users'] < $kol)
	{
		$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$kol.'\' WHERE conf_name=\'st_max_users\'') or error('Unable to update config value \'st_max_users\'', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$now.'\' WHERE conf_name=\'st_max_users_time\'') or error('Unable to update config value \'st_max_users_time\'', __FILE__, __LINE__, $db->error());

		// Regenerate the config cache
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_config_cache();
	}
}


//
// Display the profile navigation menu
//
function generate_profile_menu(string $page = '')
{
	global $lang_profile, $pun_config, $pun_user, $id;

?>
<div id="profile" class="block2col">
	<div class="blockmenu">
		<h2><span><?php echo $lang_profile['Profile menu'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<ul>
					<li<?php if ($page == 'essentials') echo ' class="isactive"'; ?>><a href="profile.php?section=essentials&amp;id=<?php echo $id ?>"><?php echo $lang_profile['Section essentials'] ?></a></li>
					<li<?php if ($page == 'personal') echo ' class="isactive"'; ?>><a href="profile.php?section=personal&amp;id=<?php echo $id ?>"><?php echo $lang_profile['Section personal'] ?></a></li>
					<li<?php if ($page == 'messaging') echo ' class="isactive"'; ?>><a href="profile.php?section=messaging&amp;id=<?php echo $id ?>"><?php echo $lang_profile['Section messaging'] ?></a></li>
<?php if ($pun_config['o_avatars'] == '1' || $pun_config['o_signatures'] == '1'): ?>					<li<?php if ($page == 'personality') echo ' class="isactive"'; ?>><a href="profile.php?section=personality&amp;id=<?php echo $id ?>"><?php echo $lang_profile['Section personality'] ?></a></li>
<?php endif; ?>					<li<?php if ($page == 'display') echo ' class="isactive"'; ?>><a href="profile.php?section=display&amp;id=<?php echo $id ?>"><?php echo $lang_profile['Section display'] ?></a></li>
					<li<?php if ($page == 'privacy') echo ' class="isactive"'; ?>><a href="profile.php?section=privacy&amp;id=<?php echo $id ?>"><?php echo $lang_profile['Section privacy'] ?></a></li>
<?php require PUN_ROOT.'include/uploadp.php'; ?>
<?php if ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && $pun_user['g_mod_ban_users'] == '1')): ?>					<li<?php if ($page == 'admin') echo ' class="isactive"'; ?>><a href="profile.php?section=admin&amp;id=<?php echo $id ?>"><?php echo $lang_profile['Section admin'] ?></a></li>
<?php endif; ?>				</ul>
			</div>
		</div>
	</div>
<?php

}


//
// Outputs markup to display a user's avatar
//
function generate_avatar_markup(int $user_id)
{
	global $pun_config;

	$filetypes = array('jpg', 'gif', 'png');
	$avatar_markup = '';

	foreach ($filetypes as $cur_type)
	{
		$path = $pun_config['o_avatars_dir'].'/'.$user_id.'.'.$cur_type;

		if (file_exists(PUN_ROOT.$path) && $img_size = getimagesize(PUN_ROOT.$path))
		{
			$avatar_markup = '<img src="'.pun_htmlspecialchars(get_base_url(true).'/'.$path.'?m='.filemtime(PUN_ROOT.$path)).'" '.$img_size[3].' alt="" />';
			break;
		}
	}

	return $avatar_markup;
}


//
// Generate browser's title
//
function generate_page_title($page_title, int $p = null)
{
	global $lang_common;

	if (!is_array($page_title))
		$page_title = array($page_title);

	$page_title = array_reverse($page_title);

	if ($p > 1)
		$page_title[0] .= ' ('.sprintf($lang_common['Page'], forum_number_format($p)).')';

	$crumbs = implode($lang_common['Title separator'], $page_title);

	return $crumbs;
}


//
// Save array of tracked topics in cookie
//
function set_tracked_topics(?array $tracked_topics)
{
	global $cookie_name, $cookie_path, $cookie_domain, $cookie_secure, $pun_config;

	$cookie_data = '';
	if (!empty($tracked_topics))
	{
		// Sort the arrays (latest read first)
		arsort($tracked_topics['topics'], SORT_NUMERIC);
		arsort($tracked_topics['forums'], SORT_NUMERIC);

		// Homebrew serialization (to avoid having to run unserialize() on cookie data)
		foreach ($tracked_topics['topics'] as $id => $timestamp)
			$cookie_data .= 't'.$id.'='.$timestamp.';';
		foreach ($tracked_topics['forums'] as $id => $timestamp)
			$cookie_data .= 'f'.$id.'='.$timestamp.';';

		// Enforce a byte size limit (4096 minus some space for the cookie name - defaults to 4048)
		if (strlen($cookie_data) > FORUM_MAX_COOKIE_SIZE)
		{
			$cookie_data = substr($cookie_data, 0, FORUM_MAX_COOKIE_SIZE);
			$cookie_data = substr($cookie_data, 0, strrpos($cookie_data, ';')).';';
		}
	}

	forum_setcookie($cookie_name.'_track', $cookie_data, time() + $pun_config['o_timeout_visit']);
	$_COOKIE[$cookie_name.'_track'] = $cookie_data; // Set it directly in $_COOKIE as well
}


//
// Extract array of tracked topics from cookie
//
function get_tracked_topics()
{
	global $cookie_name;

	$cookie_data = $_COOKIE[$cookie_name.'_track'] ?? null;
	if (! is_string($cookie_data))
		return array('topics' => array(), 'forums' => array());

	if (strlen($cookie_data) > FORUM_MAX_COOKIE_SIZE)
		return array('topics' => array(), 'forums' => array());

	// Unserialize data from cookie
	$tracked_topics = array('topics' => array(), 'forums' => array());
	$temp = explode(';', $cookie_data);
	foreach ($temp as $t)
	{
		$type = substr($t, 0, 1) == 'f' ? 'forums' : 'topics';
		$id = intval(substr($t, 1));
		$timestamp = intval(substr($t, strpos($t, '=') + 1));
		if ($id > 0 && $timestamp > 0)
			$tracked_topics[$type][$id] = $timestamp;
	}

	return $tracked_topics;
}


//
// Shortcut method for executing all callbacks registered with the addon manager for the given hook
//
function flux_hook(string $name)
{
	global $flux_addons;

	$flux_addons->hook($name);
}


//
// Update posts, topics, last_post, last_post_id and last_poster for a forum
//
function update_forum(int $forum_id)
{
	global $db;

	$result = $db->query('SELECT COUNT(id), SUM(num_replies) FROM '.$db->prefix.'topics WHERE forum_id='.$forum_id) or error('Unable to fetch forum topic count', __FILE__, __LINE__, $db->error());
	list($num_topics, $num_posts) = $db->fetch_row($result);

	$num_posts = $num_posts + $num_topics; // $num_posts is only the sum of all replies (we have to add the topic posts)

	$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'topics WHERE forum_id='.$forum_id.' AND moved_to IS NOT NULL') or error('Unable to fetch moved topic count', __FILE__, __LINE__, $db->error());
	$num_posts-= $db->result($result);

	$result = $db->query('SELECT last_post, last_post_id, last_poster, subject FROM '.$db->prefix.'topics WHERE forum_id='.$forum_id.' AND moved_to IS NULL ORDER BY last_post DESC LIMIT 1') or error('Unable to fetch last_post/last_post_id/last_poster', __FILE__, __LINE__, $db->error()); // last topic on index - Visman
	$post_info = $db->fetch_row($result);

	if (is_array($post_info)) // There are topics in the forum
	{
		list($last_post, $last_post_id, $last_poster, $last_topic) = $post_info;

		$db->query('UPDATE '.$db->prefix.'forums SET num_topics='.$num_topics.', num_posts='.$num_posts.', last_post='.$last_post.', last_post_id='.$last_post_id.', last_poster=\''.$db->escape($last_poster).'\', last_topic=\''.$db->escape($last_topic).'\' WHERE id='.$forum_id) or error('Unable to update last_post/last_post_id/last_poster', __FILE__, __LINE__, $db->error()); // last topic on index - Visman
	}
	else // There are no topics
		$db->query('UPDATE '.$db->prefix.'forums SET num_topics='.$num_topics.', num_posts='.$num_posts.', last_post=NULL, last_post_id=NULL, last_poster=NULL, last_topic=NULL WHERE id='.$forum_id) or error('Unable to update last_post/last_post_id/last_poster', __FILE__, __LINE__, $db->error()); // last topic on index - Visman
}


//
// Deletes any avatars owned by the specified user ID
//
function delete_avatar(int $user_id)
{
	global $pun_config;

	$filetypes = array('jpg', 'gif', 'png');

	// Delete user avatar
	foreach ($filetypes as $cur_type)
	{
		if (file_exists(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$user_id.'.'.$cur_type))
			@unlink(PUN_ROOT.$pun_config['o_avatars_dir'].'/'.$user_id.'.'.$cur_type);
	}
}


//
// Delete a topic and all of it's posts
//
function delete_topic(int $topic_id, int $flag_f = 1) // not sum - Visman
{
	global $db;

	// Delete the topic and any redirect topics
	$db->query('DELETE FROM '.$db->prefix.'topics WHERE id='.$topic_id.' OR moved_to='.$topic_id) or error('Unable to delete topic', __FILE__, __LINE__, $db->error());

	if ($flag_f == 0) // уменьшение постов у юзеров и not sum - Visman
	{
		$result = $db->query('SELECT COUNT(id), poster_id FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id.' GROUP BY poster_id') or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());
		while ($row = $db->fetch_row($result))
		{
			if ($row[1] > 1)
				$db->query('UPDATE '.$db->prefix.'users SET num_posts = num_posts-'.$row[0].' WHERE id='.$row[1]) or error('Unable to update posters messages count', __FILE__, __LINE__, $db->error());
		}
	}

	// Create a list of the post IDs in this topic
	$post_ids = '';
	$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());
	while ($row = $db->fetch_row($result))
		$post_ids .= $post_ids != '' ? ','.$row[0] : $row[0];

	// Make sure we have a list of post IDs
	if ($post_ids != '')
	{
		// MOD warnings - Visman
		$db->query('DELETE FROM '.$db->prefix.'warnings WHERE id IN ('.$post_ids.')') or error('Unable to delete warnings', __FILE__, __LINE__, $db->error());

		strip_search_index($post_ids);

		// Delete posts in topic
		$db->query('DELETE FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id) or error('Unable to delete posts', __FILE__, __LINE__, $db->error());
	}

	// Delete any subscriptions for this topic
	$db->query('DELETE FROM '.$db->prefix.'topic_subscriptions WHERE topic_id='.$topic_id) or error('Unable to delete subscriptions', __FILE__, __LINE__, $db->error());

	global $pun_user;
	require PUN_ROOT.'include/poll.php';
	poll_delete($topic_id);
}


//
// Delete a single post
//
function delete_post(int $post_id, int $topic_id)
{
	global $db;

	$result = $db->query('SELECT id, poster, posted FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id.' ORDER BY id DESC LIMIT 2') or error('Unable to fetch post info', __FILE__, __LINE__, $db->error());
	list($last_id, ,) = $db->fetch_row($result);
	list($second_last_id, $second_poster, $second_posted) = $db->fetch_row($result);

	// Delete the post
	$db->query('DELETE FROM '.$db->prefix.'posts WHERE id='.$post_id) or error('Unable to delete post', __FILE__, __LINE__, $db->error());
	// MOD warnings - Visman
	$db->query('DELETE FROM '.$db->prefix.'warnings WHERE id='.$post_id) or error('Unable to delete warnings', __FILE__, __LINE__, $db->error());

	strip_search_index($post_id);

	// Count number of replies in the topic
	$result = $db->query('SELECT COUNT(id) FROM '.$db->prefix.'posts WHERE topic_id='.$topic_id) or error('Unable to fetch post count for topic', __FILE__, __LINE__, $db->error());
	$num_replies = $db->result($result, 0) - 1;

	// If the message we deleted is the most recent in the topic (at the end of the topic)
	if ($last_id == $post_id)
	{
		// If there is a $second_last_id there is more than 1 reply to the topic
		if (!empty($second_last_id))
			$db->query('UPDATE '.$db->prefix.'topics SET last_post='.$second_posted.', last_post_id='.$second_last_id.', last_poster=\''.$db->escape($second_poster).'\', num_replies='.$num_replies.' WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
		else
			// We deleted the only reply, so now last_post/last_post_id/last_poster is posted/id/poster from the topic itself
			$db->query('UPDATE '.$db->prefix.'topics SET last_post=posted, last_post_id=id, last_poster=poster, num_replies='.$num_replies.' WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
	}
	else
		// Otherwise we just decrement the reply counter
		$db->query('UPDATE '.$db->prefix.'topics SET num_replies='.$num_replies.' WHERE id='.$topic_id) or error('Unable to update topic', __FILE__, __LINE__, $db->error());
}


//
// Delete every .php file in the forum's cache directory
//
function forum_clear_cache()
{
	$d = dir(FORUM_CACHE_DIR);
	while (($entry = $d->read()) !== false)
	{
		if (substr($entry, -4) == '.php')
			@unlink(FORUM_CACHE_DIR.$entry);
	}
	$d->close();
}


//
// Replace censored words in $text
//
function censor_words(string $text)
{
	global $db;
	static $search_for, $replace_with;

	// If not already built in a previous call, build an array of censor words and their replacement text
	if (!isset($search_for))
	{
		if (file_exists(FORUM_CACHE_DIR.'cache_censoring.php'))
			include FORUM_CACHE_DIR.'cache_censoring.php';

		if (!defined('PUN_CENSOR_LOADED'))
		{
			if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
				require PUN_ROOT.'include/cache.php';

			generate_censoring_cache();
			require FORUM_CACHE_DIR.'cache_censoring.php';
		}
	}

	if (!empty($search_for))
		$text = substr(preg_replace($search_for, $replace_with, ' '.$text.' '), 1, -1);

	return $text;
}

//
// This function returns an array in which the banned usernames are the keys - Visman
//
function get_usernames_banlist()
{
	global $pun_bans;
	static $ban_list;

	if (! isset($ban_list))
	{
		$ban_list = [];

		foreach ($pun_bans as $cur_ban) {
			if (is_string($cur_ban['username'])) {
				$ban_list[$cur_ban['username']] = true;
			}
		}
	}

	return $ban_list;
}


//
// Determines the correct title for $user
// $user must contain the elements 'username', 'title', 'posts', 'g_id' and 'g_user_title'
//
function get_title(array $user)
{
	global $lang_common, $pun_config;

	// If the user is banned
	if (isset(get_usernames_banlist()[mb_strtolower($user['username'])]))
		$user_title = $lang_common['Banned'];
	// If the user has a custom title
	else if ($user['title'] != '')
		$user_title = pun_htmlspecialchars($pun_config['o_censoring'] == '1' ? censor_words($user['title']) : $user['title']);
	// If the user group has a default user title
	else if ($user['g_user_title'] != '')
		$user_title = pun_htmlspecialchars($user['g_user_title']);
	// If the user is a guest
	else if ($user['g_id'] == PUN_GUEST)
		$user_title = $lang_common['Guest'];
	// If nothing else helps, we assign the default
	else
		$user_title = $lang_common['Member'];

	return $user_title;
}


//
// Generate a string with numbered links (for multipage scripts)
//
function paginate(int $num_pages, int $cur_page, string $link)
{
	global $lang_common;

	$pages = array();

	if ($num_pages <= 1)
	{
		$pages = $cur_page < 0 ? [] : ['<strong class="item1">1</strong>'];
	}
	// If $cur_page == -1, we link to all pages (used in viewforum.php)
	else if ($cur_page == -1)
	{
		if ($num_pages > 999)
			$d = 2;
		else if ($num_pages > 99)
			$d = 3;
		else
			$d = min(4, $num_pages - 2);

		$current = $num_pages - $d;

		if ($current > 2)
			$pages[] = '<span class="spacer">'.$lang_common['Spacer'].'</span>';

		for ($current; $current <= $num_pages; ++$current)
			$pages[] = '<a href="'.$link.'&amp;p='.$current.'">'.forum_number_format($current).'</a>';
	}
	else
	{
		// Add a previous page link
		if ($num_pages > 1 && $cur_page > 1)
			$pages[] = '<a rel="prev"'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.($cur_page == 2 ? '' : '&amp;p='.($cur_page - 1)).'">'.$lang_common['Previous'].'</a>';

		if ($cur_page > 3)
		{
			$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.'">1</a>';

			if ($cur_page > 5)
				$pages[] = '<span class="spacer">'.$lang_common['Spacer'].'</span>';
		}

		// Don't ask me how the following works. It just does, OK? :-)
		for ($current = $cur_page == 5 ? $cur_page - 3 : $cur_page - 2, $stop = $cur_page + 4 == $num_pages ? $cur_page + 4 : $cur_page + 3; $current < $stop; ++$current)
		{
			if ($current < 1 || $current > $num_pages)
				continue;
			else if ($current != $cur_page)
				$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.($current == 1 ? '' : '&amp;p='.$current).'">'.forum_number_format($current).'</a>';
			else
				$pages[] = '<strong'.(empty($pages) ? ' class="item1"' : '').'>'.forum_number_format($current).'</strong>';
		}

		if ($cur_page <= $num_pages - 3)
		{
			if ($cur_page != ($num_pages-3) && $cur_page != ($num_pages-4))
				$pages[] = '<span class="spacer">'.$lang_common['Spacer'].'</span>';

			$pages[] = '<a'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.'&amp;p='.$num_pages.'">'.forum_number_format($num_pages).'</a>';
		}

		// Add a next page link
		if ($num_pages > 1 && $cur_page < $num_pages)
			$pages[] = '<a rel="next"'.(empty($pages) ? ' class="item1"' : '').' href="'.$link.'&amp;p='.($cur_page +1).'">'.$lang_common['Next'].'</a>';
	}

	return implode(' ', $pages);
}


//
// Display a message
//
function message(string $message, bool $no_back_link = false, string $http_status = null)
{
	global $db, $lang_common, $pun_config, $pun_start, $tpl_main, $pun_user, $page_js;

	witt_query(); // MOD Кто в этой теме - Visman

	// Did we receive a custom header?
	if (!is_null($http_status)) {
		header('HTTP/1.1 ' . $http_status);
	}

	if (!defined('PUN_HEADER'))
	{
		$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Info']);
		define('PUN_ACTIVE_PAGE', 'index');
		require PUN_ROOT.'header.php';
	}

?>

<div id="msg" class="block">
	<h2><span><?php echo $lang_common['Info'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<p><?php echo $message ?></p>
<?php if (!$no_back_link): ?>			<p><a href="javascript: history.go(-1)"><?php echo $lang_common['Go back'] ?></a></p>
<?php endif; ?>		</div>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


//
// Format a time string according to $time_format and time zones
//
function format_time($timestamp, bool $date_only = false, ?string $date_format = null, ?string $time_format = null, bool $time_only = false, bool $no_text = false, array $user = null)
{
	global $lang_common, $pun_user, $forum_date_formats, $forum_time_formats;

	if ($timestamp == '')
		return $lang_common['Never'];

	if (is_null($user))
		$user = $pun_user;

	$diff = ($user['timezone'] + $user['dst']) * 3600;
	$timestamp += $diff;
	$now = time();

	if (is_null($date_format))
		$date_format = $forum_date_formats[$user['date_format']];

	if (is_null($time_format))
		$time_format = $forum_time_formats[$user['time_format']];

	$date = gmdate($date_format, $timestamp);
	$today = gmdate($date_format, $now+$diff);
	$yesterday = gmdate($date_format, $now+$diff-86400);

	if (!$no_text)
	{
		if ($date == $today)
			$date = $lang_common['Today'];
		else if ($date == $yesterday)
			$date = $lang_common['Yesterday'];
	}

	if ($date_only)
		return $date;
	else if ($time_only)
		return gmdate($time_format, $timestamp);
	else
		return $date.' '.gmdate($time_format, $timestamp);
}


//
// A wrapper for PHP's number_format function
//
function forum_number_format($number, int $decimals = 0)
{
	global $lang_common;

	return is_numeric($number) ? number_format($number, $decimals, $lang_common['lang_decimal_point'], $lang_common['lang_thousands_sep']) : $number;
}


//
// Generate a random key of length $len
//
function random_key(int $len, bool $readable = false, bool $hash = false)
{
	$key = '';

	if ($hash)
		$key = substr(bin2hex(random_bytes(intdiv($len, 2) + 1)), 0, $len);
	else if ($readable)
	{
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
		$max = strlen($chars) - 1;

		for ($i = 0; $i < $len; ++$i)
			$key .= $chars[random_int(0, $max)];
	}
	else
	{
		for ($i = 0; $i < $len; ++$i)
			$key .= chr(random_int(33, 126));
	}

	return $key;
}


//
// Make sure that HTTP_REFERER matches base_url/script
// ********* новые ф-ии проверки подлинности - Visman
// rev 59 - Variant of functions not depending on Base URL
function confirm_message($error_msg = false)
{
	global $lang_common, $errors;

	if (isset($errors) && is_array($errors))
		$errors[] = $error_msg ? $error_msg : $lang_common['Bad referrer'];
	else
		message($error_msg ? $error_msg : $lang_common['Bad referrer']);
}


function confirm_referrer(string $script, $error_msg = false, $use_ip = true)
{
	$hash = $_POST['csrf_hash'] ?? ($_GET['csrf_hash'] ?? null);

	if (! is_string($hash) || ! hash_equals(csrf_hash($script, $use_ip), $hash))
		confirm_message($error_msg);
}


function csrf_hash($script = false, $use_ip = true, $user = false)
{
	global $pun_config, $pun_user;
	static $arr = array();

	$script = $script ?: basename($_SERVER['SCRIPT_NAME']);
	$ip = $use_ip ? get_remote_address() : '';
	$user = is_array($user) ? $user : $pun_user;

	$key = $script.$ip.$user['id'].get_current_protocol();

	if (!isset($arr[$key]))
		$arr[$key] = pun_hash(PUN_ROOT.$script.$user['id'].pun_hash($ip.$user['password'].$pun_config['o_crypto_pas'].get_current_protocol()));

	return $arr[$key];
}
// ********* новые ф-ии проверки подлинности - Visman


//
// Validate the given redirect URL, use the fallback otherwise
//
function validate_redirect(string $redirect_url, ?string $fallback_url)
{
	$referrer = parse_url(strtolower($redirect_url));

	// Make sure the host component exists
	if (!isset($referrer['host']))
		$referrer['host'] = '';

	// Remove www subdomain if it exists
	if (strpos($referrer['host'], 'www.') === 0)
		$referrer['host'] = substr($referrer['host'], 4);

	// Make sure the path component exists
	if (!isset($referrer['path']))
		$referrer['path'] = '';

	$valid = parse_url(strtolower(get_base_url()));

	// Remove www subdomain if it exists
	if (strpos($valid['host'], 'www.') === 0)
		$valid['host'] = substr($valid['host'], 4);

	// Make sure the path component exists
	if (!isset($valid['path']))
		$valid['path'] = '';

	if ($referrer['host'] == $valid['host'] && preg_match('%^'.preg_quote($valid['path'], '%').'/(.*?)\.php%i', $referrer['path']))
		return $redirect_url;
	else
		return $fallback_url;
}


//
// Generate a random password of length $len
// Compatibility wrapper for random_key
//
function random_pass(int $len)
{
	return random_key($len, true);
}


//
// Compute a hash of $str
//
function pun_hash(string $str)
{
	global $salt1;

	return sha1($str.$salt1); // соль на пароль - Visman
}


//
// Compute a random hash used against CSRF attacks
//
function pun_csrf_token()
{
	global $pun_user;
	static $token;

	if (!isset($token))
		$token = pun_hash($pun_user['id'].$pun_user['password'].pun_hash(get_remote_address()));

	return $token;
}

//
// Check if the CSRF hash is correct
//
function check_csrf($token)
{
	global $lang_common;

	if (! is_string($token) || ! hash_equals($token, pun_csrf_token()))
		message($lang_common['Bad csrf hash'], false, '404 Not Found');
}


//
// Try to determine the correct remote IP-address
//
function get_remote_address()
{
	$remote_addr = $_SERVER['REMOTE_ADDR'];

	// If we are behind a reverse proxy try to find the real users IP
	if (defined('FORUM_BEHIND_REVERSE_PROXY'))
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			// The general format of the field is:
			// X-Forwarded-For: client1, proxy1, proxy2
			// where the value is a comma+space separated list of IP addresses, the left-most being the farthest downstream client,
			// and each successive proxy that passed the request adding the IP address where it received the request from.
			$forwarded_for = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$forwarded_for = trim($forwarded_for[0]);

			if (@preg_match('%^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$%', $forwarded_for) || @preg_match('%^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$%', $forwarded_for))
				$remote_addr = $forwarded_for;
		}
	}

	return $remote_addr;
}


//
// Calls htmlspecialchars with a few options already set
//
function pun_htmlspecialchars($str)
{
	return is_string($str) ? htmlspecialchars($str, ENT_QUOTES, 'UTF-8') : '';
}


//
// Calls htmlspecialchars_decode with a few options already set
//
function pun_htmlspecialchars_decode($str)
{
	return is_string($str) ? htmlspecialchars_decode($str, ENT_QUOTES) : '';
}


//
// A wrapper for mb_strlen for compatibility
//
function pun_strlen(string $str)
{
	return mb_strlen($str);
}


//
// Convert \r\n and \r to \n
//
function pun_linebreaks(string $str)
{
	return str_replace(array("\r\n", "\r"), "\n", $str);
}


//
// A wrapper for utf8_trim for compatibility
//
function pun_trim($str, string $charlist = null)
{
	if (! is_scalar($str)) {
		$str = '';
	}
	if (is_string($charlist)) {
		$charlist = preg_quote($charlist, '%');
		$str = preg_replace('%^[' . $charlist . ']+%u', '', $str);

		return preg_replace('%[' . $charlist . ']+$%u', '', $str);
	} else {
		return trim($str);
	}
}


//
// Checks if a string is in all uppercase
//
function is_all_uppercase(string $string)
{
	return mb_strtoupper($string) === $string && mb_strtolower($string) !== $string;
}


//
// strcmp() for UTF-8 - Visman
//
function pun_strcasecmp(string $strX, string $strY)
{
	return strcmp(mb_strtolower($strX), mb_strtolower($strY));
}


//
// Inserts $element into $input at $offset
// $offset can be either a numerical offset to insert at (eg: 0 inserts at the beginning of the array)
// or a string, which is the key that the new element should be inserted before
// $key is optional: it's used when inserting a new key/value pair into an associative array
//
function array_insert(array &$input, $offset, $element, $key = null)
{
	if (is_null($key))
		$key = $offset;

	// Determine the proper offset if we're using a string
	if (!is_int($offset))
		$offset = array_search($offset, array_keys($input), true);

	// Out of bounds checks
	if ($offset > count($input))
		$offset = count($input);
	else if ($offset < 0)
		$offset = 0;

	$input = array_merge(array_slice($input, 0, $offset), array($key => $element), array_slice($input, $offset));
}


//
// Display a message when board is in maintenance mode
//
function maintenance_message()
{
	global $db, $pun_config, $lang_common, $pun_user;

	header('HTTP/1.1 503 Service Unavailable');

	// Send no-cache headers
	// Send the Content-type header in case the web server is setup to send something else
	forum_http_headers();

	// Deal with newlines, tabs and multiple spaces
	$pattern = array("\t", '  ', '  ');
	$replace = array('&#160; &#160; ', '&#160; ', ' &#160;');
	$message = str_replace($pattern, $replace, $pun_config['o_maintenance_message']);

	if (file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/maintenance.tpl'))
	{
		$tpl_file = PUN_ROOT.'style/'.$pun_user['style'].'/maintenance.tpl';
		$tpl_inc_dir = PUN_ROOT.'style/'.$pun_user['style'].'/';
	}
	else
	{
		$tpl_file = PUN_ROOT.'include/template/maintenance.tpl';
		$tpl_inc_dir = PUN_ROOT.'include/user/';
	}

	$tpl_maint = file_get_contents($tpl_file);

	// START SUBST - <pun_include "*">
	preg_match_all('%<pun_include "([^/\\\\]*?)\.(php[45]?|inc|html?|txt)">%i', $tpl_maint, $pun_includes, PREG_SET_ORDER);

	foreach ($pun_includes as $cur_include)
	{
		ob_start();

		// Allow for overriding user includes, too.
		if (file_exists($tpl_inc_dir.$cur_include[1].'.'.$cur_include[2]))
			require $tpl_inc_dir.$cur_include[1].'.'.$cur_include[2];
		else if (file_exists(PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2]))
			require PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2];
		else
			error(sprintf($lang_common['Pun include error'], htmlspecialchars($cur_include[0]), basename($tpl_file)));

		$tpl_temp = ob_get_contents();
		$tpl_maint = str_replace($cur_include[0], $tpl_temp, $tpl_maint);
		ob_end_clean();
	}
	// END SUBST - <pun_include "*">


	// START SUBST - <pun_language>
	$tpl_maint = str_replace('<pun_language>', $lang_common['lang_identifier'], $tpl_maint);
	// END SUBST - <pun_language>


	// START SUBST - <pun_content_direction>
	$tpl_maint = str_replace('<pun_content_direction>', $lang_common['lang_direction'], $tpl_maint);
	// END SUBST - <pun_content_direction>


	// START SUBST - <pun_head>
	ob_start();

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Maintenance']);

?>
<title><?php echo generate_page_title($page_title) ?></title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $pun_user['style'].'.css' ?>" />
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_maint = str_replace('<pun_head>', $tpl_temp, $tpl_maint);
	ob_end_clean();
	// END SUBST - <pun_head>


	// START SUBST - <pun_maint_main>
	ob_start();

?>
<div class="block">
	<h2><?php echo $lang_common['Maintenance'] ?></h2>
	<div class="box">
		<div class="inbox">
			<p><?php echo $message ?></p>
		</div>
	</div>
</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_maint = str_replace('<pun_maint_main>', $tpl_temp, $tpl_maint);
	ob_end_clean();
	// END SUBST - <pun_maint_main>


	// End the transaction
	$db->end_transaction();


	// Close the db connection (and free up any result data)
	$db->close();

	exit($tpl_maint);
}


//
// Display $message and redirect user to $destination_url
//
function redirect(string $destination_url, string $message)
{
	global $db, $pun_config, $lang_common, $pun_user;

	// Prefix with base_url (unless there's already a valid URI)
	if (strpos($destination_url, 'http://') !== 0 && strpos($destination_url, 'https://') !== 0 && strpos($destination_url, '/') !== 0)
		$destination_url = get_base_url(true).'/'.$destination_url;

	// Do a little spring cleaning
	$destination_url = preg_replace('%([\r\n])|(\%0[ad])|(;\s*data\s*:)%i', '', $destination_url);

	// If the delay is 0 seconds, we might as well skip the redirect all together
	if ($pun_config['o_redirect_delay'] == '0')
	{
		$db->end_transaction();
		$db->close();

		header('Location: '.str_replace('&amp;', '&', $destination_url));
		exit;
	}

	// Send no-cache headers
	// Send the Content-type header in case the web server is setup to send something else
	forum_http_headers();

	if (file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/redirect.tpl'))
	{
		$tpl_file = PUN_ROOT.'style/'.$pun_user['style'].'/redirect.tpl';
		$tpl_inc_dir = PUN_ROOT.'style/'.$pun_user['style'].'/';
	}
	else
	{
		$tpl_file = PUN_ROOT.'include/template/redirect.tpl';
		$tpl_inc_dir = PUN_ROOT.'include/user/';
	}

	$tpl_redir = file_get_contents($tpl_file);

	// START SUBST - <pun_include "*">
	preg_match_all('%<pun_include "([^/\\\\]*?)\.(php[45]?|inc|html?|txt)">%i', $tpl_redir, $pun_includes, PREG_SET_ORDER);

	foreach ($pun_includes as $cur_include)
	{
		ob_start();

		// Allow for overriding user includes, too.
		if (file_exists($tpl_inc_dir.$cur_include[1].'.'.$cur_include[2]))
			require $tpl_inc_dir.$cur_include[1].'.'.$cur_include[2];
		else if (file_exists(PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2]))
			require PUN_ROOT.'include/user/'.$cur_include[1].'.'.$cur_include[2];
		else
			error(sprintf($lang_common['Pun include error'], htmlspecialchars($cur_include[0]), basename($tpl_file)));

		$tpl_temp = ob_get_contents();
		$tpl_redir = str_replace($cur_include[0], $tpl_temp, $tpl_redir);
		ob_end_clean();
	}
	// END SUBST - <pun_include "*">


	// START SUBST - <pun_language>
	$tpl_redir = str_replace('<pun_language>', $lang_common['lang_identifier'], $tpl_redir);
	// END SUBST - <pun_language>


	// START SUBST - <pun_content_direction>
	$tpl_redir = str_replace('<pun_content_direction>', $lang_common['lang_direction'], $tpl_redir);
	// END SUBST - <pun_content_direction>


	// START SUBST - <pun_head>
	ob_start();

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Redirecting']);

?>
<meta http-equiv="refresh" content="<?php echo $pun_config['o_redirect_delay'] ?>;URL=<?php echo $destination_url ?>" />
<title><?php echo generate_page_title($page_title) ?></title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $pun_user['style'].'.css' ?>" />
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_redir = str_replace('<pun_head>', $tpl_temp, $tpl_redir);
	ob_end_clean();
	// END SUBST - <pun_head>


	// START SUBST - <pun_redir_main>
	ob_start();

?>
<div class="block">
	<h2><?php echo $lang_common['Redirecting'] ?></h2>
	<div class="box">
		<div class="inbox">
			<p><?php echo $message.'<br /><br /><a href="'.$destination_url.'">'.$lang_common['Click redirect'].'</a>' ?></p>
		</div>
	</div>
</div>
<?php

	$tpl_temp = trim(ob_get_contents());
	$tpl_redir = str_replace('<pun_redir_main>', $tpl_temp, $tpl_redir);
	ob_end_clean();
	// END SUBST - <pun_redir_main>


	// START SUBST - <pun_footer>
	ob_start();

	// End the transaction
	$db->end_transaction();

	// Display executed queries (if enabled)
	if (defined('PUN_SHOW_QUERIES'))
		display_saved_queries();

	$tpl_temp = trim(ob_get_contents());
	$tpl_redir = str_replace('<pun_footer>', $tpl_temp, $tpl_redir);
	ob_end_clean();
	// END SUBST - <pun_footer>


	// Close the db connection (and free up any result data)
	$db->close();

	exit($tpl_redir);
}


//
// Display a simple error message
//
function error(string $message, string $file = null, $line = null, $db_error = false)
{
	global $pun_config, $lang_common;

	// Set some default settings if the script failed before $pun_config could be populated
	if (empty($pun_config))
	{
		$pun_config = array(
			'o_board_title'	=> 'FluxBB',
			'o_gzip'		=> '0'
		);
	}

	// Set some default translations if the script failed before $lang_common could be populated
	if (empty($lang_common))
	{
		$lang_common = array(
			'Title separator'	=> ' / ',
			'Page'				=> 'Page %s'
		);
	}

	// Empty all output buffers and stop buffering
	while (@ob_end_clean());

	// "Restart" output buffering if we are using ob_gzhandler (since the gzip header is already sent)
	if ($pun_config['o_gzip'] && extension_loaded('zlib'))
		ob_start('ob_gzhandler');

	header('HTTP/1.1 500 Internal Server Error');

	// Send no-cache headers
	// Send the Content-type header in case the web server is setup to send something else
	forum_http_headers();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php $page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), 'Error') ?>
<title><?php echo generate_page_title($page_title) ?></title>
<style type="text/css">
<!--
BODY {MARGIN: 10% 20% auto 20%; font: 10px Verdana, Arial, Helvetica, sans-serif}
#errorbox {BORDER: 1px solid #B84623}
H2 {MARGIN: 0; COLOR: #FFFFFF; BACKGROUND-COLOR: #B84623; FONT-SIZE: 1.1em; PADDING: 5px 4px}
#errorbox DIV {PADDING: 6px 5px; BACKGROUND-COLOR: #F1F1F1}
-->
</style>
</head>
<body>

<div id="errorbox">
	<h2>An error was encountered</h2>
	<div>
<?php

	if (defined('PUN_DEBUG') && !is_null($file) && !is_null($line))
	{
		$file = str_replace(realpath(PUN_ROOT), '', $file);

		echo "\t\t".'<strong>File:</strong> '.pun_htmlspecialchars($file).'<br />'."\n\t\t".'<strong>Line:</strong> '.((int) $line).'<br /><br />'."\n\t\t".'<strong>FluxBB reported</strong>: '.pun_htmlspecialchars($message)."\n";

		if ($db_error)
		{
			echo "\t\t".'<br /><br /><strong>Database reported:</strong> '.pun_htmlspecialchars($db_error['error_msg']).($db_error['error_no'] ? ' (Errno: '.$db_error['error_no'].')' : '')."\n";

			if ($db_error['error_sql'] != '')
				echo "\t\t".'<br /><br /><strong>Failed query:</strong> '.pun_htmlspecialchars($db_error['error_sql'])."\n";
		}
	}
	else
		echo "\t\t".'Error: <strong>'.pun_htmlspecialchars($message).'.</strong>'."\n";

?>
	</div>
</div>

</body>
</html>
<?php

	// If a database connection was established (before this error) we close it
	if ($db_error)
		$GLOBALS['db']->close();

	exit;
}


//
// Removes any "bad" characters (characters which mess with the display of a page, are invisible, etc) from user input
//
function forum_remove_bad_characters()
{
	$_GET = remove_bad_characters($_GET);
	$_POST = remove_bad_characters($_POST);
	$_COOKIE = remove_bad_characters($_COOKIE);
	$_REQUEST = remove_bad_characters($_REQUEST);
}

//
// Removes any "bad" characters (characters which mess with the display of a page, are invisible, etc) from the given string
// See: http://kb.mozillazine.org/Network.IDN.blacklist_chars
//
function remove_bad_characters($array)
{
	static $bad_utf8_chars;

	if (!isset($bad_utf8_chars))
	{
		$bad_utf8_chars = array(
			"\xcc\xb7"		=> '',		// COMBINING SHORT SOLIDUS OVERLAY		0337	*
			"\xcc\xb8"		=> '',		// COMBINING LONG SOLIDUS OVERLAY		0338	*
			"\xe1\x85\x9F"	=> '',		// HANGUL CHOSEONG FILLER				115F	*
			"\xe1\x85\xA0"	=> '',		// HANGUL JUNGSEONG FILLER				1160	*
			"\xe2\x80\x8b"	=> '',		// ZERO WIDTH SPACE						200B	*
			"\xe2\x80\x8c"	=> '',		// ZERO WIDTH NON-JOINER				200C
			"\xe2\x80\x8d"	=> '',		// ZERO WIDTH JOINER					200D
			"\xe2\x80\x8e"	=> '',		// LEFT-TO-RIGHT MARK					200E
			"\xe2\x80\x8f"	=> '',		// RIGHT-TO-LEFT MARK					200F
			"\xe2\x80\xaa"	=> '',		// LEFT-TO-RIGHT EMBEDDING				202A
			"\xe2\x80\xab"	=> '',		// RIGHT-TO-LEFT EMBEDDING				202B
			"\xe2\x80\xac"	=> '', 		// POP DIRECTIONAL FORMATTING			202C
			"\xe2\x80\xad"	=> '',		// LEFT-TO-RIGHT OVERRIDE				202D
			"\xe2\x80\xae"	=> '',		// RIGHT-TO-LEFT OVERRIDE				202E
			"\xe2\x80\xaf"	=> '',		// NARROW NO-BREAK SPACE				202F	*
			"\xe2\x81\x9f"	=> '',		// MEDIUM MATHEMATICAL SPACE			205F	*
			"\xe2\x81\xa0"	=> '',		// WORD JOINER							2060
			"\xe3\x85\xa4"	=> '',		// HANGUL FILLER						3164	*
			"\xef\xbb\xbf"	=> '',		// ZERO WIDTH NO-BREAK SPACE			FEFF
			"\xef\xbe\xa0"	=> '',		// HALFWIDTH HANGUL FILLER				FFA0	*
			"\xef\xbf\xb9"	=> '',		// INTERLINEAR ANNOTATION ANCHOR		FFF9	*
			"\xef\xbf\xba"	=> '',		// INTERLINEAR ANNOTATION SEPARATOR		FFFA	*
			"\xef\xbf\xbb"	=> '',		// INTERLINEAR ANNOTATION TERMINATOR	FFFB	*
			"\xef\xbf\xbc"	=> '',		// OBJECT REPLACEMENT CHARACTER			FFFC	*
			"\xef\xbf\xbd"	=> '',		// REPLACEMENT CHARACTER				FFFD	*
			"\xe2\x80\x80"	=> ' ',		// EN QUAD								2000	*
			"\xe2\x80\x81"	=> ' ',		// EM QUAD								2001	*
			"\xe2\x80\x82"	=> ' ',		// EN SPACE								2002	*
			"\xe2\x80\x83"	=> ' ',		// EM SPACE								2003	*
			"\xe2\x80\x84"	=> ' ',		// THREE-PER-EM SPACE					2004	*
			"\xe2\x80\x85"	=> ' ',		// FOUR-PER-EM SPACE					2005	*
			"\xe2\x80\x86"	=> ' ',		// SIX-PER-EM SPACE						2006	*
			"\xe2\x80\x87"	=> ' ',		// FIGURE SPACE							2007	*
			"\xe2\x80\x88"	=> ' ',		// PUNCTUATION SPACE					2008	*
			"\xe2\x80\x89"	=> ' ',		// THIN SPACE							2009	*
			"\xe2\x80\x8a"	=> ' ',		// HAIR SPACE							200A	*
			"\xE3\x80\x80"	=> ' ',		// IDEOGRAPHIC SPACE					3000	*
		);
	}

	if (is_array($array))
		return array_map('remove_bad_characters', $array);

	// Strip out any invalid characters
	$array = htmlspecialchars_decode(htmlspecialchars((string) $array, ENT_SUBSTITUTE, 'UTF-8')); // Visman

	// Remove control characters
	$array = preg_replace('%[\x00-\x08\x0b-\x0c\x0e-\x1f]%', '', $array);

	// Replace some "bad" characters
	$array = str_replace(array_keys($bad_utf8_chars), array_values($bad_utf8_chars), $array);

	return $array;
}


//
// Converts the file size in bytes to a human readable file size
//
function file_size($size)
{
	global $lang_common;

	$units = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB');

	for ($i = 0; $size > 1024; $i++)
		$size /= 1024;

	return sprintf($lang_common['Size unit '.$units[$i]], round($size, 2));
}


//
// Fetch a list of available styles
//
function forum_list_styles()
{
	$styles = array();

	$d = dir(PUN_ROOT.'style');
	while (($entry = $d->read()) !== false)
	{
		if ($entry[0] == '.')
			continue;

		if (substr($entry, -4) == '.css')
			$styles[] = substr($entry, 0, -4);
	}
	$d->close();

	natcasesort($styles);

	return $styles;
}


//
// Fetch a list of available language packs
//
function forum_list_langs()
{
	static $languages;

	if (!isset($languages))
	{
		$languages = array();

		$d = dir(PUN_ROOT.'lang');
		while (($entry = $d->read()) !== false)
		{
			if ($entry[0] == '.')
				continue;

			if (is_dir(PUN_ROOT.'lang/'.$entry) && file_exists(PUN_ROOT.'lang/'.$entry.'/common.php'))
				$languages[] = $entry;
		}
		$d->close();

		natcasesort($languages);
	}

	return $languages;
}


//
// Generate a cache ID based on the last modification time for all stopwords files
//
function generate_stopwords_cache_id()
{
	$files = glob(PUN_ROOT.'lang/*/stopwords.txt');
	if ($files === false)
		return 'cache_id_error';

	$hash = array();

	foreach ($files as $file)
	{
		$hash[] = $file;
		$hash[] = filemtime($file);
	}

	return sha1(implode('|', $hash));
}


//
// Split text into chunks ($inside contains all text inside $start and $end, and $outside contains all text outside)
//
function split_text(string $text, string $start, string $end, bool $retab = true)
{
	global $pun_config;

	$result = array(0 => array(), 1 => array()); // 0 = inside, 1 = outside

	// split the text into parts
	$parts = preg_split('%'.preg_quote($start, '%').'(.*)'.preg_quote($end, '%').'%Us', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	$num_parts = count($parts);

	// preg_split results in outside parts having even indices, inside parts having odd
	for ($i = 0;$i < $num_parts;$i++)
		$result[1 - ($i % 2)][] = $parts[$i];

	if ($pun_config['o_indent_num_spaces'] != 8 && $retab)
	{
		$spaces = str_repeat(' ', $pun_config['o_indent_num_spaces']);
		$result[1] = str_replace("\t", $spaces, $result[1]);
	}

	return $result;
}


//
// Extract blocks from a text with a starting and ending string
// This function always matches the most outer block so nesting is possible
//
function extract_blocks(string $text, string $start, string $end, bool $retab = true)
{
	global $pun_config;

	$code = array();
	$start_len = strlen($start);
	$end_len = strlen($end);
	$regex = '%(?:'.preg_quote($start, '%').'|'.preg_quote($end, '%').')%';
	$matches = array();

	if (preg_match_all($regex, $text, $matches))
	{
		$counter = $offset = 0;
		$start_pos = $end_pos = false;

		foreach ($matches[0] as $match)
		{
			if ($match == $start)
			{
				if ($counter == 0)
					$start_pos = strpos($text, $start);
				$counter++;
			}
			elseif ($match == $end)
			{
				$counter--;
				if ($counter == 0)
					$end_pos = strpos($text, $end, $offset + 1);
				$offset = strpos($text, $end, $offset + 1);
			}

			if ($start_pos !== false && $end_pos !== false)
			{
				$code[] = substr($text, $start_pos + $start_len,
					$end_pos - $start_pos - $start_len);
				$text = substr_replace($text, "\1", $start_pos,
					$end_pos - $start_pos + $end_len);
				$start_pos = $end_pos = false;
				$offset = 0;
			}
		}
	}

	if ($pun_config['o_indent_num_spaces'] != 8 && $retab)
	{
		$spaces = str_repeat(' ', $pun_config['o_indent_num_spaces']);
		$text = str_replace("\t", $spaces, $text);
	}

	return array($code, $text);
}


//
// function url_valid($url) {
//
// Return associative array of valid URI components, or FALSE if $url is not
// RFC-3986 compliant. If the passed URL begins with: "www." or "ftp.", then
// "http://" or "ftp://" is prepended and the corrected full-url is stored in
// the return array with a key name "url". This value should be used by the caller.
//
// Return value: FALSE if $url is not valid, otherwise array of URI components:
// e.g.
// Given: "http://www.jmrware.com:80/articles?height=10&width=75#fragone"
// Array(
//	  [scheme] => http
//	  [authority] => www.jmrware.com:80
//	  [userinfo] =>
//	  [host] => www.jmrware.com
//	  [IP_literal] =>
//	  [IPV6address] =>
//	  [ls32] =>
//	  [IPvFuture] =>
//	  [IPv4address] =>
//	  [regname] => www.jmrware.com
//	  [port] => 80
//	  [path_abempty] => /articles
//	  [query] => height=10&width=75
//	  [fragment] => fragone
//	  [url] => http://www.jmrware.com:80/articles?height=10&width=75#fragone
// )
function url_valid(string $url)
{
	if (strpos($url, 'www.') === 0) $url = 'http://'. $url;
	if (strpos($url, 'ftp.') === 0) $url = 'ftp://'. $url;
	if (!preg_match('/# Valid absolute URI having a non-empty, valid DNS host.
		^
		(?P<scheme>[A-Za-z][A-Za-z0-9+\-.]*):\/\/
		(?P<authority>
		  (?:(?P<userinfo>(?:[A-Za-z0-9\-._~!$&\'()*+,;=:]|%[0-9A-Fa-f]{2})*)@)?
		  (?P<host>
			(?P<IP_literal>
			  \[
			  (?:
				(?P<IPV6address>
				  (?:												 (?:[0-9A-Fa-f]{1,4}:){6}
				  |												   ::(?:[0-9A-Fa-f]{1,4}:){5}
				  | (?:							 [0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){4}
				  | (?:(?:[0-9A-Fa-f]{1,4}:){0,1}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){3}
				  | (?:(?:[0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){2}
				  | (?:(?:[0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})?::	[0-9A-Fa-f]{1,4}:
				  | (?:(?:[0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})?::
				  )
				  (?P<ls32>[0-9A-Fa-f]{1,4}:[0-9A-Fa-f]{1,4}
				  | (?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}
					   (?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)
				  )
				|	(?:(?:[0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})?::	[0-9A-Fa-f]{1,4}
				|	(?:(?:[0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})?::
				)
			  | (?P<IPvFuture>[Vv][0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&\'()*+,;=:]+)
			  )
			  \]
			)
		  | (?P<IPv4address>(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}
							   (?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))
		  | (?P<regname>(?:[A-Za-z0-9\-._~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})+)
		  )
		  (?::(?P<port>[0-9]*))?
		)
		(?P<path_abempty>(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)
		(?:\?(?P<query>		  (?:[A-Za-z0-9\-._~!$&\'()*+,;=:@\\/?]|%[0-9A-Fa-f]{2})*))?
		(?:\#(?P<fragment>	  (?:[A-Za-z0-9\-._~!$&\'()*+,;=:@\\/?]|%[0-9A-Fa-f]{2})*))?
		$
		/mx', $url, $m)) return FALSE;
	switch ($m['scheme'])
	{
	case 'https':
	case 'http':
		if ($m['userinfo']) return FALSE; // HTTP scheme does not allow userinfo.
		break;
	case 'ftps':
	case 'ftp':
		break;
	default:
		return FALSE;	// Unrecognised URI scheme. Default to FALSE.
	}
	// Validate host name conforms to DNS "dot-separated-parts".
	if ($m['regname']) // If host regname specified, check for DNS conformance.
	{
		if (!preg_match('/# HTTP DNS host name.
			^					   # Anchor to beginning of string.
			(?!.{256})			   # Overall host length is less than 256 chars.
			(?:					   # Group dot separated host part alternatives.
			  [0-9A-Za-z]\.		   # Either a single alphanum followed by dot
			|					   # or... part has more than one char (63 chars max).
			  [0-9A-Za-z]		   # Part first char is alphanum (no dash).
			  [\-0-9A-Za-z]{0,61}  # Internal chars are alphanum plus dash.
			  [0-9A-Za-z]		   # Part last char is alphanum (no dash).
			  \.				   # Each part followed by literal dot.
			)*					   # One or more parts before top level domain.
			(?:					   # Top level domains
			  [A-Za-z]{2,63}|	   # Country codes are exactly two alpha chars.
			  xn--[0-9A-Za-z]{4,59})		   # Internationalized Domain Name (IDN)
			$					   # Anchor to end of string.
			/ix', $m['host'])) return FALSE;
	}
	$m['url'] = $url;
	for ($i = 0; isset($m[$i]); ++$i) unset($m[$i]);
	return $m; // return TRUE == array of useful named $matches plus the valid $url.
}


//
// Check whether a file/folder is writable.
//
// This function also works on Windows Server where ACLs seem to be ignored.
//
function forum_is_writable(string $path)
{
	if (is_dir($path))
	{
		$path = rtrim($path, '/').'/';
		return forum_is_writable($path.random_key(8, true).'.tmp');
	}

	// Check temporary file for read/write capabilities
	$rm = file_exists($path);
	$f = @fopen($path, 'a');

	if ($f === false)
		return false;

	fclose($f);

	if (!$rm)
		@unlink($path);

	return true;
}


// DEBUG FUNCTIONS BELOW

//
// Display executed queries (if enabled)
//
function display_saved_queries()
{
	global $db, $lang_common;

	// Get the queries so that we can print them out
	$saved_queries = $db->get_saved_queries();

?>

<div id="debug" class="blocktable">
	<h2><span><?php echo $lang_common['Debug table'] ?></span></h2>
	<div class="box">
		<div class="inbox">
			<table>
			<thead>
				<tr>
					<th class="tcl" scope="col"><?php echo $lang_common['Query times'] ?></th>
					<th class="tcr" scope="col"><?php echo $lang_common['Query'] ?></th>
				</tr>
			</thead>
			<tbody>
<?php

	$query_time_total = 0.0;
	foreach ($saved_queries as $cur_query)
	{
		$query_time_total += $cur_query[1];

?>
				<tr>
					<td class="tcl"><?php echo ($cur_query[1] != 0) ? $cur_query[1] : '&#160;' ?></td>
					<td class="tcr"><?php echo pun_htmlspecialchars($cur_query[0]) ?></td>
				</tr>
<?php

	}

?>
				<tr>
					<td class="tcl" colspan="2"><?php printf($lang_common['Total query time'], $query_time_total.' s') ?></td>
				</tr>
			</tbody>
			</table>
		</div>
	</div>
</div>
<?php

}


//
// Dump contents of variable(s)
//
function dump()
{
	echo '<pre>';

	$num_args = func_num_args();

	for ($i = 0; $i < $num_args; ++$i)
	{
		print_r(func_get_arg($i));
		echo "\n\n";
	}

	echo '</pre>';
	exit;
}


// *** Visman ***
//
// генерация javascript в футере
//
function generation_js(array $arr)
{
	$res = '';
	if (!empty($arr['j']))
		$res.= '<script type="text/javascript" src="'.FORUM_AJAX_JQUERY.'"></script>'."\n";
	if (!empty($arr['f']))
		$res.= '<script type="text/javascript" src="'.implode('"></script>'."\n".'<script type="text/javascript" src="', $arr['f']).'"></script>'."\n";
	if (!empty($arr['c']))
		$res.= '<script type="text/javascript">'."\n".'/* <![CDATA[ */'."\n".implode("\n", $arr['c'])."\n".'/* ]]> */'."\n".'</script>'."\n";

	return $res;
}


//
// MOD Кто в этой теме
// Отложенное выполнение запроса
//
function witt_query($var = null)
{
	global $db, $pun_user, $pun_config;
	static $query;

	$need = !empty($pun_user['new_visit']) || $pun_user['group_id'] != PUN_GUEST || $pun_user['logged'] < time() - $pun_config['o_timeout_online'] / 10;

	if (!defined('WITT_ENABLE'))
	{
		if (is_string($var) && $need)
			$db->query(str_replace(array(':?comma?:', ':?equal?:', ':?column?:', ':?value?:'), '', $var)) or error('Unable to insert/update into online list', __FILE__, __LINE__, $db->error());
	}
	else
	{
		if (is_string($var))
			$query = $var;

		elseif (!empty($query))
		{
			if (is_array($var) && isset($var['column']) && isset($var['value']))
			{
				$need  = true;
				$query = str_replace(array(':?comma?:', ':?equal?:', ':?column?:', ':?value?:'), array(', ', '=', $var['column'], '\''.$db->escape($var['value']).'\''), $query);
			}
			else
				$query = str_replace(array(':?comma?:', ':?equal?:', ':?column?:', ':?value?:'), '', $query);

			if ($need)
				$db->query($query) or error('Unable to insert/update into online list', __FILE__, __LINE__, $db->error());

			$query = null;
		}
	}
}


//
// MOD Subforums
//
function sf_crumbs(int $id)
{
	global $sf_array_desc;

	$str = '';
	while (isset($sf_array_desc[$id]) && isset($sf_array_desc[$id][0]))
	{
		$id = $sf_array_desc[$id][0];
		$str = "\t\t\t".'<li><span>»&#160;</span><strong><a href="viewforum.php?id='.$id.'">'.pun_htmlspecialchars($sf_array_desc[$id]['forum_name']).'</a></strong></li>'."\n".$str;
	}

	return $str;
}


//
// Checks the password on the user's data array
//
function forum_password_verify(string $password, $user)
{
	global $salt1;

	if (empty($user['password']) || ! is_string($user['password']) || pun_strlen($password) > 100000)
	{
		return false;
	}

	// v 1.5.10.79 or later
	if (password_verify($password, $user['password']))
	{
		return 1;
	}
	// If there is a salt in the database we have upgraded from 1.3-legacy though haven't yet logged in
	else if (!empty($user['salt']))
	{
		if (hash_equals(sha1($user['salt'].sha1($password)), $user['password']))
		{
			return 3;
		}
	}
	// If the length isn't 40 then the password isn't using sha1, so it must be md5 from 1.2
	else if (strlen($user['password']) === 32)
	{
		if (hash_equals(md5($password . $salt1), $user['password']))
		{
			return 2;
		}
	}
	// Otherwise we should have a normal sha1 password (v 1.5.10.78 and less)
	else if (strlen($user['password']) === 40)
	{
		if (hash_equals(pun_hash($password), $user['password']))
		{
			return 2;
		}
	}

	return false;
}


//
// Sets common http headers
//
function forum_http_headers(string $type = 'text/html')
{
	$now = gmdate('D, d M Y H:i:s') . ' GMT';

	header('Content-type: ' . $type . '; charset=utf-8');
	header('Cache-Control: private, no-cache');
	header('Date: ' . $now);
	header('Last-Modified: ' . $now);
	header('Expires: ' . $now);
}
