import { createContext, useContext } from 'react';
import type { Session } from '@supabase/supabase-js';

export interface AuthState {
  /** undefined while the stored session is being restored */
  session: Session | null | undefined;
  signIn: (email: string, password: string) => Promise<{ error: string | null }>;
  signOut: () => Promise<void>;
}

export const AuthContext = createContext<AuthState | null>(null);

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>');
  return ctx;
}
