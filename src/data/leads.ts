import { useQuery } from '@tanstack/react-query';
import { isPriority, isStage } from '@/components/ui/stages';
import type { LeadRow, Role } from '@/lib/leads-list';
import { supabase } from '@/lib/supabase';
import { isTaskKind } from './tasks-meta';

const ROLES: Role[] = ['owner', 'manager', 'employee'];

interface RawLead {
  id: string;
  business_name: string;
  activity_type_id: string | null;
  contact_name: string | null;
  contact_role: string | null;
  phone_e164: string | null;
  stage: string;
  score: number | null;
  priority: string | null;
  source: string;
  expected_value: number | null;
  created_at: string;
  last_contact_at: string | null;
  stage_changed_at: string;
  do_not_contact: boolean;
  wa_consent: boolean;
  activity: { name: string } | { name: string }[] | null;
  tasks: { id: string; title: string; kind: string; due_at: string }[] | null;
}

/** Maps one row; rows with an unknown stage are dropped rather than shown wrong. */
export function toLeadRow(r: RawLead): LeadRow | null {
  if (!isStage(r.stage)) return null;
  const act = Array.isArray(r.activity) ? r.activity[0] : r.activity;
  const next = [...(r.tasks ?? [])].sort((a, b) => a.due_at.localeCompare(b.due_at))[0];
  return {
    id: r.id,
    businessName: r.business_name,
    activityId: r.activity_type_id,
    activity: act?.name ?? '',
    contactName: r.contact_name?.trim() || null,
    contactRole: ROLES.find((x) => x === r.contact_role) ?? null,
    phone: r.phone_e164,
    stage: r.stage,
    score: r.score,
    priority: r.priority && isPriority(r.priority) ? r.priority : null,
    source: r.source === 'import' ? 'import' : 'visit',
    expectedValue: r.expected_value,
    createdAt: new Date(r.created_at),
    lastContactAt: r.last_contact_at ? new Date(r.last_contact_at) : null,
    stageChangedAt: new Date(r.stage_changed_at),
    doNotContact: r.do_not_contact,
    waConsent: r.wa_consent,
    next: next && isTaskKind(next.kind) ? { id: next.id, title: next.title, kind: next.kind, dueAt: new Date(next.due_at) } : null,
  };
}

/**
 * All of the user's leads with their earliest open task. One person has
 * hundreds of leads, so search, filters and sorting run in the browser.
 */
export async function fetchLeads(): Promise<LeadRow[]> {
  const { data, error } = await supabase
    .from('leads')
    .select(
      'id, business_name, activity_type_id, contact_name, contact_role, phone_e164, stage, score, priority, source, expected_value, created_at, last_contact_at, stage_changed_at, do_not_contact, wa_consent, activity:activity_types(name), tasks(id, title, kind, due_at)',
    )
    .is('tasks.done_at', null)
    .is('tasks.cancelled_at', null)
    .order('created_at', { ascending: false });
  if (error) throw new Error(error.message);
  const rows: RawLead[] = data;
  return rows.flatMap((r) => {
    const row = toLeadRow(r);
    return row ? [row] : [];
  });
}

export function useLeads() {
  return useQuery({ queryKey: ['leads'], queryFn: fetchLeads });
}
