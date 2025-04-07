<?php

/**
 * Copyright (C) 2013-2022 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;


function security_lang(string $val, bool $isset = false)
{
	static $lang_sec;

	if (!isset($lang_sec))
	{
		global $pun_user;

		if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/security.php'))
			require PUN_ROOT.'lang/'.$pun_user['language'].'/security.php';
		else
			require PUN_ROOT.'lang/English/security.php';
	}

	if ($isset)
		return isset($lang_sec[$val]);
	else
		return isset($lang_sec[$val]) ? $lang_sec[$val] : $val;
}


function security_encode_for_js(string $s)
{
	global $pun_config, $page_js;

	if (isset($pun_config['o_coding_forms']) && $pun_config['o_coding_forms'] == '1')
	{
		$page_js['f']['decode64'] = 'js/b64.js';
		return base64_encode($s);
	}
	return $s;
}


function security_show_random_value($val)
{
	static $random;

	if ($val === false)
	{
		$random = 0;
		return;
	}

	if (security_lang('Idx'.$val, true) && is_array(security_lang('Idx'.$val)) && $random < 2)
	{
		$arr = security_lang('Idx'.$val);
		$new = $arr[array_rand($arr)];

		if (pun_strlen($new) > pun_strlen($val))
			$random++;

		return $new;
	}

	return $val;
}


function security_random_name(string $s)
{
	global $pun_config;
	static $s1_ar, $sar;

	if (!isset($pun_config['o_crypto_enable']) || $pun_config['o_crypto_enable'] != '1')
		return $s;

	if (!isset($sar))
		$sar = array();

	if (!empty($sar[$s]))
		return $sar[$s];

	if (!isset($s1_ar))
		$s1_ar = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

	$key = $s1_ar[random_int(0, strlen($s1_ar) - 1)];
	$s1_ar = str_replace($key, '', $s1_ar);

	$key .= random_int(1, 9);

	$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

	$len = random_int(1, 5);

	for ($i = 0; $i < $len; ++$i)
		$key .= $chars[random_int(0, strlen($chars) - 1)];

	$sar[$s] = $key;
	return $key;
}


function security_show_captcha(int $tabindex, bool $acaptcha = true, bool $qcaptcha = false)
{
	global $lang_common, $cur_index;

	$result = array();

	if ($acaptcha || $qcaptcha)
	{

?>
			<div class="inform">
				<fieldset>
					<legend><?php echo security_lang('Captcha legend') ?></legend>
					<div class="infldset">
<?php

		if ($qcaptcha)
		{
			global $pun_config, $page_js;

			if (isset($pun_config['o_coding_forms']) && $pun_config['o_coding_forms'] == '1')
			{
				$inp_name = 'jst_'.random_int(1, 1000);
				$inp_code = random_int(2, 99);
				$result[$inp_name] = $inp_code;

				$page_js['c'][] = 'document.getElementById("id'.$inp_name.'").value="'.$inp_code.'";';

?>
						<noscript><p style="color: red; font-weight: bold"><?php echo security_lang('Enable JS') ?></p></noscript>
						<input type="hidden" id="id<?php echo $inp_name ?>" name="<?php echo $inp_name ?>" value="1" />
<?php

			}
			$inp_name = security_random_name('form_qcaptha'.random_int(1, 1000));
			$inp_code = random_int(2, 99);
			$result[$inp_name] = $inp_code;

?>
						<label class="required"><span class="b64"><?php echo security_encode_for_js('<input type="checkbox" name="'.$inp_name.'" value="'.$inp_code.'"'.($tabindex > 0 ? ' tabindex="'.($tabindex++).'"' : (empty($cur_index) ? '' : ' tabindex="'.($cur_index++).'"')).' />') ?></span><strong> <?php echo security_lang('Not robot') ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /></label>
<?php

		} // $qcaptcha

		if ($acaptcha)
		{
			$len = random_int(2, 3);
			$c = array('+', '-', '*', '/');
			$a = $d = array();

			for ($i = 1; $i < $len; $i++)
			{
				$y = array_rand($c);
				$d[$i] = $c[$y];
				array_splice($c, $y, 1);
			}

			$pred = $prea = 0;
			for ($i = $len; $i > 0; $i--)
			{
				$a[$i] = random_int(1, 9);

				if ($i < $len && $d[$i] == '/')
				{
					if ($pred == '/')
					{
						$f = $a[$i] % $prea;

						if ($f != 0)
							$a[$i]+= $prea - $f;

						$prea = $a[$i] * $prea;
					}
					else
					{
						$f = $a[$i] % $a[$i + 1];

						if ($f != 0)
							$a[$i]+= $a[$i + 1] - $f;

						$prea = $a[$i] * $a[$i + 1];
					}
				}
				else
					$prea = 0;

				$pred = $i < $len ? $d[$i] : '';
			}

			$str = '';
			for ($i = 1; $i <= $len; $i++)
				$str.= $a[$i].($i < $len ? $d[$i] : '');

			eval('$sum = '.$str.';');

			$inp_idx = random_int(1, $len + 1);
			$type = random_int(0, 1);
			$inp_name = security_random_name('form_captha'.random_int(1, 1000));
			$inp_code = '<input type="text" name="'.$inp_name.'" size="4" maxlength="4"'.($tabindex > 0 ? ' tabindex="'.($tabindex++).'"' : (empty($cur_index) ? '' : ' tabindex="'.($cur_index++).'"')).' />';
			$result[$inp_name] = $inp_idx > $len ? $sum : $a[$inp_idx];
			security_show_random_value(false);

?>
						<label class="required"><strong><?php echo security_lang('Captcha text') ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><?php

			if (!$type)
				echo ($inp_idx > $len ? $inp_code : security_show_random_value($sum)).' '.security_show_random_value('=').' ';

			for ($i = 1; $i <= $len; $i++)
				echo ($i == $inp_idx ? $inp_code : security_show_random_value($a[$i])).($i < $len ? ' '.security_show_random_value($d[$i]).' ' : '');

			if ($type)
				echo ' '.security_show_random_value('=').' '.($inp_idx > $len ? $inp_code : security_show_random_value($sum));

?></label>
<?php

		} // $acaptcha

?>
					</div>
				</fieldset>
			</div>
<?php

	}

	return (count($result) ? serialize($result) : ''); // to $form_captcha
}


function security_test_browser()
{
	return empty($_SERVER['HTTP_ACCEPT']) || '*/*' == $_SERVER['HTTP_ACCEPT'] || empty($_SERVER['HTTP_ACCEPT_ENCODING']) || empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) || empty($_SERVER['HTTP_ORIGIN']);
}


function security_verify_captcha(string $form_captcha)
{
	$form_captcha = unserialize($form_captcha);

	foreach ($form_captcha as $key => $val)
	{
		if (!isset($_POST[$key]) || pun_trim($_POST[$key]) != $val)
		{
			if (substr($key, 0, 4) == 'jst_')
				return 8; // js выключен

			return 7; // ошибка captcha
		}
	}

	return true;
}


function security_msg(string $error)
{
	return security_lang('Error '.$error, true) ? security_lang('Error '.$error) : 'Error '.$error;
}


define('FORUM_SEC_FUNCTIONS_LOADED', true);
