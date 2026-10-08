import { useState, type SyntheticEvent } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '@/auth/AuthContext';
import { Button } from '@/components/ui/Button';
import { TextField } from '@/components/ui/TextField';
import { LogoMark } from '@/components/app/LogoMark';
import { OfflineBanner } from '@/components/app/OfflineBanner';
import { env } from '@/lib/env';

interface FromState {
  from?: string;
}

export default function Login() {
  const { session, signIn } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = (location.state as FromState | null)?.from ?? '/';
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [emailError, setEmailError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (session) return <Navigate to={from} replace />;

  async function onSubmit(e: SyntheticEvent) {
    e.preventDefault();
    setError(null);
    setEmailError(null);
    if (!/^\S+@\S+\.\S+$/.test(email.trim())) {
      setEmailError('البريد غير مكتمل. اكتبه بصيغة name@example.com');
      return;
    }
    if (!password) {
      setError('كلمة المرور فارغة. اكتبها ثم اضغط دخول.');
      return;
    }
    if (!env.configured) {
      setError('إعداد الاتصال ناقص. أضف VITE_SUPABASE_URL و VITE_SUPABASE_ANON_KEY ثم أعد التشغيل.');
      return;
    }
    setBusy(true);
    const res = await signIn(email, password);
    setBusy(false);
    if (res.error) {
      setError(res.error);
      return;
    }
    void navigate(from, { replace: true });
  }

  return (
    <div className="flex min-h-dvh flex-col bg-surface text-ink">
      <OfflineBanner />
      <main className="mx-auto flex w-full max-w-md flex-1 flex-col justify-between px-6 pb-[calc(var(--space-8)+var(--space-2))] pt-[calc(var(--space-12)+var(--space-6))]">
        <div className="flex flex-col gap-[calc(var(--space-8)+var(--space-2))]">
          <div className="flex flex-col gap-4">
            <LogoMark />
            <div className="flex flex-col gap-1">
              <h1 className="m-0 text-display font-bold">ميداني</h1>
              <p className="m-0 text-body text-ink-muted">سجّل المحل، أرسل الرسالة، وتابع حتى الإغلاق.</p>
            </div>
          </div>
          <form className="flex flex-col gap-5" onSubmit={(e) => void onSubmit(e)} noValidate>
            <TextField
              label="البريد الإلكتروني"
              type="email"
              inputMode="email"
              ltr
              autoComplete="email"
              name="email"
              value={email}
              error={emailError ?? undefined}
              onChange={(e) => {
                setEmail(e.target.value);
              }}
            />
            <TextField
              label="كلمة المرور"
              type="password"
              autoComplete="current-password"
              name="password"
              value={password}
              error={error ?? undefined}
              onChange={(e) => {
                setPassword(e.target.value);
              }}
            />
            <Button type="submit" variant="primary" block loading={busy} className="mt-2">
              دخول
            </Button>
          </form>
        </div>
        <p className="m-0 mt-8 text-center text-label-sm font-normal text-ink-muted">حساب واحد فقط. لا يوجد تسجيل جديد من التطبيق.</p>
      </main>
    </div>
  );
}
