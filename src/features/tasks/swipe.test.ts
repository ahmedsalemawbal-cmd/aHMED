import { describe, expect, it } from 'vitest';
import { commitDistance, COMMIT_MIN, dragOffset, swipeIntent } from './swipe';

describe('task row swipe (Tasks.dc.html)', () => {
  it('left completes, right postpones, a short move does nothing', () => {
    expect(swipeIntent(-130, 358)).toBe('complete');
    expect(swipeIntent(130, 358)).toBe('postpone');
    expect(swipeIntent(-100, 358)).toBeNull();
    expect(swipeIntent(40, 358)).toBeNull();
  });

  it('needs a third of the row, never less than the minimum', () => {
    expect(commitDistance(358)).toBeCloseTo(119.33, 1);
    expect(commitDistance(180)).toBe(COMMIT_MIN);
    expect(swipeIntent(-COMMIT_MIN, 180)).toBe('complete');
  });

  it('the row follows the finger up to 60% and not toward a blocked action', () => {
    const both = { complete: true, postpone: true };
    expect(dragOffset(-50, 300, both)).toBe(-50);
    expect(dragOffset(-500, 300, both)).toBe(-180);
    expect(dragOffset(500, 300, both)).toBe(180);
    expect(dragOffset(-50, 300, { complete: false, postpone: true })).toBe(0);
    expect(dragOffset(50, 300, { complete: true, postpone: false })).toBe(0);
  });
});
