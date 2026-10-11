import { useMemo, useState, type DragEvent, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import type { UseQueryResult } from '@tanstack/react-query';
import { DeskHeader } from '@/components/app/DeskHeader';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { Skeleton } from '@/components/app/Skeleton';
import type { Stage } from '@/components/ui/stages';
import { activityOptions } from '@/features/leads/list-view';
import { formatSAR } from '@/lib/format';
import type { LeadRow } from '@/lib/leads-list';
import { BOARD_STAGES, buildColumns, columnSumText, filterByActivity, lostToggleText, openDealsText, openSummary, type PipelineColumn } from './board';
import { CardItem } from './CardItem';
import { ActivitySelect, NoActivityMatch, PipelineEmpty } from './parts';
import { STAGE_TONE } from './stage-tone';
import type { usePipelineActions } from './usePipelineActions';
import type { PipelineView } from './usePipelineView';

const DRAG_TYPE = 'text/plain';

/** Screen 7 on the desktop — design/screens/DeskPipeline.dc.html: a kanban with drag and drop. */
export function DeskPipeline({
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
  const summary = openSummary(rows);
  const failed = leads.isError && !all;
  const empty = all !== undefined && all.length === 0;
  const noMatch = !empty && all !== undefined && rows.length === 0;

  const [lostOpen, setLostOpen] = useState(false);
  const [dragging, setDragging] = useState<LeadRow | null>(null);
  const [over, setOver] = useState<Stage | null>(null);

  const endDrag = () => {
    setDragging(null);
    setOver(null);
  };

  /** Drop target props for a column (or the folded «خسارة»). */
  const dropZone = (stage: Stage) => ({
    onDragOver: (e: DragEvent<HTMLElement>) => {
      if (!dragging) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      if (over !== stage) setOver(stage);
    },
    onDragLeave: (e: DragEvent<HTMLElement>) => {
      if (e.relatedTarget instanceof Node && e.currentTarget.contains(e.relatedTarget)) return;
      setOver((o) => (o === stage ? null : o));
    },
    onDrop: (e: DragEvent<HTMLElement>) => {
      e.preventDefault();
      const id = e.dataTransfer.getData(DRAG_TYPE) || dragging?.id;
      const lead = all?.find((r) => r.id === id);
      endDrag();
      if (lead) actions.moveTo(lead, stage);
    },
  });

  const column = (col: PipelineColumn, head?: ReactNode) => {
    const tone = STAGE_TONE[col.stage];
    const isOver = over === col.stage && dragging !== null && dragging.stage !== col.stage;
    return (
      <section
        key={col.stage}
        aria-label={col.label}
        data-stage={col.stage}
        {...dropZone(col.stage)}
        className={`flex max-h-full w-[232px] flex-none flex-col overflow-hidden rounded-lg bg-surface-sunken ${isOver ? `outline-2 outline-offset-2 ${tone.outline} outline-dashed` : ''}`}
      >
        <div className={`flex flex-col gap-[2px] border-t-4 px-3 pb-[10px] pt-3 ${tone.border} ${tone.soft}`}>
          <div className="flex items-center justify-between gap-2">
            {head ?? (
              <h2 className={`m-0 flex items-center gap-[6px] text-body-sm font-bold leading-5 ${tone.text}`}>
                <span aria-hidden="true" className="size-2 rounded-full bg-current" />
                {col.label}
              </h2>
            )}
            <span className="text-label-sm font-bold text-ink tabular-nums">{col.count}</span>
          </div>
          <span className="text-label-sm font-normal text-ink-muted tabular-nums">{columnSumText(col.sum)}</span>
        </div>
        <div className="flex min-h-[120px] flex-1 flex-col gap-2 overflow-y-auto p-2">
          {col.cards.map((c) => (
            <CardItem
              key={c.id}
              card={c}
              desktop
              drag={{
                dragging: dragging?.id === c.id,
                onDragStart: (e) => {
                  e.dataTransfer.setData(DRAG_TYPE, c.id);
                  e.dataTransfer.effectAllowed = 'move';
                  setDragging(c.row);
                },
                onDragEnd: endDrag,
              }}
              onOpen={() => {
                void navigate(`/leads/${c.id}`);
              }}
              onMenu={() => {
                actions.openStageSheet(c.row);
              }}
            />
          ))}
        </div>
      </section>
    );
  };

  const lostOver = over === 'lost' && dragging !== null && dragging.stage !== 'lost';

  return (
    <div className="flex h-dvh min-h-[560px] flex-col">
      <DeskHeader
        title="Pipeline"
        search={false}
        subtitle={
          all ? (
            <>
              {`${openDealsText(summary.count)} · `}
              {summary.value > 0 ? (
                <>
                  قيمة متوقعة <b className="text-ink tabular-nums">{formatSAR(summary.value)}</b>
                </>
              ) : (
                'بدون قيمة متوقعة بعد'
              )}
              {' · اسحب البطاقة لتغيير مرحلتها'}
            </>
          ) : (
            'اسحب البطاقة لتغيير مرحلتها'
          )
        }
        extra={all && !empty ? <ActivitySelect value={view.activity} options={activities} onChange={view.setActivity} /> : null}
      />

      {failed || empty || noMatch ? (
        <main className="w-full px-10 pb-12 pt-6">
          {failed ? (
            <ErrorRetry
              title="تعذّر تحميل الـ Pipeline"
              onRetry={() => {
                void leads.refetch();
              }}
            />
          ) : (
            <div className="rounded-lg border border-line bg-surface-raised py-6">
              {empty ? (
                <PipelineEmpty />
              ) : (
                <NoActivityMatch
                  onClear={() => {
                    view.setActivity('all');
                  }}
                />
              )}
            </div>
          )}
        </main>
      ) : (
        <main aria-label="لوحة المراحل" className="min-h-0 w-full flex-1 overflow-x-auto overflow-y-hidden px-10 pb-6 pt-6">
          {all ? (
            <div className="flex h-full min-w-max items-start gap-3">
              {BOARD_STAGES.map((s) => column(cols[s]))}
              {lostOpen ? (
                column(
                  cols.lost,
                  <h2 className="m-0">
                    <button
                      type="button"
                      aria-expanded="true"
                      onClick={() => {
                        setLostOpen(false);
                      }}
                      className={`-mx-2 -my-3 flex min-h-touch cursor-pointer items-center gap-[6px] rounded-md border-0 bg-transparent px-2 text-body-sm font-bold ${STAGE_TONE.lost.text}`}
                    >
                      <span aria-hidden="true" className="size-2 rounded-full bg-current" />
                      {cols.lost.label}
                      <span className="sr-only"> · اطوِ العمود</span>
                    </button>
                  </h2>,
                )
              ) : (
                <button
                  type="button"
                  aria-expanded="false"
                  aria-label={`${lostToggleText(cols.lost.count)} · افتح العمود`}
                  data-stage="lost"
                  onClick={() => {
                    setLostOpen(true);
                  }}
                  {...dropZone('lost')}
                  className={`flex min-h-[240px] w-14 flex-none cursor-pointer flex-col items-center gap-[10px] rounded-lg border border-dashed border-line-strong bg-transparent px-0 py-[14px] ${STAGE_TONE.lost.text} ${lostOver ? `outline-2 outline-offset-2 outline-dashed ${STAGE_TONE.lost.outline}` : ''}`}
                >
                  <span aria-hidden="true" className="size-2 rounded-full bg-current" />
                  <span className="text-body-sm font-bold tabular-nums [writing-mode:vertical-rl]">{lostToggleText(cols.lost.count)}</span>
                </button>
              )}
            </div>
          ) : (
            <BoardSkeleton />
          )}
        </main>
      )}
    </div>
  );
}

function BoardSkeleton() {
  return (
    <div className="flex min-w-max items-start gap-3" aria-busy="true" aria-label="جارٍ التحميل">
      {BOARD_STAGES.map((s, i) => (
        <div key={s} className="flex w-[232px] flex-none flex-col gap-2">
          <Skeleton className="h-[66px] rounded-lg" />
          {Array.from({ length: (i % 3) + 1 }, (_, k) => (
            <Skeleton key={k} className="h-[92px] rounded-lg" />
          ))}
        </div>
      ))}
      <Skeleton className="h-[240px] w-14 flex-none rounded-lg" />
    </div>
  );
}
