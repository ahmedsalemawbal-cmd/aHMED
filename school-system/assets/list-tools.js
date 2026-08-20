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

      var box = document.getElementById(opener.dataset.modalOpen);

      /* «استبدال هذا المستند» تفتح نافذة الرفع وقد اختير نوعه — لا نافذة
         يُعاد فيها اختيار ما ضغط عليه المستخدم للتوّ. */
      if (box && opener.dataset.pickName) {
        var field = box.querySelector('[name="' + opener.dataset.pickName + '"]');
        if (field) { field.value = opener.dataset.pickValue || ''; }
      }

      show(box);
      return;
    }

    /* الطباعة: اطبع أولًا — إرسال نموذجٍ ينقل الصفحة فلا تصل window.print. */
    if (e.target.closest('[data-sch-print]')) {
      e.preventDefault();
      window.print();
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

/* ═══════════════════════════════════════════════════════════════════════
   تبويبات ملفّ الطالب — تحسينٌ فوق روابط حقيقية

   كل تبويبة رابط `?tab=N` يعمل بلا جافاسكربت. وهذا يمنع إعادة التحميل
   ويكتب الحالة في تاريخ المتصفّح، فزرّ الرجوع يعيد التبويبة السابقة لا
   الصفحة السابقة. والصفحات كلّها في المستند أصلًا — البيانات تُقرأ مرة
   واحدة في الخادم، فالتبديل إظهارٌ لا طلب.
   ═══════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var root = document.querySelector('[data-sch-tabs]');
  if (!root) { return; }

  /* الشريط هو الترتيب، والنقاط السفلية تحمل المفاتيح نفسها. خلطهما في قائمة
     واحدة يجعل «التالي» بعد آخر تبويبة يقع على أوّل نقطة — فيظهر زرّ يعيدك
     إلى البداية في موضع «النهاية». */
  var links = Array.prototype.slice.call(root.querySelectorAll('.sch-sp__tabs [data-tab-go]'));
  var marks = Array.prototype.slice.call(root.querySelectorAll('[data-tab-go]'));
  var panes = Array.prototype.slice.call(root.querySelectorAll('[data-tab-pane]'));
  var steps = Array.prototype.slice.call(root.querySelectorAll('[data-tab-step]'));
  var edges = Array.prototype.slice.call(root.querySelectorAll('[data-tab-edge]'));
  if (links.length < 2) { return; }

  var order = links.map(function (a) { return a.dataset.tabGo; });
  var at = 0;

  function paint(n, push) {
    var idx = order.indexOf(n);
    if (idx < 0) { return false; }
    at = idx;

    panes.forEach(function (p) { p.classList.toggle('is-on', p.dataset.tabPane === n); });
    marks.forEach(function (a) {
      var hit = a.dataset.tabGo === n;
      a.classList.toggle('is-on', hit);
      a.setAttribute('aria-current', hit ? 'true' : 'false');
    });
    links[idx].scrollIntoView({ block: 'nearest', inline: 'nearest' });

    /* الترقيم السفلي يحمل اسمَي الجارتين — يُعاد بناؤه مع كل تبديل */
    steps.forEach(function (s) {
      var peer = links[at + parseInt(s.dataset.tabStep, 10)];
      s.hidden = !peer;
      if (!peer) { return; }
      s.href = peer.href;
      var label = s.querySelector('[data-tab-label]');
      if (label) { label.textContent = peer.dataset.tabLabel || ''; }
    });

    /* و«البداية»/«النهاية» تحلّان محلّ الجارة الغائبة — وتُخفيان متى وُجدت.
       بلا هذا تبقى «البداية» معروضة بعد التبديل لآخر صفحة. */
    edges.forEach(function (e) {
      e.hidden = !!links[at + (e.dataset.tabEdge === 'start' ? -1 : 1)];
    });

    if (push) {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', n);
      window.history.pushState({ schTab: n }, '', url);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    return true;
  }

  root.addEventListener('click', function (e) {
    var hit = e.target.closest('[data-tab-go], [data-tab-step]');
    if (!hit || hit.hidden) { return; }

    var n = hit.dataset.tabGo;
    if (!n) {
      var peer = links[at + parseInt(hit.dataset.tabStep, 10)];
      n = peer ? peer.dataset.tabGo : '';
    }
    if (n && paint(n, true)) { e.preventDefault(); }
  });

  /* ما رسمه الخادم هو الأصل — والرابط قد لا يحمل `tab` إطلاقًا (أوّل فتحة،
     أو عودة بعد حفظ). قراءة الرابط وحدها كانت تُرجع الشاشة للتبويبة الأولى
     فوق ما رسمه الخادم، فيضغط الموظف «المستندات» فيرى «الهوية». */
  var booted = root.querySelector('[data-tab-pane].is-on');
  var boot   = new URLSearchParams(window.location.search).get('tab')
    || (booted ? booted.dataset.tabPane : order[0]);

  /* الرجوع بالمتصفّح يعود لرابطٍ قد لا يحمل `tab` — فالمرجع هو ما فُتحت به
     الصفحة أوّل مرة، لا التبويبة المعروضة الآن (وإلّا لم يفعل الرجوع شيئًا). */
  window.addEventListener('popstate', function () {
    paint(new URLSearchParams(window.location.search).get('tab') || boot, false);
  });

  paint(boot, false);
})();

/* ═══════════════════════════════════════════════════════════════════════
   بناء الجداول — تحسينات فوق شاشة تعمل بلا جافاسكربت

   الشبكة تُملأ أصلًا بـ«اختر مادة ثم اضغط خانة» (روابط ونماذج حقيقية)،
   وهذه الطبقة تضيف: المِزواد (+/−) · شرائح أيام الدوام · إرسال القائمة عند
   الاختيار · والسحب والإفلات. من يُطفئ الجافاسكربت يبني جدوله كاملًا.
   ═══════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var root = document.querySelector('[data-sch-tt]');
  if (!root) { return; }

  /* ── المِزواد: +/− على حقل رقميّ ── */
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-tt-step][data-tt-for]');
    if (!b) { return; }

    var f = document.getElementById(b.dataset.ttFor);
    if (!f) { return; }

    var step = parseInt(b.dataset.ttStep, 10) || 1;
    var min  = f.min !== '' ? parseInt(f.min, 10) : -Infinity;
    var max  = f.max !== '' ? parseInt(f.max, 10) : Infinity;
    var next = (parseInt(f.value, 10) || 0) + step;

    f.value = Math.max(min, Math.min(max, next));
    f.dispatchEvent(new Event('change', { bubbles: true }));
  });

  /* ── أيام الدوام: الشرائح تكتب القناع ── */
  var mask = document.getElementById('tt-mask');
  root.addEventListener('click', function (e) {
    var d = e.target.closest('[data-tt-day]');
    if (!d || !mask) { return; }

    var bit = 1 << (parseInt(d.dataset.ttDay, 10) - 1);
    var now = parseInt(mask.value, 10) || 0;
    var on  = !(now & bit);

    /* يومٌ واحد على الأقل — أسبوعٌ بلا دوام لا يقبله المولّد ولا معنى له */
    if (!on && (now & ~bit) === 0) { return; }

    mask.value = on ? (now | bit) : (now & ~bit);
    d.classList.toggle('is-on', on);
    d.setAttribute('aria-pressed', on ? 'true' : 'false');
  });

  /* ── القائمة تُرسل نموذجها عند الاختيار ── */
  root.addEventListener('change', function (e) {
    var sel = e.target.closest('[data-tt-submit]');
    if (!sel) { return; }
    var form = sel.form || sel.closest('form');
    if (form) { form.requestSubmit ? form.requestSubmit() : form.submit(); }
  });

  /* ── السحب والإفلات ──
     المصدر إمّا مادة من السلّة (`subj:`) أو خانة مملوءة (`cell:`).
     والإفلات يُرسل نموذج الخانة الهدف بعد ضبط فعله — فلا مسار كتابةٍ
     ثانٍ يتباعد عن مسار الضغط، ولا نداء يتخطّى النونس. */
  var drag = null;

  root.addEventListener('dragstart', function (e) {
    var pickEl = e.target.closest('[data-tt-pick]');
    var cellEl = e.target.closest('[data-tt-cell]');

    if (pickEl) { drag = { kind: 'subj', id: pickEl.dataset.ttPick }; }
    else if (cellEl && cellEl.querySelector('b')) { drag = { kind: 'cell', from: cellEl }; }
    else { return; }

    if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'sch'); }
  });

  root.addEventListener('dragend', function () { drag = null; });

  root.addEventListener('dragover', function (e) {
    if (drag && e.target.closest('[data-tt-cell]')) { e.preventDefault(); }
  });

  root.addEventListener('drop', function (e) {
    var cell = e.target.closest('[data-tt-cell]');
    if (!drag || !cell) { return; }
    e.preventDefault();

    var form = cell.form || cell.closest('form');
    if (!form) { drag = null; return; }

    var sid = drag.kind === 'subj'
      ? drag.id
      : (drag.from.dataset.ttSubject || '');

    drag = null;
    if (!sid) { return; }

    /* نموذج الخانة فعلُه واحد (`tt_place`) ونونسُه واحد: المادة وحدها تتغيّر —
       صفرٌ يعني تفريغًا. فلا تبديلَ فعلٍ في المتصفّح ولا نونس لا يطابق. */
    var sub = form.querySelector('[name="subject_id"]');
    if (!sub) { return; }
    sub.value = sid;

    /* السحب من خانة إلى أخرى يُفرّغ الأصل بعد وصول الهدف — والخادم يُعيد
       التحميل، فيُترك التفريغ للضغطة التالية بدل نداءَين متسابقَين. */
    form.requestSubmit ? form.requestSubmit() : form.submit();
  });
})();
