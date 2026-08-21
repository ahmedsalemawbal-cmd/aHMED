/**
 * تتبّع الباص الحي.
 *
 * الخريطة من OpenStreetMap عبر Leaflet: بلا مفتاح وبلا محاسبة بعدد الفتحات —
 * ولي أمر يفتحها مرتين يوميًا يعني آلاف الفتحات شهريًا لمدرسة واحدة،
 * ولا يصح أن تنمو فاتورتك مع نجاح منتجك.
 *
 * والأب لا يريد أن يحدّق في نقطة تتحرك — يريد أن يعرف متى ينزل لاستقبال ابنه.
 * فاللوح السفلي أهم من الخريطة نفسها.
 */
(function () {
  'use strict';

  var root = document.getElementById('p-track');
  if (!root || typeof L === 'undefined') { return; }

  /* عنصر مفقود لا يوقف الشاشة كلها: نُرجع كائنًا صامتًا بدل استثناء يكسر ما بعده. */
  var NOOP = { textContent: '', innerHTML: '', hidden: true, style: {},
               classList: { add: function () {}, remove: function () {}, toggle: function () {} } };
  function el(id) { return document.getElementById(id) || NOOP; }

  var API   = root.dataset.api;
  var NONCE = root.dataset.nonce;
  var AR    = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

  function ar(n) {
    return String(n).split('').map(function (d) {
      return /\d/.test(d) ? AR[+d] : d;
    }).join('');
  }

  var map = L.map('scha-map', {
    zoomControl: false,
    attributionControl: true
  }).setView([21.5433, 39.1728], 14);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  L.control.zoom({ position: 'bottomleft' }).addTo(map);

  /* أيقونة الباص: قرص أبيض يُبقيه مقروءًا فوق أي خلفية — شارع أبيض أو حديقة خضراء */
  function busIcon(flip) {
    return L.divIcon({
      className: 'sch-bus-icon',
      iconSize: [46, 46],
      iconAnchor: [23, 23],
      html:
        '<span class="sch-bus__halo"></span>' +
        '<span class="sch-bus__disc">' +
        '<svg viewBox="0 0 26 20" width="30" height="23" style="transform:scaleX(' + (flip ? -1 : 1) + ')">' +
        '<rect x="1" y="2" width="24" height="13" rx="3.5" fill="#F2B705"/>' +
        '<rect x="3.5" y="4.5" width="6" height="5" rx="1.2" fill="#0D1520" opacity=".72"/>' +
        '<rect x="11" y="4.5" width="6" height="5" rx="1.2" fill="#0D1520" opacity=".72"/>' +
        '<rect x="18.5" y="4.5" width="4" height="5" rx="1.2" fill="#BFE9FF"/>' +
        '<rect x="1" y="11.5" width="24" height="2" fill="#C99400"/>' +
        '<circle cx="7" cy="16" r="2.6" fill="#2C3A4B"/>' +
        '<circle cx="19" cy="16" r="2.6" fill="#2C3A4B"/>' +
        '</svg></span>'
    });
  }

  function stopIcon(state) {
    var cls = 'sch-stop sch-stop--' + state;
    var inner = state === 'mine'
      ? '<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M4 12 12 5l8 7v8h-6v-5h-4v5H4z"/></svg>'
      : '';

    return L.divIcon({
      className: cls,
      iconSize: state === 'mine' ? [28, 28] : [16, 16],
      iconAnchor: state === 'mine' ? [14, 14] : [8, 8],
      html: inner
    });
  }

  var bus = null;
  var line = null;
  var doneLine = null;
  var markers = [];
  var lastLatLng = null;
  var followed = false;

  function draw(d) {
    el('trk-off').hidden = d.live;

    if (!d.live) {
      el('trk-live').classList.add('is-off');
      return;
    }

    el('trk-dir').textContent =
      d.direction === 'morning' ? 'رحلة الذهاب' : 'رحلة العودة';
    el('trk-meta').textContent =
      [d.route, d.bus].filter(Boolean).join(' · ');

    /* المسار: المقطوع صلب والباقي متقطع */
    var pts = d.stops.filter(function (s) { return s.lat && s.lng; })
                     .map(function (s) { return [s.lat, s.lng]; });

    if (pts.length > 1) {
      if (line) { map.removeLayer(line); }
      line = L.polyline(pts, {
        color: '#C6D2DE', weight: 5, opacity: 1,
        dashArray: '9 9', lineCap: 'round', lineJoin: 'round'
      }).addTo(map);

      var donePts = d.stops.filter(function (s) { return s.done && s.lat && s.lng; })
                           .map(function (s) { return [s.lat, s.lng]; });

      if (d.position) { donePts.push([d.position.lat, d.position.lng]); }

      if (doneLine) { map.removeLayer(doneLine); }
      if (donePts.length > 1) {
        doneLine = L.polyline(donePts, {
          color: '#0F766E', weight: 5, lineCap: 'round', lineJoin: 'round'
        }).addTo(map);
      }
    }

    markers.forEach(function (m) { map.removeLayer(m); });
    markers = d.stops.filter(function (s) { return s.lat && s.lng; }).map(function (s) {
      var state = s.mine ? 'mine' : (s.done ? 'done' : 'next');
      return L.marker([s.lat, s.lng], { icon: stopIcon(state), title: s.name }).addTo(map);
    });

    if (d.position) {
      var ll = [d.position.lat, d.position.lng];
      var flip = lastLatLng && ll[1] < lastLatLng[1];

      if (bus) { map.removeLayer(bus); }
      bus = L.marker(ll, { icon: busIcon(flip), zIndexOffset: 900 }).addTo(map);
      lastLatLng = ll;

      if (!followed) {
        followed = true;
        var all = pts.concat([ll]);
        map.fitBounds(L.latLngBounds(all).pad(0.25));
      }
    }

    /* اللوح السفلي — ما يريده الأب فعلًا */
    var p = d.progress;

    if (p) {
      var left = p.stops_remaining;
      el('trk-min').innerHTML =
        ar(p.minutes) + '<span>' + (p.minutes === 1 ? 'دقيقة' : 'دقائق') + '</span>';

      el('trk-head').textContent =
        p.arrived ? 'الباص عند نقطتكم الآن'
                  : (left === 1 ? 'يبعد عنكم نقطة واحدة'
                                : 'يبعد عنكم ' + ar(left) + ' نقاط');

      var eta = new Date(Date.now() + p.minutes * 60000);
      el('trk-sub').textContent =
        'الوصول المتوقع ' + ar(eta.getHours()) + ':' + ar(String(eta.getMinutes()).padStart(2, '0'));

      var pct = p.stops_total ? (p.stops_done / p.stops_total) : 0;
      el('trk-ring').style.strokeDashoffset = (163.4 * (1 - pct)).toFixed(1);
    }

    el('trk-kid-s').textContent =
      d.child.onboard ? 'على متن الحافلة' : 'لم يصعد بعد';
    el('trk-kid').classList.toggle('is-off', !d.child.onboard);

    /*
     * اسم المحطّة نصٌّ حرّ يكتبه من يملك `sch_manage_transport` — وحقنُه
     * بـ`innerHTML` يجعله شفرةً تعمل في صفحةٍ تطبع نونس `wp_rest` حيًّا
     * وبجلسة وليّ الأمر. `textContent` يقفل الباب.
     *
     * (وكان المتغيّر المحليّ اسمه `el` فيطمس الدالة `el()` المعرَّفة أعلاه
     * في الملفّ نفسه — اسمٌ مستعارٌ لا يظهر أثره إلا حين يُستدعى بعده.)
     */
    var bar = el('trk-stops');
    bar.textContent = '';
    d.stops.forEach(function (s) {
      var dot = document.createElement('span');
      dot.className = 'scha-stp' + (s.done ? ' is-past' : '') + (s.mine ? ' is-mine' : '');
      dot.appendChild(document.createElement('i'));

      var label = document.createElement('span');
      label.textContent = s.name;
      dot.appendChild(label);

      bar.appendChild(dot);
    });
  }

  function pull() {
    fetch(API, { credentials: 'same-origin', headers: { 'X-WP-Nonce': NONCE } })
      .then(function (r) { return r.json(); })
      .then(draw)
      .catch(function () {
        el('trk-live').classList.add('is-off');
      });
  }

  pull();
  var timer = setInterval(pull, 15000);

  // التحديث يتوقف حين تختفي الشاشة — لا نستنزف بطارية الأب بلا فائدة.
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      pull();
      if (!timer) { timer = setInterval(pull, 15000); }
    } else {
      clearInterval(timer);
      timer = null;
    }
  });
})();
