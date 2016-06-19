<?php

/**
 * Copyright (C) 2011-2013 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Load language file
if (file_exists(PUN_ROOT.'lang/'.$pun_user['language'].'/upload.php'))
	require PUN_ROOT.'lang/'.$pun_user['language'].'/upload.php';
else
	require PUN_ROOT.'lang/English/upload.php';

$gd  = extension_loaded('gd');
$gd2 = ($gd && function_exists('imagecreatetruecolor'));

$extimage = array('gif', 'jpeg', 'jpg', 'jpe', 'png', 'bmp', 'tiff', 'tif', 'swf', 'psd', 'iff', 'wbmp', 'wbm', 'xbm');
$extforno = array('phtml','php','php3','php4','php5','php6','php7','phps','cgi','exe','pl','asp','aspx','shtml','shtm','fcgi','fpl','jsp','htm','html','wml','htaccess');

$extimage2 = array(
	1 => array('gif'),
	2 => array('jpg', 'jpeg', 'jpe'),
	3 => array('png'),
	4 => array('swf'),
	5 => array('psd'),
	6 => array('bmp'),
	7 => array('tif', 'tiff'),
	8 => array('tif', 'tiff'),
	9 => array('jpg', 'jpeg', 'jpe'),
	10 => array('jpg', 'jpeg', 'jpe'),
	11 => array('jpg', 'jpeg', 'jpe'),
	12 => array('jpg', 'jpeg', 'jpe'),
	13 => array('swf'),
	14 => array('iff'),
	15 => array('wbmp', 'wbm'),
	16 => array('xbm'),
);

$extimageGD = array(
	'gif' => 'gif',
	'jpeg' => 'jpeg',
	'jpg' => 'jpeg',
	'jpe' => 'jpeg',
	'png' => 'png',
	'bmp' => 'bmp',
	'wbmp' => 'wbmp',
	'wbm' => 'wbmp',
	'xbm' => 'xbm',
);

function parse_file($f)
{
	static $UTF8AR = null;

	if (is_null($UTF8AR))
	{
		$UTF8AR = array(
			'à' => 'a', 'ô' => 'o', 'ď' => 'd', 'ḟ' => 'f', 'ë' => 'e', 'š' => 's', 'ơ' => 'o',
			'ß' => 'ss', 'ă' => 'a', 'ř' => 'r', 'ț' => 't', 'ň' => 'n', 'ā' => 'a', 'ķ' => 'k',
			'ŝ' => 's', 'ỳ' => 'y', 'ņ' => 'n', 'ĺ' => 'l', 'ħ' => 'h', 'ṗ' => 'p', 'ó' => 'o',
			'ú' => 'u', 'ě' => 'e', 'é' => 'e', 'ç' => 'c', 'ẁ' => 'w', 'ċ' => 'c', 'õ' => 'o',
			'ṡ' => 's', 'ø' => 'o', 'ģ' => 'g', 'ŧ' => 't', 'ș' => 's', 'ė' => 'e', 'ĉ' => 'c',
			'ś' => 's', 'î' => 'i', 'ű' => 'u', 'ć' => 'c', 'ę' => 'e', 'ŵ' => 'w', 'ṫ' => 't',
			'ū' => 'u', 'č' => 'c', 'ö' => 'oe', 'è' => 'e', 'ŷ' => 'y', 'ą' => 'a', 'ł' => 'l',
			'ų' => 'u', 'ů' => 'u', 'ş' => 's', 'ğ' => 'g', 'ļ' => 'l', 'ƒ' => 'f', 'ž' => 'z',
			'ẃ' => 'w', 'ḃ' => 'b', 'å' => 'a', 'ì' => 'i', 'ï' => 'i', 'ḋ' => 'd', 'ť' => 't',
			'ŗ' => 'r', 'ä' => 'ae', 'í' => 'i', 'ŕ' => 'r', 'ê' => 'e', 'ü' => 'ue', 'ò' => 'o',
			'ē' => 'e', 'ñ' => 'n', 'ń' => 'n', 'ĥ' => 'h', 'ĝ' => 'g', 'đ' => 'd', 'ĵ' => 'j',
			'ÿ' => 'y', 'ũ' => 'u', 'ŭ' => 'u', 'ư' => 'u', 'ţ' => 't', 'ý' => 'y', 'ő' => 'o',
			'â' => 'a', 'ľ' => 'l', 'ẅ' => 'w', 'ż' => 'z', 'ī' => 'i', 'ã' => 'a', 'ġ' => 'g',
			'ṁ' => 'm', 'ō' => 'o', 'ĩ' => 'i', 'ù' => 'u', 'į' => 'i', 'ź' => 'z', 'á' => 'a',
			'û' => 'u', 'þ' => 'th', 'ð' => 'dh', 'æ' => 'ae', 'µ' => 'u', 'ĕ' => 'e',
			'À' => 'A', 'Ô' => 'O', 'Ď' => 'D', 'Ḟ' => 'F', 'Ë' => 'E', 'Š' => 'S', 'Ơ' => 'O',
			'Ă' => 'A', 'Ř' => 'R', 'Ț' => 'T', 'Ň' => 'N', 'Ā' => 'A', 'Ķ' => 'K',
			'Ŝ' => 'S', 'Ỳ' => 'Y', 'Ņ' => 'N', 'Ĺ' => 'L', 'Ħ' => 'H', 'Ṗ' => 'P', 'Ó' => 'O',
			'Ú' => 'U', 'Ě' => 'E', 'É' => 'E', 'Ç' => 'C', 'Ẁ' => 'W', 'Ċ' => 'C', 'Õ' => 'O',
			'Ṡ' => 'S', 'Ø' => 'O', 'Ģ' => 'G', 'Ŧ' => 'T', 'Ș' => 'S', 'Ė' => 'E', 'Ĉ' => 'C',
			'Ś' => 'S', 'Î' => 'I', 'Ű' => 'U', 'Ć' => 'C', 'Ę' => 'E', 'Ŵ' => 'W', 'Ṫ' => 'T',
			'Ū' => 'U', 'Č' => 'C', 'Ö' => 'Oe', 'È' => 'E', 'Ŷ' => 'Y', 'Ą' => 'A', 'Ł' => 'L',
			'Ų' => 'U', 'Ů' => 'U', 'Ş' => 'S', 'Ğ' => 'G', 'Ļ' => 'L', 'Ƒ' => 'F', 'Ž' => 'Z',
			'Ẃ' => 'W', 'Ḃ' => 'B', 'Å' => 'A', 'Ì' => 'I', 'Ï' => 'I', 'Ḋ' => 'D', 'Ť' => 'T',
			'Ŗ' => 'R', 'Ä' => 'Ae', 'Í' => 'I', 'Ŕ' => 'R', 'Ê' => 'E', 'Ü' => 'Ue', 'Ò' => 'O',
			'Ē' => 'E', 'Ñ' => 'N', 'Ń' => 'N', 'Ĥ' => 'H', 'Ĝ' => 'G', 'Đ' => 'D', 'Ĵ' => 'J',
			'Ÿ' => 'Y', 'Ũ' => 'U', 'Ŭ' => 'U', 'Ư' => 'U', 'Ţ' => 'T', 'Ý' => 'Y', 'Ő' => 'O',
			'Â' => 'A', 'Ľ' => 'L', 'Ẅ' => 'W', 'Ż' => 'Z', 'Ī' => 'I', 'Ã' => 'A', 'Ġ' => 'G',
			'Ṁ' => 'M', 'Ō' => 'O', 'Ĩ' => 'I', 'Ù' => 'U', 'Į' => 'I', 'Ź' => 'Z', 'Á' => 'A',
			'Û' => 'U', 'Þ' => 'Th', 'Ð' => 'Dh', 'Æ' => 'Ae', 'Ĕ' => 'E',
			'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'jo',
			'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'jj', 'к' => 'k', 'л' => 'l', 'м' => 'm',
			'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
			'ф' => 'f', 'х' => 'kh', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => '',
			'ы' => 'y', 'ь' => '', 'э' => 'eh', 'ю' => 'ju', 'я' => 'ja',
			'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Jo',
			'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'Jj', 'К' => 'K', 'Л' => 'L', 'М' => 'M',
			'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U',
			'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'C', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shh', 'Ъ' => '',
			'Ы' => 'Y', 'Ь' => '', 'Э' => 'Eh', 'Ю' => 'Ju', 'Я' => 'Ja',
			);
	}

	$f = preg_replace('%[\x00-\x1f]%', '', $f);
	$f = preg_replace('%[@=\' ]+%', '-', $f);
	$f = str_replace(array_keys($UTF8AR), array_values($UTF8AR), $f);
	$f = preg_replace('%[^\w\.-]+%', '', $f);

	return $f;
}

function dir_size($dir)
{
	global $extforno;

	$upload = 0;
	$open = opendir(PUN_ROOT.$dir);
	while(($file = readdir($open)) !== false)
	{
		if (is_file(PUN_ROOT.$dir.$file))
		{
			$ext = strtolower(substr(strrchr($file, '.'), 1)); // берем расширение файла
			if ($ext != '' && $file[0] != '#' && !in_array($ext, $extforno))
				$upload += filesize(PUN_ROOT.$dir.$file);
		}
	}
	closedir($open);
	return $upload;
}

if ($gd && !function_exists('ImageCreateFromBMP'))
{
	/*********************************************/
	/* Fonction: ImageCreateFromBMP              */
	/* Author:   DHKold                          */
	/* Contact:  admin@dhkold.com                */
	/* Date:     The 15th of June 2005           */
	/* Version:  2.0B                            */
	/*********************************************/

	function ImageCreateFromBMP($filename)
	{
		global $gd2;
		//Ouverture du fichier en mode binaire
		if (! $f1 = fopen($filename,"rb")) return FALSE;

		//1 : Chargement des ent�tes FICHIER
		$FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1,14));
		if ($FILE['file_type'] != 19778) return FALSE;

		//2 : Chargement des ent�tes BMP
		$BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel'.
									'/Vcompression/Vsize_bitmap/Vhoriz_resolution'.
									'/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1,40));
		$BMP['colors'] = pow(2,$BMP['bits_per_pixel']);
		if ($BMP['size_bitmap'] == 0) $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
		$BMP['bytes_per_pixel'] = $BMP['bits_per_pixel']/8;
		$BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
		$BMP['decal'] = ($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] -= floor($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] = 4-(4*$BMP['decal']);
		if ($BMP['decal'] == 4) $BMP['decal'] = 0;

		//3 : Chargement des couleurs de la palette
		$PALETTE = array();
		if ($BMP['colors'] < 16777216)
		{
			$PALETTE = unpack('V'.$BMP['colors'], fread($f1,$BMP['colors']*4));
		}

		//4 : Cr�ation de l'image
		$IMG = fread($f1,$BMP['size_bitmap']);
		$VIDE = chr(0);

		if ($gd2)
			$res = imagecreatetruecolor($BMP['width'],$BMP['height']);
		else
			$res = imagecreate($BMP['width'],$BMP['height']);

		$P = 0;
		$Y = $BMP['height']-1;
		while ($Y >= 0)
		{
			$X=0;
			while ($X < $BMP['width'])
			{
				if ($BMP['bits_per_pixel'] == 24)
					$COLOR = unpack("V",substr($IMG,$P,3).$VIDE);
				elseif ($BMP['bits_per_pixel'] == 16)
				{
					$COLOR = unpack("n",substr($IMG,$P,2));
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				elseif ($BMP['bits_per_pixel'] == 8)
				{
					$COLOR = unpack("n",$VIDE.substr($IMG,$P,1));
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				elseif ($BMP['bits_per_pixel'] == 4)
				{
					$COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
					if (($P*2)%2 == 0) $COLOR[1] = ($COLOR[1] >> 4) ; else $COLOR[1] = ($COLOR[1] & 0x0F);
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				elseif ($BMP['bits_per_pixel'] == 1)
				{
					$COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
					if     (($P*8)%8 == 0) $COLOR[1] =  $COLOR[1]        >>7;
					elseif (($P*8)%8 == 1) $COLOR[1] = ($COLOR[1] & 0x40)>>6;
					elseif (($P*8)%8 == 2) $COLOR[1] = ($COLOR[1] & 0x20)>>5;
					elseif (($P*8)%8 == 3) $COLOR[1] = ($COLOR[1] & 0x10)>>4;
					elseif (($P*8)%8 == 4) $COLOR[1] = ($COLOR[1] & 0x8)>>3;
					elseif (($P*8)%8 == 5) $COLOR[1] = ($COLOR[1] & 0x4)>>2;
					elseif (($P*8)%8 == 6) $COLOR[1] = ($COLOR[1] & 0x2)>>1;
					elseif (($P*8)%8 == 7) $COLOR[1] = ($COLOR[1] & 0x1);
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				}
				else
					return FALSE;
				imagesetpixel($res,$X,$Y,$COLOR[1]);
				$X++;
				$P += $BMP['bytes_per_pixel'];
			}
			$Y--;
			$P+=$BMP['decal'];
		}

		//Fermeture du fichier
		fclose($f1);

		return $res;
	}
}

function img_resize ($file, $dir, $name, $type, $width = 0, $height = 0, $quality = 75, $flag = false)
{
	global $gd, $gd2, $extimage2, $extimageGD;
	
	if (!$gd) return false;
	if (!file_exists($file)) return false;

	$size = getimagesize($file);
	if ($size === false) return false;

	$type2 = strtolower($type);
	$type1 = (($flag && in_array($type2, array('jpeg','jpg','jpe','gif','png','bmp'))) || ($type2 == 'bmp')) ? 'jpeg' : $extimageGD[$type2];
	
	$icfunc = 'imagecreatefrom'.$extimageGD[$extimage2[$size[2]][0]]; //  $type;
	if (!function_exists($icfunc)) return false;

	$xr = ($width == 0) ? 1 : $width / $size[0];
	$yr = ($height == 0) ? 1 : $height / $size[1];
	$r = min($xr, $yr, 1);
	$width = round($size[0] * $r);
	$height = round($size[1] * $r);

	$image = @$icfunc($file);
	if (!isset($image) || empty($image)) return false;

	if ($gd2)
	{
		$idest = imagecreatetruecolor($width, $height);
		imagefill($idest, 0, 0, 0x7FFFFFFF);
		imagecolortransparent($idest, 0x7FFFFFFF);
		if ($type1 == 'gif')
		{
			$palette = imagecolorstotal($image);
			imagetruecolortopalette($idest, true, $palette);
		}
		imagecopyresampled($idest, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
		imagesavealpha($idest, true);
	}
	else
	{
		$idest = imagecreate($width, $height);
		imagecopyresized($idest, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
	}

	$icfunc = 'image'.$type1;
	if (!function_exists($icfunc))
	{
		$type1 = 'jpeg';
		$icfunc = 'image'.$type1;
		if (!function_exists($icfunc)) return false;
	}

	if ($flag) $type = $type1;

	if ($type1 == 'png' && version_compare(PHP_VERSION, '5.1.2', '>='))
	{
		$quality = floor((100 - $quality) / 11);
		@imagepng($idest, PUN_ROOT.$dir.$name.'.'.$type, $quality);
	}
	else if ($type1 == 'jpeg')
		@imagejpeg($idest, PUN_ROOT.$dir.$name.'.'.$type, $quality);
	else
		@$icfunc($idest, PUN_ROOT.$dir.$name.'.'.$type);

	imagedestroy($image);
	imagedestroy($idest);

	if (!file_exists(PUN_ROOT.$dir.$name.'.'.$type)) return false;
	@chmod(PUN_ROOT.$dir.$name.'.'.$type, 0644);

	return array($name, $type);
}

function isXSSattack ($file)
{
	global $lang_up;
	// сканируем содержание загруженного файла
	$fin = fopen($file, "rb");
	if (!$fin)
		return $lang_up['Error open'];

	$buf1 = '';
	while ($buf2 = fread($fin, 4096))
	{
		if (preg_match( "%<(script|html|head|title|body|table|a\s+href|img\s|plaintext|cross\-domain\-policy|embed|applet|\?php)%si", $buf1.$buf2 ))
		{
			fclose($fin);
			return $lang_up['Error inject'];
		}
		$buf1 = substr($buf2,-30);
	}
	fclose($fin);
	return false;
}

function return_bytes ($val)
{
// Author: Ivo Mandalski
	if(empty($val))return 0;

	$val = trim($val);

	preg_match('#([0-9]+)[\s]*([a-z]+)#i', $val, $matches);

	$last = '';
	if(isset($matches[2])){
		$last = $matches[2];
	}

	if(isset($matches[1])){
		$val = (int)$matches[1];
	}

	switch (strtolower($last))
	{
		case 'g':
		case 'gb':
			$val *= 1024;
		case 'm':
		case 'mb':
			$val *= 1024;
		case 'k':
		case 'kb':
			$val *= 1024;
	}

	return (int)$val;
}
