import { useEffect, useMemo, useRef, type KeyboardEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import type { UseQueryResult } from '@tanstack/react-query';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { Skeleton } from '@/components/app/Skeleton';
import { Icon } from '@/components/ui/Icon';
import type { Stage } from '@/components/ui/stages';
import { tabKeyTarget } from '@/features/lead/text';
import { activityOptions } from '@/features/leads/list-view';
import type { LeadRow } from '@/lib/leads-list';
import { buildColumns, defaultStage, filterByActivity, stageSummaryText, stageValueText, TAB_STAGES, type Columns } from './board';
import { CardItem } from './CardItem';
import { NoActivityMatch, PipelineEmpty } from './parts';
import { STAGE_TONE } from './stage-tone';
import type { usePipelineActions } from './usePipelineActions';
import type { PipelineView } from './usePipelineView';

/** Screen 7 on the phone and on a tablet in portrait — design/screens/Pipeline.dc.html. */
export function MobilePipeline({
  leads,
  now,
  view,
  actions,
}: {
  leads: UseQueryResult<LeadRow[]>;
  now: Date;
  view: PipelineView;
  actions: ReturnType<typeof usePipelineActions>;
}) {
  const navigate = useNavigate();
  const all = leads.data;
  const rows = useMemo(() => filterByActivity(all ?? [], view.activity), [all, view.activity]);
  const cols = useMemo(() => buildColumns(rows, now), [rows, now]);
  const activities = useMemo(() => activityOptions(all ?? []), [all]);
  const selected = view.stage ?? defaultStage(cols);
  const col = cols[selected];
  const failed = leads.isError && !all;
  const empty = all !== undefined && all.length === 0;
  const noMatch = !empty && all !== undefined && rows.length === 0;
  const value = stageValueText(col.sum);

  return (
    <>
      {/* the stage tabs stay while the cards scroll under them */}
      <header className="sticky top-0 z-10 flex flex-col gap-[14px] bg-surface px-4 pb-3 pt-6">
        <h1 className="m-0 text-title-1 font-bold">Pipeline</h1>
        {all && !empty ? <StageTabs cols={cols} selected={selected} onPick={view.pickStage} /> : null}
        {leads.isPending ? (
          <div className="-mx-4 flex gap-2 overflow-hidden px-4" aria-hidden="true">
            {[0, 1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-16 w-[104px] flex-none rounded-[12px]" />
            ))}
          </div>
        ) : null}
        {view.activity !== 'all' && all && !empty ? (
          <div className="flex">
            <button
              type="button"
              aria-label={`إزالة فلتر النشاط: ${activities.find((a) => a.id === view.activity)?.name ?? 'غير موجود'}`}
              onClick={() => {
                view.setActivity('all');
              }}
              className="app-seg flex min-h-[44px] items-center gap-1 rounded-full border-[1.5px] border-line-strong bg-surface-sunken pe-3 ps-[14px] text-body-sm font-bold text-ink"
            >
              {`النشاط: ${activities.find((a) => a.id === view.activity)?.name ?? 'غير موجود'}`}
              <Icon name="x" size={16} />
            </button>
          </div>
        ) : null}
      </header>

      <main className="flex flex-col gap-3 px-4 pb-6 pt-1">
        {failed ? (
          <ErrorRetry
            title="تعذّر تحميل الـ Pipeline"
            onRetry={() => {
              void leads.refetch();
            }}
          />
        ) : null}
        {leads.isPending ? (
          <div className="flex flex-col gap-3" aria-busy="true" aria-label="جارٍ التحميل">
            <Skeleton className="h-[22px] w-full" />
            {[0, 1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-[98px] rounded-lg" />
            ))}
          </div>
        ) : null}
        {empty ? <PipelineEmpty /> : null}
        {noMatch ? (
          <NoActivityMatch
            onClear={() => {
              view.setActivity('all');
            }}
          />
        ) : null}

        {all && !empty && !noMatch ? (
          <>
            <section
              id="pipeline-panel"
              role="tabpanel"
              aria-labelledby={`pipeline-tab-${selected}`}
              tabIndex={col.count ? undefined : 0}
              className="flex flex-col gap-3"
            >
              <div className="flex flex-wrap items-baseline justify-between gap-x-3 text-body-sm text-ink-muted">
                <span className="tabular-nums" aria-live="polite">
                  {stageSummaryText(col)}
                </span>
                {value ? (
                  <span>
                    قيمة متوقعة <b className="text-[17px] text-ink tabular-nums">{value}</b> ر.س
                  </span>
                ) : (
                  <span>بدون قيمة بعد</span>
                )}
              </div>
              {col.count ? (
                <div className="grid grid-cols-1 gap-3 tablet:grid-cols-2">
                  {col.cards.map((c) => (
                    <CardItem
                      key={c.id}
                      card={c}
                      desktop={false}
                      onOpen={() => {
                        void navigate(`/leads/${c.id}`);
                      }}
                      onMenu={() => {
                        actions.openStageSheet(c.row);
                      }}
                    />
                  ))}
                </div>
              ) : (
                <p className="m-0 rounded-lg border border-dashed border-line-strong px-4 py-6 text-center text-body-sm text-ink-muted">{`لا عملاء في «${col.label}» الآن.`}</p>
              )}
            </section>
            <p className="m-0 mt-1 flex items-center gap-2 text-label-sm font-normal text-ink-muted">
              <span className="flex-none">
                <Icon name="info" size={16} />
              </span>
              اضغط مطولاً على بطاقة لتغيير مرحلتها. البطاقة بحد التحذير: أكثر من 7 أيام بدون حركة.
            </p>
          </>
        ) : null}
      </main>
    </>
  );
}

/** Pipeline.dc.html stage strip: real tabs, one tab stop, arrow keys follow the reading direction. */
function StageTabs({ cols, selected, onPick }: { cols: Columns; selected: Stage; onPick: (s: Stage) => void }) {
  const refs = useRef<Partial<Record<Stage, HTMLButtonElement | null>>>({});

  // the chosen tab is always in view, also when it came from the link
  useEffect(() => {
    refs.current[selected]?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
  }, [selected]);

  const onKey = (e: KeyboardEvent<HTMLButtonElement>, i: number) => {
    const rtl = getComputedStyle(e.currentTarget).direction === 'rtl';
    const j = tabKeyTarget(e.key, i, TAB_STAGES.length, rtl);
    const stage = j === null ? undefined : TAB_STAGES[j];
    if (!stage) return;
    e.preventDefault();
    onPick(stage);
    refs.current[stage]?.focus();
  };

  return (
    <div role="tablist" aria-label="المراحل" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
      {TAB_STAGES.map((s, i) => {
        const on = s === selected;
        const c = cols[s];
        const tone = STAGE_TONE[s];
        // «خسارة» stays last and quieter until chosen (brief.md: the lost column is folded)
        const quiet = s === 'lost' && !on;
        const frame = on ? `border-2 ${tone.border} ${tone.soft}` : quiet ? 'border border-dashed border-line-strong bg-transparent' : 'border border-line bg-surface-raised';
        return (
          <button
            key={s}
            ref={(el) => {
              refs.current[s] = el;
            }}
            id={`pipeline-tab-${s}`}
            type="button"
            role="tab"
            aria-selected={on}
            // only the shown panel is in the page
            aria-controls={on ? 'pipeline-panel' : undefined}
            tabIndex={on ? 0 : -1}
            onClick={() => {
              onPick(s);
            }}
            onKeyDown={(e) => {
              onKey(e, i);
            }}
            className={`flex min-h-16 min-w-[104px] flex-none cursor-pointer flex-col items-start gap-[2px] rounded-[12px] px-3 py-2 text-start ${frame}`}
          >
            <span className={`flex items-center gap-[6px] whitespace-nowrap text-[13px] font-bold leading-[18px] ${tone.text}`}>
              <span aria-hidden="true" className="size-2 rounded-full bg-current" />
              {c.label}
            </span>
            <span className={`text-[22px] font-bold leading-7 tabular-nums ${quiet ? 'text-ink-muted' : 'text-ink'}`}>{c.count}</span>
          </button>
        );
      })}
    </div>
  );
}
