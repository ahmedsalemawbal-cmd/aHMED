import { Toast } from '@/components/ui/Toast';

/** Load failure: an error Toast with «أعد المحاولة» (design-system.md: الحالات). */
export function ErrorRetry({ title, message, onRetry }: { title: string; message?: string; onRetry: () => void }) {
  return <Toast tone="error" title={title} message={message ?? 'تأكد من الشبكة ثم أعد المحاولة.'} action="أعد المحاولة" onAction={onRetry} className="max-w-none" />;
}
