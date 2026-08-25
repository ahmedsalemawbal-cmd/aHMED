-- ══ مَن يرى ماذا — يُفحص في قاعدة البيانات لا في الواجهة ══
--
-- وُلد هذا الملفّ من عطبٍ مرّ من تحت سبعةٍ وخمسين فحصًا: رشّحتُ المجلّدات
-- بحسب نوع الحساب، وتركتُ سياسة القوالب «كلّ منشورٍ يُرى». وصفحة
-- «ملفّاتي» تعرض «قوالب تناسب دورك» بطلبٍ مباشرٍ على `templates` لا يمرّ
-- بالمجلّدات — ففتح المالك حساب معلّمٍ جديد فوجد تقرير المدرسة فيه.
--
--     الدرس: حجبُ الحاوية لا يحجب ما فيها.
--
-- وفحوص المتصفّح عندنا تُحاكي طلبات الخادم فتردّ ما نُمليه عليها. فلا
-- تكشف عطبًا في السياسة أبدًا، مهما كثرت. ولا يكشفه إلّا استعلامٌ ينتحل
-- هويّة مستخدمٍ حقيقيّ ويرى ما يراه.
--
--     psql "$DATABASE_URL" -f midad/supabase/tests/audience.sql
--
-- ولا يكتب شيئًا: يقرأ حسابين قائمين — مدرسةً ومعلّمًا — وينتحلهما.
-- فحصٌ يكتب في قاعدةٍ حيّة يترك أثرًا إن تعثّر، وأثرٌ في auth.users
-- ليس هيّنًا. فيقرأ ولا يكتب.

\set ON_ERROR_STOP on

create or replace function pg_temp.seen(u uuid, what text)
returns text language plpgsql as $$
declare out text;
begin
  perform set_config('role', 'authenticated', true);
  perform set_config('request.jwt.claims',
    json_build_object('sub', u, 'role', 'authenticated')::text, true);

  if what = 'folders' then
    select coalesce(string_agg(name, ' · ' order by sort), '—') into out
      from public.template_folders;
  else
    select coalesce(string_agg(title, ' · ' order by title), '—') into out
      from public.templates;
  end if;

  perform set_config('role', 'postgres', true);
  perform set_config('request.jwt.claims', '', true);
  return out;
end $$;

do $$
declare
  su uuid; tu uuid;
  s_f text; s_t text; t_f text; t_t text;
begin
  -- مالكُ حسابِ مدرسةٍ ومالكُ حسابِ معلّم، أيًّا كانا
  select p.id into su from public.profiles p
    join public.subscribers s on s.id = p.subscriber_id
   where s.account_type = 'school' and p.is_owner limit 1;
  select p.id into tu from public.profiles p
    join public.subscribers s on s.id = p.subscriber_id
   where s.account_type = 'teacher' and p.is_owner limit 1;

  if su is null or tu is null then
    raise notice '⚠ لا حسابَ مدرسةٍ أو معلّمٍ لننتحله — تُخطّى';
    return;
  end if;

  s_f := pg_temp.seen(su, 'folders');   s_t := pg_temp.seen(su, 'templates');
  t_f := pg_temp.seen(tu, 'folders');   t_t := pg_temp.seen(tu, 'templates');

  raise notice 'المدرسة ← مجلّدات: %', s_f;
  raise notice 'المدرسة ← قوالب:   %', s_t;
  raise notice 'المعلّم ← مجلّدات: %', t_f;
  raise notice 'المعلّم ← قوالب:   %', t_t;

  -- ① كلٌّ يرى مجلّداته
  if s_f not like '%ملفّات المدرسة%' then
    raise exception 'المدرسة لا ترى «ملفّات المدرسة»: %', s_f; end if;
  if s_f not like '%قوالب الشهادات%' then
    raise exception 'مالك المدرسة لا يرى «قوالب الشهادات»: %', s_f; end if;
  if t_f not like '%ملفّاتي%' then
    raise exception 'المعلّم لا يرى «ملفّاتي»: %', t_f; end if;

  -- ② ولا يرى مجلّد غيره
  if t_f like '%المدرسة%' or t_f like '%الشهادات%' then
    raise exception 'المعلّم يرى مجلّدًا ليس له: %', t_f; end if;

  -- ③ والقالب يرثُ جمهور مجلّده — وهذا هو ما فات
  if t_t like '%نافس%' then
    raise exception 'المعلّم يرى قالب مدرسة: %', t_t; end if;

  raise notice '✅ كلٌّ يرى ما له، ولا يرى ما لغيره';
end $$;
