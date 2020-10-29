// upload.js v3.1.0 Copyright (C) 2020 Visman (mio.visman@yandex.ru)
if (typeof FluxBB === 'undefined' || !FluxBB) {var FluxBB = {};}

FluxBB.upload = (function (doc, win) {
	'use strict';

	var state = 0,
		anchor,
		files = {},
		page = 0,
		pages = 1,
		textarea;

	function get(elem) {
		return doc.getElementById(elem);
	}

	function newXhr() {
		if (typeof XMLHttpRequest === 'undefined') {
			try {
				return new ActiveXObject('Microsoft.XMLHTTP');
			} catch (e) {}
		} else {
			return new XMLHttpRequest();
		}
		return false;
	}

	function createStartLink(ul) {
		var a = doc.createElement('a'),
			span = doc.createElement('span'),
			li = doc.createElement('li');
		a.innerHTML = FluxBB.uploadvars.lang.upfiles;
		a.href = FluxBB.uploadvars.action;
		span.appendChild(a);
		li.appendChild(span);
		ul.appendChild(li);
		return a;
	}

	function findAnchor(node) {
		while (node) {
			if ('FIELDSET' === node.tagName) {
				anchor = node.parentNode;
				return true;
			}
			node = node.parentNode;
		}
		return false;
	}

	function popUp(url) {
		var h = Math.min(430, screen.height),
			w = Math.min(820, screen.width),
			t = Math.max((screen.height - h) / 3, 0),
			l = (screen.width - w) / 2;
		win.open(url, 'gest', 'top=' + t + ',left=' + l + ',width=' + w + ',height=' + h + ',resizable=yes,location=no,menubar=no,status=no,scrollbars=yes');
	}

	function insertAfter(newNode, node) {
		if (node.parentNode.lastChild === node) {
			return node.parentNode.appendChild(newNode);
		} else {
			return node.parentNode.insertBefore(newNode, node.nextSibling);
		}
	}

	function setInput(name, value, type) {
		var input = doc.createElement('input');
		input.type = type || 'hidden';
		input.name = name;
		input.value = value;
		return input;
	}

	function initLoader() {
		var style = doc.createElement('link'),
			head = doc.querySelector('head');
		style.href = FluxBB.uploadvars.style;
		style.rel = 'stylesheet';
		style.type = 'text/css';
		head.appendChild(style);

		var tmp = get('upf-template').children;
		while (tmp[0]) {
			anchor = insertAfter(tmp[0], anchor);
		}

		var form = doc.createElement('form');
		form.id = 'upf-dataform';
		var div = doc.createElement('div');
		form.appendChild(div);

		var input = setInput('upfile', '', 'file');
		input.id = 'upfile';
		div.appendChild(input);
		div.appendChild(setInput('csrf_hash', FluxBB.uploadvars.token));
		div.appendChild(setInput('ajx', '1'));
		div.appendChild(setInput('action', 'upload'));
		get('upf-template').appendChild(form);

		get('upf-button').addEventListener('click', FluxBB.upload.buttonHandler, false);
		input.addEventListener('change', FluxBB.upload.changeHandler, false);

		files['-'] = {link: get('upf--')};
		loadFileData();
	}

	function postData(data, successHandler, errorHandler) {
		var xhr = newXhr();
		if (!xhr) {
			errorHandler && errorHandler(0, 'XMLHttpRequest not working');
			return;
		}
		xhr.open('POST', FluxBB.uploadvars.action, true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState == 4) {
				if (xhr.status == 200) {
					var data = xhr.responseText;
					if (typeof data === 'string') {
						try {
							data = JSON.parse(data);
						} catch (e) {
							errorHandler && errorHandler(0, e.message);
							return;
						}
					}
					if ('error' in data) {
						errorHandler && errorHandler('confirm' in data ? data.confirm : 0, data.error);
					} else {
						successHandler && successHandler(data);
					}
				} else {
					errorHandler && errorHandler(xhr.status, xhr.statusText);
				}
			}
		};
		if (data instanceof FormData) {
			xhr.send(data);
		} else {
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			data.ajx = 1;
			data.csrf_hash = FluxBB.uploadvars.token;
			var query = '',
				separator = '';
			for (var key in data) {
				query += separator + key + '=' + encodeURIComponent(data[key]);
				separator = '&';
			}
			xhr.send(query);
		}
	}

	function updateData(data, auto) {
		pages = data.pages;

		setLegend(data.size, data.percent);

		for (var key in data.files) {
			addFileToGallery(key, data.files[key]);
			if (auto) {
				insertCode(key, true);
			}
		}

		get('upf-container').addEventListener('scroll', FluxBB.upload.listHandler, false);
		var event;
		if (typeof Event === 'function') {
			event = new Event('scroll');
		} else {
			event = document.createEvent('Event');
			event.initEvent('scroll', false, false);
		}
		get('upf-container').dispatchEvent(event);
	}

	function loadFileData() {
		get('upf-container').removeEventListener('scroll', FluxBB.upload.listHandler, false);

		if (page >= pages) {
			return;
		}
		++page;

		postData({action: 'view', p: page}, function (data) {
			updateData(data);
		}, function (status, text) {
			alert(text);
		});
	}

	function addFileToGallery(key, data) {
		if (key in files) {
			return;
		}
		var max = '';
		for (var cur in files) {
			if (key > cur && cur > max) {
				max = cur;
			}
		}
		var node = files['-'].link.cloneNode(true);
		node.id = 'upf-' + key;

		var name = node.querySelector('.upf-name');
		name.title = data.filename;
		name.querySelector('span').textContent = data.alt;

		node.querySelector('.upf-size').querySelector('span').textContent = data.size;

		var url = node.querySelector('.upf-file').querySelector('a');
		url.href = data.url;
		var child = url.querySelector('span');
		if (data.mini) {
			url.removeChild(child);
			var child = doc.createElement('img');
			child.src = data.mini;
			child.alt = data.alt;
			url.appendChild(child);
		} else {
			child.textContent = data.alt;
		}

		node.querySelector('.upf-delete').querySelector('a').addEventListener('click', FluxBB.upload.actionHandler, false);
		node.querySelector('.upf-insert').querySelector('a').addEventListener('click', FluxBB.upload.actionHandler, false);
		if (data.mini) {
			node.querySelector('.upf-insert-t').querySelector('a').addEventListener('click', FluxBB.upload.actionHandler, false);
		} else {
			node.querySelector('.upf-insert-t').style.display = 'none';
		}

		files[max].link.parentNode.insertBefore(node, files[max].link);
		data.link = node;
		files[key] = data;
	}

	function setLegend(size, percent)
	{
		try {
			var rgb = 'rgb(' + Math.ceil((percent > 50 ? 50 : percent)*255/50) + ', ' + Math.ceil((percent < 50 ? 50 : 100 - percent)*255/50) + ', 0)',
				legend = get('upf-legend'),
				div = legend.querySelector('div'),
				span = div.querySelector('span');
			legend.style.borderColor = div.style.backgroundColor = rgb;
			div.style.width = span.textContent = percent + '%';
		} catch (e) {}
		try {
			get('upf-legend-p').querySelector('span').textContent = size;
		} catch (e) {}
	}

	function deleteFile(key) {
		if (!confirm(FluxBB.uploadvars.lang.confirmation)) {
			return;
		}

		var file = files[key];

		file.link.classList.add('upf-removal');

		postData({action: 'delete', file: file.filename, p: page}, function (data) {
			file.link.parentNode.removeChild(file.link);
			file.link = null;
			delete files[key];
			updateData(data);
		}, function (status, text) {
			if (typeof status === 'string') {
				if (!confirm(text + ' ' + FluxBB.uploadvars.lang.confirmation)) {
					file.link.classList.remove('upf-removal');
					return;
				}

				postData({action: 'delete', confirm: status, file: file.filename, p: page}, function (data) {
					file.link.parentNode.removeChild(file.link);
					file.link = null;
					delete files[key];
					updateData(data);
				}, function (status, text) {
					file.link.classList.remove('upf-removal');
					alert(text);
				});
			} else {
				file.link.classList.remove('upf-removal');
				alert(text);
			}
		});
	}

	function insertCode(key, thumb) {
		var file = files[key];
		thumb = thumb && file.mini;

		if (thumb) {
			insertText('', '[url=' + file.url + '][img]' + file.mini + '[/img][/url]', '');
		} else if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].indexOf(file.ext) > -1) {
			insertText('', '[img]' + file.url + '[/img]', '');
		} else {
			insertText('[url=' + file.url + ']', '[/url]', file.filename);
		}
	}

	function insertText(open, close, text) {
		textarea.focus();
		// all and IE9+
		if ('selectionStart' in textarea) {
			var len = textarea.value.length,
				sp = Math.min(textarea.selectionStart, len), // IE bug
				ep = Math.min(textarea.selectionEnd, len); // IE bug

			textarea.value = textarea.value.substring(0, sp)
				+ open
				+ (sp == ep ? text : textarea.value.substring(sp, ep))
				+ close
				+ textarea.value.substring(ep);

			textarea.selectionStart = textarea.selectionEnd = ep + close.length + open.length + (sp == ep ? text.length : 0);
		}
		// IE9-
		else if (doc.selection && doc.selection.createRange) {
			var sel = doc.selection.createRange();
			sel.text = open + (!sel.text ? text : sel.text) + close;
		}
		textarea.focus();
	}
//*********************//
	return {
		init : function () {
			if (0 !== state) {
				return false;
			}
			state = -1;

			doc.removeEventListener("DOMContentLoaded", FluxBB.upload.init, false);

			textarea = doc.getElementsByName('req_message')[0];
			if (textarea && false !== findAnchor(textarea)) {
				var bblinks = anchor.querySelector('.bblinks');
				if (bblinks) {
					var link = createStartLink(bblinks);
					link.addEventListener('click', FluxBB.upload.clickStart, false);
					state = (typeof FormData === 'undefined') ? 1 : 2;
				}
			}
		},

		clickStart : function (event) {
			event.preventDefault();
			switch (state) {
				case 1:
					popUp(FluxBB.uploadvars.action);
					break;
				case 2:
					initLoader();
					state = 3;
					break;
			}
		},

		listHandler : function (event) {
			var list = event.currentTarget;
			if (list.scrollWidth - list.scrollLeft - list.clientWidth < 140) {
				loadFileData();
			}
		},

		actionHandler : function (event) {
			event.preventDefault();
			var target = event.currentTarget.parentNode,
				cl = target.className,
				key = target.parentNode.id.substring(4);

			if (!(key in files)) {
				return;
			}

			if (cl.indexOf('delete') > -1) {
				deleteFile(key);
			} else if (cl.indexOf('insert-t') > -1) {
				insertCode(key, true)
			} else if (cl.indexOf('insert') > -1) {
				insertCode(key, false)
			}
		},

		buttonHandler : function(event) {
			var event;
			try {
				event = new MouseEvent('click');
			} catch (e) {
				event = document.createEvent('MouseEvent');
				event.initEvent('click', false, false);
			}
			get('upfile').dispatchEvent(event);
		},

		changeHandler : function(event) {
			var files = event.target.files;
			if (1 !== files.length) {
				return;
			}

			var file = files[0];
			if (file.size > FluxBB.uploadvars.maxsize) {
				alert(FluxBB.uploadvars.lang.large);
			} else if (FluxBB.uploadvars.exts.indexOf(file.name.match(/\.([^.]*)$/)[1].toLowerCase()) < 0) {
				alert(FluxBB.uploadvars.lang.bad_type);
			} else {
				var form = new FormData(get('upf-dataform'));
				get('upf-button').classList.add('upf-uploading');
				postData(form, function (data) {
					get('upf-button').classList.remove('upf-uploading');
					updateData(data, true);
				}, function (status, text) {
					get('upf-button').classList.remove('upf-uploading');
					alert(text);
				});
			}
		}
	};
}(document, window));

if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", FluxBB.upload.init, false);
}
