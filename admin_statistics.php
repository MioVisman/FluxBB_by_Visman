<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if (!$pun_user['is_admmod'])
	message($lang_common['No permission'], false, '403 Forbidden');

// Load the admin_index.php language file
require PUN_ROOT.'lang/'.$admin_language.'/admin_index.php';

$action = $_GET['action'] ?? null;


// Show phpinfo() output
if ($action === 'phpinfo' && $pun_user['g_id'] == PUN_ADMIN)
{
	// Is phpinfo() a disabled function?
	if (strpos(strtolower((string) ini_get('disable_functions')), 'phpinfo') !== false)
		message($lang_admin_index['PHPinfo disabled message']);

	phpinfo();
	exit;
}


// Get the server load averages (if possible)
$server_load = $lang_admin_index['Not available'];
switch (strtoupper(substr(PHP_OS, 0, 3)))
{
	case 'WIN':
		@exec('wmic cpu get loadpercentage /all', $output_load);
		if (!empty($output_load) && preg_match('%(?:^|==)(\d+)(?:$|==)%', implode('==', $output_load) , $load_percentage))
		{
			$server_load = $load_percentage[1].' %';
		}
		break;
	default:
		if (function_exists('sys_getloadavg'))
		{
			$load_averages = sys_getloadavg();
			$server_load = forum_number_format($load_averages[0], 2).' '.forum_number_format($load_averages[1], 2).' '.forum_number_format($load_averages[2], 2);
			break;
		}

		@exec('uptime', $output_load);
		if (!empty($output_load) && preg_match('%averages?: ([0-9\.]+),?\s+([0-9\.]+),?\s+([0-9\.]+)%i', implode(' ', $output_load) , $load_averages))
		{
			$server_load = forum_number_format($load_averages[1], 2).' '.forum_number_format($load_averages[2], 2).' '.forum_number_format($load_averages[3], 2);
			break;
		}
}

// Get number of current visitors
$result = $db->query('SELECT COUNT(user_id) FROM '.$db->prefix.'online WHERE idle=0') or error('Unable to fetch online count', __FILE__, __LINE__, $db->error());
$num_online = $db->result($result);


// Collect some additional info about MySQL
if ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb')
{
	// Calculate total db size/row count
	$result = $db->query('SHOW TABLE STATUS LIKE \''.$db->prefix.'%\'') or error('Unable to fetch table status', __FILE__, __LINE__, $db->error());

	$total_records = $total_size = 0;
	while ($status = $db->fetch_assoc($result))
	{
		$total_records += $status['Rows'];
		$total_size += $status['Data_length'] + $status['Index_length'];
	}

	$total_size = file_size($total_size);
}


// Check for the existence of various PHP opcode caches/optimizers
if (ini_get('opcache.enable') && function_exists('opcache_invalidate'))
	$php_accelerator = '<a href="https://www.php.net/opcache/">Zend OPcache</a>';
elseif (ini_get('wincache.fcenabled'))
	$php_accelerator = '<a href="https://www.php.net/wincache/">Windows Cache for PHP</a>';
elseif (ini_get('apc.enabled') && function_exists('apc_delete_file'))
	$php_accelerator = '<a href="https://web.archive.org/web/20160324235630/http://www.php.net/apc/">Alternative PHP Cache (APC)</a>';
elseif (isset($_PHPA))
	$php_accelerator = '<a href="https://www.ioncube.com/">ionCube PHP Accelerator</a>';
else if (ini_get('eaccelerator.enable'))
	$php_accelerator = '<a href="http://eaccelerator.net/">eAccelerator</a>';
elseif (ini_get('xcache.cacher'))
	$php_accelerator = '<a href="https://web.archive.org/web/20120224193029/http://xcache.lighttpd.net/">XCache</a>';
else
	$php_accelerator = $lang_admin_index['NA'];


$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Server statistics']);
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('index');

?>
	<div class="block">
		<h2><span><?php echo $lang_admin_index['Server statistics head'] ?></span></h2>
		<div id="adstats" class="box">
			<div class="inbox">
				<dl>
					<dt><?php echo $lang_admin_index['Server load label'] ?></dt>
					<dd>
						<?php printf($lang_admin_index['Server load data']."\n", $server_load, $num_online) ?>
					</dd>
<?php if ($pun_user['g_id'] == PUN_ADMIN): ?>					<dt><?php echo $lang_admin_index['Environment label'] ?></dt>
					<dd>
						<?php printf($lang_admin_index['Environment data OS'], PHP_OS) ?><br />
						<?php printf($lang_admin_index['Environment data version'], phpversion(), '<a href="admin_statistics.php?action=phpinfo">'.$lang_admin_index['Show info'].'</a>') ?><br />
						<?php printf($lang_admin_index['Environment data acc']."\n", $php_accelerator) ?>
					</dd>
					<dt><?php echo $lang_admin_index['Database label'] ?></dt>
					<dd>
						<?php echo implode(' ', $db->get_version())."\n" ?>
<?php if (isset($total_records) && isset($total_size)): ?>						<br /><?php printf($lang_admin_index['Database data rows']."\n", forum_number_format($total_records)) ?>
						<br /><?php printf($lang_admin_index['Database data size']."\n", $total_size) ?>
<?php endif; ?>					</dd>
<?php endif; ?>
				</dl>
			</div>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

require PUN_ROOT.'footer.php';
