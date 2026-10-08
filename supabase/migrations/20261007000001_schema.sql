-- ميداني · المخطط
-- المصدر: docs/brief.md (نموذج البيانات) بعد تعديلات docs/decisions.md.
-- حُذف: audit_reports، quotes.public_token، leads.source = 'qr'.
-- كل جدول: id, owner_id, created_at, updated_at. RLS في الهجرة التالية.

create extension if not exists pgcrypto with schema extensions;

-- ---------------------------------------------------------------------------
-- updated_at
-- ---------------------------------------------------------------------------
create or replace function public.set_updated_at()
returns trigger
language plpgsql
set search_path = ''
as $$
begin
  new.updated_at := now();
  return new;
end;
$$;

-- ---------------------------------------------------------------------------
-- profiles: صف واحد لكل مستخدم، id = owner_id = auth.uid()
-- ---------------------------------------------------------------------------
create table public.profiles (
  id uuid primary key default auth.uid() references auth.users (id) on delete cascade,
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  full_name text not null default '',
  brand_name text,
  phone text,
  signature text,
  default_tone text not null default 'friendly' check (default_tone in ('friendly', 'formal', 'short')),
  weekly_visit_goal integer not null default 20 check (weekly_visit_goal between 1 and 500),
  theme text not null default 'auto' check (theme in ('light', 'dark', 'auto')),
  vat_enabled boolean not null default false,
  vat_rate numeric(5, 2) check (vat_rate is null or (vat_rate >= 0 and vat_rate <= 100)),
  constraint profiles_id_is_owner check (id = owner_id)
);

-- ---------------------------------------------------------------------------
-- activity_types: الأنشطة وبنود التقييم
-- checklist = { "general":  [{ "id", "label", "weight", "group" }],
--               "specific": [{ "id", "label" }] }
-- ---------------------------------------------------------------------------
create table public.activity_types (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  name text not null check (char_length(btrim(name)) > 0),
  icon text not null default 'store',
  checklist jsonb not null default '{"general": [], "specific": []}'::jsonb
    check (jsonb_typeof(checklist -> 'general') = 'array' and jsonb_typeof(checklist -> 'specific') = 'array'),
  default_service_ids uuid[] not null default '{}',
  sort_order integer not null default 0,
  unique (owner_id, name)
);

-- ---------------------------------------------------------------------------
-- services: كتالوج الخدمات
-- weakness_item_ids: بنود التقييم التي تقترح هذه الخدمة إن كانت إجابتها لا أو جزئي
-- ---------------------------------------------------------------------------
create table public.services (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  name text not null check (char_length(btrim(name)) > 0),
  description text,
  price_from numeric(12, 2) check (price_from is null or price_from >= 0),
  billing text not null check (billing in ('monthly', 'one_time')),
  activity_type_ids uuid[] not null default '{}',
  weakness_item_ids text[] not null default '{}',
  is_active boolean not null default true,
  sort_order integer not null default 0,
  unique (owner_id, name)
);

-- ---------------------------------------------------------------------------
-- leads: العميل المحتمل
-- ---------------------------------------------------------------------------
create table public.leads (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  business_name text not null check (char_length(btrim(business_name)) > 0),
  activity_type_id uuid references public.activity_types (id) on delete set null,
  contact_name text,
  contact_role text check (contact_role in ('owner', 'manager', 'employee')),
  phone_e164 text check (phone_e164 ~ '^9665[0-9]{8}$'),
  is_decision_maker boolean not null default false,
  best_contact_time text check (best_contact_time in ('morning', 'afternoon', 'evening')),
  wa_consent boolean not null default false,
  do_not_contact boolean not null default false,
  lat double precision check (lat between -90 and 90),
  lng double precision check (lng between -180 and 180),
  address text,
  maps_url text,
  instagram_url text,
  source text not null default 'visit' check (source in ('visit', 'import')),
  stage text not null default 'visited'
    check (stage in ('not_visited', 'visited', 'contacted', 'replied', 'meeting', 'proposal', 'won', 'lost')),
  score integer check (score between 0 and 100),
  priority text check (priority in ('hot', 'warm', 'cold')),
  expected_value numeric(12, 2) check (expected_value is null or expected_value >= 0),
  next_action_at timestamptz,
  lost_reason text check (lost_reason in ('price', 'not_now', 'has_marketer', 'no_reply', 'not_interested', 'other')),
  lost_note text,
  won_value numeric(12, 2) check (won_value is null or won_value >= 0),
  won_billing text check (won_billing in ('monthly', 'one_time')),
  stage_changed_at timestamptz not null default now(),
  last_contact_at timestamptz,
  constraint leads_lost_has_reason check (stage <> 'lost' or lost_reason is not null),
  constraint leads_location_pair check ((lat is null) = (lng is null))
);
create index leads_owner_stage_idx on public.leads (owner_id, stage);
create index leads_owner_phone_idx on public.leads (owner_id, phone_e164);
create index leads_owner_next_idx on public.leads (owner_id, next_action_at);

-- ---------------------------------------------------------------------------
-- visits / visit_media / assessments
-- ---------------------------------------------------------------------------
create table public.visits (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  lead_id uuid not null references public.leads (id) on delete cascade,
  visited_at timestamptz not null default now(),
  notes text,
  key_observation text check (key_observation is null or char_length(key_observation) <= 90),
  outcome text,
  next_step text,
  lat double precision check (lat between -90 and 90),
  lng double precision check (lng between -180 and 180)
);
create index visits_lead_idx on public.visits (lead_id, visited_at desc);

create table public.visit_media (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  visit_id uuid not null references public.visits (id) on delete cascade,
  storage_path text not null unique,
  kind text not null check (kind in ('photo', 'video', 'audio'))
);
create index visit_media_visit_idx on public.visit_media (visit_id);

create table public.assessments (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  lead_id uuid not null references public.leads (id) on delete cascade,
  visit_id uuid references public.visits (id) on delete set null,
  answers jsonb not null default '{}'::jsonb check (jsonb_typeof(answers) = 'object'),
  score integer not null check (score between 0 and 100),
  weaknesses jsonb not null default '[]'::jsonb check (jsonb_typeof(weaknesses) = 'array')
);
create index assessments_lead_idx on public.assessments (lead_id, created_at desc);

-- قيم الإجابات مغلقة: yes / partial / no
create or replace function public.assessment_answers_valid(a jsonb)
returns boolean
language sql
immutable
set search_path = ''
as $$
  select coalesce(bool_and(value in ('"yes"'::jsonb, '"partial"'::jsonb, '"no"'::jsonb)), true)
  from jsonb_each(a);
$$;
alter table public.assessments
  add constraint assessments_answers_closed check (public.assessment_answers_valid(answers));

-- ---------------------------------------------------------------------------
-- lead_services
-- ---------------------------------------------------------------------------
create table public.lead_services (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  lead_id uuid not null references public.leads (id) on delete cascade,
  service_id uuid not null references public.services (id) on delete cascade,
  status text not null default 'suggested' check (status in ('suggested', 'dropped')),
  note text,
  unique (lead_id, service_id)
);

-- ---------------------------------------------------------------------------
-- message_templates / messages / tasks
-- ---------------------------------------------------------------------------
create table public.message_templates (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  name text not null check (char_length(btrim(name)) > 0),
  activity_type_id uuid references public.activity_types (id) on delete set null,
  stage text check (stage in ('not_visited', 'visited', 'contacted', 'replied', 'meeting', 'proposal', 'won', 'lost')),
  kind text not null default 'first' check (kind in ('first', 'followup_3', 'followup_7', 'custom')),
  body text not null check (char_length(btrim(body)) > 0),
  usage_count integer not null default 0 check (usage_count >= 0),
  reply_count integer not null default 0 check (reply_count >= 0)
);

create table public.tasks (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  lead_id uuid not null references public.leads (id) on delete cascade,
  title text not null check (char_length(btrim(title)) > 0),
  kind text not null default 'custom'
    check (kind in ('first_message', 'followup_3', 'followup_7', 'schedule_meeting', 'meeting', 'quote_followup', 'retry', 'custom')),
  due_at timestamptz not null,
  done_at timestamptz,
  cancelled_at timestamptz
);
create index tasks_owner_due_idx on public.tasks (owner_id, due_at) where done_at is null and cancelled_at is null;
create index tasks_lead_idx on public.tasks (lead_id);

create table public.messages (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  lead_id uuid not null references public.leads (id) on delete cascade,
  kind text not null check (kind in ('first', 'followup_3', 'followup_7', 'custom')),
  body text not null,
  status text not null default 'draft' check (status in ('draft', 'sent', 'replied')),
  template_id uuid references public.message_templates (id) on delete set null,
  tone text check (tone in ('friendly', 'formal', 'short')),
  generated_by text check (generated_by in ('ai', 'fallback', 'manual')),
  task_id uuid references public.tasks (id) on delete set null,
  sent_at timestamptz,
  replied_at timestamptz,
  constraint messages_sent_has_time check (status = 'draft' or sent_at is not null)
);
create index messages_lead_idx on public.messages (lead_id, created_at desc);

-- ---------------------------------------------------------------------------
-- stage_history: يُكتب بالـ trigger فقط
-- ---------------------------------------------------------------------------
create table public.stage_history (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  lead_id uuid not null references public.leads (id) on delete cascade,
  from_stage text check (from_stage in ('not_visited', 'visited', 'contacted', 'replied', 'meeting', 'proposal', 'won', 'lost')),
  to_stage text not null check (to_stage in ('not_visited', 'visited', 'contacted', 'replied', 'meeting', 'proposal', 'won', 'lost')),
  changed_at timestamptz not null default now()
);
create index stage_history_lead_idx on public.stage_history (lead_id, changed_at desc);

-- ---------------------------------------------------------------------------
-- quotes: عرض السعر (PDF فقط، بلا رابط عام)
-- items = [{ "service_id", "name", "billing", "qty", "unit_price" }]
-- ---------------------------------------------------------------------------
create table public.quotes (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null default auth.uid() references auth.users (id) on delete cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  lead_id uuid not null references public.leads (id) on delete cascade,
  number text not null check (number ~ '^Q-[0-9]{4}-[0-9]{3,}$'),
  items jsonb not null default '[]'::jsonb check (jsonb_typeof(items) = 'array'),
  discount_pct integer not null default 0 check (discount_pct in (0, 5, 10, 15)),
  validity_days integer not null default 14 check (validity_days in (7, 14, 30)),
  notes text,
  monthly_total numeric(12, 2) not null default 0 check (monthly_total >= 0),
  once_total numeric(12, 2) not null default 0 check (once_total >= 0),
  vat_amount numeric(12, 2) not null default 0 check (vat_amount >= 0),
  total numeric(12, 2) not null default 0 check (total >= 0),
  status text not null default 'draft' check (status in ('draft', 'sent')),
  valid_until date,
  sent_at timestamptz,
  unique (owner_id, number)
);
create index quotes_lead_idx on public.quotes (lead_id);

-- updated_at triggers
do $$
declare t text;
begin
  foreach t in array array['profiles', 'activity_types', 'services', 'leads', 'visits', 'visit_media',
                           'assessments', 'lead_services', 'message_templates', 'messages', 'tasks',
                           'stage_history', 'quotes']
  loop
    execute format('create trigger set_updated_at before update on public.%I
                    for each row execute function public.set_updated_at()', t);
  end loop;
end;
$$;
