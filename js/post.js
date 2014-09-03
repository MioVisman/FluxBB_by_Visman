// post.js v2.0.0 Copyright (C) 2014 Visman (visman@inbox.ru)

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
		} else {
			var list = node.all || node.getElementsByTagName('*');
			var result = [];
			for (var index = 0, elem; elem = list[index++];) {
				if (elem.className && (' ' + elem.className + ' ').indexOf(' ' + classname + ' ') > -1) {
					result[result.length] = elem;
				}
			}
			return result;
		}
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
		var html = "";
		for (var i = 0; i < FluxBB.vars.bbSmImg.length; i++) {
			html += "<img src=\"img/smilies/" + FluxBB.vars.bbSmImg[i] + "\" alt=\"" + FluxBB.vars.bbSmTxt[i] + "\" onclick=\"return FluxBB.post.insText('', ' " + FluxBB.vars.bbSmTxt[i].replace(/\\/g, '\\\\').replace(/&#039;/g, '\\\'') + " ');\" />"
		}
		return html;
	}
	
	function ColorMapBB() {
		var colors = [
		"#000000","#000033","#000066","#000099","#0000cc","#0000ff","#330000","#330033",
		"#330066","#330099","#3300cc","#3300ff","#660000","#660033","#660066","#660099",
		"#6600cc","#6600ff","#990000","#990033","#990066","#990099","#9900cc","#9900ff",
		"#cc0000","#cc0033","#cc0066","#cc0099","#cc00cc","#cc00ff","#ff0000","#ff0033",
		"#ff0066","#ff0099","#ff00cc","#ff00ff","#003300","#003333","#003366","#003399",
		"#0033cc","#0033ff","#333300","#333333","#333366","#333399","#3333cc","#3333ff",
		"#663300","#663333","#663366","#663399","#6633cc","#6633ff","#993300","#993333",
		"#993366","#993399","#9933cc","#9933ff","#cc3300","#cc3333","#cc3366","#cc3399",
		"#cc33cc","#cc33ff","#ff3300","#ff3333","#ff3366","#ff3399","#ff33cc","#ff33ff",
		"#006600","#006633","#006666","#006699","#0066cc","#0066ff","#336600","#336633",
		"#336666","#336699","#3366cc","#3366ff","#666600","#666633","#666666","#666699",
		"#6666cc","#6666ff","#996600","#996633","#996666","#996699","#9966cc","#9966ff",
		"#cc6600","#cc6633","#cc6666","#cc6699","#cc66cc","#cc66ff","#ff6600","#ff6633",
		"#ff6666","#ff6699","#ff66cc","#ff66ff","#009900","#009933","#009966","#009999",
		"#0099cc","#0099ff","#339900","#339933","#339966","#339999","#3399cc","#3399ff",
		"#669900","#669933","#669966","#669999","#6699cc","#6699ff","#999900","#999933",
		"#999966","#999999","#9999cc","#9999ff","#cc9900","#cc9933","#cc9966","#cc9999",
		"#cc99cc","#cc99ff","#ff9900","#ff9933","#ff9966","#ff9999","#ff99cc","#ff99ff",
		"#00cc00","#00cc33","#00cc66","#00cc99","#00cccc","#00ccff","#33cc00","#33cc33",
		"#33cc66","#33cc99","#33cccc","#33ccff","#66cc00","#66cc33","#66cc66","#66cc99",
		"#66cccc","#66ccff","#99cc00","#99cc33","#99cc66","#99cc99","#99cccc","#99ccff",
		"#cccc00","#cccc33","#cccc66","#cccc99","#cccccc","#ccccff","#ffcc00","#ffcc33",
		"#ffcc66","#ffcc99","#ffcccc","#ffccff","#00ff00","#00ff33","#00ff66","#00ff99",
		"#00ffcc","#00ffff","#33ff00","#33ff33","#33ff66","#33ff99","#33ffcc","#33ffff",
		"#66ff00","#66ff33","#66ff66","#66ff99","#66ffcc","#66ffff","#99ff00","#99ff33",
		"#99ff66","#99ff99","#99ffcc","#99ffff","#ccff00","#ccff33","#ccff66","#ccff99",
		"#ccffcc","#ccffff","#ffff00","#ffff33","#ffff66","#ffff99","#ffffcc","#ffffff"];
		var html = '<table class="tbl"><tr>';
		for (var i=0; i<colors.length; i++) {
			html += "<td style='background-color:" + colors[i] + "' onclick=\"return FluxBB.post.insText('[color=" + colors[i] + "]', '[/color]');\" onfocus=\"FluxBB.post.showMapColor('" + colors[i] +  "');\" onmouseover=\"FluxBB.post.showMapColor('" + colors[i] + "');\">"
			html += '</td>';
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
			if (typeof(textarea) === 'undefined') return false;

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
			  lang = {'b':'Полужирный текст', 'i':'Наклонный текст', 'u':'Подчеркнутый текст', 's':'Зачёркнутый текст', 'center':'По центру', 'right':'По правому краю', 'justify':'По ширине', 'mono':'Моношрифт', 'url':'Ссылка', 'email':'Электронная почта', 'img':'Картинка', 'list':'Список', '*':'Элемент списка', 'quote':'Цитата', 'code':'Блок кода', 'hr':'Горизонтальная линия', 'color':'Цвет текста', 'spoiler':'Скрытый текст', 'smileys':'Смайлы', 'upfiles':'Загрузки', 'QQ':'Цитировать', 'Loading':'Загрузка...', 'Must':'Вы должны выделить текст для цитирования'};
			} else {
			  lang = {'b':'Bold text', 'i':'Italic text', 'u':'Underlined text', 's':'Strike-through text', 'center':'Center', 'right':'Right', 'justify':'Justify', 'mono':'Mono', 'url':'Link', 'email':'E-mail', 'img':'Image', 'list':'List', '*':'List element', 'quote':'Quote', 'code':'Code block', 'hr':'Horizontal line', 'color':'Colour of text', 'spoiler':'Spoiler', 'smileys':'Smileys', 'upfiles':'Uploads', 'QQ':'Quote', 'Loading':'Loading...', 'Must':'You must select text before quoting'};
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
					if (typeof(dt) !== 'undefined') {
						var	a = dt.getElementsByTagName('a')[0];
						if (typeof(a) === 'undefined') a = dt.getElementsByTagName('strong')[0];
						var n = a.innerHTML;
						// Decode html special chars
						n = n.replace(/&lt;/g, '<');
						n = n.replace(/&gt;/g, '>');
						n = n.replace(/&quot;/g, '"');
						n = n.replace(/&#039;/g, '\'');
						n = n.replace(/&nbsp;/g, ' ');
						n = n.replace(/&#160;/g, ' ');
						nameusers[id] = n.replace(/&amp;/g, '&');
			      dt.innerHTML = '<strong><a href="#req_message" onclick="return FluxBB.post.insName(' + id + ');">@ </a></strong>' + dt.innerHTML;

						var quote = getCN('postquote', blockposts[i])[0];
						if (typeof(quote) !== 'undefined') {
							a = quote.getElementsByTagName('a')[0];
							p = quote.parentNode;
							p.innerHTML += '<li class="postquote"><span id="pq' + id + '"><a href="' + a.href.replace(/&/g, '&amp;') + '" onmousedown="FluxBB.post.getText();" onclick="return FluxBB.post.quote(' + id + ');">' + lang['QQ'] + '</a></span></li>';
						}
					}
				}
			}
			
			if (!!FluxBB.vars.bbFlagUp && !FluxBB.vars.bbGuest) {
				var all_ul = doc.getElementsByTagName("ul"),
						i = all_ul.length - 1;
				while (i > -1) {
					if (all_ul[i].className == "bblinks") {
						var ul_html = all_ul[i].innerHTML;
						ul_html += "<li><span><a href=\"upfiles.php\" onclick=\"return FluxBB.post.popUp(this.href);\"><strong>"+lang['upfiles']+"</strong></a></span></li>";
						all_ul[i].innerHTML = ul_html;
						i = 0;
					}
					i--;
				}
			}
		},

		insText : function (open, close) {
			get('bbcode_color_map').style.display = 'none';
			get('bbcode_smileys').style.display = 'none';
			textarea.focus();
			// IE support
			if (doc.selection && doc.selection.createRange) {
				sel = doc.selection.createRange();
				sel.text = open + sel.text + close;
			}
			// Moz support
			else if (textarea.selectionStart || textarea.selectionStart == '0') {
				var startPos = textarea.selectionStart;
				var endPos = textarea.selectionEnd;
				textarea.value = textarea.value.substring(0, startPos) + open + textarea.value.substring(startPos, endPos) + close + textarea.value.substring(endPos);
				if (startPos == endPos && open == '') {
					textarea.selectionStart = startPos + close.length;
					textarea.selectionEnd = endPos + close.length;
				} else {
					textarea.selectionStart = startPos + open.length;
					textarea.selectionEnd = endPos + open.length;
				}
			}
			// Fallback support for other browsers
			else {
				textarea.value += open + close;
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
		  if (typeof(id) !== 'number' || id < 1) return false;
			if (quote_text != "") {
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
					req.open("POST", 'pjq.php?' + id, true);
					req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
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
			win.open(url, 'gest', "top=" + t + ",left=" + l + ",width=" + w + ",height=" + h + ",resizable=yes,location=no,menubar=no,status=no,scrollbars=yes");
			return false;
		},
		
		overlay : function (prt, str) {
			var m = get(str);
			if (m.style.display != 'block') {

				if (str == 'bbcode_smileys') {
					get('bbcode_color_map').style.display = 'none';
					if (flag_sm) {
						flag_sm = false;
						m.innerHTML = SmileysMapBB();
					}
				}

				if (str == 'bbcode_color_map') {
					get('bbcode_smileys').style.display = 'none';
					if (flag_cr) {
						flag_cr = false;
						m.innerHTML = ColorMapBB();
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
			get("selectedMapColor").style.backgroundColor = color;
			get("selectedMapColorBox").value = color;
		}
	};
}(document, window));

if (typeof(jQuery) !== "undefined") {
	(function($){var textarea,staticOffset;var iLastMousePos=0;var iMin=64;var grip;$.fn.TextAreaResizer=function(){return this.each(function(){textarea=$(this).addClass('processed'),staticOffset=null;$(this).wrap('<div class="resizable-textarea"><span></span></div>').parent().append($('<div class="grippie"></div>').bind("mousedown",{el:this},startDrag));var grippie=$('div.grippie',$(this).parent())[0];grippie.style.marginRight=(grippie.offsetWidth-$(this)[0].offsetWidth)+'px'})};function startDrag(e){textarea=$(e.data.el);textarea.blur();iLastMousePos=mousePosition(e).y;staticOffset=textarea.height()-iLastMousePos;if(!window.ActiveXObject){textarea.css('opacity',0.25)}$(document).mousemove(performDrag).mouseup(endDrag);return false}function performDrag(e){var iThisMousePos=mousePosition(e).y;var iMousePos=staticOffset+iThisMousePos;if(iLastMousePos>=(iThisMousePos)){iMousePos-=5}iLastMousePos=iThisMousePos;iMousePos=Math.max(iMin,iMousePos);textarea.height(iMousePos+'px');if(iMousePos<iMin){endDrag(e)}return false}function endDrag(e){$(document).unbind('mousemove',performDrag).unbind('mouseup',endDrag);if(!window.ActiveXObject){textarea.css('opacity',1)}textarea.focus();textarea=null;staticOffset=null;iLastMousePos=0}function mousePosition(e){return{x:e.clientX+document.documentElement.scrollLeft,y:e.clientY+document.documentElement.scrollTop}}})(jQuery);
	$(document).ready(function() {$('textarea:not(.processed)').TextAreaResizer();});
}