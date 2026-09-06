-- ══ الجمهور ينتقل من المجلّد إلى الملفّ ══
--
-- كان الجمهور مكتوبًا على المجلّد، فكلّ ما فيه يرثه. فلم يكن للمالك
-- سبيلٌ إلى ملفٍّ واحدٍ يخالف مجلّده، ولا إلى ملفٍّ يراه الجميع.
-- فصار على الملفّ نفسه: مدرسةٌ، أو معلّمٌ، أو الكلّ.
--
-- والمجلّد يبقى — للتنظيم، ولحراسة ما له مجلّدٌ خاصّ كالشهادات.

-- ① مجلّدٌ عامّ يُعرَف بالتصريح لا بالاستنتاج.
--    قوالب «الكلّ» بلا مجلّدٍ في الجدول، وتُعرَض في المجلّد العامّ لكلّ
--    جمهور. ولو استنتجنا «العامّ» من نفي `owner_only` لصحّ اليوم وكذب
--    غدًا متى أُضيف مجلّدٌ ثالث.
alter table public.template_folders
  add column if not exists is_general boolean not null default false;
update public.template_folders set is_general = true
 where slug in ('school-files', 'my-files');

-- ② الجمهور على الملفّ
alter table public.templates
  add column if not exists audience text not null default 'school';
alter table public.templates drop constraint if exists templates_audience_ck;
alter table public.templates add constraint templates_audience_ck
  check (audience in ('school', 'teacher', 'all'));

update public.templates t
   set audience = f.audience
  from public.template_folders f
 where f.id = t.folder_id;

-- ③ ترتيبان لا ترتيب: أوّلويّة المدير غير أوّلويّة المعلّم، وقالبُ
--    «الكلّ» يظهر في القائمتين — فسحبُه في إحداهما لا يحرّكه في الأخرى.
alter table public.templates
  add column if not exists sort_school  integer not null default 0,
  add column if not exists sort_teacher integer not null default 0;
update public.templates set sort_school = sort, sort_teacher = sort;

-- ④ ومجلّدُ قوالب «الكلّ» يُشتقّ عند العرض لا يُخزَّن
update public.templates set folder_id = null where audience = 'all';

-- ⑤ الرؤية بالجمهور، والمجلّد يبقى حارسًا لما له مجلّدٌ خاصّ
--    (الشهادات `owner_only`: لا يراها معلّمو المدرسة).
drop policy if exists templates_read on public.templates;
create policy templates_read on public.templates for select using (
  app.is_admin() or (
    status = 'published'
    and (
      templates.audience = 'all'
      or exists (
        select 1 from public.subscribers s
         where s.id = app.subscriber_id()
           and s.account_type::text = templates.audience
      )
    )
    and (
      folder_id is null
      or exists (
        select 1 from public.template_folders f
         where f.id = templates.folder_id and app.folder_visible(f.*)
      )
    )
  )
);

-- ⑥ العدّاد يعدّ ما يُعرَض: قوالبُ المجلّد، وقوالبُ «الكلّ» في كلّ عامّ
create or replace view public.template_folder_counts as
  select f.id as folder_id, f.slug, count(t.id) as template_count
    from public.template_folders f
    left join public.templates t
      on t.status = 'published'
     and ( t.folder_id = f.id
           or (t.folder_id is null and t.audience = 'all' and f.is_general) )
   group by f.id, f.slug;
