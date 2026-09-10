/**
 * Osoul Mail — أدوات مشتركة للواجهة.
 */

import { icon } from './icons.js';
import { t, pick, locale } from './i18n.js';

/* ------------------------------------------------------------- الاختصارات */

export const $ = (sel, root) => (root || document).querySelector(sel);
export const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

export function on(node, ev, fn, opt) {
  if (node) node.addEventListener(ev, fn, opt);
  return node;
}

/** تفويض الأحداث: مستمع واحد لكل الصفوف بدل مستمع لكل صف. */
export function delegate(root, ev, sel, fn) {
  on(root, ev, (e) => {
    const hit = e.target.closest(sel);
    if (hit && root.contains(hit)) fn(e, hit);
  });
}

export function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

/* ----------------------------------------------------------------- التنسيق */

export function fmtBytes(n) {
  const v = Number(n) || 0;
  if (v < 1024) return `${v} ${t('unitB')}`;
  if (v < 1024 * 1024) return `${(v / 1024).toFixed(0)} ${t('unitKB')}`;
  if (v < 1024 * 1024 * 1024) return `${(v / 1048576).toFixed(1)} ${t('unitMB')}`;
  return `${(v / 1073741824).toFixed(2)} ${t('unitGB')}`;
}

/* المنسّقات تُبنى مرة لكل لغة: إنشاء Intl.DateTimeFormat لكل صف في القائمة
   مكلف، وتبديل اللغة نادر. */
const FMT_CACHE = new Map();

function fmt(kind) {
  const key = `${locale()}:${kind}`;
  let f = FMT_CACHE.get(key);
  if (f) return f;
  const opts = {
    time: { hour: '2-digit', minute: '2-digit', hour12: true },
    day: { day: 'numeric', month: 'short' },
    full: { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: true },
  }[kind];
  f = new Intl.DateTimeFormat(locale(), opts);
  FMT_CACHE.set(key, f);
  return f;
}

/** وقت مختصر لقائمة الرسائل: اليوم = الساعة، هذه السنة = يوم/شهر، غير ذلك = السنة. */
export function fmtWhen(ts) {
  if (!ts) return '';
  const d = new Date(ts);
  const now = new Date();
  const sameDay = d.toDateString() === now.toDateString();
  if (sameDay) return fmt('time').format(d);
  const yesterday = new Date(now.getTime() - 86400000);
  if (d.toDateString() === yesterday.toDateString()) return t('yesterday');
  if (d.getFullYear() === now.getFullYear()) return fmt('day').format(d);
  return `${fmt('day').format(d)} ${d.getFullYear()}`;
}

export function fmtFull(ts) {
  return ts ? fmt('full').format(new Date(ts)) : '';
}

/** عنوان مجموعة زمنية في القائمة. */
export function groupOf(ts) {
  if (!ts) return 'older';
  const d = new Date(ts);
  const now = new Date();
  if (d.toDateString() === now.toDateString()) return 'today';
  const yesterday = new Date(now.getTime() - 86400000);
  if (d.toDateString() === yesterday.toDateString()) return 'yesterday';
  const week = new Date(now.getTime() - 7 * 86400000);
  if (d > week) return 'thisWeek';
  if (d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth()) return 'thisMonth';
  return 'older';
}

/* ------------------------------------------------------------- الصورة الرمزية */

const AV_COLORS = ['#0a5c86', '#1683bd', '#2f6f8f', '#4a7c59', '#8a5a3c', '#6b4c7a', '#a04d4d', '#3d6b7d'];

export function avColor(seed) {
  const s = String(seed || '?');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % AV_COLORS.length;
  return AV_COLORS[h];
}

export function initials(name, email) {
  const src = String(name || '').trim() || String(email || '').split('@')[0] || '?';
  const parts = src.split(/[\s._-]+/).filter(Boolean);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return src.slice(0, 2).toUpperCase();
}

export function avatarHTML(name, email, cls) {
  const bg = avColor(email || name);
  return `<div class="avatar av ${cls || ''}" style="background:${bg}">${esc(initials(name, email))}</div>`;
}

/** عرض المرسل: الاسم إن وُجد وإلا البريد. */
export function who(addr) {
  if (!addr) return '';
  return addr.name || addr.email || '';
}

/* -------------------------------------------------------------- الإشعارات */

let toastTimer = 0;

export function toast(message, type) {
  const box = $('#toasts');
  if (!box) return;
  const el = document.createElement('div');
  el.className = `toast ${type || ''}`;
  const ico = type === 'err' ? 'alert' : type === 'ok' ? 'check' : 'info';
  el.innerHTML = `${icon(ico, 'sm')}<span>${esc(message)}</span>`;
  box.appendChild(el);
  clearTimeout(toastTimer);
  setTimeout(() => el.remove(), type === 'err' ? 6000 : 3200);
}

/* ------------------------------------------------------------ استدعاء IPC */

/**
 * نداء العملية الرئيسية مع معالجة موحّدة للخطأ.
 * يرمي استثناءً يحمل الرسالة العربية الجاهزة للعرض.
 */
export async function call(fn, ...args) {
  const res = await fn(...args);
  if (res && res.ok) return res.data;
  const err = new Error((res && res.error && res.error.code) || 'UNKNOWN');
  err.info = (res && res.error) || null;
  throw err;
}

export function errText(err) {
  return pick(err && err.info);
}

/* ------------------------------------------------------------- قائمة منسدلة */

let openMenu = null;

/** فتح قائمة عند عنصر. items = [{label, icon, danger, onClick}] أو {sep:true} أو {cap:'…'} */
export function menu(anchor, items) {
  closeMenu();
  const el = document.createElement('div');
  el.className = 'menu';
  el.innerHTML = items.map((it, i) => {
    if (it.sep) return '<div class="sep"></div>';
    if (it.cap) return `<div class="cap">${esc(it.cap)}</div>`;
    return `<button data-i="${i}" class="${it.danger ? 'danger' : ''}">${it.icon ? icon(it.icon, 'sm') : ''}<span>${esc(it.label)}</span></button>`;
  }).join('');

  document.body.appendChild(el);

  const r = anchor.getBoundingClientRect();
  const w = el.offsetWidth;
  const h = el.offsetHeight;
  let left = document.documentElement.dir === 'rtl' ? r.right - w : r.left;
  left = Math.max(8, Math.min(left, window.innerWidth - w - 8));
  const top = r.bottom + h + 8 > window.innerHeight ? Math.max(8, r.top - h - 6) : r.bottom + 6;
  el.style.left = `${left}px`;
  el.style.top = `${top}px`;

  delegate(el, 'click', 'button[data-i]', (_e, btn) => {
    const item = items[Number(btn.dataset.i)];
    closeMenu();
    if (item && item.onClick) item.onClick();
  });

  openMenu = el;
  setTimeout(() => {
    on(document, 'mousedown', outside, { once: true });
    on(document, 'keydown', escKey, { once: true });
  }, 0);
  return el;
}

function outside(e) {
  if (openMenu && !openMenu.contains(e.target)) closeMenu();
  else if (openMenu) on(document, 'mousedown', outside, { once: true });
}

function escKey(e) {
  if (e.key === 'Escape') closeMenu();
  else if (openMenu) on(document, 'keydown', escKey, { once: true });
}

export function closeMenu() {
  if (openMenu) {
    openMenu.remove();
    openMenu = null;
  }
}

/* --------------------------------------------------------- شارة شريط المهام */

/** رسم شارة عدد غير المقروء وإرسالها كصورة إلى شريط المهام. */
export function paintBadge(count) {
  const n = Number(count) || 0;
  if (!n) {
    window.osoul.setBadge({ dataUrl: '', count: 0 });
    return;
  }
  const size = 32;
  const c = document.createElement('canvas');
  c.width = size;
  c.height = size;
  const g = c.getContext('2d');
  g.fillStyle = '#d0483f';
  g.beginPath();
  g.arc(size / 2, size / 2, size / 2 - 1, 0, Math.PI * 2);
  g.fill();
  g.fillStyle = '#fff';
  const label = n > 99 ? '99+' : String(n);
  g.font = `bold ${label.length > 2 ? 13 : 17}px "Segoe UI", sans-serif`;
  g.textAlign = 'center';
  g.textBaseline = 'middle';
  g.fillText(label, size / 2, size / 2 + 1);
  window.osoul.setBadge({ dataUrl: c.toDataURL('image/png'), count: n });
}

/* ------------------------------------------------------------- نافذة سؤال */

/**
 * بديل prompt() الذي لا تدعمه Electron.
 * @returns {Promise<string|null>} النص، أو null عند الإلغاء
 */
export function ask(title, opts) {
  const o = opts || {};
  return new Promise((resolve) => {
    const back = document.createElement('div');
    back.className = 'sheet-back';
    back.innerHTML = `
      <div class="sheet ask" role="dialog">
        <div class="body">
          <p>${esc(title)}</p>
          <input type="text" id="ask-in" dir="${o.dir || 'auto'}" spellcheck="false"
                 value="${esc(o.value || '')}" placeholder="${esc(o.placeholder || '')}">
          <div class="row">
            <button class="btn primary" id="ask-ok">${esc(o.okLabel || t('ok'))}</button>
            <button class="btn" id="ask-no">${esc(t('cancel'))}</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(back);

    const input = $('#ask-in', back);
    const done = (value) => {
      back.remove();
      document.removeEventListener('keydown', keys);
      resolve(value);
    };
    const keys = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); done(null); }
      if (e.key === 'Enter') { e.preventDefault(); done(input.value.trim() || null); }
    };
    on(document, 'keydown', keys);
    on($('#ask-ok', back), 'click', () => done(input.value.trim() || null));
    on($('#ask-no', back), 'click', () => done(null));
    on(back, 'mousedown', (e) => { if (e.target === back) done(null); });

    setTimeout(() => { input.focus(); input.select(); }, 50);
  });
}
