<?php

/**
 * Copyright (C) 2010-2023 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_QUIET_VISIT', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';

if ($pun_user['g_read_board'] == '0')
	message($lang_common['No view'], false, '403 Forbidden');

if (! is_string($_GET['u'] ?? null) || $pun_user['is_bot'])
	message($lang_common['Bad request'], false, '404 Not Found');

if ($pun_user['is_guest'])
	confirm_referrer('re.php');

if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/re.php'))
	require PUN_ROOT.'lang/'.$pun_user['language'].'/re.php';
else
	require PUN_ROOT.'lang/English/re.php';

$url = str_replace('&amp;', '&', preg_replace(['%(https?|ftp)___%i', '%([\r\n])|(\%0[ad])|(;\s*data\s*:)%i'], ['$1://', ''], $_GET['u']));

$page_js['c']['re'] = 'function fluxrdr() {if(history.length<2){window.close()}else{history.go(-1)}return false}';

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_re['Redirect']);
define('PUN_ACTIVE_PAGE', 'redirect');
require PUN_ROOT.'header.php';

$tpl_main = str_replace('<div id="punre"', '<div id="punmisc"', $tpl_main);
$tpl_main = str_replace('NOINDEX, FOLLOW', 'NOINDEX, NOFOLLOW', $tpl_main);

?>
<div id="rules" class="block">
	<div class="hd"><h2><span><?php echo $lang_re['Redirect'] ?></span></h2></div>
	<div class="box">
		<div id="rules-block" class="inbox">
			<div class="usercontent"><?php echo $lang_re['Text1'].'<strong><a href="'.pun_htmlspecialchars($url).'" rel="nofollow">'.pun_htmlspecialchars($url).'</a></strong><br />'.$lang_re['Text2'] ?></div>
		</div>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
