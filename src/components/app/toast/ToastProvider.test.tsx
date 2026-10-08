import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useToast } from './context';
import { ToastProvider } from './ToastProvider';

function FireMany() {
  const toast = useToast();
  return (
    <button
      type="button"
      onClick={() => {
        toast.show({ tone: 'error', title: 'تعذّر الحفظ', action: 'أعد المحاولة' });
        for (let i = 0; i < 3; i++) toast.show({ tone: 'success', title: `تم ${i.toString()}` });
      }}
    >
      many
    </button>
  );
}

function Fire({ tone }: { tone: 'success' | 'error' }) {
  const toast = useToast();
  return (
    <button
      type="button"
      onClick={() => {
        toast.show(tone === 'success' ? { tone, title: 'حُفظ العميل' } : { tone, title: 'تعذّر الحفظ', message: 'تأكد من الشبكة.', action: 'أعد المحاولة' });
      }}
    >
      fire
    </button>
  );
}

describe('ToastProvider', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });
  afterEach(() => {
    vi.useRealTimers();
  });

  it('success disappears after 4 seconds', () => {
    render(
      <ToastProvider>
        <Fire tone="success" />
      </ToastProvider>,
    );
    act(() => {
      screen.getByRole('button', { name: 'fire' }).click();
    });
    expect(screen.getByRole('status')).toHaveTextContent('حُفظ العميل');
    act(() => {
      vi.advanceTimersByTime(3999);
    });
    expect(screen.queryByText('حُفظ العميل')).not.toBeNull();
    act(() => {
      vi.advanceTimersByTime(1);
    });
    expect(screen.queryByText('حُفظ العميل')).toBeNull();
  });

  it('an error is never dropped when newer toasts arrive', () => {
    render(
      <ToastProvider>
        <FireMany />
      </ToastProvider>,
    );
    act(() => {
      screen.getByRole('button', { name: 'many' }).click();
    });
    expect(screen.getByRole('alert')).toHaveTextContent('تعذّر الحفظ');
    expect(screen.queryByText('تم 0')).toBeNull();
    expect(screen.getByText('تم 2')).toBeInTheDocument();
  });

  it('error stays until its action is used', () => {
    render(
      <ToastProvider>
        <Fire tone="error" />
      </ToastProvider>,
    );
    act(() => {
      screen.getByRole('button', { name: 'fire' }).click();
    });
    act(() => {
      vi.advanceTimersByTime(60_000);
    });
    expect(screen.getByRole('alert')).toHaveTextContent('تعذّر الحفظ');
    act(() => {
      screen.getByRole('button', { name: 'أعد المحاولة' }).click();
    });
    expect(screen.queryByRole('alert')).toBeNull();
  });
});
