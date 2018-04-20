<?php

/**
 * @copyright  Copyright (c) 2016-2018 Visman. All rights reserved.
 * @author     Visman <mio.visman@yandex.ru>
 * @link       https://github.com/MioVisman/Parserus
 * @license    https://opensource.org/licenses/MIT The MIT License (MIT)
 */

class Parserus
{
    /**
     * Массив дерева тегов построенный методом parse()
     * @var array
     */
    protected $data;

    /**
     * Индекс последнего элемента из массива data
     * @var int
     */
    protected $dataId;

    /**
     * Индекс текущего элемента дерева из массива data
     * @var int
     */
    protected $curId;

    /**
     * Битовая маска флагов для функции htmlspecialchars()
     * @var int
     */
    protected $eFlags;

    /**
     * Массив искомых значений для замены при преобразовании текста в HTML
     * @var array
     */
    protected $tSearch = ["\n", "\t", '  ', '  '];

    /**
     * Массив значений замены при преобразовании текста в HTML
     * @var array
     */
    protected $tRepl;

    /**
     * Массив разрешенных тегов. Если null, то все теги из bbcodes разрешены
     * @var array|null
     */
    protected $whiteList = null;

    /**
     * Массив запрещенных тегов. Если null, то все теги из bbcodes разрешены
     * @var array|null
     */
    protected $blackList = null;

    /**
     * Ассоциативный массив bb-кодов
     * @var array
     */
    protected $bbcodes = [];

    /**
     * Ассоциативный массив переменных, которые можно использовать в bb-кодах
     * @var array
     */
    protected $attrs = [];

    /**
     * Ассоциативный массив смайлов
     * @var array
     */
    protected $smilies = [];

    /**
     * Паттерн для поиска смайлов в тексте при получении HTML
     * @var string|null
     */
    protected $smPattern = null;

    /**
     * Флаг необходимости обработки смайлов при получении HTML
     * @var bool
     */
    protected $smOn = false;

    /**
     * Шаблон подстановки при обработке смайлов
     * Например: <img src="{url}" alt="{alt}">
     * @var string
     */
    protected $smTpl = '';

    /**
     * Имя тега под которым идет отображение смайлов
     * @var string
     */
    protected $smTag = '';

    /**
     * Список тегов в которых не нужно отображать смайлы
     * @var array
     */
    protected $smBL = [];

    /**
     * Массив ошибок полученных при отработке метода parse()
     * @var array
     */
    protected $errors = [];

    /**
     * Флаг строгого режима поиска ошибок
     * Нужен, например, для проверки атрибутов тегов при получении текста от пользователя
     * @var bool
     */
    protected $strict = false;

    /**
     * Максимальная глубина дерева тегов при строгом режиме поиска ошибок
     * @var int
     */
    protected $maxDepth;

    /**
     * Конструктор
     *
     * @param int $flag Один из флагов ENT_HTML401, ENT_XML1, ENT_XHTML, ENT_HTML5
     */
    public function __construct($flag = ENT_HTML5)
    {
        if (! in_array($flag, [ENT_HTML401, ENT_XML1, ENT_XHTML, ENT_HTML5])) {
            $flag = ENT_HTML5;
        }
        $this->eFlags = $flag | ENT_QUOTES | ENT_SUBSTITUTE;
        $this->tRepl = in_array($flag, [ENT_HTML5, ENT_HTML401])
            ? ['<br>',   '&nbsp; &nbsp; ', '&nbsp; ', ' &nbsp;']
            : ['<br />', '&#160; &#160; ', '&#160; ', ' &#160;'];
    }

    /**
     * Метод добавляет один bb-код
     *
     * @param  array    $bb   Массив описания bb-кода
     *
     * @return Parserus $this
     */
    public function addBBCode(array $bb)
    {
        $res = [
            'type' => 'inline',
            'parents' => ['inline' => 1, 'block' => 2],
            'auto' => true,
            'self nesting' => false,
        ];

        if ($bb['tag'] === 'ROOT') {
            $tag = 'ROOT';
        } else {
            $tag = strtolower($bb['tag']);
        }

        if (isset($bb['type'])) {
            $res['type'] = $bb['type'];

            if ($bb['type'] !== 'inline') {
                $res['parents'] = ['block' => 1];
                $res['auto'] = false;
            }
        }

        if (isset($bb['parents'])) {
            $res['parents'] = array_flip($bb['parents']);
        }

        if (isset($bb['auto'])) {
            $res['auto'] = (bool) $bb['auto'];
        }

        if (isset($bb['self nesting'])) {
            $res['self nesting'] = (int) $bb['self nesting'] > 0 ? (int) $bb['self nesting'] : false;
        }

        if (isset($bb['recursive'])) {
            $res['recursive'] = true;
        }

        if (isset($bb['text only'])) {
            $res['text only'] = true;
        }

        if (isset($bb['tags only'])) {
            $res['tags only'] = true;
        }

        if (isset($bb['single'])) {
            $res['single'] = true;
        }

        if (isset($bb['pre'])) {
            $res['pre'] = true;
        }

        $res['handler'] = isset($bb['handler']) ? $bb['handler'] : null;
        $res['text handler'] = isset($bb['text handler']) ? $bb['text handler'] : null;

        $required = [];
        $attrs = [];
        $other = false;

        if (! isset($bb['attrs'])) {
            $cur = [];

            if (isset($bb['body format'])) {
                $cur['body format'] = $bb['body format'];
            }
            if (isset($bb['text only'])) {
                $cur['text only'] = true;
            }

            $attrs['no attr'] = $cur;
        } else {
            foreach ($bb['attrs'] as $attr => $cur) {
                if (! is_array($cur)) {
                    $cur = [];
                }

                if (isset($bb['text only'])) {
                    $cur['text only'] = true;
                }

                $attrs[$attr] = $cur;

                if (isset($cur['required'])) {
                    $required[] = $attr;
                }

                if ($attr !== 'Def' && $attr !== 'no attr') {
                    $other = true;
                }
            }
        }

        $res['attrs'] = $attrs;
        $res['required'] = $required;
        $res['other'] = $other;

        $this->bbcodes[$tag] = $res;
        return $this;
    }

    /**
     * Метод задает массив bb-кодов
     *
     * @param  array    $bbcodes Массив описаний bb-кодов
     *
     * @return Parserus $this
     */
    public function setBBCodes(array $bbcodes)
    {
        $this->bbcodes = [];

        foreach ($bbcodes as $bb) {
            $this->addBBCode($bb);
        }

        $this->defaultROOT();
        return $this;
    }

    /**
     * Метод устанавливает тег ROOT при его отсутствии
     */
    protected function defaultROOT()
    {
        if (! isset($this->bbcodes['ROOT'])) {
            $this->addBBCode(['tag' => 'ROOT', 'type' => 'block']);
        }
    }

    /**
     * Метод задает массив смайлов
     *
     * @param  array    $smilies Ассоциативный массив смайлов
     *
     * @return Parserus $this
     */
    public function setSmilies(array $smilies)
    {
        $this->smilies = $smilies;
        $this->createSmPattern();
        return $this;
    }

    /**
     * Метод генерирует паттерн для поиска смайлов в тексте
     */
    protected function createSmPattern()
    {
        if (empty($this->smilies)) {
            $this->smPattern = null;
            return;
        }

        $arr = array_keys($this->smilies);
        sort($arr);
        $arr[] = '  ';

        $symbol = '';
        $pattern = '';
        $quote = '';
        $sub = [];

        foreach ($arr as $val) {
            if (preg_match('%^(.)(.+)%u', $val, $match)) {
                if ($symbol === $match[1]) {
                    $sub[] = preg_quote($match[2], '%');
                } else {
                    if (count($sub) > 1) {
                        $pattern .= $quote . preg_quote($symbol, '%') . '(?:' . implode('|', $sub) . ')';
                        $quote = '|';
                    } else if (count($sub) == 1) {
                        $pattern .= $quote . preg_quote($symbol, '%') . $sub[0];
                        $quote = '|';
                    }
                    $symbol = $match[1];
                    $sub = [preg_quote($match[2], '%')];
                }
            }
        }

        $this->smPattern = '%(?<=\s|^)(?:' . $pattern . ')(?![\p{L}\p{N}])%u';
    }

    /**
     * Метод устанавливает шаблон для отображения смайлов
     *
     * @param  string   $tpl  Строка шаблона, например: <img src="{url}" alt="{alt}">
     * @param  string   $tag  Имя тега под которым идет отображение смайлов
     * @param  array    $bl   Список тегов в которых не нужно отображать смайлы
     *
     * @return Parserus $this
     */
    public function setSmTpl($tpl, $tag = 'img', array $bl = ['url'])
    {
        $this->smTpl = $tpl;
        $this->smTag = $tag;
        $this->smBL = array_flip($bl);
        return $this;
    }

    /**
     * Метод включает (если есть возможность) отображение смайлов на текущем дереве тегов
     *
     * @return Parserus $this
     */
    public function detectSmilies()
    {
        $this->smOn = null !== $this->smPattern && isset($this->bbcodes[$this->smTag]);
        return $this;
    }

    /**
     * Метод устанавливает список разрешенных bb-кодов
     *
     * @param  mixed    $list Массив bb-кодов, null и т.д.
     *
     * @return Parserus $this
     */
    public function setWhiteList($list = null)
    {
        $this->whiteList = is_array($list) ? $list : null;
        return $this;
    }

    /**
     * Метод устанавливает список запрещенных bb-кодов
     *
     * @param  mixed    $list Массив bb-кодов, null и т.д.
     *
     * @return Parserus $this
     */
    public function setBlackList($list = null)
    {
        $this->blackList = ! empty($list) && is_array($list) ? $list : null;
        return $this;
    }

    /**
     * Метод задает значение переменной для возможного использования в bb-кодах
     *
     * @param  string   $name Имя переменной
     * @param  mixed    $val  Значение переменной
     *
     * @return Parserus $this
     */
    public function setAttr($name, $val)
    {
        $this->attrs[$name] = $val;
        return $this;
    }

    /**
     * Метод для получения значения переменной
     *
     * @param  string $name Имя переменной
     *
     * @return mixed  Значение переменной или null, если переменная не была задана ранее
     */
    public function attr($name)
    {
        return isset($this->attrs[$name]) ? $this->attrs[$name] : null;
    }

    /**
     * Метод добавляет новый тег в дерево тегов
     *
     * @param  string $tag      Имя тега
     * @param  int    $parentId Указатель на родителя
     * @param  array  $attrs    Массив атрибутов тега
     * @param  bool   $textOnly Флаг. Если true, то в теле только текст
     *
     * @return int              Указатель на данный тег
     */
    protected function addTagNode($tag, $parentId = null, array $attrs = [], $textOnly = false)
    {
        $this->data[++$this->dataId] = [
            'tag'      => $tag,
            'parent'   => $parentId,
            'children' => [],
            'attrs'    => $attrs,
        ];

        if ($textOnly) {
            $this->data[$this->dataId]['text only'] = true;
        }

        if (null !== $parentId) {
            $this->data[$parentId]['children'][] = $this->dataId;
        }

        return $this->dataId;
    }

    /**
     * Метод добавляет текстовый узел в дерево тегов
     *
     * @param  string $text     Текст
     * @param  int    $parentId Указатель на родителя
     *
     * @return string           Пустая строка
     */
    protected function addTextNode($text, $parentId)
    {
        if (isset($text[0])) {
            $this->data[++$this->dataId] = [
                'text'   => $text,
                'parent' => $parentId,
            ];

            $this->data[$parentId]['children'][] = $this->dataId;
        }

        return '';
    }

    /**
     * Метод нормализует содержимое атрибута
     *
     * @param  string $attr Содержимое атрибута полученное из регулярного выражения
     *
     * @return string
     */
    protected function getNormAttr($attr)
    {
        // удаление крайних кавычек
        if (isset($attr[1])
            && $attr[0] === $attr[strlen($attr) - 1]
            && ($attr[0] === '"' || $attr[0] === '\'')
        ) {
            return substr($attr, 1, -1);
        }

        return $attr;
    }

    /**
     * Метод выделяет все атрибуты с их содержимым для обрабатываемого тега
     *
     * @param  string           $tag  Имя обрабатываемого тега
     * @param  string           $type "Тип атрибутов" = ' ', '=' или ']'
     * @param  string           $text Текст из которого выделяются атрибуты
     *
     * @return null|array
     */
    protected function parseAttrs($tag, $type, $text)
    {
        $attrs = [];
        $tagText = '';

        if ($type === '=') {
            $pattern = '%^(?!\x20)
                ("[^\x00-\x1f"]*(?:"+(?!\x20*+\]|\x20++[a-z-]{2,15}=)[^\x00-\x1f"]*)*"
                |\'[^\x00-\x1f\']*(?:\'+(?!\x20*+\]|\x20++[a-z-]{2,15}=)[^\x00-\x1f\']*)*\'
                |[^\x00-\x20\]]+(?:\x20++(?!\]|[a-z-]{2,15}=)[^\x00-\x20\]]+)*)
                \x20*
                (\]|\x20(?=[a-z-]{2,15}=))%x';

            $match = preg_split($pattern, $text, 2, PREG_SPLIT_DELIM_CAPTURE);

            if (! isset($match[1])) {
                return null;
            }

            $type = $match[2];
            $tagText .= $match[1] . $match[2];
            $text = $match[3];

            $tmp = $this->getNormAttr($match[1]);
            if (isset($tmp[0])) {
                $attrs['Def'] = $tmp;

                // в теге не может быть первичного атрибута
                if ($this->strict
                    && ! isset($this->bbcodes[$tag]['attrs']['Def'])
                ) {
                    $this->errors[] = [7, $tag];
                    return null;
                }
            }
        }

        if ($type !== ']') {
            $pattern = '%^\x20*+([a-z-]{2,15})
                =(?!\x20)
                ("[^\x00-\x1f"]*(?:"+(?!\x20*+\]|\x20++[a-z-]{2,15}=)[^\x00-\x1f"]*)*"
                |\'[^\x00-\x1f\']*(?:\'+(?!\x20*+\]|\x20++[a-z-]{2,15}=)[^\x00-\x1f\']*)*\'
                |[^\x00-\x20\]]+(?:\x20++(?!\]|[a-z-]{2,15}=)[^\x00-\x20\]]+)*)
                \x20*
                (\]|\x20(?=[a-z-]{2,15}=))%x';

            do {
                $match = preg_split($pattern, $text, 2, PREG_SPLIT_DELIM_CAPTURE);

                if (! isset($match[1])) {
                    return null;
                }

                $tagText .= $match[1] . '=' . $match[2] . $match[3];
                $text = $match[4];

                $tmp = $this->getNormAttr($match[2]);
                if (isset($tmp[0])) {
                    $attrs[$match[1]] = $tmp;

                    if ($this->strict) {
                        // в теге не может быть вторичных атрибутов
                        if (! $this->bbcodes[$tag]['other']) {
                            $this->errors[] = [8, $tag];
                            return null;
                        }
                        // этот атрибут отсутвтует в описании тега
                        if (! isset($this->bbcodes[$tag]['attrs'][$match[1]])) {
                            $this->errors[] = [10, $tag, $match[1]];
                            return null;
                        }
                    }
                }

            } while ($match[3] !== ']');
        }

        if (empty($attrs)) {
            // в теге должны быть атрибуты
            if (! empty($this->bbcodes[$tag]['required'])
                || ! isset($this->bbcodes[$tag]['attrs']['no attr'])
            ) {
                $this->errors[] = [6, $tag];
                return null;
            }
        } else {
            foreach ($this->bbcodes[$tag]['required'] as $key) {
                // нет обязательного атрибута
                if (! isset($attrs[$key])) {
                    $this->errors[] = [13, $tag, $key];
                    return null;
                }
            }
        }

        return [
            'attrs' => $attrs,
            'tag'   => $tagText,
            'text'  => $text,
        ];
    }

    /**
     * Метод определяет указатель на родительский тег для текущего
     *
     * @param  string    $tag Имя тега
     *
     * @return int|false      false, если невозможно подобрать родителя
     */
    protected function findParent($tag)
    {
        if (false === $this->bbcodes[$tag]['self nesting']) {
            $curId = $this->curId;

            while (null !== $curId) {
                // этот тег нельзя открыть внутри аналогичного
                if ($this->data[$curId]['tag'] === $tag) {
                    $this->errors[] = [12, $tag];
                    return false;
                }
                $curId = $this->data[$curId]['parent'];
            }
        }

        $curId = $this->curId;
        $curTag = $this->data[$curId]['tag'];

        while (null !== $curId) {
            if (isset($this->bbcodes[$tag]['parents'][$this->bbcodes[$curTag]['type']])) {
                return $curId;
            } else if ($this->bbcodes[$tag]['type'] === 'inline'
                       || false === $this->bbcodes[$curTag]['auto']
            ) {
                // тег не может быть открыт на этой позиции
                $this->errors[] = [3, $tag, $this->data[$this->curId]['tag']];
                return false;
            }

            $curId = $this->data[$curId]['parent'];
            $curTag = $this->data[$curId]['tag'];
        }

        $this->errors[] = [3, $tag, $this->data[$this->curId]['tag']];
        return false;
    }

    /**
     * Метод проводит проверку значений атрибутов и(или) тела тега на соответствие правилам
     *
     * @param  string      $tag   Имя тега
     * @param  array       $attrs Массив атрибутов
     * @param  string      $text  Текст из которого выделяется тело тега
     *
     * @return array|false        false в случае ошибки
     */
    protected function validationTag($tag, array $attrs, $text)
    {
        if (empty($attrs)) {
            $attrs['no attr'] = null;
        }

        $body = null;
        $end = null;
        $tested = [];
        $flag = false;
        $bb = $this->bbcodes[$tag];

        foreach ($attrs as $key => $val) {
            // проверка формата атрибута
            if (isset($bb['attrs'][$key]['format'])
                && ! preg_match($bb['attrs'][$key]['format'], $val)
            ) {
                $this->errors[] = [9, $tag, $key];
                return false;
            }

            // для рекурсивного тега тело не проверяется даже если есть правила
            if (isset($bb['recursive'])) {
                continue;
            }

            // тело тега
            if (null === $body
                && (isset($bb['attrs'][$key]['body format'])
                    || isset($bb['attrs'][$key]['text only']))
            ) {
                $ptag = preg_quote($tag, '%');
                $match = preg_split('%^([^\[]*(?:\[(?!/' . $ptag . '\])[^\[]*)*)(?:\[/' . $ptag . '\])?%i', $text, 2, PREG_SPLIT_DELIM_CAPTURE);

                $body = $match[1];
                $end = $match[2];
            }

            // для тега с 'text only' устанавливается флаг для возврата тела
            if (isset($bb['attrs'][$key]['text only'])) {
                $flag = true;
            }

            // проверка формата тела тега
            if (isset($bb['attrs'][$key]['body format'])) {
                if (isset($tested[$bb['attrs'][$key]['body format']])) {
                    continue;
                } else if (! preg_match($bb['attrs'][$key]['body format'], $body)) {
                    $this->errors[] = [11, $tag];
                    return false;
                }

                $tested[$bb['attrs'][$key]['body format']] = true;
            }
        }

        unset($attrs['no attr']);

        return [
            'attrs' => $attrs,
            'body'  => $flag ? $body : null,
            'end'   => $end,
        ];
    }

    /**
     * Метод закрывает текущий тег
     *
     * @param  string $tag     Имя обрабатываемого тега
     * @param  string $curText Текст до тега, который еще не был учтен
     * @param  string $tagText Текст самого тега - [/tag]
     *
     * @return string          Пустая строка, если тег удалось закрыть
     */
    protected function closeTag($tag, $curText, $tagText) {
        // ошибка одиночного тега
        if (isset($this->bbcodes[$tag]['single'])) {
            $this->errors[] = [5, $tag];
            return $curText . $tagText;
        }

        $curId = $this->curId;
        $curTag = $this->data[$curId]['tag'];

        while ($curTag !== $tag && $curId > 0) {
            if ($this->bbcodes[$tag]['type'] === 'inline'
                || false === $this->bbcodes[$curTag]['auto']
            ) {
                break;
            }

            $curId = $this->data[$curId]['parent'];
            $curTag = $this->data[$curId]['tag'];
        }

        // ошибка закрытия тега
        if ($curTag !== $tag) {
            $this->errors[] = [4, $tag];
            return $curText . $tagText;
        }

        $this->addTextNode($curText, $this->curId);

        $this->curId = $this->data[$curId]['parent'];
        return '';
    }

    /**
     * Сброс состояния
     *
     * @param array $opts Ассоциативный массив опций
     */
    protected function reset(array $opts)
    {
        $this->defaultROOT();
        $this->data = [];
        $this->dataId = -1;
        $this->curId = $this->addTagNode(
            isset($opts['root']) && isset($this->bbcodes[$opts['root']])
            ? $opts['root']
            : 'ROOT'
        );
        $this->smOn = false;
        $this->errors = [];
        $this->strict = isset($opts['strict']) ? (bool) $opts['strict'] : false;
        $this->maxDepth = isset($opts['depth']) ? (int) $opts['depth'] : 10;
    }

    /**
     * Метод строит дерево тегов из текста содержащего bb-коды
     *
     * @param  string   $text Обрабатываемый текст
     * @param  array    $opts Ассоциативный массив опций
     *
     * @return Parserus $this
     */
    public function parse($text, array $opts = [])
    {
        $this->reset($opts);
        $curText = '';
        $recCount = 0;

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        while (($match = preg_split('%(\[(/)?(' . ($recCount ? $recTag : '[a-z\*][a-z\d-]{0,10}') . ')((?(1)\]|[=\]\x20])))%i', $text, 2, PREG_SPLIT_DELIM_CAPTURE))
               && isset($match[1])
        ) {
            /* $match[0] - текст до тега
             * $match[1] - [ + (|/) + имя тега + (]| |=)
             * $match[2] - (|/)
             * $match[3] - имя тега
             * $match[4] - тип атрибутов --> (]| |=)
             * $match[5] - остаток текста до конца
             */
            $tagText = $match[1];
            $curText .= $match[0];
            $text = $match[5];
            $tag = strtolower($match[3]);

            if (! isset($this->bbcodes[$tag])) {
                $curText .= $tagText;
                continue;
            }

            if (! empty($match[2])) {
                if ($recCount && --$recCount) {
                    $curText .= $tagText;
                } else {
                    $curText = $this->closeTag($tag, $curText, $tagText);
                }
                continue;
            }

            $attrs = $this->parseAttrs($tag, $match[4], $text);

            if (null === $attrs) {
                $curText .= $tagText;
                continue;
            }

            if (isset($attrs['tag'][0])) {
                $tagText .= $attrs['tag'];
                $text = $attrs['text'];
            }

            if ($recCount) {
                ++$recCount;
                $curText .= $tagText;
                continue;
            }

            if (null !== $this->blackList && in_array($tag, $this->blackList)) {
                $curText .= $tagText;
                $this->errors[] = [1, $tag];
                continue;
            }

            if (null !== $this->whiteList && ! in_array($tag, $this->whiteList)) {
                $curText .= $tagText;
                $this->errors[] = [2, $tag];
                continue;
            }

            if (($parentId = $this->findParent($tag)) === false) {
                $curText .= $tagText;
                continue;
            }

            if (($attrs = $this->validationTag($tag, $attrs['attrs'], $text)) === false) {
                $curText .= $tagText;
                continue;
            }

            $curText = $this->addTextNode($curText, $this->curId);

            $id = $this->addTagNode(
                $tag,
                $parentId,
                $attrs['attrs'],
                isset($attrs['body']) || isset($this->bbcodes[$tag]['text only'])
            );

            if (isset($attrs['body'])) {
                $this->addTextNode($attrs['body'], $id);

                $text = $attrs['end'];
                $this->curId = $parentId;

            } else if (isset($this->bbcodes[$tag]['single'])) {
                $this->curId = $parentId;

            } else {
                $this->curId = $id;

                if (isset($this->bbcodes[$tag]['recursive'])) {
                    $recCount = 1;
                    $recTag = preg_quote($tag, '%');
                }
            }
        }

        $this->addTextNode($curText . $text, $this->curId);

        if ($this->strict) {
            $this->searchError();
        }

        return $this;
    }

    /**
     * Метод проверяет глубину дерева тегов
     * Метод проверяет лимит вложенности тегов в самих себя
     *
     * @param int   $id    Указатель на текущий тег
     * @param int   $depth Глубина дерева на текущий момент
     * @param array $tags  Массив количества вложений тегов с включенным 'self nesting'
     *
     * @return bool
     */
    protected function searchError($id = 0, $depth = -1, array $tags = [])
    {
        if (isset($this->data[$id]['text'])) {
            return false;
        }

        ++$depth;

        if ($depth > $this->maxDepth) {
            $this->errors[] = [15, $this->maxDepth];
            return true;
        }

        $tag = $this->data[$id]['tag'];
        if (false !== $this->bbcodes[$tag]['self nesting']) {
            if (isset($tags[$tag])) {
                ++$tags[$tag];
            } else {
                $tags[$tag] = 0;
            }
            if ($tags[$tag] > $this->bbcodes[$tag]['self nesting']) {
                $this->errors[] = [16, $tag, $this->bbcodes[$tag]['self nesting']];
                return true;
            }
        }
        foreach ($this->data[$id]['children'] as $child) {
            if ($this->searchError($child, $depth, $tags)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Метод возвращает HTML построенный на основании дерева тегов
     *
     * @param  int    $id Указатель на текущий тег
     *
     * @return string
     */
    public function getHtml($id = 0)
    {
        if (isset($this->data[$id]['tag'])) {

            $body = '';
            foreach ($this->data[$id]['children'] as $cid) {
                $body .= $this->getHtml($cid);
            }

            $bb = $this->bbcodes[$this->data[$id]['tag']];

            if (null === $bb['handler']) {
                return $body;
            }

            $attrs = [];
            foreach ($this->data[$id]['attrs'] as $key => $val) {
                if (isset($bb['attrs'][$key])) {
                    $attrs[$key] = $this->e($val);
                }
            }

            return $bb['handler']($body, $attrs, $this);
        }

        $pid = $this->data[$id]['parent'];
        $bb = $this->bbcodes[$this->data[$pid]['tag']];

        if (isset($bb['tags only'])) {
            return '';
        }

        switch (2 * (end($this->data[$pid]['children']) === $id)
                + ($this->data[$pid]['children'][0] === $id)
        ) {
            case 1:
                $text = $this->e(preg_replace('%^\x20*\n%', '', $this->data[$id]['text']));
                break;
            case 2:
                $text = $this->e(preg_replace('%\n\x20*$%D', '', $this->data[$id]['text']));
                break;
            case 3:
                $text = $this->e(preg_replace('%^\x20*\n|\n\x20*$%D', '', $this->data[$id]['text']));
                break;
            default:
                $text = $this->e($this->data[$id]['text']);
                break;
        }

        if (empty($this->data[$pid]['text only'])
            && $this->smOn
            && isset($this->bbcodes[$this->smTag]['parents'][$bb['type']])
            && ! isset($this->smBL[$this->data[$pid]['tag']])
        ) {
            $text = preg_replace_callback($this->smPattern, function($m) {
                return str_replace(
                    ['{url}', '{alt}'],
                    [$this->e($this->smilies[$m[0]]), $this->e($m[0])],
                    $this->smTpl
                );
            }, $text);
        }

        if (! isset($bb['pre'])) {
            $text = str_replace($this->tSearch, $this->tRepl, $text);
        }

        return $text;
    }

    /**
     * Метод возвращает текст с bb-кодами построенный на основании дерева тегов
     *
     * @param  int    $id Указатель на текущий тег
     *
     * @return string
     */
    public function getCode($id = 0)
    {
        if (isset($this->data[$id]['text'])) {
            return $this->data[$id]['text'];
        }

        $body = '';
        foreach ($this->data[$id]['children'] as $cid) {
            $body .= $this->getCode($cid);
        }

        if ($id === 0) {
            return $body;
        }

        $tag = $this->data[$id]['tag'];
        $attrs = $this->data[$id]['attrs'];

        $def = '';
        $other = '';
        $count = count($attrs);
        foreach ($attrs as $attr => $val) {
            $quote = '';
            if ($count > 1 || strpbrk($val, ' \'"]')) {
                $quote = '"';
                if (false !== strpos($val, '"') && false === strpos($val, '\'')) {
                    $quote = '\'';
                }
            }
            if ($attr === 'Def') {
                $def = '=' . $quote . $val . $quote;
            } else {
                $other .= ' ' . $attr . '=' . $quote . $val . $quote;
            }
        }

        return '[' . $tag . $def . $other . ']' . (isset($this->bbcodes[$tag]['single']) ? '' : $body . '[/' . $tag .']');
    }

    /**
     * Метод возвращает текст без bb-кодов построенный на основании дерева тегов
     *
     * @param  int    $id Указатель на текущий тег
     *
     * @return string
     */
    public function getText($id = 0)
    {
        if (isset($this->data[$id]['tag'])) {

            $body = '';
            foreach ($this->data[$id]['children'] as $cid) {
                $child = $this->getText($cid);
                if (isset($body{0}, $child{0})) {
                    $body .= ' ' . $child;
                } else {
                    $body .= $child;
                }
            }

            $bb = $this->bbcodes[$this->data[$id]['tag']];

            if (null === $bb['text handler']) {
                return $body;
            }

            $attrs = [];
            foreach ($this->data[$id]['attrs'] as $key => $val) {
                if (isset($bb['attrs'][$key])) {
                    $attrs[$key] = $val;
                }
            }

            return $bb['text handler']($body, $attrs, $this);
        }

        $pid = $this->data[$id]['parent'];
        $bb = $this->bbcodes[$this->data[$pid]['tag']];

        return  isset($bb['tags only']) ? '' : $this->data[$id]['text'];
    }

    /**
     * Метод ищет в текстовых узлах ссылки и создает на их месте узлы с bb-кодами url
     * Для уменьшения нагрузки использовать при сохранении, а не при выводе
     *
     * @return Parserus $this
     */
    public function detectUrls()
    {
        $pattern = '%\b(?<=\s|^)
            (?>(?:ht|f)tps?://|www\.|ftp\.)
            (?:[\p{L}\p{N}]+(?:[\p{L}\p{N}\-]*[\p{L}\p{N}])?\.)+
            \p{L}[\p{L}\p{N}\-]*[\p{L}\p{N}]
            (?::\d{1,5})?
            (?:/
                (?:[\p{L}\p{N};:@&=$_.+!*\'"(),\%/-]+)?
                (?:\?[\p{L}\p{N};:@&=$_.+!*\'"(),\%-]+)?
                (?:\#[\p{L}\p{N}-]+)?
            )?%xu';

        return $this->detect('url', $pattern, true);
    }

    /**
     * Метод ищет в текстовых узлах совпадения с $pattern и создает на их месте узлы с bb-кодами $tag
     *
     * @param  string   $tag      Имя для создания bb-кода
     * @param  string   $pattern  Регулярное выражение для поиска
     * @param  bool     $textOnly Флаг. true, если содержимое созданного тега текстовое
     *
     * @return Parserus $this
     */
    protected function detect($tag, $pattern, $textOnly)
    {
        if (! isset($this->bbcodes[$tag])) {
            return $this;
        }

        $error = null;
        if (null !== $this->blackList && in_array($tag, $this->blackList)) {
            $error = 1;
        } else if (null !== $this->whiteList && ! in_array($tag, $this->whiteList)) {
            $error = 2;
        }

        for ($id = $this->dataId; $id > 0; --$id) {
            // не текстовый узел
            if (! isset($this->data[$id]['text'])) {
                continue;
            }

            $pid = $this->data[$id]['parent'];

            // родитель может содержать только текст или не подходит по типу
            if (isset($this->data[$pid]['text only']) ||
                ! isset($this->bbcodes[$tag]['parents'][$this->bbcodes[$this->data[$pid]['tag']]['type']])
            ) {
                continue;
            }

            if (! preg_match_all($pattern, $this->data[$id]['text'], $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            } else if ($error) {
                $this->errors[] = [$error, $tag];
                return $this;
            }

            $idx = array_search($id, $this->data[$pid]['children']);
            $arrEnd = array_slice($this->data[$pid]['children'], $idx + 1);
            $this->data[$pid]['children'] = array_slice($this->data[$pid]['children'], 0, $idx);

            $pos = 0;

            foreach ($matches[0] as $match) {
                $this->addTextNode(substr($this->data[$id]['text'], $pos, $match[1] - $pos), $pid);

                $new = $this->addTagNode($tag, $pid, [], $textOnly);
                $this->addTextNode($match[0], $new);

                $pos = $match[1] + strlen($match[0]);
            }

            $this->addTextNode((string) substr($this->data[$id]['text'], $pos), $pid);
            unset($this->data[$id]);

            $this->data[$pid]['children'] = array_merge($this->data[$pid]['children'], $arrEnd);
        }

        return $this;
    }

    /**
     * Метод удаляет пустые теги из дерева
     *
     * @param  string $mask Маска символов, которые не учитываются при определении пустоты текстовых узлов
     * @param  bool   $flag Если true, то при пустом дереве оно не будет очищено, а останется без изменений,
     *                      но будет оставлена ошибка, которая отобразится в getErrors()
     * @return bool         Если true, то дерево тегов пусто
     */
    public function stripEmptyTags($mask = '', $flag = false)
    {
        if ($flag) {
            $data = $this->data;

            if ($this->stripEmptyTags_($mask, 0)) {
                $this->errors[] = [14];
                $this->data = $data;
                return true;
            }
            return false;

        } else {
            return $this->stripEmptyTags_($mask, 0);
        }
    }

    /**
     * Метод рекурсивно удаляет пустые теги из дерева
     *
     * @param  string $mask Маска символов, которые не учитываются при определении пустоты текстовых узлов
     * @param  int    $id   Указатель на текущий тег
     *
     * @return bool         Если true, то тег/узел пустой
     */
    protected function stripEmptyTags_($mask, $id)
    {
        // текстовый узел
        if (isset($this->data[$id]['text'])) {
            if (isset($mask[0])) {
                return trim($this->data[$id]['text'], $mask) === '';
            }
            return false;
        }

        // одиночный тег
        if (isset($this->bbcodes[$this->data[$id]['tag']]['single'])) {
            return false;
        }

        $res = true;
        // перебор детей с удалением тегов
        foreach ($this->data[$id]['children'] as $key => $cid) {
            if ($this->stripEmptyTags_($mask, $cid)) {
                if (isset($this->data[$cid]['tag'])) {
                    unset($this->data[$id]['children'][$key]);
                    unset($this->data[$cid]);
                }
            } else {
               $res = false;
            }
        }

        if ($res) {
            foreach ($this->data[$id]['children'] as $cid) {
                unset($this->data[$cid]);
            }
            $this->data[$id]['children'] = [];
        }

        return $res;
    }

    /**
     * Метод возвращает массив ошибок
     *
     * @param  array $lang   Массив строк шаблонов описания ошибок
     * @param  array $errors Массив, который дополняется ошибками
     *
     * @return array
     */
    public function getErrors(array $lang = [], array $errors = [])
    {
        $defLang = [
            1 => 'Тег [%1$s] находится в черном списке',
            2 => 'Тег [%1$s] отсутствует в белом списке',
            3 => 'Тег [%1$s] нельзя открыть внутри тега [%2$s]',
            4 => 'Не найден начальный тег для парного тега [/%1$s]',
            5 => 'Найден парный тег [/%1$s] для одиночного тега [%1$s]',
            6 => 'В теге [%1$s] отсутствуют атрибуты',
            7 => 'Тег [%1$s=...] не может содержать первичный атрибут',
            8 => 'Тег [%1$s ...] не может содержать вторичные атрибуты',
            9 => 'Атрибут \'%2$s\' тега [%1$s] не соответствует шаблону',
            10 => 'Тег [%1$s ...] содержит неизвестный вторичный атрибут \'%2$s\'',
            11 => 'Тело тега [%1$s] не соответствует шаблону',
            12 => 'Тег [%1$s] нельзя открыть внутри аналогичного тега',
            13 => 'В теге [%1$s] отсутствует обязательный атрибут \'%2$s\'',
            14 => 'Все теги пустые',
            15 => 'Глубина дерева тегов больше %1$s',
            16 => 'Тег [%1$s] вложен в себя больше %2$s раз',
        ];

        foreach ($this->errors as $args) {
            $err = array_shift($args);

            if (isset($lang[$err])) {
                $text = $lang[$err];
            } else if (isset($defLang[$err])) {
                $text = $defLang[$err];
            } else {
                $text = 'Unknown error';
            }

            $errors[] = vsprintf($text, array_map([$this, 'e'], $args));
        }

        return $errors;
    }

    /**
     * Метод преобразует специальные символы в HTML-сущности
     *
     * @param  string $text
     *
     * @return string
     */
    public function e($text)
    {
        return htmlspecialchars($text, $this->eFlags, 'UTF-8');
    }
}
