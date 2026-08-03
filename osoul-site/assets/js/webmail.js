/* =========================================================================
 * Osoul Albinaa — Employee Webmail SPA
 * A live, Gmail/Outlook-class client over the osoul/v1/mail REST API.
 * Vanilla JS, no build step. Bilingual (AR/EN), theme-aware, RTL-first.
 * ========================================================================= */
(function () {
	'use strict';

	var CFG  = window.OSOUL_MAIL || {};
	var LANG = CFG.lang === 'en' ? 'en' : 'ar';
	var ROOT = document.getElementById('osoul-mail');

	/* ------------------------------ i18n ------------------------------ */
	var STR = {
		ar: {
			compose: 'إنشاء', search: 'بحث في البريد', inbox: 'الوارد', sent: 'المُرسَل',
			drafts: 'المسودات', trash: 'المهملات', junk: 'المزعج', archive: 'الأرشيف', folders: 'المجلدات',
			connect_title: 'اربط بريدك', connect_sub: 'أدخل بريدك من هوستنجر وكلمة مروره لتبدأ الإرسال والاستقبال.',
			email: 'البريد الإلكتروني', password: 'كلمة مرور البريد', display_name: 'الاسم الظاهر (اختياري)',
			connect: 'ربط البريد', connecting: 'جاري التحقق…',
			hoster_note: 'الإعدادات مضبوطة تلقائياً على هوستنجر — تحتاج فقط بريدك وكلمة مروره.',
			advanced: 'إعدادات متقدمة', imap_host: 'خادم الوارد (IMAP)', imap_port: 'منفذ الوارد',
			smtp_host: 'خادم الصادر (SMTP)', smtp_port: 'منفذ الصادر',
			no_messages: 'لا توجد رسائل هنا', pick_message: 'اختر رسالة لعرضها',
			reply: 'رد', reply_all: 'رد للكل', forward: 'تحويل', delete: 'حذف', move: 'نقل',
			mark_unread: 'تعليم كغير مقروء', to: 'إلى', cc: 'نسخة', bcc: 'نسخة مخفية', subject: 'الموضوع',
			send: 'إرسال', sending: 'جاري الإرسال…', save_draft: 'حفظ كمسودة', discard: 'تجاهل',
			new_message: 'رسالة جديدة', attach: 'إرفاق ملف', attachments: 'مرفقات',
			settings: 'الإعدادات', signature: 'التوقيع', save: 'حفظ', cancel: 'إلغاء',
			logout: 'تسجيل الخروج', theme: 'المظهر', language: 'اللغة', account: 'الحساب',
			disconnect: 'فصل ربط البريد', refresh: 'تحديث',
			sent_ok: 'تم إرسال الرسالة', draft_ok: 'تم حفظ المسودة', deleted_ok: 'تم نقل الرسالة إلى المهملات',
			purged_ok: 'تم حذف الرسالة نهائياً', moved_ok: 'تم نقل الرسالة', saved_ok: 'تم الحفظ',
			need_rcpt: 'أضف مستلماً واحداً على الأقل', confirm_purge: 'حذف الرسالة نهائياً؟',
			connected_as: 'مرتبط بـ', me: 'أنا', loading: 'جاري التحميل…', page_of: 'من',
			no_subject: '(بدون موضوع)', to_prefix: 'إلى: ', on_wrote: 'كتب:', original_message: 'الرسالة الأصلية',
			link_prompt: 'أدخل الرابط:', download: 'تنزيل', reconnect_needed: 'انتهت جلسة البريد. أعد الربط.',
		},
		en: {
			compose: 'Compose', search: 'Search mail', inbox: 'Inbox', sent: 'Sent',
			drafts: 'Drafts', trash: 'Trash', junk: 'Spam', archive: 'Archive', folders: 'Folders',
			connect_title: 'Connect your mailbox', connect_sub: 'Enter your Hostinger email and its password to start sending and receiving.',
			email: 'Email address', password: 'Email password', display_name: 'Display name (optional)',
			connect: 'Connect mailbox', connecting: 'Verifying…',
			hoster_note: 'Server settings are pre-set for Hostinger — you only need your email and its password.',
			advanced: 'Advanced settings', imap_host: 'Incoming server (IMAP)', imap_port: 'Incoming port',
			smtp_host: 'Outgoing server (SMTP)', smtp_port: 'Outgoing port',
			no_messages: 'No messages here', pick_message: 'Select a message to read',
			reply: 'Reply', reply_all: 'Reply all', forward: 'Forward', delete: 'Delete', move: 'Move',
			mark_unread: 'Mark as unread', to: 'To', cc: 'Cc', bcc: 'Bcc', subject: 'Subject',
			send: 'Send', sending: 'Sending…', save_draft: 'Save draft', discard: 'Discard',
			new_message: 'New message', attach: 'Attach file', attachments: 'Attachments',
			settings: 'Settings', signature: 'Signature', save: 'Save', cancel: 'Cancel',
			logout: 'Sign out', theme: 'Theme', language: 'Language', account: 'Account',
			disconnect: 'Disconnect mailbox', refresh: 'Refresh',
			sent_ok: 'Message sent', draft_ok: 'Draft saved', deleted_ok: 'Moved to Trash',
			purged_ok: 'Message deleted', moved_ok: 'Message moved', saved_ok: 'Saved',
			need_rcpt: 'Add at least one recipient', confirm_purge: 'Delete this message permanently?',
			connected_as: 'Connected as', me: 'Me', loading: 'Loading…', page_of: 'of',
			no_subject: '(no subject)', to_prefix: 'To: ', on_wrote: 'wrote:', original_message: 'Original message',
			link_prompt: 'Enter URL:', download: 'Download', reconnect_needed: 'Mail session expired. Please reconnect.',
		}
	};
	// Feature-set strings (filters, sort, bulk, storage, contacts, folders).
	var MORE = {
		ar: { f_all: 'الكل', f_unread: 'غير مقروء', f_read: 'مقروء', f_starred: 'مميّز', sort: 'ترتيب', newest: 'الأحدث أولاً', oldest: 'الأقدم أولاً',
			selected: 'محدد', b_read: 'مقروء', b_unread: 'غير مقروء', b_star: 'تمييز', b_delete: 'حذف', b_move: 'نقل', b_cancel: 'إلغاء',
			storage: 'مساحة التخزين', add_folder: 'مجلد جديد', folder_name: 'اسم المجلد', contacts: 'جهات الاتصال', no_contacts: 'لا توجد جهات اتصال بعد.',
			select_all: 'تحديد الكل', deleted_n: 'تم حذف الرسائل', done_n: 'تم التنفيذ', expand: 'تكبير', collapse: 'تصغير', new_folder_ok: 'تم إنشاء المجلد' },
		en: { f_all: 'All', f_unread: 'Unread', f_read: 'Read', f_starred: 'Starred', sort: 'Sort', newest: 'Newest first', oldest: 'Oldest first',
			selected: 'selected', b_read: 'Read', b_unread: 'Unread', b_star: 'Star', b_delete: 'Delete', b_move: 'Move', b_cancel: 'Cancel',
			storage: 'Storage', add_folder: 'New folder', folder_name: 'Folder name', contacts: 'Contacts', no_contacts: 'No contacts yet.',
			select_all: 'Select all', deleted_n: 'Messages deleted', done_n: 'Done', expand: 'Expand', collapse: 'Collapse', new_folder_ok: 'Folder created' }
	};
	function t(k) { var v = STR[LANG][k]; if (v == null) v = MORE[LANG][k]; return v != null ? v : (STR.ar[k] || MORE.ar[k] || k); }

	/* ------------------------------ icons ----------------------------- */
	var I = {
		inbox: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
		sent: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>',
		drafts: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
		trash: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
		junk: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>',
		archive: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M10 12h4"/></svg>',
		folder: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>',
		star: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
		starf: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
		reply: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 17l-5-5 5-5"/><path d="M4 12h11a5 5 0 0 1 5 5v1"/></svg>',
		replyall: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17l-5-5 5-5M12 17l-5-5 5-5"/><path d="M7 12h9a5 5 0 0 1 5 5v1"/></svg>',
		forward: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17l5-5-5-5"/><path d="M20 12H9a5 5 0 0 0-5 5v1"/></svg>',
		search: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
		close: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>',
		menu: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>',
		refresh: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>',
		attach: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
		back: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>',
		dots: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>',
		gear: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.14.31.4.57.71.71H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
		mail: '<svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
	};
	var FOLDER_ICON = { inbox: I.inbox, sent: I.sent, drafts: I.drafts, trash: I.trash, junk: I.junk, archive: I.archive };
	I.sort = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5h10M11 9h7M11 13h4M3 17l3 3 3-3M6 18V4"/></svg>';
	I.expand = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>';
	I.compress = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/></svg>';
	I.plus = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>';
	I.contacts = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>';

	/* ------------------------------ state ----------------------------- */
	var S = {
		connected: false, folders: [], folder: 'INBOX', special: 'inbox', display: t('inbox'),
		page: 0, total: 0, per: 25, messages: [], search: '', currentUid: 0, currentMsg: null,
		from_name: '', signature: '', email: CFG.email || '', name: CFG.name || '', busy: false,
		filter: '', sort: 'newest', sel: {}
	};

	/* --------------------------- dom helpers -------------------------- */
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
	function q(sel, root) { return (root || document).querySelector(sel); }
	function qa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
	function on(node, ev, fn) { if (node) node.addEventListener(ev, fn); }
	function fmtBytes(n) { n = +n || 0; if (n < 1024) return n + ' B'; if (n < 1048576) return (n / 1024).toFixed(0) + ' KB'; return (n / 1048576).toFixed(1) + ' MB'; }

	function avColor(s) { var h = 0, i; s = String(s || '?'); for (i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) % 360; } return 'linear-gradient(135deg,hsl(' + h + ',62%,54%),hsl(' + ((h + 26) % 360) + ',58%,42%))'; }
	function initials(name, email) {
		name = (name || '').trim();
		if (name && !/@/.test(name)) { var p = name.split(/\s+/); return (p[0][0] + (p[1] ? p[1][0] : '')).toUpperCase(); }
		return (email || '?').charAt(0).toUpperCase();
	}
	function avatar(name, email, cls) {
		var key = email || name || '?';
		return '<span class="om-avatar ' + (cls || '') + '" style="background:' + avColor(key) + '">' + esc(initials(name, email)) + '</span>';
	}

	/* ------------------------------- api ------------------------------ */
	function api(path, opts) {
		opts = opts || {};
		var url = CFG.rest + path;
		if (opts.query) {
			var qs = Object.keys(opts.query).filter(function (k) { return opts.query[k] !== '' && opts.query[k] != null; })
				.map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(opts.query[k]); }).join('&');
			if (qs) url += '?' + qs;
		}
		// Default to POST whenever there is a body/form — a GET/HEAD request may
		// never carry a body (the browser throws otherwise).
		var method = opts.method || ((opts.body || opts.form) ? 'POST' : 'GET');
		var init = { method: method, headers: { 'X-WP-Nonce': CFG.nonce }, credentials: 'same-origin' };
		if (opts.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
		if (opts.form) { init.method = 'POST'; init.body = opts.form; }
		return fetch(url, init).then(function (r) {
			return r.text().then(function (txt) {
				var j; try { j = txt ? JSON.parse(txt) : {}; } catch (e) { j = {}; }
				if (!r.ok) { var m = (j && j.message) ? j.message : ('HTTP ' + r.status); var err = new Error(m); err.status = r.status; throw err; }
				return j;
			});
		});
	}

	/* ------------------------------ toast ----------------------------- */
	function toast(msg, type) {
		var wrap = q('.om-toasts') || (function () { var w = document.createElement('div'); w.className = 'om-toasts'; document.body.appendChild(w); return w; })();
		var el = document.createElement('div'); el.className = 'om-toast ' + (type || ''); el.textContent = msg;
		wrap.appendChild(el);
		setTimeout(function () { el.style.transition = 'opacity .3s,transform .3s'; el.style.opacity = '0'; el.style.transform = 'translateY(8px)'; setTimeout(function () { el.remove(); }, 320); }, 3400);
	}

	/* ============================ SHELL ============================ */
	function buildShell() {
		var logo = CFG.logo
			? '<img src="' + esc(CFG.logo) + '" alt="Osoul">'
			: '<span class="mk">أ</span>';
		ROOT.innerHTML =
			'<div class="om-top">' +
				'<button class="om-ic om-menu-toggle" aria-label="menu">' + I.menu + '</button>' +
				'<span class="om-logo">' + logo + '<span class="om-logo-tx">' + esc(LANG === 'ar' ? 'بريد أصول البناء' : 'Osoul Mail') + '</span></span>' +
				'<div class="om-search"><span class="om-si">' + I.search + '</span>' +
					'<input type="text" placeholder="' + esc(t('search')) + '" autocomplete="off">' +
					'<button class="om-sx" aria-label="clear">' + I.close + '</button></div>' +
				'<div class="om-top-actions">' +
					'<button class="om-ic om-refresh" title="' + esc(t('refresh')) + '">' + I.refresh + '</button>' +
					'<div class="om-menu-wrap">' +
						'<button class="om-ic om-avatar-btn">' + avatar(S.name, S.email) + '</button>' +
						'<div class="om-menu">' +
							'<div class="who"><b>' + esc(S.name) + '</b><span>' + esc(S.email) + '</span></div>' +
							'<button data-act="settings">' + I.gear + ' ' + esc(t('settings')) + '</button>' +
							'<button data-act="theme">🌓 ' + esc(t('theme')) + '</button>' +
							'<button data-act="lang">🌐 ' + esc(t('language')) + '</button>' +
							'<button data-act="disconnect">✕ ' + esc(t('disconnect')) + '</button>' +
							'<button data-act="logout">⎋ ' + esc(t('logout')) + '</button>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>' +
			'<div class="om-main">' +
				'<div class="om-scrim"></div>' +
				'<aside class="om-rail">' +
					'<button class="om-compose">✎ ' + esc(t('compose')) + '</button>' +
					'<nav class="om-folders"></nav>' +
						'<button class="om-addfold">' + I.plus + ' <span>' + esc(t('add_folder')) + '</span></button>' +
						'<button class="om-contacts-nav">' + I.contacts + ' <span>' + esc(t('contacts')) + '</span></button>' +
						'<div class="om-quota"></div>' +
					'<div class="om-rail-foot">Osoul Albinaa • Webmail</div>' +
				'</aside>' +
				'<section class="om-list">' +
						'<div class="om-list-head"></div>' +
						'<div class="om-list-tools"></div>' +
						'<div class="om-bulk"></div>' +
						'<div class="om-rows"></div>' +
					'</section>' +
				'<section class="om-view"></section>' +
			'</div>';

		var search = q('.om-search input');
		var searchWrap = q('.om-search');
		var sTimer;
		on(search, 'input', function () {
			searchWrap.classList.toggle('has', !!search.value);
			clearTimeout(sTimer);
			sTimer = setTimeout(function () { S.search = search.value.trim(); S.page = 0; loadMessages(); }, 400);
		});
		on(q('.om-sx'), 'click', function () { search.value = ''; searchWrap.classList.remove('has'); S.search = ''; S.page = 0; loadMessages(); });
		on(q('.om-refresh'), 'click', function () { refreshAll(); });
		on(q('.om-compose'), 'click', function () { openCompose('new'); });
		on(q('.om-addfold'), 'click', addFolder);
		on(q('.om-contacts-nav'), 'click', openContacts);
		on(q('.om-menu-toggle'), 'click', function () { q('.om-rail').classList.toggle('open'); q('.om-scrim').classList.toggle('open'); });
		on(q('.om-scrim'), 'click', function () { q('.om-rail').classList.remove('open'); q('.om-scrim').classList.remove('open'); });

		var avBtn = q('.om-avatar-btn'), menu = q('.om-menu');
		on(avBtn, 'click', function (e) { e.stopPropagation(); menu.classList.toggle('open'); });
		on(document, 'click', function () { menu.classList.remove('open'); });
		on(menu, 'click', function (e) {
			var b = e.target.closest('button'); if (!b) return; var act = b.getAttribute('data-act');
			menu.classList.remove('open');
			if (act === 'logout') { location.href = CFG.logout; }
			else if (act === 'theme') { toggleTheme(); }
			else if (act === 'lang') { toggleLang(); }
			else if (act === 'settings') { openSettings(); }
			else if (act === 'disconnect') { doDisconnect(); }
		});
	}

	function toggleTheme() {
		var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
		var next = cur === 'dark' ? 'light' : 'dark';
		document.documentElement.setAttribute('data-theme', next);
		try { localStorage.setItem('osoul_theme', next); } catch (e) {}
	}
	function toggleLang() {
		var next = LANG === 'ar' ? 'en' : 'ar';
		try { localStorage.setItem('osoul_lang', next); } catch (e) {}
		document.cookie = 'osoul_lang=' + next + ';path=/;max-age=31536000;SameSite=Lax';
		location.reload();
	}

	/* ------------------------- folders (rail) ------------------------- */
	function renderFolders() {
		var nav = q('.om-folders'); if (!nav) return;
		nav.innerHTML = S.folders.map(function (f) {
			var icon = FOLDER_ICON[f.special] || I.folder;
			var label = f.special ? t(f.special) : f.display;
			var badge = (f.special === 'inbox' && f.unseen > 0)
				? '<span class="fc badge om-num">' + f.unseen + '</span>'
				: (f.unseen > 0 ? '<span class="fc om-num">' + f.unseen + '</span>' : '');
			return '<button class="om-fold' + (f.raw === S.folder ? ' sel' : '') + (f.unseen > 0 ? ' unread' : '') + '" data-raw="' + esc(f.raw) + '" data-special="' + esc(f.special) + '" data-display="' + esc(label) + '">' +
				'<span class="fi">' + icon + '</span><span class="fn">' + esc(label) + '</span>' + badge + '</button>';
		}).join('');
		qa('.om-fold', nav).forEach(function (btn) {
			on(btn, 'click', function () {
				S.folder = btn.getAttribute('data-raw');
				S.special = btn.getAttribute('data-special');
				S.display = btn.getAttribute('data-display');
				S.page = 0; S.search = ''; S.filter = ''; clearSel(); var si = q('.om-search input'); if (si) { si.value = ''; q('.om-search').classList.remove('has'); }
				q('.om-rail').classList.remove('open'); q('.om-scrim').classList.remove('open');
				renderFolders(); clearView(); loadMessages();
			});
		});
	}
	function pickInbox() {
		var inbox = S.folders.filter(function (f) { return f.special === 'inbox'; })[0] || S.folders[0];
		return inbox;
	}

	/* --------------------------- message list ------------------------- */
	function listHead() {
		var totalPages = Math.max(1, Math.ceil(S.total / S.per));
		var pager = S.total > S.per
			? '<div class="om-page"><button class="om-ic om-prev" ' + (S.page <= 0 ? 'disabled' : '') + '>' + (LANG === 'ar' ? '›' : '‹') + '</button>' +
				'<span><b>' + (S.page + 1) + '</b> ' + t('page_of') + ' ' + totalPages + '</span>' +
				'<button class="om-ic om-next" ' + (S.page + 1 >= totalPages ? 'disabled' : '') + '>' + (LANG === 'ar' ? '‹' : '›') + '</button></div>'
			: '';
		q('.om-list-head').innerHTML = '<span class="t">' + esc(S.search ? ('🔍 ' + S.search) : S.display) + '</span>' + pager;
		on(q('.om-prev'), 'click', function () { if (S.page > 0) { S.page--; loadMessages(); } });
		on(q('.om-next'), 'click', function () { S.page++; loadMessages(); });
	}
	function skeleton() {
		var rows = q('.om-rows'); var h = '';
		for (var i = 0; i < 8; i++) { h += '<div class="om-sk"><div class="c"></div><div class="l"><div class="b"></div><div class="b"></div><div class="b"></div></div></div>'; }
		rows.innerHTML = h;
	}
	// Filter tabs + sort control (below the folder title).
	function listTools() {
		var tabs = [['', 'f_all'], ['unread', 'f_unread'], ['read', 'f_read'], ['starred', 'f_starred']];
		var el = q('.om-list-tools'); if (!el) return;
		el.innerHTML =
			'<label class="om-selall" title="' + esc(t('select_all')) + '"><input type="checkbox" class="om-selall-cb"></label>' +
			'<div class="om-tabs">' + tabs.map(function (x) {
				return '<button class="om-tab' + (S.filter === x[0] ? ' on' : '') + '" data-f="' + x[0] + '">' + esc(t(x[1])) + '</button>';
			}).join('') + '</div>' +
			'<button class="om-sort" title="' + esc(t('sort')) + '">' + I.sort + '<span>' + esc(S.sort === 'oldest' ? t('oldest') : t('newest')) + '</span></button>';
		qa('.om-tab', el).forEach(function (b) {
			on(b, 'click', function () { S.filter = b.getAttribute('data-f'); S.page = 0; clearSel(); loadMessages(); });
		});
		on(q('.om-sort', el), 'click', function () { S.sort = (S.sort === 'oldest') ? 'newest' : 'oldest'; S.page = 0; loadMessages(); });
		on(q('.om-selall-cb', el), 'change', function () {
			var on_ = this.checked; S.sel = {};
			if (on_) { S.messages.forEach(function (m) { S.sel[m.uid] = true; }); }
			renderList(); renderBulk();
		});
	}
	function loadMessages() {
		listHead(); listTools(); skeleton();
		api('messages', { query: { folder: S.folder, page: S.page, search: S.search, filter: S.filter, sort: S.sort } }).then(function (r) {
			S.total = r.total; S.messages = r.messages || [];
			listHead(); renderList(); renderBulk();
		}).catch(function (e) { q('.om-rows').innerHTML = '<div class="om-empty"><div class="h">' + esc(e.message) + '</div></div>'; });
	}
	function renderList() {
		var rows = q('.om-rows');
		if (!S.messages.length) {
			rows.innerHTML = '<div class="om-empty">' + I.mail + '<div class="h">' + esc(t('no_messages')) + '</div></div>';
			return;
		}
		var showTo = (S.special === 'sent' || S.special === 'drafts');
		rows.innerHTML = S.messages.map(function (m) {
			var who = showTo
				? (m.to && m.to.length ? (m.to[0].name || m.to[0].email) + (m.to.length > 1 ? ' +' + (m.to.length - 1) : '') : '—')
				: (m.from.name || m.from.email || '—');
			var whoKey = showTo ? (m.to && m.to[0] ? m.to[0].email : '') : m.from.email;
			var whoName = showTo ? (m.to && m.to[0] ? m.to[0].name : '') : m.from.name;
			var checked = S.sel[m.uid] ? ' checked' : '';
			return '<div class="om-row' + (m.seen ? '' : ' unread') + (m.uid === S.currentUid ? ' sel' : '') + (S.sel[m.uid] ? ' picked' : '') + '" data-uid="' + m.uid + '">' +
				'<label class="om-r-check"><input type="checkbox" data-uid="' + m.uid + '"' + checked + '></label>' +
				'<div class="om-r-av">' + avatar(whoName, whoKey) + '</div>' +
				'<div class="om-r-body">' +
					'<div class="om-r-line1">' + (m.seen ? '' : '<span class="om-r-dot"></span>') +
						'<span class="om-r-from">' + esc(who) + '</span>' +
						'<span class="om-r-date om-num">' + esc(m.date_fmt) + '</span></div>' +
					'<div class="om-r-sub">' + esc(m.subject || t('no_subject')) + '</div>' +
					'<div class="om-r-meta"><span class="om-r-snip">' + (m.has_attach ? '<span class="om-clip">' + I.attach + '</span> ' : '') + '</span></div>' +
				'</div>' +
				'<button class="om-r-star' + (m.flagged ? ' on' : '') + '" data-uid="' + m.uid + '" title="star">' + (m.flagged ? I.starf : I.star) + '</button>' +
			'</div>';
		}).join('');
		qa('.om-row', rows).forEach(function (row) {
			on(row, 'click', function (e) {
				if (e.target.closest('.om-r-star') || e.target.closest('.om-r-check')) return;
				openMessage(+row.getAttribute('data-uid'));
			});
		});
		qa('.om-r-star', rows).forEach(function (st) {
			on(st, 'click', function (e) { e.stopPropagation(); toggleStar(+st.getAttribute('data-uid'), st); });
		});
		qa('.om-r-check input', rows).forEach(function (cb) {
			on(cb, 'change', function (e) {
				e.stopPropagation(); var uid = +cb.getAttribute('data-uid');
				if (cb.checked) { S.sel[uid] = true; } else { delete S.sel[uid]; }
				cb.closest('.om-row').classList.toggle('picked', cb.checked);
				renderBulk();
			});
		});
	}

	/* --------------------------- bulk selection ----------------------- */
	function clearSel() { S.sel = {}; }
	function selCount() { return Object.keys(S.sel).length; }
	function renderBulk() {
		var bar = q('.om-bulk'); if (!bar) return;
		var n = selCount();
		var cb = q('.om-selall-cb'); if (cb) { cb.checked = (n > 0 && n === S.messages.length); cb.indeterminate = (n > 0 && n < S.messages.length); }
		if (!n) { bar.classList.remove('show'); bar.innerHTML = ''; return; }
		bar.classList.add('show');
		bar.innerHTML = '<span class="om-bulk-n om-num">' + n + '</span><span class="om-bulk-lbl">' + esc(t('selected')) + '</span>' +
			'<span style="flex:1"></span>' +
			'<button class="om-bulk-b" data-a="read">' + esc(t('b_read')) + '</button>' +
			'<button class="om-bulk-b" data-a="unread">' + esc(t('b_unread')) + '</button>' +
			'<button class="om-bulk-b" data-a="star">' + I.star + '</button>' +
			'<button class="om-bulk-b danger" data-a="delete">' + I.trash + '</button>' +
			'<button class="om-bulk-x" title="' + esc(t('b_cancel')) + '">' + I.close + '</button>';
		qa('.om-bulk-b', bar).forEach(function (b) { on(b, 'click', function () { bulkAction(b.getAttribute('data-a')); }); });
		on(q('.om-bulk-x', bar), 'click', function () { clearSel(); renderList(); renderBulk(); });
	}
	function bulkAction(action) {
		var uids = Object.keys(S.sel).map(Number); if (!uids.length) return;
		api('batch', { body: { folder: S.folder, action: action, uids: uids } }).then(function () {
			if (action === 'delete') {
				uids.forEach(function (u) { S.messages = S.messages.filter(function (m) { return m.uid !== u; }); });
				S.total = Math.max(0, S.total - uids.length);
				toast(t('deleted_n'), 'ok');
			} else if (action === 'read' || action === 'unread') {
				S.messages.forEach(function (m) { if (S.sel[m.uid]) m.seen = (action === 'read'); });
				toast(t('done_n'), 'ok');
			} else if (action === 'star') {
				S.messages.forEach(function (m) { if (S.sel[m.uid]) m.flagged = true; });
			}
			clearSel(); renderList(); renderBulk(); refreshFoldersQuiet();
			if (!S.messages.length) loadMessages();
		}).catch(function (e) { toast(e.message, 'err'); });
	}

	function toggleStar(uid, btn) {
		var m = S.messages.filter(function (x) { return x.uid === uid; })[0]; if (!m) return;
		var on_ = !m.flagged; m.flagged = on_;
		btn.classList.toggle('on', on_); btn.innerHTML = on_ ? I.starf : I.star;
		api('flag', { body: { folder: S.folder, uid: uid, flag: 'flagged', on: on_ } }).catch(function () {});
	}

	/* ---------------------------- reading pane ------------------------ */
	function clearView() {
		S.currentUid = 0; S.currentMsg = null;
		q('.om-view').innerHTML = '<div class="om-view-empty">' + I.mail + '<div>' + esc(t('pick_message')) + '</div></div>';
		setReading(false);
	}
	function setReading(on_) { var app = q('.om-app') || ROOT; if (app) app.classList.toggle('reading', !!on_); }

	function openMessage(uid) {
		S.currentUid = uid;
		qa('.om-row').forEach(function (r) { r.classList.toggle('sel', +r.getAttribute('data-uid') === uid); });
		var row = S.messages.filter(function (m) { return m.uid === uid; })[0];
		if (row && !row.seen) { row.seen = true; var rn = q('.om-row[data-uid="' + uid + '"]'); if (rn) { rn.classList.remove('unread'); var d = q('.om-r-dot', rn); if (d) d.remove(); } bumpUnread(-1); }
		var view = q('.om-view');
		view.innerHTML = '<div class="om-view-empty"><span class="om-spin"></span></div>';
		setReading(true);
		api('message', { query: { folder: S.folder, uid: uid } }).then(function (m) {
			if (S.currentUid !== uid) return;
			S.currentMsg = m; renderMessage(m);
		}).catch(function (e) { view.innerHTML = '<div class="om-view-empty"><div>' + esc(e.message) + '</div></div>'; });
	}

	function renderMessage(m) {
		var view = q('.om-view');
		var toLine = (m.to || []).map(function (a) { return esc(a.name || a.email); }).join(', ');
		var ccLine = (m.cc && m.cc.length) ? ('<br>' + esc(t('cc')) + ': ' + m.cc.map(function (a) { return esc(a.name || a.email); }).join(', ')) : '';
		var atts = (m.attachments || []).map(function (a) {
			var ext = (a.name.split('.').pop() || 'file').slice(0, 4);
			return '<button class="om-att" data-index="' + a.index + '" data-name="' + esc(a.name) + '"><span class="ai">' + esc(ext) + '</span><span><span class="an">' + esc(a.name) + '</span><span class="as om-num">' + fmtBytes(a.size) + '</span></span></button>';
		}).join('');

		view.innerHTML =
			'<div class="om-msg">' +
				'<div class="om-msg-bar">' +
					'<button class="om-ic om-back" title="back">' + I.back + '</button>' +
					'<button class="om-btn primary" data-act="reply">' + I.reply + ' <span>' + esc(t('reply')) + '</span></button>' +
					'<button class="om-btn" data-act="replyall">' + I.replyall + ' <span>' + esc(t('reply_all')) + '</span></button>' +
					'<button class="om-btn" data-act="forward">' + I.forward + ' <span>' + esc(t('forward')) + '</span></button>' +
					'<span style="flex:1"></span>' +
					'<button class="om-btn icon" data-act="unread" title="' + esc(t('mark_unread')) + '">✉</button>' +
					'<button class="om-btn icon" data-act="movemenu" title="' + esc(t('move')) + '">' + I.archive + '</button>' +
					'<button class="om-btn icon danger" data-act="delete" title="' + esc(t('delete')) + '">' + I.trash + '</button>' +
				'</div>' +
				'<div class="om-msg-scroll"><div class="om-msg-card">' +
					'<div class="om-msg-h">' +
						'<div class="om-msg-subj">' + esc(m.subject || t('no_subject')) + '</div>' +
						'<div class="om-msg-from">' + avatar(m.from.name, m.from.email) +
							'<div class="om-mf-info"><div class="om-mf-name">' + esc(m.from.name || m.from.email) + '</div>' +
							'<div class="om-mf-mail">' + esc(m.from.email) + '</div></div>' +
							'<span class="om-mf-date om-num">' + esc(m.date_fmt) + '</span>' +
							'<button class="om-mf-star' + (m.flagged ? ' on' : '') + '" data-act="star">' + (m.flagged ? I.starf : I.star) + '</button>' +
						'</div>' +
						'<div class="om-msg-to">' + esc(t('to_prefix')) + toLine + ccLine + '</div>' +
					'</div>' +
					(atts ? '<div class="om-att-row">' + atts + '</div>' : '') +
					'<div class="om-msg-body"></div>' +
				'</div></div>' +
			'</div>';

		// Body in a sandboxed, auto-sized iframe (or plain text).
		var body = q('.om-msg-body', view);
		if (m.html) {
			var iframe = document.createElement('iframe');
			iframe.className = 'om-frame';
			iframe.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
			var doc = '<!doctype html><html dir="' + (LANG === 'ar' ? 'rtl' : 'ltr') + '"><head><meta charset="utf-8"><base target="_blank">' +
				'<style>html,body{margin:0}body{font-family:\'Readex Pro\',\'IBM Plex Sans\',system-ui,sans-serif;color:#1a2432;background:#fff;padding:20px;word-wrap:break-word;overflow-x:auto;line-height:1.75}img{max-width:100%;height:auto}a{color:#0B84D6}table{max-width:100%}</style></head><body>' + m.html + '</body></html>';
			iframe.srcdoc = doc;
			body.appendChild(iframe);
			iframe.addEventListener('load', function () {
				try { var h = iframe.contentDocument.body.scrollHeight; iframe.style.height = (h + 30) + 'px'; } catch (e) { iframe.style.height = '600px'; }
			});
		} else {
			body.innerHTML = '<div class="om-plain">' + esc(m.text || '') + '</div>';
		}

		// Wire actions.
		var bar = q('.om-msg-bar', view);
		on(bar, 'click', function (e) {
			var b = e.target.closest('[data-act]'); if (!b) return; var act = b.getAttribute('data-act');
			if (act === 'reply') openCompose('reply', m);
			else if (act === 'replyall') openCompose('replyall', m);
			else if (act === 'forward') openCompose('forward', m);
			else if (act === 'delete') deleteMessage(m.uid);
			else if (act === 'unread') markUnread(m.uid);
			else if (act === 'movemenu') openMoveMenu(b, m.uid);
		});
		on(q('.om-back', view), 'click', function () { setReading(false); });
		on(q('.om-mf-star', view), 'click', function () {
			m.flagged = !m.flagged; var b = q('.om-mf-star', view); b.classList.toggle('on', m.flagged); b.innerHTML = m.flagged ? I.starf : I.star;
			var lr = S.messages.filter(function (x) { return x.uid === m.uid; })[0]; if (lr) lr.flagged = m.flagged;
			var ls = q('.om-r-star[data-uid="' + m.uid + '"]'); if (ls) { ls.classList.toggle('on', m.flagged); ls.innerHTML = m.flagged ? I.starf : I.star; }
			api('flag', { body: { folder: S.folder, uid: m.uid, flag: 'flagged', on: m.flagged } }).catch(function () {});
		});
		qa('.om-att', view).forEach(function (a) {
			on(a, 'click', function () { downloadAttachment(m.uid, +a.getAttribute('data-index'), a.getAttribute('data-name')); });
		});
	}

	function bumpUnread(delta) {
		var f = S.folders.filter(function (x) { return x.raw === S.folder; })[0];
		if (f) { f.unseen = Math.max(0, (f.unseen || 0) + delta); renderFolders(); }
	}

	function downloadAttachment(uid, index, name) {
		toast(t('download') + '…');
		api('attachment', { query: { folder: S.folder, uid: uid, index: index } }).then(function (r) {
			var bin = atob(r.b64), len = bin.length, arr = new Uint8Array(len);
			for (var i = 0; i < len; i++) arr[i] = bin.charCodeAt(i);
			var blob = new Blob([arr], { type: r.mime || 'application/octet-stream' });
			var url = URL.createObjectURL(blob), a = document.createElement('a');
			a.href = url; a.download = r.name || name || 'attachment'; document.body.appendChild(a); a.click();
			setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
		}).catch(function (e) { toast(e.message, 'err'); });
	}

	function deleteMessage(uid) {
		var isTrash = S.special === 'trash';
		if (isTrash && !confirm(t('confirm_purge'))) return;
		api('delete', { body: { folder: S.folder, uid: uid } }).then(function (r) {
			removeRow(uid);
			toast(r.mode === 'purged' ? t('purged_ok') : t('deleted_ok'), 'ok');
			if (!isTrash) bumpUnread(0);
			refreshFoldersQuiet();
		}).catch(function (e) { toast(e.message, 'err'); });
	}
	function markUnread(uid) {
		api('flag', { body: { folder: S.folder, uid: uid, flag: 'seen', on: false } }).then(function () {
			var m = S.messages.filter(function (x) { return x.uid === uid; })[0]; if (m) { m.seen = false; }
			renderList(); bumpUnread(1); clearView(); setReading(false);
		}).catch(function (e) { toast(e.message, 'err'); });
	}
	function openMoveMenu(anchor, uid) {
		closeAnyMenu();
		var dests = S.folders.filter(function (f) { return f.raw !== S.folder && ['inbox', 'archive', 'junk', 'trash', ''].indexOf(f.special) >= 0; });
		var menu = document.createElement('div'); menu.className = 'om-menu open om-move-menu';
		menu.style.position = 'fixed'; menu.style.zIndex = 90;
		menu.innerHTML = dests.map(function (f) { return '<button data-raw="' + esc(f.raw) + '">' + (FOLDER_ICON[f.special] || I.folder) + ' ' + esc(f.special ? t(f.special) : f.display) + '</button>'; }).join('');
		document.body.appendChild(menu);
		var r = anchor.getBoundingClientRect(); menu.style.top = (r.bottom + 6) + 'px';
		menu.style.left = Math.max(8, r.right - 220) + 'px';
		qa('button', menu).forEach(function (b) { on(b, 'click', function () { doMove(uid, b.getAttribute('data-raw')); menu.remove(); }); });
		setTimeout(function () { on(document, 'click', function h() { menu.remove(); document.removeEventListener('click', h); }); }, 10);
	}
	function doMove(uid, destRaw) {
		api('move', { body: { folder: S.folder, uid: uid, dest_raw: destRaw } }).then(function () {
			removeRow(uid); toast(t('moved_ok'), 'ok'); refreshFoldersQuiet();
		}).catch(function (e) { toast(e.message, 'err'); });
	}
	function removeRow(uid) {
		S.messages = S.messages.filter(function (m) { return m.uid !== uid; });
		S.total = Math.max(0, S.total - 1);
		var rn = q('.om-row[data-uid="' + uid + '"]'); if (rn) rn.remove();
		if (S.currentUid === uid) { clearView(); setReading(false); }
		if (!S.messages.length) loadMessages();
	}
	function closeAnyMenu() { qa('.om-move-menu').forEach(function (m) { m.remove(); }); }

	/* ============================ COMPOSE ============================ */
	var composeState = null;
	function openCompose(mode, m) {
		if (q('.om-compose-dock')) q('.om-compose-dock').remove();
		var to = '', cc = '', subject = '', quoted = '', irt = '', refs = '';
		var sig = S.signature ? ('<br><br>--<br>' + S.signature) : '';
		if (mode === 'reply' || mode === 'replyall') {
			to = (m.reply_to && m.reply_to.email) ? m.reply_to.email : (m.from.email || '');
			if (mode === 'replyall') {
				var others = (m.to || []).concat(m.cc || []).map(function (a) { return a.email; })
					.filter(function (e) { return e && e.toLowerCase() !== (S.email || '').toLowerCase() && e.toLowerCase() !== (m.from.email || '').toLowerCase(); });
				cc = Array.from(new Set(others)).join(', ');
			}
			subject = /^re:/i.test(m.subject) ? m.subject : ('Re: ' + (m.subject || ''));
			irt = m.message_id; refs = m.references || m.message_id;
			quoted = quoteBlock(m);
		} else if (mode === 'forward') {
			subject = /^fwd:/i.test(m.subject) ? m.subject : ('Fwd: ' + (m.subject || ''));
			quoted = quoteBlock(m);
		}
		composeState = { atts: [], irt: irt, refs: refs, mode: mode };

		var dock = document.createElement('div'); dock.className = 'om-compose-dock';
		dock.innerHTML =
			'<div class="om-cd-head"><span class="t">' + esc(mode === 'new' ? t('new_message') : subject || t('new_message')) + '</span>' +
				'<button class="om-ic om-cd-expand" title="' + esc(t('expand')) + '">' + I.expand + '</button>' +
				'<button class="om-ic om-cd-min">–</button><button class="om-ic om-cd-close">' + I.close + '</button></div>' +
			'<div class="om-cd-body">' +
				'<div class="om-cd-fields">' +
					'<div class="om-cd-fld"><label>' + esc(t('to')) + '</label><input class="cto" type="text" autocomplete="off" value="' + esc(to) + '"><span class="ccbcc"><button class="show-cc">' + esc(t('cc')) + '</button><button class="show-bcc">' + esc(t('bcc')) + '</button></span></div>' +
					'<div class="om-cd-fld cc-row om-hide"><label>' + esc(t('cc')) + '</label><input class="ccc" type="text" autocomplete="off" value="' + esc(cc) + '"></div>' +
					'<div class="om-cd-fld bcc-row om-hide"><label>' + esc(t('bcc')) + '</label><input class="cbcc" type="text" autocomplete="off"></div>' +
					'<div class="om-cd-fld"><label>' + esc(t('subject')) + '</label><input class="csubj" type="text" value="' + esc(subject) + '"></div>' +
				'</div>' +
				'<div class="om-cd-editor" contenteditable="true" data-ph="…"></div>' +
				'<div class="om-cd-atts"></div>' +
				'<div class="om-cd-tools">' +
					'<button class="om-tool" data-cmd="bold" title="Bold">B</button>' +
					'<button class="om-tool" data-cmd="italic" title="Italic" style="font-style:italic">I</button>' +
					'<button class="om-tool" data-cmd="underline" title="Underline" style="text-decoration:underline">U</button>' +
					'<button class="om-tool" data-cmd="insertUnorderedList" title="List">•</button>' +
					'<button class="om-tool" data-cmd="createLink" title="Link">🔗</button>' +
					'<button class="om-tool om-attach" title="' + esc(t('attach')) + '">' + I.attach + '</button>' +
					'<input type="file" class="om-file" multiple style="display:none">' +
					'<span class="sp"></span>' +
					'<button class="om-btn om-draft">' + esc(t('save_draft')) + '</button>' +
					'<button class="om-send">✉ <span>' + esc(t('send')) + '</span></button>' +
				'</div>' +
			'</div>';
		document.body.appendChild(dock);

		var editor = q('.om-cd-editor', dock);
		editor.innerHTML = (mode === 'new' ? sig : (sig + quoted));
		if (mode === 'reply' || mode === 'replyall') { editor.focus(); moveCaretStart(editor); }
		else if (mode === 'new') { q('.cto', dock).focus(); }

		on(q('.om-cd-close', dock), 'click', function () { dock.remove(); });
		on(q('.om-cd-min', dock), 'click', function () { dock.classList.remove('full'); dock.classList.toggle('min'); });
		on(q('.om-cd-expand', dock), 'click', function () {
			dock.classList.remove('min'); var full = dock.classList.toggle('full');
			q('.om-cd-expand', dock).innerHTML = full ? I.compress : I.expand;
		});
		on(q('.om-cd-head', dock), 'click', function (e) { if (e.target.closest('.om-ic')) return; dock.classList.toggle('min'); });
		on(q('.show-cc', dock), 'click', function () { q('.cc-row', dock).classList.toggle('om-hide'); });
		on(q('.show-bcc', dock), 'click', function () { q('.bcc-row', dock).classList.toggle('om-hide'); });

		qa('.om-tool[data-cmd]', dock).forEach(function (b) {
			on(b, 'mousedown', function (e) { e.preventDefault(); });
			on(b, 'click', function () {
				var cmd = b.getAttribute('data-cmd');
				if (cmd === 'createLink') { var url = prompt(t('link_prompt'), 'https://'); if (url) document.execCommand('createLink', false, url); }
				else document.execCommand(cmd, false, null);
				editor.focus();
			});
		});
		var fileInput = q('.om-file', dock);
		on(q('.om-attach', dock), 'click', function () { fileInput.click(); });
		on(fileInput, 'change', function () { Array.prototype.forEach.call(fileInput.files, uploadAttachment); fileInput.value = ''; });
		on(q('.om-send', dock), 'click', function () { doSend(dock, false); });
		on(q('.om-draft', dock), 'click', function () { doSend(dock, true); });

		// recipient autocomplete
		[q('.cto', dock), q('.ccc', dock), q('.cbcc', dock)].forEach(setupAutocomplete);
	}

	function quoteBlock(m) {
		var head = (m.from.name || m.from.email) + ' ' + t('on_wrote');
		var inner = m.html ? m.html : ('<pre style="white-space:pre-wrap;font-family:inherit">' + esc(m.text || '') + '</pre>');
		return '<br><br><div style="border-inline-start:3px solid #d0d7e2;padding-inline-start:12px;color:#555">' +
			'<div style="color:#888;font-size:12px;margin-bottom:6px">— ' + esc(head) + '</div>' + inner + '</div>';
	}
	function moveCaretStart(el) { try { var r = document.createRange(); r.setStart(el, 0); r.collapse(true); var s = getSelection(); s.removeAllRanges(); s.addRange(r); } catch (e) {} }

	function uploadAttachment(file) {
		var chips = q('.om-cd-atts'); if (!chips) return;
		var chip = document.createElement('div'); chip.className = 'om-cd-att up';
		chip.innerHTML = '<span>' + esc(file.name) + '</span> <span class="om-spin" style="width:12px;height:12px;border-width:2px"></span>';
		chips.appendChild(chip);
		var fd = new FormData(); fd.append('file', file);
		api('upload', { form: fd }).then(function (r) {
			composeState.atts.push({ token: r.token, name: r.name, size: r.size });
			chip.className = 'om-cd-att';
			chip.innerHTML = '<span>' + esc(r.name) + ' (' + fmtBytes(r.size) + ')</span><button class="x" title="remove">×</button>';
			on(q('.x', chip), 'click', function () {
				composeState.atts = composeState.atts.filter(function (a) { return a.token !== r.token; });
				chip.remove();
			});
		}).catch(function (e) { chip.className = 'om-cd-att'; chip.innerHTML = '<span style="color:var(--err)">' + esc(file.name) + ' ✕</span>'; toast(e.message, 'err'); });
	}

	function doSend(dock, draft) {
		var to = q('.cto', dock).value.trim();
		var cc = q('.ccc', dock).value.trim();
		var bcc = q('.cbcc', dock).value.trim();
		var subj = q('.csubj', dock).value.trim();
		var bodyHtml = q('.om-cd-editor', dock).innerHTML;
		if (!draft && !to && !cc && !bcc) { toast(t('need_rcpt'), 'err'); return; }
		var sendBtn = q('.om-send', dock), draftBtn = q('.om-draft', dock);
		sendBtn.disabled = true; draftBtn.disabled = true;
		sendBtn.innerHTML = '<span class="om-spin" style="width:14px;height:14px;border-width:2px"></span> ' + esc(t('sending'));
		api('send', { body: {
			to: to, cc: cc, bcc: bcc, subject: subj, body_html: bodyHtml, draft: draft,
			attachments: composeState.atts.map(function (a) { return a.token; }),
			in_reply_to: composeState.irt, references: composeState.refs
		} }).then(function () {
			dock.remove();
			toast(draft ? t('draft_ok') : t('sent_ok'), 'ok');
			refreshFoldersQuiet();
			if (draft && S.special === 'drafts') loadMessages();
			if (!draft && S.special === 'sent') loadMessages();
		}).catch(function (e) {
			sendBtn.disabled = false; draftBtn.disabled = false; sendBtn.innerHTML = '✉ <span>' + esc(t('send')) + '</span>';
			toast(e.message, 'err');
		});
	}

	/* -------------------------- autocomplete -------------------------- */
	function setupAutocomplete(input) {
		if (!input) return;
		var box = document.createElement('div'); box.className = 'om-ac'; document.body.appendChild(box);
		var hi = -1, matches = [];
		function currentToken() { var v = input.value; var i = Math.max(v.lastIndexOf(','), v.lastIndexOf(';')); return v.slice(i + 1).trim(); }
		function place() { var r = input.getBoundingClientRect(); box.style.position = 'fixed'; box.style.top = (r.bottom + 2) + 'px'; box.style.left = r.left + 'px'; box.style.minWidth = r.width + 'px'; }
		function close() { box.classList.remove('open'); hi = -1; }
		function pick(c) {
			var v = input.value; var i = Math.max(v.lastIndexOf(','), v.lastIndexOf(';'));
			input.value = (i >= 0 ? v.slice(0, i + 1) + ' ' : '') + c.email + ', '; close(); input.focus();
		}
		on(input, 'input', function () {
			var tok = currentToken().toLowerCase(); if (tok.length < 1) { close(); return; }
			matches = S.contacts.filter(function (c) { return (c.email + ' ' + (c.name || '')).toLowerCase().indexOf(tok) >= 0; }).slice(0, 6);
			if (!matches.length) { close(); return; }
			place();
			box.innerHTML = matches.map(function (c, i) { return '<div data-i="' + i + '">' + esc(c.name || c.email) + ' <span class="e">' + esc(c.email) + '</span></div>'; }).join('');
			box.classList.add('open'); hi = -1;
			qa('div', box).forEach(function (d) { on(d, 'mousedown', function (e) { e.preventDefault(); pick(matches[+d.getAttribute('data-i')]); }); });
		});
		on(input, 'keydown', function (e) {
			if (!box.classList.contains('open')) return;
			if (e.key === 'ArrowDown') { hi = Math.min(matches.length - 1, hi + 1); }
			else if (e.key === 'ArrowUp') { hi = Math.max(0, hi - 1); }
			else if (e.key === 'Enter' && hi >= 0) { e.preventDefault(); pick(matches[hi]); return; }
			else if (e.key === 'Escape') { close(); return; }
			else return;
			e.preventDefault();
			qa('div', box).forEach(function (d, i) { d.classList.toggle('hi', i === hi); });
		});
		on(input, 'blur', function () { setTimeout(close, 150); });
	}

	/* ---------------------------- settings ---------------------------- */
	function openSettings() {
		var modal = document.createElement('div'); modal.className = 'om-modal';
		modal.innerHTML = '<div class="om-modal-card"><h3>' + esc(t('settings')) + '</h3>' +
			'<div class="om-field"><label>' + esc(t('display_name')) + '</label><input class="om-input set-name" value="' + esc(S.from_name) + '"></div>' +
			'<div class="om-field"><label>' + esc(t('signature')) + '</label><textarea class="om-input set-sig">' + esc(S.signature) + '</textarea></div>' +
			'<div class="om-modal-actions"><button class="om-btn set-cancel">' + esc(t('cancel')) + '</button><button class="om-btn primary set-save">' + esc(t('save')) + '</button></div></div>';
		document.body.appendChild(modal);
		on(modal, 'click', function (e) { if (e.target === modal) modal.remove(); });
		on(q('.set-cancel', modal), 'click', function () { modal.remove(); });
		on(q('.set-save', modal), 'click', function () {
			var name = q('.set-name', modal).value.trim(), sig = q('.set-sig', modal).value;
			api('profile', { body: { from_name: name, signature: sig } }).then(function (r) {
				S.from_name = r.from_name || name; S.signature = r.signature; modal.remove(); toast(t('saved_ok'), 'ok');
			}).catch(function (e) { toast(e.message, 'err'); });
		});
	}
	function doDisconnect() {
		if (!confirm(t('disconnect') + '؟')) return;
		api('disconnect', { method: 'POST', body: {} }).then(function () { location.reload(); }).catch(function (e) { toast(e.message, 'err'); });
	}

	/* --------------------------- onboarding --------------------------- */
	function renderOnboard(err) {
		var b = S.bootstrap || {};
		var d = b.defaults || { imap_host: 'imap.hostinger.com', imap_port: 993, smtp_host: 'smtp.hostinger.com', smtp_port: 465 };
		ROOT.innerHTML =
			'<div class="om-top"><span class="om-logo">' + (CFG.logo ? '<img src="' + esc(CFG.logo) + '">' : '<span class="mk">أ</span>') + '<span>' + esc(LANG === 'ar' ? 'بريد أصول البناء' : 'Osoul Mail') + '</span></span>' +
				'<div class="om-top-actions"><button class="om-ic ob-lang">🌐</button><button class="om-ic ob-logout" title="' + esc(t('logout')) + '">⎋</button></div></div>' +
			'<div class="om-onb"><div class="om-onb-card">' +
				'<div class="om-onb-ic">' + I.mail + '</div>' +
				'<h2>' + esc(t('connect_title')) + '</h2><p class="sub">' + esc(t('connect_sub')) + '</p>' +
				'<div class="om-alert err' + (err ? ' show' : '') + '">' + esc(err || '') + '</div>' +
				'<div class="om-note"><span>ℹ️</span><span>' + esc(t('hoster_note')) + '</span></div>' +
				'<div class="om-field"><label>' + esc(t('email')) + '</label><input class="om-input ob-email" type="email" dir="ltr" value="' + esc(b.email || CFG.email || '') + '" placeholder="name@osoulalbinaa.com"></div>' +
				'<div class="om-field"><label>' + esc(t('password')) + '</label><div class="om-pass-wrap"><input class="om-input ob-pass" type="password" dir="ltr" autocomplete="off"><button class="om-eye" type="button">👁</button></div></div>' +
				'<div class="om-field"><label>' + esc(t('display_name')) + '</label><input class="om-input ob-name" type="text" value="' + esc(b.from_name || CFG.name || '') + '"></div>' +
				'<button class="om-adv-toggle" type="button">⚙ ' + esc(t('advanced')) + '</button>' +
				'<div class="om-adv">' +
					'<div class="om-field"><label>' + esc(t('imap_host')) + '</label><input class="om-input ob-ih" dir="ltr" value="' + esc(b.imap_host || d.imap_host) + '"></div>' +
					'<div class="om-field"><label>' + esc(t('imap_port')) + '</label><input class="om-input ob-ip" dir="ltr" value="' + esc(b.imap_port || d.imap_port) + '"></div>' +
					'<div class="om-field"><label>' + esc(t('smtp_host')) + '</label><input class="om-input ob-sh" dir="ltr" value="' + esc(b.smtp_host || d.smtp_host) + '"></div>' +
					'<div class="om-field"><label>' + esc(t('smtp_port')) + '</label><input class="om-input ob-sp" dir="ltr" value="' + esc(b.smtp_port || d.smtp_port) + '"></div>' +
				'</div>' +
				'<button class="om-btn primary om-full ob-connect">' + esc(t('connect')) + '</button>' +
			'</div></div>';
		on(q('.ob-logout'), 'click', function () { location.href = CFG.logout; });
		on(q('.ob-lang'), 'click', toggleLang);
		on(q('.om-adv-toggle'), 'click', function () { q('.om-adv').classList.toggle('open'); });
		on(q('.om-eye'), 'click', function () { var p = q('.ob-pass'); p.type = p.type === 'password' ? 'text' : 'password'; });
		on(q('.ob-connect'), 'click', function () {
			var btn = q('.ob-connect'), alert = q('.om-alert');
			var payload = {
				email: q('.ob-email').value.trim(), password: q('.ob-pass').value,
				from_name: q('.ob-name').value.trim(),
				imap_host: q('.ob-ih').value.trim(), imap_port: q('.ob-ip').value.trim(),
				smtp_host: q('.ob-sh').value.trim(), smtp_port: q('.ob-sp').value.trim()
			};
			alert.classList.remove('show'); btn.disabled = true; btn.innerHTML = '<span class="om-spin" style="width:16px;height:16px;border-width:2px"></span> ' + esc(t('connecting'));
			api('connect', { body: payload }).then(function () { start(); }).catch(function (e) {
				btn.disabled = false; btn.textContent = t('connect');
				alert.textContent = e.message; alert.classList.add('show', 'err');
			});
		});
		var pass = q('.ob-pass'); if (pass) on(pass, 'keydown', function (e) { if (e.key === 'Enter') q('.ob-connect').click(); });
	}

	/* --------------------------- storage meter ------------------------ */
	function loadQuota() {
		api('quota').then(function (q_) {
			var el = q('.om-quota'); if (!el) return;
			if (!q_ || !q_.total) { el.innerHTML = ''; return; }
			var pct = Math.min(100, Math.round(q_.used / q_.total * 100));
			var hot = pct >= 85 ? ' hot' : '';
			el.innerHTML = '<div class="om-quota-lbl"><span>' + esc(t('storage')) + '</span><span class="om-num">' + pct + '%</span></div>' +
				'<div class="om-quota-bar' + hot + '"><i style="width:' + pct + '%"></i></div>' +
				'<div class="om-quota-sub om-num">' + fmtBytes(q_.used) + ' / ' + fmtBytes(q_.total) + '</div>';
		}).catch(function () {});
	}

	/* --------------------------- custom folder ------------------------ */
	function addFolder() {
		var name = prompt(t('folder_name'), ''); if (name == null) return;
		name = name.trim(); if (!name) return;
		api('folder-create', { body: { name: name } }).then(function () {
			toast(t('new_folder_ok'), 'ok'); refreshFoldersQuiet();
		}).catch(function (e) { toast(e.message, 'err'); });
	}

	/* --------------------------- contacts view ------------------------ */
	function openContacts() {
		q('.om-rail').classList.remove('open'); q('.om-scrim').classList.remove('open');
		var modal = document.createElement('div'); modal.className = 'om-modal';
		var list = S.contacts && S.contacts.length
			? '<div class="om-contacts-list">' + S.contacts.map(function (c) {
				return '<button class="om-contact" data-email="' + esc(c.email) + '">' + avatar(c.name, c.email) +
					'<span class="om-contact-i"><b>' + esc(c.name || c.email) + '</b><span>' + esc(c.email) + '</span></span></button>';
			}).join('') + '</div>'
			: '<div class="om-empty" style="padding:34px">' + I.contacts + '<div class="h">' + esc(t('no_contacts')) + '</div></div>';
		modal.innerHTML = '<div class="om-modal-card"><h3>' + I.contacts + ' ' + esc(t('contacts')) + '</h3>' + list +
			'<div class="om-modal-actions"><button class="om-btn om-c-close">' + esc(t('cancel')) + '</button></div></div>';
		document.body.appendChild(modal);
		on(modal, 'click', function (e) { if (e.target === modal) modal.remove(); });
		on(q('.om-c-close', modal), 'click', function () { modal.remove(); });
		qa('.om-contact', modal).forEach(function (b) {
			on(b, 'click', function () { modal.remove(); openCompose('new'); var to = q('.cto'); if (to) { to.value = b.getAttribute('data-email') + ', '; to.focus(); } });
		});
	}

	/* ------------------------- refresh / polling ---------------------- */
	function refreshAll() { loadMessages(); refreshFoldersQuiet(); loadQuota(); }
	function refreshFoldersQuiet() {
		api('folders').then(function (r) { S.folders = r.folders || S.folders; renderFolders(); }).catch(function () {});
	}
	function loadContacts() { api('contacts').then(function (r) { S.contacts = r.contacts || []; }).catch(function () {}); }

	var pollTimer = null;
	function startPolling() {
		if (pollTimer) clearInterval(pollTimer);
		pollTimer = setInterval(function () {
			if (document.hidden || q('.om-compose-dock')) return;
			refreshFoldersQuiet();
			if (S.page === 0 && !S.search && S.special === 'inbox') {
				api('messages', { query: { folder: S.folder, page: 0, search: '' } }).then(function (r) {
					var topOld = S.messages[0] ? S.messages[0].uid : 0;
					var topNew = (r.messages && r.messages[0]) ? r.messages[0].uid : 0;
					if (topNew !== topOld) { S.total = r.total; S.messages = r.messages || []; listHead(); renderList(); }
				}).catch(function () {});
			}
		}, CFG.poll || 20000);
	}

	/* ------------------------------ start ----------------------------- */
	function start() {
		api('bootstrap').then(function (b) {
			S.bootstrap = b; S.from_name = b.from_name || ''; S.signature = b.signature || ''; S.email = b.email || S.email;
			if (!b.connected) { renderOnboard(b.conn_error || ''); return; }
			S.folders = b.folders || [];
			buildShell(); renderFolders();
			var inbox = pickInbox();
			if (inbox) { S.folder = inbox.raw; S.special = inbox.special; S.display = inbox.special ? t(inbox.special) : inbox.display; }
			renderFolders(); clearView(); loadMessages(); loadContacts(); loadQuota(); startPolling();
		}).catch(function (e) {
			ROOT.innerHTML = '<div class="om-onb"><div class="om-onb-card"><h2>⚠</h2><p class="sub">' + esc(e.message) + '</p><button class="om-btn primary om-full" onclick="location.reload()">' + esc(t('refresh')) + '</button></div></div>';
		});
	}

	start();
})();
