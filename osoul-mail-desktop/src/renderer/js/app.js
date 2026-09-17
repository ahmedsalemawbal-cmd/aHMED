/**
 * Osoul Mail — إقلاع الواجهة.
 *
 * المسار: قراءة الإعدادات → رسم آخر لقطة محفوظة فورًا إن وُجدت →
 * استئناف الجلسة في الخلفية → استبدال اللقطة ببيانات الخادم.
 *
 * اللقطة هي ما يجعل الفتح فوريًا: بدونها يقف الموظف أمام شاشة انتظار حتى
 * يتم اتصال IMAP ويردّ الخادم بالمجلدات وأول صفحة.
 */

import { $, toast } from './util.js';
import { renderLogin } from './login.js';
import { startMail, S } from './mail.js';
import { t, setLang } from './i18n.js';

let boot = null;

async function main() {
  const res = await window.osoul.boot();
  if (!res.ok) {
    showFatal(t('appBootFailed'));
    return;
  }
  boot = res.data;

  // السمة تُطبَّق قبل أي رسم حتى لا تومض الشاشة.
  document.documentElement.dataset.theme = boot.settings.theme === 'light' ? 'light' : 'dark';
  setLang(boot.settings.lang);

  if (!boot.saved) {
    showLogin('');
    return;
  }

  // اللقطة أولًا: صندوق كامل على الشاشة قبل أن تبدأ الشبكة.
  let painted = false;
  if (boot.cached) {
    S.version = boot.version;
    S.stale = true;
    startMail(boot.cached, boot);
    painted = true;
  }

  const resumed = await window.osoul.resume();
  if (resumed.ok && resumed.data && resumed.data.resumed) {
    S.version = boot.version;
    S.stale = false;
    startMail(resumed.data, boot);
    return;
  }

  // البيانات المحفوظة لم تعد صالحة: لا نترك لقطة قديمة تبدو حيّة.
  const message = (resumed.error && resumed.error.ar) || '';
  if (painted) {
    if (resumed.ok === false && resumed.error && resumed.error.code === 'NETWORK') {
      // عطل شبكي مؤقت: اللقطة تبقى معروضة ويُخبَر الموظف أنها غير محدّثة.
      S.conn = 'offline';
      toast(t('offlineSnapshot'), 'err');
      return;
    }
    $('#app').hidden = true;
  }
  showLogin(resumed.ok ? '' : message);
}

function showLogin(message) {
  $('#boot').hidden = true;
  renderLogin(boot, (data) => {
    S.version = boot.version;
    S.stale = false;
    startMail(data, boot);
  });
  if (message) {
    const box = $('#lg-err');
    if (box) {
      box.textContent = message;
      box.hidden = false;
    }
  }
}

function showFatal(text) {
  $('#boot').innerHTML = `<div class="in"><p>${text}</p></div>`;
}

// أخطاء غير متوقعة يجب أن تظهر للموظف لا أن تختفي في السجلات.
window.addEventListener('unhandledrejection', (e) => {
  console.error('unhandled', e.reason);
  toast(t('unexpected'), 'err');
});

main();
