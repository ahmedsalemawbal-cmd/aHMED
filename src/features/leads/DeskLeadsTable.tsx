import { Link } from 'react-router-dom';
import { Skeleton } from '@/components/app/Skeleton';
import { PriorityBadge } from '@/components/ui/PriorityBadge';
import { StageBadge } from '@/components/ui/StageBadge';
import { formatSAR } from '@/lib/format';
import type { LeadRow, ListQuery, SortKey } from '@/lib/leads-list';
import { ariaSort, lastContactText, nextWhen, scoreBarClass, SORT_NAME } from './list-view';
import { SortGlyph } from './parts';

const COLUMNS: { key: SortKey; end?: boolean; pad: string }[] = [
  { key: 'name', pad: 'px-3' },
  { key: 'stage', pad: 'px-3' },
  { key: 'score', pad: 'px-3' },
  { key: 'priority', pad: 'px-3' },
  { key: 'last', pad: 'px-3' },
  { key: 'next', pad: 'px-3' },
  { key: 'value', pad: 'px-5', end: true },
];

function ScoreCell({ score }: { score: number | null }) {
  return (
    <div className="flex items-center gap-2">
      <b className="min-w-[22px]">{score ?? '—'}</b>
      <span className="block h-[6px] w-[56px] overflow-hidden rounded-full bg-surface-sunken" aria-hidden="true">
        {score !== null ? <span className={`block h-full ${scoreBarClass(score)}`} style={{ width: `${Math.min(100, Math.max(0, score)).toString()}%` }} /> : null}
      </span>
    </div>
  );
}

/** The DeskLeads.dc.html table: sortable headers, a checkbox per row and one for the page. */
export function DeskLeadsTable({
  rows,
  query,
  now,
  selected,
  onSort,
  onToggle,
  onTogglePage,
}: {
  rows: LeadRow[];
  query: ListQuery;
  now: Date;
  selected: ReadonlySet<string>;
  onSort: (key: SortKey) => void;
  onToggle: (id: string) => void;
  onTogglePage: (select: boolean) => void;
}) {
  const onPage = rows.filter((r) => selected.has(r.id)).length;
  const all = rows.length > 0 && onPage === rows.length;
  return (
    <div className="overflow-x-auto rounded-lg border border-line bg-surface-raised">
      <table aria-label="العملاء" className="w-full min-w-[980px] border-collapse text-body-sm tabular-nums">
        <thead>
          <tr className="bg-surface text-start text-label-sm text-ink-muted">
            <th scope="col" className="w-12 px-4">
              <input
                type="checkbox"
                aria-label="تحديد كل الصفوف"
                className="block size-5 cursor-pointer accent-action"
                checked={all}
                ref={(el) => {
                  if (el) el.indeterminate = onPage > 0 && !all;
                }}
                onChange={() => {
                  onTogglePage(!all);
                }}
              />
            </th>
            {COLUMNS.map((c) => {
              const sort = ariaSort(query, c.key);
              return (
                <th key={c.key} scope="col" aria-sort={sort} className={`${c.pad} font-bold ${c.end ? 'text-end' : 'text-start'}`}>
                  <button
                    type="button"
                    onClick={() => {
                      onSort(c.key);
                    }}
                    className={`inline-flex min-h-[44px] cursor-pointer items-center gap-1 border-0 bg-transparent p-0 font-bold hover:text-ink ${sort === 'none' ? 'text-ink-muted' : 'text-ink'}`}
                  >
                    {SORT_NAME[c.key]}
                    <SortGlyph state={sort} size={14} />
                  </button>
                </th>
              );
            })}
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => {
            const on = selected.has(r.id);
            const when = nextWhen(r, now);
            return (
              <tr key={r.id} className={`border-t border-line ${on ? 'bg-surface-sunken' : ''}`}>
                <td className="px-4">
                  <input
                    type="checkbox"
                    aria-label={`تحديد ${r.businessName}`}
                    className="block size-5 cursor-pointer accent-action"
                    checked={on}
                    onChange={() => {
                      onToggle(r.id);
                    }}
                  />
                </td>
                <td className="p-3">
                  <Link to={`/leads/${r.id}`} className="text-[15px] font-bold text-ink no-underline hover:underline">
                    {r.businessName}
                  </Link>
                  <div className="text-label-sm text-ink-muted">{[r.activity, r.contactName].filter(Boolean).join(' · ')}</div>
                </td>
                <td className="whitespace-nowrap p-3">
                  <StageBadge stage={r.stage} size="sm" />
                </td>
                <td className="p-3">
                  <ScoreCell score={r.score} />
                </td>
                <td className="whitespace-nowrap p-3">{r.priority ? <PriorityBadge priority={r.priority} size="sm" /> : <span className="text-ink-muted">—</span>}</td>
                <td className="whitespace-nowrap p-3 text-ink-muted">{lastContactText(r, now) ?? '—'}</td>
                <td className="p-3">
                  {r.next && when ? (
                    <>
                      <div className="font-bold">{r.next.title}</div>
                      <div className={`text-label-sm ${when.overdue ? 'font-bold text-warning' : 'text-ink-muted'}`}>{when.text}</div>
                    </>
                  ) : (
                    <span className="text-ink-muted">—</span>
                  )}
                </td>
                <td className="whitespace-nowrap px-5 py-3 text-end font-bold">{r.expectedValue !== null ? formatSAR(r.expectedValue) : '—'}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

export function DeskTableSkeleton() {
  return (
    <div className="flex flex-col gap-4" aria-busy="true" aria-label="جارٍ التحميل">
      <div className="flex gap-2">
        <Skeleton className="h-10 w-[320px]" />
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-10 w-[128px]" />
        ))}
      </div>
      <div className="flex flex-col gap-[1px] overflow-hidden rounded-lg border border-line bg-surface-raised">
        <Skeleton className="h-11 rounded-none" />
        {[0, 1, 2, 3, 4, 5, 6, 7].map((i) => (
          <Skeleton key={i} className="h-[66px] rounded-none" />
        ))}
      </div>
    </div>
  );
}
