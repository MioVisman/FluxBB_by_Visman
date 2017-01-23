// collapse.js v2.0.2 Copyright (C) 2014-2016 Visman (mio.visman@yandex.ru)
if (typeof FluxBB === 'undefined' || !FluxBB) {var FluxBB = {};}

FluxBB.collapse = (function (doc) {
	'use strict';

	var dd;

	function get(e) {
		return doc.getElementById(e);
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
	
	function setCookie(name, value, expires, path, domain, secure) {
		if (!name) return false;
		var str = FluxBB.vars.collapse_cookieid + name + '=' + encodeURIComponent(value);

		if (expires) str += '; expires=' + expires.toGMTString();
		if (path)    str += '; path=' + path;
		if (domain)  str += '; domain=' + domain;
		if (secure)  str += '; secure';

		doc.cookie = str;
		return true;
	}

	function getCookie(name) {
		if (!name) return false;
		name = (FluxBB.vars.collapse_cookieid + name).replace(/([\.\$\?\*\|\{\}\(\)\[\]\\\/\+\^])/g, '\\$1');
		var m = doc.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : false;
	}
	
	function getCSS(element, property) {
		return (typeof getComputedStyle === 'undefined' ? element.currentStyle : getComputedStyle(element, null))[property];
	}

	return {
		init: function () {
			var i, tmp, cur, saved, old = true, f = true,
					blocktables = getCN('blocktable', get('brdmain'));

			dd = new Date();
			dd.setFullYear(dd.getFullYear() + 1);

			for (i in blocktables) {
				cur = blocktables[i];
				if (cur.id) {
				  if (f) {
						if (getCSS(cur.getElementsByTagName('h2')[0], 'position') == 'absolute' || getCSS(cur.getElementsByTagName('thead')[0], 'display') == 'none')
						  old = false;
				    f = false;
					}
					var id = cur.id.replace('idx', '');
					if (old) {
						cur.getElementsByTagName('h2')[0].insertAdjacentHTML('afterBegin', '<span class="conr"><img src="' + FluxBB.vars.collapse_folder + 'exp_up.png" onclick="FluxBB.collapse.toggle(' + id + ')" alt="-" id="collapse_img_' + id + '" /></span>');
						getCN('box', cur)[0].setAttribute('id', 'collapse_box_' + id);
					} else {
						cur.getElementsByTagName('tbody')[0].setAttribute('id', 'collapse_box_' + id);
						var ths = cur.getElementsByTagName('thead')[0].getElementsByTagName('th'), th = ths[ths.length-1];
						th.insertAdjacentHTML('beforeEnd', '<span class="conr"><img src="' + FluxBB.vars.collapse_folder + 'exp_up.png" onclick="FluxBB.collapse.toggle(' + id + ')" alt="-" id="collapse_img_' + id + '" /></span>');
					}
				}
			}
			
			if (tmp = getCookie('collaps')) {
				saved = tmp.split(',');

				for(i = 0 ; i < saved.length; i++) {
					FluxBB.collapse.toggle(saved[i]);
				}
			}

		},
		
		toggle: function (id) {
			var saved, clean = [], i, tmp;

			if (tmp = getCookie('collaps')) {
				saved = tmp.split(',');

				for(i = 0 ; i < saved.length; i++) {
					if (saved[i] != id && saved[i] != '') {
						clean[clean.length] = saved[i];
					}
				}
			}

			if (get('collapse_box_'+id).style.display == '') {
				clean[clean.length] = id;
				get('collapse_box_'+id).style.display = 'none';
				get('collapse_img_'+id).src = get('collapse_img_'+id).src.replace('up','down');
				get('collapse_img_'+id).setAttribute('alt', '+');
			} else {
				get('collapse_box_'+id).style.display = '';
				get('collapse_img_'+id).src = get('collapse_img_'+id).src.replace('down','up');
				get('collapse_img_'+id).setAttribute('alt', '-');
			}

			if (clean.length == 0) {
				setCookie('collaps', null, new Date(0));
			} else {
				setCookie('collaps', clean.join(','), dd);
			}
		}
	};
}(document));
