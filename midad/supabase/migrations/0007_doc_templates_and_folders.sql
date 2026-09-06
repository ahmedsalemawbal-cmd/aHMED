-- ═══════════════════════════════════════════════════════════════════════════
-- ٠٠٠٧ — القوالب تصير مستنداتٍ تُحرَّر، ومكتبةٌ بمجلّدات
--
-- القرار: القالب لم يعد استمارةَ حقولٍ تُملأ ({{field}}) بل **مستندًا كاملًا**
-- يفتحه المعلّم ويحرّره كما يحرّر الوورد. فالمتن HTML غنيّ، لا قالبَ استبدال.
--
-- الأعمدة القديمة (body · fields) تبقى ولا تُحذف: حذفُ عمودٍ لا رجعة فيه،
-- وإبقاؤه مهجورًا لا يكلّف شيئًا. تُعلَّم بتعليقٍ في الكتالوج كي لا يُبنى
-- عليها من جديد.
-- ═══════════════════════════════════════════════════════════════════════════

-- ───────────────────────── ١) مجلّدات المكتبة ─────────────────────────
-- على مستوى المنصّة لا المشترك: القوالب نفسها كتالوجٌ عامّ يديره المشرف،
-- فمجلّداتها كذلك. ولو صارت لكلّ مدرسة مجلّداتها فذاك جدولٌ آخر لاحقًا
-- (folders_subscriber) ولا يخلط بهذا.

create table if not exists public.template_folders (
  id          uuid primary key default gen_random_uuid(),
  slug        text not null unique,
  name        text not null,
  blurb       text,
  -- لونٌ مميّز للبطاقة: hex بستّ خانات، والتحقّق في القاعدة لا في الواجهة
  accent      text not null default '#5B4BD6'
              constraint template_folders_accent_hex check (accent ~ '^#[0-9A-Fa-f]{6}$'),
  icon        text not null default 'folder',
  sort        int  not null default 0,
  is_active   boolean not null default true,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

create index if not exists template_folders_sort_idx
  on public.template_folders(sort, name);

drop trigger if exists template_folders_touch on public.template_folders;
create trigger template_folders_touch before update on public.template_folders
  for each row execute function app.touch_updated_at();

-- ───────────────────────── ٢) القوالب: نمط المستند ─────────────────────────

do $$ begin
  create type public.template_kind as enum ('doc', 'form');
exception when duplicate_object then null; end $$;

alter table public.templates
  add column if not exists kind            public.template_kind not null default 'doc',
  add column if not exists folder_id       uuid references public.template_folders(id) on delete set null,
  add column if not exists content_html    text not null default '',
  -- إعداد الصفحة: المقاس والاتّجاه والهوامش بالمليمتر
  add column if not exists page            jsonb not null default
    '{"size":"A4","orientation":"portrait","margins":{"top":18,"right":16,"bottom":18,"left":16}}'::jsonb,
  -- من أيّ PDF جاء هذا القالب — للرجوع والمقارنة عند التحقّق
  add column if not exists source_pdf_path text,
  add column if not exists source_pages    int;

create index if not exists templates_folder_idx on public.templates(folder_id);
create index if not exists templates_kind_idx   on public.templates(kind);

comment on column public.templates.body   is 'مهجور — كان متن {{الحقول}}. استُبدل بـ content_html. لا يُبنى عليه.';
comment on column public.templates.fields is 'مهجور — كان تعريف الحقول. استُبدل بالتحرير المباشر. لا يُبنى عليه.';

-- ───────────────────────── ٣) الملفّات: متنٌ خاصّ بها ─────────────────────────
-- الملفّ ينسخ متن القالب عند الإنشاء ثمّ يستقلّ عنه: فتعديلُ القالب لاحقًا
-- لا يعبث بما كتبه المعلّم في ملفّه.

alter table public.documents
  add column if not exists content_html text not null default '',
  add column if not exists page         jsonb;

comment on column public.documents.data is 'مهجور — كان قيم الحقول. استُبدل بـ content_html.';

-- ───────────────────────── ٤) حماية الصفوف ─────────────────────────
alter table public.template_folders enable row level security;

-- المجلّدات كتالوج: يقرؤها كلّ مسجَّلٍ دخوله، ويكتبها مشرف المنصّة وحده
drop policy if exists template_folders_read on public.template_folders;
create policy template_folders_read on public.template_folders for select
  using (is_active or app.is_admin());

drop policy if exists template_folders_admin on public.template_folders;
create policy template_folders_admin on public.template_folders for all
  using (app.is_admin()) with check (app.is_admin());

-- ───────────────────────── ٥) دلو ملفّات PDF الأصليّة ─────────────────────────
-- **غير عامّ**: هذه ملفّات المالك، لا تُعرض لأحد. المشرف وحده يرفع ويقرأ.

insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('template-sources', 'template-sources', false, 26214400, array['application/pdf'])
on conflict (id) do update
  set public = false,
      file_size_limit = excluded.file_size_limit,
      allowed_mime_types = excluded.allowed_mime_types;

drop policy if exists tpl_src_read on storage.objects;
create policy tpl_src_read on storage.objects for select to authenticated
  using (bucket_id = 'template-sources' and app.is_admin());

drop policy if exists tpl_src_write on storage.objects;
create policy tpl_src_write on storage.objects for insert to authenticated
  with check (bucket_id = 'template-sources' and app.is_admin());

drop policy if exists tpl_src_update on storage.objects;
create policy tpl_src_update on storage.objects for update to authenticated
  using (bucket_id = 'template-sources' and app.is_admin())
  with check (bucket_id = 'template-sources' and app.is_admin());

drop policy if exists tpl_src_delete on storage.objects;
create policy tpl_src_delete on storage.objects for delete to authenticated
  using (bucket_id = 'template-sources' and app.is_admin());

-- ───────────────────────── ٦) عدّاد المجلّد ─────────────────────────
-- منظرٌ يعطي كلّ مجلّدٍ عدد قوالبه المنشورة — فالواجهة لا تجلب القوالب كلّها
-- لتعدّها. security_invoker كي يسري RLS على القارئ لا على صاحب المنظر.

create or replace view public.template_folder_counts
with (security_invoker = true) as
select f.id            as folder_id,
       f.slug,
       count(t.id)     as template_count
from public.template_folders f
left join public.templates t
       on t.folder_id = f.id and t.status = 'published'
group by f.id, f.slug;
