/* =========================================================
 * مدرسة الفلك المنير — الجافاسكربت العام (مخزّن/cached).
 * قائمة الجوال · ظل الترويسة عند التمرير · أنيميشن الظهور ·
 * عدّادات الأرقام · الأسئلة الشائعة · إرسال نموذج التسجيل · زر الأعلى.
 * ========================================================= */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initLayout();
    initHeader();
    initDrawer();
    initReveal();
    initCounters();
    initFaq();
    initEnrollForm();
    initScrollTop();
    initSliders();
    initDynamicGrades();
    initRate();
    initReviewForm();
    initJobForm();
    initDashboard();
  });

  /* ترتيب الصفحة: نقل ترويستنا/محتوانا/تذييلنا لتكون أبناءً مباشرين للـ body،
     وإخفاء محتوى القالب الأصلي عند وجود صفحة «فلك» — ليعمل الموقع بعرض كامل
     مع القوالب البسيطة (نفس فكرة إضافة أصول). */
  function initLayout() {
    var body = document.body;
    var header = document.getElementById('falak-header');
    var page = document.getElementById('falak-page');
    var footer = document.getElementById('falak-footer');
    var drawer = document.getElementById('falak-drawer');
    var floats = document.querySelector('.fk-float');

    // عناصرنا التي يجب أن تبقى ظاهرة.
    var ours = [header, page, footer, drawer, floats].filter(Boolean);

    // عند وجود صفحة «فلك»: أخفِ كل محتوى القالب الأصلي.
    if (page) {
      Array.prototype.slice.call(body.children).forEach(function (el) {
        if (ours.indexOf(el) !== -1) return;
        var tag = el.tagName;
        if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'LINK' || tag === 'NOSCRIPT' || tag === 'TEMPLATE') return;
        el.style.setProperty('display', 'none', 'important');
      });
      // رتّب عناصرنا في نهاية body بالترتيب الصحيح.
      [page, footer].forEach(function (el) { if (el) body.appendChild(el); });
      // صفحة الداش بورد بلا ترويسة → بلا حشو علوي (يمنع الفراغ الأبيض).
      if (body.classList.contains('falak-dashboard')) {
        body.style.setProperty('padding-top', '0', 'important');
      } else {
        body.style.setProperty('padding-top', '96px', 'important');
        var mq = window.matchMedia('(max-width:1024px)');
        if (mq.matches) body.style.setProperty('padding-top', '70px', 'important');
      }
    }

    // تأكد أن الترويسة والتذييل والعائمات أبناء مباشرون للـ body.
    [header, drawer, floats].forEach(function (el) {
      if (el && el.parentNode !== body) body.appendChild(el);
    });
    if (header && body.firstChild !== header) body.insertBefore(header, body.firstChild);
  }

  /* ظل الترويسة عند التمرير */
  function initHeader() {
    var header = document.getElementById('falak-header');
    if (!header) return;
    var onScroll = function () {
      if (window.scrollY > 20) header.classList.add('scrolled');
      else header.classList.remove('scrolled');
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    // إبراز الرابط النشط حسب الصفحة الحالية.
    var path = window.location.pathname.replace(/\/+$/, '');
    document.querySelectorAll('.fkh-nav a, .fkh-drawer-nav a').forEach(function (a) {
      try {
        var ap = new URL(a.href, window.location.origin).pathname.replace(/\/+$/, '');
        if (ap && ap === path) a.classList.add('active');
      } catch (e) {}
    });
  }

  /* درج قائمة الجوال */
  function initDrawer() {
    var drawer = document.getElementById('falak-drawer');
    var openBtn = document.getElementById('falak-burger');
    if (!drawer || !openBtn) return;
    var closeBtn = drawer.querySelector('.fkh-drawer-close');
    var ov = drawer.querySelector('.fkh-drawer-ov');

    var open = function () {
      drawer.classList.add('open');
      document.body.style.overflow = 'hidden';
      openBtn.setAttribute('aria-expanded', 'true');
    };
    var close = function () {
      drawer.classList.remove('open');
      document.body.style.overflow = '';
      openBtn.setAttribute('aria-expanded', 'false');
    };
    openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (ov) ov.addEventListener('click', close);
    drawer.querySelectorAll('.fkh-drawer-nav a').forEach(function (a) {
      a.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
  }

  /* أنيميشن الظهور عند التمرير */
  function initReveal() {
    var els = document.querySelectorAll('.fk-reveal');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) {
      els.forEach(function (el) { el.classList.add('in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (el, i) {
      el.style.transitionDelay = (Math.min(i % 4, 3) * 80) + 'ms';
      io.observe(el);
    });
  }

  /* عدّادات الأرقام المتصاعدة */
  function initCounters() {
    var nums = document.querySelectorAll('[data-count]');
    if (!nums.length) return;
    var run = function (el) {
      var target = parseFloat(el.getAttribute('data-count')) || 0;
      var dur = 1500, start = null;
      var step = function (ts) {
        if (!start) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * eased).toLocaleString('ar-EG');
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = target.toLocaleString('ar-EG');
      };
      requestAnimationFrame(step);
    };
    if (!('IntersectionObserver' in window)) {
      nums.forEach(run);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { run(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.5 });
    nums.forEach(function (el) { io.observe(el); });
  }

  /* الأسئلة الشائعة (accordion) */
  function initFaq() {
    var items = document.querySelectorAll('.fk-faq-item');
    if (!items.length) return;
    items.forEach(function (item) {
      var q = item.querySelector('.fk-faq-q');
      var a = item.querySelector('.fk-faq-a');
      if (!q || !a) return;
      q.setAttribute('aria-expanded', 'false');
      q.addEventListener('click', function () {
        var isOpen = item.classList.contains('open');
        // إغلاق البقية.
        items.forEach(function (other) {
          if (other !== item) {
            other.classList.remove('open');
            var oa = other.querySelector('.fk-faq-a');
            var oq = other.querySelector('.fk-faq-q');
            if (oa) oa.style.maxHeight = null;
            if (oq) oq.setAttribute('aria-expanded', 'false');
          }
        });
        if (isOpen) {
          item.classList.remove('open');
          a.style.maxHeight = null;
          q.setAttribute('aria-expanded', 'false');
        } else {
          item.classList.add('open');
          a.style.maxHeight = a.scrollHeight + 'px';
          q.setAttribute('aria-expanded', 'true');
        }
      });
    });
  }

  /* نموذج التسجيل: إرسال POST عادي موثوق (يعمل في كل المتصفحات ومتصفحات التطبيقات) */
  function initEnrollForm() {
    var form = document.getElementById('falak-enroll-form');
    if (!form) return;
    var btn = form.querySelector('.fk-form-submit');
    var msg = form.querySelector('.fk-form-msg');

    // إطلاق أحداث التحويل عند العودة بنجاح (?fk=ok) — بعد الحفظ في لوحة التحكم.
    try {
      if (/[?&]fk=ok(?:&|$)/.test(location.search)) {
        if (window.ttq)    { try { ttq.track('CompleteRegistration'); } catch (e) {} }
        if (window.snaptr) { try { snaptr('track', 'SIGN_UP'); } catch (e) {} }
        if (msg) { msg.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      }
    } catch (e) {}

    // نترك المتصفح يُرسل النموذج فعليًا (POST عادي). لا نمنع الإرسال إطلاقًا حتى لا يضيع أي طلب.
    // يُطلق حدث submit فقط بعد نجاح تحقّق المتصفح من الحقول المطلوبة.
    // نؤجّل تعطيل الزر عبر setTimeout حتى يبدأ الإرسال فعليًا (كي لا يُلغى الإرسال في أي متصفح).
    form.addEventListener('submit', function () {
      setTimeout(function () {
        if (btn) { btn.disabled = true; btn.innerHTML = 'جارٍ الإرسال…'; }
      }, 0);
    });
  }

  /* ملء قائمة المراحل حسب النوع (ولد/بنت) */
  function fillGrades(select, section) {
    if (!select) return;
    var grades = (window.falakData && falakData.grades && falakData.grades[section]) || [];
    select.innerHTML = '';
    if (!grades.length) {
      select.innerHTML = '<option value="">—</option>';
      select.disabled = true;
      return;
    }
    grades.forEach(function (g) {
      var o = document.createElement('option');
      o.value = g; o.textContent = g;
      select.appendChild(o);
    });
    select.disabled = false;
  }

  /* المراحل الديناميكية في نموذج التسجيل العام */
  function initDynamicGrades() {
    var form = document.getElementById('falak-enroll-form');
    if (!form) return;
    var grade = document.getElementById('falak-grade');
    var radios = form.querySelectorAll('input[name="section"]');
    radios.forEach(function (r) {
      r.addEventListener('change', function () {
        if (r.checked) fillGrades(grade, r.value);
      });
    });
  }

  /* السلايدرات (تقليب يدوي: أزرار + سحب) */
  function initSliders() {
    document.querySelectorAll('.fk-slider').forEach(function (slider) {
      var track = slider.querySelector('.fk-sl-track');
      if (!track) return;
      var prev = slider.querySelector('.fk-sl-prev');
      var next = slider.querySelector('.fk-sl-next');
      var rtl = getComputedStyle(track).direction === 'rtl';
      var move = function (dir) { // dir: +1 التالي، -1 السابق
        var slide = track.querySelector('.fk-slide');
        var gap = parseFloat(getComputedStyle(track).gap) || 20;
        var amount = (slide ? slide.getBoundingClientRect().width : 300) + gap;
        track.scrollBy({ left: dir * amount * (rtl ? -1 : 1), behavior: 'smooth' });
      };
      if (prev) prev.addEventListener('click', function () { move(-1); });
      if (next) next.addEventListener('click', function () { move(1); });
    });
  }

  /* نجوم التقييم */
  function initRate() {
    document.querySelectorAll('[data-rate]').forEach(function (rate) {
      var input = rate.querySelector('input[name="rating"]');
      var stars = [].slice.call(rate.querySelectorAll('.fk-star'));
      var paint = function (v) { stars.forEach(function (s) { s.classList.toggle('on', (+s.dataset.v) <= v); }); };
      stars.forEach(function (s) {
        s.addEventListener('click', function () { var v = +s.dataset.v; if (input) input.value = v; paint(v); });
      });
      paint(input ? (+input.value || 5) : 5);
    });
  }

  /* إرسال نموذج التقييم */
  function initReviewForm() {
    var form = document.getElementById('falak-review-form');
    if (!form) return;
    var msg = form.querySelector('.fk-form-msg');
    var btn = form.querySelector('.fk-form-submit');
    var data = window.falakData || {};

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (msg) { msg.className = 'fk-form-msg'; msg.textContent = ''; }
      var hp = form.querySelector('[name="fk_website"]');
      if (hp && hp.value) return;

      var fd = new FormData(form), payload = {};
      fd.forEach(function (v, k) { payload[k] = v; });
      if (!payload.name || !payload.text) {
        show('err', 'الرجاء كتابة الاسم والتقييم.');
        return;
      }
      if (!data.review) { show('err', 'تعذّر الإرسال حاليًا.'); return; }

      var t = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.innerHTML = 'جارٍ الإرسال…'; }
      fetch(data.review, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce || '' },
        body: JSON.stringify(payload)
      }).then(function (r) {
        if (btn) { btn.disabled = false; btn.innerHTML = t; }
        if (r.ok) { show('ok', 'شكرًا لك! تم استلام تقييمك وسيظهر بعد اعتماده. 🌟'); form.reset(); initRate(); }
        else { show('err', 'تعذّر إرسال التقييم، حاول لاحقًا.'); }
      }).catch(function () {
        if (btn) { btn.disabled = false; btn.innerHTML = t; }
        show('err', 'تعذّر الاتصال، حاول لاحقًا.');
      });
    });

    function show(type, text) {
      if (!msg) { alert(text); return; }
      msg.className = 'fk-form-msg ' + type;
      msg.textContent = text;
      msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  /* إرسال نموذج التوظيف */
  function initJobForm() {
    var form = document.getElementById('falak-job-form');
    if (!form) return;
    var msg = form.querySelector('.fk-form-msg');
    var btn = form.querySelector('.fk-form-submit');
    var data = window.falakData || {};

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (msg) { msg.className = 'fk-form-msg'; msg.textContent = ''; }
      var hp = form.querySelector('[name="fk_website"]');
      if (hp && hp.value) return;

      var fd = new FormData(form), payload = {};
      fd.forEach(function (v, k) { payload[k] = v; });
      if (!payload.name || !payload.phone || !payload.position) {
        show('err', 'الرجاء تعبئة الاسم والجوال والوظيفة المطلوبة.');
        return;
      }
      if (!data.job) { show('err', 'تعذّر الإرسال حاليًا.'); return; }

      var t = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.innerHTML = 'جارٍ الإرسال…'; }
      fetch(data.job, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce || '' },
        body: JSON.stringify(payload)
      }).then(function (r) {
        if (btn) { btn.disabled = false; btn.innerHTML = t; }
        if (r.ok) { show('ok', 'تم استلام طلب التوظيف بنجاح ✅ سنتواصل معك عند توفّر شاغرٍ مناسب.'); form.reset(); }
        else { show('err', 'تعذّر إرسال الطلب، حاول لاحقًا.'); }
      }).catch(function () {
        if (btn) { btn.disabled = false; btn.innerHTML = t; }
        show('err', 'تعذّر الاتصال، حاول لاحقًا.');
      });
    });

    function show(type, text) {
      if (!msg) { alert(text); return; }
      msg.className = 'fk-form-msg ' + type;
      msg.textContent = text;
      msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  /* تفاعلات الداش بورد: فتح النماذج، نسخ الرابط، مراحل ديناميكية */
  function initDashboard() {
    document.querySelectorAll('[data-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var t = document.getElementById(btn.getAttribute('data-toggle'));
        if (t) t.hidden = !t.hidden;
      });
    });
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var t = document.querySelector(btn.getAttribute('data-copy'));
        if (!t) return;
        t.select();
        try { document.execCommand('copy'); } catch (e) {}
        if (navigator.clipboard) { navigator.clipboard.writeText(t.value).catch(function () {}); }
        var o = btn.innerHTML;
        btn.innerHTML = 'تم النسخ ✓';
        setTimeout(function () { btn.innerHTML = o; }, 1500);
      });
    });
    var ds = document.getElementById('dash-section');
    var dg = document.getElementById('dash-grade');
    if (ds && dg) {
      ds.addEventListener('change', function () { fillGrades(dg, ds.value); });
    }
  }

  /* زر العودة للأعلى */
  function initScrollTop() {
    var btn = document.getElementById('falak-top');
    if (!btn) return;
    window.addEventListener('scroll', function () {
      if (window.scrollY > 500) btn.classList.add('show');
      else btn.classList.remove('show');
    }, { passive: true });
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
})();
