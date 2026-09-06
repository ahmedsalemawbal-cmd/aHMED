-- ============================================================
-- 0002 — دوال المساعدة وسياسات RLS
-- العزل مضمونٌ في القاعدة: مشتركٌ لا يرى بيانات مشتركٍ آخر
-- حتى لو نادى الـ API مباشرةً بمفتاح anon.
-- ============================================================

create or replace function app.subscriber_id() returns uuid
language sql stable security definer set search_path = public, auth as $$
  select subscriber_id from public.profiles where id = auth.uid()
$$;

create or replace function app.is_admin() returns boolean
language sql stable security definer set search_path = public, auth as $$
  select exists(select 1 from public.platform_admins where user_id = auth.uid())
$$;

create or replace function app.is_owner() returns boolean
language sql stable security definer set search_path = public, auth as $$
  select coalesce((select is_owner from public.profiles where id = auth.uid()), false)
$$;

create or replace function app.member_active() returns boolean
language sql stable security definer set search_path = public, auth as $$
  select coalesce((select status = 'active' from public.profiles where id = auth.uid()), false)
$$;

-- الحالة المحسوبة — التجربة تنتهي بمرور الوقت لا بتغيّر العمود
create or replace function public.subscriber_state(sub public.subscribers) returns text
language sql stable set search_path = public as $$
  select case
    when sub.status = 'suspended' then 'suspended'
    when sub.status = 'active'    then 'active'
    when sub.status = 'expired'   then 'expired'
    when sub.status = 'trial' and sub.trial_ends_at > now() then 'trial'
    else 'expired'
  end
$$;

create or replace function public.trial_days_left(sub public.subscribers) returns int
language sql stable set search_path = public as $$
  select greatest(0, ceil(extract(epoch from (sub.trial_ends_at - now())) / 86400.0)::int)
$$;

create or replace function app.can_write() returns boolean
language sql stable security definer set search_path = public, auth as $$
  select exists(
    select 1 from public.subscribers s
    join public.profiles p on p.subscriber_id = s.id
    where p.id = auth.uid()
      and p.status = 'active'
      and public.subscriber_state(s) in ('trial','active')
  )
$$;

grant usage on schema app to authenticated, anon;
grant execute on all functions in schema app to authenticated, anon;

create or replace function app.touch_updated_at() returns trigger
language plpgsql set search_path = '' as $$
begin new.updated_at = now(); return new; end $$;

do $$
declare t text;
begin
  foreach t in array array['plans','subscribers','profiles','templates','documents'] loop
    execute format('drop trigger if exists trg_touch_%1$s on public.%1$s', t);
    execute format('create trigger trg_touch_%1$s before update on public.%1$s for each row execute function app.touch_updated_at()', t);
  end loop;
end $$;

-- كلّ الجداول محميّة — بلا استثناء
alter table public.roles              enable row level security;
alter table public.plans              enable row level security;
alter table public.subscribers        enable row level security;
alter table public.profiles           enable row level security;
alter table public.platform_admins    enable row level security;
alter table public.subscriptions      enable row level security;
alter table public.invoices           enable row level security;
alter table public.templates          enable row level security;
alter table public.documents          enable row level security;
alter table public.link_keys          enable row level security;
alter table public.noor_tables        enable row level security;
alter table public.noor_ingest_log    enable row level security;
alter table public.ai_usage           enable row level security;
alter table public.platform_settings  enable row level security;
alter table public.audit_log          enable row level security;
alter table public.contact_messages   enable row level security;
alter table public.push_tokens        enable row level security;

drop policy if exists roles_read on public.roles;
create policy roles_read on public.roles for select using (true);
drop policy if exists roles_admin on public.roles;
create policy roles_admin on public.roles for all using (app.is_admin()) with check (app.is_admin());

drop policy if exists plans_read on public.plans;
create policy plans_read on public.plans for select using (is_active or app.is_admin());
drop policy if exists plans_admin on public.plans;
create policy plans_admin on public.plans for all using (app.is_admin()) with check (app.is_admin());

drop policy if exists subscribers_read on public.subscribers;
create policy subscribers_read on public.subscribers for select
  using (id = app.subscriber_id() or app.is_admin());
drop policy if exists subscribers_owner_update on public.subscribers;
create policy subscribers_owner_update on public.subscribers for update
  using (id = app.subscriber_id() and app.is_owner())
  with check (id = app.subscriber_id() and app.is_owner());
drop policy if exists subscribers_admin on public.subscribers;
create policy subscribers_admin on public.subscribers for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists profiles_read on public.profiles;
create policy profiles_read on public.profiles for select
  using (id = auth.uid() or subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists profiles_self_update on public.profiles;
create policy profiles_self_update on public.profiles for update
  using (id = auth.uid()) with check (id = auth.uid());
drop policy if exists profiles_owner_manage on public.profiles;
create policy profiles_owner_manage on public.profiles for update
  using (subscriber_id = app.subscriber_id() and app.is_owner())
  with check (subscriber_id = app.subscriber_id() and app.is_owner());
drop policy if exists profiles_admin on public.profiles;
create policy profiles_admin on public.profiles for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists platform_admins_self on public.platform_admins;
create policy platform_admins_self on public.platform_admins for select
  using (user_id = auth.uid() or app.is_admin());

drop policy if exists subscriptions_read on public.subscriptions;
create policy subscriptions_read on public.subscriptions for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists subscriptions_admin on public.subscriptions;
create policy subscriptions_admin on public.subscriptions for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists invoices_read on public.invoices;
create policy invoices_read on public.invoices for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists invoices_admin on public.invoices;
create policy invoices_admin on public.invoices for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists templates_read on public.templates;
create policy templates_read on public.templates for select
  using (status = 'published' or app.is_admin());
drop policy if exists templates_admin on public.templates;
create policy templates_admin on public.templates for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists documents_read on public.documents;
create policy documents_read on public.documents for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists documents_insert on public.documents;
create policy documents_insert on public.documents for insert
  with check (subscriber_id = app.subscriber_id() and owner_id = auth.uid() and app.can_write());
drop policy if exists documents_update on public.documents;
create policy documents_update on public.documents for update
  using (subscriber_id = app.subscriber_id() and app.can_write())
  with check (subscriber_id = app.subscriber_id());
drop policy if exists documents_delete on public.documents;
create policy documents_delete on public.documents for delete
  using (subscriber_id = app.subscriber_id() and (owner_id = auth.uid() or app.is_owner()));
drop policy if exists documents_admin on public.documents;
create policy documents_admin on public.documents for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists link_keys_own on public.link_keys;
create policy link_keys_own on public.link_keys for all
  using (user_id = auth.uid() or app.is_admin())
  with check (user_id = auth.uid() and subscriber_id = app.subscriber_id());

drop policy if exists noor_tables_read on public.noor_tables;
create policy noor_tables_read on public.noor_tables for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists noor_tables_write on public.noor_tables;
create policy noor_tables_write on public.noor_tables for insert
  with check (subscriber_id = app.subscriber_id() and owner_id = auth.uid() and app.can_write());
drop policy if exists noor_tables_update on public.noor_tables;
create policy noor_tables_update on public.noor_tables for update
  using (subscriber_id = app.subscriber_id() and app.can_write())
  with check (subscriber_id = app.subscriber_id());
drop policy if exists noor_tables_delete on public.noor_tables;
create policy noor_tables_delete on public.noor_tables for delete
  using (subscriber_id = app.subscriber_id() and (owner_id = auth.uid() or app.is_owner()));

drop policy if exists noor_log_read on public.noor_ingest_log;
create policy noor_log_read on public.noor_ingest_log for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());

drop policy if exists ai_usage_read on public.ai_usage;
create policy ai_usage_read on public.ai_usage for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());

-- المفاتيح السرّية (ميسر والذكاء) لا تُقرأ إلّا بصلاحية مالك المنصّة
drop policy if exists settings_public_read on public.platform_settings;
create policy settings_public_read on public.platform_settings for select
  using (key in ('general','payment_public','trial') or app.is_admin());
drop policy if exists settings_admin on public.platform_settings;
create policy settings_admin on public.platform_settings for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists audit_read on public.audit_log;
create policy audit_read on public.audit_log for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists audit_admin on public.audit_log;
create policy audit_admin on public.audit_log for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists contact_insert on public.contact_messages;
create policy contact_insert on public.contact_messages for insert with check (true);
drop policy if exists contact_admin on public.contact_messages;
create policy contact_admin on public.contact_messages for select using (app.is_admin());

drop policy if exists push_own on public.push_tokens;
create policy push_own on public.push_tokens for all
  using (user_id = auth.uid()) with check (user_id = auth.uid());
