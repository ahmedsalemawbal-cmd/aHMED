/**
 * Osoul Mail — لوحة الإعدادات.
 *
 * ما يملكه الموظف فعلًا: المظهر، اسم المرسل، التوقيع، الإشعارات، الصور
 * الخارجية. لا إعدادات خادم هنا — تلك تُضبط مرة في بوابة الدخول.
 */

import { icon } from './icons.js';
import { $, on, delegate, esc, call, errText, toast } from './util.js';
import { ringBell, stopBell } from './sound.js';
import { t, getLang, setLang, langName } from './i18n.js';

/**
 * @param {object} state
 * @param {{onChange:Function, onLogout:Function}} handlers
 */
export function openSettings(state, handlers) {
  if ($('.sheet-back')) return;

  const s = state.settings || {};
  const back = document.createElement('div');
  back.className = 'sheet-back';
  back.innerHTML = `
    <div class="sheet" role="dialog" aria-label="${esc(t('settings'))}">
      <div class="head">
        ${icon('settings', 'sm')}
        <h3>${esc(t('settings'))}</h3>
        <button class="icon-btn" id="st-close" title="${esc(t('close'))}">${icon('x', 'sm')}</button>
      </div>
      <div class="body">

        <div class="group">
          <h4>${esc(t('account'))}</h4>
          <div class="opt">
            <div class="lab">
              <div>${esc(t('email'))}</div>
              <div class="d" dir="ltr" style="text-align:start">${esc(state.account.email)}</div>
            </div>
          </div>
          <div class="opt" style="display:block">
            <div class="lab" style="margin-bottom:8px">
              <div>${esc(t('displayName'))}</div>
              <div class="d">${esc(t('displayNameHint'))}</div>
            </div>
            <input type="text" id="st-fromname" value="${esc(s.fromName || state.account.fromName || '')}"
                   placeholder="${esc(t('fullName'))}">
          </div>
        </div>

        <div class="group">
          <h4>${esc(t('language'))}</h4>
          <div class="opt">
            <div class="lab">
              <div>${esc(t('language'))}</div>
            </div>
            <div class="seg" id="st-lang">
              <button data-lang="ar" class="${getLang() === 'ar' ? 'on' : ''}">${esc(langName('ar'))}</button>
              <button data-lang="en" class="${getLang() === 'en' ? 'on' : ''}">${esc(langName('en'))}</button>
            </div>
          </div>
        </div>

        <div class="group">
          <h4>${esc(t('appearance'))}</h4>
          <div class="opt">
            <div class="lab"><div>${esc(t('theme'))}</div></div>
            <div class="seg" id="st-theme">
              <button data-theme="dark" class="${s.theme !== 'light' ? 'on' : ''}">${esc(t('dark'))}</button>
              <button data-theme="light" class="${s.theme === 'light' ? 'on' : ''}">${esc(t('light'))}</button>
            </div>
          </div>
        </div>

        <div class="group">
          <h4>${esc(t('signature'))}</h4>
          <div class="opt" style="display:block">
            <div class="lab" style="margin-bottom:8px">
              <div class="d">${esc(t('signatureHint'))}</div>
            </div>
            <textarea id="st-signature" placeholder="${esc(t('fullName'))}">${esc(s.signature || '')}</textarea>
          </div>
        </div>

        <div class="group">
          <h4>${esc(t('mailSection'))}</h4>
          <div class="opt">
            <div class="lab">
              <div>${esc(t('notifTitle'))}</div>
              <div class="d">${esc(t('notifHint'))}</div>
            </div>
            <button class="switch ${s.notifications !== false ? 'on' : ''}" id="st-notif"><i></i></button>
          </div>
          <div class="opt">
            <div class="lab">
              <div>${esc(t('bellTitle'))}</div>
              <div class="d">${esc(t('bellHint'))}</div>
            </div>
            <button class="btn" id="st-sound-test" title="${esc(t('bellTest'))}">${icon('bell', 'sm')}</button>
            <button class="switch ${s.sound !== false ? 'on' : ''}" id="st-sound"><i></i></button>
          </div>
          <div class="opt">
            <div class="lab">
              <div>${esc(t('bellSecs'))}</div>
              <div class="d">${esc(t('bellSecsHint'))}</div>
            </div>
            <div class="seg" id="st-secs">
              ${[3, 5, 10].map((n) => `<button data-secs="${n}" class="${(s.soundSeconds || 5) === n ? 'on' : ''}">${esc(t('secs', { n }))}</button>`).join('')}
            </div>
          </div>
          <div class="opt">
            <div class="lab">
              <div>${esc(t('remoteImages'))}</div>
              <div class="d">${esc(t('remoteImagesHint'))}</div>
            </div>
            <button class="switch ${s.showRemoteImages ? 'on' : ''}" id="st-images"><i></i></button>
          </div>
        </div>

        <div class="group">
          <h4>${esc(t('ai'))}</h4>
          ${state.aiManaged ? `
          <div class="opt">
            <div class="lab">
              <div>${esc(t('aiManagedTitle'))}</div>
              <div class="d">${esc(t('aiManagedHint'))}</div>
            </div>
            <span class="pill ok">${esc(t('aiOn'))}</span>
          </div>` : `
          <div class="opt" style="display:block">
            <div class="lab" style="margin-bottom:8px">
              <div>${esc(t('aiKeyTitle'))}</div>
              <div class="d">${esc(t('aiKeyHint'))}</div>
            </div>
            <input type="text" id="st-aikey" dir="ltr" spellcheck="false"
                   placeholder="${esc(t('aiKeyPlaceholder'))}" value="${esc(state.aiKeyMask || '')}">
          </div>`}
        </div>

        <div class="group">
          <h4>${esc(t('passwordTitle'))}</h4>
          <div class="opt">
            <div class="lab">
              <div>${esc(t('pwOnServer'))}</div>
              <div class="d">${esc(t('pwOnServerHint'))}</div>
            </div>
            ${state.policy && state.policy.passwordChangeUrl
              ? `<button class="btn" id="st-pw-open">${icon('lock', 'sm')}<span>${esc(t('pwOpenProvider'))}</span></button>`
              : ''}
          </div>
          <div class="opt" style="display:block">
            <div class="lab" style="margin-bottom:8px">
              <div>${esc(t('pwUpdateHere'))}</div>
              <div class="d">${esc(t('pwUpdateHereHint'))}</div>
            </div>
            <div class="pw-form">
              <input type="password" id="st-pw-now" dir="ltr" autocomplete="current-password"
                     placeholder="${esc(t('pwCurrent'))}">
              <input type="password" id="st-pw-new" dir="ltr" autocomplete="new-password"
                     placeholder="${esc(t('pwNew'))}">
              <input type="password" id="st-pw-new2" dir="ltr" autocomplete="new-password"
                     placeholder="${esc(t('pwConfirm'))}">
              <button class="btn primary" id="st-pw-save">${icon('check', 'sm')}<span>${esc(t('pwSave'))}</span></button>
            </div>
          </div>
        </div>

        <div class="group">
          <h4>${esc(t('session'))}</h4>
          <div class="opt">
            <div class="lab">
              <div>${esc(t('signOut'))}</div>
              <div class="d">${esc(t('signOutHint'))}</div>
            </div>
            <button class="btn danger" id="st-logout">${icon('logout', 'sm')}<span>${esc(t('signOutBtn'))}</span></button>
          </div>
        </div>

        <div style="text-align:center;font-size:11px;color:var(--text3);padding-top:6px">
          ${esc(t('version', { v: state.version || '1.0.0' }))}
        </div>
      </div>
    </div>
  `;

  document.body.appendChild(back);

  function close() {
    back.remove();
    document.removeEventListener('keydown', keys);
  }
  function keys(e) { if (e.key === 'Escape') close(); }
  on(document, 'keydown', keys);
  on($('#st-close', back), 'click', close);
  on(back, 'mousedown', (e) => { if (e.target === back) close(); });

  /* السمة */
  delegate($('#st-theme', back), 'click', 'button[data-theme]', (_e, btn) => {
    const theme = btn.dataset.theme;
    $('#st-theme .on', back).classList.remove('on');
    btn.classList.add('on');
    handlers.onChange({ theme });
    save({ theme });
  });

  /* اللغة — تبديلها يعيد رسم الواجهة كلها فورًا */
  delegate($('#st-lang', back), 'click', 'button[data-lang]', async (_e, btn) => {
    const next = btn.dataset.lang;
    if (next === getLang()) return;
    setLang(next);
    await save({ lang: next });
    close();
    handlers.onChange({ lang: next });
  });

  /* مفتاح الذكاء الاصطناعي */
  on($('#st-aikey', back), 'change', async (e) => {
    const key = e.target.value.trim();
    if (key && key.includes('•')) return; // لم يُعدّله المستخدم
    try {
      const res = await call(window.osoul.setAiKey, { key });
      state.aiKeyMask = res.mask || '';
      state.aiReady = !!res.ready;
      toast(key ? t('aiKeySaved') : t('aiKeyCleared'), 'ok');
    } catch (err) {
      toast(errText(err), 'err');
    }
  });

  /* كلمة المرور */
  on($('#st-pw-open', back), 'click', () => {
    window.osoul.openExternal(state.policy.passwordChangeUrl);
  });

  on($('#st-pw-save', back), 'click', async () => {
    const now = $('#st-pw-now', back).value;
    const next = $('#st-pw-new', back).value;
    const again = $('#st-pw-new2', back).value;

    if (!now || !next) { toast(t('pwFillAll'), 'err'); return; }
    if (next !== again) { toast(t('pwMismatch'), 'err'); $('#st-pw-new2', back).focus(); return; }

    const btn = $('#st-pw-save', back);
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner sm"></span><span>${esc(t('pwChecking'))}</span>`;
    try {
      await call(window.osoul.changePassword, { current: now, next });
      $('#st-pw-now', back).value = '';
      $('#st-pw-new', back).value = '';
      $('#st-pw-new2', back).value = '';
      toast(t('pwUpdated'), 'ok');
    } catch (err) {
      toast(errText(err), 'err');
    } finally {
      btn.disabled = false;
      btn.innerHTML = `${icon('check', 'sm')}<span>${esc(t('pwSave'))}</span>`;
    }
  });

  /* المفاتيح */
  on($('#st-notif', back), 'click', (e) => {
    const el = e.currentTarget;
    el.classList.toggle('on');
    save({ notifications: el.classList.contains('on') });
  });
  on($('#st-sound', back), 'click', (e) => {
    const el = e.currentTarget;
    el.classList.toggle('on');
    const enabled = el.classList.contains('on');
    save({ sound: enabled });
    if (enabled) ringBell(1.2); else stopBell();
  });
  on($('#st-sound-test', back), 'click', () => ringBell(state.settings.soundSeconds || 5));
  delegate($('#st-secs', back), 'click', 'button[data-secs]', (_e, btn) => {
    const secs = Number(btn.dataset.secs);
    $('#st-secs .on', back).classList.remove('on');
    btn.classList.add('on');
    save({ soundSeconds: secs });
    ringBell(secs);
  });

  on($('#st-images', back), 'click', (e) => {
    const el = e.currentTarget;
    el.classList.toggle('on');
    save({ showRemoteImages: el.classList.contains('on') });
  });

  /* الحقول النصية — تُحفظ عند الخروج من الحقل */
  on($('#st-fromname', back), 'change', (e) => save({ fromName: e.target.value.trim() }));
  on($('#st-signature', back), 'change', (e) => save({ signature: e.target.value }));

  on($('#st-logout', back), 'click', () => {
    if (window.confirm(t('confirmSignOut'))) {
      close();
      handlers.onLogout();
    }
  });

  async function save(patch) {
    try {
      const next = await call(window.osoul.saveSettings, patch);
      Object.assign(state.settings, next);
    } catch (err) {
      toast(errText(err), 'err');
    }
  }
}
