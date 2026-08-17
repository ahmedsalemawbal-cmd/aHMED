/**
 * أدوات القوائم المشتركة.
 *
 * تعمل على أي شاشة فيها مربعات تحديد — تُكتب مرة وتفيد كل شاشة تعلن أفعالها.
 */
(function () {
  'use strict';

  var AR = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
  function ar(n) {
    return String(n).split('').map(function (d) { return /\d/.test(d) ? AR[+d] : d; }).join('');
  }

  var bar = document.getElementById('sch-bulk');
  var form = document.getElementById('sch-bulk-form');

  /* حقل مفقود لا يكسر الشريط كله */
  function el(id) { return document.getElementById(id); }
  function setVal(id, v) { var e = el(id); if (e) { e.value = v; } }

  function picks() {
    return Array.prototype.slice.call(document.querySelectorAll('[data-pick]:checked'));
  }

  function sync() {
    var on = picks();
    var all = document.querySelectorAll('[data-pick]');

    if (bar) {
      bar.hidden = on.length === 0;
      var n = document.getElementById('sch-bulk-n');
      if (n) { n.textContent = ar(on.length) + ' محدد'; }
    }

    document.querySelectorAll('[data-pick]').forEach(function (c) {
      var row = c.closest('tr') || c.closest('[data-row]');
      if (row) { row.classList.toggle('is-picked', c.checked); }
    });

    var master = document.querySelector('[data-pick-all]');
    if (master) {
      master.checked = on.length > 0 && on.length === all.length;
      master.indeterminate = on.length > 0 && on.length < all.length;
    }
  }

  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-pick]')) { sync(); }

    if (e.target.matches('[data-pick-all]')) {
      var v = e.target.checked;
      document.querySelectorAll('[data-pick]').forEach(function (c) { c.checked = v; });
      sync();
    }
  });

  /* Shift + نقرة تحدد مدى — أسرع بكثير من ثلاثين ضغطة */
  var last = null;
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-pick]');
    if (!c) { return; }

    if (e.shiftKey && last) {
      var all = Array.prototype.slice.call(document.querySelectorAll('[data-pick]'));
      var a = all.indexOf(last);
      var b = all.indexOf(c);
      all.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (x) { x.checked = c.checked; });
      sync();
    }

    last = c;
  });

  /* ---------- تنفيذ الفعل ---------- */
  if (bar && form) {
    bar.addEventListener('click', function (e) {
      if (e.target.closest('[data-clear]')) {
        document.querySelectorAll('[data-pick]').forEach(function (c) { c.checked = false; });
        sync();
        return;
      }

      var btn = e.target.closest('[data-op]');
      if (!btn) { return; }

      var ids = picks().map(function (c) { return c.value; });
      if (!ids.length) { return; }

      var text = '';
      var pick = '';

      /* المعامل يُطلب قبل التأكيد: لا معنى لتأكيد فعل ناقص */
      if (btn.dataset.param === 'text') {
        text = window.prompt(btn.dataset.label, '');
        if (text === null || text.trim() === '') { return; }
      }

      if (btn.dataset.param === 'select') {
        var opts = {};
        try { opts = JSON.parse(btn.dataset.options || '{}'); } catch (err) { opts = {}; }

        var lines = Object.keys(opts).map(function (k) { return k + ' — ' + opts[k]; });
        if (!lines.length) { window.alert('لا توجد خيارات متاحة.'); return; }

        pick = window.prompt(btn.dataset.label + '\n\n' + lines.join('\n'), Object.keys(opts)[0]);
        if (pick === null || !opts[pick]) { return; }
      }

      var confirmMsg = btn.dataset.confirm;
      if (confirmMsg && !window.confirm(confirmMsg.replace('%d', ids.length))) { return; }

      setVal('sch-bulk-op', btn.dataset.op);
      setVal('sch-bulk-text', text);
      setVal('sch-bulk-select', pick);
      setVal('sch-bulk-ids', ids.join(','));

      btn.disabled = true;
      form.submit();
    });
  }

  /* ---------- التوسّع داخل الصف ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-expand]');
    if (!t) { return; }
    e.preventDefault();

    var row = t.closest('[data-row]') || t.closest('tr');
    if (!row) { return; }

    row.classList.toggle('is-open');

    var det = row.nextElementSibling;
    if (det && det.hasAttribute('data-detail')) { det.hidden = !row.classList.contains('is-open'); }
  });

  /* ---------- التصفية: زر مسح لكل حقل ---------- */
  document.querySelectorAll('[data-filter]').forEach(function (f) {
    var field = f.querySelector('select, input');
    var clear = f.querySelector('[data-clear-field]');

    function mark() {
      f.classList.toggle('has', field && field.value !== '');
    }

    if (field) { field.addEventListener('change', mark); mark(); }

    if (clear) {
      clear.addEventListener('click', function () {
        if (!field) { return; }
        field.value = '';
        mark();
        var form = f.closest('form');
        if (form) { form.submit(); }
      });
    }
  });

  sync();
})();

/**
 * حماية من الضغط المزدوج.
 *
 * ضغطتان على «حفظ» تعنيان سجلين — وأول من يشتكي هو الموظف الذي أنشأ
 * طالبين بنفس الاسم ولا يعرف أيهما يحذف.
 *
 * والزر يقول «جارٍ الحفظ…» فيعرف أن ضغطته وصلت ولا يعيدها.
 */
(function () {
  'use strict';

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) { return; }
    if (form.method.toLowerCase() !== 'post') { return; }

    if (form.dataset.sent === '1') {
      e.preventDefault();
      return;
    }

    // النموذج الذي مُنع إرساله (تأكيد مرفوض أو تحقق فاشل) لا يُقفل
    setTimeout(function () {
      if (form.dataset.sent === '1') { return; }
      form.dataset.sent = '1';

      var btn = form.querySelector('button[type=submit], button:not([type]), input[type=submit]');
      if (!btn) { return; }

      btn.dataset.label = btn.textContent;
      btn.disabled = true;
      btn.classList.add('is-sending');
      if (btn.tagName === 'BUTTON') { btn.textContent = 'جارٍ الحفظ…'; }

      // شبكة أمان: لو لم تنتقل الصفحة خلال 12 ثانية نُعيد الزر
      setTimeout(function () {
        form.dataset.sent = '';
        btn.disabled = false;
        btn.classList.remove('is-sending');
        if (btn.dataset.label) { btn.textContent = btn.dataset.label; }
      }, 12000);
    }, 0);
  }, true);
})();

/**
 * النوافذ المنبثقة — منطق واحد لكل الشاشات.
 *
 * لا تُغلق بالخلفية ولا بـEsc: نقرة عابرة تمحو نموذجًا مملوءًا.
 * والإغلاق بزر X أو «إلغاء» وحدهما.
 */
(function () {
  'use strict';

  var open = null;
  var last = null;

  function show(modal) {
    if (!modal) { return; }

    last = document.activeElement;
    open = modal;
    modal.hidden = false;
    document.body.classList.add('sch-locked');

    /* الشريط الطافي يختفي: طبقتان متنافستان تُربكان العين */
    var bulk = document.getElementById('sch-bulk');
    if (bulk) {
      bulk.dataset.wasHidden = bulk.hidden ? '1' : '0';
      bulk.hidden = true;
    }

    var first = modal.querySelector('input:not([type=hidden]):not([disabled]), select, textarea');
    if (first) { first.focus(); }
  }

  function hide() {
    if (!open) { return; }

    open.hidden = true;
    document.body.classList.remove('sch-locked');

    var bulk = document.getElementById('sch-bulk');
    if (bulk && bulk.dataset.wasHidden === '0') { bulk.hidden = false; }

    if (last) { last.focus(); }
    open = null;
  }

  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');
    if (opener) {
      e.preventDefault();
      show(document.getElementById(opener.dataset.modalOpen));
      return;
    }

    if (e.target.closest('[data-modal-close]')) { hide(); }
  });

  /* حبس التركيز: الخروج بـTab يُربك من يتصفّح بلوحة المفاتيح */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab' || !open) { return; }

    var f = open.querySelectorAll(
      'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select, textarea'
    );
    if (!f.length) { return; }

    var first = f[0];
    var lastEl = f[f.length - 1];

    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); lastEl.focus(); }
    else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); first.focus(); }
  });

  /* عاد الحفظ بخطأ؟ نُعيد فتح النافذة التي أُرسلت منها */
  var params = new URLSearchParams(window.location.search);
  if (params.has('err') && params.has('from')) {
    show(document.getElementById(params.get('from')));
  }
})();
