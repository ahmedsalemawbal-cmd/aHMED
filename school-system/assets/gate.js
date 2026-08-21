/**
 * تطبيق البوابة — منطق المسح.
 *
 * يستخدم المحرك الميداني المشترك (field-core.js) للطابور ومنع التكرار.
 * المسح عبر BarcodeDetector حيث توفّر، والبحث بالاسم بديل دائم يعمل في كل جهاز.
 *
 * والخروج المبكر لا يمرّ بخانة كتابة: `SCH_Custody` تشترط مفتاحًا من قائمة
 * المخوَّلين، وكان الحارس يُسأل الاسم نصًّا — فلا يخرج طفلٌ صغير أبدًا،
 * ويخرج الكبير مع من كتب الحارس اسمه أيًّا كان. والقائمة هنا قائمةُ الأب.
 */
(function () {
  'use strict';

  var CFG = window.SCH_GATE;
  if (!CFG || !window.SCHField) { return; }

  var netEl    = document.getElementById('schg-net');
  var countsEl = document.getElementById('schg-counts');
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

  var pickEl    = document.getElementById('schg-pick');
  var pickWho   = document.getElementById('schg-pick-who');
  var pickList  = document.getElementById('schg-pick-list');
  var pickWhy   = document.getElementById('schg-pick-reason');
  var pickX     = document.getElementById('schg-pick-cancel');

  var mode = CFG.defaultMode;
  var lastToken = '';
  var lastAt = 0;
  var busy = false;

  function api(path) {
    return fetch(CFG.api + path, {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': CFG.nonce }
    }).then(function (r) {
      if (!r.ok) { throw new Error(String(r.status)); }
      return r.json();
    });
  }

  var queue = new window.SCHField.Queue({
    key: 'sch_gate_queue',
    endpoint: 'custody/scan',
    api: CFG.api,
    nonce: CFG.nonce,
    nonceUrl: CFG.nonceUrl,
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

      // العدّاد كان مجمَّدًا منذ فتح الصفحة — والحارس على الشاشة نفسها من
      // السابعة إلى الثانية. الخادم يعيده مُنسَّقًا مع كل مسح.
      if (res.ok && b && b.counts && countsEl) {
        countsEl.textContent = b.counts;
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
    metaEl.textContent = student.klass || student.class || student.academic_no || '';
    stateEl.textContent = stateLabel || '';

    if (student.id && !bad) {
      photoEl.hidden = false;
      photoEl.src = CFG.photoBase + student.id;
    } else {
      photoEl.hidden = true;
      photoEl.removeAttribute('src');
    }

    if (navigator.vibrate) { navigator.vibrate(bad ? [90, 60, 90] : 40); }

    clearTimeout(hideTimer);
    hideTimer = setTimeout(function () { cardEl.hidden = true; }, 3000);
  }

  /** رسالة خطأ في مكان البطاقة — الصمت عند البوابة أسوأ من الرسالة. */
  function fail(message) {
    show({ name: message }, '', true);
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

  // ---------- نافذة اختيار المستلم ----------

  function closePick() {
    pickEl.hidden = true;
    pickList.textContent = '';
    pickWhy.value = '';
    busy = false;
  }

  pickX.addEventListener('click', closePick);

  /**
   * تُفتح للخروج المبكر: قائمة المخوَّلين أزرارًا، وسببٌ اختياري.
   * ومن لا مخوَّل له تُعرض له رسالة صريحة بدل خانة كتابة — الحارس لا يُطلب
   * منه أن يقرّر من يستلم الطفل.
   */
  function openPick(student, pickers) {
    if (!pickers.length) {
      fail(CFG.i18n.noPickers);
      busy = false;
      return;
    }

    pickList.textContent = '';
    pickWhy.value = '';
    pickWho.textContent = student.name + (student.class ? ' — ' + student.class : '');

    pickers.forEach(function (p) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'schg-pick__one';

      var strong = document.createElement('strong');
      strong.textContent = p.name;
      b.appendChild(strong);

      var sub = document.createElement('span');
      sub.textContent = p.until
        ? p.relation + ' · ' + CFG.i18n.until + ' ' + String(p.until).slice(0, 10)
        : p.relation;
      b.appendChild(sub);

      b.addEventListener('click', function () {
        var reason = pickWhy.value.trim();
        closePick();
        send({ student_id: student.id, picker: p.key, reason: reason });
      });

      pickList.appendChild(b);
    });

    pickEl.hidden = false;
    pickWhy.focus();
  }

  // ---------- التسجيل ----------

  /** الإرسال النهائي — الحمولة كاملة ونقطة التحقق مضافة. */
  function send(payload) {
    payload.checkpoint = mode;
    queue.push(payload);
  }

  /**
   * بوابة كل عملية: الخروج المبكر يمرّ بنافذة الاختيار، وما عداه يُرسَل فورًا.
   *
   * @param {{token?: string, student?: object}} what
   */
  function submit(what) {
    if (mode !== 'early_out') {
      send(what.token ? { badge_token: what.token } : { student_id: what.student.id });
      return;
    }

    if (busy) { return; }
    busy = true;

    // المسح يعطي رمزًا لا معرّفًا، وقائمة المخوَّلين تحتاج المعرّف.
    var resolve = what.student
      ? Promise.resolve(what.student)
      : api('gate/search?token=' + encodeURIComponent(what.token))
          .then(function (d) {
            var s = (d.items || [])[0];
            if (!s) { throw new Error('not_found'); }
            return s;
          });

    resolve
      .then(function (student) {
        return api('gate/pickers/' + student.id).then(function (d) {
          openPick(student, d.items || []);
        });
      })
      .catch(function (e) {
        fail(String(e && e.message) === 'not_found' ? CFG.i18n.notFound : CFG.i18n.pickErr);
        busy = false;
      });
  }

  function scanned(token) {
    var now = Date.now();
    // نفس البطاقة خلال ٣ ثوانٍ = مسح مكرر بالخطأ، لا عملية جديدة.
    if (token === lastToken && now - lastAt < 3000) { return; }
    lastToken = token;
    lastAt = now;

    submit({ token: token });
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
          if (!pickEl.hidden) { return; } // النافذة مفتوحة — لا مسح فوق قرارٍ معلَّق
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

    if (q.length < 2) { resEl.textContent = ''; return; }

    searchTimer = setTimeout(function () {
      // `students` مشروط بـ`sch_view_students` والحارس لا يملكها، فكان
      // البديلُ الوحيد حين تتعطّل الكاميرا يردّ 403. و`gate/search` مسارٌ
      // ضيّق: اسم وشعبة وحالة، بصلاحية من يعمل في الميدان.
      api('gate/search?q=' + encodeURIComponent(q))
        .then(function (data) {
          resEl.textContent = '';
          (data.items || []).forEach(function (s) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'schg-result';

            var strong = document.createElement('strong');
            strong.textContent = s.name;
            b.appendChild(strong);

            var sub = document.createElement('span');
            sub.textContent = [s.class, s.state_label].filter(Boolean).join(' · ');
            b.appendChild(sub);

            b.addEventListener('click', function () {
              searchEl.value = '';
              resEl.textContent = '';
              submit({ student: s });
            });
            resEl.appendChild(b);
          });
        })
        .catch(function () { resEl.textContent = ''; });
    }, 300);
  });

  window.SCHField.keepAwake();
  startCamera();
})();
