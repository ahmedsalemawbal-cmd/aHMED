import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { clearPending, hasPending, setPending, useReturnPrompt } from './useReturnPrompt';

function setVisibility(v: 'hidden' | 'visible') {
  Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => v });
  document.dispatchEvent(new Event('visibilitychange'));
}

describe('useReturnPrompt', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });
  afterEach(() => {
    vi.useRealTimers();
    setVisibility('visible');
  });

  it('asks after the user leaves for WhatsApp and comes back', () => {
    const onReturn = vi.fn();
    const { result } = renderHook(() => useReturnPrompt(onReturn));
    act(() => {
      result.current(2500);
    });
    act(() => {
      setVisibility('hidden');
    });
    act(() => {
      vi.advanceTimersByTime(10_000);
    });
    expect(onReturn).not.toHaveBeenCalled(); // still away: no fallback while hidden
    act(() => {
      setVisibility('visible');
    });
    expect(onReturn).toHaveBeenCalledTimes(1);
    act(() => {
      setVisibility('hidden');
      setVisibility('visible');
    });
    expect(onReturn).toHaveBeenCalledTimes(1); // once per arm
  });

  it('blur then focus counts as leaving and coming back (desktop app)', () => {
    const onReturn = vi.fn();
    const { result } = renderHook(() => useReturnPrompt(onReturn));
    act(() => {
      result.current();
      window.dispatchEvent(new Event('blur'));
      window.dispatchEvent(new Event('focus'));
    });
    expect(onReturn).toHaveBeenCalledTimes(1);
  });

  it('falls back to asking when the page never lost focus', () => {
    const onReturn = vi.fn();
    const { result } = renderHook(() => useReturnPrompt(onReturn));
    act(() => {
      result.current(2500);
    });
    act(() => {
      vi.advanceTimersByTime(2499);
    });
    expect(onReturn).not.toHaveBeenCalled();
    act(() => {
      vi.advanceTimersByTime(1);
    });
    expect(onReturn).toHaveBeenCalledTimes(1);
  });

  it('copy arms without a fallback: nothing until the user leaves', () => {
    const onReturn = vi.fn();
    const { result } = renderHook(() => useReturnPrompt(onReturn));
    act(() => {
      result.current();
      vi.advanceTimersByTime(60_000);
    });
    expect(onReturn).not.toHaveBeenCalled();
  });

  it('unarmed visibility changes do nothing', () => {
    const onReturn = vi.fn();
    renderHook(() => useReturnPrompt(onReturn));
    act(() => {
      setVisibility('hidden');
      setVisibility('visible');
    });
    expect(onReturn).not.toHaveBeenCalled();
  });
});

describe('pending «هل أرسلت؟» across reloads', () => {
  afterEach(() => {
    clearPending();
  });
  it('is remembered per lead for 12 hours', () => {
    setPending('l1');
    expect(hasPending('l1')).toBe(true);
    expect(hasPending('l2')).toBe(false);
    expect(hasPending('l1', Date.now() + 13 * 3_600_000)).toBe(false);
    clearPending();
    expect(hasPending('l1')).toBe(false);
  });
});
