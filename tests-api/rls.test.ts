// RLS against the real Supabase project, through the public API, with two
// real users. User B must not read, change, delete or link to anything of A.
import { createClient, type SupabaseClient } from '@supabase/supabase-js';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import type { Database } from '../src/lib/database.types';
import { loadEnv, required } from './env';

const env = loadEnv();
const url = required(env, 'VITE_SUPABASE_URL');
const key = required(env, 'VITE_SUPABASE_ANON_KEY');

type Db = SupabaseClient<Database>;

function client(): Db {
  return createClient<Database>(url, key, { auth: { persistSession: false, autoRefreshToken: false } });
}

async function signedIn(email: string, password: string): Promise<{ db: Db; id: string }> {
  const db = client();
  const { data, error } = await db.auth.signInWithPassword({ email, password });
  if (error) throw error;
  return { db, id: data.user.id };
}

let A: { db: Db; id: string };
let B: { db: Db; id: string };
let leadId = '';
let visitId = '';
let activityId = '';
const tag = `rls-${Date.now().toString()}`;
let objectPath = '';

beforeAll(async () => {
  A = await signedIn(required(env, 'E2E_USER_A_EMAIL'), required(env, 'E2E_USER_A_PASSWORD'));
  B = await signedIn(required(env, 'E2E_USER_B_EMAIL'), required(env, 'E2E_USER_B_PASSWORD'));

  const act = await A.db.from('activity_types').select('id').eq('name', 'مطعم').single();
  if (act.error) throw act.error;
  activityId = act.data.id;

  const lead = await A.db
    .from('leads')
    .insert({ business_name: tag, activity_type_id: activityId, phone_e164: '966551234567', stage: 'visited' })
    .select('id')
    .single();
  if (lead.error) throw lead.error;
  leadId = lead.data.id;

  const visit = await A.db.from('visits').insert({ lead_id: leadId, key_observation: 'ملاحظة' }).select('id').single();
  if (visit.error) throw visit.error;
  visitId = visit.data.id;

  objectPath = `${A.id}/${visitId}/test.png`;
  const up = await A.db.storage
    .from('visit-media')
    .upload(objectPath, new Blob([new Uint8Array([137, 80, 78, 71])], { type: 'image/png' }));
  if (up.error) throw up.error;
});

afterAll(async () => {
  if (objectPath) await A.db.storage.from('visit-media').remove([objectPath]);
  if (leadId) await A.db.from('leads').delete().eq('id', leadId);
});

describe('RLS: user B against user A', () => {
  const tables = ['profiles', 'activity_types', 'services', 'leads', 'visits', 'visit_media', 'assessments',
    'lead_services', 'message_templates', 'messages', 'tasks', 'stage_history', 'quotes'] as const;

  it.each(tables)('B reads nothing of A in %s', async (table) => {
    const { data, error } = await B.db.from(table).select('id').eq('owner_id', A.id);
    expect(error).toBeNull();
    expect(data).toEqual([]);
  });

  it('A sees its own lead and its stage history', async () => {
    const { data } = await A.db.from('stage_history').select('to_stage').eq('lead_id', leadId);
    expect(data).toEqual([{ to_stage: 'visited' }]);
  });

  it('B cannot update or delete A lead', async () => {
    const upd = await B.db.from('leads').update({ business_name: 'مخترق' }).eq('id', leadId).select('id');
    expect(upd.data).toEqual([]);
    const del = await B.db.from('leads').delete().eq('id', leadId).select('id');
    expect(del.data).toEqual([]);
    const still = await A.db.from('leads').select('business_name').eq('id', leadId).single();
    expect(still.data?.business_name).toBe(tag);
  });

  it('B cannot insert a row owned by A', async () => {
    const { error } = await B.db.from('leads').insert({ owner_id: A.id, business_name: 'باسم غيري' });
    expect(error?.code).toBe('42501');
  });

  it('B cannot link its rows to A lead or A activity', async () => {
    const visit = await B.db.from('visits').insert({ lead_id: leadId });
    expect(visit.error?.code).toBe('23503');
    const lead = await B.db.from('leads').insert({ business_name: 'محل ب', activity_type_id: activityId });
    expect(lead.error?.code).toBe('23503');
  });

  it('B cannot write stage history directly', async () => {
    const { error } = await B.db.from('stage_history').insert({ lead_id: leadId, to_stage: 'won' });
    expect(error).not.toBeNull();
  });

  it('B cannot read, overwrite or upload into A storage folder', async () => {
    const dl = await B.db.storage.from('visit-media').download(objectPath);
    expect(dl.data).toBeNull();
    const signed = await B.db.storage.from('visit-media').createSignedUrl(objectPath, 60);
    expect(signed.data).toBeNull();
    const up = await B.db.storage
      .from('visit-media')
      .upload(`${A.id}/x/evil.png`, new Blob([new Uint8Array([1])], { type: 'image/png' }));
    expect(up.error).not.toBeNull();
  });

  it('anonymous visitors read nothing', async () => {
    const anon = client();
    const { data } = await anon.from('leads').select('id');
    expect(data ?? []).toEqual([]);
    const ins = await anon.from('leads').insert({ business_name: 'anon' });
    expect(ins.error).not.toBeNull();
  });
});
