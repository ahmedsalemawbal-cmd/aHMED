import { ICONS } from './icons.mjs';

// Escapes text for use inside HTML content and attribute values.
export const esc = value =>
  String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

// Inline Phosphor icon. `name` is the regular weight; prefix it with "duo:" for the duotone weight.
// Icons are 1em squares that follow the surrounding font-size and colour, like the icon font did.
export function icon(name, cls = '') {
  const duo = name.startsWith('duo:');
  const key = duo ? name.slice(4) : name;
  const paths = (duo ? ICONS.duotone : ICONS.regular)[key];
  if (!paths) throw new Error(`Unknown icon: ${name}`);
  return `<svg class="i${cls ? ' ' + cls : ''}" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true" focusable="false">${paths}</svg>`;
}

// Direction-aware arrow/caret names: "forward" points left in Arabic and right in English.
export const dirIcons = L => ({
  fwd: L === 'ar' ? 'arrow-left' : 'arrow-right',
  back: L === 'ar' ? 'arrow-right' : 'arrow-left',
  fwdUp: L === 'ar' ? 'arrow-up-left' : 'arrow-up-right',
  caret: L === 'ar' ? 'caret-left' : 'caret-right'
});

// Diamond + hairline ornament used above section titles.
export const ornament = (kind = 'center') =>
  kind === 'center'
    ? '<span class="orn" aria-hidden="true"><span class="orn-line"></span><span class="dia"></span><span class="orn-line"></span></span>'
    : '<span class="orn" aria-hidden="true"><span class="dia"></span><span class="orn-line"></span></span>';

// Short hash used to bust caches when CSS/JS change.
export function hash(text) {
  let h = 2166136261;
  for (let i = 0; i < text.length; i++) h = Math.imul(h ^ text.charCodeAt(i), 16777619);
  return (h >>> 0).toString(36);
}
