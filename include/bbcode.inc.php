<?php

/**
 * Copyright (C) 2011-2013 Visman (visman@inbox.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

if (file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/img/bbcode/b.png'))
	$btndir = 'style/'.$pun_user['style'].'/img/bbcode/';
else
	$btndir = 'style/Air/img/bbcode/';
$smldir = 'img/smilies/';

require PUN_ROOT.'lang/'.$pun_user['language'].'/bbcode.php';

if (!isset($smilies))
{
	if (file_exists(FORUM_CACHE_DIR.'cache_smilies.php'))
		include FORUM_CACHE_DIR.'cache_smilies.php';
	else
	{
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_smiley_cache();
		require FORUM_CACHE_DIR.'cache_smilies.php';
	}
}

$smil_g = $smil_i = $smil_t = array();
foreach ($smilies as $smileyt => $smileyi)
{
	if (isset($smil_g[$smileyi])) continue;
	$smil_g[$smileyi] = true;
	$smil_i[] = "'".$smileyi."'";
	$smil_t[] = "'".addslashes($smileyt)."'";
}

$bbres = '<style type="text/css">div.grippie {background:#EEEEEE url(img/grippie.png) no-repeat scroll center 2px;border-color:#DDDDDD;border-style:solid;border-width:0pt 1px 1px;cursor:s-resize;height:9px;overflow:hidden;} .resizable-textarea textarea {display:block;margin-bottom:0pt;width:95%;height: 20%;}</style>';
$tpl_main = str_replace('</head>', $bbres."\n".'</head>', $tpl_main);

$page_js['j'] = 1;
$page_js['c']['arq'] = 'var apq = {\'Must\':\''.$lang_common['Must'].'\',\'Loading\':\''.$lang_common['Loading'].'\',\'Flag\':\'Topic\',\'Guest\':\''.$pun_user['is_guest'].'\'};';
$page_js['f']['bbcode'] = 'js/post.js';

$page_js['c']['a'] = 'var bbcode_l = {\'btndir\':\''.$btndir.'\'';
foreach ($lang_bbcode as $i => $k)
	$page_js['c']['a'].= ',\''.$i.'\':\''.$k.'\'';
$page_js['c']['a'].= '};'."\n";
$page_js['c']['a'].= 'var bbcode_sm_img = new Array('.implode(',',$smil_i).');'."\n";
$page_js['c']['a'].= 'var bbcode_sm_txt = new Array('.implode(',',$smil_t).');'."\n";
$page_js['c']['a'].= 'var cur_index = '.$cur_index.';'."\n";
$page_js['c']['a'].= 'ForumBBSet();';
$cur_index += 21;

unset($smil_g, $smil_i, $smil_t);

if (!$pun_user['is_guest'] && isset($pun_user['g_up_ext']))
{
	if ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_up_limit'] > 0 && $pun_user['g_up_max'] > 0))
		$page_js['c']['up'] = 'ForumUpSet();';
}
