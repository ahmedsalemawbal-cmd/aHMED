-- ══════════════════════════════════════════════════════════════
-- أصولُ البناء — 0001 : البنية
--
-- والفرقُ الجوهريُّ عن مِداد أنّ هذه **شركةٌ واحدة** لا سوقُ مشتركين.
-- فلا عمودَ `subscriber_id` في شيء، ومحورُ العزل الدورُ والملكيّة:
--
--     مَن أنت؟ ومَن صاحبُ هذا الصفّ؟
--
-- وكلُّ ما هنا إضافةٌ خالصة (`if not exists`)، فتُعاد الهجرةُ بلا أثر.
-- ══════════════════════════════════════════════════════════════

create extension if not exists pgcrypto with schema extensions;

-- مخطّطٌ داخليٌّ لا يعرضه PostgREST — موضعُ الدوالّ التي تُبنى عليها
-- السياسات. وإخفاؤه ليس أمانًا بذاته، لكنّه يمنع أن يُنادى ما لم
-- يُقصد أن يُنادى.
create schema if not exists app;

-- ─────────────────── الأنواعُ المعدودة ───────────────────
-- ولمَ أنواعٌ لا نصوص؟ لأنّ السياسةَ تقارن بالقيمة، والنصُّ الحرُّ
-- يُدخل قيمةً لم يحسب لها كاتبُ السياسةِ حسابًا — `'Admin'` أو
-- `'admin '` — فتسقط في `else`. والنوعُ يمنع القيمةَ من أن توجد.

do $$ begin
  create type public.portal_role as enum
    ('admin','branch','rep','partner','customer','employee');
exception when duplicate_object then null; end $$;

do $$ begin
  -- 'osoul'  عرضٌ عاديّ (موقع/مندوب/فرع/عميل)
  -- 'supply' طلبُ توريدٍ من شريكٍ إلى أصول — «عميلُه» الشريكُ نفسُه
  -- 'cust'   عرضُ الشريك لعميله — خلفَ الجدار، لا تراه أصولُ أبدًا
  create type public.quote_kind as enum ('osoul','supply','cust');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.quote_stage as enum
    ('new','pricing','sent','negotiation','won','lost');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.quote_source as enum ('web','rep','branch','partner');
exception when duplicate_object then null; end $$;

-- ─────────────────── ① الهويّات ───────────────────
--
-- ولا جدولَ `branches` ولا `reps` منفصلًا: `branch_id` يشير إلى صفٍّ
-- في `profiles` نفسِه. والعلّةُ أنّ **الفرعَ حسابُ دخول** لا سجلٌّ
-- إداريّ — يسجّل الدخولَ ويكتب ويُحادث. فلو فُصل لصارت له هويّتان،
-- ثمّ يسأل كاتبُ السياسةِ أيَّهما يعني بـ«فرعِ هذا العرض» — وسؤالٌ
-- كهذا في سياسةِ أمانٍ عطبٌ ينتظر.
--
--     هويّةٌ واحدةٌ لصاحبِ دخولٍ واحد.

create table if not exists public.profiles (
  id            uuid primary key references auth.users(id) on delete cascade,
  role          public.portal_role not null default 'customer',
  full_name     text not null default '',
  phone         text,
  email         text,
  company       text,
  city          text,
  manager_name  text,
  branch_id     uuid references public.profiles(id) on delete set null,
  avatar_url    text,
  is_active     boolean not null default true,
  -- الشريكُ لا يعمل حتّى يعتمده المدير. ولغيره لا معنى له فيُصدَّق.
  approved      boolean not null default false,
  created_by    uuid references public.profiles(id) on delete set null,
  created_at    timestamptz not null default now(),
  updated_at    timestamptz not null default now()
);
create unique index if not exists profiles_email_key
  on public.profiles (lower(email)) where email is not null;
create index if not exists profiles_role_idx   on public.profiles (role);
create index if not exists profiles_branch_idx on public.profiles (branch_id);

-- هويّةُ الشريك التجاريّة — منها تُؤخذ اللقطةُ لحظةَ الإصدار.
create table if not exists public.partner_profiles (
  user_id      uuid primary key references public.profiles(id) on delete cascade,
  company_ar   text not null default '',
  company_en   text not null default '',
  logo_url     text not null default '',
  vat_number   text not null default '',
  cr_number    text not null default '',
  address_ar   text not null default '',
  address_en   text not null default '',
  bank_info    text not null default '',
  phone        text not null default '',
  email        text not null default '',
  terms_ar     text not null default '',
  terms_en     text not null default '',
  color        text not null default '#00344F',
  accent       text not null default '#0074A4',
  prefix       text not null default '',
  margin_min   numeric(6,2) not null default 0,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);

-- ─────────────────── ② المنتجات ───────────────────
--
-- والسعرُ ليس عمودًا هنا. المندوبُ يتصفّح المنتجات **بلا أسعار**،
-- وحجبُ عمودٍ في PostgREST لا يكون بسياسة — لأنّ RLS ترشّح الصفوفَ
-- لا الأعمدة. فلو كان `price` عمودًا لقرأه بـ`?select=price` مهما
-- صنعت الواجهة.

create table if not exists public.products (
  id          uuid primary key default gen_random_uuid(),
  slug        text not null unique,
  group_key   text not null default 'doors'
              check (group_key in ('doors','gypsum','strut')),
  name_ar     text not null default '',
  name_en     text not null default '',
  sub_ar      text not null default '',
  sub_en      text not null default '',
  desc_ar     text not null default '',
  desc_en     text not null default '',
  badge_ar    text not null default '',
  badge_en    text not null default '',
  image_url   text not null default '',
  specs_ar    text[] not null default '{}',
  specs_en    text[] not null default '{}',
  certs       text[] not null default '{}',
  is_active   boolean not null default true,
  sort        int not null default 0,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
create index if not exists products_group_idx on public.products (group_key, sort);

create table if not exists public.product_prices (
  product_id     uuid primary key references public.products(id) on delete cascade,
  list_price     numeric(12,2) not null default 0,
  partner_price  numeric(12,2) not null default 0,
  updated_at     timestamptz not null default now()
);

-- ─────────────────── ③ العروض ───────────────────
-- ولا مالَ في هذا الجدول. ما فيه: مَن، ومتى، وأيُّ نوع.

create table if not exists public.quotes (
  id            uuid primary key default gen_random_uuid(),
  kind          public.quote_kind   not null default 'osoul',
  stage         public.quote_stage  not null default 'new',
  source        public.quote_source not null default 'web',

  -- لا تُكتب إلّا من `public.issue_quote`، ويحرسها `app.quote_seal`.
  number        text unique,
  issued_at     timestamptz,
  brand         jsonb not null default '{}'::jsonb,

  token         text not null unique
                default encode(extensions.gen_random_bytes(16), 'hex'),
  lang          text not null default 'ar' check (lang in ('ar','en','ur')),

  -- في `supply` هذه بياناتُ **الشريك نفسِه** لا عميلِه — وهذا أصلُ
  -- الفصل: طلبُ التوريد لا يحمل بيانات العميل النهائيّ.
  customer_name  text not null default '',
  customer_phone text not null default '',
  customer_email text not null default '',
  customer_uid   uuid references public.profiles(id) on delete set null,

  rep_id      uuid references public.profiles(id) on delete set null,
  branch_id   uuid references public.profiles(id) on delete set null,
  partner_id  uuid references public.profiles(id) on delete set null,
  supply_id   uuid references public.quotes(id) on delete set null,

  note          text not null default '',
  terms_ar      text not null default '',
  terms_en      text not null default '',
  validity_days int  not null default 30 check (validity_days > 0),
  log           jsonb not null default '[]'::jsonb,
  archived_at   timestamptz,

  created_by  uuid references public.profiles(id) on delete set null,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now(),

  -- عرضُ شريكٍ بلا شريكٍ صفٌّ يتيمٌ خلف الجدار لا يراه أحد — لا أصولُ
  -- ولا شريك. والقيدُ يمنع وجودَه أصلًا.
  constraint quotes_partner_required
    check (kind = 'osoul' or partner_id is not null),
  constraint quotes_cust_has_supply
    check (kind <> 'cust' or supply_id is not null)
);
create index if not exists quotes_kind_idx     on public.quotes (kind, created_at desc);
create index if not exists quotes_partner_idx  on public.quotes (partner_id, kind);
create index if not exists quotes_rep_idx      on public.quotes (rep_id);
create index if not exists quotes_branch_idx   on public.quotes (branch_id);
create index if not exists quotes_customer_idx on public.quotes (customer_uid);
create index if not exists quotes_stage_idx    on public.quotes (stage);

create table if not exists public.quote_items (
  id           uuid primary key default gen_random_uuid(),
  quote_id     uuid not null references public.quotes(id) on delete cascade,
  line_no      int  not null default 0,
  product_slug text not null default '',
  name_ar      text not null default '',
  name_en      text not null default '',
  qty          int  not null default 1 check (qty > 0),
  unique (quote_id, line_no)
);
create index if not exists quote_items_quote_idx on public.quote_items (quote_id);

-- ══ الثابتُ ② — المالُ صفٌّ لا عمود ══
--
-- المطلوب: «المندوبُ لا يرى سعرًا حتّى يُصدِر المديرُ العرض». وهذا
-- شرطٌ **لكلِّ صفٍّ على حدة** (هذا صدر وذاك لم يصدر)، **ولعمودٍ دون
-- بقيّة أعمدة البند** (يرى الاسمَ والكميّةَ لا السعر). و RLS تُقرّر
-- بالصفّ لا بالعمود.
--
--     ما لا يُصاغ صفًّا لا تحكمه سياسةُ صفوف.

create table if not exists public.quote_prices (
  item_id     uuid primary key references public.quote_items(id) on delete cascade,
  -- التكرارُ مقصود: السياسةُ تحتاج `quote_id` بلا وصلٍ إلى `quote_items`،
  -- ووصلٌ داخل سياسةٍ يُقيَّم لكلّ صفّ.
  quote_id    uuid not null references public.quotes(id) on delete cascade,
  unit_price  numeric(12,2) not null default 0,
  -- تكلفةُ الشريك على بند عرضِه لعميله — ربحُه، ولا يراه إلّا هو.
  cost_price  numeric(12,2) not null default 0,
  updated_at  timestamptz not null default now()
);
create index if not exists quote_prices_quote_idx on public.quote_prices (quote_id);

-- مالُ الترويسة. جدولٌ ثانٍ لأنّ حبّتَه غيرُ حبّةِ البند: صفٌّ للعرض
-- كلِّه لا للبند. وضمُّهما يُلجئ إلى صفٍّ وهميٍّ برقم بندٍ صفر.
create table if not exists public.quote_money (
  quote_id        uuid primary key references public.quotes(id) on delete cascade,
  discount_amount numeric(12,2) not null default 0 check (discount_amount >= 0),
  vat_rate        numeric(5,2)  not null default 15,
  updated_at      timestamptz not null default now()
);

-- ─────────── ④ العدّاد — لكلِّ جهةٍ عدّادُها، يُصفَّر سنويًّا ───────────
-- ولمَ جدولٌ لا `sequence`؟ لأنّ المطلوب عدّادًا لكلِّ شريكٍ على حدة
-- يُصفَّر كلَّ سنة، والتسلسلُ لا يُصفَّر في معاملةٍ ولا يُنشأ لكلّ
-- شريكٍ إلّا بـDDL. والصفُّ يُصفَّر بمفتاحٍ جديد: (الجهة، السنة).
create table if not exists public.quote_counters (
  scope_id uuid not null default '00000000-0000-0000-0000-000000000000'::uuid,
  year     int  not null,
  n        int  not null default 0,
  primary key (scope_id, year)
);

-- ─────────────────── ⑤ الطلباتُ والعملاء ───────────────────

create table if not exists public.leads (
  id          uuid primary key default gen_random_uuid(),
  name        text not null default '',
  phone       text not null default '',
  email       text not null default '',
  subject     text not null default '',
  message     text not null default '',
  lang        text not null default 'ar',
  source      text not null default 'contact',
  items       jsonb not null default '[]'::jsonb,
  user_agent  text not null default '',
  handled     boolean not null default false,
  created_at  timestamptz not null default now()
);
create index if not exists leads_created_idx on public.leads (created_at desc);

-- جهاتُ الاتّصال — ملفُّ العميل ٣٦٠.
-- و`owner_partner_id` هو الجدارُ هنا: فارغٌ = جهةُ أصول، ومملوءٌ =
-- جهةُ شريكٍ لا يراها إلّا هو. وهذا يحلّ ما حلّه الاصطلاحُ النصّيُّ
-- `partner:{id}` في ووردبريس، لكن **بمفتاحٍ أجنبيٍّ تفحصه القاعدة**
-- لا بنصٍّ يُشتقّ بالتقطيع.
create table if not exists public.contacts (
  id               uuid primary key default gen_random_uuid(),
  owner_partner_id uuid references public.profiles(id) on delete cascade,
  ckey             text not null,          -- جوّالٌ مُطبَّع، ثمّ بريد، ثمّ اسم
  display_name     text not null default '',
  company          text not null default '',
  city             text not null default '',
  project_type     text not null default '',
  source           text not null default '',
  tags             text[] not null default '{}',
  created_at       timestamptz not null default now(),
  updated_at       timestamptz not null default now()
);
create unique index if not exists contacts_scope_key
  on public.contacts (coalesce(owner_partner_id,
       '00000000-0000-0000-0000-000000000000'::uuid), ckey);

create table if not exists public.contact_notes (
  id         uuid primary key default gen_random_uuid(),
  contact_id uuid not null references public.contacts(id) on delete cascade,
  author_id  uuid references public.profiles(id) on delete set null,
  body       text not null default '',
  created_at timestamptz not null default now()
);
create index if not exists contact_notes_contact_idx
  on public.contact_notes (contact_id, created_at desc);

-- ─────────────────── ⑥ المحادثة ───────────────────
-- خيطٌ واحدٌ لكلّ حساب، طرفاه المديرُ وصاحبُ الحساب.

create table if not exists public.chat_threads (
  id              uuid primary key default gen_random_uuid(),
  account_id      uuid not null unique references public.profiles(id) on delete cascade,
  admin_read_at   timestamptz,
  account_read_at timestamptz,
  last_message_at timestamptz not null default now(),
  created_at      timestamptz not null default now()
);

create table if not exists public.chat_messages (
  id          uuid primary key default gen_random_uuid(),
  thread_id   uuid not null references public.chat_threads(id) on delete cascade,
  sender_id   uuid references public.profiles(id) on delete set null,
  from_side   text not null default 'account' check (from_side in ('admin','account')),
  body        text not null default '',
  image_url   text not null default '',
  created_at  timestamptz not null default now()
);
create index if not exists chat_messages_thread_idx
  on public.chat_messages (thread_id, created_at);

-- ─────────────────── ⑦ بريدُ الموظّفين ───────────────────
-- على نمط `ai` و`ai_secret` في مِداد: صفّان لا واحد.
--   `employee_mailboxes`       — العنوانُ والخوادمُ وهل ضُبطت.
--   `employee_mailbox_secrets` — كلمةُ المرور. لا يقرؤها أحدٌ من
--                                متصفّح، ولا المديرُ، ولا صاحبُها.
--
--     ما لا يُقرأ لا يُسرَق.

create table if not exists public.employee_mailboxes (
  id           uuid primary key default gen_random_uuid(),
  user_id      uuid not null unique references public.profiles(id) on delete cascade,
  address      text not null,
  display_name text not null default '',
  signature    text not null default '',
  imap_host    text not null default 'imap.hostinger.com',
  imap_port    int  not null default 993,
  smtp_host    text not null default 'smtp.hostinger.com',
  smtp_port    int  not null default 465,
  configured   boolean not null default false,
  -- آخرُ حرفين وحدهما: يكفيان صاحبَها ليعرف أيَّ كلمةٍ وضع، ولا
  -- يكفيان أحدًا ليستعملها.
  hint         text not null default '',
  is_active    boolean not null default true,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);

create table if not exists public.employee_mailbox_secrets (
  mailbox_id   uuid primary key
               references public.employee_mailboxes(id) on delete cascade,
  password_enc bytea not null,
  updated_at   timestamptz not null default now()
);

-- ─────────────────── ⑧ الإعدادات ───────────────────
-- مفتاحٌ وقيمةُ jsonb، كـ`platform_settings` في مِداد. وكلُّ مفتاحٍ
-- ينتهي بـ`_secret` لا يُقرأ ألبتّة — لا بدورٍ ولا بصلاحيّة.
create table if not exists public.settings (
  key        text primary key,
  value      jsonb not null default '{}'::jsonb,
  updated_at timestamptz not null default now()
);
