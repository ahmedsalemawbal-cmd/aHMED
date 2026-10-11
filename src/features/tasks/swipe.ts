/**
 * Swipe on a task row (Tasks.dc.html: «اسحب المهمة يساراً للإنجاز، ويميناً
 * للتأجيل»). The hint names screen directions, so these are physical: left
 * completes, right postpones. Pure so the rules are unit-tested.
 */

export type TaskSwipe = 'complete' | 'postpone';

/** A swipe commits past this many pixels, or a third of the row on wide rows. */
export const COMMIT_MIN = 80;

/** How far the row follows the finger, as a share of its width. */
const MAX_SHARE = 0.6;

export function commitDistance(width: number): number {
  return Math.max(COMMIT_MIN, width / 3);
}

/** What a release does: dx < 0 is a move to the left. */
export function swipeIntent(dx: number, width: number): TaskSwipe | null {
  const t = commitDistance(width);
  if (dx <= -t) return 'complete';
  if (dx >= t) return 'postpone';
  return null;
}

/** Where the row is drawn while dragging; it never moves toward an action the row does not offer. */
export function dragOffset(dx: number, width: number, allowed: { complete: boolean; postpone: boolean }): number {
  const max = width * MAX_SHARE;
  if (dx < 0 && !allowed.complete) return 0;
  if (dx > 0 && !allowed.postpone) return 0;
  return Math.max(-max, Math.min(max, dx));
}
