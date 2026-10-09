import { STAGES, type Priority, type Stage } from '@/components/ui/stages';
import type { TaskKind } from '@/data/tasks-meta';
import { normalizeName } from './duplicates';
import { toLocal } from './phone';

export type Role = 'owner' | 'manager' | 'employee';
export type Source = 'visit' | 'import';

/** One row of «العملاء», shared by the mobile cards and the desktop table. */
export interface LeadRow {
  id: string;
  businessName: string;
  activityId: string | null;
  activity: string;
  contactName: string | null;
  contactRole: Role | null;
  phone: string | null;
  stage: Stage;
  score: number | null;
  priority: Priority | null;
  source: Source;
  expectedValue: number | null;
  createdAt: Date;
  lastContactAt: Date | null;
  stageChangedAt: Date;
  doNotContact: boolean;
  waConsent: boolean;
  /** earliest open task */
  next: { id: string; title: string; kind: TaskKind; dueAt: Date } | null;
}

export type SortKey = 'next' | 'recent' | 'score' | 'name' | 'stage' | 'priority' | 'last' | 'value';
export type SortDir = 'asc' | 'desc';

export interface ListQuery {
  q: string;
  stage: Stage | 'all';
  activity: string;
  priority: Priority | 'all';
  source: Source | 'all';
  sort: SortKey;
  dir: SortDir;
  page: number;
}

export const DEFAULT_QUERY: ListQuery = { q: '', stage: 'all', activity: 'all', priority: 'all', source: 'all', sort: 'next', dir: 'asc', page: 1 };

/** The natural direction of each sort: soonest action, newest lead, lowest score (biggest opportunity). */
export const DEFAULT_DIR: Record<SortKey, SortDir> = { next: 'asc', recent: 'desc', score: 'asc', name: 'asc', stage: 'asc', priority: 'asc', last: 'desc', value: 'desc' };

export const SORT_LABEL: Record<'next' | 'recent' | 'score', string> = { next: 'الإجراء القادم', recent: 'الأحدث', score: 'الدرجة' };

const ARABIC_DIGITS = /[٠-٩۰-۹]/g;

/** ٠٥٥ → 055 (the keyboard may type Arabic-Indic digits). */
export function latinDigits(s: string): string {
  return s.replace(ARABIC_DIGITS, (d) => String((d.charCodeAt(0) - (d >= '۰' ? 0x06f0 : 0x0660)) % 10));
}

/**
 * Search by shop or contact name (spelling-tolerant), or by phone: three or
 * more digits match the number as typed (05…) or stored (9665…).
 */
export function matchesSearch(row: Pick<LeadRow, 'businessName' | 'contactName' | 'phone'>, query: string): boolean {
  const q = latinDigits(query).trim();
  if (!q) return true;
  const digits = q.replace(/\D/g, '');
  if (digits.length >= 3 && digits.length === q.replace(/[\s+()-]/g, '').length) {
    if (!row.phone) return false;
    return row.phone.includes(digits) || toLocal(row.phone).includes(digits);
  }
  const n = normalizeName(q);
  if (!n) return true;
  return normalizeName(row.businessName).includes(n) || (row.contactName ? normalizeName(row.contactName).includes(n) : false);
}

/** Everything except the stage, so the stage chips can count within the other filters. */
export function filterExceptStage(rows: LeadRow[], q: ListQuery): LeadRow[] {
  return rows.filter(
    (r) =>
      matchesSearch(r, q.q) &&
      (q.activity === 'all' || r.activityId === q.activity) &&
      (q.priority === 'all' || r.priority === q.priority) &&
      (q.source === 'all' || r.source === q.source),
  );
}

export function filterLeads(rows: LeadRow[], q: ListQuery): LeadRow[] {
  return filterExceptStage(rows, q).filter((r) => q.stage === 'all' || r.stage === q.stage);
}

export function stageCounts(rows: LeadRow[]): Record<Stage | 'all', number> {
  const counts = Object.fromEntries([['all', rows.length], ...STAGES.map((s) => [s.id, 0])]) as Record<Stage | 'all', number>;
  for (const r of rows) counts[r.stage]++;
  return counts;
}

const STAGE_ORDER = Object.fromEntries(STAGES.map((s, i) => [s.id, i])) as Record<Stage, number>;
const PRIORITY_ORDER: Record<Priority, number> = { hot: 0, warm: 1, cold: 2 };
const collator = new Intl.Collator('ar');

function keyOf(r: LeadRow, k: SortKey): number | string | null {
  switch (k) {
    case 'next':
      return r.next ? r.next.dueAt.getTime() : null;
    case 'recent':
      return r.createdAt.getTime();
    case 'score':
      return r.score;
    case 'name':
      return r.businessName;
    case 'stage':
      return STAGE_ORDER[r.stage];
    case 'priority':
      return r.priority ? PRIORITY_ORDER[r.priority] : null;
    case 'last':
      return r.lastContactAt ? r.lastContactAt.getTime() : null;
    case 'value':
      return r.expectedValue;
  }
}

/** Stable sort; empty values always last; ties fall back to the newest lead. */
export function sortLeads(rows: LeadRow[], key: SortKey, dir: SortDir): LeadRow[] {
  const sign = dir === 'asc' ? 1 : -1;
  return rows
    .map((r, i) => ({ r, i }))
    .sort((a, b) => {
      const x = keyOf(a.r, key);
      const y = keyOf(b.r, key);
      if (x === null && y !== null) return 1;
      if (y === null && x !== null) return -1;
      if (x !== null && y !== null && x !== y) {
        const c = typeof x === 'string' && typeof y === 'string' ? collator.compare(x, y) : (x as number) - (y as number);
        if (c !== 0) return c * sign;
      }
      const t = b.r.createdAt.getTime() - a.r.createdAt.getTime();
      return t !== 0 ? t : a.i - b.i;
    })
    .map(({ r }) => r);
}

export const PAGE_SIZE = 25;

export function paginate<T>(rows: T[], page: number, size = PAGE_SIZE): { rows: T[]; page: number; pages: number; from: number; to: number; total: number } {
  const total = rows.length;
  const pages = Math.max(1, Math.ceil(total / size));
  const p = Math.min(Math.max(1, Math.floor(page) || 1), pages);
  const start = (p - 1) * size;
  const slice = rows.slice(start, start + size);
  return { rows: slice, page: p, pages, from: total ? start + 1 : 0, to: start + slice.length, total };
}

const STAGE_IDS = new Set<string>(STAGES.map((s) => s.id));
const SORTS: SortKey[] = ['next', 'recent', 'score', 'name', 'stage', 'priority', 'last', 'value'];

/** The list state lives in the URL (?q=&stage=&sort=…), so the desktop header search and back/forward work. */
export function queryFromParams(sp: URLSearchParams): ListQuery {
  const stage = sp.get('stage') ?? 'all';
  const priority = sp.get('priority') ?? 'all';
  const source = sp.get('source') ?? 'all';
  const sort = SORTS.find((s) => s === sp.get('sort')) ?? DEFAULT_QUERY.sort;
  const dir = sp.get('dir');
  return {
    q: sp.get('q') ?? '',
    stage: STAGE_IDS.has(stage) ? (stage as Stage) : 'all',
    activity: sp.get('activity') ?? 'all',
    priority: priority === 'hot' || priority === 'warm' || priority === 'cold' ? priority : 'all',
    source: source === 'visit' || source === 'import' ? source : 'all',
    sort,
    dir: dir === 'asc' || dir === 'desc' ? dir : DEFAULT_DIR[sort],
    page: Math.max(1, Number(sp.get('page')) || 1),
  };
}

export function paramsFromQuery(q: ListQuery): URLSearchParams {
  const sp = new URLSearchParams();
  if (q.q) sp.set('q', q.q);
  if (q.stage !== 'all') sp.set('stage', q.stage);
  if (q.activity !== 'all') sp.set('activity', q.activity);
  if (q.priority !== 'all') sp.set('priority', q.priority);
  if (q.source !== 'all') sp.set('source', q.source);
  if (q.sort !== DEFAULT_QUERY.sort) sp.set('sort', q.sort);
  if (q.dir !== DEFAULT_DIR[q.sort]) sp.set('dir', q.dir);
  if (q.page > 1) sp.set('page', String(q.page));
  return sp;
}

/** «47 عميلاً»، «عميل واحد»، «عميلان»، «5 عملاء» */
export function leadsCountText(n: number): string {
  if (n === 0) return 'لا عملاء';
  if (n === 1) return 'عميل واحد';
  if (n === 2) return 'عميلان';
  if (n <= 10) return `${n.toString()} عملاء`;
  return `${n.toString()} عميلاً`;
}
