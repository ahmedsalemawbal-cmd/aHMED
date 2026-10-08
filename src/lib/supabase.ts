import { createClient } from '@supabase/supabase-js';
import type { Database } from './database.types';
import { env } from './env';

/**
 * Browser client. Only the public URL and anon/publishable key reach the
 * browser; every table is protected by RLS (owner_id = auth.uid()).
 * When env is missing we still create a client against a placeholder so the
 * app renders its error state instead of crashing.
 */
export const supabase = createClient<Database>(env.supabaseUrl || 'http://localhost:54321', env.supabaseAnonKey || 'missing-key', {
  auth: { persistSession: true, autoRefreshToken: true, detectSessionInUrl: false },
});
