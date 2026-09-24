/* global ELD_ADMIN, jQuery */
(function ($) {
	'use strict';

	var CFG = window.ELD_ADMIN || {};
	var sourceBase = '';
	var busy = false;

	function status(msg) {
		var $el = $('#eld-status');
		$el.text(msg).show();
		clearTimeout(status._t);
		status._t = setTimeout(function () {
			$el.fadeOut(200);
		}, 2500);
	}

	function log(msg, type) {
		var cls = type === 'error' ? 'err' : type === 'ok' ? 'ok' : '';
		$('#eld-log').prepend(
			'<div class="' + cls + '">[' + new Date().toLocaleTimeString() + '] ' + msg + '</div>'
		);
	}

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = CFG.nonce;
		return $.post(CFG.ajaxUrl, data);
	}

	function trim(s) {
		return $.trim(String(s == null ? '' : s));
	}

	function absoluteUrl(href, base) {
		href = trim(href);
		if (!href || href.indexOf('javascript:') === 0 || href.indexOf('#') === 0) {
			return '';
		}
		if (/^https?:\/\//i.test(href)) {
			return href;
		}
		base = base || sourceBase || CFG.homeUrl;
		try {
			return new URL(href, base).href;
		} catch (e) {
			return href;
		}
	}

	function setSourceBase(url) {
		try {
			var u = new URL(url);
			sourceBase = u.origin + '/';
		} catch (e) {
			sourceBase = CFG.homeUrl;
		}
	}

	function normalizeHtml(raw, fullHead) {
		var msg = String(raw || '');
		try {
			if (fullHead) {
				msg = msg
					.replace(/<html/gi, '<eb-html')
					.replace(/<\/html>/gi, '</eb-html>')
					.replace(/<head/gi, '<eb-head')
					.replace(/<\/head>/gi, '</eb-head>');
			} else {
				msg = msg.replace(/<\/head>/gi, '</head>').replace(/<\/html>/gi, '</html>');
				var parts = msg.split('</head>');
				msg = parts.length > 1 ? parts[1] : parts[0];
				msg = msg.split('</html>')[0];
			}

			msg = msg
				.replace(/<iframe/gi, '<eb-iframe')
				.replace(/<\/iframe>/gi, '</eb-iframe>')
				.replace(/<link/gi, '<eb-link')
				.replace(/<\/link>/gi, '</eb-link>')
				.replace(/<style/gi, '<eb-style')
				.replace(/<\/style>/gi, '</eb-style>')
				.replace(/<script/gi, '<eb-script')
				.replace(/<\/script>/gi, '</eb-script>');

			if (CFG.homeUrl && sourceBase) {
				msg = msg.split(CFG.homeUrl).join(sourceBase);
			}

			msg = msg.replace(/\sdata-src=/gi, ' data-old-src=').replace(/\ssrc=/gi, ' data-src=');
		} catch (e) {
			console.error(e);
		}
		return msg;
	}

	function putHtml(html) {
		var $box = $('#eld-html-sandbox');
		$box.html(html);
		var remove = trim($('#eld-remove-html').val());
		if (remove) {
			remove.split(',').forEach(function (sel) {
				sel = trim(sel);
				if (sel) {
					$box.find(sel).remove();
				}
			});
		}
		$box.find('eb-style, style').remove();
		$box.find('img').removeAttr('srcset');
		absolutizeSandboxUrls();
		return $box;
	}

	/**
	 * Đổi URL tương đối (src/data-src/href…) trong sandbox thành tuyệt đối theo sourceBase.
	 */
	function absolutizeSandboxUrls(base) {
		base = base || sourceBase || CFG.homeUrl;
		if (!base) return;
		var attrs = [
			'src',
			'data-src',
			'data-old-src',
			'data-original',
			'data-lazy-src',
			'data-image',
			'data-lazy',
			'href',
			'poster',
		];
		$('#eld-html-sandbox')
			.find('img, a, source, video, audio, eb-iframe, iframe')
			.each(function () {
				var $el = $(this);
				attrs.forEach(function (attr) {
					var v = $el.attr(attr);
					if (!v) return;
					var abs = absoluteUrl(v, base);
					if (abs && abs !== v) {
						$el.attr(attr, abs);
					}
				});
			});
	}

	function imgAttr($el) {
		return (
			$el.attr('data-original') ||
			$el.attr('data-lazy-src') ||
			$el.attr('data-old-src') ||
			$el.attr('data-image') ||
			$el.attr('data-lazy') ||
			$el.attr('data-src') ||
			$el.attr('src') ||
			''
		);
	}

	/**
	 * Sau khi xử lý xong: data-src (tạm) → src để ảnh load được khi lưu DB.
	 * Không đụng sandbox — chỉ áp lên HTML xuất ra form/import.
	 */
	function restoreSrcInHtml(html) {
		html = String(html || '');
		if (!html || (html.indexOf('data-src') === -1 && html.indexOf('data-old-src') === -1)) {
			return html;
		}
		var $wrap = $('<div>').html(html);
		$wrap.find('img, eb-iframe, iframe').each(function () {
			var $el = $(this);
			var url = imgAttr($el);
			if (url) {
				$el.attr('src', url);
			}
			$el.removeAttr(
				'data-src data-old-src data-original data-lazy-src data-image data-lazy'
			);
		});
		return $wrap.html();
	}

	/**
	 * Port selector engine from EchBay leech_data.js
	 * Supports: &&, ,, :each, :eachn, img[attr]
	 */
	function extractBySelector(selector, opts) {
		opts = opts || {};
		selector = trim(selector).replace(/\s\s+/g, ' ');
		if (!selector) {
			return '';
		}

		var $root = $('#eld-html-sandbox');
		var out = '';

		if (opts.youtube) {
			var $y = $root.find(selector).first();
			return absoluteUrl(
				$y.attr('data-old-src') || $y.attr('data-src') || $y.attr('src') || ''
			);
		}

		if (opts.img) {
			var arrGet = selector.replace(/\s?\|\|\s?/g, ',');
			if (arrGet.indexOf('[') !== -1) {
				arrGet.split(',').forEach(function (part) {
					part = trim(part);
					var getAttr = part.split('[');
					var str = '';
					if (getAttr.length > 1) {
						str = $root.find(getAttr[0]).attr(getAttr[1].split(']')[0]) || '';
					} else {
						str = imgAttr($root.find(getAttr[0]).first());
					}
					if (str) {
						out += absoluteUrl(str) + '\n';
					}
				});
			} else {
				$root.find(arrGet).each(function () {
					var str = imgAttr($(this));
					if (str) {
						out += absoluteUrl(str) + '\n';
					}
				});
			}
			return trim(out);
		}

		var chunks = selector.replace(/\s?\+\+\s?/g, '&&').split('&&');
		chunks.forEach(function (chunk) {
			chunk = trim(chunk);
			var str = '';
			var eachn = 0;

			if (chunk.indexOf(':each') !== -1) {
				var queries = chunk.split(',');
				for (var j = 0; j < queries.length; j++) {
					var q = trim(queries[j]);
					if (q.indexOf(':each') !== -1) {
						eachn = q.indexOf(':eachn') !== -1 ? 1 : 0;
						q = q.split(':')[0];
						var tagBegin = q.split(' ').pop().split('#')[0].split('.')[0];
						var tagEnd = tagBegin ? '</' + tagBegin + '>' : '';
						var tagOpen = tagBegin ? '<' + tagBegin + '>' : '';
						var strEach = '';

						$root.find(q.replace(/\s?\|\|\s?/g, ',')).each(function () {
							var getHtml = opts.strip_tags ? $(this).text() || '' : $(this).html() || '';
							if (getHtml) {
								strEach += eachn === 1 ? getHtml + '\n' : tagOpen + getHtml + tagEnd;
							}
						});

						if (strEach) {
							if (eachn === 0 && tagEnd.toLowerCase() === '</li>') {
								str += '<ul>' + strEach + '</ul>';
							} else {
								str += strEach;
							}
						}
					} else {
						str = opts.strip_tags ? $root.find(q).text() || '' : $root.find(q).html() || '';
						str = trim(str);
						if (str) {
							break;
						}
					}
				}
			} else {
				var $el = $root.find(chunk);
				str = opts.strip_tags ? $el.text() || '' : $el.html() || '';
				str = trim(str);
			}
			out += str;
		});

		return trim(out);
	}

	function digitsOnly(v, allowDot) {
		v = String(v || '');
		return allowDot ? v.replace(/[^0-9.\-+]/g, '') : v.replace(/[^0-9\-+]/g, '');
	}

	function skuFromUrl(url) {
		try {
			var u = new URL(url);
			var path = u.pathname.replace(/\/+/g, '-').replace(/^-|-$/g, '');
			path = path
				.toLowerCase()
				.replace(/[^a-z0-9\-]+/g, '-')
				.replace(/-+/g, '-')
				.replace(/^-|-$/g, '');
			return u.hostname + '-' + (path || 'item');
		} catch (e) {
			return 'eld-' + Date.now();
		}
	}

	function slugFromUrl(url) {
		try {
			var parts = new URL(url).pathname.split('/').filter(Boolean);
			var last = parts.length ? parts[parts.length - 1] : '';
			if ($('#eld-strip-html-ext').is(':checked')) {
				last = last.replace(/\.(html?|php|aspx?)$/i, '');
			}
			return last
				.toLowerCase()
				.replace(/[^a-z0-9\-]+/g, '-')
				.replace(/-+/g, '-')
				.replace(/^-|-$/g, '');
		} catch (e) {
			return '';
		}
	}

	function taxonomyForPostType(postType) {
		return postType === 'product' ? 'product_cat' : 'category';
	}

	function syncTaxonomyFromPostType() {
		$('#eld-taxonomy').val(taxonomyForPostType($('#eld-post-type').val()));
		fillTermSelect();
	}

	function fillTermSelect() {
		var tax = taxonomyForPostType($('#eld-post-type').val());
		var list = (CFG.terms && CFG.terms[tax]) || [];
		var $sel = $('#eld-term-id');
		var prev = $sel.val();
		$sel.empty().append($('<option>').val('0').text('— không chọn —'));
		list.forEach(function (t) {
			$sel.append($('<option>').val(String(t.id)).text(t.name));
		});
		if (prev && $sel.find('option[value="' + prev + '"]').length) {
			$sel.val(prev);
		} else {
			$sel.val('0');
		}
	}

	var UI_PREFS_KEY = 'eld_admin_ui_v1';
	var uiPrefsTimer = null;

	function collectUiPrefs() {
		return {
			categories_url: $('#eld-categories-url').val(),
			is_detail_url: $('#eld-is-detail-url').is(':checked'),
			list_selector: $('#eld-list-selector').val(),
			full_head: $('#eld-full-head').is(':checked'),
			convert_encoding: $('#eld-convert-encoding').is(':checked'),
			bypass_cache: $('#eld-bypass-cache').is(':checked'),
			post_type: $('#eld-post-type').val(),
			term_id: $('#eld-term-id').val(),
			auto_next: $('#eld-auto-next').is(':checked'),
			delay: $('#eld-delay').val(),
			download_images: $('#eld-download-images').is(':checked'),
			download_content_images: $('#eld-download-content-images').is(':checked'),
			min_title: $('#eld-min-title').val(),
			remove_html: $('#eld-remove-html').val(),
			slug_from_url: $('#eld-slug-from-url').is(':checked'),
			strip_html_ext: $('#eld-strip-html-ext').is(':checked'),
			fields: {
				title: $('#sel-title').val(),
				date: $('#sel-date').val(),
				image: $('#sel-image').val(),
				youtube: $('#sel-youtube').val(),
				price: $('#sel-price').val(),
				sale_price: $('#sel-sale-price').val(),
				product_sku: $('#sel-product-sku').val(),
				excerpt: $('#sel-excerpt').val(),
				content: $('#sel-content').val(),
				highlights: $('#sel-highlights').val(),
				gallery: $('#sel-gallery').val(),
				category: $('#sel-category').val(),
				category_2: $('#sel-category-2').val(),
			},
		};
	}

	function applyUiPrefs(p) {
		if (!p || typeof p !== 'object') return;
		if (p.categories_url != null) $('#eld-categories-url').val(p.categories_url);
		if (typeof p.is_detail_url === 'boolean') $('#eld-is-detail-url').prop('checked', p.is_detail_url);
		if (p.list_selector != null) $('#eld-list-selector').val(p.list_selector);
		if (typeof p.full_head === 'boolean') $('#eld-full-head').prop('checked', p.full_head);
		if (typeof p.convert_encoding === 'boolean') {
			$('#eld-convert-encoding').prop('checked', p.convert_encoding);
		}
		if (typeof p.bypass_cache === 'boolean') $('#eld-bypass-cache').prop('checked', p.bypass_cache);
		if (p.post_type) $('#eld-post-type').val(p.post_type);
		syncTaxonomyFromPostType();
		if (p.term_id != null) $('#eld-term-id').val(String(p.term_id));
		if (typeof p.auto_next === 'boolean') $('#eld-auto-next').prop('checked', p.auto_next);
		if (p.delay != null) $('#eld-delay').val(p.delay);
		if (typeof p.download_images === 'boolean') {
			$('#eld-download-images').prop('checked', p.download_images);
		}
		if (typeof p.download_content_images === 'boolean') {
			$('#eld-download-content-images').prop('checked', p.download_content_images);
		}
		if (p.min_title != null) $('#eld-min-title').val(p.min_title);
		if (p.remove_html != null) $('#eld-remove-html').val(p.remove_html);
		if (typeof p.slug_from_url === 'boolean') $('#eld-slug-from-url').prop('checked', p.slug_from_url);
		if (typeof p.strip_html_ext === 'boolean') {
			$('#eld-strip-html-ext').prop('checked', p.strip_html_ext);
		}
		var f = p.fields || {};
		if (f.title != null) $('#sel-title').val(f.title);
		if (f.date != null) $('#sel-date').val(f.date);
		if (f.image != null) $('#sel-image').val(f.image);
		if (f.youtube != null) $('#sel-youtube').val(f.youtube);
		if (f.price != null) $('#sel-price').val(f.price);
		if (f.sale_price != null) $('#sel-sale-price').val(f.sale_price);
		if (f.product_sku != null) $('#sel-product-sku').val(f.product_sku);
		if (f.excerpt != null) $('#sel-excerpt').val(f.excerpt);
		if (f.content != null) $('#sel-content').val(f.content);
		if (f.highlights != null) $('#sel-highlights').val(f.highlights);
		if (f.gallery != null) $('#sel-gallery').val(f.gallery);
		if (f.category != null) $('#sel-category').val(f.category);
		if (f.category_2 != null) $('#sel-category-2').val(f.category_2);
	}

	function saveUiPrefs() {
		try {
			localStorage.setItem(UI_PREFS_KEY, JSON.stringify(collectUiPrefs()));
		} catch (e) {}
	}

	function scheduleSaveUiPrefs() {
		clearTimeout(uiPrefsTimer);
		uiPrefsTimer = setTimeout(saveUiPrefs, 300);
	}

	function restoreUiPrefs() {
		try {
			var raw = localStorage.getItem(UI_PREFS_KEY);
			if (!raw) return;
			applyUiPrefs(JSON.parse(raw));
		} catch (e) {}
	}

	function collectProfile() {
		var firstUrl = trim($('#eld-categories-url').val().split(/\n/)[0]);
		var domain = '';
		try {
			domain = firstUrl ? new URL(firstUrl).hostname : '';
		} catch (e) {}
		return {
			id: domain ? domain.replace(/\./g, '-') : 'profile-' + Date.now(),
			domain: domain || 'unknown',
			list_selector: $('#eld-list-selector').val(),
			fields: {
				title: $('#sel-title').val(),
				date: $('#sel-date').val(),
				image: $('#sel-image').val(),
				youtube: $('#sel-youtube').val(),
				price: $('#sel-price').val(),
				sale_price: $('#sel-sale-price').val(),
				product_sku: $('#sel-product-sku').val(),
				excerpt: $('#sel-excerpt').val(),
				content: $('#sel-content').val(),
				highlights: $('#sel-highlights').val(),
				gallery: $('#sel-gallery').val(),
				category: $('#sel-category').val(),
				category_2: $('#sel-category-2').val(),
			},
			options: {
				post_type: $('#eld-post-type').val(),
				taxonomy: $('#eld-taxonomy').val(),
				term_id: $('#eld-term-id').val(),
				is_detail_url: $('#eld-is-detail-url').is(':checked'),
				full_head: $('#eld-full-head').is(':checked'),
				convert_encoding: $('#eld-convert-encoding').is(':checked'),
				bypass_cache: $('#eld-bypass-cache').is(':checked'),
				auto_next: $('#eld-auto-next').is(':checked'),
				delay: $('#eld-delay').val(),
				download_images: $('#eld-download-images').is(':checked'),
				download_content_images: $('#eld-download-content-images').is(':checked'),
				min_title: $('#eld-min-title').val(),
				remove_html: $('#eld-remove-html').val(),
				slug_from_url: $('#eld-slug-from-url').is(':checked'),
				strip_html_ext: $('#eld-strip-html-ext').is(':checked'),
			},
		};
	}

	function applyProfile(p) {
		if (!p) return;
		if (p.list_selector) $('#eld-list-selector').val(p.list_selector);
		var f = p.fields || {};
		if (f.title != null) $('#sel-title').val(f.title);
		if (f.date != null) $('#sel-date').val(f.date);
		if (f.image != null) $('#sel-image').val(f.image);
		if (f.youtube != null) $('#sel-youtube').val(f.youtube);
		if (f.price != null) $('#sel-price').val(f.price);
		if (f.sale_price != null) $('#sel-sale-price').val(f.sale_price);
		if (f.product_sku != null) $('#sel-product-sku').val(f.product_sku);
		if (f.excerpt != null) $('#sel-excerpt').val(f.excerpt);
		if (f.content != null) $('#sel-content').val(f.content);
		if (f.highlights != null) $('#sel-highlights').val(f.highlights);
		if (f.gallery != null) $('#sel-gallery').val(f.gallery);
		if (f.category != null) $('#sel-category').val(f.category);
		if (f.category_2 != null) $('#sel-category-2').val(f.category_2);
		var o = p.options || {};
		if (o.post_type) $('#eld-post-type').val(o.post_type);
		syncTaxonomyFromPostType();
		if (o.term_id != null) $('#eld-term-id').val(String(o.term_id));
		if (typeof o.is_detail_url === 'boolean') $('#eld-is-detail-url').prop('checked', o.is_detail_url);
		if (typeof o.full_head === 'boolean') $('#eld-full-head').prop('checked', o.full_head);
		if (typeof o.convert_encoding === 'boolean') {
			$('#eld-convert-encoding').prop('checked', o.convert_encoding);
		}
		if (typeof o.bypass_cache === 'boolean') $('#eld-bypass-cache').prop('checked', o.bypass_cache);
		if (typeof o.auto_next === 'boolean') $('#eld-auto-next').prop('checked', o.auto_next);
		if (o.delay != null) $('#eld-delay').val(o.delay);
		if (typeof o.download_images === 'boolean') $('#eld-download-images').prop('checked', o.download_images);
		if (typeof o.download_content_images === 'boolean') {
			$('#eld-download-content-images').prop('checked', o.download_content_images);
		}
		if (o.min_title) $('#eld-min-title').val(o.min_title);
		if (o.remove_html) $('#eld-remove-html').val(o.remove_html);
		if (typeof o.slug_from_url === 'boolean') $('#eld-slug-from-url').prop('checked', o.slug_from_url);
		if (typeof o.strip_html_ext === 'boolean') $('#eld-strip-html-ext').prop('checked', o.strip_html_ext);
		saveUiPrefs();
		status('Đã nạp profile: ' + (p.domain || p.id));
	}

	function fetchHtml(url) {
		setSourceBase(url);
		status(CFG.i18n.fetching);
		return ajax('eld_fetch_html', {
			url: url,
			source_url: sourceBase,
			bypass_cache: $('#eld-bypass-cache').is(':checked') ? 1 : 0,
			convert_encoding: $('#eld-convert-encoding').is(':checked') ? 1 : 0,
		}).then(function (res) {
			if (!res || !res.success) {
				throw new Error((res && res.data && res.data.message) || 'Fetch failed');
			}
			return res.data.html;
		});
	}

	function addQueueUrl(href, text, imageUrl) {
		href = absoluteUrl(href);
		if (!href) return;
		var exists = false;
		$('#eld-url-queue a, #eld-url-done a').each(function () {
			if ($(this).attr('href') === href) exists = true;
		});
		if (exists) return;
		imageUrl = absoluteUrl(imageUrl || '');
		var $a = $('<a>', {
			href: href,
			'data-eld-detail': '1',
		}).text(text || href);
		if (imageUrl) {
			$a.attr('data-eld-image', imageUrl);
		}
		$('#eld-url-queue').append($('<li>').append($a));
	}

	/** Ưu tiên attr title (tránh icon trong text), rồi text / html. */
	function linkLabel($el) {
		var title = trim($el.attr('title') || '');
		if (title) return title;
		var text = trim($el.text() || '');
		if (text) return text;
		var html = trim($el.html() || '');
		if (html) {
			return trim(
				$('<div>')
					.html(html)
					.text()
			);
		}
		return '';
	}

	/** Ảnh trong thẻ list (thường là thumb trong <a>). */
	function linkThumbImage($el) {
		var $img = $el.is('img') ? $el : $el.find('img').first();
		if (!$img.length) {
			return '';
		}
		return absoluteUrl(imgAttr($img));
	}

	function listImageForUrl(url) {
		var $a = $('#eld-url-queue a, #eld-url-done a')
			.filter(function () {
				return $(this).attr('href') === url;
			})
			.first();
		return trim($a.attr('data-eld-image') || '');
	}

	function parseListFromHtml(html) {
		var fullHead = $('#eld-full-head').is(':checked');
		putHtml(normalizeHtml(html, fullHead));
		var sel = trim($('#eld-list-selector').val()) || 'a';
		var count = 0;
		$('#eld-html-sandbox')
			.find(sel)
			.each(function () {
				var $a = $(this);
				var href = $a.attr('href') || $a.attr('data-href') || '';
				addQueueUrl(href, linkLabel($a) || href, linkThumbImage($a));
				count++;
			});
		log('Tìm thấy ' + $('#eld-url-queue li').length + ' URL (matched nodes: ' + count + ')', 'ok');
	}

	function fillFormFromSandbox(detailUrl) {
		setSourceBase(detailUrl);
		absolutizeSandboxUrls(detailUrl);

		var title = extractBySelector($('#sel-title').val() || 'h1', { strip_tags: true });
		var content = extractBySelector($('#sel-content').val());
		var excerpt = extractBySelector($('#sel-excerpt').val(), { strip_tags: true });
		var selImage = trim($('#sel-image').val());
		var image = selImage
			? extractBySelector(selImage, { img: true }).split('\n')[0] || ''
			: '';
		if (!image) {
			image = listImageForUrl(detailUrl);
		}
		var gallery = extractBySelector($('#sel-gallery').val(), { img: true });
		var youtube = extractBySelector($('#sel-youtube').val(), { youtube: true });
		var price = digitsOnly(extractBySelector($('#sel-price').val(), { strip_tags: true }));
		var sale = digitsOnly(extractBySelector($('#sel-sale-price').val(), { strip_tags: true }));
		var productSku = extractBySelector($('#sel-product-sku').val(), { strip_tags: true });
		var highlights = extractBySelector($('#sel-highlights').val());
		var cat1 = extractBySelector($('#sel-category').val(), { strip_tags: true });
		var cat2 = extractBySelector($('#sel-category-2').val(), { strip_tags: true });
		var dateRaw = extractBySelector($('#sel-date').val(), { strip_tags: true });

		$('#f-source').val(detailUrl);
		$('#f-sku').val(skuFromUrl(detailUrl));
		$('#f-title').val(title);
		$('#f-slug').val($('#eld-slug-from-url').is(':checked') ? slugFromUrl(detailUrl) : '');
		$('#f-date').val(dateRaw);
		$('#f-image').val(image);
		$('#f-youtube').val(youtube);
		$('#f-price').val(price);
		$('#f-sale-price').val(sale);
		$('#f-product-sku').val(productSku);
		$('#f-excerpt').val(excerpt);
		$('#f-content').val(restoreSrcInHtml(content));
		$('#f-highlights').val(restoreSrcInHtml(highlights));
		$('#f-gallery').val(gallery);
		$('#f-new-category').val(cat1);
		$('#f-new-category-2').val(cat2);

		log('Parsed: ' + (title || '(no title)') + ' ← ' + detailUrl, title ? 'ok' : 'error');
	}

	function buildItemPayload() {
		return {
			source_url: $('#f-source').val(),
			sku: $('#f-sku').val(),
			title: $('#f-title').val(),
			slug: $('#f-slug').val(),
			post_date: $('#f-date').val(),
			image_url: $('#f-image').val(),
			youtube_url: $('#f-youtube').val(),
			price: $('#f-price').val(),
			sale_price: $('#f-sale-price').val(),
			product_sku: $('#f-product-sku').val(),
			excerpt: $('#f-excerpt').val(),
			content: restoreSrcInHtml($('#f-content').val()),
			highlights: restoreSrcInHtml($('#f-highlights').val()),
			gallery: $('#f-gallery').val(),
			new_category: $('#f-new-category').val(),
			new_category_2: $('#f-new-category-2').val(),
			post_type: $('#eld-post-type').val(),
			taxonomy: $('#eld-taxonomy').val(),
			term_id: parseInt($('#eld-term-id').val(), 10) || 0,
			download_image: $('#eld-download-images').is(':checked'),
			download_content_images: $('#eld-download-content-images').is(':checked'),
			post_status: 'publish',
		};
	}

	function markDone(url) {
		var $a = $('#eld-url-queue a').filter(function () {
			return $(this).attr('href') === url;
		});
		if ($a.length) {
			var $li = $a.closest('li');
			$('#eld-url-done').append($li);
		}
	}

	function importCurrent() {
		if (busy) return $.Deferred().reject().promise();
		var item = buildItemPayload();
		var minTitle = parseInt($('#eld-min-title').val(), 10) || 16;
		if (!item.title || item.title.length < minTitle) {
			log('Bỏ qua: title quá ngắn', 'error');
			return $.Deferred().reject().promise();
		}
		busy = true;
		status(CFG.i18n.importing);
		return ajax('eld_import_item', {
			item: JSON.stringify(item),
		})
			.then(function (res) {
				busy = false;
				if (!res || !res.success) {
					var msg = (res && res.data && res.data.message) || 'Import failed';
					log(msg, 'error');
					throw new Error(msg);
				}
				var d = res.data;
				log(
					d.message + ' #' + d.id + ' <a href="' + d.link + '" target="_blank">xem</a>',
					'ok'
				);
				markDone(item.source_url);
				status(CFG.i18n.done);
				return d;
			})
			.fail(function () {
				busy = false;
			});
	}

	function fetchDetail(url) {
		return fetchHtml(url).then(function (html) {
			putHtml(normalizeHtml(html, $('#eld-full-head').is(':checked')));
			fillFormFromSandbox(url);
		});
	}

	var categoryLines = [];
	var categoryIndex = 0;

	function delayMs() {
		return (parseInt($('#eld-delay').val(), 10) || 0) * 1000;
	}

	function processNext() {
		var $first = $('#eld-url-queue li:first a');
		if (!$first.length) {
			if ($('#eld-auto-next').is(':checked') && categoryIndex < categoryLines.length) {
				log('Hết bài danh mục hiện tại — chuyển URL danh mục tiếp theo', 'ok');
				setTimeout(fetchNextCategory, delayMs());
				return;
			}
			log('Queue trống — đã hết bài để xử lý', 'ok');
			return;
		}
		var url = $first.attr('href');
		log('Đang xử lý: ' + url);
		fetchDetail(url)
			.then(function () {
				return importCurrent();
			})
			.then(function () {
				if ($('#eld-auto-next').is(':checked')) {
					setTimeout(processNext, delayMs());
				}
			})
			.fail(function (err) {
				log(String(err && err.message ? err.message : err), 'error');
				if ($('#eld-auto-next').is(':checked')) {
					markDone(url);
					setTimeout(processNext, delayMs() || 3000);
				}
			});
	}

	function startAutoProcessIfEnabled() {
		if (!$('#eld-auto-next').is(':checked')) {
			return;
		}
		if (!$('#eld-url-queue li:first a').length) {
			if (categoryIndex < categoryLines.length) {
				setTimeout(fetchNextCategory, delayMs());
				return;
			}
			log('Queue trống — không có URL để tự chạy', 'error');
			return;
		}
		log('Tự động tiếp tục: lấy chi tiết & đăng bài…', 'ok');
		setTimeout(processNext, delayMs());
	}

	function fetchNextCategory() {
		if (categoryIndex >= categoryLines.length) {
			log('Đã xử lý hết các URL danh mục', 'ok');
			return;
		}
		var line = categoryLines[categoryIndex];
		categoryIndex += 1;
		var url = line.split('|')[0].trim();
		log('Danh mục ' + categoryIndex + '/' + categoryLines.length + ': ' + url, 'ok');

		if ($('#eld-is-detail-url').is(':checked')) {
			addQueueUrl(url, url);
			startAutoProcessIfEnabled();
			return;
		}

		fetchHtml(url)
			.then(parseListFromHtml)
			.then(function () {
				startAutoProcessIfEnabled();
			})
			.fail(function (err) {
				log(String(err && err.message ? err.message : err), 'error');
				if ($('#eld-auto-next').is(':checked')) {
					setTimeout(fetchNextCategory, delayMs() || 3000);
				}
			});
	}

	// Events
	restoreUiPrefs();
	$('#eld-post-type').on('change', function () {
		syncTaxonomyFromPostType();
		scheduleSaveUiPrefs();
	});
	if (!$('#eld-taxonomy').val()) {
		syncTaxonomyFromPostType();
	}

	$(
		'#eld-categories-url, #eld-list-selector, #eld-delay, #eld-min-title, #eld-remove-html, #eld-term-id, ' +
			'#sel-title, #sel-date, #sel-image, #sel-youtube, #sel-price, #sel-sale-price, #sel-product-sku, ' +
			'#sel-excerpt, #sel-content, #sel-highlights, #sel-gallery, #sel-category, #sel-category-2'
	).on('input change', scheduleSaveUiPrefs);

	$(
		'#eld-is-detail-url, #eld-full-head, #eld-convert-encoding, #eld-bypass-cache, #eld-auto-next, ' +
			'#eld-download-images, #eld-download-content-images, #eld-slug-from-url, #eld-strip-html-ext'
	).on('change', scheduleSaveUiPrefs);

	$('#eld-fetch-list').on('click', function () {
		categoryLines = $('#eld-categories-url')
			.val()
			.split(/\n/)
			.map(trim)
			.filter(Boolean);
		categoryIndex = 0;
		if (!categoryLines.length) {
			alert('Nhập URL danh mục');
			return;
		}
		log('Có ' + categoryLines.length + ' URL danh mục / chi tiết', 'ok');
		fetchNextCategory();
	});

	$('#eld-fetch-detail').on('click', function () {
		processNext();
	});

	$('#eld-import-current').on('click', function () {
		importCurrent();
	});

	$(document).on('click', 'a[data-eld-detail]', function (e) {
		e.preventDefault();
		var url = $(this).attr('href');
		fetchDetail(url).fail(function (err) {
			log(String(err.message || err), 'error');
		});
	});

	$('#eld-clear-cache').on('click', function () {
		ajax('eld_clear_cache').then(function (res) {
			if (res.success) {
				log('Đã xóa ' + res.data.cleared + ' file cache', 'ok');
			}
		});
	});

	$('#eld-save-profile').on('click', function () {
		var profile = collectProfile();
		ajax('eld_save_profile', { profile: JSON.stringify(profile) }).then(function (res) {
			if (res.success) {
				log('Đã lưu profile ' + profile.domain, 'ok');
				var $sel = $('#eld-profile-select');
				if (!$sel.find('option[value="' + profile.id + '"]').length) {
					$sel.append(
						$('<option>')
							.val(profile.id)
							.text(profile.domain)
					);
				}
				$sel.val(profile.id);
			}
		});
	});

	$('#eld-load-profile').on('click', function () {
		var id = $('#eld-profile-select').val();
		if (!id) return;
		ajax('eld_list_profiles').then(function (res) {
			if (res.success && res.data.profiles[id]) {
				applyProfile(res.data.profiles[id]);
			}
		});
	});

	$('#eld-delete-profile').on('click', function () {
		var id = $('#eld-profile-select').val();
		if (!id || !confirm('Xóa profile?')) return;
		ajax('eld_delete_profile', { id: id }).then(function (res) {
			if (res.success) {
				$('#eld-profile-select option[value="' + id + '"]').remove();
				log('Đã xóa profile', 'ok');
			}
		});
	});

	$('#eld-save-list').on('click', function () {
		ajax('eld_save_list_urls', { list: $('#eld-saved-list').val() }).then(function (res) {
			if (res.success) log('Đã lưu danh sách URL', 'ok');
		});
	});

	$('#eld-use-saved-list').on('click', function () {
		$('#eld-saved-list')
			.val()
			.split(/\n/)
			.map(trim)
			.filter(Boolean)
			.forEach(function (line) {
				addQueueUrl(line.split('|')[0].trim(), line);
			});
		log('Đã nạp saved list vào queue', 'ok');
		startAutoProcessIfEnabled();
	});

	$('#eld-check-update').on('click', function () {
		var $button = $(this);
		var $status = $('#eld-update-status');
		var $info = $('#eld-update-info');
		var $details = $('#eld-update-details');

		$button.prop('disabled', true).text('Checking…');
		$status.html('<span style="color:#666;">Đang kiểm tra cập nhật…</span>');
		$info.hide();
		$details.empty();

		ajax('eld_check_update')
			.done(function (res) {
				if (!res || !res.success) {
					var errMsg =
						(res && res.data && res.data.message) || 'Không kiểm tra được cập nhật';
					$status.html('<span style="color:#dc3545;">Error checking updates</span>');
					$details.html('<p>' + errMsg + '</p>');
					$info.show();
					return;
				}

				var data = res.data || {};
				if (data.has_update) {
					$status.html(
						'<span style="color:#d63384;"><strong>Update Available!</strong></span>'
					);
					$details.html(
						'<p><strong>New Version:</strong> ' +
							data.remote_version +
							'</p>' +
							'<p><a class="button button-primary" href="' +
							(CFG.pluginsUrl || 'plugins.php') +
							'" target="_blank">Go to Plugins page to update</a></p>' +
							'<p style="color:#666;"><em>Bản cập nhật sẽ hiện trên trang Plugins của WordPress.</em></p>'
					);
				} else {
					$status.html(
						'<span style="color:#198754;"><strong>' +
							(data.message || 'Up to date') +
							'</strong></span>'
					);
					$details.html(
						'<p>Current: ' +
							(data.current_version || CFG.version || '') +
							' · Remote: ' +
							(data.remote_version || '') +
							'</p>'
					);
				}
				$info.show();
			})
			.fail(function () {
				$status.html('<span style="color:#dc3545;">Unable to connect to GitHub</span>');
			})
			.always(function () {
				$button.prop('disabled', false).text('Check for Updates');
			});
	});
})(jQuery);
