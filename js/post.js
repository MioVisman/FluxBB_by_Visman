function getElementsByCN (classname, node)
{
	node = node || document;
	if (node.querySelectorAll)
	{
		return node.querySelectorAll('.' + classname);
	}
	else if (node.getElementsByClassName)
	{
		return node.getElementsByClassName(classname);
	}
	else
	{
		var list = node.all || node.getElementsByTagName('*');
		var result = [];
		for (var index = 0, elem; elem = list[index++];)
		{
			if (elem.className && (' ' + elem.className + ' ').indexOf(' ' + classname + ' ') > -1)
			{
				result[result.length] = elem;
			}
		}
		return result;
	}
}

function insert_text(open, close)
{

	var msgfield = document.getElementsByName('req_message')[0];
	msgfield.focus();
	// IE support
	if (document.selection && document.selection.createRange)
	{
		sel = document.selection.createRange();
		sel.text = open + sel.text + close;
	}
	// Moz support
	else if (msgfield.selectionStart || msgfield.selectionStart == '0')
	{
		var startPos = msgfield.selectionStart;
		var endPos = msgfield.selectionEnd;
		msgfield.value = msgfield.value.substring(0, startPos) + open + msgfield.value.substring(startPos, endPos) + close + msgfield.value.substring(endPos, msgfield.value.length);
		if (startPos == endPos && open == '')
		{
			msgfield.selectionStart = startPos + close.length;
			msgfield.selectionEnd = endPos + close.length;
		}
		else
		{
			msgfield.selectionStart = startPos + open.length;
			msgfield.selectionEnd = endPos + open.length;
		}
	}
	// Fallback support for other browsers
	else
	{
		msgfield.value += open + close;
	}
	msgfield.focus();
	return false;
}

function insert_name(id)
{
	return insert_text('','[b]@' + nameusers[id] + '[/b], ');
}

function get_quote_text()
{
	//IE
	if (document.selection && document.selection.createRange()) {quote_text = document.selection.createRange().text;}
	//NS,FF,SM
	if (document.getSelection) {quote_text = document.getSelection();}
}

function Quote(mmid)
{
	if (quote_text!='')
	{
		var endq = '[quote="' + nameusers[mmid] + '"]\n' + quote_text + '\n[/quote]\n';
		insert_text('',endq);
	}
	else if (typeof(jQuery) != 'undefined' && mmid != 'undefined' && typeof(mmid) == 'number' && mmid > 0 && apq['Guest'] != '1')
	{
		if (apq_id != -1)
		{
			$('#pq' + apq_id).html(apq_temp);
		}
		apq_user = nameusers[mmid];
		apq_id = mmid;
		apq_temp = $('#pq' + apq_id).html();
		$('#pq' + apq_id).html('<img src="img/loading.gif" />&#160;<a href="#">' + apq['Loading'] + '</a>');

		if (apq['Flag'] == 'Topic')
		{
			var values = {
				action: 'quote',
				id: mmid
			};
		}
		else if (apq['Flag'] == 'PM')
		{
			var values = {
				action: 'pmquote',
				id: mmid
			};
		}
		else
		{
			var values = new Array(1);
		}
		$.post('pjq.php?' + mmid, values, function(data) {apq_ready(data)});
	}
	else
	{
		alert(apq['Must']);
	}
	return false;
}

function apq_ready(data)
{
	if (apq_id != -1)
	{
		$('#pq' + apq_id).html(apq_temp);
		apq_id = -1;
	}
	var quote_message = match(data, 'quote_post');
	if (quote_message != '')
	{
		var endq = '[quote="' + apq_user + '"]\n' + quote_message + '\n[/quote]\n';
		insert_text('',endq);
		return 1;
	}
	alert(data);
}

function match(str, substr)
{
	if (str.indexOf('<' + substr + '>') != -1)
	{
		var newstr = str.substring(str.indexOf('<' + substr + '>') + substr.length+2);
		newstr = newstr.substring(0, newstr.indexOf('</' + substr + '>'));
		return newstr;
	}
	else
		return '';
}

function getposOffset(overlay, offsettype)
{
	var totaloffset=(offsettype=='left')? overlay.offsetLeft : overlay.offsetTop;
	var parentEl=overlay.offsetParent;
	totaloffset=(offsettype=='left')? totaloffset+parentEl.offsetLeft : totaloffset+parentEl.offsetTop;
	return totaloffset;
}

function overlay(curobj, subobjstr, opt_position)
{
	if (document.getElementById) {
		var subobj=document.getElementById(subobjstr);
		if (subobjstr == 'bbcode_smileys' && bbcode_sm_vis) {
			bbcode_sm_vis = false;
			if (subobj) subobj.innerHTML = SmileysMapBB();
		}
		if (subobjstr == 'bbcode_color_map' && bbcode_cr_vis) {
			bbcode_cr_vis = false;
			if (subobj) subobj.innerHTML = ColorMapBB();
			subobj.style.overflow = 'hidden';
		}
		if (subobj.style.display!='block') {
			subobj.style.display='block';
			subobj.style.position='absolute';
//			subobj.zIndex=99;
			var x = getposOffset(curobj, 'left');
			var y = getposOffset(curobj, 'top');
			var xpos=x+((typeof opt_position!='undefined' && opt_position.indexOf('right')!=-1)? -(subobj.offsetWidth-curobj.offsetWidth)/2 : 0); 
			var ypos=y+((typeof opt_position!='undefined' && opt_position.indexOf('bottom')!=-1)? curobj.offsetHeight : 0);
			subobj.style.left=xpos+'px';
			subobj.style.top=ypos+'px';
		} else
			subobj.style.display='none';

		return false;
	}
	else
		return true;
}

function overlayclose(subobj)
{
	document.getElementById(subobj).style.display='none';
}

function showMapColor(color)
{
	document.getElementById("selectedMapColor").style.backgroundColor = color;
	document.getElementById("selectedMapColorBox").value = color;
}

function ColorMapBB()
{
	var colors = new Array(
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
		"#ccffcc","#ccffff","#ffff00","#ffff33","#ffff66","#ffff99","#ffffcc","#ffffff"
	);
	var html = '<table class="tbl"><tr>';
	for (var i=0; i<colors.length; i++) {
		html += "<td style='background-color:" + colors[i] + "' onclick=\"return insert_text('[color=" + colors[i] + "]', '[/color]');\" onfocus=\"showMapColor('" + colors[i] +  "');\" onmouseover=\"showMapColor('" + colors[i] + "');\">"
		html += '</td>';
		if ((i+1) % 18 == 0)	html += '</tr><tr>';
	}
	html += '<td colspan="9" id="selectedMapColor" height="16"></td>'
	+ '<td colspan="9">'
	+ '<input id="selectedMapColorBox" name="selectedMapColorBox" type="text" size="7" maxlength="7" style="text-align:center;font-weight:bold;width:90px;border:1px solid;" value="" />'
	+ '</td></tr></table>';
	return html;
}

function SmileysMapBB()
{
	var html = "";
	for (var i=0; i<bbcode_sm_img.length; i++) {
		html += "<img src=\"img/smilies/" + bbcode_sm_img[i] + "\" alt=\"" + bbcode_sm_txt[i] + "\" onclick=\"return insert_text('',' " + bbcode_sm_txt[i].replace(/\\/g, '\\\\').replace(/&#039;/g, '\\\'') + " ');\" />"
	}
	return html;
}

function ForumBBSet()
{
	bbcode_sm_vis = true;
	bbcode_cr_vis = true;

	var textarea = document.getElementsByName('req_message')[0];
	if (typeof(textarea) == 'undefined') return false;

	var div = document.createElement('div');
	div.setAttribute('id', 'bbcode_bar');

	div.innerHTML = '<div id="bbcodewrapper"><div id="bbcodebuttons">'
	+'<img src="'+bbcode_l['btndir']+'b.png" alt="[b]" title="'+bbcode_l['b']+'" onclick="return insert_text(\'[b]\',\'[/b]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'i.png" alt="[i]" title="'+bbcode_l['i']+'" onclick="return insert_text(\'[i]\',\'[/i]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'u.png" alt="[u]" title="'+bbcode_l['u']+'" onclick="return insert_text(\'[u]\',\'[/u]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'s.png" alt="[s]" title="'+bbcode_l['s']+'" onclick="return insert_text(\'[s]\',\'[/s]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'spacer.png" alt="|" />'
	+'<img src="'+bbcode_l['btndir']+'center.png" alt="[center]" title="'+bbcode_l['center']+'" onclick="return insert_text(\'[center]\',\'[/center]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'right.png" alt="[right]" title="'+bbcode_l['right']+'" onclick="return insert_text(\'[right]\',\'[/right]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'justify.png" alt="[justify]" title="'+bbcode_l['justify']+'" onclick="return insert_text(\'[justify]\',\'[/justify]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'mono.png" alt="[mono]" title="'+bbcode_l['mono']+'" onclick="return insert_text(\'[mono]\',\'[/mono]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'spacer.png" alt="|" />'
	+'<img src="'+bbcode_l['btndir']+'url.png" alt="[url]" title="'+bbcode_l['url']+'" onclick="return insert_text(\'[url]\',\'[/url]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'email.png" alt="[email]" title="'+bbcode_l['email']+'" onclick="return insert_text(\'[email]\',\'[/email]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'img.png" alt="[img]" title="'+bbcode_l['img']+'" onclick="return insert_text(\'[img]\',\'[/img]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'spacer.png" alt="|" />'
	+'<img src="'+bbcode_l['btndir']+'list.png" alt="[list]" title="'+bbcode_l['list']+'" onclick="return insert_text(\'[list]\',\'[/list]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'li.png" alt="[*]" title="'+bbcode_l['*']+'" onclick="return insert_text(\'[*]\',\'[/*]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'spacer.png" alt="|" />'
	+'<img src="'+bbcode_l['btndir']+'quote.png" alt="[quote]" title="'+bbcode_l['quote']+'" onclick="return insert_text(\'[quote]\',\'[/quote]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'code.png" alt="[code]" title="'+bbcode_l['code']+'" onclick="return insert_text(\'[code]\',\'[/code]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'hr.png" alt="[hr]" title="'+bbcode_l['hr']+'" onclick="return insert_text(\'\',\'[hr]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'color.png" alt="[color=]" title="'+bbcode_l['color']+'" onclick="return overlay(this,\'bbcode_color_map\',\'rightbottom\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'sp.png" alt="[spoiler]" title="'+bbcode_l['spoiler']+'" onclick="return insert_text(\'[spoiler]\',\'[/spoiler]\');" tabindex="'+(cur_index++)+'" />'
	+'<img src="'+bbcode_l['btndir']+'spacer.png" alt="|" />'
	+'<img src="'+bbcode_l['btndir']+'smile.png" alt="smile" title="'+bbcode_l['smileys']+'" onclick="return overlay(this,\'bbcode_smileys\',\'rightbottom\');" tabindex="'+(cur_index++)+'" />'
	+'</div></div>'
	+'<div class="clearer"></div>'
	+'<div id="bbcode_color_map" onclick="overlayclose(\'bbcode_color_map\');"></div>'
	+'<div id="bbcode_smileys" onclick="overlayclose(\'bbcode_smileys\');"></div>';

	var p = textarea.parentNode;
	p.insertBefore(div,textarea);

	var blockposts = getElementsByCN('blockpost');
	for (i in blockposts)
	{
		if (blockposts[i].id)
		{
			var id = blockposts[i].id.replace('p', '');
			var dt = blockposts[i].getElementsByTagName('dt')[0];
			if (typeof(dt) != 'undefined')
			{
				var	a = dt.getElementsByTagName('a')[0];
				if (typeof(a) == 'undefined') a = dt.getElementsByTagName('strong')[0];
				var n = a.innerHTML;
				// Decode html special chars
				n = n.replace(/&lt;/g, '<');
				n = n.replace(/&gt;/g, '>');
				n = n.replace(/&quot;/g, '"');
				n = n.replace(/&#039;/g, '\'');
				n = n.replace(/&nbsp;/g, ' ');
				n = n.replace(/&#160;/g, ' ');
				nameusers[id] = n.replace(/&amp;/g, '&');
	      dt.innerHTML = '<strong><a href="#req_message" onclick="return insert_name(' + id + ')">@ </a></strong>' + dt.innerHTML;
      
				var quote = getElementsByCN('postquote', blockposts[i])[0];
				if (typeof(quote) != 'undefined')
				{
					a = quote.getElementsByTagName('a')[0];
					p = quote.parentNode;
					p.innerHTML += '<li class="postquote"><span id="pq' + id + '"><a href="' + a.href.replace(/&/g, '&amp;') + '" onmousedown="get_quote_text()" onclick="Quote(' + id + '); return false;">' + bbcode_l['QQ'] + '</a></span></li>';
				}
			}
		}
	}

}

function ForumPopUp(c,d,a,b,e){window.open(c,d,"top="+(screen.height-b)/3+", left="+(screen.width-a)/2+", width="+a+", height="+b+", "+e);return false};

function ForumUpSet(){var all_ul=document.getElementsByTagName("ul"),i=all_ul.length-1;while (i>-1){if(all_ul[i].className=="bblinks"){var ul_html=all_ul[i].innerHTML;ul_html+="<li><span><a href=\"upfiles.php\" onclick=\"return ForumPopUp(this.href,'gest','820','430','resizable=yes,location=no,menubar=no,status=no,scrollbars=yes');\"><strong>"+bbcode_l['upfiles']+"</strong></a></span></li>";all_ul[i].innerHTML=ul_html;i=0;}i--}};

if (typeof(jQuery) != "undefined") {
	(function($){var textarea,staticOffset;var iLastMousePos=0;var iMin=64;var grip;$.fn.TextAreaResizer=function(){return this.each(function(){textarea=$(this).addClass('processed'),staticOffset=null;$(this).wrap('<div class="resizable-textarea"><span></span></div>').parent().append($('<div class="grippie"></div>').bind("mousedown",{el:this},startDrag));var grippie=$('div.grippie',$(this).parent())[0];grippie.style.marginRight=(grippie.offsetWidth-$(this)[0].offsetWidth)+'px'})};function startDrag(e){textarea=$(e.data.el);textarea.blur();iLastMousePos=mousePosition(e).y;staticOffset=textarea.height()-iLastMousePos;if(!window.ActiveXObject){textarea.css('opacity',0.25)}$(document).mousemove(performDrag).mouseup(endDrag);return false}function performDrag(e){var iThisMousePos=mousePosition(e).y;var iMousePos=staticOffset+iThisMousePos;if(iLastMousePos>=(iThisMousePos)){iMousePos-=5}iLastMousePos=iThisMousePos;iMousePos=Math.max(iMin,iMousePos);textarea.height(iMousePos+'px');if(iMousePos<iMin){endDrag(e)}return false}function endDrag(e){$(document).unbind('mousemove',performDrag).unbind('mouseup',endDrag);if(!window.ActiveXObject){textarea.css('opacity',1)}textarea.focus();textarea=null;staticOffset=null;iLastMousePos=0}function mousePosition(e){return{x:e.clientX+document.documentElement.scrollLeft,y:e.clientY+document.documentElement.scrollTop}}})(jQuery);
	$(document).ready(function() {$('textarea:not(.processed)').TextAreaResizer();});
}

var quote_text = '', apq_id = -1 , apq, apq_user, apq_temp, bbcode_l, bbcode_sm_vis, bbcode_cr_vis, cur_index, nameusers = [];
