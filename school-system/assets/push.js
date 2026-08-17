/**
 * الإشعارات الفورية — طرف المتصفح.
 *
 * ثلاث خطوات: نأخذ عامل الخدمة المسجَّل، نسأل الإذن، ثم نسلّم الاشتراك للخادم.
 *
 * **ولا نسأل الإذن عند فتح الصفحة.** المتصفح يحجب الطلب التلقائي، والمستخدم
 * الذي رفض مرة لا يُسأل ثانية أبدًا — فالطلب يخرج من زرّ يضغطه بنفسه
 * (`data-sch-push`)، إلا لمن سبق أن أذن فنجدّد اشتراكه صامتين.
 */
(function () {
  'use strict';

  var CFG = window.SCH_PUSH;

  if (!CFG || !CFG.key || !('serviceWorker' in navigator) || !('PushManager' in window)) {
    return;
  }

  /** المفتاح العام يصل نصًّا بـbase64url، وpushManager يريد بايتات. */
  function bytes(b64) {
    var s = (b64 + '='.repeat((4 - (b64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    var raw = window.atob(s);
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
    return out;
  }

  function b64(buf) {
    var b = new Uint8Array(buf);
    var s = '';
    for (var i = 0; i < b.length; i++) { s += String.fromCharCode(b[i]); }
    return window.btoa(s);
  }

  /** عائلة الجهاز لا بصمته — ليعرف صاحبه أي جهاز اشترك. */
  function device() {
    var ua = navigator.userAgent || '';
    if (/iPhone|iPad|iPod/i.test(ua)) { return 'iPhone / iPad'; }
    if (/Android/i.test(ua)) { return 'Android'; }
    if (/Windows/i.test(ua)) { return 'Windows'; }
    if (/Mac OS X/i.test(ua)) { return 'Mac'; }
    return 'جهاز';
  }

  function send(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce },
      body: JSON.stringify(body)
    });
  }

  function store(sub) {
    var j = sub.toJSON();
    return send(CFG.sub, {
      endpoint: j.endpoint,
      p256dh: (j.keys && j.keys.p256dh) || b64(sub.getKey('p256dh')),
      auth: (j.keys && j.keys.auth) || b64(sub.getKey('auth')),
      device: device()
    });
  }

  /**
   * `ready` ينتظر عامل الخدمة الذي يتحكّم بهذه الصفحة — وينتظر **إلى الأبد**
   * إن لم يكن ثمّة عامل. فالشاشة التي لا تسجّل عاملها تمرّر لنا مساره.
   */
  function worker() {
    if (!CFG.sw) { return navigator.serviceWorker.ready; }

    return navigator.serviceWorker.getRegistration()
      .then(function (r) { return r || navigator.serviceWorker.register(CFG.sw); })
      .then(function () { return navigator.serviceWorker.ready; });
  }

  /** الاشتراك ملك التسجيل الذي أنشأه — فلا يصل الإشعار عاملًا آخر. */
  function subscribe() {
    return worker().then(function (reg) {
      return reg.pushManager.getSubscription().then(function (has) {
        if (has) { return store(has); }
        return reg.pushManager
          .subscribe({ userVisibleOnly: true, applicationServerKey: bytes(CFG.key) })
          .then(store);
      });
    });
  }

  function paint(state) {
    var els = document.querySelectorAll('[data-sch-push]');
    for (var i = 0; i < els.length; i++) {
      els[i].setAttribute('data-sch-push-state', state);
      var say = els[i].querySelector('[data-sch-push-label]');
      if (!say) { continue; }
      say.textContent = state === 'on' ? 'الإشعارات مفعّلة'
        : state === 'blocked' ? 'الإشعارات محجوبة من المتصفح'
        : 'تفعيل الإشعارات';
    }
  }

  function ask() {
    if (Notification.permission === 'denied') { paint('blocked'); return; }

    Notification.requestPermission().then(function (p) {
      if (p !== 'granted') { paint(p === 'denied' ? 'blocked' : 'off'); return; }
      subscribe().then(function () { paint('on'); }).catch(function () { paint('off'); });
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-sch-push]') : null;
    if (btn) { e.preventDefault(); ask(); }
  });

  // من أذن سابقًا: نجدّد اشتراكه صامتين — الاشتراك ينتهي وحده أحيانًا،
  // وتجديده بلا سؤال هو الفرق بين إشعار يصل وإشعار يختفي بعد شهر.
  if (window.Notification && Notification.permission === 'granted') {
    subscribe().then(function () { paint('on'); }).catch(function () {});
  } else {
    paint(window.Notification && Notification.permission === 'denied' ? 'blocked' : 'off');
  }
}());
