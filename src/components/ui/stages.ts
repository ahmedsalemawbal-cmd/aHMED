export type Stage = 'not_visited' | 'visited' | 'contacted' | 'replied' | 'meeting' | 'proposal' | 'won' | 'lost';
export type Priority = 'hot' | 'warm' | 'cold';

/** Ordered stages with their fixed Arabic names (Maidani.STAGES). Never use synonyms. */
export const STAGES: { id: Stage; label: string }[] = [
  { id: 'not_visited', label: 'لم يُزر' },
  { id: 'visited', label: 'تمت الزيارة' },
  { id: 'contacted', label: 'تم الإرسال' },
  { id: 'replied', label: 'رد' },
  { id: 'meeting', label: 'اجتماع' },
  { id: 'proposal', label: 'عرض سعر' },
  { id: 'won', label: 'تم الإغلاق' },
  { id: 'lost', label: 'خسارة' },
];

export const STAGE_LABEL: Record<Stage, string> = Object.fromEntries(STAGES.map((s) => [s.id, s.label])) as Record<Stage, string>;

export function isStage(value: string): value is Stage {
  return value in STAGE_LABEL;
}

export const PRIORITY: Record<Priority, { label: string; icon: 'flame' | 'sun' | 'snow' }> = {
  hot: { label: 'حار', icon: 'flame' },
  warm: { label: 'دافئ', icon: 'sun' },
  cold: { label: 'بارد', icon: 'snow' },
};

export function isPriority(value: string): value is Priority {
  return value in PRIORITY;
}

/** Score levels shared by ScoreBar and the priority rules: <50, 50–69, ≥70. */
export type ScoreLevel = 'low' | 'mid' | 'high';
export function scoreLevel(score: number): ScoreLevel {
  return score < 50 ? 'low' : score < 70 ? 'mid' : 'high';
}

/** Inside the app a low score reads as a high sales opportunity; on a client report as weak presence. */
export const LEVEL_TEXT: Record<'opportunity' | 'presence', Record<ScoreLevel, string>> = {
  opportunity: { low: 'فرصة عالية', mid: 'فرصة متوسطة', high: 'فرصة منخفضة' },
  presence: { low: 'حضور ضعيف', mid: 'حضور متوسط', high: 'حضور قوي' },
};

export function scoreLevelText(score: number, context: 'opportunity' | 'presence' = 'opportunity'): string {
  return LEVEL_TEXT[context][scoreLevel(score)];
}
