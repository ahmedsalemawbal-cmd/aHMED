import type { QueryClient } from '@tanstack/react-query';

/**
 * After any write or realtime change: refetch what is on screen, except the
 * open message editor, which keeps the user's text and reloads on its next visit.
 */
export function refreshAll(qc: QueryClient): void {
  void qc.invalidateQueries({ queryKey: ['message-context'], refetchType: 'none' });
  void qc.invalidateQueries({ predicate: (q) => q.queryKey[0] !== 'message-context' });
}
