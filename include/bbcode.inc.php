<?php

/**
 * Copyright (C) 2011-2015 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

if (file_exists(PUN_ROOT.'style/'.$pun_user['style'].'/img/bbcode/b.png'))
	$btndir = 'style/'.$pun_user['style'].'/img/bbcode/';
else
	$btndir = 'style/Air/img/bbcode/';

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

$page_js['j'] = 1; // for resize textarea :(
$page_js['f']['bbcode'] = 'js/post.js';
$page_js['c'][] = 'if (typeof FluxBB === \'undefined\' || !FluxBB) {var FluxBB = {};}
FluxBB.vars = {
	bbDir: "'.$btndir.'",
	bbGuest: '.($pun_user['is_guest'] ? 1 : 0).',
	bbCIndex: '.$cur_index.',
	bbSmImg: ['.implode(',', $smil_i).'],
	bbSmTxt: ['.implode(',', $smil_t).']
};
FluxBB.post.init();';

$cur_index += 19;

unset($smil_g, $smil_i, $smil_t);

$page_js['f']['textarea-caret-position'] = 'js/textarea-caret-position/index.js';
$page_js['f']['emoji-autocomplete'] = 'js/emoji-autocomplete.js';
