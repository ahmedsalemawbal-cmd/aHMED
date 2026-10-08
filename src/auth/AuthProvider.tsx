import { useEffect, useMemo, useState, type ReactNode } from 'react';
import type { Session } from '@supabase/supabase-js';
import { supabase } from '@/lib/supabase';
import { AuthContext, type AuthState } from './AuthContext';
import { signInErrorMessage } from './messages';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null | undefined>(undefined);

  useEffect(() => {
    let active = true;
    void supabase.auth.getSession().then(({ data }) => {
      if (active) setSession(data.session);
    });
    const { data } = supabase.auth.onAuthStateChange((_event, next) => {
      setSession(next);
    });
    return () => {
      active = false;
      data.subscription.unsubscribe();
    };
  }, []);

  const value = useMemo<AuthState>(
    () => ({
      session,
      async signIn(email, password) {
        try {
          const { error } = await supabase.auth.signInWithPassword({ email: email.trim(), password });
          return { error: error ? signInErrorMessage(error) : null };
        } catch (err) {
          return { error: signInErrorMessage(err instanceof Error ? err : { message: String(err) }) };
        }
      },
      async signOut() {
        await supabase.auth.signOut();
      },
    }),
    [session],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
