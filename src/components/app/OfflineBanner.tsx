import { useOnline } from '@/hooks/useOnline';

/** Fixed bar at the top of the screen while offline (design-system.md: الحالات). */
export function OfflineBanner() {
  const online = useOnline();
  if (online) return null;
  return (
    <div
      role="status"
      className="sticky top-0 z-40 border-b border-warning bg-warning-soft px-4 py-2 text-body-sm font-bold text-warning"
    >
      بدون اتصال. سنحفظ ونزامن عند عودة الشبكة.
    </div>
  );
}
