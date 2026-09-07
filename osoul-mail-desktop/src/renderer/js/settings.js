/**
 * Osoul Mail — لوحة الإعدادات.
 *
 * ما يملكه الموظف فعلًا: المظهر، اسم المرسل، التوقيع، الإشعارات، الصور
 * الخارجية. لا إعدادات خادم هنا — تلك تُضبط مرة في بوابة الدخول.
 */

import { icon } from './icons.js';
import { $, on, delegate, esc, call, errText, toast } from './util.js';

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
    <div class="sheet" role="dialog" aria-label="الإعدادات">
      <div class="head">
        ${icon('settings', 'sm')}
        <h3>الإعدادات</h3>
        <button class="icon-btn" id="st-close" title="إغلاق">${icon('x', 'sm')}</button>
      </div>
      <div class="body">

        <div class="group">
          <h4>الحساب</h4>
          <div class="opt">
            <div class="lab">
              <div>البريد الإلكتروني</div>
              <div class="d" dir="ltr" style="text-align:start">${esc(state.account.email)}</div>
            </div>
          </div>
          <div class="opt" style="display:block">
            <div class="lab" style="margin-bottom:8px">
              <div>الاسم الظاهر للمستلمين</div>
              <div class="d">يظهر بدل عنوان بريدك في صندوق وارد الطرف الآخر.</div>
            </div>
            <input type="text" id="st-fromname" value="${esc(s.fromName || state.account.fromName || '')}"
                   placeholder="الاسم الكامل">
          </div>
        </div>

        <div class="group">
          <h4>المظهر</h4>
          <div class="opt">
            <div class="lab"><div>السمة</div></div>
            <div class="seg" id="st-theme">
              <button data-theme="dark" class="${s.theme !== 'light' ? 'on' : ''}">داكن</button>
              <button data-theme="light" class="${s.theme === 'light' ? 'on' : ''}">فاتح</button>
            </div>
          </div>
        </div>

        <div class="group">
          <h4>التوقيع</h4>
          <div class="opt" style="display:block">
            <div class="lab" style="margin-bottom:8px">
              <div class="d">يُضاف تلقائيًا أسفل كل رسالة جديدة.</div>
            </div>
            <textarea id="st-signature" placeholder="الاسم&#10;المسمى الوظيفي&#10;شركة أصول البناء الصناعية">${esc(s.signature || '')}</textarea>
          </div>
        </div>

        <div class="group">
          <h4>البريد</h4>
          <div class="opt">
            <div class="lab">
              <div>إشعارات الرسائل الجديدة</div>
              <div class="d">إشعار ويندوز عند وصول رسالة.</div>
            </div>
            <button class="switch ${s.notifications !== false ? 'on' : ''}" id="st-notif"><i></i></button>
          </div>
          <div class="opt">
            <div class="lab">
              <div>تحميل الصور الخارجية تلقائيًا</div>
              <div class="d">إبقاؤه مغلقًا يمنع المُرسِل من معرفة أنك فتحت رسالته.</div>
            </div>
            <button class="switch ${s.showRemoteImages ? 'on' : ''}" id="st-images"><i></i></button>
          </div>
        </div>

        <div class="group">
          <h4>الجلسة</h4>
          <div class="opt">
            <div class="lab">
              <div>تسجيل الخروج</div>
              <div class="d">يمسح بيانات الدخول المحفوظة على هذا الجهاز.</div>
            </div>
            <button class="btn danger" id="st-logout">${icon('logout', 'sm')}<span>خروج</span></button>
          </div>
        </div>

        <div style="text-align:center;font-size:11px;color:var(--text3);padding-top:6px">
          بريد أصول البناء — الإصدار ${esc(state.version || '1.0.0')}
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

  /* المفاتيح */
  on($('#st-notif', back), 'click', (e) => {
    const el = e.currentTarget;
    el.classList.toggle('on');
    save({ notifications: el.classList.contains('on') });
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
    if (window.confirm('تسجيل الخروج ومسح بيانات الدخول من هذا الجهاز؟')) {
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
