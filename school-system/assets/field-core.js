/**
 * المحرك الميداني المشترك.
 *
 * المنطق الصعب في التطبيقات الميدانية ليس الشاشة — بل:
 * الطابور المحلي، ومنع التكرار، والمزامنة بعد انقطاع الشبكة.
 * يُكتب هنا مرة واحدة، وتُبنى فوقه قشرتان: تطبيق السائق وتطبيق البوابة.
 *
 * ثلاث قواعد:
 * 1. كل عملية تُحفظ محليًا أولًا ثم تُرسل — لا شيء يضيع.
 * 2. كل عملية تحمل client_uuid ثابتًا — إعادة الإرسال لا تنشئ سجلًا ثانيًا.
 * 3. رد 4xx **الدائم** يُسقط العنصر من الطابور حتى لا يعلق؛ فشل الشبكة
 *    وانتهاء النونس يعيدان المحاولة — الأول لأنه عابر، والثاني لأنه يُصلَح.
 */
window.SCHField = (function () {
  'use strict';

  function uuid() {
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }

  /** توقيت محلي لا UTC — الطابور قد يُرسَل بعد ساعة من الحدث. */
  function stamp() {
    var d = new Date();
    var p = function (n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
      + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  }

  /*
   * الحالات التي تُعاد المحاولة معها لا تُسقَط.
   *
   * 401/403 = نونس REST انتهى — والحارس يترك جهازه مفتوحًا طوال اليوم، فكل
   * مسحاته بعد ١٢ ساعة كانت **تُمحى نهائيًّا** وهو يرى «تمّ». و408/429/5xx
   * حالات خادم عابرة. الباقي (400 · 404 · 409 · 422) خطأٌ في العملية نفسها
   * لا يُصلحه التكرار.
   */
  var RETRY = [401, 403, 408, 429];
  var MAX_TRIES = 12;

  function Queue(opts) {
    this.key      = opts.key;
    this.endpoint = opts.endpoint;
    this.api      = opts.api;
    this.nonce    = opts.nonce;
    this.nonceUrl = opts.nonceUrl || '';
    this.onChange = opts.onChange || function () {};
    this.onResult = opts.onResult || function () {};
    this.busy     = false;
    this.renewing = false;

    var self = this;
    window.addEventListener('online', function () { self.flush(); });
    window.addEventListener('offline', function () { self.onChange(self.pending(), false); });
    setInterval(function () { self.flush(); }, 20000);
  }

  Queue.prototype.read = function () {
    try { return JSON.parse(localStorage.getItem(this.key) || '[]'); }
    catch (e) { return []; }
  };

  Queue.prototype.write = function (q) {
    try { localStorage.setItem(this.key, JSON.stringify(q)); } catch (e) { /* الذاكرة ممتلئة */ }
    this.onChange(q.length, navigator.onLine);
  };

  Queue.prototype.pending = function () { return this.read().length; };

  /** إضافة عملية — تُحفظ فورًا ثم تُرسل. */
  Queue.prototype.push = function (payload) {
    var item = Object.assign({ client_uuid: uuid(), occurred_at: stamp() }, payload);
    var q = this.read();
    q.push(item);
    this.write(q);
    this.flush();
    return item;
  };

  /**
   * تجديد نونس REST من ووردبريس نفسه.
   *
   * الجلسة (الكوكي) ما زالت سارية — النونس وحده هو ما انتهى. و`rest-nonce`
   * فعلٌ من نواة ووردبريس يُرجع نونسًا جديدًا لمن هو داخلٌ فعلًا.
   */
  Queue.prototype.renew = function () {
    if (this.renewing || !this.nonceUrl) { return; }

    var self = this;
    this.renewing = true;

    fetch(this.nonceUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (t) {
        t = (t || '').trim();
        if (/^[A-Za-z0-9]{8,20}$/.test(t)) { self.nonce = t; }
      })
      .catch(function () {})
      .then(function () { self.renewing = false; });
  };

  Queue.prototype.flush = function () {
    if (this.busy || !navigator.onLine) { return; }

    var q = this.read();
    if (!q.length) { this.onChange(0, true); return; }

    var self = this;
    var item = q[0];
    this.busy = true;

    fetch(this.api + this.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.nonce },
      body: JSON.stringify(item)
    })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (body) {
          return { ok: r.ok, status: r.status, body: body };
        });
      })
      .then(function (res) {
        var retryable = RETRY.indexOf(res.status) !== -1 || res.status >= 500;

        if (retryable) {
          if (res.status === 401 || res.status === 403) { self.renew(); }

          // شبكة أمان: لا يعلق عنصرٌ إلى الأبد. بعد اثنتي عشرة محاولة
          // يُسقَط ويُبلَّغ به — والسكوت أسوأ من الرسالة.
          var q2 = self.read();
          if (q2.length && q2[0].client_uuid === item.client_uuid) {
            q2[0]._tries = (q2[0]._tries || 0) + 1;
            if (q2[0]._tries >= MAX_TRIES) {
              q2.shift();
              self.write(q2);
              self.onResult(res, item);
              return;
            }
            self.write(q2);
          }
          return;
        }

        // 4xx دائم أو نجاح — أسقطه حتى لا يعلق الطابور.
        if (res.ok || (res.status >= 400 && res.status < 500)) {
          var rest = self.read();
          rest.shift();
          self.write(rest);
          self.onResult(res, item);
        }
      })
      .catch(function () { /* الشبكة — نعيد لاحقًا */ })
      .then(function () {
        self.busy = false;
        if (self.read().length) { setTimeout(function () { self.flush(); }, 1200); }
      });
  };

  /** إبقاء الشاشة مضاءة — بدونها يتوقف العمل عند إطفائها. */
  function keepAwake() {
    if (!('wakeLock' in navigator)) { return; }
    navigator.wakeLock.request('screen').catch(function () {});
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') { keepAwake(); }
  });

  return { Queue: Queue, uuid: uuid, stamp: stamp, keepAwake: keepAwake };
})();
