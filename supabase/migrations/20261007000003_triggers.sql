-- ميداني · سلامة البيانات
-- 1) كل مرجع إلى سجل آخر يجب أن يكون لنفس المالك.
-- 2) كل تغيير مرحلة يُسجَّل في stage_history ويحدّث stage_changed_at.
-- 3) رقم عرض السعر Q-YYYY-NNN تسلسلي لكل مالك وسنة (توقيت الرياض).

-- ---------------------------------------------------------------------------
-- 1) تطابق المالك
-- الوسائط أزواج: (اسم العمود، الجدول المرجعي)
-- ---------------------------------------------------------------------------
create or replace function public.enforce_same_owner()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
declare
  i integer := 0;
  col text;
  ref_table text;
  ref_id uuid;
  found boolean;
begin
  while i < tg_nargs loop
    col := tg_argv[i];
    ref_table := tg_argv[i + 1];
    ref_id := (to_jsonb(new) ->> col)::uuid;
    if ref_id is not null then
      execute format('select exists (select 1 from public.%I where id = $1 and owner_id = $2)', ref_table)
        into found using ref_id, new.owner_id;
      if not found then
        raise exception 'المرجع %.% غير موجود أو لا يخص هذا المستخدم', tg_table_name, col
          using errcode = '23503';
      end if;
    end if;
    i := i + 2;
  end loop;
  return new;
end;
$$;

create trigger same_owner before insert or update on public.leads
  for each row execute function public.enforce_same_owner('activity_type_id', 'activity_types');
create trigger same_owner before insert or update on public.visits
  for each row execute function public.enforce_same_owner('lead_id', 'leads');
create trigger same_owner before insert or update on public.visit_media
  for each row execute function public.enforce_same_owner('visit_id', 'visits');
create trigger same_owner before insert or update on public.assessments
  for each row execute function public.enforce_same_owner('lead_id', 'leads', 'visit_id', 'visits');
create trigger same_owner before insert or update on public.lead_services
  for each row execute function public.enforce_same_owner('lead_id', 'leads', 'service_id', 'services');
create trigger same_owner before insert or update on public.message_templates
  for each row execute function public.enforce_same_owner('activity_type_id', 'activity_types');
create trigger same_owner before insert or update on public.tasks
  for each row execute function public.enforce_same_owner('lead_id', 'leads');
create trigger same_owner before insert or update on public.messages
  for each row execute function public.enforce_same_owner('lead_id', 'leads', 'template_id', 'message_templates', 'task_id', 'tasks');
create trigger same_owner before insert or update on public.quotes
  for each row execute function public.enforce_same_owner('lead_id', 'leads');

-- المصفوفات: معرّفات الخدمات والأنشطة يجب أن تخص المالك نفسه
create or replace function public.enforce_activity_services_owner()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
  if exists (
    select 1 from unnest(new.default_service_ids) as s(id)
    where not exists (select 1 from public.services v where v.id = s.id and v.owner_id = new.owner_id)
  ) then
    raise exception 'خدمة افتراضية لا تخص هذا المستخدم' using errcode = '23503';
  end if;
  return new;
end;
$$;

create or replace function public.enforce_service_activities_owner()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
  if exists (
    select 1 from unnest(new.activity_type_ids) as a(id)
    where not exists (select 1 from public.activity_types t where t.id = a.id and t.owner_id = new.owner_id)
  ) then
    raise exception 'نشاط لا يخص هذا المستخدم' using errcode = '23503';
  end if;
  return new;
end;
$$;

create trigger same_owner_arrays before insert or update of default_service_ids on public.activity_types
  for each row execute function public.enforce_activity_services_owner();
create trigger same_owner_arrays before insert or update of activity_type_ids on public.services
  for each row execute function public.enforce_service_activities_owner();

-- ---------------------------------------------------------------------------
-- 2) سجل المراحل
-- ---------------------------------------------------------------------------
create or replace function public.leads_stage_before()
returns trigger
language plpgsql
set search_path = ''
as $$
begin
  if tg_op = 'INSERT' or new.stage is distinct from old.stage then
    new.stage_changed_at := now();
  end if;
  return new;
end;
$$;

create or replace function public.leads_stage_after()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
  if tg_op = 'INSERT' then
    insert into public.stage_history (owner_id, lead_id, from_stage, to_stage, changed_at)
    values (new.owner_id, new.id, null, new.stage, new.stage_changed_at);
  elsif new.stage is distinct from old.stage then
    insert into public.stage_history (owner_id, lead_id, from_stage, to_stage, changed_at)
    values (new.owner_id, new.id, old.stage, new.stage, new.stage_changed_at);
  end if;
  return null;
end;
$$;

create trigger stage_before before insert or update of stage on public.leads
  for each row execute function public.leads_stage_before();
create trigger stage_after after insert or update of stage on public.leads
  for each row execute function public.leads_stage_after();

-- ---------------------------------------------------------------------------
-- 3) رقم عرض السعر
-- ---------------------------------------------------------------------------
create or replace function public.quotes_assign_number()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
declare
  yr text := to_char(now() at time zone 'Asia/Riyadh', 'YYYY');
  next_n integer;
begin
  if new.number is null or new.number = '' then
    perform pg_advisory_xact_lock(hashtext('quotes:' || new.owner_id::text || ':' || yr));
    select coalesce(max(split_part(number, '-', 3)::integer), 0) + 1 into next_n
    from public.quotes
    where owner_id = new.owner_id and split_part(number, '-', 2) = yr;
    new.number := 'Q-' || yr || '-' || lpad(next_n::text, 3, '0');
  end if;
  return new;
end;
$$;

create trigger assign_number before insert on public.quotes
  for each row execute function public.quotes_assign_number();

-- دوال الـ trigger لا تُستدعى مباشرة
revoke execute on function public.enforce_same_owner() from public, anon, authenticated;
revoke execute on function public.enforce_activity_services_owner() from public, anon, authenticated;
revoke execute on function public.enforce_service_activities_owner() from public, anon, authenticated;
revoke execute on function public.leads_stage_after() from public, anon, authenticated;
revoke execute on function public.quotes_assign_number() from public, anon, authenticated;
