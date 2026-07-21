/* =========================================================
 * Osoul Albinaa — global front-end script (cached).
 * Consolidated from the inline scripts the legacy snippet
 * printed on every page, plus NEW server-side lead capture.
 * Loaded in the footer (defer-equivalent).
 * ========================================================= */
(function () {
  'use strict';

  var DATA = window.osoulData || { whatsapp: '966556847029', restUrl: '', nonce: '', lang: 'ar' };

  /* ───────────────────────────────────────
   * Helpers
   * ─────────────────────────────────────── */
  function getLang() { try { return localStorage.getItem('osoul_lang') || 'ar'; } catch (e) { return 'ar'; } }

  /**
   * NEW: record a lead on the server before any WhatsApp/mailto hand-off.
   * Fire-and-forget — never blocks the user's WhatsApp flow, so even if the
   * site is offline the legacy behaviour still works.
   */
  function recordLead(payload) {
    if (!DATA.restUrl) { return Promise.resolve(); }
    try {
      return fetch(DATA.restUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': DATA.nonce },
        body: JSON.stringify(payload),
        keepalive: true
      }).catch(function () {});
    } catch (e) { return Promise.resolve(); }
  }
  window.osoulRecordLead = recordLead;

  /* ───────────────────────────────────────
   * Language switcher
   * ─────────────────────────────────────── */
  window.osoulSwitchLang = function (l) {
    try { localStorage.setItem('osoul_lang', l); } catch (e) {}
    document.cookie = 'osoul_lang=' + (l === 'en' ? 'en' : 'ar') + ';path=/;max-age=31536000;SameSite=Lax';
    var html = document.documentElement;
    html.setAttribute('lang', l === 'en' ? 'en' : 'ar');
    html.dir = l === 'en' ? 'ltr' : 'rtl';
    html.classList.toggle('lang-en', l === 'en');
    if (document.body) {
      document.body.classList.toggle('lang-en', l === 'en');
      document.body.classList.toggle('lang-ar', l !== 'en');
      document.body.setAttribute('dir', l === 'en' ? 'ltr' : 'rtl');
    }
    document.querySelectorAll('[data-lang-btn]').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-lang-btn') === l);
    });
    document.querySelectorAll('[data-lang]').forEach(function (el) {
      var elLang = el.getAttribute('data-lang');
      var isBlock = (el.tagName === 'DIV' || el.tagName === 'P' || el.tagName === 'H1' ||
                     el.tagName === 'H2' || el.tagName === 'H3' || el.tagName === 'H4' || el.tagName === 'SECTION');
      el.style.display = (elLang === l) ? (isBlock ? 'block' : 'inline') : 'none';
    });
    document.querySelectorAll('.ohp,.osrv,.osi,.odp,.odps,.oab,.oct,.obl,.obls').forEach(function (c) {
      c.style.direction = l === 'en' ? 'ltr' : 'rtl';
    });
    var placeholders = {
      'qf-notes': { ar: 'مقاسات، تشطيبة، موعد التسليم...', en: 'Dimensions, finish, delivery date...' },
      'qf-qty':   { ar: 'مثال: 10', en: 'e.g. 10' },
      'qf-phone': { ar: '05XXXXXXXX', en: '05XXXXXXXX' }
    };
    Object.keys(placeholders).forEach(function (k) {
      document.querySelectorAll('[id*="' + k + '"]').forEach(function (el) {
        el.placeholder = placeholders[k][l] || '';
      });
    });
  };

  /* ───────────────────────────────────────
   * Header: scroll state, burger, submenus
   * ─────────────────────────────────────── */
  function initHeader() {
    var hdr = document.getElementById('osoul-header');
    var burger = document.getElementById('osh-burger');
    var menu = document.getElementById('osh-mobile-menu');
    var overlay = document.getElementById('osh-overlay');
    var isOpen = false;

    if (hdr) {
      var ticking = false;
      function upd() { hdr.classList.toggle('scrolled', window.scrollY > 60); ticking = false; }
      window.addEventListener('scroll', function () {
        if (!ticking) { requestAnimationFrame(upd); ticking = true; }
      }, { passive: true });
      upd();
    }

    window.oshToggleMenu = function () {
      isOpen = !isOpen;
      if (burger) burger.classList.toggle('open', isOpen);
      if (menu) menu.classList.toggle('open', isOpen);
      if (overlay) overlay.classList.toggle('open', isOpen);
      document.body.style.overflow = isOpen ? 'hidden' : '';
    };
    window.oshCloseMenu = function () {
      isOpen = false;
      if (burger) burger.classList.remove('open');
      if (menu) menu.classList.remove('open');
      if (overlay) overlay.classList.remove('open');
      document.body.style.overflow = '';
    };
    window.oshToggleSub = function (e, idx) {
      var s = document.getElementById('osh-mob-sub-' + idx);
      var a = document.getElementById('osh-mob-arrow-' + idx);
      if (!s) return;
      var o = s.classList.contains('open');
      document.querySelectorAll('.osh-mob-sub').forEach(function (el) { el.classList.remove('open'); });
      document.querySelectorAll('.osh-mob-nav>li>a svg').forEach(function (el) { el.style.transform = ''; });
      if (!o) { e.preventDefault(); s.classList.add('open'); if (a) a.style.transform = 'rotate(-180deg)'; }
    };
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') window.oshCloseMenu(); });

    var cp = window.location.pathname;
    document.querySelectorAll('.osh-nav>li>a').forEach(function (a) {
      try {
        var u = new URL(a.href);
        if (u.pathname === cp || (cp !== '/' && u.pathname !== '/' && cp.indexOf(u.pathname) === 0)) {
          a.parentElement.classList.add('active');
        }
      } catch (err) {}
    });
  }

  /* ───────────────────────────────────────
   * Quote-list drawer (localStorage-backed)
   * ─────────────────────────────────────── */
  var OQL_KEY = 'osoul_quote_list';
  function oqlLoad() { try { return JSON.parse(localStorage.getItem(OQL_KEY) || '[]'); } catch (e) { return []; } }
  function oqlSave(list) { try { localStorage.setItem(OQL_KEY, JSON.stringify(list)); } catch (e) {} }

  function oqlSyncBadges() {
    var total = oqlLoad().reduce(function (s, p) { return s + p.qty; }, 0);
    [document.getElementById('oql-badge'), document.getElementById('oql-header-badge')].forEach(function (b) {
      if (b) { b.textContent = total; b.style.display = total > 0 ? 'flex' : 'none'; }
    });
    var count = document.getElementById('oql-total-count');
    if (count) count.textContent = total;
  }

  function oqlRender() {
    var list = oqlLoad();
    var el = document.getElementById('oql-list');
    var empty = document.getElementById('oql-empty-state');
    var footer = document.getElementById('oql-footer-actions');
    oqlSyncBadges();
    if (!list.length) {
      if (empty) empty.style.display = '';
      if (el) el.innerHTML = '';
      if (footer) footer.style.display = 'none';
      return;
    }
    if (empty) empty.style.display = 'none';
    if (footer) footer.style.display = '';
    if (!el) return;
    var lang = getLang();
    el.innerHTML = list.map(function (p, i) {
      return '<div class="oql-item">' +
        '<div class="oql-item-img"><img src="' + p.img + '" alt="' + p.name + '"></div>' +
        '<div class="oql-item-info">' +
          '<div class="oql-item-cat">' + (lang === 'en' ? p.group_en : p.group_ar) + '</div>' +
          '<div class="oql-item-name">' + (lang === 'en' ? p.name_en : p.name) + '</div>' +
          '<div class="oql-item-qty">' +
            '<button class="oql-qty-btn" onclick="oqlQty(' + i + ',-1)">−</button>' +
            '<span class="oql-qty-val">' + p.qty + '</span>' +
            '<button class="oql-qty-btn" onclick="oqlQty(' + i + ',1)">+</button>' +
          '</div>' +
        '</div>' +
        '<button class="oql-item-del" onclick="oqlRemove(' + i + ')" aria-label="del"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg></button>' +
      '</div>';
    }).join('');
  }

  window.oqlOpenDrawer = function () {
    var d = document.getElementById('oql-drawer'), o = document.getElementById('oql-overlay');
    if (d) d.classList.add('open');
    if (o) o.classList.add('open');
    document.body.style.overflow = 'hidden';
    oqlRender();
  };
  window.oqlCloseDrawer = function () {
    var d = document.getElementById('oql-drawer'), o = document.getElementById('oql-overlay');
    if (d) d.classList.remove('open');
    if (o) o.classList.remove('open');
    document.body.style.overflow = '';
  };
  window.oqlAddProduct = function (slug, name, name_en, group_ar, group_en, img) {
    var list = oqlLoad();
    var ex = list.find(function (p) { return p.slug === slug; });
    if (ex) { ex.qty++; } else { list.push({ slug: slug, name: name, name_en: name_en, group_ar: group_ar, group_en: group_en, img: img, qty: 1 }); }
    oqlSave(list); oqlRender(); window.oqlOpenDrawer();
  };
  window.oqlQty = function (i, d) {
    var list = oqlLoad();
    if (!list[i]) return;
    list[i].qty += d;
    if (list[i].qty < 1) list.splice(i, 1);
    oqlSave(list); oqlRender();
  };
  window.oqlRemove = function (i) { var l = oqlLoad(); l.splice(i, 1); oqlSave(l); oqlRender(); };
  window.oqlClearAll = function () { oqlSave([]); oqlRender(); };

  // NEW: submit a real quote request (name/phone/email + the list) to the server.
  window.oqlSubmitRequest = function () {
    var list = oqlLoad();
    if (!list.length) { return; }
    var g = function (id) { var e = document.getElementById(id); return e ? e.value.trim() : ''; };
    var name = g('oql-name'), phone = g('oql-phone'), email = g('oql-email'), website = g('oql-website');
    var lang = getLang();
    var status = document.getElementById('oql-status');
    var btn = document.querySelector('.oql-submit-btn');
    function show(type, text) {
      if (!status) { if (type === 'err') alert(text); return; }
      status.style.display = 'block';
      status.className = 'oql-status ' + type;
      status.textContent = text;
    }
    if (!name || (!phone && !email)) {
      show('err', lang === 'en' ? 'Please enter your name and a phone or email.' : 'الرجاء إدخال الاسم ورقم الجوال أو الإيميل.');
      return;
    }
    var url = DATA.quoteUrl || '';
    if (!url) { window.oqlSendWhatsApp(); return; }
    if (btn) { btn.disabled = true; }
    show('ok', lang === 'en' ? 'Sending…' : 'جارٍ الإرسال…');
    var items = list.map(function (p) { return { slug: p.slug, name: p.name, name_en: p.name_en, qty: p.qty }; });
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': DATA.nonce },
      body: JSON.stringify({ name: name, phone: phone, email: email, website: website, lang: lang, items: items })
    }).then(function (res) {
      if (btn) { btn.disabled = false; }
      if (res.ok) {
        show('ok', lang === 'en'
          ? 'Thank you! Your request has been received — our team will send you a quotation soon.'
          : 'شكراً لك! تم استلام طلبك — بيرسل لك فريقنا عرض السعر قريباً.');
        oqlSave([]); oqlRender();
        ['oql-name', 'oql-phone', 'oql-email'].forEach(function (id) { var e = document.getElementById(id); if (e) e.value = ''; });
        return;
      }
      return res.json().then(function (j) {
        show('err', (j && j.message) ? j.message : (lang === 'en' ? 'Something went wrong. Please try WhatsApp.' : 'حدث خطأ. جرّب واتساب.'));
      }).catch(function () {
        show('err', lang === 'en' ? 'Something went wrong. Please try WhatsApp.' : 'حدث خطأ. جرّب واتساب.');
      });
    }).catch(function () {
      if (btn) { btn.disabled = false; }
      show('err', lang === 'en' ? 'Network error. Please try WhatsApp instead.' : 'تعذّر الاتصال. جرّب واتساب بدلاً من ذلك.');
    });
  };

  window.oqlSendWhatsApp = function () {
    var list = oqlLoad(); if (!list.length) return;
    var lang = getLang();
    // NEW: capture server-side first.
    recordLead({ source: 'quote', lang: lang, name: 'Quote list', phone: '', items: list });
    var msg = lang === 'en' ? 'Hello, I would like to request a quote for:\n' : 'مرحباً، أود طلب عرض سعر للمنتجات التالية:\n';
    list.forEach(function (p) { msg += '- ' + (lang === 'en' ? p.name_en : p.name) + ' (x' + p.qty + ')\n'; });
    window.open('https://wa.me/' + DATA.whatsapp + '?text=' + encodeURIComponent(msg), '_blank');
  };
  window.oqlSendEmail = function () {
    var list = oqlLoad(); if (!list.length) return;
    var lang = getLang();
    recordLead({ source: 'quote', lang: lang, name: 'Quote list', phone: '', items: list });
    var subj = lang === 'en' ? 'Quote Request — Osoul Albinaa' : 'طلب عرض سعر — أصول البناء';
    var body = lang === 'en' ? 'Hello,\n\nI would like a quote for:\n' : 'مرحباً،\n\nأود الاستفسار عن:\n';
    list.forEach(function (p) { body += '- ' + (lang === 'en' ? p.name_en : p.name) + ' (x' + p.qty + ')\n'; });
    window.location.href = 'mailto:info@osoulalbinaa.com?subject=' + encodeURIComponent(subj) + '&body=' + encodeURIComponent(body);
  };

  /* ───────────────────────────────────────
   * Contact form + archive quick-quote (NEW server capture)
   * ─────────────────────────────────────── */
  window.octSendWhatsApp = function () {
    var g = function (id) { var e = document.getElementById(id); return e ? e.value.trim() : ''; };
    var name = g('oct-name'), phone = g('oct-phone'), email = g('oct-email'), msg = g('oct-msg');
    var subjEl = document.getElementById('oct-subject');
    var subjVal = subjEl ? subjEl.options[subjEl.selectedIndex].text : '';
    var lang = getLang();
    if (!name || !phone) {
      alert(lang === 'en' ? 'Please fill in your name and phone number.' : 'الرجاء إدخال الاسم ورقم الجوال.');
      return;
    }
    recordLead({ source: 'contact', lang: lang, name: name, phone: phone, email: email, subject: subjVal, message: msg });
    var wa = lang === 'en'
      ? 'Hello Osoul Albinaa,\nName: ' + name + '\nPhone: ' + phone + (email ? '\nEmail: ' + email : '') + (subjVal ? '\nSubject: ' + subjVal : '') + (msg ? '\nMessage: ' + msg : '')
      : 'مرحباً أصول البناء،\nالاسم: ' + name + '\nالجوال: ' + phone + (email ? '\nالإيميل: ' + email : '') + (subjVal ? '\nالموضوع: ' + subjVal : '') + (msg ? '\nالرسالة: ' + msg : '');
    window.open('https://wa.me/' + DATA.whatsapp + '?text=' + encodeURIComponent(wa), '_blank');
  };

  // NEW: primary on-site submission — posts to the REST endpoint and shows an
  // inline success/error message instead of leaving the site for WhatsApp.
  window.octSubmit = function () {
    var g = function (id) { var e = document.getElementById(id); return e ? e.value.trim() : ''; };
    var name = g('oct-name'), phone = g('oct-phone'), email = g('oct-email'), msg = g('oct-msg');
    var subjEl = document.getElementById('oct-subject');
    var subjVal = (subjEl && subjEl.options[subjEl.selectedIndex]) ? subjEl.options[subjEl.selectedIndex].text : '';
    var website = g('oct-website'); // honeypot
    var lang = getLang();
    var status = document.getElementById('oct-status');
    var btn = document.querySelector('.oct-sbtn');
    function show(type, text) {
      if (!status) { if (type === 'err') alert(text); return; }
      status.style.display = 'block';
      status.className = 'oct-status ' + type;
      status.textContent = text;
    }
    if (!name || !phone) {
      show('err', lang === 'en' ? 'Please fill in your name and phone number.' : 'الرجاء إدخال الاسم ورقم الجوال.');
      return;
    }
    if (!DATA.restUrl) { window.octSendWhatsApp(); return; } // graceful fallback
    if (btn) { btn.disabled = true; }
    show('ok', lang === 'en' ? 'Sending…' : 'جارٍ الإرسال…');
    fetch(DATA.restUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': DATA.nonce },
      body: JSON.stringify({ source: 'contact', lang: lang, name: name, phone: phone, email: email, subject: subjVal, message: msg, website: website })
    }).then(function (res) {
      if (btn) { btn.disabled = false; }
      if (res.ok) {
        show('ok', lang === 'en'
          ? 'Thank you! Your request has been received — we will contact you soon.'
          : 'شكراً لك! تم استلام طلبك — سنتواصل معك قريباً.');
        ['oct-name', 'oct-phone', 'oct-email', 'oct-msg'].forEach(function (id) { var e = document.getElementById(id); if (e) e.value = ''; });
        return;
      }
      return res.json().then(function (j) {
        show('err', (j && j.message) ? j.message : (lang === 'en' ? 'Something went wrong. Please try WhatsApp.' : 'حدث خطأ. جرّب واتساب.'));
      }).catch(function () {
        show('err', lang === 'en' ? 'Something went wrong. Please try WhatsApp.' : 'حدث خطأ. جرّب واتساب.');
      });
    }).catch(function () {
      if (btn) { btn.disabled = false; }
      show('err', lang === 'en' ? 'Network error. Please try WhatsApp instead.' : 'تعذّر الاتصال. جرّب واتساب بدلاً من ذلك.');
    });
  };

  window.odpsSubmitQuote = function (page) {
    var p = document.getElementById('qf-product-' + page);
    var q = document.getElementById('qf-qty-' + page);
    var ph = document.getElementById('qf-phone-' + page);
    if (!p || !q || !ph) return;
    var lang = getLang();
    var productText = p.options[p.selectedIndex] ? p.options[p.selectedIndex].text : '';
    recordLead({
      source: 'archive', lang: lang, name: 'Quick quote', phone: ph.value,
      subject: productText, message: 'Qty: ' + q.value
    });
    var msg = lang === 'en'
      ? "Hi, I'd like a quote for: " + productText + ', Qty: ' + q.value + ', Phone: ' + ph.value
      : 'مرحباً، أود الاستفسار عن: ' + productText + ' | الكمية: ' + q.value + ' | الهاتف: ' + ph.value;
    window.open('https://wa.me/' + DATA.whatsapp + '?text=' + encodeURIComponent(msg), '_blank');
  };

  /* ───────────────────────────────────────
   * Cookie consent
   * ─────────────────────────────────────── */
  function initCookie() {
    try {
      if (!localStorage.getItem('osoul_cookie_consent')) {
        setTimeout(function () { var c = document.getElementById('osoul-cookie'); if (c) c.classList.add('show'); }, 2500);
      }
    } catch (e) {}
    window.oscAccept = function () { try { localStorage.setItem('osoul_cookie_consent', 'accepted'); } catch (e) {} var c = document.getElementById('osoul-cookie'); if (c) c.classList.remove('show'); };
    window.oscDecline = function () { try { localStorage.setItem('osoul_cookie_consent', 'declined'); } catch (e) {} var c = document.getElementById('osoul-cookie'); if (c) c.classList.remove('show'); };
  }

  /* ───────────────────────────────────────
   * Certificate lightbox
   * ─────────────────────────────────────── */
  window.certLbOpen = function (src, alt) {
    var lb = document.getElementById('cert-lightbox-overlay'), img = document.getElementById('cert-lightbox-img');
    if (!lb || !img) return;
    img.src = src; img.alt = alt || '';
    lb.classList.add('open'); document.body.style.overflow = 'hidden';
  };
  window.certLbClose = function (e) {
    if (e && e.target && e.target.closest && e.target.closest('.cert-lightbox-img')) return;
    var lb = document.getElementById('cert-lightbox-overlay');
    if (lb) lb.classList.remove('open');
    document.body.style.overflow = '';
  };

  /* ───────────────────────────────────────
   * Scroll-reveal animations
   * ─────────────────────────────────────── */
  function initAnimations() {
    if (typeof IntersectionObserver === 'undefined') return;
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          var d = parseInt(e.target.getAttribute('data-aos-delay') || 0, 10);
          setTimeout(function () { e.target.classList.add('aos-animate'); }, d);
          obs.unobserve(e.target);
        }
      });
    }, { threshold: 0.12 });
    document.querySelectorAll('[data-aos]').forEach(function (el) { obs.observe(el); });
  }

  /* ───────────────────────────────────────
   * Animated number counters
   * ─────────────────────────────────────── */
  function initCounters() {
    if (typeof IntersectionObserver === 'undefined') return;
    function animateStat(el) {
      var raw = el.getAttribute('data-orig') || el.textContent.trim();
      el.setAttribute('data-orig', raw);
      var m = raw.match(/^([^0-9]*)([0-9]+)([^0-9]*)$/);
      if (!m) { el.setAttribute('data-counting', '1'); return; }
      var prefix = m[1], num = parseInt(m[2], 10), suffix = m[3];
      if (num < 2) { el.setAttribute('data-counting', '1'); return; }
      var duration = 1600, startTime = null;
      el.setAttribute('data-counting', '1');
      el.textContent = prefix + '0' + suffix;
      function ease(t) { return 1 - Math.pow(1 - t, 3); }
      function step(ts) {
        if (!startTime) startTime = ts;
        var p = Math.min((ts - startTime) / duration, 1);
        el.textContent = prefix + Math.floor(ease(p) * num) + suffix;
        if (p < 1) { requestAnimationFrame(step); } else { el.textContent = prefix + num + suffix; }
      }
      requestAnimationFrame(step);
    }
    var els = document.querySelectorAll('#ohero .sn,.oab-stat-n,.opg-stat-n,.ohp-stat-n');
    if (!els.length) return;
    var arr = Array.prototype.slice.call(els);
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        var idx = arr.indexOf(e.target);
        setTimeout(function () { animateStat(e.target); }, idx * 120);
        obs.unobserve(e.target);
      });
    }, { threshold: 0.4, rootMargin: '0px 0px -40px 0px' });
    arr.forEach(function (el) { obs.observe(el); });
  }

  /* ───────────────────────────────────────
   * Hero video sound toggle
   * ─────────────────────────────────────── */
  function initHeroSound() {
    var vid = document.getElementById('ohero-video');
    var btn = document.getElementById('ohero-sound-btn');
    if (!vid || !btn) return;
    var iconMuted = document.getElementById('ohero-icon-muted');
    var iconSound = document.getElementById('ohero-icon-sound');
    var muted = true;
    window.oheroToggleSound = function () {
      muted = !muted; vid.muted = muted;
      if (!muted) { vid.volume = 0.15; }
      if (iconMuted) iconMuted.style.display = muted ? 'block' : 'none';
      if (iconSound) iconSound.style.display = muted ? 'none' : 'block';
      btn.style.borderColor = muted ? 'rgba(255,255,255,.25)' : 'rgba(210,18,23,.7)';
      btn.style.background = muted ? 'rgba(13,31,60,.7)' : 'rgba(210,18,23,.25)';
    };
  }

  /* ───────────────────────────────────────
   * Delegated quote-button handler (any language)
   * ─────────────────────────────────────── */
  function initQuoteDelegation() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('.opp-card-quote');
      if (!btn) return;
      e.preventDefault();
      var slug = btn.getAttribute('data-oql-slug');
      if (!slug) return;
      window.oqlAddProduct(
        slug,
        btn.getAttribute('data-oql-name') || '',
        btn.getAttribute('data-oql-name-en') || '',
        btn.getAttribute('data-oql-group-ar') || '',
        btn.getAttribute('data-oql-group-en') || '',
        btn.getAttribute('data-oql-img') || ''
      );
    }, true);
  }

  /* ───────────────────────────────────────
   * Boot
   * ─────────────────────────────────────── */
  function boot() {
    window.osoulSwitchLang(getLang());
    initHeader();
    initCookie();
    initAnimations();
    initCounters();
    initHeroSound();
    initQuoteDelegation();
    oqlRender();

    // Cert lightbox bindings
    document.querySelectorAll('.cert-photo-card').forEach(function (card) {
      var img = card.querySelector('img');
      if (!img) return;
      card.addEventListener('click', function () { window.certLbOpen(img.src, img.alt); });
      card.style.cursor = 'zoom-in';
    });

    // Drawer close on Escape / overlay
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { window.oqlCloseDrawer(); window.certLbClose(); } });

    // Sync language float buttons
    document.querySelectorAll('[data-lang-btn]').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-lang-btn') === getLang());
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  window.addEventListener('storage', oqlRender);
  setInterval(oqlSyncBadges, 3000);
})();
