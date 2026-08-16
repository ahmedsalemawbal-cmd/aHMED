/**
 * تطبيق البوابة — منطق المسح.
 *
 * يستخدم المحرك الميداني المشترك (field-core.js) للطابور ومنع التكرار.
 * المسح عبر BarcodeDetector حيث توفّر، والبحث بالاسم بديل دائم يعمل في كل جهاز.
 */
(function () {
  'use strict';

  var CFG = window.SCH_GATE;
  if (!CFG || !window.SCHField) { return; }

  var netEl    = document.getElementById('schg-net');
  var modesEl  = document.getElementById('schg-modes');
  var cardEl   = document.getElementById('schg-card');
  var photoEl  = document.getElementById('schg-photo');
  var nameEl   = document.getElementById('schg-name');
  var metaEl   = document.getElementById('schg-meta');
  var stateEl  = document.getElementById('schg-state');
  var hintEl   = document.getElementById('schg-hint');
  var videoEl  = document.getElementById('schg-video');
  var searchEl = document.getElementById('schg-search');
  var resEl    = document.getElementById('schg-results');
  var recentEl = document.getElementById('schg-recent');

  var mode = CFG.defaultMode;
  var lastToken = '';
  var lastAt = 0;

  var queue = new window.SCHField.Queue({
    key: 'sch_gate_queue',
    endpoint: 'custody/scan',
    api: CFG.api,
    nonce: CFG.nonce,
    onChange: function (pending, online) {
      if (!online) {
        netEl.textContent = CFG.i18n.offline + (pending ? ' · ' + pending : '');
        netEl.className = 'schg-pill schg-pill--bad';
        return;
      }
      netEl.textContent = pending ? CFG.i18n.queued + ' · ' + pending : CFG.i18n.synced;
      netEl.className = 'schg-pill' + (pending ? ' schg-pill--warn' : ' schg-pill--ok');
    },
    onResult: function (res) {
      var b = res.body;
      // موظف: يُعرض دوره وحالة حضوره بدل الشعبة وحالة العهدة.
      if (res.ok && b && b.kind === 'staff' && b.person) {
        show({ name: b.person.name, klass: b.person.role_label, staff: true, late: b.status === 'late' }, b.flash || b.status_label, false);
        addRecent(b.person.name, b.checkpoint_label);
      } else if (res.ok && b && b.student) {
        show(b.student, b.state_label, false);
        addRecent(b.student.name, b.checkpoint_label);
      } else if (b && b.message) {
        show({ name: b.message }, '', true);
      }
    }
  });

  // ---------- الأوضاع ----------

  function setMode(next) {
    mode = next;
    modesEl.querySelectorAll('.schg-mode').forEach(function (b) {
      b.classList.toggle('is-on', b.dataset.mode === next);
    });
  }

  modesEl.addEventListener('click', function (e) {
    var btn = e.target.closest('.schg-mode');
    if (btn) { setMode(btn.dataset.mode); }
  });

  setMode(mode);

  // ---------- بطاقة التأكيد ----------

  var hideTimer = null;

  function show(student, stateLabel, bad) {
    cardEl.hidden = false;
    cardEl.classList.toggle('is-bad', !!bad);
    cardEl.classList.toggle('is-staff', !!student.staff);
    cardEl.classList.toggle('is-late', !!student.late);

    nameEl.textContent = student.name || '';
    metaEl.textContent = student.klass || student.academic_no || '';
    stateEl.textContent = stateLabel || '';

    if (student.id && !bad) {
      photoEl.hidden = false;
      photoEl.src = CFG.photoBase + student.id + '/?sch_photo=1';
    } else {
      photoEl.hidden = true;
      photoEl.removeAttribute('src');
    }

    if (navigator.vibrate) { navigator.vibrate(bad ? [90, 60, 90] : 40); }

    clearTimeout(hideTimer);
    hideTimer = setTimeout(function () { cardEl.hidden = true; }, 3000);
  }

  function addRecent(name, label) {
    var row = document.createElement('div');
    row.className = 'schg-recent__i';

    var b = document.createElement('strong');
    b.textContent = name;
    var s1 = document.createElement('span');
    s1.textContent = label || '';
    var s2 = document.createElement('span');
    s2.setAttribute('dir', 'ltr');
    s2.textContent = new Date().toTimeString().slice(0, 5);

    row.appendChild(b); row.appendChild(s1); row.appendChild(s2);
    recentEl.insertBefore(row, recentEl.firstChild);

    while (recentEl.children.length > 10) { recentEl.removeChild(recentEl.lastChild); }
  }

  // ---------- التسجيل ----------

  function submit(payload) {
    // الخروج المبكر يتطلب المستلم والسبب — يُسألان قبل الإرسال.
    if (mode === 'early_out') {
      var receiver = window.prompt(CFG.i18n.receiver);
      if (!receiver) { return; }
      var reason = window.prompt(CFG.i18n.reason) || '';
      payload.receiver_name = receiver;
      payload.reason = reason;
    }

    payload.checkpoint = mode;
    queue.push(payload);
  }

  function scanned(token) {
    var now = Date.now();
    // نفس البطاقة خلال ٣ ثوانٍ = مسح مكرر بالخطأ، لا عملية جديدة.
    if (token === lastToken && now - lastAt < 3000) { return; }
    lastToken = token;
    lastAt = now;

    submit({ badge_token: token });
  }

  // ---------- الكاميرا ----------

  function startCamera() {
    if (!('BarcodeDetector' in window) || !navigator.mediaDevices) {
      hintEl.textContent = CFG.i18n.noCamera;
      return;
    }

    var detector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128'] });

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(function (stream) {
        videoEl.srcObject = stream;
        videoEl.play();
        (document.getElementById('schg-scan') || {}).classList.add('is-live');

        setInterval(function () {
          if (videoEl.readyState !== 4) { return; }
          detector.detect(videoEl)
            .then(function (codes) {
              if (codes && codes.length) { scanned(codes[0].rawValue); }
            })
            .catch(function () {});
        }, 400);
      })
      .catch(function () { hintEl.textContent = CFG.i18n.noCamera; });
  }

  // ---------- البحث بالاسم ----------

  var searchTimer = null;

  searchEl.addEventListener('input', function () {
    clearTimeout(searchTimer);
    var q = searchEl.value.trim();

    if (q.length < 2) { resEl.innerHTML = ''; return; }

    searchTimer = setTimeout(function () {
      fetch(CFG.api + 'students?search=' + encodeURIComponent(q) + '&per_page=6', {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': CFG.nonce }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          resEl.innerHTML = '';
          (data.items || []).forEach(function (s) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'schg-result';
            b.textContent = s.full_name + ' — ' + (s.grade_level || '');
            b.addEventListener('click', function () {
              submit({ student_id: s.id });
              searchEl.value = '';
              resEl.innerHTML = '';
            });
            resEl.appendChild(b);
          });
        })
        .catch(function () {});
    }, 300);
  });

  window.SCHField.keepAwake();
  startCamera();
})();
