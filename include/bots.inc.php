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

		return true;
	}

	$ua = getenv('HTTP_USER_AGENT');

	if (!isbot($ua)) return $ra;

	$pat = array(
		'%(https?://|www\.).*%i',
		'%.*compatible[^\s]*%i',
		'%[\w\.-]+@[\w\.-]+.*%',
		'%.*?([^\s]+(bot|spider|crawler)[^\s]*).*%i',
		'%(?<=[\s_-])(bot|spider|crawler).*%i',
		'%(Mozilla|Gecko|Firefox|AppleWebKit)[^\s]*%i',
//		'%(MSIE|Windows|\.NET|Linux)[^;]+%i',
//		'%[^\s]*\.(com|html)[^\s]*%i',
		'%\/[v\d]+.*%',
		'%[^0-9a-z\.]+%i'
	);
	$rep = array(
		' ',
		' ',
		' ',
		'$1',
		' ',
		' ',
//		' ',
//		' ',
		' ',
		' '
	);
	$ua = pun_trim(preg_replace($pat, $rep, $ua));

	if (empty($ua)) return $ra.'[Bot]Unknown';

	$a = explode(' ', $ua);
	$ua = $a[0];
	if (strlen($ua) < 20 && !empty($a[1])) $ua.= ' '.$a[1];
	if (strlen($ua) > 25) $ua = 'Unknown';

	return $ra.'[Bot]'.$ua;
}

$remote_addr = isbotex($remote_addr);
