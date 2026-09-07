/**
 * Osoul Mail — بوابة الدخول.
 *
 * حقلان فقط: البريد وكلمة المرور. خوادم البريد معبّأة مسبقًا من السياسة،
 * ولا تظهر إلا لمن يفتح "إعدادات متقدمة" عمدًا. لا تسجيل ولا استعادة كلمة
 * مرور ذاتية: الحسابات يُنشئها مسؤول النظام، والتطبيق بوابة لا لوحة إدارة.
 */

import { icon } from './icons.js';
import { $, on, esc, call, errText } from './util.js';

/**
 * رسم البوابة وانتظار دخول ناجح.
 *
 * @param {{policy:object, saved:object|null, canRemember:boolean, version:string}} boot
 * @param {(data:object) => void} onSuccess
 */
export function renderLogin(boot, onSuccess) {
  const root = $('#login');
  const policy = boot.policy || {};
  const saved = boot.saved || null;
  const domains = policy.allowedDomains || [];
  const hint = domains.length === 1 ? `@${domains[0]}` : 'name@company.com';

  root.innerHTML = `
    <form class="gate" autocomplete="on" novalidate>
      <div class="mark">
        <img src="assets/logo.png" alt="أصول البناء">
        <h1>بريد أصول البناء</h1>
        <p>OSOUL ALBINAA MAIL</p>
      </div>

      <div class="msg err" id="lg-err" hidden></div>

      <div class="field">
        <label for="lg-email">البريد الإلكتروني</label>
        <div class="wrap">
          ${icon('mail', 'sm')}
          <input id="lg-email" name="email" type="email" inputmode="email" dir="ltr"
                 placeholder="${esc(hint)}" autocomplete="username"
                 value="${esc(saved ? saved.email : '')}" spellcheck="false" required>
        </div>
      </div>

      <div class="field">
        <label for="lg-pass">كلمة المرور</label>
        <div class="wrap">
          ${icon('lock', 'sm')}
          <input id="lg-pass" name="password" type="password" dir="ltr"
                 placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="peek" id="lg-peek" tabindex="-1"
                  title="إظهار كلمة المرور" aria-label="إظهار كلمة المرور">${icon('eye', 'sm')}</button>
        </div>
      </div>

      <div class="row">
        <label class="check">
          <input type="checkbox" id="lg-remember" ${boot.canRemember ? 'checked' : 'disabled'}>
          <span>إبقائي مسجلًا للدخول</span>
        </label>
      </div>

      <button type="submit" class="btn primary wide" id="lg-go">
        <span class="lbl">دخول إلى بريدي</span>
      </button>

      ${policy.allowAdvancedServers ? advancedHTML(policy) : ''}

      <div class="foot">
        الدخول مقصور على الموظفين المعتمدين${domains.length ? ` في نطاق ${esc(domains.join('، '))}` : ''}.<br>
        للحصول على حساب أو إعادة تعيين كلمة المرور راجع مسؤول النظام.
      </div>
    </form>
  `;

  const form = $('form.gate', root);
  const email = $('#lg-email', root);
  const pass = $('#lg-pass', root);
  const go = $('#lg-go', root);
  const errBox = $('#lg-err', root);
  let busy = false;

  // إظهار/إخفاء كلمة المرور.
  on($('#lg-peek', root), 'click', () => {
    const shown = pass.type === 'text';
    pass.type = shown ? 'password' : 'text';
    $('#lg-peek', root).innerHTML = icon(shown ? 'eye' : 'eyeOff', 'sm');
    pass.focus();
  });

  function showError(text) {
    errBox.innerHTML = `${icon('alert', 'sm')}<span>${esc(text)}</span>`;
    errBox.hidden = false;
  }

  function clearError() {
    errBox.hidden = true;
  }

  on(email, 'input', clearError);
  on(pass, 'input', clearError);

  on(form, 'submit', async (e) => {
    e.preventDefault();
    if (busy) return;

    const payload = {
      email: email.value.trim(),
      password: pass.value,
      remember: $('#lg-remember', root).checked,
    };
    if (!payload.email) { showError('اكتب بريدك الإلكتروني.'); email.focus(); return; }
    if (!payload.password) { showError('اكتب كلمة المرور.'); pass.focus(); return; }

    const adv = $('#lg-adv', root);
    if (adv && adv.open) {
      payload.imapHost = $('#lg-imap-host', root).value.trim();
      payload.imapPort = $('#lg-imap-port', root).value.trim();
      payload.smtpHost = $('#lg-smtp-host', root).value.trim();
      payload.smtpPort = $('#lg-smtp-port', root).value.trim();
    }

    busy = true;
    clearError();
    setBusy(go, true, 'جارٍ التحقق…');

    try {
      const data = await call(window.osoul.login, payload);
      // نُبقي زر الدخول مشغولًا: الشاشة على وشك التبدّل.
      onSuccess(data);
    } catch (err) {
      showError(errText(err));
      setBusy(go, false, 'دخول إلى بريدي');
      busy = false;
      pass.select();
    }
  });

  root.hidden = false;
  setTimeout(() => (saved && saved.email ? pass : email).focus(), 60);
}

function advancedHTML(policy) {
  return `
    <details class="advanced" id="lg-adv">
      <summary>${icon('settings', 'sm')}<span>إعدادات الخادم المتقدمة</span></summary>
      <div class="field">
        <label>خادم الاستقبال IMAP</label>
        <div class="pair">
          <input id="lg-imap-host" type="text" dir="ltr" value="${esc(policy.imapHost || '')}" spellcheck="false">
          <input id="lg-imap-port" type="text" dir="ltr" value="${esc(String(policy.imapPort || 993))}" spellcheck="false">
        </div>
      </div>
      <div class="field">
        <label>خادم الإرسال SMTP</label>
        <div class="pair">
          <input id="lg-smtp-host" type="text" dir="ltr" value="${esc(policy.smtpHost || '')}" spellcheck="false">
          <input id="lg-smtp-port" type="text" dir="ltr" value="${esc(String(policy.smtpPort || 465))}" spellcheck="false">
        </div>
      </div>
    </details>
  `;
}

function setBusy(btn, busy, label) {
  btn.disabled = busy;
  btn.innerHTML = busy
    ? `<span class="spinner sm"></span><span class="lbl">${esc(label)}</span>`
    : `<span class="lbl">${esc(label)}</span>`;
}
