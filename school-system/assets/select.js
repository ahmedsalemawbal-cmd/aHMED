/**
 * القائمة المنسدلة الاحترافية — ترقية تدريجية لكل <select> في الداشبورد.
 *
 * تُبقي عنصر <select> الأصلي حيًّا (يُرسل مع النموذج وتُطلق حدث change)،
 * وتبني فوقه واجهة مخصّصة: زرّ أنيق + لوحة خيارات فيها المختار مظلّل بعلامة صح.
 * لوحة الخيارات تُلحق بـ body وتُوضع بإحداثيات ثابتة كي لا يقصّها أي جدول أو بطاقة.
 *
 * لوحة المفاتيح: مسافة/سهم لأسفل يفتح · الأسهم تنقّل · Enter يختار · Esc يغلق ·
 * أول حرف يقفز للخيار. وكل شيء يحترم prefers-reduced-motion وRTL والوضعين.
 */
(function () {
  'use strict';

  var openWidget = null;

  function closeOpen() {
    if (openWidget) { openWidget.close(); }
  }

  function Widget(select) {
    var self = this;
    this.select = select;

    var wrap = document.createElement('span');
    wrap.className = 'sch-sel';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('sch-sel__native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'sch-sel__btn';
    btn.setAttribute('aria-haspopup', 'listbox');
    btn.setAttribute('aria-expanded', 'false');
    if (select.getAttribute('aria-label')) {
      btn.setAttribute('aria-label', select.getAttribute('aria-label'));
    }
    if (select.disabled) { btn.disabled = true; }

    var label = document.createElement('span');
    label.className = 'sch-sel__val';

    var chev = document.createElement('span');
    chev.className = 'sch-sel__chev';
    chev.setAttribute('aria-hidden', 'true');
    chev.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 10 6 6 6-6"/></svg>';

    btn.appendChild(label);
    btn.appendChild(chev);
    wrap.appendChild(btn);

    var panel = document.createElement('div');
    panel.className = 'sch-sel__panel';
    panel.setAttribute('role', 'listbox');
    panel.hidden = true;

    this.wrap = wrap;
    this.btn = btn;
    this.labelEl = label;
    this.panel = panel;
    this.active = -1;

    this.render();

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      self.toggle();
    });
    btn.addEventListener('keydown', function (e) { self.onBtnKey(e); });
    panel.addEventListener('keydown', function (e) { self.onPanelKey(e); });
    // تغيّر القيمة برمجيًا (كأزرار «علّم الكل») يحدّث الزر أيضًا
    select.addEventListener('change', function () {
      if (!self._internal) { self.syncFromSelect(); }
    });

    // قوائم متعاقبة (المرحلة تُغيّر الصفوف) تُعيد بناء الخيارات — نُعيد الرسم
    this._mo = new MutationObserver(function () {
      self.render();
      if (openWidget === self) { self.position(); }
    });
    this._mo.observe(select, { childList: true });

    select.schWidget = this;
  }

  Widget.prototype.render = function () {
    var self = this;
    this.panel.innerHTML = '';
    this.opts = [];

    Array.prototype.forEach.call(this.select.options, function (o, i) {
      var opt = document.createElement('div');
      opt.className = 'sch-sel__opt';
      opt.setAttribute('role', 'option');
      opt.dataset.i = String(i);
      if (o.disabled) { opt.classList.add('is-disabled'); }

      var t = document.createElement('span');
      t.className = 'sch-sel__optt';
      t.textContent = o.text;

      var ck = document.createElement('span');
      ck.className = 'sch-sel__ck';
      ck.setAttribute('aria-hidden', 'true');
      ck.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6.5 9.5 17 4 11.5"/></svg>';

      opt.appendChild(t);
      opt.appendChild(ck);

      opt.addEventListener('click', function (e) {
        e.preventDefault();
        if (o.disabled) { return; }
        self.pick(i);
      });
      opt.addEventListener('mousemove', function () { self.setActive(i, false); });

      self.panel.appendChild(opt);
      self.opts.push(opt);
    });

    this.syncFromSelect();
  };

  Widget.prototype.syncFromSelect = function () {
    var sel = this.select.selectedIndex;
    var o = this.select.options[sel];
    var placeholder = o && o.value === '' && sel === 0;

    this.labelEl.textContent = o ? o.text : '';
    this.labelEl.classList.toggle('is-placeholder', !!placeholder);

    for (var i = 0; i < this.opts.length; i++) {
      var on = i === sel;
      this.opts[i].classList.toggle('is-selected', on);
      this.opts[i].setAttribute('aria-selected', on ? 'true' : 'false');
    }
  };

  Widget.prototype.pick = function (i) {
    if (this.select.selectedIndex !== i) {
      this.select.selectedIndex = i;
      this._internal = true;
      this.select.dispatchEvent(new Event('input', { bubbles: true }));
      this.select.dispatchEvent(new Event('change', { bubbles: true }));
      this._internal = false;
    }
    this.syncFromSelect();
    this.close();
    this.btn.focus();
  };

  Widget.prototype.toggle = function () { this.panel.hidden ? this.open() : this.close(); };

  Widget.prototype.open = function () {
    if (this.btn.disabled) { return; }
    closeOpen();
    openWidget = this;

    document.body.appendChild(this.panel);
    this.panel.hidden = false;
    this.wrap.classList.add('is-open');
    this.btn.setAttribute('aria-expanded', 'true');
    this.position();

    this.setActive(this.select.selectedIndex >= 0 ? this.select.selectedIndex : 0, true);
    this.panel.focus({ preventScroll: true });
  };

  Widget.prototype.close = function () {
    if (this.panel.hidden) { return; }
    this.panel.hidden = true;
    this.wrap.classList.remove('is-open');
    this.btn.setAttribute('aria-expanded', 'false');
    if (this.panel.parentNode === document.body) { document.body.removeChild(this.panel); }
    if (openWidget === this) { openWidget = null; }
  };

  Widget.prototype.position = function () {
    var p = this.panel;

    // تصفير قياسات الفتحة السابقة قبل القياس — وإلا قِيست اللوحة بعرضها القديم
    // بعد أن يُعيد المراقب بناء خياراتها وهي مفتوحة.
    p.style.width = '';
    p.style.maxHeight = '';
    p.style.insetInlineStart = 'auto';
    p.style.insetInlineEnd = 'auto';
    p.style.top = '-9999px';
    p.style.left = '0px';

    var r  = this.btn.getBoundingClientRect();
    var vw = document.documentElement.clientWidth;
    var vh = window.innerHeight;

    // عرض الزرّ أرضية لا سقفًا: تتمدّد اللوحة لتسع أطول خيار ثم تتوقّف عند حدّها.
    // كان تثبيتها على عرض الزرّ يجعل «وكيل الشؤون التعليمية» ينكسر ثلاثة أسطر.
    p.style.minWidth = r.width + 'px';

    // القرار أولًا بالارتفاع الطبيعي، ثم تُقصّ اللوحة على الجهة المختارة
    // فتُمرَّر داخل نفسها بدل أن تُدفع خارج الشاشة.
    var natural = p.offsetHeight;
    var gap     = 6;
    var edge    = 8;
    var room_dn = vh - (r.bottom + gap) - edge;
    var room_up = r.top - gap - edge;
    var up      = (natural > room_dn) && (room_up > room_dn);

    p.style.maxHeight = Math.max(120, up ? room_up : room_dn) + 'px';

    var w = p.offsetWidth;
    var h = p.offsetHeight;

    // المحاذاة بالحافّة المنطقية: في RTL تُثبَّت الحافّة اليمنى فتنمو اللوحة يسارًا.
    // تثبيت left وحده كان يدفعها خارج الشاشة كلما زاد عرضها عن الزرّ.
    var rtl  = (getComputedStyle(this.btn).direction === 'rtl');
    var left = rtl ? (r.right - w) : r.left;

    p.style.left = Math.max(edge, Math.min(left, vw - w - edge)) + 'px';
    p.style.top  = (up ? (r.top - h - gap) : (r.bottom + gap)) + 'px';
  };

  Widget.prototype.setActive = function (i, scroll) {
    if (i < 0 || i >= this.opts.length) { return; }
    if (this.active >= 0 && this.opts[this.active]) { this.opts[this.active].classList.remove('is-active'); }
    this.active = i;
    var el = this.opts[i];
    el.classList.add('is-active');
    if (scroll) { el.scrollIntoView({ block: 'nearest' }); }
  };

  Widget.prototype.step = function (dir) {
    var n = this.opts.length, i = this.active;
    for (var k = 0; k < n; k++) {
      i = (i + dir + n) % n;
      if (!this.select.options[i].disabled) { this.setActive(i, true); return; }
    }
  };

  Widget.prototype.onBtnKey = function (e) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      this.open();
    }
  };

  Widget.prototype.onPanelKey = function (e) {
    switch (e.key) {
      case 'ArrowDown': e.preventDefault(); this.step(1); break;
      case 'ArrowUp':   e.preventDefault(); this.step(-1); break;
      case 'Home':      e.preventDefault(); this.setActive(0, true); break;
      case 'End':       e.preventDefault(); this.setActive(this.opts.length - 1, true); break;
      case 'Enter':
      case ' ':         e.preventDefault(); if (this.active >= 0) { this.pick(this.active); } break;
      case 'Escape':    e.preventDefault(); this.close(); this.btn.focus(); break;
      case 'Tab':       this.close(); break;
      default:
        if (e.key.length === 1) { this.typeAhead(e.key); }
    }
  };

  Widget.prototype.typeAhead = function (ch) {
    ch = ch.toLowerCase();
    for (var i = 0; i < this.opts.length; i++) {
      var idx = (this.active + 1 + i) % this.opts.length;
      var o = this.select.options[idx];
      if (!o.disabled && (o.text || '').trim().toLowerCase().indexOf(ch) === 0) { this.setActive(idx, true); return; }
    }
  };

  // ---------- الترقية والأحداث العامة ----------

  function enhance(root) {
    var body = document.querySelector('.sch-body') || document;
    (root || body).querySelectorAll('select:not([multiple]):not([data-plain]):not(.sch-sel__native)').forEach(function (s) {
      if (s.closest('.sch-body') && !s.schWidget) {
        try { new Widget(s); } catch (e) {}
      }
    });
  }

  document.addEventListener('click', function (e) {
    if (openWidget && !openWidget.panel.contains(e.target) && !openWidget.btn.contains(e.target)) {
      openWidget.close();
    }
  });
  window.addEventListener('resize', closeOpen);
  window.addEventListener('scroll', function () { if (openWidget) { openWidget.position(); } }, true);

  // شاشات تُبنى بعد التحميل (نوافذ، صفوف الحضور) — نرقّي ما استُجدّ.
  var mo = new MutationObserver(function (muts) {
    for (var i = 0; i < muts.length; i++) {
      for (var j = 0; j < muts[i].addedNodes.length; j++) {
        var n = muts[i].addedNodes[j];
        if (n.nodeType !== 1) { continue; }
        if (n.tagName === 'SELECT') { enhance(n.parentNode); }
        else if (n.querySelector && n.querySelector('select')) { enhance(n); }
      }
    }
  });

  function boot() {
    enhance(document);
    mo.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.SCH = window.SCH || {};
  window.SCH.enhanceSelects = enhance;
})();
