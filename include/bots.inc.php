<?php

/**
 * Copyright (C) 2011-2023 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (!defined('PUN')) exit;


function ua_isbot(string $ua, string $ual)
{
	if (!trim($ua))
		return false;

	if (strpos($ual, 'bot') !== false || strpos($ual, 'spider') !== false ||
			strpos($ual, 'crawler') !== false || strpos($ual, 'http') !== false)
		return true;

	if (strpos($ua, 'Mozilla/') !== false)
	{
		if (strpos($ua, 'Gecko') !== false)
			return false;

		if (strpos($ua, '(compatible; MSIE ') !== false && strpos($ua, 'Windows') !== false)
			return false;
	}
	else if (strpos($ua, 'Opera/') !== false)
	{
		if (strpos($ua, 'Presto/') !== false)
			return false;
	}

	return true;
}


function ua_isbotex(string $ra)
{
	$ua = getenv('HTTP_USER_AGENT');
	$ual = strtolower($ua);

	if (!ua_isbot($ua, $ual))
		return $ra;

	if (strpos($ual, 'mozilla') !== false)
		$ua = preg_replace('%Mozilla.*?compatible%i', ' ', $ua);

	if(strpos($ual, 'http') !== false || strpos($ual, 'www.') !== false)
		$ua = preg_replace('%(?:https?://|www\.)[^\)]*(\)[^/]+$)?%i', ' ', $ua);

	if (strpos($ua, '@') !== false)
		$ua = preg_replace('%\b[\w\.-]+@[^\)]+%i', ' ', $ua);

	if (strpos($ual, 'bot') !== false || strpos($ual, 'spider') !== false ||
			strpos($ual, 'crawler') !== false || strpos($ual, 'engine') !== false)
	{
		$f = true;
		$p = '%(?<=[^a-z\d\.-])(?:robot|bot|spider|crawler)\b.*%i';
	}
	else
	{
		$f = false;
		$p = '%^$%';
	}

//	if ($f && preg_match('%\b([a-z\d\.! _-]+(?:robot|(?<!ro)bot|spider|crawler|engine)[a-z\d\.! _-]*)%i', $ua, $matches))
	if ($f && preg_match('%\b(([a-z\d\.! _-]+)?(?:robot|(?<!ro)bot|spider|crawler|engine)(?(2)[a-z\d\.! _-]*|[a-z\d\.! _-]+))%i', $ua, $matches))
	{
		$ua = $matches[1];

		$pat = array(
			$p,
			'%[^a-z\d\.!-]+%i',
			'%(?<=^|\s|-)v?\d+\.\d[^\s]*\s*%i',
			'%(?<=^|\s)\S{1,2}(?:\s|$)%',
		);
		$rep = array(
			'',
			' ',
			'',
			'',
		);
	}
	else
	{
		$pat = array(
			'%\((?:KHTML|Linux|Mac|Windows|X11)[^\)]*\)?%i',
			$p,
			'%\b(?:AppleWebKit|Chrom|compatible|Firefox|Gecko|Mobile(?=[/ ])|Moz|Opera|OPR|Presto|Safari|Version)[^\s]*%i',
			'%\b(?:InfoP|Intel|Linux|Mac|MRA|MRS|MSIE|SV|Trident|Win|WOW|X11)[^;\)]*%i',
			'%\.NET[^;\)]*%i',
			'%/.*%',
			'%[^a-z\d\.!-]+%i',
			'%(?<=^|\s|-)v?\d+\.\d[^\s]*\s*%i',
			'%(?<=^|\s)\S{1,2}(?:\s|$)%',
		);
		$rep = array(
			' ',
			'',
			'',
			'',
			'',
			'',
			' ',
			'',
			'',
		);
	}
	$ua = trim(preg_replace($pat, $rep, $ua), ' -');

	if (empty($ua))
		return $ra.'[Bot]Unknown';

	$a = explode(' ', $ua);

	$ua = $a[0];
	if (strlen($ua) < 20 && !empty($a[1]) && strlen($ua.' '.$a[1]) < 26)
		$ua.= ' '.$a[1];
	else if (strlen($ua) > 25)
		$ua = 'Unknown';

	return $ra.'[Bot]'.$ua;
}


define('FORUM_BOT_FUNCTIONS_LOADED', true);
