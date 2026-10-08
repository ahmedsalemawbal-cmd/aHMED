import { readFileSync, existsSync } from 'node:fs';

/** Reads .env.local (never committed) for API tests run from Node. */
export function loadEnv(): Record<string, string> {
  const env: Record<string, string> = {};
  if (existsSync('.env.local')) {
    for (const line of readFileSync('.env.local', 'utf8').split('\n')) {
      const m = /^([A-Z0-9_]+)=(.*)$/.exec(line.trim());
      if (m?.[1] && m[2] !== undefined) env[m[1]] = m[2];
    }
  }
  return { ...env, ...Object.fromEntries(Object.entries(process.env).filter((e): e is [string, string] => e[1] !== undefined)) };
}

export function required(env: Record<string, string>, key: string): string {
  const v = env[key];
  if (!v) throw new Error(`Missing ${key}. Copy .env.example to .env.local and fill it.`);
  return v;
}
