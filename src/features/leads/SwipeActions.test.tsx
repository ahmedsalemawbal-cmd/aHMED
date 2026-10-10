import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SwipeActions } from './SwipeActions';

function setup() {
  const onCard = vi.fn();
  const onWa = vi.fn();
  const onVisit = vi.fn();
  const utils = render(
    <div>
      <SwipeActions
        label="إجراءات سريعة: مطعم ريدان"
        actions={[
          { key: 'wa', label: 'واتساب', icon: 'chat', tone: 'whatsapp', onSelect: onWa },
          { key: 'visit', label: 'زيارة جديدة', icon: 'pin', tone: 'neutral', onSelect: onVisit },
        ]}
      >
        <button type="button" onClick={onCard}>
          مطعم ريدان
        </button>
      </SwipeActions>
      <p>خارج البطاقة</p>
    </div>,
  );
  const root = utils.container.querySelector<HTMLElement>('[data-swipe]');
  if (!root) throw new Error('no swipe root');
  return { root, onCard, onWa, onVisit };
}

function drag(el: HTMLElement, dx: number, dy = 0) {
  fireEvent.touchStart(el, { touches: [{ clientX: 100, clientY: 100 }] });
  for (let i = 1; i <= 5; i++) fireEvent.touchMove(el, { touches: [{ clientX: 100 + (dx * i) / 5, clientY: 100 + (dy * i) / 5 }] });
  fireEvent.touchEnd(el, { touches: [] });
}

describe('SwipeActions', () => {
  beforeEach(() => {
    document.documentElement.setAttribute('dir', 'rtl');
  });
  afterEach(() => {
    document.documentElement.removeAttribute('dir');
  });

  it('closed: actions are hidden from assistive tech and out of the tab order', () => {
    const { root } = setup();
    expect(root.dataset.swipe).toBe('closed');
    expect(screen.queryByRole('button', { name: 'واتساب' })).toBeNull();
    const group = root.querySelector('[role="group"]');
    expect(group).toHaveAttribute('aria-hidden', 'true');
    expect(group).toHaveAttribute('inert');
  });

  it('a swipe toward the right (RTL) reveals both actions; the card is not opened', () => {
    const { root, onCard, onVisit } = setup();
    const card = screen.getByRole('button', { name: 'مطعم ريدان' });
    drag(card, 120);
    fireEvent.click(card);
    expect(onCard).not.toHaveBeenCalled();
    expect(root.dataset.swipe).toBe('open');
    expect(screen.getByRole('group', { name: 'إجراءات سريعة: مطعم ريدان' })).not.toHaveAttribute('aria-hidden');
    fireEvent.click(screen.getByRole('button', { name: 'زيارة جديدة' }));
    expect(onVisit).toHaveBeenCalledTimes(1);
    expect(root.dataset.swipe).toBe('closed');
  });

  it('a short swipe springs back; a vertical move never opens', () => {
    const { root } = setup();
    const card = screen.getByRole('button', { name: 'مطعم ريدان' });
    drag(card, 30);
    expect(root.dataset.swipe).toBe('closed');
    drag(card, 120, 200);
    expect(root.dataset.swipe).toBe('closed');
  });

  it('only the click that ends a drag is swallowed; a later keyboard click opens the lead', () => {
    const clock = vi.spyOn(performance, 'now').mockReturnValue(1000);
    const { root, onCard } = setup();
    const card = screen.getByRole('button', { name: 'مطعم ريدان' });
    drag(card, 30);
    expect(root.dataset.swipe).toBe('closed');
    fireEvent.click(card);
    expect(onCard).not.toHaveBeenCalled();
    // a drag the browser sends no click for: «Enter» on the card seconds later still opens it
    drag(card, 30);
    clock.mockReturnValue(4000);
    fireEvent.click(card);
    expect(onCard).toHaveBeenCalledTimes(1);
    clock.mockRestore();
  });

  it('a tap elsewhere or on the card closes it; swiping back closes it', () => {
    const { root, onCard } = setup();
    const card = screen.getByRole('button', { name: 'مطعم ريدان' });
    drag(card, 120);
    expect(root.dataset.swipe).toBe('open');
    fireEvent.pointerDown(screen.getByText('خارج البطاقة'));
    expect(root.dataset.swipe).toBe('closed');

    drag(card, 120);
    fireEvent.touchStart(card, { touches: [{ clientX: 100, clientY: 100 }] });
    fireEvent.touchEnd(card, { touches: [] });
    fireEvent.click(card);
    expect(onCard).not.toHaveBeenCalled();
    expect(root.dataset.swipe).toBe('closed');

    drag(card, 120);
    drag(card, -120);
    expect(root.dataset.swipe).toBe('closed');
    // a plain tap opens the lead again
    fireEvent.touchStart(card, { touches: [{ clientX: 100, clientY: 100 }] });
    fireEvent.touchEnd(card, { touches: [] });
    fireEvent.click(card);
    expect(onCard).toHaveBeenCalledTimes(1);
  });
});
