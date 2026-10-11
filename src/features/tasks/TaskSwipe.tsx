import { useRef, useState, type CSSProperties, type MouseEvent, type ReactNode, type TouchEvent } from 'react';
import { Icon } from '@/components/ui/Icon';
import { CLICK_AFTER_DRAG_MS, lockAxis, type Axis } from '@/features/leads/swipe';
import { dragOffset, swipeIntent } from './swipe';

interface Gesture {
  x0: number;
  y0: number;
  width: number;
  axis: Axis | null;
}

/**
 * Touch swipe on a task row: left completes, right opens «أجّل». The row
 * follows the finger over a coloured strip naming the action, then springs
 * back. Vertical scrolling stays native (touch-action: pan-y), and both
 * actions also have visible controls in the row (the checkbox and «أجّل»).
 */
export function TaskSwipe({
  onComplete,
  onPostpone,
  surface,
  children,
}: {
  /** absent: the row cannot be completed by swipe (it is done already) */
  onComplete?: () => void;
  onPostpone?: () => void;
  /** the row's own background, so the strip only shows where the row has moved away */
  surface: string;
  children: ReactNode;
}) {
  const [dx, setDx] = useState(0);
  const [dragging, setDragging] = useState(false);
  const gesture = useRef<Gesture | null>(null);
  const endedAt = useRef<number | null>(null);
  const allowed = { complete: Boolean(onComplete), postpone: Boolean(onPostpone) };

  if (!allowed.complete && !allowed.postpone) return <>{children}</>;

  const onTouchStart = (e: TouchEvent<HTMLDivElement>) => {
    endedAt.current = null;
    const t = e.touches[0];
    if (!t || e.touches.length > 1) {
      gesture.current = null;
      return;
    }
    gesture.current = { x0: t.clientX, y0: t.clientY, width: e.currentTarget.getBoundingClientRect().width, axis: null };
  };

  const onTouchMove = (e: TouchEvent<HTMLDivElement>) => {
    const g = gesture.current;
    const t = e.touches[0];
    if (!g || !t) return;
    const moved = t.clientX - g.x0;
    if (g.axis === null) {
      g.axis = lockAxis(moved, t.clientY - g.y0);
      if (g.axis === 'x') setDragging(true);
    }
    if (g.axis === 'x') setDx(dragOffset(moved, g.width, allowed));
  };

  const onTouchEnd = () => {
    const g = gesture.current;
    gesture.current = null;
    if (!g || g.axis !== 'x') return;
    endedAt.current = performance.now();
    setDragging(false);
    const intent = swipeIntent(dx, g.width);
    setDx(0);
    if (intent === 'complete') onComplete?.();
    if (intent === 'postpone') onPostpone?.();
  };

  const onClickCapture = (e: MouseEvent<HTMLDivElement>) => {
    const ended = endedAt.current;
    endedAt.current = null;
    // the click that ends a swipe must not tick the box or open the lead
    if (ended !== null && performance.now() - ended < CLICK_AFTER_DRAG_MS) {
      e.preventDefault();
      e.stopPropagation();
    }
  };

  const shift = { transform: `translateX(${dx.toString()}px)` } as CSSProperties;
  // moving left uncovers the strip on the right (the start in RTL), and the other way round
  const strip = dx < 0 ? 'complete' : dx > 0 ? 'postpone' : null;

  return (
    <div
      className="relative touch-pan-y overflow-x-clip"
      data-swipe={strip ?? 'closed'}
      onTouchStart={onTouchStart}
      onTouchMove={onTouchMove}
      onTouchEnd={onTouchEnd}
      onTouchCancel={onTouchEnd}
      onPointerDownCapture={() => {
        endedAt.current = null;
      }}
      onClickCapture={onClickCapture}
    >
      {strip ? (
        <div
          aria-hidden="true"
          className={`absolute inset-0 flex items-center px-5 text-label font-bold ${strip === 'complete' ? 'justify-start bg-success text-surface-raised' : 'justify-end bg-surface-sunken text-ink'}`}
        >
          <span className="flex items-center gap-2">
            <Icon name={strip === 'complete' ? 'check' : 'clock'} size={20} />
            {strip === 'complete' ? 'إنجاز' : 'أجّل'}
          </span>
        </div>
      ) : null}
      <div style={shift} className={`relative z-[1] ${surface} ${dragging ? '' : 'transition-transform duration-200 ease-out motion-reduce:transition-none'}`}>
        {children}
      </div>
    </div>
  );
}
