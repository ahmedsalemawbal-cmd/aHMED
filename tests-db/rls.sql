-- اختبار RLS والسلامة: يعمل كاملاً داخل معاملة ويُلغى في النهاية.
-- يُشغَّل محلياً (npm run test:db) وعلى مشروع Supabase نفسه.
-- أي فشل يرفع استثناءً يبدأ بـ FAIL.
begin;

-- مستخدمان: A و B (الـ trigger ينشئ لكل منهما ملفاً وأنشطة وخدمات وقوالب)
insert into auth.users (id, email, aud, role, raw_user_meta_data) values
  ('a0000000-0000-4000-8000-00000000000a', 'rls-a@maidani.test', 'authenticated', 'authenticated', '{"full_name": "أ"}'),
  ('b0000000-0000-4000-8000-00000000000b', 'rls-b@maidani.test', 'authenticated', 'authenticated', '{"full_name": "ب"}');

-- ---------------------------------------------------------------- بيانات البداية
do $$
declare n integer; w integer;
begin
  select count(*) into n from public.profiles where id = 'a0000000-0000-4000-8000-00000000000a';
  if n <> 1 then raise exception 'FAIL bootstrap: profile count %', n; end if;
  select count(*) into n from public.activity_types where owner_id = 'a0000000-0000-4000-8000-00000000000a';
  if n <> 4 then raise exception 'FAIL bootstrap: activity count %', n; end if;
  select count(*) into n from public.services where owner_id = 'a0000000-0000-4000-8000-00000000000a';
  if n <> 6 then raise exception 'FAIL bootstrap: service count %', n; end if;
  select count(*) into n from public.message_templates where owner_id = 'a0000000-0000-4000-8000-00000000000a';
  if n <> 3 then raise exception 'FAIL bootstrap: template count %', n; end if;
  for w in
    select sum((item ->> 'weight')::integer)
    from public.activity_types t, jsonb_array_elements(t.checklist -> 'general') item
    where t.owner_id = 'a0000000-0000-4000-8000-00000000000a'
    group by t.id
  loop
    if w <> 100 then raise exception 'FAIL bootstrap: weights sum %', w; end if;
  end loop;
  select count(*) into n
  from public.activity_types t, jsonb_array_elements(t.checklist -> 'general') item
  where t.owner_id = 'a0000000-0000-4000-8000-00000000000a' and t.name = 'مطعم';
  if n <> 13 then raise exception 'FAIL bootstrap: general items %', n; end if;
end $$;

-- ---------------------------------------------------------------- المستخدم A ينشئ بياناته
set local role authenticated;
select set_config('request.jwt.claims', '{"sub": "a0000000-0000-4000-8000-00000000000a", "role": "authenticated"}', true);

insert into public.leads (id, business_name, activity_type_id, contact_name, contact_role, phone_e164, stage, score, priority)
select 'aaaaaaaa-0000-4000-8000-000000000001', 'مطعم ريدان', id, 'خالد العتيبي', 'owner', '966551234567', 'visited', 48, 'hot'
from public.activity_types where name = 'مطعم';
insert into public.visits (id, lead_id, key_observation)
values ('aaaaaaaa-0000-4000-8000-000000000002', 'aaaaaaaa-0000-4000-8000-000000000001', 'أطباقهم ممتازة وحسابهم بدون فيديو');
insert into public.visit_media (visit_id, storage_path, kind)
values ('aaaaaaaa-0000-4000-8000-000000000002', 'a0000000-0000-4000-8000-00000000000a/aaaaaaaa-0000-4000-8000-000000000002/1.jpg', 'photo');
insert into public.assessments (lead_id, visit_id, answers, score, weaknesses)
values ('aaaaaaaa-0000-4000-8000-000000000001', 'aaaaaaaa-0000-4000-8000-000000000002', '{"g1": "yes", "s3": "no"}', 48, '["s3"]');
insert into public.lead_services (lead_id, service_id, note)
select 'aaaaaaaa-0000-4000-8000-000000000001', id, 'لأنه: لا ينشر فيديو أو ريلز' from public.services where name = 'تصوير وإنتاج فيديو وريلز';
insert into public.tasks (id, lead_id, title, kind, due_at)
values ('aaaaaaaa-0000-4000-8000-000000000003', 'aaaaaaaa-0000-4000-8000-000000000001', 'متابعة أولى', 'followup_3', now() + interval '3 days');
insert into public.messages (lead_id, kind, body, status, sent_at, task_id)
values ('aaaaaaaa-0000-4000-8000-000000000001', 'first', 'السلام عليكم', 'sent', now(), 'aaaaaaaa-0000-4000-8000-000000000003');
insert into public.quotes (id, lead_id) values ('aaaaaaaa-0000-4000-8000-000000000004', 'aaaaaaaa-0000-4000-8000-000000000001');
insert into public.quotes (lead_id) values ('aaaaaaaa-0000-4000-8000-000000000001');
insert into storage.objects (bucket_id, name)
values ('visit-media', 'a0000000-0000-4000-8000-00000000000a/aaaaaaaa-0000-4000-8000-000000000002/1.jpg');
update public.leads set stage = 'contacted' where id = 'aaaaaaaa-0000-4000-8000-000000000001';
select set_config('test.a_activity', (select id::text from public.activity_types where name = 'مطعم'), true);

do $$
declare n integer; num text;
begin
  -- سجل المراحل: الإنشاء ثم التغيير
  select count(*) into n from public.stage_history where lead_id = 'aaaaaaaa-0000-4000-8000-000000000001';
  if n <> 2 then raise exception 'FAIL stage_history rows %', n; end if;
  select count(*) into n from public.stage_history
  where lead_id = 'aaaaaaaa-0000-4000-8000-000000000001' and from_stage = 'visited' and to_stage = 'contacted';
  if n <> 1 then raise exception 'FAIL stage_history transition missing'; end if;
  -- ترقيم العروض
  select number into num from public.quotes where id = 'aaaaaaaa-0000-4000-8000-000000000004';
  if num !~ '^Q-[0-9]{4}-001$' then raise exception 'FAIL quote number %', num; end if;
  select count(*) into n from public.quotes where number ~ '^Q-[0-9]{4}-002$';
  if n <> 1 then raise exception 'FAIL second quote number'; end if;
  -- A لا يكتب في stage_history مباشرة
  begin
    insert into public.stage_history (lead_id, to_stage) values ('aaaaaaaa-0000-4000-8000-000000000001', 'won');
    raise exception 'FAIL user inserted into stage_history';
  exception when insufficient_privilege then null;
  end;
  -- القيم المغلقة
  begin
    insert into public.leads (business_name, source) values ('اختبار', 'qr');
    raise exception 'FAIL source qr accepted';
  exception when check_violation then null;
  end;
  begin
    insert into public.leads (business_name, phone_e164) values ('اختبار', '0551234567');
    raise exception 'FAIL local phone format accepted';
  exception when check_violation then null;
  end;
  begin
    insert into public.quotes (lead_id, discount_pct) values ('aaaaaaaa-0000-4000-8000-000000000001', 20);
    raise exception 'FAIL discount 20 accepted';
  exception when check_violation then null;
  end;
  begin
    insert into public.assessments (lead_id, answers, score) values ('aaaaaaaa-0000-4000-8000-000000000001', '{"g1": "maybe"}', 10);
    raise exception 'FAIL answer maybe accepted';
  exception when check_violation then null;
  end;
end $$;

-- ---------------------------------------------------------------- المستخدم B
select set_config('request.jwt.claims', '{"sub": "b0000000-0000-4000-8000-00000000000b", "role": "authenticated"}', true);

do $$
declare
  n integer;
  t text;
  a uuid := 'a0000000-0000-4000-8000-00000000000a';
  a_lead uuid := 'aaaaaaaa-0000-4000-8000-000000000001';
begin
  -- لا يقرأ شيئاً من بيانات A في أي جدول
  foreach t in array array['profiles', 'activity_types', 'services', 'leads', 'visits', 'visit_media',
                           'assessments', 'lead_services', 'message_templates', 'messages', 'tasks',
                           'stage_history', 'quotes']
  loop
    execute format('select count(*) from public.%I where owner_id = $1', t) into n using a;
    if n <> 0 then raise exception 'FAIL B reads % rows of A in %', n, t; end if;
  end loop;
  select count(*) into n from storage.objects where name like 'a0000000-0000-4000-8000-00000000000a/%';
  if n <> 0 then raise exception 'FAIL B reads A storage objects'; end if;

  -- B يرى بياناته فقط
  select count(*) into n from public.activity_types;
  if n <> 4 then raise exception 'FAIL B sees % activity types', n; end if;

  -- لا يعدّل ولا يحذف
  update public.leads set business_name = 'مخترق' where id = a_lead;
  get diagnostics n = row_count;
  if n <> 0 then raise exception 'FAIL B updated A lead'; end if;
  delete from public.leads where id = a_lead;
  get diagnostics n = row_count;
  if n <> 0 then raise exception 'FAIL B deleted A lead'; end if;
  update public.profiles set full_name = 'مخترق' where id = a;
  get diagnostics n = row_count;
  if n <> 0 then raise exception 'FAIL B updated A profile'; end if;
  delete from storage.objects where name like 'a0000000-0000-4000-8000-00000000000a/%';
  get diagnostics n = row_count;
  if n <> 0 then raise exception 'FAIL B deleted A storage objects'; end if;

  -- لا ينشئ سجلاً باسم A
  begin
    insert into public.leads (owner_id, business_name) values (a, 'باسم غيري');
    raise exception 'FAIL B inserted lead as A';
  exception when insufficient_privilege then null;
  end;
  -- لا يربط سجله بعميل A
  begin
    insert into public.visits (lead_id) values (a_lead);
    raise exception 'FAIL B linked visit to A lead';
  exception when foreign_key_violation then null;
  end;
  begin
    insert into public.tasks (lead_id, title, due_at) values (a_lead, 'x', now());
    raise exception 'FAIL B linked task to A lead';
  exception when foreign_key_violation then null;
  end;
  begin
    insert into public.leads (business_name, activity_type_id)
    values ('محل ب', current_setting('test.a_activity')::uuid);
    raise exception 'FAIL B linked lead to A activity';
  exception when foreign_key_violation then null;
  end;
  -- لا يرفع ملفاً في مجلد A
  begin
    insert into storage.objects (bucket_id, name) values ('visit-media', 'a0000000-0000-4000-8000-00000000000a/x/evil.jpg');
    raise exception 'FAIL B uploaded into A folder';
  exception when insufficient_privilege then null;
  end;
end $$;

-- ---------------------------------------------------------------- A: بياناته سليمة بعد محاولات B
select set_config('request.jwt.claims', '{"sub": "a0000000-0000-4000-8000-00000000000a", "role": "authenticated"}', true);
do $$
declare nm text;
begin
  select business_name into nm from public.leads where id = 'aaaaaaaa-0000-4000-8000-000000000001';
  if nm is distinct from 'مطعم ريدان' then raise exception 'FAIL A lead changed to %', nm; end if;
end $$;

-- ---------------------------------------------------------------- anon: لا شيء
reset role;
set local role anon;
do $$
declare n integer;
begin
  begin
    select count(*) into n from public.leads;
    if n <> 0 then raise exception 'FAIL anon reads % leads', n; end if;
  exception when insufficient_privilege then null;
  end;
  begin
    insert into public.leads (owner_id, business_name) values ('a0000000-0000-4000-8000-00000000000a', 'anon');
    raise exception 'FAIL anon inserted a lead';
  exception when insufficient_privilege then null;
  end;
end $$;

reset role;
select 'RLS_OK' as result;
rollback;
