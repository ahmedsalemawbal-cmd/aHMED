-- ═══════════════════════════════════════════════════════════════════
--  اختبار سياسات الأمان — سلوكًا لا شكلًا
--
--  وجود السياسة لا يعني أنها تمنع. الاختبار الوحيد الذي يُطمئن هو أن
--  تجلس في مقعد وليّ أمر حقيقي وتحاول أن ترى ابن غيرك — فتُمنع.
--
--  يُشغَّل بـ:  psql -f rls_test.sql
--  ويخرج بخطأ إن سقط أي تأكيد.
-- ═══════════════════════════════════════════════════════════════════

begin;

set local role postgres;

-- ── تجهيز: أسرتان وطفلان ومعلّم ──────────────────────────────────────

insert into auth.users (id, email) values
    ('11111111-1111-1111-1111-111111111111', 'abu.sami@test'),
    ('22222222-2222-2222-2222-222222222222', 'abu.nora@test'),
    ('33333333-3333-3333-3333-333333333333', 'teacher@test'),
    ('44444444-4444-4444-4444-444444444444', 'guard@test');

insert into public.sch_users (auth_id, login, email, display_name, role) values
    ('11111111-1111-1111-1111-111111111111', 'abusami', 'abu.sami@test', 'أبو سامي', 'sch_guardian'),
    ('22222222-2222-2222-2222-222222222222', 'abunora', 'abu.nora@test', 'أبو نورة', 'sch_guardian'),
    ('33333333-3333-3333-3333-333333333333', 'teach',   'teacher@test',  'أ. خالد',  'sch_teacher'),
    ('44444444-4444-4444-4444-444444444444', 'guard',   'guard@test',    'الحارس',   'sch_guard');

insert into public.sch_students
    (full_name, qr_token, custody_state, status, created_at, updated_at) values
    ('سامي أحمد', 'qr-sami-000000000000000000000', 'at_school', 'active', now(), now()),
    ('نورة سعد',  'qr-nora-000000000000000000000', 'at_school', 'active', now(), now());

insert into public.sch_guardian_student (guardian_user_id, student_id, can_pickup, created_at)
select u.id, s.id, true, now()
  from public.sch_users u, public.sch_students s
 where (u.login = 'abusami' and s.full_name = 'سامي أحمد')
    or (u.login = 'abunora' and s.full_name = 'نورة سعد');

-- ── أداة التأكيد ────────────────────────────────────────────────────

create or replace function pg_temp.expect(label text, got anyelement, want anyelement)
returns void language plpgsql as $$
begin
    if got is distinct from want then
        raise exception '❌ %  — النتيجة % والمتوقَّع %', label, got, want;
    end if;
    raise notice '✅ %', label;
end $$;

-- تُنتحل هوية مستخدم كما يفعل Supabase: دور محدود + مطالبة JWT
create or replace function pg_temp.as_user(uid uuid)
returns void language plpgsql as $$
begin
    perform set_config('request.jwt.claim.sub', uid::text, true);
    execute 'set local role authenticated';
end $$;

do $$ begin
    if not exists (select 1 from pg_roles where rolname = 'authenticated') then
        create role authenticated nologin;
    end if;
end $$;

grant usage on schema public to authenticated;
grant select, insert, update, delete on all tables in schema public to authenticated;
grant execute on all functions in schema public to authenticated;
grant usage on schema auth to authenticated;
grant select on auth.users to authenticated;

-- ═══ (١) وليّ الأمر يرى ابنه — ولا يرى ابن غيره ═══════════════════════

select pg_temp.as_user('11111111-1111-1111-1111-111111111111');

select pg_temp.expect('أبو سامي يرى طالبًا واحدًا',
    (select count(*) from public.sch_students), 1::bigint);

select pg_temp.expect('والذي يراه هو ابنه',
    (select full_name from public.sch_students), 'سامي أحمد'::varchar);

select pg_temp.expect('ولا يرى «نورة» ولو سألها بالاسم',
    (select count(*) from public.sch_students where full_name = 'نورة سعد'), 0::bigint);

-- ═══ (٢) ولا يكتب في سجلّ ليس له ═══════════════════════════════════════

do $$
begin
    begin
        update public.sch_students set notes = 'تلاعب' where full_name = 'سامي أحمد';
        -- الصفّ مقروء لكن لا سياسة UPDATE لوليّ الأمر: التحديث يمرّ بلا أثر
        if (select count(*) from public.sch_students where notes = 'تلاعب') > 0 then
            raise exception '❌ وليّ الأمر عدّل سجلّ الطالب';
        end if;
        raise notice '✅ وليّ الأمر لا يعدّل سجلّ ابنه';
    exception when insufficient_privilege then
        raise notice '✅ وليّ الأمر لا يعدّل سجلّ ابنه (رُفض صراحةً)';
    end;
end $$;

-- ═══ (٣) سجلّ العهدة لا يُعدَّل ولا يُحذف — من أحد ═════════════════════

reset role;
insert into public.sch_custody_events
    (student_id, from_state, to_state, checkpoint, client_uuid, occurred_at, created_at)
select id, 'home', 'at_school', 'gate_in', 'test-gate-in-1', now(), now()
  from public.sch_students where full_name = 'سامي أحمد';

select pg_temp.as_user('44444444-4444-4444-4444-444444444444');   -- الحارس

do $$
declare n int;
begin
    update public.sch_custody_events set to_state = 'home';
    get diagnostics n = row_count;
    if n > 0 then raise exception '❌ سجلّ العهدة عُدِّل (% صفًّا)', n; end if;
    raise notice '✅ سجلّ العهدة لا يُعدَّل';

    delete from public.sch_custody_events;
    get diagnostics n = row_count;
    if n > 0 then raise exception '❌ سجلّ العهدة حُذف (% صفًّا)', n; end if;
    raise notice '✅ سجلّ العهدة لا يُحذف';
exception when insufficient_privilege then
    raise notice '✅ سجلّ العهدة محميّ (رُفض صراحةً)';
end $$;

-- ═══ (٤) الحارس لا يرى الملفّ الصحّي ══════════════════════════════════

reset role;
insert into public.sch_student_health (student_id, blood_type, chronic, allergies, updated_at)
select id, 'O+', 'الربو', 'الفول السوداني', now()
  from public.sch_students where full_name = 'سامي أحمد';

select pg_temp.as_user('44444444-4444-4444-4444-444444444444');
select pg_temp.expect('الحارس لا يرى الملفّ الصحّي',
    (select count(*) from public.sch_student_health), 0::bigint);

-- ═══ (٥) وليّ الأمر يرى الحساسية وفصيلة الدم — لا التشخيص ═════════════

select pg_temp.as_user('11111111-1111-1111-1111-111111111111');

select pg_temp.expect('وليّ الأمر لا يقرأ جدول الصحة مباشرةً',
    (select count(*) from public.sch_student_health), 0::bigint);

reset role;
grant select on public.sch_health_for_family to authenticated;
select pg_temp.as_user('11111111-1111-1111-1111-111111111111');

select pg_temp.expect('لكنه يرى حساسية ابنه من العرض المحدود',
    (select allergies from public.sch_health_for_family), 'الفول السوداني'::varchar);

select pg_temp.expect('ولا يرى منه تشخيصًا',
    (select count(*) from information_schema.columns
      where table_name = 'sch_health_for_family' and column_name = 'chronic'), 0::bigint);

select pg_temp.as_user('22222222-2222-2222-2222-222222222222');   -- أبو نورة
select pg_temp.expect('وأبو نورة لا يرى حساسية سامي',
    (select count(*) from public.sch_health_for_family), 0::bigint);

-- ═══ (٦) المعلّم يرى كل الطلاب ═════════════════════════════════════════

select pg_temp.as_user('33333333-3333-3333-3333-333333333333');
select pg_temp.expect('المعلّم يرى طلاب المدرسة',
    (select count(*) from public.sch_students), 2::bigint);

select pg_temp.expect('ولا يرى الرواتب',
    (select count(*) from public.sch_payroll_runs), 0::bigint);

reset role;
rollback;
