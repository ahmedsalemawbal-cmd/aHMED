import { useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/lib/supabase';
import type { Json } from '@/lib/database.types';

export class OfflineError extends Error {
  constructor() {
    super('offline');
  }
}

export interface CreateResult {
  leadId: string;
  visitId: string;
  mediaFailed: number;
}

function parseCreateResult(data: Json): { lead_id: string; visit_id: string } {
  if (data && typeof data === 'object' && !Array.isArray(data) && typeof data.lead_id === 'string' && typeof data.visit_id === 'string') {
    return { lead_id: data.lead_id, visit_id: data.visit_id };
  }
  throw new Error('استجابة غير متوقعة من الخادم');
}

function kindOf(file: File): 'photo' | 'video' | 'audio' {
  if (file.type.startsWith('video/')) return 'video';
  if (file.type.startsWith('audio/')) return 'audio';
  return 'photo';
}

function extOf(file: File): string {
  const fromName = /\.([a-z0-9]{2,5})$/i.exec(file.name)?.[1];
  return (fromName ?? file.type.split('/')[1] ?? 'bin').toLowerCase();
}

/** Saves the lead in one transaction, then uploads the visit media to the private bucket. */
export async function createLead(payload: { [key: string]: Json }, media: File[]): Promise<CreateResult> {
  if (!navigator.onLine) throw new OfflineError();
  const { data, error } = await supabase.rpc('create_lead_from_visit', { p: payload });
  if (error) throw new Error(error.message);
  const r = parseCreateResult(data);
  const { data: userData } = await supabase.auth.getSession();
  const uid = userData.session?.user.id;
  let mediaFailed = 0;
  if (uid) {
    for (const file of media) {
      const path = `${uid}/${r.visit_id}/${crypto.randomUUID()}.${extOf(file)}`;
      const up = await supabase.storage.from('visit-media').upload(path, file, { contentType: file.type || undefined, upsert: false });
      if (up.error) {
        mediaFailed++;
        continue;
      }
      const row = await supabase.from('visit_media').insert({ visit_id: r.visit_id, storage_path: path, kind: kindOf(file) });
      if (row.error) mediaFailed++;
    }
  }
  return { leadId: r.lead_id, visitId: r.visit_id, mediaFailed };
}

export function useCreateLead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ payload, media }: { payload: { [key: string]: Json }; media: File[] }) => createLead(payload, media),
    onSuccess: () => {
      void qc.invalidateQueries();
    },
  });
}
