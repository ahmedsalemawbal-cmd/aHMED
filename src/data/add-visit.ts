import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { isStage, type Stage } from '@/components/ui/stages';
import type { Json } from '@/lib/database.types';
import type { Answer } from '@/lib/scoring';
import { supabase } from '@/lib/supabase';
import { OfflineError } from './create-lead';
import { refreshAll } from './refresh';

/* --------------------------------------------------- the last assessment */

export interface LastAssessment {
  answers: Record<string, Answer>;
  score: number;
}

/** assessments.answers as stored; anything but yes / partial / no is dropped. */
export function parseAnswers(json: Json): Record<string, Answer> {
  if (!json || typeof json !== 'object' || Array.isArray(json)) return {};
  const out: Record<string, Answer> = {};
  for (const [id, v] of Object.entries(json)) {
    if (v === 'yes' || v === 'partial' || v === 'no') out[id] = v;
  }
  return out;
}

/** The answers of the latest assessment, so a new visit starts from them. */
export async function fetchLastAssessment(leadId: string): Promise<LastAssessment | null> {
  const { data, error } = await supabase.from('assessments').select('answers, score, created_at').eq('lead_id', leadId).order('created_at', { ascending: false }).limit(1);
  if (error) throw new Error(error.message);
  const row = data[0];
  return row ? { answers: parseAnswers(row.answers), score: row.score } : null;
}

export function useLastAssessment(leadId: string, enabled = true) {
  return useQuery({ queryKey: ['last-assessment', leadId], queryFn: () => fetchLastAssessment(leadId), enabled });
}

/* ------------------------------------------------------------- the save */

export interface AddVisitResult {
  leadId: string;
  visitId: string;
  /** the lead's score after the visit */
  score: number | null;
  stageBefore: Stage | null;
  /** true when this visit id was already saved (a retry): nothing was added */
  existing: boolean;
  /** files that could not be uploaded */
  mediaFailed: number;
}

/** The add_visit RPC answer; anything else is an unexpected server reply. */
export function parseAddVisitResult(data: Json): Omit<AddVisitResult, 'mediaFailed'> {
  if (data && typeof data === 'object' && !Array.isArray(data) && typeof data.lead_id === 'string' && typeof data.visit_id === 'string') {
    const before = data.stage_before;
    return {
      leadId: data.lead_id,
      visitId: data.visit_id,
      score: typeof data.score === 'number' ? data.score : null,
      stageBefore: typeof before === 'string' && isStage(before) ? before : null,
      existing: data.existing === true,
    };
  }
  throw new Error('استجابة غير متوقعة من الخادم');
}

export function mediaKind(file: File): 'photo' | 'video' | 'audio' {
  if (file.type.startsWith('video/')) return 'video';
  if (file.type.startsWith('audio/')) return 'audio';
  return 'photo';
}

function mediaExt(file: File): string {
  const fromName = /\.([a-z0-9]{2,5})$/i.exec(file.name)?.[1];
  return (fromName ?? file.type.split('/')[1] ?? 'bin').toLowerCase();
}

/** Private bucket path (PLAN.md 1.2): {owner_id}/{visit_id}/{file}. */
export function mediaPath(ownerId: string, visitId: string, file: File, name: string): string {
  return `${ownerId}/${visitId}/${name}.${mediaExt(file)}`;
}

/** Uploads the visit's photos and videos; returns how many failed. */
async function uploadMedia(visitId: string, media: File[]): Promise<number> {
  if (!media.length) return 0;
  const { data } = await supabase.auth.getSession();
  const uid = data.session?.user.id;
  if (!uid) return media.length;
  let failed = 0;
  for (const file of media) {
    const path = mediaPath(uid, visitId, file, crypto.randomUUID());
    const up = await supabase.storage.from('visit-media').upload(path, file, { contentType: file.type || undefined, upsert: false });
    if (up.error) {
      failed++;
      continue;
    }
    const row = await supabase.from('visit_media').insert({ visit_id: visitId, storage_path: path, kind: mediaKind(file) });
    if (row.error) failed++;
  }
  return failed;
}

/**
 * «زيارة جديدة» for a registered lead: visit, assessment, the lead's score,
 * priority, value and services in one transaction (RPC add_visit, migration 10).
 * The visit id comes from the client, so a retry never adds the visit twice.
 */
export async function addVisit(payload: { [key: string]: Json }, media: File[]): Promise<AddVisitResult> {
  if (!navigator.onLine) throw new OfflineError();
  const { data, error } = await supabase.rpc('add_visit', { p: payload });
  if (error) throw new Error(error.message);
  const r = parseAddVisitResult(data);
  const mediaFailed = await uploadMedia(r.visitId, media);
  return { ...r, mediaFailed };
}

export function useAddVisit() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ payload, media }: { payload: { [key: string]: Json }; media: File[] }) => addVisit(payload, media),
    onSuccess: () => {
      refreshAll(qc);
    },
  });
}
