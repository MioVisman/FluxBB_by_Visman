<?php

/**
 * Copyright (C) 2011-2013 Visman (visman@inbox.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */
 
if (!defined('PUN')) exit;

function isbotex($ra)
{
	function isbot($ua)
	{
		if ('' == pun_trim($ua)) return false;

		$ual = strtolower($ua);
		if (strstr($ual, 'bot') || strstr($ual, 'spider') || strstr($ual, 'crawler')) return true;
		
		if (strstr($ua, 'Mozilla/'))
		{
			if (strstr($ua, 'Gecko')) return false;
			if (strstr($ua, '(compatible; MSIE ') && strstr($ua, 'Windows')) return false;
		}
		else if (strstr($ua, 'Opera/'))
		{
			if (strstr($ua, 'Presto/')) return false;
		}
//		else
//		{
//			return false;
//		}
    
		return true;
	}

	$ua = $_SERVER['HTTP_USER_AGENT'];

	if (isbot($ua))
	{
		$bots = array(
		  'Googlebot-Mobile' => 'Googlebot-Mobile',
		  'Google' => 'Google',
		  'Yandex' => 'Yandex',
		  'Yahoo' => 'Yahoo',
		  'msnbot' => 'MSN',
		  'bingbot' => 'Bing',
		  'Ezooms' => 'Ezooms',
		  'Mail.' => 'Mail.Ru',
		  'MJ12bot' => 'Majestic-12',
		  'magpie-crawler' => 'Magpie',
		  '360Spider' => '360Spider',
		  'proximic' => 'Proximic',
		  'Baiduspider' => 'Baidu',
//		  '' => '',
//		  '' => '',
//		  '' => '',
//		  '' => '',
		);
	
		foreach ($bots as $str => $bot)
		{
			if (strstr($ua, $str)) return '[Bot] '.$bot;
		}

		return '[Bot] Unknown#-#'.$ra;
	}
	return $ra;
}

$remote_addr = isbotex($remote_addr);