<?php

/**
 * Copyright (C) 2015-2018 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

class addon_security_for_register extends flux_addon
{
	var $version;
	var $att_period;
	var $att_max;
	var $time_min;
	var $time_max;
	var $form_key;


	function register($manager)
	{
		global $pun_user;

		if (!$pun_user['is_guest']) return;

		$this->version = '1.0.0';
		$this->att_period = 20;
		$this->att_max = 3;
		$this->time_min = 3;
		$this->time_max = 3600;
		$this->form_key = 'form_key';

		$manager->bind('register_before_header', array($this, 'hook_register_before_header'));
		$manager->bind('register_before_submit', array($this, 'hook_register_before_submit'));
		$manager->bind('register_after_validation', array($this, 'hook_register_after_validation'));
	}


	function hook_register_before_header()
	{
		global $db, $pun_config;

		if (empty($pun_config['o_sec_of_register']) || $pun_config['o_sec_of_register'] != $this->version)
		{
			$db->drop_table('sec_of_register') or error('Unable to drop sec_of_register table', __FILE__, __LINE__, $db->error());
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name LIKE \'o\_sec\_of\_register%\'') or error('Unable to remove config entries', __FILE__, __LINE__, $db->error());;

			$schema = array
			(
				'FIELDS'		=> array(
					'form_key'			=> array(
						'datatype'		=> 'varchar(40)',
						'allow_null'	=> false
					),
					'form_time'			=> array(
						'datatype'		=> 'INT(10) UNSIGNED',
						'allow_null'	=> false,
						'default'			=> '0'
					),
					'form_ip'				=> array(
						'datatype'		=> 'varchar(39)',
						'allow_null'	=> false
					),
					'form_captcha'	=> array(
						'datatype'		=> 'varchar(100)',
						'allow_null'	=> false
					)
				),
				'INDEXES'		=> array(
					'form_key_idx'	=> array('form_key'),
					'form_time_idx'	=> array('form_time')
				)
			);

			$db->create_table('sec_of_register', $schema) or error('Unable to create sec_of_register table', __FILE__, __LINE__, $db->error());

			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_sec_of_register\', \''.$db->escape($this->version).'\')') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_sec_of_register_time\', \''.$db->escape(time()).'\')') or error('Unable to update board config', __FILE__, __LINE__, $db->error());

			$this->gen_cache();
		}
		else if (time() - $this->time_max > $pun_config['o_sec_of_register_time'])
		{
			$db->query('DELETE FROM '.$db->prefix.'sec_of_register WHERE form_time<'.(time() - $this->time_max)) or error('Unable to delete sec_of_register data', __FILE__, __LINE__, $db->error());
			$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$db->escape(time()).'\' WHERE conf_name=\'o_sec_of_register_time\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());

			$this->gen_cache();
		}
	}


	function hook_register_before_submit()
	{
		global $db, $pun_config;

		if (!defined('FORUM_SEC_FUNCTIONS_LOADED'))
			include PUN_ROOT.'include/security.php';

		$now = time();
		$ip = get_remote_address();
		$key = pun_hash(random_key(9).$now.$ip);

		$enable_acaptcha = isset($pun_config['o_enable_acaptcha']) && $pun_config['o_enable_acaptcha'] == '1';

		$form_captcha = security_show_captcha(0, $enable_acaptcha, true);

		$db->query('INSERT INTO '.$db->prefix.'sec_of_register (form_key, form_time, form_ip, form_captcha) VALUES (\''.$db->escape($key).'\', '.$now.', \''.$db->escape($ip).'\', \''.$db->escape($form_captcha).'\')') or error('Unable to insert data in sec_of_register', __FILE__, __LINE__, $db->error());

		echo "\t\t\t".'<div><input type="hidden" name="'.pun_htmlspecialchars($this->form_key).'" value="'.pun_htmlspecialchars($key).'" /></div>'."\n";
	}


	function hook_register_after_validation()
	{
		global $db, $errors;

		if (!defined('FORUM_SEC_FUNCTIONS_LOADED'))
			include PUN_ROOT.'include/security.php';

		$now = time();
		$form_key = $_POST[$this->form_key] ?? null;

		if (! is_string($form_key))
		{
			$errors[] = security_msg('1');
			return;
		}

		if (!isset($_POST['req_user'], $_POST['req_email1'], $_POST['timezone'], $_POST['email_setting']) || (empty($_POST['language']) && count(forum_list_langs()) > 1) || empty($_POST['register2']))
			$errors[] = security_msg('1');

		if (security_test_browser())
			$errors[] = security_msg('2');

		$result = $db->query('SELECT * FROM '.$db->prefix.'sec_of_register WHERE form_key=\''.$db->escape($form_key).'\' LIMIT 1') or error('Unable to get sec_of_register data', __FILE__, __LINE__, $db->error());
		$cur_form = $db->fetch_assoc($result);

		if (empty($cur_form['form_time']) || $cur_form['form_captcha'] == 'error')
		{
			$errors[] = security_msg('3');
			return;
		}

		if ($cur_form['form_ip'] !== get_remote_address())
			$errors[] = security_msg('4');

		if ($now - $this->time_min < $cur_form['form_time'])
			$errors[] = security_msg('5');

		if ($now - $this->time_max > $cur_form['form_time'])
			$errors[] = security_msg('6');

		if (!empty($cur_form['form_captcha']))
		{
			$verify_captcha = security_verify_captcha($cur_form['form_captcha']);

			if ($verify_captcha !== true)
				$errors[] = security_msg($verify_captcha);
		}

		if (empty($errors))
			$db->query('DELETE FROM '.$db->prefix.'sec_of_register WHERE form_key=\''.$db->escape($form_key).'\'') or error('Unable to delete sec_of_register data', __FILE__, __LINE__, $db->error());
		else
			$db->query('UPDATE '.$db->prefix.'sec_of_register SET form_captcha=\'error\' WHERE form_key=\''.$db->escape($form_key).'\'') or error('Unable to update sec_of_register data', __FILE__, __LINE__, $db->error());
	}


	function gen_cache()
	{
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require PUN_ROOT.'include/cache.php';

		generate_config_cache();
	}
}
