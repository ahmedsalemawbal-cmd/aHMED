import { STAGE_LABEL, STAGES, type Stage } from '@/components/ui/stages';
import { formatNumber, formatSAR } from '@/lib/format';
import { leadsCountText, type LeadRow } from '@/lib/leads-list';
import { daysInStage, isStale } from '@/lib/stale';

/** Kanban columns in order (DeskPipeline.dc.html); «خسارة» is folded after them (brief.md §7). */
export const BOARD_STAGES: Stage[] = ['not_visited', 'visited', 'contacted', 'replied', 'meeting', 'proposal', 'won'];

/** The phone's stage tabs: the board, then the quieter «خسارة». */
export const TAB_STAGES: Stage[] = [...BOARD_STAGES, 'lost'];

/** Open deals for the desktop header: from «تمت الزيارة» to «عرض سعر». */
const OPEN_STAGES: ReadonlySet<Stage> = new Set<Stage>(['visited', 'contacted', 'replied', 'meeting', 'proposal']);

export interface PipelineCard {
  id: string;
  businessName: string;
  activity: string;
  /** «2,500 ر.س», or «—» without an expected value */
  value: string;
  daysInStage: number;
  stale: boolean;
  row: LeadRow;
}

export interface PipelineColumn {
  stage: Stage;
  label: string;
  count: number;
  /** sum of the expected values, 0 when none has one */
  sum: number;
  cards: PipelineCard[];
}

export type Columns = Record<Stage, PipelineColumn>;

export function toCard(row: LeadRow, now: Date): PipelineCard {
  const days = daysInStage(row.stageChangedAt, now);
  return {
    id: row.id,
    businessName: row.businessName,
    activity: row.activity,
    value: row.expectedValue !== null ? formatSAR(row.expectedValue) : '—',
    daysInStage: days,
    stale: isStale(row.stage, days),
    row,
  };
}

const collator = new Intl.Collator('ar');

/**
 * Every stage with its leads, the most recently moved first (a card just
 * moved shows at the top of its new column), then by name.
 */
export function buildColumns(rows: LeadRow[], now: Date): Columns {
  const cols = Object.fromEntries(STAGES.map((s) => [s.id, { stage: s.id, label: s.label, count: 0, sum: 0, cards: [] as PipelineCard[] }])) as Columns;
  for (const r of rows) {
    const col = cols[r.stage];
    col.cards.push(toCard(r, now));
    col.count++;
    col.sum += r.expectedValue ?? 0;
  }
  for (const s of STAGES) {
    cols[s.id].cards.sort((a, b) => b.row.stageChangedAt.getTime() - a.row.stageChangedAt.getTime() || collator.compare(a.businessName, b.businessName));
  }
  return cols;
}

/** The activity filter («النشاط: الكل»); an unknown id shows nothing rather than everything. */
export function filterByActivity(rows: LeadRow[], activity: string): LeadRow[] {
  return activity === 'all' ? rows : rows.filter((r) => r.activityId === activity);
}

/** Open deals (visited … proposal): how many and their expected value. */
export function openSummary(rows: Pick<LeadRow, 'stage' | 'expectedValue'>[]): { count: number; value: number } {
  let count = 0;
  let value = 0;
  for (const r of rows) {
    if (!OPEN_STAGES.has(r.stage)) continue;
    count++;
    value += r.expectedValue ?? 0;
  }
  return { count, value };
}

/** «صفقة واحدة مفتوحة»، «صفقتان مفتوحتان»، «3 صفقات مفتوحة»، «12 صفقة مفتوحة». */
export function openDealsText(n: number): string {
  if (n === 0) return 'لا صفقات مفتوحة';
  if (n === 1) return 'صفقة واحدة مفتوحة';
  if (n === 2) return 'صفقتان مفتوحتان';
  const r = n % 100;
  if (r >= 3 && r <= 10) return `${n.toString()} صفقات مفتوحة`;
  return `${n.toString()} صفقة مفتوحة`;
}

/** Column head: «28,500 ر.س متوقعة», or «بدون قيمة بعد». */
export function columnSumText(sum: number): string {
  return sum > 0 ? `${formatSAR(sum)} متوقعة` : 'بدون قيمة بعد';
}

/** Phone summary line: «تم الإرسال · 4 عملاء». */
export function stageSummaryText(col: Pick<PipelineColumn, 'label' | 'count'>): string {
  return `${col.label} · ${leadsCountText(col.count)}`;
}

/** The sum on the phone summary line, as digits only («28,500»); null without a value. */
export function stageValueText(sum: number): string | null {
  return sum > 0 ? formatNumber(sum) : null;
}

/** ?stage= → a known stage, or null. */
export function parseStage(param: string | null): Stage | null {
  return STAGES.find((s) => s.id === param)?.id ?? null;
}

/** Without ?stage=, the phone opens the first column that has leads (else «تمت الزيارة»). */
export function defaultStage(cols: Columns): Stage {
  return TAB_STAGES.find((s) => cols[s].count > 0) ?? 'visited';
}

/** Collapsed «خسارة · 6» button and its expanded heading. */
export function lostToggleText(count: number): string {
  return `${STAGE_LABEL.lost} · ${count.toString()}`;
}
