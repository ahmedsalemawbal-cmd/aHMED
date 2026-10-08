import { useAuth } from '@/auth/AuthContext';
import { Button } from '@/components/ui/Button';
import { OfflineBanner } from '@/components/app/OfflineBanner';

/** Placeholder until phase 1b builds the Today screen. */
export default function Home() {
  const { signOut } = useAuth();
  return (
    <div className="min-h-dvh bg-surface text-ink">
      <OfflineBanner />
      <main className="mx-auto flex max-w-md flex-col gap-6 px-4 py-12">
        <h1 className="m-0 text-title-1 font-bold">اليوم</h1>
        <p className="m-0 text-body text-ink-muted">تم الدخول. شاشة اليوم تأتي في المرحلة التالية.</p>
        <Button
          variant="danger"
          block
          onClick={() => {
            void signOut();
          }}
        >
          تسجيل الخروج
        </Button>
      </main>
    </div>
  );
}
