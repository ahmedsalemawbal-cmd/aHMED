import { useCallback, useEffect, useRef } from 'react';

const PENDING_KEY = 'maidani.pending-sent';
/** After this long the question «هل أرسلت؟» is stale; the follow-up shows on «اليوم» instead. */
const PENDING_TTL_MS = 12 * 3_600_000;

interface Pending {
  leadId: string;
  at: number;
}

/** Remembers that WhatsApp was opened, so a PWA the phone killed meanwhile still asks on reopen. */
export function setPending(leadId: string): void {
  try {
    localStorage.setItem(PENDING_KEY, JSON.stringify({ leadId, at: Date.now() } satisfies Pending));
  } catch {
    // private mode: the in-memory prompt still works
  }
}

export function clearPending(): void {
  try {
    localStorage.removeItem(PENDING_KEY);
  } catch {
    // nothing to clear
  }
}

export function hasPending(leadId: string, now = Date.now()): boolean {
  try {
    const raw = localStorage.getItem(PENDING_KEY);
    if (!raw) return false;
    const p = JSON.parse(raw) as Partial<Pending>;
    return p.leadId === leadId && typeof p.at === 'number' && now - p.at < PENDING_TTL_MS;
  } catch {
    return false;
  }
}

/**
 * Calls `onReturn` once the user comes back after leaving for WhatsApp
 * (tab hidden or window blurred, then visible and focused again). If the page
 * never lost focus within `fallbackMs` (link opened in place, desktop app
 * handler), it asks anyway so the confirmation is never skipped.
 */
export function useReturnPrompt(onReturn: () => void) {
  const state = useRef<{ left: boolean; timer: ReturnType<typeof setTimeout> | null } | null>(null);
  const latest = useRef(onReturn);
  useEffect(() => {
    latest.current = onReturn;
  });
  const fire = useCallback(() => {
    const s = state.current;
    if (s?.timer) clearTimeout(s.timer);
    state.current = null;
    latest.current();
  }, []);

  useEffect(() => {
    const left = () => {
      if (state.current) state.current.left = true;
    };
    const back = () => {
      if (state.current?.left && document.visibilityState === 'visible') fire();
    };
    const onVisibility = () => {
      if (document.visibilityState === 'hidden') left();
      else back();
    };
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('blur', left);
    window.addEventListener('focus', back);
    return () => {
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('blur', left);
      window.removeEventListener('focus', back);
      if (state.current?.timer) clearTimeout(state.current.timer);
    };
  }, [fire]);

  /** Arms the prompt; with `fallbackMs` it also fires if the user never left. */
  return useCallback((fallbackMs?: number) => {
    if (state.current?.timer) clearTimeout(state.current.timer);
    const s: { left: boolean; timer: ReturnType<typeof setTimeout> | null } = { left: false, timer: null };
    if (fallbackMs !== undefined) {
      s.timer = setTimeout(() => {
        if (state.current === s && !s.left) fire();
      }, fallbackMs);
    }
    state.current = s;
  }, [fire]);
}
