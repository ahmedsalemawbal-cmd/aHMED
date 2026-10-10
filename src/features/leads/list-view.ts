import { STAGE_LABEL, STAGES, type Stage } from '@/components/ui/stages';
import { agoText, overdueText, whenText } from '@/lib/dates';
import { DEFAULT_DIR, leadsCountText, SORT_LABEL, type LeadRow, type ListQuery, type SortKey } from '@/lib/leads-list';
import { taskAction } from '@/data/tasks-meta';

/** Every sort's name: the three sorts of brief.md §5 plus the desktop column sorts. */
export const SORT_NAME: Record<SortKey, string> = {
  ...SORT_LABEL,
  name: 'المحل',
  stage: 'المرحلة',
  priority: 'الأولوية',
  last: 'آخر تواصل',
  value: 'القيمة المتوقعة',
};

export const MOBILE_SORTS: SortKey[] = ['next', 'recent', 'score'];

/** The phone's sorts, plus the current one when a desktop column sort came in the link. */
export function sortChoices(current: SortKey): SortKey[] {
  return MOBILE_SORTS.includes(current) ? MOBILE_SORTS : [...MOBILE_SORTS, current];
}

/** «الكل» first, then each stage that has leads, plus the selected one even when empty. */
export function stageChipIds(counts: Record<Stage | 'all', number>, selected: Stage | 'all'): (Stage | 'all')[] {
  return ['all', ...STAGES.map((s) => s.id).filter((id) => counts[id] > 0 || id === selected)];
}

export function chipLabel(id: Stage | 'all'): string {
  return id === 'all' ? 'الكل' : STAGE_LABEL[id];
}

/** The activities that have leads, by name. */
export function activityOptions(rows: Pick<LeadRow, 'activityId' | 'activity'>[]): { id: string; name: string }[] {
  const seen = new Map<string, string>();
  for (const r of rows) if (r.activityId && !seen.has(r.activityId)) seen.set(r.activityId, r.activity || 'بدون اسم');
  const collator = new Intl.Collator('ar');
  return [...seen].map(([id, name]) => ({ id, name })).sort((a, b) => collator.compare(a.name, b.name));
}

export interface NextWhen {
  text: string;
  overdue: boolean;
}

/** «متأخرة منذ يومين» once the moment has passed, else «اليوم 4:00 م» / «السبت 10 أكتوبر». */
export function nextWhen(row: Pick<LeadRow, 'next'>, now: Date): NextWhen | null {
  if (!row.next) return null;
  const overdue = row.next.dueAt.getTime() < now.getTime();
  return { text: overdue ? overdueText(row.next.dueAt, now) : whenText(row.next.dueAt, now), overdue };
}

export function lastContactText(row: Pick<LeadRow, 'lastContactAt'>, now: Date): string | null {
  return row.lastContactAt ? agoText(row.lastContactAt, now) : null;
}

/** WhatsApp only with a number and without «لا تتواصل». */
export function canWhatsApp(row: Pick<LeadRow, 'phone' | 'doNotContact'>): boolean {
  return Boolean(row.phone) && !row.doNotContact;
}

/** The message screen, prepared for the lead's next task when that task is a message. */
export function messageHref(row: Pick<LeadRow, 'id' | 'next'>): string {
  const base = `/leads/${row.id}/message`;
  return row.next && taskAction(row.next.kind) === 'whatsapp' ? `${base}?task=${encodeURIComponent(row.next.id)}` : base;
}

/** Score bar colour (DeskLeads.dc.html): <50 hot, <70 warm, else cold. */
export function scoreBarClass(score: number): 'bg-priority-hot' | 'bg-priority-warm' | 'bg-priority-cold' {
  return score < 50 ? 'bg-priority-hot' : score < 70 ? 'bg-priority-warm' : 'bg-priority-cold';
}

/** «عميل واحد محدد»، «عميلان محددان»، «3 عملاء محددون»، «11 عميلاً محدداً»، «100 عميل محدد». */
export function selectionText(n: number): string {
  if (n === 1) return 'عميل واحد محدد';
  if (n === 2) return 'عميلان محددان';
  const r = n % 100;
  if (r >= 3 && r <= 10) return `${n.toString()} عملاء محددون`;
  if (r >= 11) return `${n.toString()} عميلاً محدداً`;
  return `${n.toString()} عميل محدد`;
}

type Named = Pick<LeadRow, 'businessName'>;

/** Object of «نقل … إلى خسارة»: «مطعم ريدان»، «عميلين»، «3 عملاء»، «11 عميلاً». */
export function leadsObjectText(rows: Named[]): string {
  const n = rows.length;
  if (n === 1) return rows[0]?.businessName ?? 'عميل واحد';
  if (n === 2) return 'عميلين';
  return leadsCountText(n);
}

/** Subject of a toast: the shop for one lead, else «عميلان»، «3 عملاء»، «11 عميلاً». */
export function leadsSubjectText(rows: Named[]): string {
  const only = rows.length === 1 ? rows[0] : undefined;
  return only ? only.businessName : leadsCountText(rows.length);
}

export function lostSheetTitle(rows: Named[]): string {
  return `نقل ${leadsObjectText(rows)} إلى خسارة`;
}

/** «المرحلة الآن: عرض سعر · 3 عملاء»، «المرحلة الآن: تمت الزيارة · مطعم ريدان» */
export function stageToastText(stage: Stage, rows: Named[]): string {
  return `المرحلة الآن: ${STAGE_LABEL[stage]} · ${leadsSubjectText(rows)}`;
}

/** «نُقل 3 عملاء إلى خسارة»، «نُقل مطعم ريدان إلى خسارة» */
export function lostToastText(rows: Named[]): string {
  return `نُقل ${leadsSubjectText(rows)} إلى خسارة`;
}

/** «صُدّر 3 عملاء إلى ملف CSV» */
export function exportToastText(rows: Named[]): string {
  return `صُدّر ${leadsSubjectText(rows)} إلى ملف CSV`;
}

/** A one-lead toast from the lead page, named in the list: «حُدد الاجتماع · مطعم ريدان». */
export function aboutLead<T extends { title: string }>(toast: T, row: Named): T {
  return { ...toast, title: `${toast.title} · ${row.businessName}` };
}

/** A column header click: the same column flips direction, another starts at its natural one. */
export function nextSort(q: Pick<ListQuery, 'sort' | 'dir'>, key: SortKey): Pick<ListQuery, 'sort' | 'dir'> {
  if (q.sort === key) return { sort: key, dir: q.dir === 'asc' ? 'desc' : 'asc' };
  return { sort: key, dir: DEFAULT_DIR[key] };
}

export function ariaSort(q: Pick<ListQuery, 'sort' | 'dir'>, key: SortKey): 'ascending' | 'descending' | 'none' {
  if (q.sort !== key) return 'none';
  return q.dir === 'asc' ? 'ascending' : 'descending';
}

/** What the list shows, without the page: a change means its first results come into view. */
export function listKey(q: ListQuery): string {
  return JSON.stringify([q.q.trim(), q.stage, q.activity, q.priority, q.source, q.sort, q.dir]);
}

/** Search, activity, priority or source narrow the list (the stage has its own chips). */
export function hasFilters(q: ListQuery): boolean {
  return q.q.trim() !== '' || q.stage !== 'all' || q.activity !== 'all' || q.priority !== 'all' || q.source !== 'all';
}

export const SOURCE_LABEL: Record<'visit' | 'import', string> = { visit: 'زيارة', import: 'استيراد' };
