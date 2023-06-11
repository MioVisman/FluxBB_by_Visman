// post.js v2.3.0 Copyright (C) 2014-2023 Visman (mio.visman@yandex.ru)
if (typeof FluxBB === 'undefined' || !FluxBB) {var FluxBB = {};}

FluxBB.post = (function (doc, win) {
	'use strict';

	var nameusers = [],
			bbcode = [],
			lang = [],
			fls = false,
			typepost = (/pmsnew/.test(doc.location.pathname) ? 'pmquote' : 'quote'),
			quote_text = '',
			apq_id = -1,
			apq_user,
			apq_temp,
			textarea,
			flag_sm = true,
			flag_cr = true;

	function get(elem) {
		return doc.getElementById(elem);
	}

	function getCN(classname, node) {
		node = node || doc;
		if (node.querySelectorAll) {
			return node.querySelectorAll('.' + classname);
		} else if (node.getElementsByClassName) {
			return node.getElementsByClassName(classname);
		}
		return [];
	}

	function createElement(elem) {
		return (doc.createElementNS) ? doc.createElementNS('http://www.w3.org/1999/xhtml', elem) : doc.createElement(elem);
	}

	function match(str, substr) {
		if (str.indexOf('<' + substr + '>') != -1) {
			var newstr = str.substring(str.indexOf('<' + substr + '>') + substr.length + 2);
			return newstr.substring(0, newstr.indexOf('</' + substr + '>'));
		} else return '';
	}

	function cr_req() {
		if (win.XMLHttpRequest) {
			return new XMLHttpRequest();
		} else {
			try {
				return new ActiveXObject('Microsoft.XMLHTTP');
			} catch (e){}
		}
		return !1;
	}

	function check_apq () {
		if (apq_id != -1)	{
			get('pq' + apq_id).innerHTML = apq_temp;
			apq_id = -1;
		}
	}

	function orsc(req) {
		if (req.readyState == 4) {
			check_apq();
		  if (req.status == 200) {
				var quote_message = match(req.responseText, 'quote_post');
				if (quote_message != '') {
					return FluxBB.post.insText('', '[quote="' + apq_user + '"]\n' + quote_message + '\n[/quote]\n');
				}
				alert(req.responseText);
			} else alert('Error: ' + req.status);
		}
	}

	function SmileysMapBB() {
		var html = '';
		for (var i = 0; i < FluxBB.vars.bbSmImg.length; i++) {
			html += '<img src="img/smilies/' + FluxBB.vars.bbSmImg[i] + '" alt="' + FluxBB.vars.bbSmTxt[i] + '" onclick="return FluxBB.post.insText(\'\', \' ' + FluxBB.vars.bbSmTxt[i].replace(/\\/g, '\\\\').replace(/&#039;/g, '\\\'') + ' \');" />';
		}
		return html;
	}

	function ColorMapBB() {
		var colors = [], a = ['00', '33', '66', '99', 'cc', 'ff'];
		for (var x = 0; x < 6; x++) {
			for (var y = 0; y < 6; y++) {
				for (var z = 0; z < 6; z++) {
					colors.push('#' +  a[y] + a[x] + a[z]);
				}
			}
		}
		var html = '<table class="tbl"><tr>';
		for (var i=0; i<colors.length; i++) {
			html += '<td style="background-color:' + colors[i] + '" onclick="return FluxBB.post.insText(\'[color=' + colors[i] + ']\', \'[/color]\');" onfocus="FluxBB.post.showMapColor(\'' + colors[i] +  '\');" onmouseover="FluxBB.post.showMapColor(\'' + colors[i] + '\');"></td>';
			if ((i+1) % 18 == 0)	html += '</tr><tr>';
		}
		html += '<td colspan="9" id="selectedMapColor" height="16"></td>'
		+ '<td colspan="9">'
		+ '<input id="selectedMapColorBox" name="selectedMapColorBox" type="text" size="7" maxlength="7" style="text-align:center;font-weight:bold;width:90px;border:1px solid;" value="" />'
		+ '</td></tr></table>';
		return html;
	}
//*********************//
	return {
		init : function () {
			if (fls) return false;
			fls = true;

			textarea = doc.getElementsByName('req_message')[0];
			if (typeof textarea === 'undefined') return false;

			bbcode = [{i:'b.png', a:'[b]', s:'[b]', e:'[/b]'},
				{i:'i.png', a:'[i]', s:'[i]', e:'[/i]'},
				{i:'u.png', a:'[u]', s:'[u]', e:'[/u]'},
				{i:'s.png', a:'[s]', s:'[s]', e:'[/s]'},
				{i:'spacer.png', a:'|'},
				{i:'center.png', a:'[center]', s:'[center]', e:'[/center]'},
				{i:'right.png', a:'[right]', s:'[right]', e:'[/right]'},
				{i:'justify.png', a:'[justify]', s:'[justify]', e:'[/justify]'},
				{i:'mono.png', a:'[mono]', s:'[mono]', e:'[/mono]'},
				{i:'spacer.png', a:'|'},
				{i:'url.png', a:'[url]', s:'[url]', e:'[/url]'},
				{i:'email.png', a:'[email]', s:'[email]', e:'[/email]'},
				{i:'img.png', a:'[img]', s:'[img]', e:'[/img]'},
				{i:'spacer.png', a:'|'},
				{i:'list.png', a:'[list]', s:'[list]', e:'[/list]'},
				{i:'li.png', a:'[*]', s:'[*]', e:'[/*]'},
				{i:'spacer.png', a:'|'},
				{i:'quote.png', a:'[quote]', s:'[quote]', e:'[/quote]'},
				{i:'code.png', a:'[code]', s:'[code]', e:'[/code]'},
				{i:'hr.png', a:'[hr]', s:'', e:'[hr]'},
				{i:'color.png', a:'[color=]', f:'return FluxBB.post.overlay(this, \'bbcode_color_map\');'},
				{i:'sp.png', a:'[spoiler]', s:'[spoiler]', e:'[/spoiler]'},
				{i:'spacer.png', a:'|'},
				{i:'smile.png', a:'smileys', f:'return FluxBB.post.overlay(this, \'bbcode_smileys\');'}];

			if (doc.getElementsByTagName('html')[0].getAttribute('lang') == 'ru') {
			  lang = {'b':'Полужирный текст', 'i':'Наклонный текст', 'u':'Подчеркнутый текст', 's':'Зачёркнутый текст', 'center':'По центру', 'right':'По правому краю', 'justify':'По ширине', 'mono':'Моношрифт', 'url':'Ссылка', 'email':'Электронная почта', 'img':'Картинка', 'list':'Список', '*':'Элемент списка', 'quote':'Цитата', 'code':'Блок кода', 'hr':'Горизонтальная линия', 'color':'Цвет текста', 'spoiler':'Скрытый текст', 'smileys':'Смайлы', 'QQ':'Цитировать', 'Loading':'Загрузка...', 'Must':'Вы должны выделить текст для цитирования'};
			} else {
			  lang = {'b':'Bold text', 'i':'Italic text', 'u':'Underlined text', 's':'Strike-through text', 'center':'Center', 'right':'Right', 'justify':'Justify', 'mono':'Mono', 'url':'Link', 'email':'E-mail', 'img':'Image', 'list':'List', '*':'List element', 'quote':'Quote', 'code':'Code block', 'hr':'Horizontal line', 'color':'Colour of text', 'spoiler':'Spoiler', 'smileys':'Smileys', 'QQ':'Quote', 'Loading':'Loading...', 'Must':'You must select text before quoting'};
			}

			var div = createElement('div');
			div.setAttribute('id', 'bbcode_bar');

			var t = '<div id="bbcodewrapper"><div id="bbcodebuttons">';
			for (var i in bbcode) {
				var b = bbcode[i];
				t = t + '<img src="' + FluxBB.vars.bbDir + b.i + '" alt="' + b.a + '" ';
				var p = b.a.replace(/[\[\]\|\=]/g, '');
				if (!!p) t = t + 'title="' + lang[p] + '" ';
				if (!!b.f) {
					t = t + 'onclick="' + b.f + '" tabindex="' + (FluxBB.vars.bbCIndex++) + '" ';
				} else if (!!b.s || !!b.e) {
					t = t + 'onclick="return FluxBB.post.insText(\'' + b.s + '\', \'' + b.e + '\');" tabindex="' + (FluxBB.vars.bbCIndex++) + '" ';
				}
				t = t + '/>';
			}
			div.innerHTML = t + '</div></div>'
			+ '<div class="clearer"></div>'
			+ '<div id="bbcode_color_map" onclick="this.style.display=\'none\';"></div>'
			+ '<div id="bbcode_smileys" onclick="this.style.display=\'none\';"></div>';

			var p = textarea.parentNode;
			p.insertBefore(div, textarea);

			var blockposts = getCN('blockpost');
			for (var i in blockposts) {
				if (blockposts[i].id) {
					var id = blockposts[i].id.replace('p', '');
					var dt = blockposts[i].getElementsByTagName('dt')[0];
					if (typeof dt !== 'undefined') {
						// Decode html special chars
						nameusers[id] = dt.textContent;
						dt.insertAdjacentHTML('afterBegin', '<strong><a href="#req_message" onclick="return FluxBB.post.insName(' + id + ');">@ </a></strong>');

						var quote = getCN('postquote', blockposts[i])[0];
						if (typeof quote !== 'undefined') {
							var a = quote.getElementsByTagName('a')[0];
							p = quote.parentNode;
							p.insertAdjacentHTML('beforeEnd', '<li class="postquote"><span id="pq' + id + '"><a href="' + a.href.replace(/&/g, '&amp;') + '" onmousedown="FluxBB.post.getText();" onclick="return FluxBB.post.quote(' + id + ');">' + lang['QQ'] + '</a></span></li>');
						}
					}
				}
			}
		},

		insText : function (open, close) {
			get('bbcode_color_map').style.display = 'none';
			get('bbcode_smileys').style.display = 'none';
			textarea.focus();
			// all and IE9+
			if ('selectionStart' in textarea) {
				var len = textarea.value.length,
					sp = Math.min(textarea.selectionStart, len), // IE bug
					ep = Math.min(textarea.selectionEnd, len); // IE bug

				textarea.value = textarea.value.substring(0, sp) + open + textarea.value.substring(sp, ep) + close + textarea.value.substring(ep);
				if (sp == ep && open == '') {
					textarea.selectionStart = sp + close.length;
					textarea.selectionEnd = ep + close.length;
				} else {
					textarea.selectionStart = sp + open.length;
					textarea.selectionEnd = ep + open.length;
				}
			}
			// IE9-
			else if (doc.selection && doc.selection.createRange) {
				var sel = doc.selection.createRange();
				sel.text = open + sel.text + close;
			}
			textarea.focus();
			return false;
		},

		insName: function (id) {
			return FluxBB.post.insText('', '[b]@' + nameusers[id] + '[/b], ');
		},

		getText: function () {
			if (win.getSelection) quote_text = win.getSelection().toString();
			else if (doc.selection && doc.selection.createRange) quote_text = doc.selection.createRange().text;
		},

		quote: function (id) {
			if (typeof id !== 'number' || id < 1) return false;
			if (quote_text != '') {
				return FluxBB.post.insText('', '[quote="' + nameusers[id] + '"]\n' + quote_text + '\n[/quote]\n');
			} else if (!FluxBB.vars.bbGuest){
				check_apq();
				var req = cr_req();
				if (req) {
					apq_user = nameusers[id];
					apq_id = id;
					apq_temp = get('pq' + apq_id).innerHTML;
					get('pq' + apq_id).innerHTML = '<img src="img/loading.gif" />&#160;<a href="#">' + lang['Loading'] + '</a>';

					req.onreadystatechange=function(){orsc(req);};
					req.open('POST', 'pjq.php?' + id, true);
					req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					req.send('action=' + typepost + '&id=' + id);
				}
			} else {
				alert(lang['Must']);
			}
			return false;
		},

		popUp : function (url) {
		  var h = Math.min(430, screen.height),
		  w = Math.min(820, screen.width),
			t = Math.max((screen.height - h) / 3, 0),
			l = (screen.width - w) / 2;
			win.open(url, 'gest', 'top=' + t + ',left=' + l + ',width=' + w + ',height=' + h + ',resizable=yes,location=no,menubar=no,status=no,scrollbars=yes');
			return false;
		},

		overlay : function (prt, str) {
			var m = get(str);
			if (m.style.display != 'block') {

				if (str == 'bbcode_smileys') {
					get('bbcode_color_map').style.display = 'none';
					if (flag_sm) {
						flag_sm = false;
						m.insertAdjacentHTML('afterBegin', SmileysMapBB());
					}
				}

				if (str == 'bbcode_color_map') {
					get('bbcode_smileys').style.display = 'none';
					if (flag_cr) {
						flag_cr = false;
						m.insertAdjacentHTML('afterBegin', ColorMapBB());
						m.style.overflow = 'hidden';
					}
				}

				m.style.display = 'block';
				m.style.position = 'absolute';
				m.style.left = Math.max(0, Math.min(textarea.offsetLeft + textarea.offsetParent.offsetLeft + textarea.offsetWidth - m.offsetWidth, prt.offsetLeft + prt.offsetParent.offsetLeft - (m.offsetWidth - prt.offsetWidth) / 2)) + 'px';
				m.style.top = prt.offsetTop + prt.offsetParent.offsetTop + prt.offsetHeight + 'px';
			} else {
				m.style.display = 'none';
			}

			return false;
		},

		showMapColor : function (color) {
			get('selectedMapColor').style.backgroundColor = color;
			get('selectedMapColorBox').value = color;
		}
	};
}(document, window));

if (typeof jQuery !== 'undefined') {
	(function($){var textarea,staticOffset;var iLastMousePos=0;var iMin=64;var grip;$.fn.TextAreaResizer=function(){return this.each(function(){textarea=$(this).addClass('processed'),staticOffset=null;$(this).wrap('<div class="resizable-textarea"><span></span></div>').parent().append($('<div class="grippie"></div>').bind("mousedown",{el:this},startDrag));var grippie=$('div.grippie',$(this).parent())[0];grippie.style.marginRight=(grippie.offsetWidth-$(this)[0].offsetWidth)+'px'})};function startDrag(e){textarea=$(e.data.el);textarea.blur();iLastMousePos=mousePosition(e).y;staticOffset=textarea.height()-iLastMousePos;if(!window.ActiveXObject){textarea.css('opacity',0.25)}$(document).mousemove(performDrag).mouseup(endDrag);return false}function performDrag(e){var iThisMousePos=mousePosition(e).y;var iMousePos=staticOffset+iThisMousePos;if(iLastMousePos>=(iThisMousePos)){iMousePos-=5}iLastMousePos=iThisMousePos;iMousePos=Math.max(iMin,iMousePos);textarea.height(iMousePos+'px');if(iMousePos<iMin){endDrag(e)}return false}function endDrag(e){$(document).unbind('mousemove',performDrag).unbind('mouseup',endDrag);if(!window.ActiveXObject){textarea.css('opacity',1)}textarea.focus();textarea=null;staticOffset=null;iLastMousePos=0}function mousePosition(e){return{x:e.clientX+document.documentElement.scrollLeft,y:e.clientY+document.documentElement.scrollTop}}})(jQuery);
	$(document).ready(function() {$('textarea:not(.processed)').TextAreaResizer();});
}
