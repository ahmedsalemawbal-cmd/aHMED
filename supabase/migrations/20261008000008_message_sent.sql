-- ميداني · تأكيد «هل أرسلت؟» والتراجع عنه، في معاملة واحدة وبصلاحية المستخدم.
-- الإرسال يُسجَّل فقط بعد تأكيد المستخدم (CLAUDE.md).

create or replace function public.confirm_message_sent(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  m public.messages%rowtype;
  l public.leads%rowtype;
  v_now timestamptz := now();
  f jsonb;
  tid uuid;
  ids uuid[] := '{}';
  done_ids uuid[] := '{}';
begin
  select * into m from public.messages where id = (p ->> 'message_id')::uuid;
  if not found then
    raise exception 'الرسالة غير موجودة' using errcode = 'P0002';
  end if;
  select * into l from public.leads where id = m.lead_id;
  if m.status <> 'draft' then
    return jsonb_build_object('message_id', m.id, 'lead_id', m.lead_id, 'already_sent', true);
  end if;

  update public.messages
  set status = 'sent', sent_at = v_now,
      body = coalesce(nullif(p ->> 'body', ''), body),
      tone = coalesce(nullif(p ->> 'tone', ''), tone)
  where id = m.id;

  -- the task this message answers, and the open «أرسل الرسالة الأولى» for a first message
  with closed as (
    update public.tasks set done_at = v_now
    where done_at is null and cancelled_at is null
      and (id = m.task_id or (m.kind = 'first' and lead_id = m.lead_id and kind = 'first_message'))
    returning id
  )
  select coalesce(array_agg(id), '{}') into done_ids from closed;

  if m.kind = 'first' and l.stage in ('not_visited', 'visited') then
    update public.leads set stage = 'contacted' where id = l.id;
  end if;

  -- follow-ups (none for do_not_contact)
  if not l.do_not_contact then
    for f in select value from jsonb_array_elements(coalesce(p -> 'followups', '[]'::jsonb)) loop
      if (f ->> 'kind') not in ('followup_3', 'followup_7', 'quote_followup') then
        raise exception 'نوع متابعة غير مسموح: %', f ->> 'kind' using errcode = '22023';
      end if;
      insert into public.tasks (lead_id, title, kind, due_at)
      values (l.id, f ->> 'title', f ->> 'kind', (f ->> 'due_at')::timestamptz)
      returning id into tid;
      ids := ids || tid;
    end loop;
  end if;

  if m.template_id is not null then
    update public.message_templates set usage_count = usage_count + 1 where id = m.template_id;
  end if;

  update public.leads
  set last_contact_at = v_now,
      next_action_at = (select min(t.due_at) from public.tasks t
                        where t.lead_id = l.id and t.done_at is null and t.cancelled_at is null)
  where id = l.id;

  return jsonb_build_object(
    'message_id', m.id, 'lead_id', l.id, 'already_sent', false,
    'stage_before', l.stage, 'created_task_ids', to_jsonb(ids), 'closed_task_ids', to_jsonb(done_ids),
    'last_contact_before', l.last_contact_at);
end;
$$;

-- «تراجع» من إشعار النجاح: يعيد الرسالة مسودة والمرحلة والمهام كما كانت.
create or replace function public.undo_message_sent(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  m public.messages%rowtype;
  v_lead uuid;
begin
  select * into m from public.messages where id = (p ->> 'message_id')::uuid;
  if not found then
    raise exception 'الرسالة غير موجودة' using errcode = 'P0002';
  end if;
  if m.status <> 'sent' then
    return jsonb_build_object('message_id', m.id, 'undone', false);
  end if;
  v_lead := m.lead_id;

  update public.messages set status = 'draft', sent_at = null where id = m.id;

  -- المتابعات التي أنشأها التأكيد تُلغى (تبقى في السجل) ولا تُحذف
  update public.tasks set cancelled_at = now()
  where lead_id = v_lead and done_at is null and cancelled_at is null
    and id in (select (jsonb_array_elements_text(coalesce(p -> 'created_task_ids', '[]'::jsonb)))::uuid);

  update public.tasks set done_at = null
  where lead_id = v_lead
    and id in (select (jsonb_array_elements_text(coalesce(p -> 'closed_task_ids', '[]'::jsonb)))::uuid);

  if nullif(p ->> 'stage_before', '') is not null then
    update public.leads set stage = p ->> 'stage_before'
    where id = v_lead and stage = 'contacted' and (p ->> 'stage_before') <> 'contacted';
  end if;

  if m.template_id is not null then
    update public.message_templates set usage_count = greatest(0, usage_count - 1) where id = m.template_id;
  end if;

  update public.leads
  set last_contact_at = nullif(p ->> 'last_contact_before', '')::timestamptz,
      next_action_at = (select min(t.due_at) from public.tasks t
                        where t.lead_id = v_lead and t.done_at is null and t.cancelled_at is null)
  where id = v_lead;

  return jsonb_build_object('message_id', m.id, 'undone', true);
end;
$$;

revoke execute on function public.confirm_message_sent(jsonb) from public, anon;
revoke execute on function public.undo_message_sent(jsonb) from public, anon;
grant execute on function public.confirm_message_sent(jsonb) to authenticated;
grant execute on function public.undo_message_sent(jsonb) to authenticated;
