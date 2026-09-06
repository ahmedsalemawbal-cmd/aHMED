-- ============================================================
-- مِداد — منصّة الملفّات المدرسية وجداول نور
-- 0001 — البنية الأساسية: المخطّط والجداول
-- ============================================================

create extension if not exists "pgcrypto";
create extension if not exists "citext";

-- مخطّط داخلي غير معروض عبر PostgREST، لدوال المساعدة
create schema if not exists app;

-- ------------------------------------------------------------
-- الأنواع المعدودة
-- ------------------------------------------------------------
do $$ begin
  create type public.account_type as enum ('school','teacher');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.subscriber_status as enum ('trial','active','expired','suspended');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.member_status as enum ('active','suspended');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.invoice_status as enum ('unpaid','under_review','paid','rejected','cancelled');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.document_status as enum ('draft','complete');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.template_status as enum ('draft','published');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.subscription_status as enum ('active','expired','cancelled');
exception when duplicate_object then null; end $$;

-- ------------------------------------------------------------
-- الأدوار — تحدّد ما يُرى من القوالب لا الصلاحيات
-- ------------------------------------------------------------
create table if not exists public.roles (
  id          uuid primary key default gen_random_uuid(),
  key         text not null unique,
  name_ar     text not null,
  blurb_ar    text,
  sort        int  not null default 0,
  is_system   boolean not null default false,
  created_at  timestamptz not null default now()
);

-- ------------------------------------------------------------
-- الباقات
-- ------------------------------------------------------------
create table if not exists public.plans (
  id                 uuid primary key default gen_random_uuid(),
  key                text not null unique,
  name_ar            text not null,
  account_type       public.account_type not null,
  price_sar          numeric(10,2) not null default 0,
  period_months      int not null default 12,
  seats              int not null default 1,
  template_categories text[] not null default '{}',   -- فارغ = كلّ الفئات
  noor_enabled       boolean not null default true,
  ai_quota_monthly   int not null default 100,
  features_ar        text[] not null default '{}',
  is_active          boolean not null default true,
  is_default         boolean not null default false,
  sort               int not null default 0,
  created_at         timestamptz not null default now(),
  updated_at         timestamptz not null default now()
);

-- ------------------------------------------------------------
-- المشتركون — المدرسة أو المعلّم المستقلّ
-- ------------------------------------------------------------
create table if not exists public.subscribers (
  id                uuid primary key default gen_random_uuid(),
  name              text not null,
  account_type      public.account_type not null,
  city              text,
  school_type       text,                       -- حكومية / أهلية
  education_dept    text,                       -- إدارة التعليم
  academic_year     text default '1447 هـ',
  semester          text default 'الفصل الأول',
  principal_name    text,
  contact_phone     text,
  logo_url          text,
  status            public.subscriber_status not null default 'trial',
  suspended_reason  text,
  trial_ends_at     timestamptz not null default (now() + interval '7 days'),
  plan_id           uuid references public.plans(id) on delete set null,
  ai_quota_override int,
  created_at        timestamptz not null default now(),
  updated_at        timestamptz not null default now()
);
create index if not exists subscribers_status_idx on public.subscribers(status);
create index if not exists subscribers_created_idx on public.subscribers(created_at desc);

-- ------------------------------------------------------------
-- الأعضاء — ملفّ لكلّ مستخدم في auth.users
-- ------------------------------------------------------------
create table if not exists public.profiles (
  id                  uuid primary key references auth.users(id) on delete cascade,
  subscriber_id       uuid references public.subscribers(id) on delete cascade,
  full_name           text not null,
  phone               text not null,
  email               text,
  role_key            text not null default 'teacher' references public.roles(key) on update cascade,
  is_owner            boolean not null default false,
  status              public.member_status not null default 'active',
  theme_pref          text not null default 'auto',   -- light | dark | auto
  email_notifications boolean not null default true,
  last_login_at       timestamptz,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);
create unique index if not exists profiles_phone_key on public.profiles(phone);
create index if not exists profiles_subscriber_idx on public.profiles(subscriber_id);

-- مالكو المنصّة (السوبر أدمن)
create table if not exists public.platform_admins (
  user_id    uuid primary key references auth.users(id) on delete cascade,
  created_at timestamptz not null default now()
);

-- ------------------------------------------------------------
-- الاشتراكات والفواتير
-- ------------------------------------------------------------
create table if not exists public.subscriptions (
  id            uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  plan_id       uuid not null references public.plans(id),
  status        public.subscription_status not null default 'active',
  starts_at     timestamptz not null default now(),
  ends_at       timestamptz not null,
  amount_sar    numeric(10,2) not null default 0,
  created_at    timestamptz not null default now(),
  cancelled_at  timestamptz
);
create index if not exists subscriptions_subscriber_idx on public.subscriptions(subscriber_id);
create index if not exists subscriptions_ends_idx on public.subscriptions(ends_at);

create sequence if not exists public.invoice_number_seq start 1001;

create table if not exists public.invoices (
  id              uuid primary key default gen_random_uuid(),
  number          text not null unique default ('MDD-' || to_char(now(),'YYYY') || '-' || lpad(nextval('public.invoice_number_seq')::text, 4, '0')),
  subscriber_id   uuid not null references public.subscribers(id) on delete cascade,
  subscription_id uuid references public.subscriptions(id) on delete set null,
  plan_id         uuid references public.plans(id),
  description_ar  text,
  amount_sar      numeric(10,2) not null default 0,
  tax_rate        numeric(5,4)  not null default 0,
  tax_amount      numeric(10,2) not null default 0,
  total_sar       numeric(10,2) not null default 0,
  status          public.invoice_status not null default 'unpaid',
  issued_at       timestamptz not null default now(),
  paid_at         timestamptz,
  submitted_at    timestamptz,
  receipt_url     text,
  internal_note   text,
  rejected_reason text,
  created_at      timestamptz not null default now()
);
create index if not exists invoices_subscriber_idx on public.invoices(subscriber_id);
create index if not exists invoices_status_idx on public.invoices(status);

-- ------------------------------------------------------------
-- الخدمة ① — القوالب والملفّات
-- ------------------------------------------------------------
create table if not exists public.templates (
  id               uuid primary key default gen_random_uuid(),
  slug             text not null unique,
  title            text not null,
  category_key     text not null,               -- مفتاح دور أو 'general'
  description      text,
  body             text not null default '',    -- المتن بصيغة {{field_key}}
  fields           jsonb not null default '[]'::jsonb,
  outputs          text[] not null default '{pdf,docx}',
  estimated_minutes int not null default 5,
  version          int not null default 1,
  status           public.template_status not null default 'published',
  usage_count      int not null default 0,
  is_new           boolean not null default false,
  sort             int not null default 0,
  created_at       timestamptz not null default now(),
  updated_at       timestamptz not null default now()
);
create index if not exists templates_category_idx on public.templates(category_key);
create index if not exists templates_status_idx on public.templates(status);

create table if not exists public.documents (
  id            uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  owner_id      uuid not null references public.profiles(id) on delete cascade,
  template_id   uuid references public.templates(id) on delete set null,
  title         text not null,
  data          jsonb not null default '{}'::jsonb,
  status        public.document_status not null default 'draft',
  created_at    timestamptz not null default now(),
  updated_at    timestamptz not null default now()
);
create index if not exists documents_subscriber_idx on public.documents(subscriber_id);
create index if not exists documents_owner_idx on public.documents(owner_id);
create index if not exists documents_updated_idx on public.documents(updated_at desc);

-- ------------------------------------------------------------
-- الخدمة ② — جداول نور
-- ------------------------------------------------------------
create table if not exists public.link_keys (
  id            uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  user_id       uuid not null references public.profiles(id) on delete cascade,
  key           text not null unique,
  created_at    timestamptz not null default now(),
  expires_at    timestamptz not null default (now() + interval '90 days'),
  last_used_at  timestamptz,
  revoked_at    timestamptz
);
create index if not exists link_keys_user_idx on public.link_keys(user_id);

create table if not exists public.noor_tables (
  id            uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  owner_id      uuid not null references public.profiles(id) on delete cascade,
  title         text not null,
  columns       jsonb not null default '[]'::jsonb,
  rows          jsonb not null default '[]'::jsonb,
  row_count     int not null default 0,
  source_url    text,
  created_at    timestamptz not null default now()
);
create index if not exists noor_tables_subscriber_idx on public.noor_tables(subscriber_id);

create table if not exists public.noor_ingest_log (
  id            uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  user_id       uuid references public.profiles(id) on delete set null,
  table_id      uuid references public.noor_tables(id) on delete set null,
  title         text,
  row_count     int not null default 0,
  created_at    timestamptz not null default now()
);

-- ------------------------------------------------------------
-- الذكاء الاصطناعيّ · الإعدادات · السجلّ · التواصل · الإشعارات
-- ------------------------------------------------------------
create table if not exists public.ai_usage (
  id            uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  user_id       uuid references public.profiles(id) on delete set null,
  document_id   uuid references public.documents(id) on delete set null,
  field_key     text,
  tone          text,
  tokens_in     int not null default 0,
  tokens_out    int not null default 0,
  created_at    timestamptz not null default now()
);
create index if not exists ai_usage_subscriber_month_idx on public.ai_usage(subscriber_id, created_at desc);

create table if not exists public.platform_settings (
  key        text primary key,
  value      jsonb not null default '{}'::jsonb,
  updated_at timestamptz not null default now()
);

create table if not exists public.audit_log (
  id            uuid primary key default gen_random_uuid(),
  subscriber_id uuid references public.subscribers(id) on delete cascade,
  actor_id      uuid,
  actor_name    text,
  event_type    text not null,
  message_ar    text not null,
  meta          jsonb not null default '{}'::jsonb,
  created_at    timestamptz not null default now()
);
create index if not exists audit_log_created_idx on public.audit_log(created_at desc);
create index if not exists audit_log_subscriber_idx on public.audit_log(subscriber_id);

create table if not exists public.contact_messages (
  id         uuid primary key default gen_random_uuid(),
  kind       text not null default 'contact',   -- contact | password_reset
  name       text,
  phone      text,
  email      text,
  subject    text,
  message    text,
  status     text not null default 'new',
  created_at timestamptz not null default now()
);

create table if not exists public.push_tokens (
  id         uuid primary key default gen_random_uuid(),
  user_id    uuid not null references public.profiles(id) on delete cascade,
  token      text not null unique,
  platform   text not null,                     -- ios | android
  created_at timestamptz not null default now()
);
