/**
 * تطبيق السائق — منطق الرحلة.
 *
 * ثلاث قواعد يقوم عليها الملف:
 * 1. كل تسجيل يُحفظ محليًا أولًا ثم يُرسل. انقطاع الشبكة لا يفقد شيئًا.
 * 2. كل تسجيل يحمل client_uuid ثابتًا، فإعادة الإرسال لا تُنشئ سجلًا ثانيًا.
 * 3. الواجهة تعمل بالمس بيد واحدة: أزرار كبيرة وقائمة واحدة.
 */
(function () {
  'use strict';

  var CFG = window.SCH_DRIVER;
  if (!CFG) return;

  var QUEUE_KEY = 'sch_drv_queue_' + CFG.tripId;
  var GPS_EVERY = 15000;

  var listEl   = document.getElementById('schd-list');
  var netEl    = document.getElementById('schd-net');
  var gpsEl    = document.getElementById('schd-gps');
  var countsEl = document.getElementById('schd-counts');
  var endBtn   = document.getElementById('schd-end');

  var pickEl   = document.getElementById('schd-pick');
  var pickWho  = document.getElementById('schd-pick-who');
  var pickList = document.getElementById('schd-pick-list');
  var pickX    = document.getElementById('schd-pick-cancel');

  var students = [];
  var lastSent = 0;
  var wakeLock = null;

  // ---------- أدوات ----------

  function localStamp() {
    var d = new Date();
    var p = function (n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
      + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  }

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }

  function api(path, options) {
    return fetch(CFG.api + path, Object.assign({
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce }
    }, options || {}));
  }

  function apiJson(path) {
    return api(path).then(function (r) {
      if (!r.ok) { throw new Error(String(r.status)); }
      return r.json();
    });
  }

  /**
   * تجديد نونس REST — الجلسة سارية والنونس وحده انتهى، والرحلة قد تمتدّ
   * بعد اثنتي عشرة ساعة فتُمحى تسجيلات السائق كلّها بردّ 403.
   */
  var renewing = false;

  function renewNonce() {
    if (renewing || !CFG.nonceUrl) { return; }
    renewing = true;

    fetch(CFG.nonceUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (t) {
        t = (t || '').trim();
        if (/^[A-Za-z0-9]{8,20}$/.test(t)) { CFG.nonce = t; }
      })
      .catch(function () {})
      .then(function () { renewing = false; });
  }

  /** رسالة فوق القائمة — الرفض الصامت يجعل السائق يظنّ أن كل شيء تمّ. */
  function toast(message) {
    var box = document.getElementById('schd-toast');

    if (!box) {
      box = document.createElement('div');
      box.id = 'schd-toast';
      box.className = 'schd-toast';
      box.setAttribute('role', 'status');
      (listEl.parentNode || document.body).insertBefore(box, listEl);
    }

    box.textContent = message;
    box.hidden = false;
    clearTimeout(box._t);
    box._t = setTimeout(function () { box.hidden = true; }, 6000);
  }

  function readQueue() {
    try { return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]'); }
    catch (e) { return []; }
  }

  function writeQueue(q) {
    try { localStorage.setItem(QUEUE_KEY, JSON.stringify(q)); } catch (e) { /* الذاكرة ممتلئة */ }
  }

  function setNet() {
    var pending = readQueue().length;
    if (!navigator.onLine) {
      netEl.textContent = CFG.i18n.offline + (pending ? ' · ' + pending : '');
      netEl.className = 'schd-pill schd-pill--bad';
      return;
    }
    netEl.textContent = pending ? CFG.i18n.queued + ' · ' + pending : CFG.i18n.synced;
    netEl.className = 'schd-pill' + (pending ? ' schd-pill--warn' : ' schd-pill--ok');
  }

  // ---------- قائمة الطلاب ----------

  function stateOf(s) {
    if (s.alighted_at) return 'alight';
    if (s.boarded_at) return 'board';
    if (s.absent) return 'absent';
    return '';
  }

  function render() {
    if (!students.length) {
      listEl.innerHTML = '<div class="schd-empty"><strong>لا يوجد طلاب مشتركون في هذا المسار</strong></div>';
      countsEl.textContent = '—';
      return;
    }

    var onboard = 0, done = 0;

    listEl.innerHTML = '';

    students.forEach(function (s) {
      var state = stateOf(s);
      if (state === 'board') onboard++;
      if (state === 'alight') done++;

      var row = document.createElement('div');
      row.className = 'schd-row schd-row--' + (s.excused ? 'excused' : (state || 'pending'));

      var name = document.createElement('div');
      name.className = 'schd-row__name';
      name.innerHTML = '<strong></strong><span></span>';
      name.querySelector('strong').textContent = s.name;
      name.querySelector('span').textContent = s.excused ? 'معذور اليوم' : (s.stop || '');
      row.appendChild(name);

      var actions = document.createElement('div');
      actions.className = 'schd-row__actions';

      if (s.phone) {
        var call = document.createElement('a');
        call.className = 'schd-act schd-act--call';
        call.href = 'tel:' + s.phone;
        call.textContent = '☎';
        call.setAttribute('aria-label', 'اتصال بولي الأمر');
        actions.appendChild(call);
      }

      [['board', CFG.i18n.board], ['alight', CFG.i18n.alight], ['absent', CFG.i18n.absent]].forEach(function (pair) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'schd-act schd-act--' + pair[0] + (state === pair[0] ? ' is-on' : '');
        b.textContent = pair[1];
        b.addEventListener('click', function () { mark(s, pair[0]); });
        actions.appendChild(b);
      });

      row.appendChild(actions);
      listEl.appendChild(row);
    });

    countsEl.textContent = 'في الباص ' + onboard + ' · نزل ' + done + ' · الإجمالي ' + students.length;
  }

  // ---------- اختيار المستلم ----------

  var picking = false;

  function closePick() {
    pickEl.hidden = true;
    pickList.textContent = '';
    picking = false;
  }

  if (pickX) { pickX.addEventListener('click', closePick); }

  /**
   * قائمة الأب لا خانة كتابة.
   *
   * `SCH_Custody` تشترط مفتاحًا من `pickers()`، وكان السائق يُسأل الاسم
   * نصًّا — فلا ينزل طفل روضة ولا ابتدائي أبدًا وتبقى عهدته `on_bus` إلى
   * الصباح. ومن لا مخوَّل له تُعرض رسالة صريحة: قرار التسليم ليس للسائق.
   */
  function askPicker(student, done) {
    if (picking) { return; }
    picking = true;

    apiJson('gate/pickers/' + student.student_id)
      .then(function (d) {
        var items = d.items || [];

        if (!items.length) {
          closePick();
          toast(CFG.i18n.noPickers);
          return;
        }

        pickList.textContent = '';
        pickWho.textContent = student.name + (student.stop ? ' — ' + student.stop : '');

        items.forEach(function (p) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'schd-pick__one';

          var strong = document.createElement('strong');
          strong.textContent = p.name;
          b.appendChild(strong);

          var sub = document.createElement('span');
          sub.textContent = p.until
            ? p.relation + ' · ' + CFG.i18n.until + ' ' + String(p.until).slice(0, 10)
            : p.relation;
          b.appendChild(sub);

          b.addEventListener('click', function () {
            closePick();
            done(p);
          });

          pickList.appendChild(b);
        });

        pickEl.hidden = false;
      })
      .catch(function () {
        closePick();
        toast(CFG.i18n.pickErr);
      });
  }

  function mark(student, type) {
    // النقطة القادمة أولًا — النزول خارج الترتيب تحذير لا منع.
    if (type === 'alight' && !student.is_next) {
      if (!window.confirm('نقطته لم تصل بعد. متأكد أنه ينزل هنا؟')) { return; }
    }

    // الروضة والابتدائي: لا تسليم بلا مخوَّل من قائمة الأب.
    if (type === 'alight' && student.needs_receiver) {
      askPicker(student, function (p) {
        commit(student, type, p.key, p.name);
      });
      return;
    }

    commit(student, type, '', '');
  }

  function commit(student, type, pickerKey, pickerName) {
    // تحديث فوري في الواجهة — السائق لا ينتظر الشبكة.
    if (type === 'board')  { student.boarded_at = new Date().toISOString(); student.absent = false; }
    if (type === 'alight') { student.alighted_at = new Date().toISOString(); }
    if (type === 'absent') { student.absent = true; student.boarded_at = null; }
    render();

    var q = readQueue();
    q.push({
      student_id: student.student_id,
      type: type,
      picker: pickerKey || '',
      receiver_name: pickerName || '',
      client_uuid: uuid(),
      occurred_at: localStamp()
    });
    writeQueue(q);
    setNet();

    flush();
  }

  var flushing = false;

  function flush() {
    if (flushing || !navigator.onLine) return;

    var q = readQueue();
    if (!q.length) { setNet(); return; }

    flushing = true;
    var item = q[0];

    api('driver/trip/' + CFG.tripId + '/scan', {
      method: 'POST',
      body: JSON.stringify(item)
    })
      .then(function (r) {
        /*
         * 401/403 = نونس انتهى، و408/429/5xx حالات عابرة — كلّها تُعاد
         * لا تُسقَط. وكان الشرط يُسقط كل 4xx، فرحلةٌ امتدّت بعد انتهاء
         * النونس تُمحى تسجيلاتها كلّها والسائق يرى «تمت المزامنة».
         */
        var retry = r.status === 401 || r.status === 403
                 || r.status === 408 || r.status === 429 || r.status >= 500;

        if (retry) {
          if (r.status === 401 || r.status === 403) { renewNonce(); }

          var q2 = readQueue();
          if (q2.length && q2[0].client_uuid === item.client_uuid) {
            q2[0]._tries = (q2[0]._tries || 0) + 1;
            if (q2[0]._tries >= 12) { q2.shift(); }
            writeQueue(q2);
          }
          return;
        }

        var rest = readQueue();
        rest.shift();
        writeQueue(rest);

        // رفضٌ من الخادم: الواجهة كانت تُحدَّث تفاؤلًا قبل القبول، فيرى
        // السائق «نزل» ✔ والعهدة لم تنتقل. نُبلّغه ونُعيد الحالة الحقيقية.
        if (!r.ok) {
          return r.json().catch(function () { return {}; }).then(function (body) {
            toast(body && body.message ? body.message : CFG.i18n.rejected);
            loadManifest();
          });
        }
      })
      .catch(function () { /* الشبكة — نعيد لاحقًا */ })
      .then(function () {
        flushing = false;
        setNet();
        if (readQueue().length) setTimeout(flush, 1200);
      });
  }

  function loadManifest() {
    api('driver/trip/' + CFG.tripId + '/manifest')
      .then(function (r) { return r.json(); })
      .then(function (rows) {
        students = (rows || []).map(function (r) {
          return {
            student_id: r.student_id,
            name: r.name,
            stop: r.stop,
            phone: r.phone,
            needs_receiver: !!r.needs_receiver,
            excused: !!r.excused,
            stop_id: r.stop_id,
            stop_seq: r.stop_seq,
            is_next: !!r.is_next,
            boarded_at: r.boarded_at,
            alighted_at: r.alighted_at,
            absent: false
          };
        });
        render();
      })
      .catch(function () {
        listEl.innerHTML = '<div class="schd-empty"><strong>تعذّر تحميل القائمة</strong><p>تحقق من الشبكة.</p></div>';
      });
  }

  // ---------- الموقع ----------

  function startGps() {
    if (!navigator.geolocation) {
      gpsEl.textContent = CFG.i18n.gpsOff;
      return;
    }

    navigator.geolocation.watchPosition(function (pos) {
      gpsEl.textContent = CFG.i18n.gpsOn;
      gpsEl.className = 'schd-pill schd-pill--ok';

      var now = Date.now();
      if (now - lastSent < GPS_EVERY || !navigator.onLine) return;
      lastSent = now;

      api('driver/trip/' + CFG.tripId + '/position', {
        method: 'POST',
        body: JSON.stringify({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          speed: pos.coords.speed ? Math.round(pos.coords.speed * 3.6) : null
        })
      }).catch(function () { lastSent = 0; });
    }, function () {
      gpsEl.textContent = CFG.i18n.gpsDenied;
      gpsEl.className = 'schd-pill schd-pill--bad';
    }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });
  }

  // إبقاء الشاشة مضاءة — بدونها يتوقف بث الموقع عند إطفائها.
  function keepAwake() {
    if (!('wakeLock' in navigator)) return;
    navigator.wakeLock.request('screen')
      .then(function (lock) { wakeLock = lock; })
      .catch(function () { /* مرفوض أو غير مدعوم */ });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      keepAwake();
      flush();
    }
  });

  // ---------- إنهاء الرحلة ----------

  endBtn.addEventListener('click', function () {
    if (readQueue().length) { flush(); }
    if (!window.confirm(CFG.i18n.confirm)) return;

    endBtn.disabled = true;

    api('driver/trip/' + CFG.tripId + '/close', { method: 'POST' })
      .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
      .then(function (res) {
        if (res.ok) { window.location.href = CFG.home; return; }
        endBtn.disabled = false;
        window.alert((res.body && res.body.message) || CFG.i18n.blocked);
      })
      .catch(function () {
        endBtn.disabled = false;
        window.alert(CFG.i18n.offline);
      });
  });

  // ---------- التشغيل ----------

  window.addEventListener('online', function () { setNet(); flush(); });
  window.addEventListener('offline', setNet);

  setNet();
  loadManifest();
  startGps();
  keepAwake();
  setInterval(flush, 20000);
})();
