// Applies the Supabase stub + all migrations to a fresh local database,
// then runs tests-db/*.sql. Each SQL test must end by selecting a row
// whose result ends in _OK; any FAIL raises and aborts.
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import pg from 'pg';

const root = new URL('..', import.meta.url).pathname;
const base = process.env.TEST_DATABASE_URL ?? 'postgres://postgres:postgres@localhost:5432/postgres';
const dbName = 'maidani_test';

const admin = new pg.Client({ connectionString: base });
await admin.connect();
await admin.query(`drop database if exists ${dbName} with (force)`);
await admin.query(`create database ${dbName}`);
await admin.end();

const url = new URL(base);
url.pathname = `/${dbName}`;
const db = new pg.Client({ connectionString: url.toString() });
await db.connect();

const run = async (label, sql) => {
  try {
    return await db.query(sql);
  } catch (err) {
    console.error(`✗ ${label}\n  ${err.message}`);
    await db.end();
    process.exit(1);
  }
};

await run('supabase stub', readFileSync(join(root, 'tests-db/supabase-stub.sql'), 'utf8'));
const migDir = join(root, 'supabase/migrations');
for (const f of readdirSync(migDir).filter((n) => n.endsWith('.sql')).sort()) {
  await run(`migration ${f}`, readFileSync(join(migDir, f), 'utf8'));
  console.log(`✓ migration ${f}`);
}
const testDir = join(root, 'tests-db');
for (const f of readdirSync(testDir).filter((n) => n.endsWith('.sql') && n !== 'supabase-stub.sql').sort()) {
  const res = await run(`test ${f}`, readFileSync(join(testDir, f), 'utf8'));
  const results = (Array.isArray(res) ? res : [res]).flatMap((r) => r.rows ?? []);
  const ok = results.some((row) => typeof row.result === 'string' && row.result.endsWith('_OK'));
  if (!ok) {
    console.error(`✗ test ${f}: no _OK result`);
    await db.end();
    process.exit(1);
  }
  console.log(`✓ test ${f}`);
}
await db.end();
