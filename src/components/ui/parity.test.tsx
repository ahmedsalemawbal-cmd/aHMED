// Parity with the reference implementation (design/components/bundle.js):
// the same props must produce the same markup. Deliberate accessibility
// additions are listed in ALLOWED and normalised before comparing.
import { readFileSync } from 'node:fs';
import { runInThisContext } from 'node:vm';
import * as React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { beforeAll, describe, expect, it } from 'vitest';
import * as Ours from './index';

type AnyProps = Record<string, unknown>;
type Ref = Record<string, (p: AnyProps) => React.ReactElement | null> & { STAGES: { id: string; label: string }[] };
let Maidani: Ref;

beforeAll(() => {
  const w = window as unknown as { React: typeof React; Maidani?: Ref };
  w.React = React;
  const code = readFileSync('design/components/bundle.js', 'utf8');
  // The reference bundle is the project's own design file (trusted).
  runInThisContext(code, { filename: 'design/components/bundle.js' });
  if (!w.Maidani) throw new Error('bundle.js did not define window.Maidani');
  Maidani = w.Maidani;
});

/** Deliberate differences: a11y attributes the reference lacks. */
const ALLOWED: [RegExp, string][] = [
  [/ aria-label="[^"]*"(?=[^>]*role="meter")/g, ''],
  [/(role="meter"[^>]*) aria-label="[^"]*"/g, '$1'],
  [/ role="button"(?=[^>]*md-lead-compact)/g, ''],
  [/(class="md-lead md-lead-compact[^"]*"[^>]*) role="button"/g, '$1'],
  [/ tabindex="-1"(?=[^>]*class="md-sheet")/g, ''],
  [/(class="md-sheet"[^>]*) tabindex="-1"/g, '$1'],
];

/** Attribute order is irrelevant: sort the attributes of every tag. */
function sortAttributes(html: string): string {
  return html.replace(/<([a-z][\w-]*)((?:\s+[^\s=>/]+(?:="[^"]*")?)*)\s*(\/?)>/g, (_m, tag: string, attrs: string, self: string) => {
    const list = attrs.match(/[^\s=>/]+(?:="[^"]*")?/g) ?? [];
    return `<${tag}${list.length ? ' ' + [...list].sort().join(' ') : ''}${self}>`;
  });
}
/**
 * Generated ids differ (useId vs a counter), so each distinct id/for/name/
 * aria-describedby value becomes a sequential token. The attributes must still
 * be present, and label→input and input→help links must still match.
 */
function tokenizeIds(html: string): string {
  const map = new Map<string, string>();
  return html.replace(/ (id|for|name|aria-describedby)="([^"]*)"/g, (_m, attr: string, value: string) => {
    if (!map.has(value)) map.set(value, `T${map.size.toString()}`);
    return ` ${attr}="${map.get(value) ?? ''}"`;
  });
}
function norm(html: string): string {
  return sortAttributes(tokenizeIds(ALLOWED.reduce((s, [re, rep]) => s.replace(re, rep), html)));
}

function both(name: string, props: AnyProps, children?: React.ReactNode): [string, string] {
  const ref = Maidani[name];
  const ours = (Ours as unknown as Record<string, React.ComponentType<AnyProps>>)[name];
  if (!ref || !ours) throw new Error(`missing component ${name}`);
  const a = renderToStaticMarkup(React.createElement(ref, props, children));
  const b = renderToStaticMarkup(React.createElement(ours, props, children));
  return [norm(a), norm(b)];
}

const noop = () => undefined;
const CASES: [string, AnyProps, React.ReactNode?][] = [
  ['Button', { variant: 'primary' }, 'احفظ'],
  ['Button', { variant: 'secondary', size: 'sm', icon: 'phone' }, 'اتصال'],
  ['Button', { variant: 'whatsapp', block: true }, 'إرسال عبر واتساب'],
  ['Button', { variant: 'ghost', loading: true }, 'جارٍ الحفظ'],
  ['Button', { variant: 'danger', disabled: true }, 'نقل إلى خسارة'],
  ['TextField', { label: 'اسم المحل', required: true, defaultValue: 'مطعم ريدان' }],
  ['TextField', { label: 'الجوال', ltr: true, inputMode: 'tel', hint: 'يُحوَّل تلقائياً إلى 9665… عند الحفظ', defaultValue: '0551234567' }],
  ['TextField', { label: 'الجوال', ltr: true, error: 'الرقم ناقص. اكتب 10 أرقام تبدأ بـ 05', defaultValue: '055' }],
  ['TextField', { label: 'القيمة المتوقعة', suffix: 'ر.س / شهرياً', defaultValue: '2500' }],
  ['TextField', { label: 'ملاحظات حرة', multiline: true, rows: 3, defaultValue: 'نص' }],
  ['TextField', { label: 'الجوال', hint: 'يُحوَّل تلقائياً إلى 9665… عند الحفظ', error: false, defaultValue: '05' }],
  ['TextField', { label: 'الجوال', hint: 'تلميح', error: '', defaultValue: '05' }],
  ['TextField', { label: 'ملاحظات', multiline: true, readOnly: true, inputMode: 'text', 'aria-label': 'z', 'data-k': '1', minLength: 2, placeholder: 'اكتب', maxLength: 90 }],
  ['TextField', { label: 'رابط', type: 'url', ltr: true, readOnly: true, 'data-k': '2' }],
  ['TextField', { label: 'بمعرّف', id: 'given-id', hint: 'ح' }],
  ['ScoreBar', { score: 30, label: '' }],
  ['ScoreBar', { score: 30, variant: 'ring', size: 0 }],
  ['LeadCard', { businessName: 'م', activity: 'م', stage: 'visited', score: 50, nextAction: '' }],
  ['StageBadge', { stage: 'won', children: '' }],
  ['Button', { variant: 'whatsapp', icon: '' }, 'واتساب'],
  ...(['not_visited', 'visited', 'contacted', 'replied', 'meeting', 'proposal', 'won', 'lost'] as const).map(
    (stage): [string, AnyProps] => ['StageBadge', { stage, size: stage === 'won' ? 'sm' : undefined }],
  ),
  ['StageBadge', { stage: 'visited' }, 'نص مخصص'],
  ['PriorityBadge', { priority: 'hot' }],
  ['PriorityBadge', { priority: 'warm', size: 'sm' }],
  ['PriorityBadge', { priority: 'cold' }],
  ...[0, 49, 50, 69, 70, 100].map((score): [string, AnyProps] => ['ScoreBar', { score, label: 'الدرجة · 9 من 13 بنود' }]),
  ['ScoreBar', { score: 44, variant: 'ring' }],
  ['ScoreBar', { score: 82, variant: 'ring', context: 'presence', size: 120 }],
  ['ScoreBar', { score: 55, context: 'presence' }],
  ['TriSelect', { label: 'التقييم 4.3 أو أعلى', weight: 10, value: 'yes', onChange: noop }],
  ['TriSelect', { label: 'يرد على المراجعات', weight: 5, value: null, onChange: noop }],
  ['TriSelect', { label: 'منيو QR', binary: true, value: 'no', onChange: noop }],
  ['TriSelect', { label: 'بند', weight: 10, value: 'partial', onChange: noop }],
  [
    'LeadCard',
    {
      businessName: 'مطعم ريدان', activity: 'مطعم', contactName: 'أ. خالد', stage: 'contacted', score: 48, priority: 'hot',
      lastContact: 'اليوم 11:20 ص', nextAction: 'متابعة أولى: عينة الريل', nextActionAt: 'السبت 10 أكتوبر', onWhatsApp: noop, onClick: noop,
    },
  ],
  [
    'LeadCard',
    { businessName: 'كافيه نسمة الحمدانية', activity: 'كافيه', stage: 'visited', score: 58, priority: 'warm', nextAction: 'أرسل الرسالة الأولى', nextActionAt: 'متأخرة منذ يومين', overdue: true },
  ],
  ['LeadCard', { businessName: 'مخبز الحي', activity: 'مخبز', stage: 'meeting', score: 63 }],
  ['LeadCard', { variant: 'compact', businessName: 'مطعم ريدان', activity: 'مطعم', value: '2,500 ر.س', daysInStage: 0 }],
  ['LeadCard', { variant: 'compact', businessName: 'حلويات السنبلة', activity: 'حلويات', value: '1,500 ر.س', daysInStage: 9, stale: true, onClick: noop }],
  ['BottomSheet', { open: false, title: 'هل أرسلت الرسالة؟' }],
  ['BottomSheet', { open: true, title: 'هل أرسلت الرسالة؟', onClose: noop, actions: React.createElement('button', null, 'نعم، أرسلتها') }, 'عند التأكيد…'],
  ['Toast', { tone: 'success', title: 'المرحلة الآن: تم الإرسال', message: 'أُنشئت متابعتان بعد 3 و7 أيام.', action: 'تراجع', onAction: noop }],
  ['Toast', { tone: 'error', title: 'تعذّر الحفظ', message: 'تأكد من الشبكة.', action: 'أعد المحاولة' }],
  ['Toast', { title: 'معلومة' }],
  ['EmptyState', { title: 'لا مهام اليوم', message: 'كل شيء منجز.', icon: 'check' }],
  ['EmptyState', { title: 'انزل للميدان', action: React.createElement('button', null, 'عميل جديد') }],
];

describe('parity with design/components/bundle.js', () => {
  it.each(CASES.map((c, i) => [`${String(i)} ${c[0]} ${JSON.stringify(c[1], (_k, v: unknown) => (typeof v === 'function' ? 'fn' : v)).slice(0, 70)}`, c] as const))(
    '%s',
    (_title, [name, props, children]) => {
      const [ref, ours] = both(name, props, children);
      expect(ours).toBe(ref);
    },
  );

  it('STAGES list is identical', () => {
    expect(Ours.STAGES).toEqual(Maidani.STAGES);
  });
});
