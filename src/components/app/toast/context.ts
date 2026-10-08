import { createContext, useContext, type ReactNode } from 'react';

export interface ToastInput {
  tone?: 'success' | 'error' | 'info';
  title: ReactNode;
  message?: ReactNode;
  action?: string;
  onAction?: () => void;
}

export interface ToastApi {
  show: (t: ToastInput) => number;
  dismiss: (id: number) => void;
}

export const ToastContext = createContext<ToastApi | null>(null);

export function useToast(): ToastApi {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error('useToast must be used inside <ToastProvider>');
  return ctx;
}

/** Success and info disappear after 4 seconds; errors stay until acted on. */
export const TOAST_AUTO_DISMISS_MS = 4000;
