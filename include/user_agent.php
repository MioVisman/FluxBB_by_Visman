<?php

function ua_get_filename($name, $folder)
{
	$name = preg_replace('%[^\w]%', '', strtolower($name));
	return get_base_url(true).'/img/user_agent/'.$folder.'/'.$name.'.png';
}

function ua_search_for_item($items, $usrag)
{
	foreach ($items as $item)
	{
		if (strpos($usrag, strtolower($item)) !== false)
			return $item;
	}
}

function get_useragent_names($usrag)
{
	$browser_img = $browser_version = '';
	
	$usrag = strtolower($usrag);
	
	// Browser detection
	$browsers = array('Arora', 'AWeb', 'Camino', 'Epiphany', 'Galeon', 'HotJava', 'iCab', 'MSIE', 'Maxthon', 'OPR', 'YaBrowser', 'Chrome', 'Safari', 'Konqueror', 'Flock', 'Iceweasel', 'SeaMonkey', 'Firebird', 'Netscape', 'Firefox', 'K-Meleon', 'Mozilla', 'Opera', 'PhaseOut', 'SlimBrowser');

	$browser = ua_search_for_item($browsers, $usrag);

	preg_match('#'.preg_quote(strtolower(($browser == 'Opera' ? 'Version' : $browser))).'[\s/]*([\.0-9]*)#', $usrag, $matches);
	$browser_version = isset($matches[1]) ? $matches[1] : '';

	if ($browser == 'MSIE')
	{
		if (intval($browser_version) >= 9)
			$browser_img = 'Internet Explorer 9';
		else if (intval($browser_version) >= 7)
			$browser_img = 'Internet Explorer 7';

		$browser = 'Internet Explorer';
	}
	elseif ($browser == 'OPR')
		$browser = 'Opera';
	elseif (!$browser)
		$browser = 'Unknown';

	// System detection
	$systems = array('Amiga', 'BeOS', 'FreeBSD', 'HP-UX', 'Linux', 'NetBSD', 'OS/2', 'SunOS', 'Symbian', 'Unix', 'Windows', 'Samsung', 'Sun', 'Macintosh', 'Mac', 'J2ME/MIDP');
	
	$system = ua_search_for_item($systems, $usrag);
	
	if ($system == 'Linux')
	{
		$systems = array('CentOS', 'Debian', 'Fedora', 'Freespire', 'Gentoo', 'Katonix', 'KateOS', 'Knoppix', 'Kubuntu', 'Linspire', 'Mandriva', 'Mandrake', 'RedHat', 'Slackware', 'Slax', 'Suse', 'Xubuntu', 'Ubuntu', 'Xandros', 'Arch', 'Ark', 'Android');

		$system = ua_search_for_item($systems, $usrag);
		if ($system == '')
			$system = 'Linux';
		
		if ($system == 'Mandrake')
			$system = 'Mandriva';
	}
	elseif ($system == 'Windows')
	{
		$version = substr($usrag, strpos($usrag, 'windows nt ') + 11);
		if (substr($version, 0, 3) == '5.1')
			$system = 'Windows XP';
		elseif (substr($version, 0, 1) == '6')
		{
			if (substr($version, 0, 3) == '6.0')
				$system = 'Windows Vista';
			else if (substr($version, 0, 3) == '6.1')
				$system = 'Windows 7';
			else
				$system = 'Windows 8';
		}
	}
	elseif ($system == 'Mac')
		$system = 'Macintosh';
	elseif (!$system)
		$system = 'Unknown';

	if (!$browser_img)
		$browser_img = $browser;

	$result = array(
		'system'			=> $system,
		'browser_img'		=> $browser_img,
		'browser_version'	=> $browser_version,
		'browser_name'		=> ($browser != 'Unknown') ? $browser.' '.$browser_version : $browser
	);

	return $result;
}

function get_useragent_icons($usrag)
{
	global $pun_user;
	static $uac = array();

	if ($usrag == '') return '';
		
	if (isset($uac[$usrag])) return $uac[$usrag];
		
	$agent = get_useragent_names($usrag);

	$result = '<img src="'.ua_get_filename($agent['system'], 'system').'" title="'.pun_htmlspecialchars($agent['system']).'" alt="'.pun_htmlspecialchars($agent['system']).'" style="margin-right: 1px"/>';
	$result .= '<img src="'.ua_get_filename($agent['browser_img'], 'browser').'" title="'.pun_htmlspecialchars($agent['browser_name']).'" alt="'.pun_htmlspecialchars($agent['browser_name']).'" style="margin-left: 1px"/>';

	$desc = ($pun_user['is_admmod']) ? ' style="cursor: pointer" onclick="alert(\''.pun_htmlspecialchars(addslashes($usrag).'\n\nSystem:\t'.addslashes($agent['system']).'\nBrowser:\t'.addslashes($agent['browser_name'])).'\')"' : '';

	$result = "\t\t\t\t\t\t".'<dd class="usercontacts"><span class="user-agent"'.$desc.'>'.$result.'</span></dd>'."\n";

	$uac[$usrag] = $result;
	return $result;
}
