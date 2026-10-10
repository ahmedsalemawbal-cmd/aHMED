import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { Skeleton } from '@/components/app/Skeleton';
import { Icon } from '@/components/ui/Icon';
import { LeadCard } from '@/components/ui/LeadCard';
import { PRIORITY } from '@/components/ui/stages';
import { leadsCountText, type LeadRow, type ListQuery } from '@/lib/leads-list';
import { activityOptions, canWhatsApp, chipLabel, lastContactText, listKey, messageHref, nextWhen, SORT_NAME, SOURCE_LABEL, stageChipIds } from './list-view';
import { NoLeads, NoResults, SearchBox, SortGlyph } from './parts';
import { SortSheet } from './SortSheet';
import { SwipeActions, type SwipeAction } from './SwipeActions';
import type { LeadsList } from './useLeadsList';

/** Screen 5 on the phone — design/screens/Leads.dc.html. */
export function MobileLeads({ list, now }: { list: LeadsList; now: Date }) {
  const { leads, rows, query, counts, sorted } = list;
  const [sheet, setSheet] = useState(false);
  const activities = useMemo(() => activityOptions(rows), [rows]);
  const failed = leads.isError && !leads.data;
  const empty = Boolean(leads.data) && rows.length === 0;

  // the header stays on screen, so a new search, chip or sort must show its first results
  const key = listKey(query);
  const shownKey = useRef(key);
  useEffect(() => {
    if (shownKey.current === key) return;
    shownKey.current = key;
    if (window.scrollY > 0) window.scrollTo({ top: 0 });
  }, [key]);

  return (
    <>
      {/* Leads.dc.html: the header stays while the list scrolls under it */}
      <header className="sticky top-0 z-10 flex flex-col gap-3 bg-surface px-4 pb-3 pt-6">
        <h1 className="m-0 text-title-1 font-bold">
          العملاء
          {leads.data ? (
            <>
              {' '}
              <span className="text-[16px] font-normal text-ink-muted tabular-nums">{rows.length}</span>
            </>
          ) : null}
        </h1>
        {!failed && !empty ? (
          <>
            <SearchBox value={list.text} onChange={list.search} />
            {leads.data ? (
              <>
                <StageChips
                  counts={counts}
                  selected={query.stage}
                  onPick={(stage) => {
                    list.update({ stage });
                  }}
                />
                <div className="flex items-center justify-between text-body-sm text-ink-muted">
                  <span className="tabular-nums" aria-live="polite">
                    {leadsCountText(sorted.length)}
                  </span>
                  <button
                    type="button"
                    aria-haspopup="dialog"
                    onClick={() => {
                      setSheet(true);
                    }}
                    className="app-seg flex min-h-[44px] items-center gap-1 border-0 bg-transparent p-0 text-body-sm font-bold text-ink"
                  >
                    <SortGlyph state="none" size={18} />
                    {`ترتيب: ${SORT_NAME[query.sort]}`}
                  </button>
                </div>
                <ActiveFilters query={query} activities={activities} onRemove={list.update} />
              </>
            ) : (
              <div className="flex gap-2" aria-hidden="true">
                <Skeleton className="h-[44px] w-[72px] rounded-full" />
                <Skeleton className="h-[44px] w-[112px] rounded-full" />
                <Skeleton className="h-[44px] w-[104px] rounded-full" />
              </div>
            )}
          </>
        ) : null}
      </header>

      <main className="flex flex-col gap-3 px-4 pb-6">
        {failed ? (
          <ErrorRetry
            title="تعذّر تحميل العملاء"
            onRetry={() => {
              void leads.refetch();
            }}
          />
        ) : null}
        {leads.isPending ? (
          <div className="flex flex-col gap-3" aria-busy="true" aria-label="جارٍ التحميل">
            {[0, 1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-[178px] rounded-lg" />
            ))}
          </div>
        ) : null}
        {empty ? <NoLeads /> : null}
        {leads.data && !empty && sorted.length === 0 ? <NoResults q={query.q} onClear={list.clearAll} /> : null}
        {sorted.map((r) => (
          <LeadItem key={r.id} row={r} now={now} />
        ))}
      </main>

      {sheet ? (
        <SortSheet
          initial={{ sort: query.sort, dir: query.dir, activity: query.activity, priority: query.priority, source: query.source }}
          activities={activities}
          onClose={() => {
            setSheet(false);
          }}
          onApply={(choice) => {
            setSheet(false);
            list.update(choice);
          }}
        />
      ) : null}
    </>
  );
}

function StageChips({ counts, selected, onPick }: { counts: LeadsList['counts']; selected: ListQuery['stage']; onPick: (s: ListQuery['stage']) => void }) {
  return (
    <div role="group" aria-label="المراحل" className="-mx-4 flex gap-2 overflow-x-auto px-4 py-[2px]">
      {stageChipIds(counts, selected).map((id) => {
        const on = id === selected;
        return (
          <button
            key={id}
            type="button"
            aria-pressed={on}
            onClick={() => {
              onPick(id);
            }}
            className={`app-seg min-h-[44px] flex-none whitespace-nowrap rounded-full border-[1.5px] px-[14px] text-body-sm font-bold ${on ? 'border-ink bg-ink text-surface-raised' : 'border-line-strong bg-surface-raised text-ink'}`}
          >
            {chipLabel(id)} <span className="font-normal tabular-nums opacity-80">{counts[id]}</span>
          </button>
        );
      })}
    </div>
  );
}

function ActiveFilters({ query, activities, onRemove }: { query: ListQuery; activities: { id: string; name: string }[]; onRemove: (patch: Partial<ListQuery>) => void }) {
  const chips: { key: string; text: string; clear: Partial<ListQuery> }[] = [];
  if (query.activity !== 'all') chips.push({ key: 'activity', text: `النشاط: ${activities.find((a) => a.id === query.activity)?.name ?? 'غير موجود'}`, clear: { activity: 'all' } });
  if (query.priority !== 'all') chips.push({ key: 'priority', text: `الأولوية: ${PRIORITY[query.priority].label}`, clear: { priority: 'all' } });
  if (query.source !== 'all') chips.push({ key: 'source', text: `المصدر: ${SOURCE_LABEL[query.source]}`, clear: { source: 'all' } });
  if (!chips.length) return null;
  return (
    <div role="group" aria-label="الفلاتر المفعّلة" className="flex flex-wrap gap-2 py-[2px]">
      {chips.map((c) => (
        <button
          key={c.key}
          type="button"
          aria-label={`إزالة فلتر ${c.text}`}
          onClick={() => {
            onRemove(c.clear);
          }}
          className="app-seg flex min-h-[44px] items-center gap-1 rounded-full border-[1.5px] border-line-strong bg-surface-sunken pe-3 ps-[14px] text-body-sm font-bold text-ink"
        >
          {c.text}
          <Icon name="x" size={16} />
        </button>
      ))}
    </div>
  );
}

function LeadItem({ row, now }: { row: LeadRow; now: Date }) {
  const navigate = useNavigate();
  const when = nextWhen(row, now);
  const wa = canWhatsApp(row);
  const openMessage = () => {
    void navigate(messageHref(row));
  };
  const actions: SwipeAction[] = [];
  if (wa) actions.push({ key: 'wa', label: 'واتساب', icon: 'chat', tone: 'whatsapp', onSelect: openMessage });
  actions.push({
    key: 'visit',
    label: 'زيارة جديدة',
    icon: 'pin',
    tone: 'neutral',
    onSelect: () => {
      void navigate(`/leads/${row.id}/visit`);
    },
  });
  return (
    <SwipeActions actions={actions} label={`إجراءات سريعة: ${row.businessName}`}>
      <LeadCard
        businessName={row.businessName}
        activity={row.activity}
        contactName={row.contactName ?? undefined}
        stage={row.stage}
        score={row.score}
        priority={row.priority}
        lastContact={lastContactText(row, now) ?? undefined}
        nextAction={row.next?.title}
        nextActionAt={when?.text}
        overdue={when?.overdue ?? false}
        onClick={() => {
          void navigate(`/leads/${row.id}`);
        }}
        onWhatsApp={wa ? openMessage : undefined}
      />
    </SwipeActions>
  );
}
