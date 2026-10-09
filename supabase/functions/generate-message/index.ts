// ميداني · generate-message
// Reads the lead under the caller's RLS, asks the AI provider for a draft in
// three tones, checks it against the message rules, saves the draft in
// `messages`, and falls back to the ready template on any failure.
// Secrets (Supabase → Edge Functions → Secrets), never sent to the browser:
//   AI_PROVIDER  'anthropic' (the only adapter so far; anything else → template)
//   AI_API_KEY   provider key
//   AI_MODEL     optional, default claude-opus-5-5
import Anthropic from 'npm:@anthropic-ai/sdk@0.132.0';
import { createClient, type SupabaseClient } from 'npm:@supabase/supabase-js@2.117.3';
import {
  buildUserPrompt,
  DEFAULT_TEMPLATES,
  DRAFTS_SCHEMA,
  parseDrafts,
  SYSTEM_PROMPT,
  templateDrafts,
  TONES,
  validateDraft,
  type Drafts,
  type MessageFacts,
  type MessageKind,
  type Tone,
} from '../_shared/message.ts';

const CORS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
};

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { ...CORS, 'Content-Type': 'application/json' } });
}

const KINDS: MessageKind[] = ['first', 'followup_3', 'followup_7', 'custom'];
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

interface Body {
  lead_id: string;
  kind: MessageKind;
  tone: Tone | null;
  task_id: string | null;
}

function parseBody(raw: unknown): Body | null {
  if (!raw || typeof raw !== 'object') return null;
  const r = raw as Record<string, unknown>;
  if (typeof r.lead_id !== 'string' || !UUID.test(r.lead_id)) return null;
  const kind = KINDS.find((k) => k === r.kind) ?? 'first';
  const tone = TONES.find((t) => t === r.tone) ?? null;
  const task = typeof r.task_id === 'string' && UUID.test(r.task_id) ? r.task_id : null;
  return { lead_id: r.lead_id, kind, tone, task_id: task };
}

interface LeadRow {
  business_name: string;
  contact_name: string | null;
  contact_role: 'owner' | 'manager' | 'employee' | null;
  activity_type_id: string | null;
  activity: { name: string; checklist: { general?: { id: string; label: string; weakness?: string }[] } } | null;
}

const ROLES = ['owner', 'manager', 'employee'] as const;

/** Runtime check of the lead row (the function's client is untyped). */
function parseLead(raw: unknown): LeadRow | null {
  if (!raw || typeof raw !== 'object') return null;
  const r = raw as Record<string, unknown>;
  if (typeof r.business_name !== 'string') return null;
  const actRaw = Array.isArray(r.activity) ? (r.activity[0] as unknown) : r.activity;
  let activity: LeadRow['activity'] = null;
  if (actRaw && typeof actRaw === 'object') {
    const a = actRaw as Record<string, unknown>;
    const checklist = (a.checklist && typeof a.checklist === 'object' ? a.checklist : {}) as Record<string, unknown>;
    const general = Array.isArray(checklist.general)
      ? checklist.general.flatMap((g: unknown) => {
          if (!g || typeof g !== 'object') return [];
          const it = g as Record<string, unknown>;
          if (typeof it.id !== 'string' || typeof it.label !== 'string') return [];
          return [{ id: it.id, label: it.label, weakness: typeof it.weakness === 'string' ? it.weakness : undefined }];
        })
      : [];
    activity = { name: typeof a.name === 'string' ? a.name : '', checklist: { general } };
  }
  return {
    business_name: r.business_name,
    contact_name: typeof r.contact_name === 'string' ? r.contact_name : null,
    contact_role: ROLES.find((x) => x === r.contact_role) ?? null,
    activity_type_id: typeof r.activity_type_id === 'string' ? r.activity_type_id : null,
    activity,
  };
}

interface Loaded {
  facts: MessageFacts;
  defaultTone: Tone;
  template: { id: string; body: string } | null;
}

async function load(db: SupabaseClient, b: Body): Promise<Loaded | null> {
  const [lead, profile, visit, assessment, services] = await Promise.all([
    db.from('leads').select('id, business_name, contact_name, contact_role, activity_type_id, activity:activity_types(name, checklist)').eq('id', b.lead_id).maybeSingle(),
    db.from('profiles').select('full_name, signature, default_tone').maybeSingle(),
    db.from('visits').select('key_observation').eq('lead_id', b.lead_id).order('visited_at', { ascending: false }).limit(1).maybeSingle(),
    db.from('assessments').select('weaknesses').eq('lead_id', b.lead_id).order('created_at', { ascending: false }).limit(1).maybeSingle(),
    db.from('lead_services').select('service:services(name)').eq('lead_id', b.lead_id).eq('status', 'suggested').order('created_at').limit(2),
  ]);
  if (lead.error || !lead.data) return null;
  const l = parseLead(lead.data);
  if (!l) return null;
  const general = l.activity?.checklist?.general ?? [];
  const weakIds = Array.isArray(assessment.data?.weaknesses) ? (assessment.data.weaknesses as unknown[]).filter((x): x is string => typeof x === 'string') : [];
  const weaknesses = weakIds.slice(0, 2).map((id) => {
    const item = general.find((g) => g.id === id);
    return item?.weakness ?? item?.label ?? id;
  });
  const svcNames = (services.data ?? []).flatMap((s: { service: { name: string } | { name: string }[] | null }) => {
    const v = Array.isArray(s.service) ? s.service[0] : s.service;
    return v ? [v.name] : [];
  });

  // template: same kind, preferring one for this activity
  const kindForTemplate = b.kind === 'custom' ? 'first' : b.kind;
  const tpl = await db.from('message_templates').select('id, body, activity_type_id').eq('kind', kindForTemplate);
  const rows = (tpl.data ?? []) as { id: string; body: string; activity_type_id: string | null }[];
  const template = rows.find((t) => t.activity_type_id && t.activity_type_id === l.activity_type_id) ?? rows.find((t) => !t.activity_type_id) ?? null;

  const p = profile.data as { full_name: string; signature: string | null; default_tone: string } | null;
  const defaultTone = TONES.find((t) => t === p?.default_tone) ?? 'friendly';
  return {
    defaultTone,
    template: template ? { id: template.id, body: template.body } : null,
    facts: {
      senderName: (p?.full_name ?? '').trim().split(/\s+/)[0] ?? '',
      signature: p?.signature ?? null,
      businessName: l.business_name,
      activity: l.activity?.name ?? '',
      contactName: (l.contact_name ?? '').trim(),
      contactRole: l.contact_role,
      observation: (visit.data?.key_observation as string | null) ?? '',
      weaknesses,
      services: svcNames,
      templateBody: template?.body ?? null,
    },
  };
}

async function generateWithAnthropic(apiKey: string, model: string, facts: MessageFacts, kind: MessageKind): Promise<Drafts> {
  const client = new Anthropic({ apiKey, timeout: 25_000, maxRetries: 1 });
  const response = await client.beta.messages.create({
    model,
    max_tokens: 8000,
    betas: ['server-side-fallback-2026-07-01'],
    fallbacks: 'default',
    output_config: { effort: 'low', format: { type: 'json_schema', schema: DRAFTS_SCHEMA } },
    system: SYSTEM_PROMPT,
    messages: [{ role: 'user', content: buildUserPrompt(facts, kind) }],
  });
  if (response.stop_reason === 'refusal') throw new Error('refusal');
  const text = response.content.find((b) => b.type === 'text');
  if (!text || text.type !== 'text') throw new Error('no text');
  const drafts = parseDrafts(JSON.parse(text.text) as unknown);
  if (!drafts) throw new Error('bad shape');
  return drafts;
}

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') return new Response('ok', { headers: CORS });
  if (req.method !== 'POST') return json(405, { error: 'method_not_allowed' });

  const authHeader = req.headers.get('Authorization');
  const url = Deno.env.get('SUPABASE_URL');
  const anon = Deno.env.get('SUPABASE_ANON_KEY');
  if (!authHeader || !url || !anon) return json(401, { error: 'unauthorized' });
  const db = createClient(url, anon, { global: { headers: { Authorization: authHeader } }, auth: { persistSession: false } });
  const { data: userData, error: userError } = await db.auth.getUser();
  if (userError || !userData.user) return json(401, { error: 'unauthorized' });

  let body: Body | null = null;
  try {
    body = parseBody(await req.json());
  } catch {
    body = null;
  }
  if (!body) return json(400, { error: 'bad_request' });

  const loaded = await load(db, body);
  if (!loaded) return json(404, { error: 'lead_not_found' });

  const tone = body.tone ?? loaded.defaultTone;
  let drafts: Drafts | null = null;
  let generatedBy: 'ai' | 'fallback' = 'fallback';
  let reason = '';
  const provider = Deno.env.get('AI_PROVIDER') ?? '';
  const apiKey = Deno.env.get('AI_API_KEY') ?? '';
  if (provider === 'anthropic' && apiKey) {
    try {
      const d = await generateWithAnthropic(apiKey, Deno.env.get('AI_MODEL') || 'claude-opus-5-5', loaded.facts, body.kind);
      const problems = validateDraft(d[tone], body.kind, tone);
      if (problems.length === 0) {
        drafts = d;
        generatedBy = 'ai';
      } else {
        reason = `rules: ${problems.join('، ')}`;
      }
    } catch (err) {
      reason = err instanceof Error ? err.message : 'error';
    }
  } else {
    reason = 'no_provider';
  }
  if (!drafts) {
    const base = loaded.template?.body ?? DEFAULT_TEMPLATES[body.kind === 'custom' ? 'first' : body.kind];
    drafts = templateDrafts(base, loaded.facts);
  }

  // one open draft per lead + kind + task: update it, or insert a new one
  let existing = db.from('messages').select('id').eq('lead_id', body.lead_id).eq('kind', body.kind).eq('status', 'draft');
  existing = body.task_id ? existing.eq('task_id', body.task_id) : existing.is('task_id', null);
  const found = await existing.order('created_at', { ascending: false }).limit(1).maybeSingle();
  const row = {
    lead_id: body.lead_id,
    kind: body.kind,
    body: drafts[tone],
    status: 'draft',
    tone,
    generated_by: generatedBy,
    template_id: generatedBy === 'fallback' ? (loaded.template?.id ?? null) : null,
    task_id: body.task_id,
  };
  const saved = found.data
    ? await db.from('messages').update(row).eq('id', (found.data as { id: string }).id).select('id').single()
    : await db.from('messages').insert(row).select('id').single();
  if (saved.error) return json(500, { error: 'save_failed' });

  if (reason) console.log(JSON.stringify({ fn: 'generate-message', fallback: reason }));
  return json(200, {
    message_id: (saved.data as { id: string }).id,
    tone,
    drafts,
    generated_by: generatedBy,
    observation: loaded.facts.observation,
    weaknesses: loaded.facts.weaknesses,
  });
});
