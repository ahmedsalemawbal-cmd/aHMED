import { useEffect, useRef, type KeyboardEvent, type MouseEvent, type TouchEvent } from 'react';

/** How long a finger rests on a card before its stage sheet opens (Pipeline.dc.html: «اضغط مطولاً»). */
export const LONG_PRESS_MS = 500;
/** Moving more than this (a scroll) cancels the press. */
export const LONG_PRESS_SLOP = 10;
/** A click this soon after a long press is that press's own click. */
const CLICK_AFTER_PRESS_MS = 600;

/** The keyboard's own context-menu keys: the Menu key, or Shift+F10. */
export function isMenuKey(e: Pick<KeyboardEvent, 'key' | 'shiftKey'>): boolean {
  return e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10');
}

/**
 * Long press on touch, right-click, and the Menu key all call `onMenu`.
 * After a long press the touch ends without a click (it would land on the
 * sheet's scrim and close it, or open the lead), and the browser's own
 * context menu never shows.
 */
export function useLongPress(onMenu: () => void) {
  const timer = useRef<number | null>(null);
  const origin = useRef<{ x: number; y: number } | null>(null);
  const fired = useRef(false);
  const swallowUntil = useRef(0);

  const cancel = () => {
    if (timer.current !== null) window.clearTimeout(timer.current);
    timer.current = null;
  };
  useEffect(() => cancel, []);

  return {
    onTouchStart: (e: TouchEvent<HTMLElement>) => {
      cancel();
      fired.current = false;
      const t = e.touches[0];
      if (!t || e.touches.length > 1) return;
      origin.current = { x: t.clientX, y: t.clientY };
      timer.current = window.setTimeout(() => {
        timer.current = null;
        fired.current = true;
        onMenu();
      }, LONG_PRESS_MS);
    },
    onTouchMove: (e: TouchEvent<HTMLElement>) => {
      const t = e.touches[0];
      const o = origin.current;
      if (!t || !o) return;
      if (Math.abs(t.clientX - o.x) > LONG_PRESS_SLOP || Math.abs(t.clientY - o.y) > LONG_PRESS_SLOP) cancel();
    },
    onTouchEnd: (e: TouchEvent<HTMLElement>) => {
      cancel();
      if (!fired.current) return;
      fired.current = false;
      swallowUntil.current = performance.now() + CLICK_AFTER_PRESS_MS;
      // no compatibility click after a long press
      if (e.cancelable) e.preventDefault();
    },
    onTouchCancel: cancel,
    onClickCapture: (e: MouseEvent<HTMLElement>) => {
      if (performance.now() >= swallowUntil.current) return;
      swallowUntil.current = 0;
      e.preventDefault();
      e.stopPropagation();
    },
    onContextMenu: (e: MouseEvent<HTMLElement>) => {
      e.preventDefault();
      cancel();
      onMenu();
    },
    onKeyDown: (e: KeyboardEvent<HTMLElement>) => {
      if (!isMenuKey(e)) return;
      e.preventDefault();
      onMenu();
    },
  };
}
