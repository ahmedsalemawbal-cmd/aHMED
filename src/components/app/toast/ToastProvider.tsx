import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Toast } from '@/components/ui/Toast';
import { TOAST_AUTO_DISMISS_MS, ToastContext, type ToastApi, type ToastInput } from './context';

interface Item extends ToastInput {
  id: number;
}

export function ToastProvider({ children, withBottomNav = true }: { children: ReactNode; withBottomNav?: boolean }) {
  const [items, setItems] = useState<Item[]>([]);
  const nextId = useRef(1);
  const timers = useRef(new Map<number, ReturnType<typeof setTimeout>>());

  const dismiss = useCallback((id: number) => {
    const t = timers.current.get(id);
    if (t) clearTimeout(t);
    timers.current.delete(id);
    setItems((list) => list.filter((i) => i.id !== id));
  }, []);

  const show = useCallback(
    (input: ToastInput) => {
      const id = nextId.current++;
      setItems((list) => {
        // keep every error (they stay until acted on) and the newest two others
        const next = [...list, { ...input, id }];
        const others = next.filter((t) => t.tone !== 'error');
        const evicted = new Set(others.slice(0, Math.max(0, others.length - 2)).map((t) => t.id));
        for (const e of evicted) {
          const timer = timers.current.get(e);
          if (timer) clearTimeout(timer);
          timers.current.delete(e);
        }
        return next.filter((t) => !evicted.has(t.id));
      });
      if ((input.tone ?? 'info') !== 'error') {
        timers.current.set(
          id,
          setTimeout(() => {
            dismiss(id);
          }, TOAST_AUTO_DISMISS_MS),
        );
      }
      return id;
    },
    [dismiss],
  );

  useEffect(() => {
    const map = timers.current;
    return () => {
      for (const t of map.values()) clearTimeout(t);
      map.clear();
    };
  }, []);

  const api = useMemo<ToastApi>(() => ({ show, dismiss }), [show, dismiss]);

  return (
    <ToastContext.Provider value={api}>
      {children}
      <div className={`app-toasts${withBottomNav ? '' : ' no-nav'}`} aria-live="polite">
        {items.map((t) => {
          const isError = t.tone === 'error';
          const action = t.action ?? (isError ? 'إغلاق' : undefined);
          return (
            <Toast
              key={t.id}
              tone={t.tone}
              title={t.title}
              message={t.message}
              action={action}
              onAction={() => {
                t.onAction?.();
                dismiss(t.id);
              }}
            />
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}
