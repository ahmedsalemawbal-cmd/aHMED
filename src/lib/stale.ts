import type { Stage } from '@/components/ui/stages';
import { calendarDaysBetween } from './dates';

/** A card is stale after more than this many days without moving (brief.md §7). */
export const STALE_AFTER_DAYS = 7;

/** Stages where a deal is still moving; «لم يُزر»، «تم الإغلاق» and «خسارة» never go stale. */
const OPEN_STAGES: ReadonlySet<Stage> = new Set<Stage>(['visited', 'contacted', 'replied', 'meeting', 'proposal']);

/** Calendar days (Riyadh) since the lead entered its stage; never negative. */
export function daysInStage(stageChangedAt: Date, now: Date): number {
  return Math.max(0, calendarDaysBetween(stageChangedAt, now));
}

/** More than 7 days without movement in an open stage: the card gets the warning border. */
export function isStale(stage: Stage, days: number): boolean {
  return OPEN_STAGES.has(stage) && days > STALE_AFTER_DAYS;
}
