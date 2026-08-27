-- ══ ملفّ الإنجاز: الأدوار، والشواهد ══
--
-- وكلُّ ما هنا **إضافةٌ خالصة**: لا عمودٌ يُحذف، ولا سياسةٌ قائمةٌ
-- تُبدَّل تبديلًا يُنقص، ولا بياناتٌ تُمسّ. فإن لم تُعجب الفكرة، حذفُ
-- ما أُضيف رجوعٌ تامٌّ إلى ما كان.

-- ① أدوارٌ ينقصها ما في قوالب المالك
insert into public.roles (key, name_ar, blurb_ar, sort, is_system) values
  ('admin_assistant', 'مساعد إداريّ',       'الشؤون الإداريّة وملفّ الإنجاز', 15, false),
  ('health_advisor',  'موجّه صحّيّ',          'الصحّة المدرسيّة',              45, false),
  ('lab_technician',  'محضر مختبر',          'المختبرات والتجارب',           55, false),
  ('kg_teacher',      'معلّمة رياض أطفال',   'مرحلة الطفولة المبكّرة',        25, false)
on conflict (key) do nothing;

-- ② القالب يعرف لأيّ دور — بُعدٌ ثانٍ فوق الجمهور.
--    «للمدرسة» يقول أيَّ حساب، و«لرائد النشاط» يقول أيَّ موظّفٍ داخله.
--    ومصفوفةٌ فارغةٌ تعني «لكلّ أدوار جمهوره» — وهو حالُ كلّ ما رُفع
--    قبل اليوم، فلا يتغيّر عند أحدٍ شيء.
alter table public.templates
  add column if not exists role_keys text[] not null default '{}';
create index if not exists templates_roles_idx
  on public.templates using gin (role_keys);

drop policy if exists templates_read on public.templates;
create policy templates_read on public.templates for select using (
  app.is_admin() or (
    status = 'published'
    and (
      templates.audience = 'all'
      or exists (select 1 from public.subscribers s
                  where s.id = app.subscriber_id()
                    and s.account_type::text = templates.audience)
    )
    and (
      folder_id is null
      or exists (select 1 from public.template_folders f
                  where f.id = templates.folder_id and app.folder_visible(f.*))
    )
    and (
      cardinality(templates.role_keys) = 0
      or exists (select 1 from public.profiles p
                  where p.id = auth.uid() and p.role_key = any (templates.role_keys))
    )
  )
);

-- ══════════════ ③ سجلّ الشواهد ══════════════
--
-- الموظّف لا يعجز عن كتابة ملفّ إنجازه؛ يعجز في يونيو عن تذكّر ما فعله
-- في أكتوبر. فالقيمة ليست في الذكاء الذي يُركّب آخرَ العام، بل في أن
-- يكون الالتقاط **أسرع من نسيانه**.
--
-- والمحور اختياريّ (`default ''`): الإلزام يُبطئ الالتقاط فيموت السجلّ
-- من أصله، وتركُه بلا سؤالٍ يجعل التوزيع تخمينًا. فيُسأل ويجوز تخطّيه.

create table if not exists public.portfolio_items (
  id             uuid primary key default gen_random_uuid(),
  subscriber_id  uuid not null references public.subscribers(id) on delete cascade,
  owner_id       uuid not null references public.profiles(id) on delete cascade,
  academic_year  text not null default '',
  axis           text not null default '',
  title          text not null default '',
  note           text not null default '',
  kind           text not null default 'photo'
                 check (kind in ('photo', 'file', 'text', 'certificate')),
  file_path      text,
  file_mime      text,
  file_size      integer,
  -- يومُ وقوع الحدث لا يومُ رفعه: يُرفع بعد أسبوعٍ أحيانًا، والملفّ
  -- يُرتَّب بتاريخ وقوعه.
  happened_on    date not null default current_date,
  created_at     timestamptz not null default now(),
  updated_at     timestamptz not null default now()
);

create index if not exists portfolio_owner_idx
  on public.portfolio_items (owner_id, happened_on desc);
create index if not exists portfolio_subscriber_idx
  on public.portfolio_items (subscriber_id, academic_year);

alter table public.portfolio_items enable row level security;

create or replace function app.is_school_lead() returns boolean
language sql stable security definer set search_path to 'public', 'app' as $$
  select exists (
    select 1 from public.profiles p
     where p.id = auth.uid()
       and p.subscriber_id = app.subscriber_id()
       and (p.is_owner or p.role_key in ('principal', 'vice_principal'))
  );
$$;

-- الموظّف يرى شواهده، والمديرُ يرى شواهد مدرسته. ولا يراها موظّفٌ آخر.
create policy portfolio_read on public.portfolio_items for select using (
  owner_id = auth.uid()
  or (subscriber_id = app.subscriber_id() and app.is_school_lead())
  or app.is_admin()
);

-- والمديرُ يرى ولا يكتب: السجلّ سجلُّ صاحبه.
create policy portfolio_insert on public.portfolio_items for insert with check (
  owner_id = auth.uid() and subscriber_id = app.subscriber_id()
);
create policy portfolio_update on public.portfolio_items for update
  using (owner_id = auth.uid()) with check (owner_id = auth.uid());
create policy portfolio_delete on public.portfolio_items for delete
  using (owner_id = auth.uid());

-- ══════════════ ④ دلوُ الشواهد ══════════════
-- خاصٌّ لا عامّ: فيه صورُ طلّاب. ويُقرأ برابطٍ موقَّعٍ قصير الأجل.
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('portfolio', 'portfolio', false, 12582912,
        array['image/png','image/jpeg','image/webp','image/heic','application/pdf'])
on conflict (id) do nothing;

-- المسار: <المشترك>/<الموظّف>/<ملفّ> — فالمالكُ يُعرف من أوّل جزأين.
create policy portfolio_files_read on storage.objects for select using (
  bucket_id = 'portfolio' and (
    (storage.foldername(name))[2] = auth.uid()::text
    or ((storage.foldername(name))[1] = app.subscriber_id()::text and app.is_school_lead())
  )
);
create policy portfolio_files_write on storage.objects for insert with check (
  bucket_id = 'portfolio'
  and (storage.foldername(name))[1] = app.subscriber_id()::text
  and (storage.foldername(name))[2] = auth.uid()::text
);
create policy portfolio_files_delete on storage.objects for delete using (
  bucket_id = 'portfolio' and (storage.foldername(name))[2] = auth.uid()::text
);
