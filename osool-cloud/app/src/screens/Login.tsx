import { useState } from 'react';
import { supabase } from '../lib/supabase';
import { Button, Field, Problem } from '../ui/kit';

export default function Login() {
  const [email, setEmail] = useState('');
  const [pass, setPass] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setErr('');

    const { error } = await supabase.auth.signInWithPassword({ email, password: pass });

    // رسالة واحدة للبريد الخطأ وللكلمة الخطأ: التفريق بينهما يخبر من
    // يجرّب أيّ البريدين مسجَّل في المدرسة — وهذه بيانات لا تُعطى.
    if (error) { setErr('البريد أو كلمة المرور غير صحيحة.'); setBusy(false); }
  };

  return (
    <main style={{
      minHeight: '100%', display: 'grid', placeItems: 'center',
      padding: 'var(--sch-s5)', background: 'var(--sch-chrome)',
    }}>
      <form onSubmit={submit} style={{
        width: 'min(400px, 100%)', background: 'var(--sch-surface)',
        borderRadius: 'var(--sch-radius-xl)', boxShadow: 'var(--sch-e3)',
        padding: 'var(--sch-s6)',
      }}>
        <div style={{
          width: 54, height: 54, borderRadius: 16, background: 'var(--sch-accent)',
          color: 'var(--sch-on-accent)', display: 'grid', placeItems: 'center',
          fontSize: 28, fontWeight: 700, marginBottom: 'var(--sch-s4)',
        }}>
          أ
        </div>

        <h1 style={{ margin: 0, fontSize: 'var(--sch-t4)', fontWeight: 700 }}>أُصول</h1>
        <p style={{ margin: '6px 0 var(--sch-s5)', color: 'var(--sch-muted)', fontSize: 'var(--sch-t2)' }}>
          نظام إدارة المدرسة
        </p>

        <Field label="البريد الإلكتروني" type="email" autoComplete="username"
               value={email} onChange={(e) => setEmail(e.target.value)} required />
        <Field label="كلمة المرور" type="password" autoComplete="current-password"
               value={pass} onChange={(e) => setPass(e.target.value)} required />

        {err && <div style={{ marginBottom: 'var(--sch-s4)' }}><Problem>{err}</Problem></div>}

        <Button type="submit" wide disabled={busy}>
          {busy ? 'جارٍ الدخول…' : 'دخول'}
        </Button>
      </form>
    </main>
  );
}
