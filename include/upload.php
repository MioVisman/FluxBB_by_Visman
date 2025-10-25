<?php

/**
 * Copyright (C) 2011-2023 Visman (mio.visman@yandex.ru)
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (! defined('PUN')) {
    exit;
}

// Load language file
if (file_exists(PUN_ROOT . 'lang/' . $pun_user['language'] . '/upload.php')) {
    require PUN_ROOT . 'lang/' . $pun_user['language'] . '/upload.php';
} else {
    require PUN_ROOT . 'lang/English/upload.php';
}

class upfClass
{
    protected $blackList = [
        '' => true,
        'asmx' => true,
        'asp' => true,
        'aspx' => true,
        'cgi' => true,
        'dll' => true,
        'exe' => true,
        'fcgi' => true,
        'fpl' => true,
        'htaccess' => true,
        'htm' => true,
        'html' => true,
        'js' => true,
        'jsp' => true,
        'php' => true,
        'php3' => true,
        'php4' => true,
        'php5' => true,
        'php6' => true,
        'php7' => true,
        'phar' => true,
        'phps' => true,
        'phtm' => true,
        'phtml' => true,
        'pl' => true,
        'py' => true,
        'rb' => true,
        'shtm' => true,
        'shtml' => true,
        'wml' => true,
        'xml' => true,
    ];

    /**
     * Список кодов типов картинок и расширений для них????
     * @var array
     */
    protected $imageType = [
        1  => ['gif', true],
        2  => ['jpg', true],
        3  => ['png', true],
        4  => ['swf', false],
        5  => ['psd', false],
        6  => ['bmp', true],
        7  => ['tiff', false],
        8  => ['tiff', false],
        9  => ['jpc', false],
        10 => ['jp2', false],
        11 => ['jpx', false],
        12 => ['jb2', false],
        13 => ['swc', false],
        14 => ['iff', false],
        15 => ['wbmp', false],
        16 => ['xbm', false],
        17 => ['ico', false],
        18 => ['webp', true],
    ];

    /**
     * Список единиц измерения
     * @var string
     */
    protected $units = 'BKMGTPEZY';

    protected $UTF8AR = [
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
    ];

    const GD = 1;
    const IMAGICK = 2;

    protected $resizeFlag = false;
    protected $libType;
    protected $libName = '-';
    protected $libVersion = '-';
    protected $error;
    protected $quality = 75;

    public function __construct()
    {
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $this->resizeFlag = true;
            $this->libType = self::IMAGICK;
            $this->libName = 'ImageMagick';
            $imagick = Imagick::getVersion();
            $this->libVersion = trim(preg_replace(['%ImageMagick%i', '%http[^\s]+%i'], '', $imagick['versionString']));
        } elseif (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            $this->resizeFlag = true;
            $this->libType = self::GD;
            $this->libName = 'GD';
            $gd = gd_info();
            $this->libVersion = $gd['GD Version'];
        }
    }

    public function isResize()
    {
        return $this->resizeFlag;
    }

    public function getLibName()
    {
        return $this->libName;
    }

    public function getLibVersion()
    {
        return $this->libVersion;
    }

    public function getError()
    {
        $error = $this->error;
        $this->error = null;
        return $error;
    }

    protected function isBadLink($link)
    {
        if (
            ! is_string($link)
            || false !== strpos($link, ':', 2)
            || false !== strpos($link, '//')
            || preg_match('%\bphar\b%i', $link)
        ) {
            $this->error = 'Bad link';
            return true;
        } else {
            return false;
        }
    }

    public function inBlackList(string $ext)
    {
        return isset($this->blackList[strtolower($ext)]);
    }

    public function dirSize(string $dir)
    {
        if ($this->isBadLink($dir)) {
            return false;
        } elseif (! is_dir($dir)) {
            $this->error = 'Directory expected';
            return false;
        } elseif (false === ($dh = opendir($dir))) {
            $this->error = 'Could not open directory';
            return false;
        }

        $size = 0;
        while (false !== ($file = readdir($dh))) {
            if ('' == trim($file) || '.' === $file[0] || '#' === $file[0] || ! is_file($dir . $file)) {
                continue;
            }
            $ext = strtolower(substr(strrchr($file, '.'), 1)); // расширение файла
            if (isset($this->blackList[$ext])) {
                continue;
            }
            $size += filesize($dir . $file);
        }

        closedir($dh);
        return $size;
    }

    /**
     * Переводит объем информации из одних единиц в другие
     * кило = 1024, а не 1000
     *
     * @param int|float|string $value
     * @param string $to
     *
     * @return int|float|false
     */
    public function size($value, $to = null)
    {
        if (is_string($value)) {
            if (! preg_match('%^([^a-z]+)([a-z]+)?$%i', trim($value), $matches)) {
                $this->error = 'Expected string indicating the amount of information';
                return false;
            }
            if (! is_numeric($matches[1])) {
                $this->error = 'String does not contain number';
                return false;
            }

            $value = 0 + $matches[1];

            if (! empty($matches[2])) {
                $unit = strtoupper($matches[2][0]);
                $expo = strpos($this->units, $unit);

                if (false === $expo) {
                    $this->error = 'Unknown unit';
                    return false;
                }

                $value *= 1024 ** $expo;
            }
        }

        if (is_string($to)) {
            $to = trim($to);
            $unit = strtoupper($to[0]);
            $expo = strpos($this->units, $unit);

            if (false === $expo) {
                $this->error = 'Unknown unit';
                return false;
            }

            $value /= 1024 ** $expo;
        }

        return 0 + $value;
    }

    /**
     * Определяет по содержимому файла расширение картинки????
     *
     * @param string $path
     *
     * @return false|array
     */
    public function imageExt(string $path)
    {
        if ($this->isBadLink($path)) {
            return false;
        }

        if (function_exists('exif_imagetype')) {
            $type = exif_imagetype($path);
        } elseif (
            function_exists('getimagesize')
            && false !== ($type = @getimagesize($path))
            && $type[0] > 0
            && $type[1] > 0
        ) {
            $type = $type[2];
        } else {
            $type = 0;
        }
        if (13 === $type)
        {
            $code = file_get_contents($path, false, null, 0, 3);
            if ('FWS' === $code || 'CWS' === $code)
                $type = 4;
        }
        return isset($this->imageType[$type]) ? $this->imageType[$type] : false;
    }

    /**
     * Фильрует и переводит в латиницу(?) имя файла
     *
     * @param string $name
     *
     * @return string
     */
    protected function filterName(string $name)
    {
        $new = false;
        if (function_exists('transliterator_transliterate')) {
            $new = transliterator_transliterate("Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC;", $name);
        }
        if (! is_string($new)) {
            $new = str_replace(array_keys($this->UTF8AR), array_values($this->UTF8AR), $name);
        }

        $name = trim(preg_replace('%[^\w-]+%', '-', $new), '-_');

        if (! isset($name[0])) {
            $name = $this->filterName(date('Ymd\-His'));
        }

        return $name;
    }

    public function getFileExt()
    {
        return $this->fileExt;
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    public function prepFileName()
    {
        if ('mini_' === substr($this->fileName, 0, 5)) {
            $this->fileName = substr($this->fileName, 5);
        }
        if (strlen($this->fileName) > 100) {
            $this->fileName = substr($this->fileName, 0, 100);
        }
        if ('' == $this->fileName) {
            $this->fileName = 'none';
        }
    }

    public function isImage()
    {
        return $this->fileAsImage;
    }

    public function setImageQuality($quality)
    {
        $this->quality = min(max(intval($quality), 1), 100);
    }

    protected $filePath;
    protected $fileName;
    protected $fileExt;
    protected $fileCalcExt;
    protected $fileAsImage;
    protected $fileIsUp;
    protected $image;

    public function loadFile(string $path, ?string $basename = null)
    {
        $this->filePath = null;
        $this->fileName = null;
        $this->fileExt = null;
        $this->fileCalcExt = null;
        $this->fileAsImage = false;
        $this->fileIsUp = null !== $basename;

        $this->destroyImage();
        $this->image = null;

        if ($this->isBadLink($path)) {
            return false;
        }

        if (null !== $basename) {
            $pattern = '%^(.+)\.(\w+)$%';
            $subject = $basename;
        } else {
            $pattern = '%[\\/]([\w-]+)\.(\w+)$%';
            $subject = $path;
        }
        if (! preg_match($pattern, $subject, $matches)) {
            $this->error = 'Bad file name or extension';
            return false;
        }

        $this->fileExt = $this->fileCalcExt = strtolower($matches[2]);
        if (isset($this->blackList[$this->fileExt])) {
            $this->error = 'Bad file extension';
            return false;
        }

        if (null !== $basename) {
            if (! is_uploaded_file($path)) {
                $this->error = 'File was not uploaded';
                return false;
            }
        } else {
            if (! is_file($path)) {
                $this->error = 'No file';
                return false;
            }
        }
        if (! is_readable($path)) {
            $this->error = 'File unreadable';
            return false;
        }

        $imageInfo = $this->imageExt($path);
        if (is_array($imageInfo)) {
            if (null !== $basename) {
                $this->fileExt = $imageInfo[0];
            }
            $this->fileCalcExt = $imageInfo[0];
            $this->fileAsImage = $imageInfo[1];
        }

        $this->fileName = null !== $basename ? $this->filterName($matches[1]) : $matches[1];
        $this->filePath = $path;

        return true;
    }

    public function isUnsafeContent()
    {
        if (null === $this->filePath) {
            return true;
        }

        $f = fopen($this->filePath, "rb");
        if (false === $f) {
            return true;
        }

        $buf1 = '';
        while ($buf2 = fread($f, 4096)) {
            if (
                preg_match( "%<(?:script|html|head|title|body|table|a\s+href|img\s|plaintext|cross\-domain\-policy|embed|applet|i?frame|\?php)%msi", $buf1 . $buf2)
                || false !== strpos($buf1 . $buf2, "tEXtprofile\0")
            ) {
                fclose($f);
                return true;
            }
            $buf1 = substr($buf2, -30);
        }
        fclose($f);
        return false;
    }

    public function loadImage()
    {
        if (null === $this->filePath || true !== $this->fileAsImage) {
            $this->error = 'No image';
            return false;
        }
        switch ($this->libType) {
            case self::IMAGICK:
                try {
                    $image = new Imagick(realpath($this->filePath));
                    $width = $image->getImageWidth();
                    $height = $image->getImageHeight();
                } catch (Exception $e) {
                    $this->error = $this->hidePath($e->getMessage());
                    return false;
                }
                break;
            case self::GD:
                $type = $this->fileCalcExt;
                switch ($type) {
                    case 'jpg':
                        $type = 'jpeg';
                        break;
                }

                $func = 'imagecreatefrom' . $type;
                if (! function_exists($func)) {
                    $this->error = 'No function to create image';
                    return false;
                }

                $image = @$func($this->filePath);
                if (! $image) {
                    $this->error = 'Failed to create image';
                    return false;
                }
                if (false === imagealphablending($image, false) || false === imagesavealpha($image, true)) {
                    $this->error = 'Failed to adjust image';
                    return false;
                }
                $width = imagesx($image);
                $height = imagesy($image);
                break;
            default:
                $this->error = 'Graphics library type not defined';
                return false;
        }
        $this->image = $image;

        return [
            $width,
            $height,
        ];
    }

    public function saveFile(string $path, bool $overwrite = false)
    {
        return $this->save($path, $overwrite, false);
    }

    public function saveImage(string $path, bool $overwrite = false)
    {
        if (empty($this->image)) {
            $this->error = 'No image';
            return false;
        }

        return $this->save($path, $overwrite, true);
    }

    protected function save(string $path, bool $overwrite, bool $isImage)
    {
        if ($this->isBadLink($path)) {
            return false;
        }

        if (! preg_match('%^(.+[\\/])([\w-]+)\.(\w+)$%', $path, $matches)) {
            $this->error = 'Bad dir name, file name or extension';
            return false;
        }

        $ext = strtolower($matches[3]);
        if (isset($this->blackList[$ext])) {
            $this->error = 'Bad file extension';
            return false;
        }
        $name = $matches[2];
        $dir = $matches[1];

        if (true !== $overwrite) {
            $tmp = '';
            $i = 0;
            while (is_file($dir . $name . $tmp . '.' . $ext) && $i < 100) {
                $tmp = '-' . random_pass(4);
                ++$i;
            }
            if ($i >= 100) {
                $this->error = 'Many similar names';
                return false;
            }
            $name .= $tmp;
        }
        $path = $dir . $name . '.' . $ext;

        if (false === $isImage) {
            $func = $this->fileIsUp ? 'move_uploaded_file' : 'copy';
            $result = @$func($this->filePath, $path);
            if (! $result) {
                $this->error = 'Failed to copy file';
                return false;
            }
        } else {
            switch ($this->libType) {
                case self::IMAGICK:
                    try {
                        //var_dump($this->image->getImageColors());
                        $type = $this->fileCalcExt;
                        switch ($type) {
                            case 'png':
                                $this->image->setImageCompressionQuality(0);
                                break;
                            default:
                                $this->image->setImageCompressionQuality($this->quality);
                                break;
                        }
                        $this->image->writeImages($path, true);
                    } catch (Exception $e) {
                        $this->error = $this->hidePath($e->getMessage(), $path);
                        return false;
                    }
                    break;
                case self::GD:
                    $result = false;
                    $type = $this->fileCalcExt;
                    $args = [$this->image, $path];
                    switch ($type) {
                        case 'jpg':
                            $type = 'jpeg';
                            $args[] = $this->quality;
                            break;
                        case 'png':
                            //$args[] = -1;
                            //$args[] = PNG_ALL_FILTERS; // PNG_NO_FILTER;
                            // imagecolorstotal
                            // , int $quality = -1 , int $filters = -1
                            break;
                        case 'webp':
                            $args[] = $this->quality;
                            break;
                    }
                    $func = 'image' . $type;
                    if (! function_exists($func)) {
                        $this->error = 'No function to save image';
                        return false;
                    }

                    $result = @$func(...$args);
                    if (true !== $result) {
                        $this->error = 'Failed to copy image';
                        return false;
                    }
                    break;
                default:
                    $this->error = 'Graphics library type not defined';
                    return false;
            }
        }

        @chmod($path, 0644);

        return [
            'path' => $path,
            'dirname' => $dir,
            'filename' => $name,
            'extension' => $ext,
        ];
    }

    public function resizeImage(int $width, ?int $height = null)
    {
        if (empty($this->image)) {
            $this->error = 'No image';
            return false;
        }

        switch ($this->libType) {
            case self::IMAGICK:
                try {
                    $oldWidth = $this->image->getImageWidth();
                    $oldHeight = $this->image->getImageHeight();
                } catch (Exception $e) {
                    $this->error = $this->hidePath($e->getMessage());
                    return false;
                }
                break;
            case self::GD:
                $oldWidth = imagesx($this->image);
                $oldHeight = imagesy($this->image);
                break;
            default:
                $this->error = 'Graphics library type not defined';
                return false;
        }

        $w = $width < 16 ? 1 : $width / $oldWidth;
        $h = ! is_numeric($height) || $height < 16 ? 1 : $height / $oldHeight;
        $r = min(1, $w, $h);
        if (1 == $r) { // ?
            return 1;
        }
        $width = (int) round($oldWidth * $r);
        $height = (int) round($oldHeight * $r);

        switch ($this->libType) {
            case self::IMAGICK:
                try {
                    // есть анимация
                    if ($this->image->getImageDelay() > 0) {
                        $image = $this->image->coalesceImages();

                        foreach ($image as $frame) {
                            $frame->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
                            $frame->setImagePage($width, $height, 0, 0);
                        }

                        $image = $image->deconstructImages();
                        //$image = $image->optimizeImageLayers();
                    // нет анимации
                    } else {
                        $image = clone $this->image;
                        $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
                    }
                } catch (Exception $e) {
                    $this->error = $this->hidePath($e->getMessage());
                    return false;
                }
                break;
            case self::GD:
                if (false === ($image = imagecreatetruecolor($width, $height))) {
                    $this->error = 'Failed to create new truecolor image';
                    return false;
                }
                if (false === ($transparent = imagecolorallocatealpha($image, 255, 255, 255, 127))) {
                    $this->error = 'Failed to create color for image';
                    return false;
                }
                if (false === imagefill($image, 0, 0, $transparent)) {
                    $this->error = 'Failed to fill image with color';
                    return false;
                }
                imagecolortransparent($image, $transparent);
                $colors = imagecolorstotal($this->image);
                if ($colors > 0 && false === imagetruecolortopalette($image, true, $colors)) {
                    $this->error = 'Failed to convert image to palette';
                    return false;
                }
                if (false === imagealphablending($image, false) || false === imagesavealpha($image, true)) {
                    $this->error = 'Failed to adjust image';
                    return false;
                }
                if (false === imagecopyresampled($image, $this->image, 0, 0, 0, 0, $width, $height, $oldWidth, $oldHeight)) {
                    $this->error = 'Failed to resize image';
                    return false;
                }
                break;
        }

        if (false === $this->destroyImage()) {
            return false;
        }
        $this->image = $image;

        return $r;
    }

    public function destroyImage()
    {
        if (empty($this->image)) {
            return true;
        }

        $result = false;

        switch ($this->libType) {
            case self::IMAGICK:
                try {
                    $result = $this->image->clear();
                } catch (Exception $e) {
                    $result = false;
                }
                break;
            case self::GD:
                if (PHP_VERSION_ID < 80000) {
                    $result = imagedestroy($this->image);
                } else {
                    $result = true;
                }
                break;
        }

        if (true === $result) {
            $this->image = null;
        } else {
            $this->error = 'Failed to clear resource';
        }

        return $result;
    }

    public function __destruct()
    {
        $this->destroyImage();
    }

    protected function hidePath(string $str, ?string $path = null)
    {
        $search = [];
        if (null !== $this->filePath) {
            $search[] = realpath($this->filePath);
            $search[] = $this->filePath;
        }
        if (null !== $path) {
            $search[] = realpath($path);
            $search[] = $path;
        }
        return empty($search) ? $str : str_replace($search, '', $str);
    }
}

$upf_class = new upfClass();
