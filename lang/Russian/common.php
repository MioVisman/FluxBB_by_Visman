<?php

// Language definitions for frequently used strings
$lang_common = array(

// Text orientation and encoding
'lang_direction' => 'ltr', // ltr (Left-To-Right) or rtl (Right-To-Left)
'lang_identifier' => 'ru',

// Number formatting
'lang_decimal_point' => '.',
'lang_thousands_sep' => ',',

// Notices
'Bad request' => 'Неверный запрос. Ссылка, по которой вы перешли, является неверной или просроченной.',
'No view' => 'У вас нет прав для просмотра форума.',
'No permission' => 'У вас нет прав на просмотр этой страницы.',
'Bad referrer' => 'Неверный csrf_hash. Вы перешли на эту страницу из неавторизованного источника.',
'Bad csrf hash' => 'Неверный CSRF хэш. Вы перешли на эту страницу из неавторизованного источника.',
'No cookie' => 'Вы вошли, но куки (cookie) не были установлены. Пожалуйста проверьте настройки браузера и, если возможно, разрешите куки для этого сайта.',
'Pun include extension' => 'Невозможно подключить файл %s из шаблона %s. Нет разрешения на подлючение файлов с расширением "%s"',
'Pun include directory' => 'Невозможно подключить файл %s из шаблона %s. Переключение директорий запрещено.',
'Pun include error' => 'Невозможно подключить файл %s из шаблона %s. Файл отсутствует и в шаблоне, и в директории пользователя.',

'Hidden text' => 'Скрытый текст',
'Show' => 'Показать',
'Hide' => 'Скрыть',

// Miscellaneous
'Announcement' => 'Объявление',
'Options' => 'Параметры',
'Submit' => 'Отправить',	// "name" of submit buttons
'Ban message' => 'Ваша учетная запись заблокирована.',
'Ban message 2' => 'Блокировка заканчивается',
'Ban message 3' => 'Причина блокирования:',
'Ban message 4' => 'Все возникшие вопросы отправляйте администратору форума по адресу',
'Never' => 'Нет',
'Today' => 'Сегодня',
'Yesterday' => 'Вчера',
'Info' => 'Информация',		// a common table header
'Go back' => 'Назад',
'Maintenance' => 'Обслуживание',
'Redirecting' => 'Перенаправление',
'Click redirect' => 'Кликните здесь, если вы не желаете ждать (или ваш браузер не поддерживает перенаправление).',
'on' => 'вкл.',		// as in "BBCode is on"
'off' => 'выкл.',
'Invalid email' => 'Указанный почтовый адрес неверен.',
'Required' => '(Обязательно)',
'required field' => 'необходимое поле в этой форме.',	// for javascript form validation
'Last post' => 'Последнее сообщение',
'by' => 'от',	// as in last post by some user
'New posts' => 'Новые&#160;сообщения',	// the link that leads to the first new post (use &#160; for spaces)
'New posts info' => 'Перейти к первому новому сообщению в теме.',	// the popup text for new posts links
'Username' => 'Имя',
'Password' => 'Пароль',
'Email' => 'E-mail',
'Send email' => 'Отправить письмо',
'Moderated by' => 'Модератор(ы):',
'Registered' => 'Дата регистрации',
'Subject' => 'Заголовок темы',
'Message' => 'Сообщение',
'Topic' => 'Тема',
'Forum' => 'Раздел',
'Posts' => 'Сообщений',
'Replies' => 'Ответов',
'Pages' => 'Страницы',
'Page' => 'Страница %s',
'BBCode' => 'BB-коды:',	// You probably shouldn't change this
'url tag' => 'Тег [url]:',
'img tag' => 'Тег [img]:',
'Smilies' => 'Смайлы:',
'and' => 'и',
'Image link' => 'картинка',	// This is displayed (i.e. <image>) instead of images when "Show images" is disabled in the profile
'wrote' => 'пишет:',	// For [quote]'s
'Mailer' => '%s',	// As in "MyForums Mailer" in the signature of outgoing e-mails
'Important information' => 'Важная информация',
'Write message legend' => 'Введите сообщение и нажмите Отправить',
'Previous' => 'Назад',
'Next' => 'Вперед',
'Spacer' => '&hellip;', // Ellipsis for paginate

// Title
'Title' => 'Титул',
'Member' => 'Участник',	// Default title
'Moderator' => 'Модератор',
'Administrator' => 'Администратор',
'Banned' => 'Забанен',
'Guest' => 'Гость',

// Stuff for include/parser.php
'BBCode error no opening tag' => 'Обнаружен парный тег [/%1$s] без соответствующего начального тега [%1$s]',
'BBCode error invalid nesting' => 'Тег [%1$s] открывается внутри [%2$s], это недопустимо',
'BBCode error invalid self-nesting' => 'Тег [%s] открывается внутри такого же тега, это недопустимо',
'BBCode error no closing tag' => 'Обнаружен парный тег [%1$s] без соответствующего закрывающего тега [/%1$s]',
'BBCode error empty attribute' => 'Тег [%s] с пустым атрибутом',
'BBCode error tag not allowed'		=>	'Вам нельзя использовать тег [%s]',
'BBCode error tag url not allowed'	=>	'Вам нельзя использовать ссылки в сообщениях',
'BBCode list size error' => 'Ваш список слишком велик, пожалуйста уменьшите его!',

// Stuff for the navigator (top of every page)
'Index' => 'Форум',
'User list' => 'Пользователи',
'Rules' => 'Правила',
'Search' => 'Поиск',
'Register' => 'Регистрация',
'Login' => 'Вход',
'Not logged in' => 'Вы не вошли.',
'Profile' => 'Профиль',
'Logout' => 'Выход',
'Logged in as' => 'Вы вошли как',
'Admin' => 'Админка',
'Last visit' => 'Последний визит: %s',
'Topic searches' => 'Темы:',
'New posts header' => 'Новые',
'Active topics' => 'Активные',
'Unanswered topics' => 'Без ответа',
'Posted topics' => 'С вашим участием',
'Show new posts' => 'Найти темы с новыми сообщениями.',
'Show active topics' => 'Найти темы с недавними сообщениями.',
'Show unanswered topics' => 'Найти темы без ответов.',
'Show posted topics' => 'Найти темы с вашим участием.',
'Mark all as read' => 'Отметить всё как прочтённое',
'Mark forum read' => 'Отметить раздел как прочтённый',
'Title separator' => ' / ',
'PM' => 'ЛС',
'PMsend' => 'Отправить личное сообщение',
'PMnew' => 'Новое личное сообщение',
'PMmess' => 'У вас есть новые личные сообщения (%s шт.).',

'Warn' => 'Предупреждение',
'WarnMess' => 'Вы получили новое предупреждение!',

// Stuff for the page footer
'Board footer' => 'Подвал форума',
'Jump to' => 'Перейти',
'Go' => ' Иди ',		// submit button in forum jump
'Moderate topic' => 'Модерирование темы',
'All' => 'All',
'Move topic' => 'Перенести тему',
'Open topic' => 'Открыть тему',
'Close topic' => 'Закрыть тему',
'Unstick topic' => 'Снять выделение темы',
'Stick topic' => 'Выделить тему',
'Moderate forum' => 'Модерирование раздела',
'Powered by' => 'Под управлением %s<br />Модифицировал &#x56;&#x69;&#x73;&#x6D;&#x61;&#x6E;',

// Debug information
'Debug table' => 'Отладочная информация',
'Querytime' => 'Сгенерировано за %1$s сек, %2$s запросов выполнено',
'Memory usage' => 'Использовано памяти: %1$s',
'Peak usage' => '(Пик: %1$s)',
'Query times' => 'Время (s)',
'Query' => 'Запрос',
'Total query time' => 'Итого: %s',

// For extern.php RSS feed
'RSS description' => 'Самые свежие темы на %s.',
'RSS description topic' => 'Самые свежие сообщения в %s.',
'RSS reply' => 'Re: ',	// The topic subject will be appended to this string (to signify a reply)
'RSS active topics feed' => 'RSS лента активных тем',
'Atom active topics feed' => 'Atom лента активных тем',
'RSS forum feed' => 'RSS лента раздела',
'Atom forum feed' => 'Atom лента раздела',
'RSS topic feed' => 'RSS лента темы',
'Atom topic feed' => 'Atom лента темы',

'After time' => 'Добавлено спустя',
'After time s' => ' с',
'After time i' => ' мин',
'After time H' => ' ч',
'After time d' => ' дн',

// Admin related stuff in the header
'New reports' => 'Есть новые сигналы',
'Maintenance mode enabled' => 'Включен режим обслуживания!',

// Units for file sizes
'Size unit B'						=>	'%s байт',
'Size unit KiB'						=>	'%s Кбайт',
'Size unit MiB'						=>	'%s Мбайт',
'Size unit GiB'						=>	'%s Гбайт',
'Size unit TiB'						=>	'%s Тбайт',
'Size unit PiB'						=>	'%s Пбайт',
'Size unit EiB'						=>	'%s Эбайт',

);
