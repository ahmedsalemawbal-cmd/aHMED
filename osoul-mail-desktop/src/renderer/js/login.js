/**
 * Osoul Mail — بوابة الدخول.
 *
 * حقلان فقط: البريد وكلمة المرور. خوادم البريد معبّأة مسبقًا من السياسة،
 * ولا تظهر إلا لمن يفتح "إعدادات متقدمة" عمدًا. لا تسجيل ولا استعادة كلمة
 * مرور ذاتية: الحسابات يُنشئها مسؤول النظام، والتطبيق بوابة لا لوحة إدارة.
 */

import { icon } from './icons.js';
import { $, on, esc, call, errText } from './util.js';
import { t, getLang, setLang, otherLangName, pick } from './i18n.js';

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

      <button type="button" class="link-btn" id="lg-check">${icon('shield', 'sm')}<span>${esc(t('runCheck'))}</span></button>
      <div class="diag" id="lg-diag" hidden></div>

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

  function showError(text, detail) {
    errBox.innerHTML = `${icon('alert', 'sm')}<span>${esc(text)}</span>`
      + (detail ? `<details class="tech"><summary>${esc(t('techDetails'))}</summary><code dir="ltr">${esc(detail)}</code></details>` : '');
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
      showError(errText(err), err && err.info && err.info.detail ? String(err.info.detail).slice(0, 300) : '');
      setBusy(go, false, t('signIn'));
      busy = false;
      pass.select();
    }
  });


  /* فحص الاتصال — يعمل ولو لم يُكتب سوى البريد، فيفصل عطل الشبكة عن كلمة
   * المرور. النتيجة صورة واحدة تكفي مسؤول النظام. */
  const diagBox = $('#lg-diag', root);
  const checkBtn = $('#lg-check', root);
  let checking = false;

  on(checkBtn, 'click', async () => {
    if (checking) return;
    const addr = email.value.trim();
    if (!addr) { showError(t('enterEmail')); email.focus(); return; }

    checking = true;
    clearError();
    checkBtn.disabled = true;
    checkBtn.innerHTML = `<span class="spinner sm"></span><span>${esc(t('checkRunning'))}</span>`;
    diagBox.hidden = false;
    diagBox.innerHTML = `<div class="loading"><div class="spinner"></div></div>`;

    try {
      const res = await call(window.osoul.diagnose, {
        email: addr,
        password: pass.value,
        imapHost: advValue('#lg-imap-host'),
        imapPort: advValue('#lg-imap-port'),
        smtpHost: advValue('#lg-smtp-host'),
        smtpPort: advValue('#lg-smtp-port'),
      });
      diagBox.innerHTML = diagHTML(res);
    } catch (err) {
      diagBox.innerHTML = `<div class="verdict bad">${esc(errText(err))}</div>`;
    } finally {
      checking = false;
      checkBtn.disabled = false;
      checkBtn.innerHTML = `${icon('shield', 'sm')}<span>${esc(t('runCheck'))}</span>`;
    }
  });

  function advValue(sel) {
    const adv = $('#lg-adv', root);
    const el = adv && adv.open ? $(sel, root) : null;
    return el ? el.value.trim() : '';
  }


  root.hidden = false;
  setTimeout(() => (saved && saved.email ? pass : email).focus(), 60);
}


/** رمز الحكم ← مفتاح النص. صريح حتى يمسك فحص الترجمة أي نقص. */
const VERDICT_KEY = {
  OK: 'verdictOk',
  NETWORK_OK: 'verdictNetworkOk',
  BAD_EMAIL: 'verdictBadEmail',
  DOMAIN: 'verdictDomain',
  DNS: 'verdictDns',
  BLOCKED: 'verdictBlocked',
  TLS: 'verdictTls',
  IMAP_AUTH: 'verdictImapAuth',
  IMAP_FAIL: 'verdictImapFail',
  SMTP_ONLY: 'verdictSmtpOnly',
  ALT_HOST: 'verdictAltHost',
};

/** نتيجة الفحص: سطر لكل مرحلة، ثم حكم واحد يقول ماذا يفعل الموظف. */
function diagHTML(res) {
  const rows = (res.steps || []).map((st) => {
    const state = st.skipped ? 'skip' : st.ok ? 'ok' : 'bad';
    const mark = st.skipped ? '—' : st.ok ? '✓' : '✕';
    return `
      <div class="dstep ${state}">
        <span class="m">${mark}</span>
        <span class="n">${esc(pick(st))}</span>
        ${st.detail ? `<code dir="ltr">${esc(String(st.detail).slice(0, 160))}</code>` : ''}
      </div>`;
  }).join('');

  const good = res.verdict === 'OK' || res.verdict === 'NETWORK_OK';
  const key = VERDICT_KEY[res.verdict] || 'verdictImapFail';
  return `${rows}<div class="verdict ${good ? 'good' : 'bad'}">${esc(t(key))}</div>`;
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
