<?php

return [
    [
        'tag' => 'ROOT',
        'type' => 'block',
        'handler' => function($body) {
            // Replace any breaks next to paragraphs so our replace below catches them
            $body = preg_replace('%(</?p>)(?:\s*<br />){1,2}%', '$1', '<p>' . $body . '</p>');
            $body = preg_replace('%(?:<br />\s*){1,2}(</?p>)%', '$1', $body);

            // Remove any empty paragraph tags (inserted via quotes/lists/code/etc) which should be stripped
            $body = str_replace('<p></p>', '', $body);

            $body = preg_replace('%<br />\s*<br />%', '</p><p>', $body);

            $body = str_replace('<p><br />', '<br /><p>', $body);
            $body = str_replace('<br /></p>', '</p><br />', $body);
            $body = str_replace('<p></p>', '<br /><br />', $body);

            return $body;
        },
    ],
    [
        'tag' => 'code',
        'type' => 'block',
        'recursive' => true,
        'text only' => true,
        'pre' => true,
        'attrs' => [
            'Def' => true,
            'no attr' => true,
        ],
        'handler' => function($body, $attrs) {
            $body = trim($body, "\n\r");
            $class = substr_count($body, "\n") > 28 ? ' class="vscroll"' : '';
            return '</p><div class="codebox"><pre' . $class . '><code>' . $body . '</code></pre></div><p>';
        },
    ],
    [
        'tag' => 'b',
        'handler' => function($body) {
            return '<strong>' . $body . '</strong>';
        },
    ],
    [
        'tag' => 'i',
        'handler' => function($body) {
            return '<em>' . $body . '</em>';
        },
    ],
    [
        'tag' => 'em',
        'handler' => function($body) {
            return '<em>' . $body . '</em>';
        },
    ],
    [
        'tag' => 'u',
        'handler' => function($body) {
            return '<span class="bbu">' . $body . '</span>';
        },
    ],
    [
        'tag' => 's',
        'handler' => function($body) {
            return '<span class="bbs">' . $body . '</span>';
        },
    ],
    [
        'tag' => 'del',
        'handler' => function($body) {
            return '<del>' . $body . '</del>';
        },
    ],
    [
        'tag' => 'ins',
        'handler' => function($body) {
            return '<ins>' . $body . '</ins>';
        },
    ],
    [
        'tag' => 'h',
        'type' => 'h',
        'handler' => function($body) {
            return '</p><h5>' . $body . '</h5><p>';
        },
    ],
    [
        'tag' => 'hr',
        'type' => 'block',
        'single' => true,
        'handler' => function() {
            return  '</p><hr /><p>';
        },
    ],
    [
        'tag' => 'color',
        'self nesting' => 5,
        'attrs' => [
            'Def' => [
                'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
            ],
        ],
        'handler' => function($body, $attrs) {
            return '<span style="color:' . $attrs['Def'] . ';">' . $body . '</span>';
        },
    ],
    [
        'tag' => 'colour',
        'self nesting' => 5,
        'attrs' => [
            'Def' => [
                'format' => '%^(?:\#(?:[\dA-Fa-f]{3}){1,2}|(?:aqua|black|blue|fuchsia|gray|green|lime|maroon|navy|olive|orange|purple|red|silver|teal|yellow|white))$%',
            ],
        ],
        'handler' => function($body, $attrs) {
            return '<span style="color:' . $attrs['Def'] . ';">' . $body . '</span>';
        },
    ],
    [
        'tag' => 'size',
        'self nesting' => 5,
        'attrs' => [
            'Def' => [
                'format' => '%^[1-9]\d{0,2}(?:em|ex|pt|px|\%)?$%',
            ],
        ],
        'handler' => function($body, $attrs) {
            if (is_numeric($attrs['Def'])) {
                $attrs['Def'] .= 'px';
            }
            return '<span style="font-size:' . $attrs['Def'] . ';">' . $body . '</span>';
        },
    ],
    [
        'tag' => 'right',
        'type' => 'block',
        'handler' => function($body) {
            return '</p><p style="text-align: right;">' . $body . '</p><p>';
        },
    ],
    [
        'tag' => 'center',
        'type' => 'block',
        'handler' => function($body) {
            return '</p><p style="text-align: center;">' . $body . '</p><p>';
        },
    ],
    [
        'tag' => 'justify',
        'type' => 'block',
        'handler' => function($body) {
            return '</p><p style="text-align: justify;">' . $body . '</p><p>';
        },
    ],
    [
        'tag' => 'mono',
        'handler' => function($body) {
            return '<code>' . $body . '</code>';
        },
    ],
    [
        'tag' => 'email',
        'type' => 'email',
        'attrs' => [
            'Def' => [
                'format' => '%^[^\x00-\x1f\s]+?@[^\x00-\x1f\s]+$%',
            ],
            'no attr' => [
                'body format' => '%^[^\x00-\x1f\s]+?@[^\x00-\x1f\s]+$%D',
                'text only' => true,
            ],
        ],
        'handler' => function($body, $attrs) {
            if (empty($attrs['Def'])) {
                return '<a href="mailto:' . $body . '">' . $body . '</a>';
            } else {
                return '<a href="mailto:' . $attrs['Def'] . '">' . $body . '</a>';
            }
        },
    ],
    [
        'tag' => '*',
        'type' => 'block',
        'self nesting' => 5,
        'parents' => ['list'],
        'auto' => true,
        'handler' => function($body) {
            return '<li><p>' . $body . '</p></li>';
        },
    ],
    [
        'tag' => 'list',
        'type' => 'list',
        'self nesting' => 5,
        'tags only' => true,
        'attrs' => [
            'Def' => true,
            'no attr' => true,
        ],
        'handler' => function($body, $attrs) {
            if (!isset($attrs['Def'])) {
                $attrs['Def'] = '*';
            }

            switch ($attrs['Def'][0]) {
                case 'a':
                    return '</p><ol class="alpha">' . $body . '</ol><p>';
                case '1':
                    return '</p><ol class="decimal">' . $body . '</ol><p>';
                default:
                    return '</p><ul>' . $body . '</ul><p>';
            }
        },
    ],
    [
        'tag' => 'after',
        'type' => 'block',
        'single' => true,
        'attrs' => [
            'Def' => [
                'format' => '%^\d+$%',
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            $lang = $parser->attr('lang');
            $arr = array();
            $sec = $attrs['Def'] % 60;
            $min = ($attrs['Def'] / 60) % 60;
            $hours = ($attrs['Def'] / 3600) % 24;
            $days = (int) ($attrs['Def'] / 86400);
            if ($days > 0) {
                $arr[] = $days . $lang['After time d'];
            }
            if ($hours > 0) {
                $arr[] = $hours . $lang['After time H'];
            }
            if ($min > 0) {
                $arr[] = (($min < 10) ? '0' . $min : $min) . $lang['After time i'];
            }
            if ($sec > 0) {
                $arr[] = (($sec < 10) ? '0' . $sec : $sec) . $lang['After time s'];
            }

            $attr = $lang['After time'] . ' ' . implode(' ', $arr);

            return '<span style="color: #808080"><em>' . $attr . ':</em></span><br />';
        },
    ],
    [
        'tag' => 'quote',
        'type' => 'block',
        'self nesting' => 5,
        'attrs' => [
            'Def' => true,
            'no attr' => true,
        ],
        'handler' => function($body, $attrs, $parser) {
            if (isset($attrs['Def'])) {
                $lang = $parser->attr('lang');
                $st = '</p><div class="quotebox"><cite>' . $attrs['Def'] .  ' ' . $lang['wrote'] . '</cite><blockquote><div><p>';
            } else {
                $st = '</p><div class="quotebox"><blockquote><div><p>';
            }

            return $st . $body . '</p></div></blockquote></div><p>';
        },
    ],
    [
        'tag' => 'spoiler',
        'type' => 'block',
        'self nesting' => 5,
        'attrs' => [
            'Def' => true,
            'no attr' => true,
        ],
        'handler' => function($body, $attrs, $parser) {
            if (isset($attrs['Def'])) {
                $st = '</p><div class="quotebox" style="padding: 0px;"><div onclick="var e,d,c=this.parentNode,a=c.getElementsByTagName(\'div\')[1],b=this.getElementsByTagName(\'span\')[0];if(a.style.display!=\'\'){while(c.parentNode&&(!d||!e||d==e)){e=d;d=(window.getComputedStyle?getComputedStyle(c, null):c.currentStyle)[\'backgroundColor\'];if(d==\'transparent\'||d==\'rgba(0, 0, 0, 0)\')d=e;c=c.parentNode;}a.style.display=\'\';a.style.backgroundColor=d;b.innerHTML=\'&#9650;\';}else{a.style.display=\'none\';b.innerHTML=\'&#9660;\';}" style="font-weight: bold; cursor: pointer; font-size: 0.9em;"><span style="padding: 0 5px;">&#9660;</span>' . $attrs['Def'] . '</div><div style="padding: 6px; margin: 0; display: none;"><p>';
            } else {
                $lang = $parser->attr('lang');
                $st = '</p><div class="quotebox" style="padding: 0px;"><div onclick="var e,d,c=this.parentNode,a=c.getElementsByTagName(\'div\')[1],b=this.getElementsByTagName(\'span\')[0];if(a.style.display!=\'\'){while(c.parentNode&&(!d||!e||d==e)){e=d;d=(window.getComputedStyle?getComputedStyle(c, null):c.currentStyle)[\'backgroundColor\'];if(d==\'transparent\'||d==\'rgba(0, 0, 0, 0)\')d=e;c=c.parentNode;}a.style.display=\'\';a.style.backgroundColor=d;b.innerHTML=\'&#9650;\';}else{a.style.display=\'none\';b.innerHTML=\'&#9660;\';}" style="font-weight: bold; cursor: pointer; font-size: 0.9em;"><span style="padding: 0 5px;">&#9660;</span>' . $lang['Hidden text'] . '</div><div style="padding: 6px; margin: 0; display: none;"><p>';
            }

            return $st . $body . '</p></div></div><p>';
        },
    ],
    [
        'tag' => 'img',
        'type' => 'img',
        'parents' => ['inline', 'block', 'url'],
        'text only' => true,
        'attrs' => [
            'Def' => [
                'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
            'no attr' => [
                'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            if (! isset($attrs['Def'])) {
                $attrs['Def'] = (substr($body, 0, 11) === 'data:image/') ? 'base64' : basename($body);
            }

            // тег в подписи
            if ($parser->attr('isSign')) {
                if ($parser->attr('showImgSign')) {
                    return '<img src="' . $body . '" alt="' . $attrs['Def'] . '" class="sigimage" />';
                }
            // тег в теле сообщения
            } else {
                if ($parser->attr('showImg')) {
                    return '<span class="postimg"><img src="' . $body . '" alt="' . $attrs['Def'] . '" /></span>';
                }
            }

            $lang = $parser->attr('lang');
            return '<a href="' . $body . '" rel="nofollow">&lt;' . $lang['Image link']. ' - ' . $attrs['Def'] . '&gt;</a>';
        },
    ],
    [
        'tag' => 'imgr',
        'type' => 'img',
        'parents' => ['inline', 'block', 'url'],
        'text only' => true,
        'attrs' => [
            'Def' => [
                'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
            'no attr' => [
                'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            if (! isset($attrs['Def'])) {
                $attrs['Def'] = (substr($body, 0, 11) === 'data:image/') ? 'base64' : basename($body);
            }

            // тег в подписи
            if ($parser->attr('isSign')) {
                if ($parser->attr('showImgSign')) {
                    return '<img src="' . $body . '" alt="' . $attrs['Def'] . '" class="sigimage" />';
                }
            // тег в теле сообщения
            } else {
                if ($parser->attr('showImg')) {
                    return '<span class="postimg"><img src="' . $body . '" alt="' . $attrs['Def'] . '" style="float: right; clear: right;" /></span>';
                }
            }

            $lang = $parser->attr('lang');
            return '<a href="' . $body . '" rel="nofollow">&lt;' . $lang['Image link']. ' - ' . $attrs['Def'] . '&gt;</a>';
        },
    ],
    [
        'tag' => 'imgl',
        'type' => 'img',
        'parents' => ['inline', 'block', 'url'],
        'text only' => true,
        'attrs' => [
            'Def' => [
                'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
            'no attr' => [
                'body format' => '%^(?:(?:ht|f)tps?://[^\x00-\x1f\s<"]+|data:image/[a-z]+;base64,(?:[a-zA-Z\d/\+\=]+))$%D'
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            if (! isset($attrs['Def'])) {
                $attrs['Def'] = (substr($body, 0, 11) === 'data:image/') ? 'base64' : basename($body);
            }

            // тег в подписи
            if ($parser->attr('isSign')) {
                if ($parser->attr('showImgSign')) {
                    return '<img src="' . $body . '" alt="' . $attrs['Def'] . '" class="sigimage" />';
                }
            // тег в теле сообщения
            } else {
                if ($parser->attr('showImg')) {
                    return '<span class="postimg"><img src="' . $body . '" alt="' . $attrs['Def'] . '" style="float: left; clear: left;" /></span>';
                }
            }

            $lang = $parser->attr('lang');
            return '<a href="' . $body . '" rel="nofollow">&lt;' . $lang['Image link']. ' - ' . $attrs['Def'] . '&gt;</a>';
        },
    ],
    [
        'tag' => 'url',
        'type' => 'url',
        'parents' => ['inline', 'block'],
        'attrs' => [
            'Def' => [
                'format' => '%^[^\x00-\x1f]+$%',
            ],
            'no attr' => [
                'body format' => '%^[^\x00-\x1f]+$%D',
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            if (isset($attrs['Def'])) {
                $url = $attrs['Def'];
            } else {
                $url = $body;
                // возможно внутри была картинка, которая отображается как ссылка
                if (preg_match('%^<a href=".++(?<=</a>)$%D', $url)) {
                    return $url;
                }
                // возможно внутри картинка
                if (preg_match('%<img src="([^"]+)"%', $url, $match)) {
                    $url = $match[1];
                }
            }

            $fUrl = str_replace(array(' ', '\'', '`', '"'), array('%20', '', '', ''), $url);

            if (strpos($url, 'www.') === 0) {
                $fUrl = 'http://'.$fUrl;
            } else if (strpos($url, 'ftp.') === 0) {
                $fUrl = 'ftp://'.$fUrl;
            } else if (strpos($url, '/') === 0) {
                $fUrl = $parser->attr('baseUrl') . $fUrl;
            } else if (!preg_match('%^([a-z0-9]{3,6})://%', $url)) {
                $fUrl = 'http://'.$fUrl;
            }

            if ($url === $body) {
                $url = htmlspecialchars_decode($url, ENT_QUOTES);
                $url = mb_strlen($url, 'UTF-8') > 55 ? mb_substr($url, 0, 39, 'UTF-8') . ' … ' . mb_substr($url, -10, null, 'UTF-8') : $url;
                $body = $parser->e($url);
            }

            $parser->setJsLink('media', 'js/media.min.js');

            return '<a href="' . $fUrl . '" rel="nofollow">' . $body . '</a>';
        },
    ],
    [
        'tag' => 'topic',
        'type' => 'url',
        'parents' => ['inline', 'block'],
        'attrs' => [
            'Def' => [
                'format' => '%^[1-9]\d*$%',
            ],
            'no attr' => [
                'body format' => '%^[1-9]\d*$%D',
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            $id = isset($attrs['Def']) ? $attrs['Def'] : $body;

            return '<a href="' . $parser->attr('baseUrl') . '/viewtopic.php?id=' . $id . '">' . $body . '</a>';
        },
    ],
    [
        'tag' => 'post',
        'type' => 'url',
        'parents' => ['inline', 'block'],
        'attrs' => [
            'Def' => [
                'format' => '%^[1-9]\d*$%',
            ],
            'no attr' => [
                'body format' => '%^[1-9]\d*$%D',
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            $id = isset($attrs['Def']) ? $attrs['Def'] : $body;

            return '<a href="' . $parser->attr('baseUrl') . '/viewtopic.php?pid=' . $id . '#p' . $id . '">' . $body . '</a>';
        },
    ],
    [
        'tag' => 'forum',
        'type' => 'url',
        'parents' => ['inline', 'block'],
        'attrs' => [
            'Def' => [
                'format' => '%^[1-9]\d*$%',
            ],
            'no attr' => [
                'body format' => '%^[1-9]\d*$%D',
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            $id = isset($attrs['Def']) ? $attrs['Def'] : $body;

            return '<a href="' . $parser->attr('baseUrl') . '/viewforum.php?id=' . $id . '">' . $body . '</a>';
        },
    ],
    [
        'tag' => 'user',
        'type' => 'url',
        'parents' => ['inline', 'block'],
        'attrs' => [
            'Def' => [
                'format' => '%^[1-9]\d*$%',
            ],
            'no attr' => [
                'body format' => '%^[1-9]\d*$%D',
            ],
        ],
        'handler' => function($body, $attrs, $parser) {
            $id = isset($attrs['Def']) ? $attrs['Def'] : $body;

            return '<a href="' . $parser->attr('baseUrl') . '/profile.php?id=' . $id . '">' . $body . '</a>';
        },
    ],
];
