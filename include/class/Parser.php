<?php

/**
 * @copyright  Copyright (c) 2017 Visman. All rights reserved.
 * @author     Visman <mio.visman@yandex.ru>
 * @link       https://github.com/MioVisman
 * @license    https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace FbV;

use Parserus;

class Parser extends Parserus
{
    /**
     * Конфиг форума
     * @var array
     */
    protected $config;

    /**
     * Текущий юзер
     * @var array
     */
    protected $user;

    /**
     * @var array
     */
    protected $js = [];

    /**
     * Конструктор
     *
     * @param array $config
     * @param array $user
     * @param array $lang
     */
    public function __construct(array $config, array $user, array $lang)
    {
        parent::__construct(ENT_XHTML);

        $this->config = $config;
        $this->user = $user;
        $this->setAttr('lang', $lang)
            ->setAttr('whiteListForSign', ['b', 'i', 'u', 's', 'em', 'del', 'ins', 'color', 'colour', 'email', 'img', 'url', 'topic', 'post', 'forum', 'user'])
            ->setAttr('baseUrl', get_base_url(true))
            ->setAttr('showImg', $user['show_img'] != '0')
            ->setAttr('showImgSign', $user['show_img_sig'] != '0');

        if ($config['p_message_bbcode'] == '1' || $config['p_sig_bbcode'] == '1') {
            $this->loadBBCodes();
        }
        if ($user['show_smilies'] == '1' && ($config['o_smilies_sig'] == '1' || $config['o_smilies'] == '1')) {
            $this->loadSmilies();
        }
    }

    /**
     * Метод добавляет один bb-код
     *
     * @param array $bb Массив описания bb-кода
     *
     * @return Parser
     */
    public function addBBCode(array $bb)
    {
        if ($bb['tag'] == 'quote') {
            $bb['self nesting'] = (int) $this->config['o_quote_depth'];
        }
        return parent::addBBCode($bb);
    }
    
    /**
     * Подгружает и инициализирует бб-коды
     */
    protected function loadBBCodes()
    {
        if (file_exists(PUN_ROOT . 'include/bbcode/bbcode.php')) {
            $bb = include PUN_ROOT . 'include/bbcode/bbcode.php';
        } else {
            $bb = include PUN_ROOT . 'include/bbcode/bbcode.dist.php';
        }
        $this->setBBCodes($bb);
    }

    /**
     * Подгружает и инициализирует смайлы
     */
    protected function loadSmilies()
    {
        if (file_exists(FORUM_CACHE_DIR.'cache_smilies.php')) {
            include FORUM_CACHE_DIR.'cache_smilies.php';
        } else {
            if (!defined('FORUM_CACHE_FUNCTIONS_LOADED')) {
                require PUN_ROOT.'include/cache.php';
            }

            generate_smiley_cache();
            require FORUM_CACHE_DIR.'cache_smilies.php';
        }
        $link = get_base_url(true) . '/img/smilies/';
        foreach ($smilies as &$sm) {
            $sm =  $link . $sm;
        }
        unset($sm);

        $this->setSmilies($smilies)
            ->setSmTpl('<img src="{url}" alt="{alt}" />');
    }

    /**
     * Преобразует бб-коды в html в сообщениях
     *
     * @param string $text
     * @param bool $hideSmilies
     *
     * @return string
     */
    public function parseMessage($text, $hideSmilies)
    {
        if ($this->config['o_censoring'] == '1') {
            $text = censor_words($text);
        }

        $whiteList = $this->config['p_message_bbcode'] == '1' ? null : [];
        $blackList = $this->config['p_message_img_tag'] == '1' ? [] : ['img'];

        $this->setAttr('isSign', false)
            ->setWhiteList($whiteList)
            ->setBlackList($blackList)
            ->parse($text);


        if ($this->config['o_smilies'] == '1' && $this->user['show_smilies'] == '1' && ! $hideSmilies) {
            $this->detectSmilies();
        }

        $text = $this->getHtml();

        // search HL - Visman
        global $string_shl;
        if (! empty($string_shl)) {
            $pattern = '%(?<=[^\p{L}\p{N}])('.str_replace(array('*', '\'', 'е'), array('(?:[\p{L}\p{N}]|&#039;|’|`|-)*', '(?:&#039;|’|`)', '[её]'), $string_shl).')(?![\p{L}\p{N}])(?=[^>]*<)%ui';
            $text = preg_replace($pattern, '<span class="shlight">$1</span>', '>' . $text . '<');
            $text = substr($text, 1, -1);
        }
        // search HL - Visman
        return $text;
    }

    /**
     * Преобразует бб-коды в html в подписях пользователей
     *
     * @param string $text
     *
     * @return string
     */
    public function parseSignature($text)
    {
        if ($this->config['o_censoring'] == '1') {
            $text = censor_words($text);
        }

        $whiteList = $this->config['p_sig_bbcode'] == '1' ? $this->attr('whiteListForSign') : [];
        $blackList = $this->config['p_sig_img_tag'] == '1' ? [] : ['img'];

        $this->setAttr('isSign', true)
            ->setWhiteList($whiteList)
            ->setBlackList($blackList)
            ->parse($text);

        if ($this->config['o_smilies_sig'] == '1' && $this->user['show_smilies'] == '1') {
            $this->detectSmilies();
        }

        return $this->getHtml();
    }

    /**
     * Проверяет разметку сообщения с бб-кодами
     * Пытается исправить неточности разметки
     * Генерирует ошибки разметки
     *
     * @param string $text
     * @param bool $isSignature
     *
     * @return string
     */
    public function prepare($text, $isSignature = false)
    {
        if ($isSignature) {
            $whiteList = $this->config['p_sig_bbcode'] == '1' ? $this->attr('whiteListForSign') : [];
            $blackList = $this->config['p_sig_img_tag'] == '1' ? [] : ['img'];
        } else {
            $whiteList = $this->config['p_message_bbcode'] == '1' ? null : [];
            $blackList = $this->config['p_message_img_tag'] == '1' ? [] : ['img'];
        }

        $this->setWhiteList($whiteList)
            ->setBlackList($blackList)
            ->parse($text, ['strict' => true])
            ->stripEmptyTags(" \n\t\r\v", true);

        if ($this->config['o_make_links'] == '1') {
            $this->detectUrls();
        }

        return trim($this->getCode());
    }

    /**
     * Устанавливает ссылку на js
     *
     * @param string $name
     * @param string $link
     *
     * @return Parser
     */
    public function setJsLink($name, $link)
    {
        $this->js['f'][$name] = $link;
        return $this;
    }

    /**
     * Устанавливает js в виде кода
     *
     * @param string $name
     * @param string $link
     *
     * @return Parser
     */
    public function setJsCode($name, $code)
    {
        $this->js['c'][$name] = $code;
        return $this;
    }

    /**
     * Включает jQuery
     *
     * @return Parser
     */
    public function enablejQuery()
    {
        $this->js['j'] = true;
        return $this;
    }

    /**
     * Объединяет массивы js у страницы и парсера
     *
     * @param array $js
     *
     * @return array
     */
    public function mergeJs(array $js)
    {
        if (empty($this->js)) {
            return $js;
        } else {
            return array_merge_recursive($js, $this->js);
        }
    }
}
