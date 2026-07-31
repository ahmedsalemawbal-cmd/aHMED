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
      body.style.setProperty('padding-top', '96px', 'important');
      var mq = window.matchMedia('(max-width:1024px)');
      if (mq.matches) body.style.setProperty('padding-top', '70px', 'important');
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

  /* إرسال نموذج التسجيل عبر REST مع رجوع لواتساب عند التعذّر */
  function initEnrollForm() {
    var form = document.getElementById('falak-enroll-form');
    if (!form) return;
    var msg = form.querySelector('.fk-form-msg');
    var btn = form.querySelector('.fk-form-submit');
    var data = window.falakData || {};

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (msg) { msg.className = 'fk-form-msg'; msg.textContent = ''; }

      // فخّ العناكب (honeypot).
      var hp = form.querySelector('[name="fk_website"]');
      if (hp && hp.value) return;

      var fd = new FormData(form);
      var payload = {};
      fd.forEach(function (v, k) { payload[k] = v; });

      // تحقّق مبسّط.
      if (!payload.student_name || !payload.guardian_phone) {
        showMsg('err', 'الرجاء تعبئة اسم الطالب ورقم الجوال على الأقل.');
        return;
      }

      var btnText = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.innerHTML = 'جارٍ الإرسال…'; }

      var done = function (ok) {
        if (btn) { btn.disabled = false; btn.innerHTML = btnText; }
        if (ok) {
          showMsg('ok', 'تم استلام طلب التسجيل بنجاح ✅ سنتواصل معكم قريبًا بإذن الله.');
          form.reset();
        } else {
          // رجوع لواتساب حتى لا يضيع الطلب.
          var wa = data.whatsapp ? 'https://wa.me/' + data.whatsapp + '?text=' + encodeURIComponent(buildWaText(payload)) : null;
          showMsg('err', 'تعذّر الإرسال عبر الموقع. ' + (wa ? 'يمكنك الإرسال عبر واتساب.' : 'حاول لاحقًا.'));
          if (wa) window.open(wa, '_blank');
        }
      };

      if (!data.rest) { done(false); return; }

      fetch(data.rest, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce || '' },
        body: JSON.stringify(payload)
      }).then(function (r) {
        return r.ok ? r.json().then(function () { done(true); }) : done(false);
      }).catch(function () { done(false); });
    });

    function showMsg(type, text) {
      if (!msg) { alert(text); return; }
      msg.className = 'fk-form-msg ' + type;
      msg.textContent = text;
      msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    function buildWaText(p) {
      return 'طلب تسجيل جديد:\n' +
        'الطالب: ' + (p.student_name || '') + '\n' +
        'المرحلة: ' + (p.grade || '') + '\n' +
        'القسم: ' + (p.section || '') + '\n' +
        'ولي الأمر: ' + (p.guardian_name || '') + '\n' +
        'الجوال: ' + (p.guardian_phone || '') + '\n' +
        'البرنامج: ' + (p.program || '');
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
