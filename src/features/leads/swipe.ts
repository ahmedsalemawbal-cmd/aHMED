/**
 * Swipe-to-reveal maths for the lead cards (brief.md §5: «إجراءات سريعة
 * بالسحب على البطاقة»). Pure so the gesture rules are unit-tested; the
 * component only feeds touch coordinates in.
 */

/** Movement under this many pixels is still a tap; past it the gesture picks an axis. */
export const AXIS_SLOP = 10;

/** Width each quick action takes behind the card (76px button + 8px gap). */
export const ACTION_WIDTH = 84;

/** A click this soon after a sideways drag is the drag's own tap, not a new one. */
export const CLICK_AFTER_DRAG_MS = 500;

export type Axis = 'x' | 'y';

/** The axis a gesture commits to once it moves past the slop: vertical scroll wins ties. */
export function lockAxis(dx: number, dy: number, slop = AXIS_SLOP): Axis | null {
  const ax = Math.abs(dx);
  const ay = Math.abs(dy);
  if (ax < slop && ay < slop) return null;
  return ax > ay ? 'x' : 'y';
}

/**
 * How much of the actions a drag reveals. The actions sit at the card's
 * trailing edge, so in RTL the card moves right (dx > 0) to open.
 */
export function dragReveal(base: number, dx: number, rtl: boolean, max: number): number {
  const opening = rtl ? dx : -dx;
  return Math.min(max, Math.max(0, base + opening));
}

/**
 * Where a released card rests: fully open or closed. Opening needs a third
 * of the width; once open, dragging back a third closes it.
 */
export function settle(reveal: number, base: number, max: number): number {
  if (max <= 0) return 0;
  const threshold = base >= max ? max * (2 / 3) : max / 3;
  return reveal > threshold ? max : 0;
}
