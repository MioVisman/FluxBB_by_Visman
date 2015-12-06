<?php

/**
 * Copyright (C) 2010-2015 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (!defined('PUN'))
	exit;

if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/pms_new.php'))
	require PUN_ROOT.'lang/'.$pun_user['language'].'/pms_new.php';
else
	require PUN_ROOT.'lang/English/pms_new.php';

function generate_pmsn_menu($page = '')
{
	global $pun_user, $lang_pmsn, $pmsn_kol_list, $pmsn_kol_new, $pmsn_kol_save;
	global $sidamp, $sidvop;

?>
<div class="block2col">
	<div class="blockmenu">
<?php
	if ($pun_user['messages_enable'] == 1 && $pun_user['g_pm'] == 1)
	{
?>
		<h2><span><?php echo $lang_pmsn['Boxs'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<ul>
					<li<?php if ($page == 'new')  echo ' class="isactive"'; ?>><a href="pmsnew.php<?php echo $sidvop ?>"><?php echo $lang_pmsn['mNew'].(($pmsn_kol_new==0) ? '' : '&#160;('.$pmsn_kol_new.')') ?></a></li>
					<li<?php if ($page == 'list') echo ' class="isactive"'; ?>><a href="pmsnew.php?mdl=list<?php echo $sidamp ?>"><?php echo $lang_pmsn['mList'].'&#160;('.$pmsn_kol_list.')' ?></a></li>
					<li<?php if ($page == 'save') echo ' class="isactive"'; ?>><a href="pmsnew.php?mdl=save<?php echo $sidamp ?>"><?php echo $lang_pmsn['mSave'].(($pmsn_kol_save==0) ? '' : '&#160;('.$pmsn_kol_save.')') ?></a></li>
				</ul>
			</div>
		</div>
<?php
		if ($pun_user['g_pm_limit'] != 0)
		{
?>
		<h2 class="block2"><span><?php echo $lang_pmsn['Storage'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<ul>
					<li<?php if ($pmsn_kol_list + 1 >= $pun_user['g_pm_limit']) echo ' style="color: red;"'; ?>><?php echo $lang_pmsn['mList'].': '.intval($pmsn_kol_list/$pun_user['g_pm_limit']*100).'%' ?></li>
					<li<?php if ($pmsn_kol_save + 1 >= $pun_user['g_pm_limit']) echo ' style="color: red;"'; ?>><?php echo $lang_pmsn['mSave'].': '.intval($pmsn_kol_save/$pun_user['g_pm_limit']*100).'%' ?></li>
				</ul>
			</div>
		</div>
<?php
		}
?>
		<h2 class="block2"><span><?php echo $lang_pmsn['Options'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<ul>
					<li><a href="pmsnew.php?action=onoff&amp;csrf_token=<?php echo pmsn_csrf_token('onoff') ?>"><?php echo $lang_pmsn['Off'] ?></a></li>
					<li><a href="pmsnew.php?action=email&amp;csrf_token=<?php echo pmsn_csrf_token('email') ?>"><?php echo (($pun_user['messages_email'] == 1) ? $lang_pmsn['Email on'] : $lang_pmsn['Email off']) ?></a></li>
					<li<?php if ($page == 'blocked') echo ' class="isactive"'; ?>><a href="pmsnew.php?mdl=blocked"><?php echo $lang_pmsn['blocked'] ?></a></li>
				</ul>
			</div>
		</div>
	</div>

<?php
	}
	else
	{
?>
		<h2><span><?php echo $lang_pmsn['Options'] ?></span></h2>
		<div class="box">
			<div class="inbox">
				<ul>
					<li><a href="pmsnew.php?action=onoff&amp;csrf_token=<?php echo pmsn_csrf_token('onoff') ?>"><?php echo $lang_pmsn['On'] ?></a></li>
				</ul>
			</div>
		</div>
	</div>

<?php
	}
}

function pmsn_user_update($user, $flag = false)
{
	global $db, $db_type;

	$mkol = $mnew = 0;
	$result = $db->query('SELECT id, starter_id, topic_st, topic_to FROM '.$db->prefix.'pms_new_topics WHERE (starter_id='.$user.' AND topic_st<2) OR (to_id='.$user.' AND topic_to<2)') or error('Unable to fetch pms topics IDs', __FILE__, __LINE__, $db->error());

	while ($ttmp = $db->fetch_assoc($result))
	{
		$ftmp = ($ttmp['starter_id'] == $user) ? $ttmp['topic_st'] : $ftmp = $ttmp['topic_to'];

		$mkol++;
		$mnew += $ftmp;
	}

	$tempf = ($flag && $mnew > 0) ? 'messages_flag=1, ' : '';

	$db->query('UPDATE '.$db->prefix.'users SET '.$tempf.'messages_new='.$mnew.', messages_all='.$mkol.' WHERE id='.$user) or error('Unable to update user', __FILE__, __LINE__, $db->error());
}

function pmsn_user_delete($user, $mflag, $topics = array())
{
	global $db, $db_type;

	$user_up = array($user);
	$tf_st = $tf_to = $tm_st = $tm_to = array();

	if (empty($topics))
		$result = $db->query('SELECT id, starter_id, to_id, see_to, topic_st, topic_to FROM '.$db->prefix.'pms_new_topics WHERE starter_id='.$user.' OR to_id='.$user) or error('Unable to fetch pms topics IDs', __FILE__, __LINE__, $db->error());
	else
		$result = $db->query('SELECT id, starter_id, to_id, see_to, topic_st, topic_to FROM '.$db->prefix.'pms_new_topics WHERE id IN ('.implode(',', $topics).')') or error('Unable to fetch pms topics IDs', __FILE__, __LINE__, $db->error());

	while ($cur_topic = $db->fetch_assoc($result))
	{
		if ($cur_topic['starter_id'] == $user && $cur_topic['see_to'] == 0 && $cur_topic['topic_to'] != 3)
		{
			$tf_st[] = $cur_topic['id'];
			if (!in_array($cur_topic['to_id'], $user_up))
				$user_up[] = $cur_topic['to_id'];
		}
		else if ($cur_topic['starter_id'] == $user)
		{
			if ($mflag == 2 && $cur_topic['topic_to'] == 2)
				$tf_st[] = $cur_topic['id'];
			else
				$tm_st[] = $cur_topic['id'];
		}
		else if ($cur_topic['to_id'] == $user)
		{
			if ($mflag == 2 && $cur_topic['topic_st'] == 2)
				$tf_to[] = $cur_topic['id'];
			else
				$tm_to[] = $cur_topic['id'];
		}
	}

	if (!empty($tm_st))
		$db->query('UPDATE '.$db->prefix.'pms_new_topics SET topic_st='.$mflag.' WHERE id IN ('.implode(',', $tm_st).')') or error('Unable to update pms_new_topics', __FILE__, __LINE__, $db->error());

	if (!empty($tm_to))
		$db->query('UPDATE '.$db->prefix.'pms_new_topics SET topic_to='.$mflag.' WHERE id IN ('.implode(',', $tm_to).')') or error('Unable to update pms_new_topics', __FILE__, __LINE__, $db->error());

	if ($mflag == 2)
	{
		$topic_full = $tf_st + $tf_to;
		if (!empty($topic_full))
		{
			$db->query('DELETE FROM '.$db->prefix.'pms_new_posts WHERE topic_id IN ('.implode(',', $topic_full).')') or error('Unable to remove posts in pms_new_posts', __FILE__, __LINE__, $db->error());;
			$db->query('DELETE FROM '.$db->prefix.'pms_new_topics WHERE id IN ('.implode(',', $topic_full).')') or error('Unable to remove topics in pms_new_topics', __FILE__, __LINE__, $db->error());;
		}
	}
	else
	{
		if (!empty($tf_st))
			$db->query('UPDATE '.$db->prefix.'pms_new_topics SET topic_st='.$mflag.', topic_to=2 WHERE id IN ('.implode(',', $tf_st).')') or error('Unable to update pms_new_topics', __FILE__, __LINE__, $db->error());

		if (!empty($tf_to))
			$db->query('UPDATE '.$db->prefix.'pms_new_topics SET topic_to='.$mflag.', topic_st=2 WHERE id IN ('.implode(',', $tf_to).')') or error('Unable to update pms_new_topics', __FILE__, __LINE__, $db->error());
	}

	// обновляем юзеров
	foreach ($user_up as $i => $s)
		pmsn_user_update($user_up[$i]);
}

function pmsn_get_var($name, $default = NULL)
{
	if (isset($_POST[$name]))
		return $_POST[$name];
	else if (isset($_GET[$name]))
		return $_GET[$name];
	else
		return $default;
}

function pmsn_csrf_token($key)
{
	global $pun_config, $pun_user;
	static $arr = array();

	if (!isset($arr[$key]))
		$arr[$key] = pun_hash(PUN_ROOT.$pun_user['id'].$pun_user['password'].pun_hash($pun_config['o_crypto_pas'].$key.get_remote_address()));

	return $arr[$key];
}
