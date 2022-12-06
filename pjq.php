<?php

/**
 * Copyright (C) 2010-2022 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

define('PUN_QUIET_VISIT', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';

forum_http_headers();

if ($pun_user['g_read_board'] == '0')
	exit($lang_common['No view']);

if ($pun_user['is_guest'])
	exit($lang_common['No permission']);

$action = $_POST['action'] ?? null;
$id = intval($_POST['id'] ?? 0);
if ($id < 1)
	exit($lang_common['Bad request']);

if ($action === "quote")
{
	// Fetch some info about the post, the topic and the forum
	$result = $db->query('SELECT p.message FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND p.id='.$id) or exit('Unable to fetch post info '.$db->error());
	$cur_post = $db->fetch_assoc($result);

	if (!$cur_post)
		exit($lang_common['Bad request']);

	if ($pun_config['o_censoring'] == '1')
		$cur_post['message'] = censor_words($cur_post['message']);

?>
<quote_post><?php echo $cur_post['message'] ?></quote_post>
<?php

}
else if ($action === "pmquote")
{
	if ($pun_config['o_pms_enabled'] != '1' || $pun_user['g_pm'] == 0 || $pun_user['messages_enable'] == 0)
		exit($lang_common['No permission']);

	// Fetch some info about the post, the topic and the forum
	$result = $db->query('SELECT p.message FROM '.$db->prefix.'pms_new_posts AS p INNER JOIN '.$db->prefix.'pms_new_topics AS t ON t.id=p.topic_id WHERE p.id='.$id.' AND (t.starter_id='.$pun_user['id'].' OR t.to_id='.$pun_user['id'].')') or exit('Unable to fetch pms_new_posts info '.$db->error());
	$cur_post = $db->fetch_assoc($result);

	if (!$cur_post)
		exit($lang_common['Bad request']);

	if ($pun_config['o_censoring'] == '1')
		$cur_post['message'] = censor_words($cur_post['message']);

?>
<quote_post><?php echo $cur_post['message'] ?></quote_post>
<?php

}
else
	exit($lang_common['Bad request']);

$db->end_transaction();
$db->close();
