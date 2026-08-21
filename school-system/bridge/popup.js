/**
 * لوح الجسر — خمس خطوات.
 *
 * الإعداد ← القراءة ← المطابقة ← الفحص ← الاستيراد. ولا صفّ يُرسَل قبل
 * أن يراه المستخدم في فحصٍ جافّ (`dry_run`)، ولا استيراد قبل ضغطةٍ ثانية.
 *
 * **والحقول تأتي من الخادم** (`/bridge/ping` تُرجع `SCH_Import::COLUMNS`)
 * لا مكتوبةً هنا — فحقلٌ يُضاف في النظام لا يحتاج إضافةً جديدة.
 */
(function () {
  'use strict';

  var API   = '/wp-json/school/v1/bridge/';
  var LIMIT = 500;   // حدّ الخادم في `SCH_Import::run_rows()`

  var el = function (id) { return document.getElementById(id); };

  var state = { site: '', key: '', fields: {}, table: null, map: {}, rows: [] };

  // ───────────────────────── أدوات ─────────────────────────

  function show(id, on) { el(id).hidden = !on; }

  function note(kind, text) {
    var n = el('note');
    n.className = 'note is-' + kind;
    n.textContent = text;
    show('result', true);
  }

  function listErrors(errors) {
    var ul = el('errors');
    ul.textContent = '';

    (errors || []).forEach(function (e) {
      var li = document.createElement('li');
      li.textContent = e;
      ul.appendChild(li);
    });
  }

  /** يُسوّى النصّ العربيّ للمقارنة: الهمزات والتاء المربوطة والتشكيل. */
  function flat(s) {
    return String(s || '')
      .replace(/[ً-ْـ]/g, '')
      .replace(/[إأآا]/g, 'ا')
      .replace(/ى/g, 'ي')
      .replace(/ة/g, 'ه')
      .replace(/[^ء-يa-z0-9]/gi, '')
      .toLowerCase();
  }

  function origin(url) {
    try { return new URL(url).origin; } catch (e) { return ''; }
  }

  /** نداء الخادم — والرسالة تُقرأ من الردّ لا تُخترع. */
  function call(path, body) {
    var opt = {
      method: body ? 'POST' : 'GET',
      headers: { 'X-SCH-Key': state.key }
    };

    if (body) {
      opt.headers['Content-Type'] = 'application/json';
      opt.body = JSON.stringify(body);
    }

    return fetch(state.site + API + path, opt).then(function (r) {
      return r.json().catch(function () {
        return { ok: false, errors: ['ردٌّ غير مفهوم من الخادم (' + r.status + ').'] };
      });
    });
  }

  // ───────────────────────── ① الإعداد ─────────────────────────

  function linked(ping) {
    el('who').textContent = (ping.school || '') + (ping.user ? ' · ' + ping.user : '');
    state.fields = ping.fields || {};

    show('setup', false);
    show('read', true);
    el('reset').hidden = false;
  }

  el('verify').addEventListener('click', function () {
    var site = origin(el('site').value.trim());
    var key  = el('key').value.trim();

    if (!site || site.indexOf('https://') !== 0) {
      note('bad', 'اكتب رابط المدرسة كاملًا ويبدأ بـhttps.');
      return;
    }
    if (!key) {
      note('bad', 'الصق مفتاح الربط.');
      return;
    }

    this.disabled = true;
    var btn = this;

    /* الصلاحية تُطلَب **لنطاق المدرسة وحده** ومن داخل الضغطة — ولا نطاق
       لنور إطلاقًا: قراءتها تمرّ بـ`activeTab` لحظة الضغط. */
    chrome.permissions.request({ origins: [site + '/*'] }, function (granted) {
      if (!granted) {
        btn.disabled = false;
        note('bad', 'لم تُمنح الإضافة إذن الاتصال بموقع المدرسة.');
        return;
      }

      state.site = site;
      state.key  = key;

      call('ping').then(function (res) {
        btn.disabled = false;

        if (!res || res.ok !== true) {
          note('bad', (res && res.error) || 'تعذّر التحقّق من المفتاح.');
          return;
        }

        chrome.storage.local.set({ site: site, key: key });
        show('result', false);
        linked(res);
      }).catch(function () {
        btn.disabled = false;
        note('bad', 'تعذّر الوصول إلى الموقع — تأكّد من الرابط ومن اتصالك.');
      });
    });
  });

  el('reset').addEventListener('click', function () {
    chrome.storage.local.remove(['site', 'key'], function () { window.location.reload(); });
  });

  // ───────────────────────── ② القراءة ─────────────────────────

  el('scan').addEventListener('click', function () {
    var box = el('tables');
    box.textContent = '';
    show('result', false);

    chrome.tabs.query({ active: true, currentWindow: true }, function (tabs) {
      var tab = tabs && tabs[0];

      if (!tab || !tab.id) {
        note('bad', 'لا صفحة مفتوحة.');
        return;
      }

      chrome.scripting.executeScript(
        { target: { tabId: tab.id }, files: ['reader.js'] },
        function (res) {
          if (chrome.runtime.lastError) {
            note('bad', 'تعذّرت قراءة هذه الصفحة — افتح صفحة الجدول في نور ثم أعد المحاولة.');
            return;
          }

          var found = (res && res[0] && res[0].result) || [];

          if (found.length === 0) {
            note('wait', 'لا جدول معروضًا في هذه الصفحة.');
            return;
          }

          found.forEach(function (t) {
            var b = document.createElement('button');
            b.type = 'button';

            var name = document.createElement('b');
            name.textContent = t.caption || ('جدول ' + (t.index + 1));

            var meta = document.createElement('span');
            meta.textContent = t.head.length + ' أعمدة · ' + t.rows.length + ' صفًّا';

            b.appendChild(name);
            b.appendChild(meta);
            b.addEventListener('click', function () { openMap(t); });
            box.appendChild(b);
          });
        }
      );
    });
  });

  // ───────────────────────── ③ المطابقة ─────────────────────────

  function guess(label, head) {
    var want = flat(label);
    var best = -1;

    head.forEach(function (h, i) {
      var got = flat(h);
      if (best === -1 && got && (got === want || got.indexOf(want) === 0 || want.indexOf(got) === 0)) {
        best = i;
      }
    });

    return best;
  }

  function openMap(table) {
    state.table = table;
    state.map   = {};

    var box = el('fields');
    box.textContent = '';

    Object.keys(state.fields).forEach(function (slug) {
      var label = state.fields[slug];
      var pick  = guess(label, table.head);

      state.map[slug] = pick;

      var row = document.createElement('div');
      row.className = 'fld' + (slug === 'full_name' ? ' fld--need' : '');

      var name = document.createElement('span');
      name.textContent = label;

      var sel = document.createElement('select');
      sel.setAttribute('aria-label', label);

      var none = document.createElement('option');
      none.value = '-1';
      none.textContent = '— بلا عمود —';
      sel.appendChild(none);

      table.head.forEach(function (h, i) {
        var o = document.createElement('option');
        o.value = String(i);
        o.textContent = h;
        if (i === pick) { o.selected = true; }
        sel.appendChild(o);
      });

      sel.addEventListener('change', function () { state.map[slug] = parseInt(this.value, 10); });

      row.appendChild(name);
      row.appendChild(sel);
      box.appendChild(row);
    });

    show('read', false);
    show('result', false);
    show('map', true);
  }

  el('back').addEventListener('click', function () {
    show('map', false);
    show('read', true);
  });

  el('again').addEventListener('click', function () {
    show('result', false);
    show('map', true);
  });

  // ───────────────────────── ④ الفحص و⑤ الاستيراد ─────────────────────────

  function build() {
    var t = state.table;

    return t.rows.map(function (cells) {
      var one = {};

      Object.keys(state.map).forEach(function (slug) {
        var i = state.map[slug];
        one[slug] = i >= 0 ? (cells[i] || '') : '';
      });

      return one;
    }).filter(function (r) {
      return (r.full_name || '') !== '' || (r.national_id || '') !== '';
    });
  }

  function send(dry, btn) {
    var rows = build();

    if (rows.length === 0) {
      note('bad', 'لا صفوف صالحة — راجع مطابقة عمود «اسم الطالب».');
      return;
    }

    /* حدّ الخادم خمسمئة صفّ. والتقسيم إلى دفعاتٍ **يكسر كشف الهوية
       المكرّرة بين الدفعات** — فيُقال العدد ويُطلب تضييق صفحة نور، ولا
       يُلتَفّ على الحدّ بصمت. */
    if (rows.length > LIMIT) {
      note('bad', 'الصفوف ' + rows.length + ' والحدّ ' + LIMIT + ' في المرة — صفِّ الصفحة في نور ثم أعد القراءة.');
      listErrors([]);
      el('commit').hidden = true;
      return;
    }

    state.rows = rows;
    btn.disabled = true;
    note('wait', dry ? 'جارٍ الفحص…' : 'جارٍ الاستيراد…');
    listErrors([]);

    call('import', { rows: rows, dry_run: dry }).then(function (res) {
      btn.disabled = false;
      res = res || {};

      if (res.ok !== true) {
        note('bad', 'وُجدت ملاحظات — أصلحها في نور ثم أعد القراءة.');
        listErrors(res.errors);
        el('commit').hidden = true;
        return;
      }

      if (dry) {
        note('ok', 'سليم — ' + rows.length + ' صفًّا جاهزة للاستيراد.');
        el('commit').hidden = false;
        return;
      }

      var msg = 'اُستورد ' + (res.imported || 0) + ' طالبًا.';
      if (res.orphans) { msg += ' و' + res.orphans + ' منهم بلا وليّ أمر — راجعهم في شاشة الطلاب.'; }

      note('ok', msg);
      el('commit').hidden = true;
    }).catch(function () {
      btn.disabled = false;
      note('bad', 'انقطع الاتصال بالموقع — أعد المحاولة.');
    });
  }

  el('check').addEventListener('click', function () { send(true, this); });
  el('commit').addEventListener('click', function () { send(false, this); });

  // ───────────────────────── البدء ─────────────────────────

  chrome.storage.local.get(['site', 'key'], function (saved) {
    if (!saved || !saved.site || !saved.key) { return; }

    state.site = saved.site;
    state.key  = saved.key;

    /* المفتاح محفوظ، والصلاحية قد تكون سُحبت من إعدادات المتصفّح — فتُفحص
       ولا تُفترض، وإلا سقط أوّل نداءٍ برسالةٍ لا تدلّ على السبب. */
    chrome.permissions.contains({ origins: [saved.site + '/*'] }, function (has) {
      if (!has) {
        el('site').value = saved.site;
        note('wait', 'إذن الاتصال بموقع المدرسة غير ممنوح — اضغط «تحقّق واربط».');
        return;
      }

      call('ping').then(function (res) {
        if (res && res.ok === true) { linked(res); return; }

        el('site').value = saved.site;
        note('bad', (res && res.error) || 'انتهت صلاحية المفتاح — أصدر مفتاحًا جديدًا.');
      }).catch(function () {
        el('site').value = saved.site;
        note('wait', 'تعذّر الوصول إلى الموقع الآن.');
      });
    });
  });
})();
