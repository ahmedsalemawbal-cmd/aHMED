import { describe, expect, it } from 'vitest';
import {
  DEFAULT_QUERY,
  filterExceptStage,
  filterLeads,
  latinDigits,
  leadsCountText,
  matchesSearch,
  paginate,
  paramsFromQuery,
  queryFromParams,
  sortLeads,
  stageCounts,
  type LeadRow,
} from './leads-list';

let n = 0;
function row(over: Partial<LeadRow> = {}): LeadRow {
  n++;
  return {
    id: `l${n.toString()}`,
    businessName: `محل ${n.toString()}`,
    activityId: 'a-rest',
    activity: 'مطعم',
    contactName: null,
    contactRole: null,
    phone: null,
    stage: 'visited',
    score: 50,
    priority: 'warm',
    source: 'visit',
    expectedValue: null,
    createdAt: new Date(Date.UTC(2026, 9, 1, n)),
    lastContactAt: null,
    stageChangedAt: new Date(Date.UTC(2026, 9, 1)),
    doNotContact: false,
    waConsent: true,
    next: null,
    ...over,
  };
}

describe('search by name or number', () => {
  const r = row({ businessName: 'مطعم الريدان', contactName: 'خالد العتيبي', phone: '966551234567' });
  it('name: spelling-tolerant, partial, contact name too', () => {
    expect(matchesSearch(r, 'ريدان')).toBe(true);
    expect(matchesSearch(r, 'مطعم ريدان')).toBe(true);
    expect(matchesSearch(r, 'خالد')).toBe(true);
    expect(matchesSearch(r, 'كافيه')).toBe(false);
    expect(matchesSearch(r, '  ')).toBe(true);
  });
  it('number: as typed (05…), as stored (9665…), Arabic digits, spaces', () => {
    expect(matchesSearch(r, '0551')).toBe(true);
    expect(matchesSearch(r, '055 123 4567')).toBe(true);
    expect(matchesSearch(r, '٠٥٥١٢٣')).toBe(true);
    expect(matchesSearch(r, '96655123')).toBe(true);
    expect(matchesSearch(r, '0559')).toBe(false);
    expect(matchesSearch(row({ phone: null }), '055')).toBe(false);
  });
  it('Arabic-Indic and Persian digits become Latin', () => {
    expect(latinDigits('٠١٢٣٤٥٦٧٨٩ ۰۱۲۳')).toBe('0123456789 0123');
  });
});

describe('filters and stage counts', () => {
  const rows = [
    row({ stage: 'visited', activityId: 'a-rest', priority: 'hot' }),
    row({ stage: 'contacted', activityId: 'a-cafe', priority: 'warm' }),
    row({ stage: 'contacted', activityId: 'a-rest', priority: 'hot', source: 'import' }),
    row({ stage: 'lost', activityId: 'a-rest', priority: null }),
  ];
  it('stage, activity, priority and source combine', () => {
    expect(filterLeads(rows, { ...DEFAULT_QUERY, stage: 'contacted' })).toHaveLength(2);
    expect(filterLeads(rows, { ...DEFAULT_QUERY, activity: 'a-rest' })).toHaveLength(3);
    expect(filterLeads(rows, { ...DEFAULT_QUERY, activity: 'a-rest', priority: 'hot' })).toHaveLength(2);
    expect(filterLeads(rows, { ...DEFAULT_QUERY, source: 'import' })).toHaveLength(1);
    expect(filterLeads(rows, { ...DEFAULT_QUERY, stage: 'contacted', activity: 'a-cafe' })).toHaveLength(1);
  });
  it('chips count within the other filters, not the stage', () => {
    const c = stageCounts(filterExceptStage(rows, { ...DEFAULT_QUERY, activity: 'a-rest', stage: 'visited' }));
    expect(c).toMatchObject({ all: 3, visited: 1, contacted: 1, lost: 1, replied: 0 });
  });
});

describe('sorting', () => {
  const soon = row({ businessName: 'ب', score: 70, next: { id: 't1', title: 'x', kind: 'followup_3', dueAt: new Date(Date.UTC(2026, 9, 2)) } });
  const later = row({ businessName: 'أ', score: 30, next: { id: 't2', title: 'x', kind: 'followup_7', dueAt: new Date(Date.UTC(2026, 9, 9)) } });
  const none = row({ businessName: 'ت', score: null, next: null });
  it('next action: soonest first, leads without one last', () => {
    expect(sortLeads([none, later, soon], 'next', 'asc').map((r) => r.id)).toEqual([soon.id, later.id, none.id]);
    expect(sortLeads([none, later, soon], 'next', 'desc').map((r) => r.id)).toEqual([later.id, soon.id, none.id]);
  });
  it('score: lowest first by default, empty last both ways', () => {
    expect(sortLeads([soon, none, later], 'score', 'asc').map((r) => r.id)).toEqual([later.id, soon.id, none.id]);
    expect(sortLeads([soon, none, later], 'score', 'desc').map((r) => r.id)).toEqual([soon.id, later.id, none.id]);
  });
  it('recent: newest lead first; name uses Arabic order', () => {
    expect(sortLeads([soon, later, none], 'recent', 'desc')[0]?.id).toBe(none.id);
    expect(sortLeads([soon, later, none], 'name', 'asc').map((r) => r.businessName)).toEqual(['أ', 'ب', 'ت']);
  });
  it('does not mutate the input', () => {
    const input = [none, later, soon];
    sortLeads(input, 'next', 'asc');
    expect(input[0]).toBe(none);
  });
});

describe('paging, URL state and counts', () => {
  it('pages of 25 with «عرض 26–47 من 47»', () => {
    const items = Array.from({ length: 47 }, (_, i) => i);
    expect(paginate(items, 2)).toMatchObject({ page: 2, pages: 2, from: 26, to: 47, total: 47 });
    expect(paginate(items, 9).page).toBe(2);
    expect(paginate([], 1)).toMatchObject({ page: 1, pages: 1, from: 0, to: 0 });
  });
  it('round-trips through the URL and drops defaults', () => {
    const q = { ...DEFAULT_QUERY, q: 'ريدان', stage: 'contacted' as const, sort: 'score' as const, dir: 'asc' as const, page: 2 };
    const sp = paramsFromQuery(q);
    expect(sp.toString()).toBe(new URLSearchParams({ q: 'ريدان', stage: 'contacted', sort: 'score', page: '2' }).toString());
    expect(queryFromParams(sp)).toEqual(q);
    expect(queryFromParams(new URLSearchParams('stage=nope&priority=x&sort=bad&page=-3'))).toEqual(DEFAULT_QUERY);
    expect(paramsFromQuery(DEFAULT_QUERY).toString()).toBe('');
  });
  it('counted noun', () => {
    expect([0, 1, 2, 5, 47].map(leadsCountText)).toEqual(['لا عملاء', 'عميل واحد', 'عميلان', '5 عملاء', '47 عميلاً']);
  });
});
