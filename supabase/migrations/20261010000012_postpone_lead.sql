-- ميداني · «أجّل 30 يوماً» (التدفق 2.4، المرحلة 1f).
-- انتهت متابعة اليوم السابع بلا رد: تُلغى متابعات العميل المفتوحة وتُنشأ مهمة «أعد المحاولة»
-- في الموعد الذي يحسبه التطبيق (بعد 30 يوماً، 10:00 بتوقيت الرياض). معاملة واحدة بصلاحية
-- المستخدم (RLS). المهام لا تُحذف: تُلغى بـ cancelled_at.
create or replace function public.postpone_lead(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  l public.leads%rowtype;
  v_now timestamptz := now();
  v_retry timestamptz := nullif(p ->> 'retry_at', '')::timestamptz;
  cancelled uuid[] := '{}';
  tid uuid;
begin
  if v_retry is null or v_retry <= v_now then
    raise exception 'اختر موعداً قادماً لإعادة المحاولة' using errcode = '22023';
  end if;
  select * into l from public.leads where id = (p ->> 'lead_id')::uuid;
  if not found then
    raise exception 'العميل غير موجود' using errcode = 'P0002';
  end if;
  if l.stage in ('won', 'lost') then
    raise exception 'العميل في مرحلة مغلقة. غيّر المرحلة أولاً.' using errcode = '22023';
  end if;
  if l.do_not_contact then
    raise exception 'العميل طلب عدم التواصل' using errcode = '22023';
  end if;

  -- المتابعات التي لم تُنجز تتوقف؛ الاجتماعات والعروض تبقى كما هي
  with c as (
    update public.tasks set cancelled_at = v_now
    where lead_id = l.id and done_at is null and cancelled_at is null
      and kind in ('first_message', 'followup_3', 'followup_7')
    returning id
  )
  select coalesce(array_agg(id), '{}') into cancelled from c;

  -- «أعد المحاولة» واحدة لكل عميل: الضغط مرتين ينقل الموعد ولا يكرر المهمة
  update public.tasks set due_at = v_retry
  where id = (select t.id from public.tasks t
              where t.lead_id = l.id and t.kind = 'retry' and t.done_at is null and t.cancelled_at is null
              order by t.created_at limit 1)
  returning id into tid;
  if tid is null then
    insert into public.tasks (lead_id, title, kind, due_at)
    values (l.id, 'أعد المحاولة', 'retry', v_retry)
    returning id into tid;
  end if;

  perform public.refresh_next_action(l.id);
  return jsonb_build_object('lead_id', l.id, 'task_id', tid, 'due_at', v_retry, 'cancelled_task_ids', to_jsonb(cancelled));
end;
$$;

revoke execute on function public.postpone_lead(jsonb) from public, anon;
grant execute on function public.postpone_lead(jsonb) to authenticated;
