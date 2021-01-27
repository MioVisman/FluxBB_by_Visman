<?php

/**
 * Copyright (C) 2010-2021 Visman (mio.visman@yandex.ru)
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (!defined('PUN') || !defined('PUN_PMS_NEW'))
	exit;

define('PUN_PMS_LOADED', 1);

define('PUN_ACTIVE_PAGE', 'pms_new');
require PUN_ROOT.'header.php';
?>
<div class="linkst">
	<div class="inbox crumbsplus">
		<ul class="crumbs">
			<li><a href="index.php"><?php echo $lang_common['Index'] ?></a></li>
			<li><span>»&#160;</span><a href="pmsnew.php"><?php echo $lang_pmsn['PM'] ?></a></li>
			<li><span>»&#160;</span><strong><?php echo $lang_pmsn[$pmsn_modul] ?></strong></li>
		</ul>
		<div class="pagepost"></div>
		<div class="clearer"></div>
	</div>
</div>
<?php

generate_pmsn_menu($pmsn_modul);

// Determine the topic offset (based on $_GET['p'])
$result = $db->query('SELECT COUNT(bl_user_id) FROM '.$db->prefix.'pms_new_block WHERE bl_id='.$pun_user['id']) or error('Unable to fetch pms_new_block', __FILE__, __LINE__, $db->error());
$num_pages = ceil($db->result($result) / $pun_user['disp_topics']);

$p = (!isset($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $num_pages) ? 1 : intval($_GET['p']);
$start_from = $pun_user['disp_topics'] * ($p - 1);

// Generate paging links
$paging_links = '<span class="pages-label">'.$lang_common['Pages'].' </span>'.paginate($num_pages, $p, 'pmsnew.php?mdl=blocked');

$pmsn_f_savedel = '<input type="submit" name="delete" value="'.$lang_pmsn['Delete'].'" />';

?>
<script type="text/javascript">
/* <![CDATA[ */
function ChekUncheck(el)
{
	var i, form = el.form;
	for (i = 0; i < form.elements.length; i++)
	{
		if (form.elements[i].type && form.elements[i].type === "checkbox") {
			form.elements[i].checked = el.checked;
		}
	}
}
/* ]]> */
</script>

	<div class="block">
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
		</div>
		<form method="post" action="pmsnew.php?mdl=blockedq">
		<div id="users1" class="blocktable">
			<input type="hidden" name="csrf_hash" value="<?php echo $pmsn_csrf_hash; ?>" />
			<input type="hidden" name="p" value="<?php echo $p; ?>" />
			<div class="box">
				<div class="inbox">
					<table>
					<thead>
						<tr>
							<th class="tcl" scope="col"><?php echo $lang_common['Username'] ?></th>
							<th class="tc2" scope="col"><?php echo $lang_common['Title'] ?></th>
							<th class="tcr" scope="col"><?php echo $lang_common['Registered'] ?></th>
							<th class="tce" scope="col"><input name="chek" type="checkbox" value="" onclick="ChekUncheck(this)" /></th>
						</tr>
					</thead>
					<tbody>
<?php

$result = $db->query('SELECT b.bl_user_id, u.username, u.id, u.title, u.registered, u.num_posts, g.g_id, g.g_user_title FROM '.$db->prefix.'pms_new_block AS b LEFT JOIN '.$db->prefix.'users AS u ON b.bl_user_id=u.id LEFT JOIN '.$db->prefix.'groups AS g ON g.g_id=u.group_id WHERE b.bl_id='.$pun_user['id'].' ORDER BY u.username LIMIT '.$start_from.','.$pun_user['disp_topics']) or error('Unable to fetch pms_new_block and users', __FILE__, __LINE__, $db->error());
$user_data = $db->fetch_assoc($result);

if (is_array($user_data))
{
	do
	{
		if (!$user_data['id'])
		{
			$user_name_field = pun_htmlspecialchars($user_data['username']);
			$user_title_field = '&#160;';
			$user_data_field = '&#160;';
		}
		else
		{
			if ($pun_user['g_view_users'] == '1')
				$user_name_field = '<a href="profile.php?id='.$user_data['id'].'">'.pun_htmlspecialchars($user_data['username']).'</a>';
			else
				$user_name_field = pun_htmlspecialchars($user_data['username']);
			$user_title_field = get_title($user_data);
			$user_data_field = format_time($user_data['registered'], true);
		}

?>
						<tr>
							<td class="tcl"><?php echo $user_name_field ?></td>
							<td class="tc2"><?php echo $user_title_field ?></td>
							<td class="tcr"><?php echo $user_data_field ?></td>
							<td class="tce"><input type="checkbox" name="user_numb[<?php echo $user_data['bl_user_id']?>]" value="1" /></td>
						</tr>
<?php

	}
	while ($user_data = $db->fetch_assoc($result));
}
else
{
	echo "\t\t\t\t\t\t".'<tr><td class="tcl" colspan="4">'.$lang_pmsn['Empty'].'</td></tr>'."\n";
	$pmsn_f_savedel = '';
}

?>
					</tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="pagepost">
			<p class="pagelink conl"><?php echo $paging_links ?></p>
			<p class="postlink conr"><?php echo $pmsn_f_savedel ?></p>
		</div>
		</form>
	</div>
<?php
