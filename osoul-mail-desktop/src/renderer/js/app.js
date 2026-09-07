/**
 * Osoul Mail — إقلاع الواجهة.
 *
 * المسار: قراءة الإعدادات → محاولة استئناف جلسة محفوظة بصمت →
 * إمّا صندوق البريد مباشرة، وإمّا بوابة الدخول.
 */

import { $, toast } from './util.js';
import { renderLogin } from './login.js';
import { startMail, S } from './mail.js';

let boot = null;

async function main() {
  const res = await window.osoul.boot();
  if (!res.ok) {
    showFatal('تعذّر تشغيل التطبيق. أعد فتحه من جديد.');
    return;
  }
  boot = res.data;

  // السمة تُطبَّق قبل أي رسم حتى لا تومض الشاشة.
  document.documentElement.dataset.theme = boot.settings.theme === 'light' ? 'light' : 'dark';

  if (boot.saved) {
    const resumed = await window.osoul.resume();
    if (resumed.ok && resumed.data && resumed.data.resumed) {
      S.version = boot.version;
      startMail(resumed.data, boot);
      return;
    }
    // بيانات محفوظة لم تعد صالحة (تغيّرت كلمة المرور أو أُوقف الحساب).
    if (resumed.ok === false && resumed.error && resumed.error.code !== 'NETWORK') {
      showLogin(resumed.error.ar);
      return;
    }
    showLogin(resumed.ok ? '' : (resumed.error && resumed.error.ar) || '');
    return;
  }

  showLogin('');
}

function showLogin(message) {
  $('#boot').hidden = true;
  renderLogin(boot, (data) => {
    S.version = boot.version;
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
  toast('حدث خطأ غير متوقع.', 'err');
});

main();
