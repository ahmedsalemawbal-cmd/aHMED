import { useEffect, useRef, useState, type CSSProperties, type MouseEvent, type ReactNode, type TouchEvent } from 'react';
import { Icon } from '@/components/ui/Icon';
import type { IconName } from '@/components/ui/icons';
import { ACTION_WIDTH, CLICK_AFTER_DRAG_MS, dragReveal, lockAxis, settle, type Axis } from './swipe';

export interface SwipeAction {
  key: string;
  label: string;
  icon: IconName;
  tone: 'whatsapp' | 'neutral';
  onSelect: () => void;
}

interface Gesture {
  x0: number;
  y0: number;
  base: number;
  axis: Axis | null;
  rtl: boolean;
}

const TONE: Record<SwipeAction['tone'], string> = {
  whatsapp: 'bg-whatsapp text-on-whatsapp',
  neutral: 'bg-surface-sunken text-ink border border-line-strong',
};

/**
 * Touch-only quick actions behind a card: swipe the card toward its leading
 * edge (right in RTL) to reveal them at the trailing edge. Vertical scrolling
 * stays native (touch-action: pan-y). While closed the actions are hidden,
 * inert and out of the tab order; a tap elsewhere or a swipe back closes them.
 */
export function SwipeActions({ actions, label, children }: { actions: SwipeAction[]; label: string; children: ReactNode }) {
  const max = actions.length * ACTION_WIDTH;
  const [dragged, setReveal] = useState(0);
  // never past the actions there are now (a lead set to «لا تتواصل» loses «واتساب»)
  const reveal = Math.min(dragged, max);
  const [dragging, setDragging] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const actionsRef = useRef<HTMLDivElement>(null);
  const gesture = useRef<Gesture | null>(null);
  /** when the last sideways drag ended, until the next press */
  const dragEndedAt = useRef<number | null>(null);
  const open = !dragging && reveal >= max && max > 0;

  // A tap anywhere else closes the open actions.
  useEffect(() => {
    if (reveal === 0 || dragging) return;
    const onDown = (e: PointerEvent) => {
      if (e.target instanceof Node && rootRef.current?.contains(e.target)) return;
      setReveal(0);
    };
    document.addEventListener('pointerdown', onDown, true);
    return () => {
      document.removeEventListener('pointerdown', onDown, true);
    };
  }, [reveal, dragging]);

  if (max === 0) return <>{children}</>;

  const onTouchStart = (e: TouchEvent<HTMLDivElement>) => {
    dragEndedAt.current = null;
    const t = e.touches[0];
    if (!t || e.touches.length > 1 || (e.target instanceof Node && actionsRef.current?.contains(e.target))) {
      gesture.current = null;
      return;
    }
    gesture.current = { x0: t.clientX, y0: t.clientY, base: reveal, axis: null, rtl: e.currentTarget.closest('[dir]')?.getAttribute('dir') === 'rtl' };
  };

  const onTouchMove = (e: TouchEvent<HTMLDivElement>) => {
    const g = gesture.current;
    const t = e.touches[0];
    if (!g || !t) return;
    const dx = t.clientX - g.x0;
    if (g.axis === null) {
      g.axis = lockAxis(dx, t.clientY - g.y0);
      if (g.axis === 'x') setDragging(true);
    }
    if (g.axis === 'x') setReveal(dragReveal(g.base, dx, g.rtl, max));
  };

  const onTouchEnd = () => {
    const g = gesture.current;
    gesture.current = null;
    if (!g || g.axis !== 'x') return;
    // the browser may still send a click for this touch: it must not open the lead
    dragEndedAt.current = performance.now();
    setDragging(false);
    setReveal((r) => settle(r, g.base, max));
  };

  const onClickCapture = (e: MouseEvent<HTMLDivElement>) => {
    const onAction = e.target instanceof Node && actionsRef.current?.contains(e.target);
    const ended = dragEndedAt.current;
    dragEndedAt.current = null;
    if (ended !== null && performance.now() - ended < CLICK_AFTER_DRAG_MS) {
      // the click that ends a drag
      e.preventDefault();
      e.stopPropagation();
      return;
    }
    if (reveal > 0 && !onAction) {
      // a tap on the open card closes it instead of opening the lead
      e.preventDefault();
      e.stopPropagation();
      setReveal(0);
    }
  };

  const shift = { '--swipe': `${reveal.toString()}px` } as CSSProperties;

  return (
    <div
      ref={rootRef}
      data-swipe={open ? 'open' : reveal > 0 ? 'moving' : 'closed'}
      className="relative touch-pan-y overflow-x-clip"
      onTouchStart={onTouchStart}
      onTouchMove={onTouchMove}
      onTouchEnd={onTouchEnd}
      onTouchCancel={onTouchEnd}
      onPointerDownCapture={() => {
        // a new press: only the click that ends a drag is swallowed
        dragEndedAt.current = null;
      }}
      onClickCapture={onClickCapture}
    >
      <div
        ref={actionsRef}
        role="group"
        aria-label={label}
        aria-hidden={open ? undefined : true}
        inert={!open}
        className={`absolute inset-y-0 end-0 flex items-stretch gap-2 ps-2 ${reveal > 0 ? 'visible' : 'invisible'}`}
        style={{ width: max }}
      >
        {actions.map((a) => (
          <button
            key={a.key}
            type="button"
            tabIndex={open ? 0 : -1}
            onClick={() => {
              setReveal(0);
              a.onSelect();
            }}
            className={`flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-lg px-1 text-center text-label-sm font-bold ${TONE[a.tone]}`}
          >
            <Icon name={a.icon} size={22} />
            <span>{a.label}</span>
          </button>
        ))}
      </div>
      <div
        style={shift}
        className={`relative z-[1] rtl:translate-x-(--swipe) ltr:-translate-x-(--swipe) ${dragging ? '' : 'transition-transform duration-200 ease-out motion-reduce:transition-none'}`}
      >
        {children}
      </div>
    </div>
  );
}
