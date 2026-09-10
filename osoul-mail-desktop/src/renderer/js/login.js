/**
 * Osoul Mail — بوابة الدخول.
 *
 * حقلان فقط: البريد وكلمة المرور. خوادم البريد معبّأة مسبقًا من السياسة،
 * ولا تظهر إلا لمن يفتح "إعدادات متقدمة" عمدًا. لا تسجيل ولا استعادة كلمة
 * مرور ذاتية: الحسابات يُنشئها مسؤول النظام، والتطبيق بوابة لا لوحة إدارة.
 */

import { icon } from './icons.js';
import { $, on, esc, call, errText } from './util.js';
import { t, getLang, setLang, otherLangName } from './i18n.js';

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
      <button type="button" class="lang-toggle" id="lg-lang"
              title="${esc(t('language'))}">${esc(otherLangName())}</button>
      <div class="mark">
        <img src="assets/logo.png" alt="">
        <h1>${esc(t('appName'))}</h1>
        <p>${esc(t('appTagline'))}</p>
      </div>

      <div class="msg err" id="lg-err" hidden></div>

      <div class="field">
        <label for="lg-email">${esc(t('email'))}</label>
        <div class="wrap">
          ${icon('mail', 'sm')}
          <input id="lg-email" name="email" type="email" inputmode="email" dir="ltr"
                 placeholder="${esc(hint)}" autocomplete="username"
                 value="${esc(saved ? saved.email : '')}" spellcheck="false" required>
        </div>
      </div>

      <div class="field">
        <label for="lg-pass">${esc(t('password'))}</label>
        <div class="wrap">
          ${icon('lock', 'sm')}
          <input id="lg-pass" name="password" type="password" dir="ltr"
                 placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="peek" id="lg-peek" tabindex="-1"
                  title="${esc(t('showPassword'))}" aria-label="${esc(t('showPassword'))}">${icon('eye', 'sm')}</button>
        </div>
      </div>

      <div class="row">
        <label class="check">
          <input type="checkbox" id="lg-remember" ${boot.canRemember ? 'checked' : 'disabled'}>
          <span>${esc(t('keepSignedIn'))}</span>
        </label>
      </div>

      <button type="submit" class="btn primary wide" id="lg-go">
        <span class="lbl">${esc(t('signIn'))}</span>
      </button>

      ${policy.allowAdvancedServers ? advancedHTML(policy) : ''}

      <div class="foot">
        ${domains.length ? esc(t('gateNoteDomain', { domains: domains.join(t('listSep')) })) : esc(t('gateNote'))}<br>
        ${esc(t('gateHelp'))}
      </div>
    </form>
  `;

  const form = $('form.gate', root);
  const email = $('#lg-email', root);
  const pass = $('#lg-pass', root);
  const go = $('#lg-go', root);
  const errBox = $('#lg-err', root);
  let busy = false;

  // تبديل اللغة قبل الدخول: الموظف الأجنبي يجب أن يفهم البوابة نفسها.
  on($('#lg-lang', root), 'click', async () => {
    const next = getLang() === 'ar' ? 'en' : 'ar';
    setLang(next);
    window.osoul.saveSettings({ lang: next });
    boot.settings.lang = next;
    renderLogin(boot, onSuccess);
  });

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
    if (!payload.email) { showError(t('enterEmail')); email.focus(); return; }
    if (!payload.password) { showError(t('enterPassword')); pass.focus(); return; }

    const adv = $('#lg-adv', root);
    if (adv && adv.open) {
      payload.imapHost = $('#lg-imap-host', root).value.trim();
      payload.imapPort = $('#lg-imap-port', root).value.trim();
      payload.smtpHost = $('#lg-smtp-host', root).value.trim();
      payload.smtpPort = $('#lg-smtp-port', root).value.trim();
    }

    busy = true;
    clearError();
    setBusy(go, true, t('checking'));

    try {
      const data = await call(window.osoul.login, payload);
      // نُبقي زر الدخول مشغولًا: الشاشة على وشك التبدّل.
      onSuccess(data);
    } catch (err) {
      showError(errText(err));
      setBusy(go, false, t('signIn'));
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
      <summary>${icon('settings', 'sm')}<span>${esc(t('advancedServers'))}</span></summary>
      <div class="field">
        <label>${esc(t('imapServer'))}</label>
        <div class="pair">
          <input id="lg-imap-host" type="text" dir="ltr" value="${esc(policy.imapHost || '')}" spellcheck="false">
          <input id="lg-imap-port" type="text" dir="ltr" value="${esc(String(policy.imapPort || 993))}" spellcheck="false">
        </div>
      </div>
      <div class="field">
        <label>${esc(t('smtpServer'))}</label>
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
