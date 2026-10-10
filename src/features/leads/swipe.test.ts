import { describe, expect, it } from 'vitest';
import { ACTION_WIDTH, dragReveal, lockAxis, settle } from './swipe';

describe('lockAxis: the gesture picks a direction after ~10px', () => {
  it('stays undecided inside the slop (a tap)', () => {
    expect(lockAxis(0, 0)).toBeNull();
    expect(lockAxis(9, -9)).toBeNull();
  });
  it('horizontal when x moves more, vertical otherwise (scroll wins ties)', () => {
    expect(lockAxis(14, 3)).toBe('x');
    expect(lockAxis(-14, 3)).toBe('x');
    expect(lockAxis(3, 14)).toBe('y');
    expect(lockAxis(12, 12)).toBe('y');
  });
});

describe('dragReveal: RTL cards open toward the right', () => {
  const max = 2 * ACTION_WIDTH;
  it('RTL: right opens, left does nothing from closed', () => {
    expect(dragReveal(0, 60, true, max)).toBe(60);
    expect(dragReveal(0, -60, true, max)).toBe(0);
  });
  it('LTR mirrors it', () => {
    expect(dragReveal(0, -60, false, max)).toBe(60);
    expect(dragReveal(0, 60, false, max)).toBe(0);
  });
  it('never past the actions, and back from open', () => {
    expect(dragReveal(0, 500, true, max)).toBe(max);
    expect(dragReveal(max, -40, true, max)).toBe(max - 40);
    expect(dragReveal(max, -500, true, max)).toBe(0);
  });
});

describe('settle: open or closed, nothing in between', () => {
  const max = 168;
  it('opening needs a third', () => {
    expect(settle(50, 0, max)).toBe(0);
    expect(settle(60, 0, max)).toBe(max);
  });
  it('closing needs a third back', () => {
    expect(settle(120, max, max)).toBe(max);
    expect(settle(100, max, max)).toBe(0);
    expect(settle(0, max, max)).toBe(0);
  });
  it('no actions, nothing to open', () => {
    expect(settle(10, 0, 0)).toBe(0);
  });
});
