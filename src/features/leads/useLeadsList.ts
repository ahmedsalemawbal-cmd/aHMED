import { useMemo, useState } from 'react';
import { useLocation, useSearchParams } from 'react-router-dom';
import { useLeads } from '@/data/leads';
import { DEFAULT_QUERY, filterExceptStage, paginate, paramsFromQuery, queryFromParams, sortLeads, stageCounts, type LeadRow, type ListQuery } from '@/lib/leads-list';

/** Marks the list's own URL updates, so typing is never overwritten by its own echo. */
const OWN_STATE = { leadsList: true } as const;

function isOwnNavigation(state: unknown): boolean {
  return typeof state === 'object' && state !== null && 'leadsList' in state;
}

const NO_ROWS: LeadRow[] = [];

/**
 * «العملاء» state: the query lives in the URL (?q=&stage=&sort=…, replaced
 * in place), the search box keeps its own text so fast typing is never lost,
 * and everything else is derived in the browser (one user, hundreds of leads).
 */
export function useLeadsList() {
  const leads = useLeads();
  const [params, setParams] = useSearchParams();
  const location = useLocation();
  const query = useMemo(() => queryFromParams(params), [params]);

  // the search text follows the URL when something else changes it (nav link, back/forward)
  const [text, setText] = useState(query.q);
  const [seenKey, setSeenKey] = useState(location.key);
  if (seenKey !== location.key) {
    setSeenKey(location.key);
    if (!isOwnNavigation(location.state) && text !== query.q) setText(query.q);
  }

  const update = (patch: Partial<ListQuery>) => {
    setParams(paramsFromQuery({ ...query, page: 1, ...patch }), { replace: true, state: OWN_STATE });
  };

  const search = (value: string) => {
    setText(value);
    update({ q: value });
  };

  const clearAll = () => {
    setText('');
    setParams(paramsFromQuery({ ...DEFAULT_QUERY, sort: query.sort, dir: query.dir }), { replace: true, state: OWN_STATE });
  };

  // desktop row selection; kept here so a layout switch (rotating a tablet) does not drop it
  const [selected, setSelected] = useState<ReadonlySet<string>>(() => new Set());

  const rows = leads.data ?? NO_ROWS;
  const derived = useMemo(() => {
    const exceptStage = filterExceptStage(rows, query);
    const counts = stageCounts(exceptStage);
    const filtered = query.stage === 'all' ? exceptStage : exceptStage.filter((r) => r.stage === query.stage);
    const sorted = sortLeads(filtered, query.sort, query.dir);
    return { counts, sorted, page: paginate(sorted, query.page) };
  }, [rows, query]);

  return {
    leads,
    rows,
    query,
    text,
    search,
    update,
    clearAll,
    selected,
    setSelected,
    ...derived,
  };
}

export type LeadsList = ReturnType<typeof useLeadsList>;
