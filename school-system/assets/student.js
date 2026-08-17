/**
 * تطبيق الطالب: لوح الرسم وقناة نتيجة اللعبة.
 */
(function () {
  'use strict';

  /* ---------- قناة اللعبة الوحيدة ----------
     اللعبة تعمل في إطار معزول بلا هوية أصل وبلا شبكة.
     المخرج الوحيد رسالة واحدة: النتيجة. ونتحقق من شكلها قبل قبولها. */
  var stage = document.querySelector('.schs-stage');

  if (stage) {
    var started = Date.now();
    var scoreEl = document.getElementById('schs-score');
    var contentId = parseInt(stage.dataset.content, 10) || 0;
    var sent = false;

    window.addEventListener('message', function (e) {
      var d = e.data;

      // أي رسالة لا تطابق هذا الشكل بالضبط تُتجاهل.
      if (!d || d.type !== 'sch_score' || typeof d.value !== 'number' || !isFinite(d.value)) { return; }

      var score = Math.max(0, Math.min(65535, Math.round(d.value)));

      if (scoreEl) {
        scoreEl.hidden = false;
        scoreEl.textContent = 'نتيجتك: ' + score;
      }

      if (sent) { return; }
      sent = true;

      var api = document.body.dataset.api;
      var nonce = document.body.dataset.nonce;
      if (!api || !nonce || !contentId) { return; }

      fetch(api + 'content/' + contentId + '/open', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({
          score: score,
          seconds: Math.round((Date.now() - started) / 1000)
        })
      }).catch(function () {});
    });
  }

  /* ---------- التصريح المتجدد ----------
     يُطلب رمز جديد من الخادم عند انتهاء النافذة.
     المفتاح لا يغادر الخادم أبدًا — الجوال يعرض الصورة فقط. */
  var pass = document.getElementById('schs-pass');

  if (pass) {
    var api = pass.dataset.api;
    var span = parseInt(pass.dataset.window, 10) || 30;
    var left = parseInt(pass.dataset.left, 10) || span;
    var ring = document.getElementById('schs-ring');
    var lbl = document.getElementById('schs-left');
    var busy = false;

    // الشاشة تبقى مضاءة: الطالب يرفع جواله أمام القارئ ولا يريد أن ينطفئ.
    if ('wakeLock' in navigator) { navigator.wakeLock.request('screen').catch(function () {}); }

    function paint() {
      if (lbl) { lbl.textContent = 'يتجدّد بعد ' + left + ' ثانية'; }
      if (ring) { ring.style.strokeDashoffset = (69.1 * (span - left) / span).toFixed(1); }
    }

    function refresh() {
      if (busy) { return; }
      busy = true;

      fetch(api, { credentials: 'same-origin', headers: { 'X-WP-Nonce': document.body.dataset.nonce } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.svg) { return; }
          (document.getElementById('schs-pass-qr') || {}).innerHTML = d.svg;
          left = d.expires_in || span;
          span = d.window || span;
          if (ring) {
            ring.style.transition = 'none';
            ring.style.strokeDashoffset = 0;
            setTimeout(function () { ring.style.transition = 'stroke-dashoffset 1s linear'; }, 30);
          }
          paint();
        })
        .catch(function () {})
        .then(function () { busy = false; });
    }

    paint();

    setInterval(function () {
      left--;
      if (left <= 0) { refresh(); return; }
      paint();
    }, 1000);

    // العودة من الخلفية بعد دقائق: الرمز الظاهر منتهٍ — نجدّده فورًا.
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') { refresh(); }
    });
  }

  /* ---------- لوح الرسم ----------
     الاسترجاع بالرسم يثبّت المعلومة أقوى من إعادة القراءة،
     وهو الشيء الوحيد الذي يجيده الابتدائي أكثر من الكتابة. */
  var canvas = document.getElementById('schs-canvas');
  if (!canvas) { return; }

  var ctx = canvas.getContext('2d');
  var strokes = [];
  var current = null;
  var color = '#0F1720';

  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  function redraw() {
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    strokes.forEach(function (s) {
      if (s.points.length < 2) { return; }
      ctx.strokeStyle = s.color;
      ctx.lineWidth = s.width;
      ctx.beginPath();
      ctx.moveTo(s.points[0].x, s.points[0].y);
      for (var i = 1; i < s.points.length; i++) {
        ctx.lineTo(s.points[i].x, s.points[i].y);
      }
      ctx.stroke();
    });
  }

  function pos(e) {
    var r = canvas.getBoundingClientRect();
    return {
      x: (e.clientX - r.left) * (canvas.width / r.width),
      y: (e.clientY - r.top) * (canvas.height / r.height)
    };
  }

  canvas.addEventListener('pointerdown', function (e) {
    canvas.setPointerCapture(e.pointerId);
    current = { color: color, width: 5, points: [pos(e)] };
    strokes.push(current);
  });

  canvas.addEventListener('pointermove', function (e) {
    if (!current) { return; }
    current.points.push(pos(e));
    redraw();
  });

  function end() { current = null; }
  canvas.addEventListener('pointerup', end);
  canvas.addEventListener('pointercancel', end);
  canvas.addEventListener('pointerleave', end);

  document.querySelectorAll('.schs-tool[data-color]').forEach(function (b) {
    b.addEventListener('click', function () {
      color = b.dataset.color;
      document.querySelectorAll('.schs-tool[data-color]').forEach(function (x) {
        x.classList.toggle('is-on', x === b);
      });
    });
  });

  var undo = document.getElementById('schs-undo');
  if (undo) { undo.addEventListener('click', function () { strokes.pop(); redraw(); }); }

  var clear = document.getElementById('schs-clear');
  if (clear) { clear.addEventListener('click', function () { strokes = []; redraw(); }); }

  var form = document.getElementById('schs-hw-form');
  var data = document.getElementById('schs-draw-data');

  if (form && data) {
    form.addEventListener('submit', function () {
      if (strokes.length) { data.value = canvas.toDataURL('image/png'); }
    });
  }
})();
