<?php

/**
 * Copyright (C) 2011-2013 Visman (visman@inbox.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */
 
if (!defined('PUN')) exit;

function isbotex ($ra)
{
	$ua = getenv('HTTP_USER_AGENT');
	$bot_alias  = Array('Rambler','Yandex','Google','Yahoo','MSN',   'Bing',   'Mail.Ru','Alexa',      'Ask Jeeves','Begun Crawler','libwww-perl','Flatland',   'Almaden');
	$bot_string = Array('Rambler','Yandex','Google','Yahoo','msnbot','bingbot','Mail.',  'ia_archiver','Ask Jeeves','Begun Robot',  'libwww-perl','flatlandbot','almaden');

	$k = count($bot_string);
	for ($i=0; $i < $k; $i++)
	{
		if (strpos($ua, $bot_string[$i]) !== false) return '[Bot] '.$bot_alias[$i];
	}

	$ual = strtolower($ua);
	$ua = ' '.$ua.' ';
	if (strpos($ual, 'http://') !== false || strpos($ual, 'bot') !== false || strpos($ual, 'spider') !== false || strpos($ual, 'crawler') !== false || strpos($ual, 'www.') !== false)
	{
		$pat = array(
			'/\[.*\]/',
			'/(mozilla|gecko|firefox|compatible|beta)(\/[\w\.]+)?/i',
			'/\((x86|i386|amd64|x11|macintosh|windows)[^\(\)]+\)/i',
			'/\(x11[^\(\)]+\)/i',
			'/http:\/\/[^\s\);]*/i',
			'/www\.[^\s\);]*/i',
			'/[\w\.-]+@[\w\.-]+/i',
			'/\W(msie|windows|linux|unix|java|sv|\.net)[ \w\.-]*/i',
			'/r?v?[\.:]?\d\.\d[\w\.-]*/i',
			'/[\s\)\(\+;:,_-]{2,}/',
		);
		$rep = array('', '', '', '', '', '', '', '', '', ' ',);
		$ua = pun_trim(preg_replace($pat, $rep, $ua));

		if (($pos = strpos($ua, '/')) !== false)
		{
			$ua = substr($ua, 0, $pos);
			$sp = substr_count($ua, ' ');
			if ($sp < 3) return '[Bot] '.$ua;
			else return '[Bot] Unknown';
		}
		else if (preg_match('#^[ \w\.\^\!-]+$#', $ua))
		{
			$sp = substr_count($ua, ' ');
			if ($sp < 3) return '[Bot] '.$ua;
			else return '[Bot] Unknown';
		}
		return '[Bot] Unknown';
	}
	return $ra;
}

$remote_addr = isbotex($remote_addr);
