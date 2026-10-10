import { useMemo } from 'react';
import { DeskHeader } from '@/components/app/DeskHeader';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { Button } from '@/components/ui/Button';
import { PRIORITY, STAGES, type Priority, type Stage } from '@/components/ui/stages';
import type { ListQuery, Source } from '@/lib/leads-list';
import { DeskLeadsTable, DeskTableSkeleton } from './DeskLeadsTable';
import { activityOptions, nextSort, selectionText, SOURCE_LABEL } from './list-view';
import { Chevron, NoLeads, NoResults, SearchBox } from './parts';
import type { LeadsList } from './useLeadsList';
import { useSelectionActions } from './useSelectionActions';

interface Option<T extends string> {
  value: T;
  label: string;
  /** text inside the native list, e.g. with a count */
  option?: string;
}

/** A design «المرحلة: الكل ⌄» button holding a native select (keyboard and screen readers for free). */
function FilterSelect<T extends string>({ label, value, options, onChange }: { label: string; value: T; options: Option<T>[]; onChange: (v: T) => void }) {
  const current = options.find((o) => o.value === value)?.label ?? 'الكل';
  return (
    <label className="relative flex min-h-control-sm cursor-pointer items-center gap-[6px] rounded-md border-[1.5px] border-line-strong bg-surface-raised px-3 text-body-sm text-ink has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus">
      <span className="text-ink-muted">{`${label}:`}</span>
      <b>{current}</b>
      <Chevron />
      <select
        aria-label={label}
        value={value}
        onChange={(e) => {
          const picked = options.find((o) => o.value === e.target.value);
          if (picked) onChange(picked.value);
        }}
        className="absolute inset-0 cursor-pointer opacity-0"
      >
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.option ?? o.label}
          </option>
        ))}
      </select>
    </label>
  );
}

const PRIORITIES: Priority[] = ['hot', 'warm', 'cold'];

/** Screen 5 on the desktop — design/screens/DeskLeads.dc.html. */
export function DeskLeads({ list, now }: { list: LeadsList; now: Date }) {
  const { leads, rows, query, counts, sorted, page, selected, setSelected } = list;
  const activities = useMemo(() => activityOptions(rows), [rows]);
  const failed = leads.isError && !leads.data;
  const empty = Boolean(leads.data) && rows.length === 0;
  // only what the filters show can be acted on
  const chosen = useMemo(() => sorted.filter((r) => selected.has(r.id)), [sorted, selected]);

  const clearSelection = () => {
    setSelected(new Set());
  };
  const actions = useSelectionActions(chosen, clearSelection);

  const toggle = (id: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const togglePage = (select: boolean) => {
    setSelected((prev) => {
      const next = new Set(prev);
      for (const r of page.rows) {
        if (select) next.add(r.id);
        else next.delete(r.id);
      }
      return next;
    });
  };

  const stageOptions: Option<Stage | 'all'>[] = [
    { value: 'all', label: 'الكل', option: `الكل · ${counts.all.toString()}` },
    ...STAGES.map((s) => ({ value: s.id, label: s.label, option: `${s.label} · ${counts[s.id].toString()}` })),
  ];
  const activityOpts: Option<string>[] = [{ value: 'all', label: 'الكل' }, ...activities.map((a) => ({ value: a.id, label: a.name }))];
  if (query.activity !== 'all' && !activities.some((a) => a.id === query.activity)) activityOpts.push({ value: query.activity, label: 'غير موجود' });

  return (
    <>
      <DeskHeader
        search={false}
        title={
          <>
            العملاء
            {leads.data ? (
              <>
                {' '}
                <span className="text-[16px] font-normal text-ink-muted tabular-nums">{rows.length}</span>
              </>
            ) : null}
          </>
        }
      />
      <main className="flex w-full flex-col gap-4 px-10 pb-12 pt-6">
        {failed ? (
          <ErrorRetry
            title="تعذّر تحميل العملاء"
            onRetry={() => {
              void leads.refetch();
            }}
          />
        ) : null}
        {leads.isPending ? <DeskTableSkeleton /> : null}
        {empty ? (
          <div className="rounded-lg border border-line bg-surface-raised py-6">
            <NoLeads />
          </div>
        ) : null}

        {leads.data && !empty ? (
          <>
            <div className="flex flex-wrap items-center gap-2" role="group" aria-label="البحث والفلاتر">
              <SearchBox size="sm" value={list.text} onChange={list.search} />
              <FilterSelect
                label="المرحلة"
                value={query.stage}
                options={stageOptions}
                onChange={(stage) => {
                  list.update({ stage });
                }}
              />
              <FilterSelect
                label="النشاط"
                value={query.activity}
                options={activityOpts}
                onChange={(activity) => {
                  list.update({ activity });
                }}
              />
              <FilterSelect<Priority | 'all'>
                label="الأولوية"
                value={query.priority}
                options={[{ value: 'all', label: 'الكل' }, ...PRIORITIES.map((p) => ({ value: p, label: PRIORITY[p].label }))]}
                onChange={(priority) => {
                  list.update({ priority });
                }}
              />
              <FilterSelect<Source | 'all'>
                label="المصدر"
                value={query.source}
                options={[
                  { value: 'all', label: 'الكل' },
                  { value: 'visit', label: SOURCE_LABEL.visit },
                  { value: 'import', label: SOURCE_LABEL.import },
                ]}
                onChange={(source) => {
                  list.update({ source });
                }}
              />
            </div>

            {chosen.length ? (
              <div role="region" aria-label="إجراءات على المحدد" className="flex flex-wrap items-center gap-3 rounded-md bg-ink px-4 py-[10px] text-body-sm text-surface-raised">
                <b className="grow tabular-nums" aria-live="polite">
                  {selectionText(chosen.length)}
                </b>
                <button
                  type="button"
                  aria-haspopup="dialog"
                  disabled={actions.busy}
                  onClick={actions.openStageSheet}
                  className="min-h-[36px] cursor-pointer rounded-[8px] border-[1.5px] border-surface-raised bg-transparent px-3 text-body-sm font-bold text-surface-raised disabled:opacity-60"
                >
                  تغيير المرحلة
                </button>
                <button
                  type="button"
                  onClick={actions.exportCsv}
                  className="min-h-[36px] cursor-pointer rounded-[8px] border-[1.5px] border-surface-raised bg-transparent px-3 text-body-sm font-bold text-surface-raised"
                >
                  تصدير CSV
                </button>
                <button
                  type="button"
                  onClick={clearSelection}
                  className="min-h-[36px] cursor-pointer border-0 bg-transparent px-2 text-body-sm text-surface-raised underline underline-offset-4"
                >
                  إلغاء التحديد
                </button>
              </div>
            ) : null}

            {sorted.length === 0 ? (
              <div className="rounded-lg border border-line bg-surface-raised py-6">
                <NoResults
                  q={query.q}
                  onClear={() => {
                    clearSelection();
                    list.clearAll();
                  }}
                />
              </div>
            ) : (
              <>
                <DeskLeadsTable
                  rows={page.rows}
                  query={query}
                  now={now}
                  selected={selected}
                  onSort={(key) => {
                    list.update(nextSort(query, key));
                  }}
                  onToggle={toggle}
                  onTogglePage={togglePage}
                />
                <Pager
                  page={page}
                  onPage={(p) => {
                    list.update({ page: p });
                    window.scrollTo({ top: 0 });
                  }}
                />
              </>
            )}
          </>
        ) : null}
      </main>

      {actions.sheets}
    </>
  );
}

function Pager({ page, onPage }: { page: LeadsList['page']; onPage: (p: ListQuery['page']) => void }) {
  return (
    <nav aria-label="الصفحات" className="flex items-center justify-between text-body-sm text-ink-muted">
      <span className="tabular-nums">{`عرض ${page.from.toString()}–${page.to.toString()} من ${page.total.toString()}`}</span>
      <div className="flex gap-2">
        <Button
          size="sm"
          disabled={page.page <= 1}
          onClick={() => {
            onPage(page.page - 1);
          }}
        >
          السابق
        </Button>
        <Button
          size="sm"
          disabled={page.page >= page.pages}
          onClick={() => {
            onPage(page.page + 1);
          }}
        >
          التالي
        </Button>
      </div>
    </nav>
  );
}
