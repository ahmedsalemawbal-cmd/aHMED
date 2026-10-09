-- ميداني · إجراءات المراحل من صفحة العميل وقائمة العملاء (المرحلة 1e).
-- كل إجراء معاملة واحدة بصلاحية المستخدم (RLS)، ويسجّل trigger المراحل كل تغيير في stage_history.
-- المهام لا تُحذف أبداً: تُلغى بـ cancelled_at فيبقى أثرها في الخط الزمني.

-- أقرب مهمة مفتوحة = الإجراء القادم
create or replace function public.refresh_next_action(p_lead uuid)
returns void
language sql
security invoker
set search_path = ''
as $$
  update public.leads
  set next_action_at = (select min(t.due_at) from public.tasks t
                        where t.lead_id = p_lead and t.done_at is null and t.cancelled_at is null)
  where id = p_lead;
$$;

-- «العميل رد» (التدفق 2.3): تُلغى المتابعات الباقية، يصبح حاراً، وتُقترح مهمة «حدد اجتماعاً».
create or replace function public.mark_replied(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  l public.leads%rowtype;
  v_now timestamptz := now();
  cancelled uuid[] := '{}';
  tid uuid;
begin
  select * into l from public.leads where id = (p ->> 'lead_id')::uuid;
  if not found then
    raise exception 'العميل غير موجود' using errcode = 'P0002';
  end if;
  if l.stage in ('won', 'lost') then
    raise exception 'العميل في مرحلة مغلقة. غيّر المرحلة أولاً.' using errcode = '22023';
  end if;

  -- آخر رسالة مرسلة هي التي رُدّ عليها (لنسبة الرد)
  update public.messages set status = 'replied', replied_at = v_now
  where id = (select m.id from public.messages m
              where m.lead_id = l.id and m.status = 'sent'
              order by m.sent_at desc limit 1);

  with c as (
    update public.tasks set cancelled_at = v_now
    where lead_id = l.id and done_at is null and cancelled_at is null
      and kind in ('first_message', 'followup_3', 'followup_7', 'retry')
    returning id
  )
  select coalesce(array_agg(id), '{}') into cancelled from c;

  update public.leads
  set stage = case when stage in ('not_visited', 'visited', 'contacted') then 'replied' else stage end,
      priority = 'hot',
      last_contact_at = v_now
  where id = l.id;

  if not exists (select 1 from public.tasks t
                 where t.lead_id = l.id and t.kind in ('schedule_meeting', 'meeting')
                   and t.done_at is null and t.cancelled_at is null) then
    insert into public.tasks (lead_id, title, kind, due_at)
    values (l.id, 'حدد اجتماعاً', 'schedule_meeting', coalesce((p ->> 'due_at')::timestamptz, v_now))
    returning id into tid;
  end if;

  perform public.refresh_next_action(l.id);
  return jsonb_build_object('lead_id', l.id, 'stage_before', l.stage, 'cancelled_task_ids', to_jsonb(cancelled), 'task_id', tid);
end;
$$;

-- موعد اجتماع (التدفق 3.1): مهمة تذكير بالموعد، وتُغلق «حدد اجتماعاً».
create or replace function public.set_meeting(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  l public.leads%rowtype;
  v_now timestamptz := now();
  v_at timestamptz := (p ->> 'at')::timestamptz;
  tid uuid;
begin
  select * into l from public.leads where id = (p ->> 'lead_id')::uuid;
  if not found then
    raise exception 'العميل غير موجود' using errcode = 'P0002';
  end if;
  if v_at is null then
    raise exception 'حدد موعد الاجتماع' using errcode = '22023';
  end if;

  update public.tasks set done_at = v_now
  where lead_id = l.id and kind = 'schedule_meeting' and done_at is null and cancelled_at is null;
  update public.tasks set cancelled_at = v_now
  where lead_id = l.id and kind in ('first_message', 'followup_3', 'followup_7', 'retry')
    and done_at is null and cancelled_at is null;

  insert into public.tasks (lead_id, title, kind, due_at)
  values (l.id, coalesce(nullif(btrim(p ->> 'title'), ''), 'اجتماع'), 'meeting', v_at)
  returning id into tid;

  update public.leads
  set stage = case when stage in ('not_visited', 'visited', 'contacted', 'replied', 'lost') then 'meeting' else stage end,
      lost_reason = null, lost_note = null
  where id = l.id;

  perform public.refresh_next_action(l.id);
  return jsonb_build_object('lead_id', l.id, 'stage_before', l.stage, 'task_id', tid);
end;
$$;

-- الإغلاق (التدفق 3.4): قيمة الصفقة ونوعها، وتُلغى مهام البيع المفتوحة.
create or replace function public.mark_won(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  l public.leads%rowtype;
  v_value numeric := (p ->> 'value')::numeric;
  v_billing text := p ->> 'billing';
begin
  select * into l from public.leads where id = (p ->> 'lead_id')::uuid;
  if not found then
    raise exception 'العميل غير موجود' using errcode = 'P0002';
  end if;
  if v_value is null or v_value < 0 or v_billing not in ('monthly', 'one_time') then
    raise exception 'اكتب قيمة الصفقة واختر نوعها' using errcode = '22023';
  end if;

  update public.tasks set cancelled_at = now()
  where lead_id = l.id and done_at is null and cancelled_at is null and kind <> 'custom';

  update public.leads
  set stage = 'won', won_value = v_value, won_billing = v_billing, lost_reason = null, lost_note = null
  where id = l.id;

  perform public.refresh_next_action(l.id);
  return jsonb_build_object('lead_id', l.id, 'stage_before', l.stage);
end;
$$;

-- الخسارة (التدفق 4): سبب مغلق، ملاحظة اختيارية، وتاريخ «أعد المحاولة» ينشئ مهمة مؤجلة.
-- تقبل عدة عملاء (التحديد المتعدد في جدول العملاء).
create or replace function public.mark_lost(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  v_reason text := p ->> 'reason';
  v_retry timestamptz := nullif(p ->> 'retry_at', '')::timestamptz;
  v_ids uuid[];
  v_id uuid;
  n integer := 0;
begin
  if v_reason not in ('price', 'not_now', 'has_marketer', 'no_reply', 'not_interested', 'other') then
    raise exception 'اختر سبب الخسارة' using errcode = '22023';
  end if;
  select coalesce(array_agg(value::uuid), '{}') into v_ids
  from jsonb_array_elements_text(coalesce(p -> 'lead_ids', jsonb_build_array(p ->> 'lead_id')));

  foreach v_id in array v_ids loop
    update public.leads
    set stage = 'lost', lost_reason = v_reason, lost_note = nullif(btrim(p ->> 'note'), '')
    where id = v_id;
    if not found then
      raise exception 'العميل غير موجود' using errcode = 'P0002';
    end if;
    update public.tasks set cancelled_at = now()
    where lead_id = v_id and done_at is null and cancelled_at is null;
    if v_retry is not null then
      insert into public.tasks (lead_id, title, kind, due_at) values (v_id, 'أعد المحاولة', 'retry', v_retry);
    end if;
    perform public.refresh_next_action(v_id);
    n := n + 1;
  end loop;
  return jsonb_build_object('updated', n);
end;
$$;

-- تغيير المرحلة المباشر (لم يُزر، تمت الزيارة، تم الإرسال، عرض سعر). الرد والاجتماع والإغلاق
-- والخسارة لها إجراءاتها لأنها تنشئ مهام أو تحتاج بيانات.
create or replace function public.set_stage(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  v_stage text := p ->> 'stage';
  n integer;
begin
  if v_stage not in ('not_visited', 'visited', 'contacted', 'proposal') then
    raise exception 'هذه المرحلة لها إجراء خاص' using errcode = '22023';
  end if;
  with u as (
    update public.leads
    set stage = v_stage, lost_reason = null, lost_note = null, won_value = null, won_billing = null
    where id in (select value::uuid
                 from jsonb_array_elements_text(coalesce(p -> 'lead_ids', jsonb_build_array(p ->> 'lead_id'))))
      and stage <> v_stage
    returning id
  )
  select count(*) into n from u;
  return jsonb_build_object('updated', n);
end;
$$;

-- «عدم التواصل»: لا متابعات لعميل طلب ذلك (brief.md)، فتُلغى المفتوحة عند تفعيله.
create or replace function public.leads_dnc_cancel_tasks()
returns trigger
language plpgsql
security invoker
set search_path = ''
as $$
begin
  if new.do_not_contact and not old.do_not_contact then
    update public.tasks set cancelled_at = now()
    where lead_id = new.id and done_at is null and cancelled_at is null
      and kind in ('first_message', 'followup_3', 'followup_7', 'quote_followup', 'retry');
    new.next_action_at := (select min(t.due_at) from public.tasks t
                           where t.lead_id = new.id and t.done_at is null and t.cancelled_at is null);
  end if;
  return new;
end;
$$;

create trigger leads_dnc_cancel_tasks
before update of do_not_contact on public.leads
for each row execute function public.leads_dnc_cancel_tasks();

revoke execute on function public.refresh_next_action(uuid) from public, anon;
revoke execute on function public.mark_replied(jsonb) from public, anon;
revoke execute on function public.set_meeting(jsonb) from public, anon;
revoke execute on function public.mark_won(jsonb) from public, anon;
revoke execute on function public.mark_lost(jsonb) from public, anon;
revoke execute on function public.set_stage(jsonb) from public, anon;
revoke execute on function public.leads_dnc_cancel_tasks() from public, anon;
grant execute on function public.refresh_next_action(uuid) to authenticated;
grant execute on function public.mark_replied(jsonb) to authenticated;
grant execute on function public.set_meeting(jsonb) to authenticated;
grant execute on function public.mark_won(jsonb) to authenticated;
grant execute on function public.mark_lost(jsonb) to authenticated;
grant execute on function public.set_stage(jsonb) to authenticated;
