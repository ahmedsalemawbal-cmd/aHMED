/**
 * قارئ الجداول — يُحقَن في صفحة نور **بضغطة المستخدم وحدها**.
 *
 * ولا يعرف نور: يجد كل `<table>` معروضٍ فيه صفّان فأكثر، ويُرجع ترويسته
 * وصفوفه. والربط بأسماء عناصر بعينها كان سيتعطّل مع أوّل تحديثٍ لواجهة
 * الوزارة — والقاعدة مكتوبة في `CLAUDE.md` منذ بُني الجسر.
 *
 * ويُرجع قيمةً واحدة (آخر تعبير) لأن `chrome.scripting.executeScript`
 * تقرأ نتيجة الدالّة لا متغيّرًا عامًّا.
 */
(function () {
  'use strict';

  var MAX_ROWS = 800;   // ما فوقه لا يُقرأ: الحدّ الحقيقيّ ٥٠٠ في الخادم
  var MAX_COLS = 40;

  function text(el) {
    return (el.innerText || el.textContent || '')
      .replace(/ /g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  /* الجدول المخفيّ ليس معروضًا — ونور يحمل جداول تخطيطٍ مطويّة. */
  function shown(t) {
    var r = t.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  }

  var out = [];

  Array.prototype.forEach.call(document.querySelectorAll('table'), function (t, i) {
    if (!shown(t)) { return; }

    var trs = Array.prototype.slice.call(t.rows, 0, MAX_ROWS + 1);
    if (trs.length < 2) { return; }

    var grid = trs.map(function (tr) {
      return Array.prototype.slice.call(tr.cells, 0, MAX_COLS).map(text);
    });

    /* الترويسة: أوّل صفّ فيه `<th>`، وإلا فالأوّل. وجدولٌ بلا ترويسةٍ
       حقيقية يبقى قابلًا للمطابقة — الأعمدة تُسمّى بأرقامها. */
    var headIndex = 0;
    for (var k = 0; k < trs.length && k < 3; k++) {
      if (trs[k].querySelector('th')) { headIndex = k; break; }
    }

    var head = grid[headIndex].map(function (h, c) {
      return h !== '' ? h : 'عمود ' + (c + 1);
    });

    var body = grid.slice(headIndex + 1).filter(function (r) {
      return r.some(function (c) { return c !== ''; });
    });

    if (body.length === 0) { return; }

    out.push({
      index: i,
      head: head,
      rows: body,
      /* عنوانٌ يُميّزه في القائمة: أقرب عنوانٍ فوقه إن وُجد. */
      caption: (function () {
        var cap = t.caption ? text(t.caption) : '';
        if (cap) { return cap; }
        var p = t.previousElementSibling;
        for (var n = 0; p && n < 3; n++, p = p.previousElementSibling) {
          if (/^H[1-6]$/.test(p.tagName)) { return text(p).slice(0, 60); }
        }
        return '';
      })()
    });
  });

  return out;
})();
