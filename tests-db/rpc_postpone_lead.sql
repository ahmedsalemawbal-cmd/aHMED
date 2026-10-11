-- postpone_lead: «أجّل 30 يوماً» after the day-7 follow-up (flow 2.4)
begin;
insert into auth.users (id, email) values ('a2000000-0000-4000-8000-0000000000a2', 'postpone-a@maidani.test'),
                                          ('b2000000-0000-4000-8000-0000000000b2', 'postpone-b@maidani.test');
set local role authenticated;
select set_config('request.jwt.claims', '{"sub": "a2000000-0000-4000-8000-0000000000a2"}', true);

-- a lead whose first message was sent: two follow-ups are open
select public.create_lead_from_visit(jsonb_build_object(
  'id', 'a2a2a2a2-0000-4000-8000-000000000001', 'business_name', 'مطعم ريدان', 'phone_e164', '966551234567',
  'activity_type_id', (select id from public.activity_types where name = 'مطعم'), 'score', 48, 'priority', 'warm',
  'key_observation', 'ملاحظة'));
select public.create_lead_from_visit(jsonb_build_object(
  'id', 'a2a2a2a2-0000-4000-8000-000000000002', 'business_name', 'كافيه نسمة', 'phone_e164', '966551234568',
  'activity_type_id', (select id from public.activity_types where name = 'كافيه'), 'score', 60, 'priority', 'cold',
  'key_observation', 'ملاحظة'));
insert into public.messages (id, lead_id, kind, body, status, tone, generated_by)
values ('a2a2a2a2-0000-4000-8000-0000000000e1', 'a2a2a2a2-0000-4000-8000-000000000001', 'first', 'نص', 'draft', 'friendly', 'ai');
select public.confirm_message_sent(jsonb_build_object(
  'message_id', 'a2a2a2a2-0000-4000-8000-0000000000e1',
  'followups', jsonb_build_array(
    jsonb_build_object('kind', 'followup_3', 'title', 'متابعة أولى', 'due_at', now() - interval '5 days'),
    jsonb_build_object('kind', 'followup_7', 'title', 'متابعة ثانية وأخيرة', 'due_at', now() - interval '1 day'))));

do $$
declare
  r jsonb; r2 jsonb; n integer; st text; nxt timestamptz;
  l1 uuid := 'a2a2a2a2-0000-4000-8000-000000000001';
  l2 uuid := 'a2a2a2a2-0000-4000-8000-000000000002';
  at timestamptz := date_trunc('day', now()) + interval '30 days 7 hours';
begin
  -- a meeting task is not a follow-up: it stays open
  insert into public.tasks (lead_id, title, kind, due_at) values (l1, 'اجتماع', 'meeting', now() + interval '40 days');

  r := public.postpone_lead(jsonb_build_object('lead_id', l1, 'retry_at', at));
  if jsonb_array_length(r -> 'cancelled_task_ids') <> 2 then raise exception 'FAIL cancelled %', r; end if;
  if (r ->> 'due_at')::timestamptz <> at then raise exception 'FAIL due_at %', r; end if;
  select count(*) into n from public.tasks where lead_id = l1 and kind in ('followup_3', 'followup_7') and cancelled_at is not null and done_at is null;
  if n <> 2 then raise exception 'FAIL follow-ups not cancelled %', n; end if;
  select count(*) into n from public.tasks where id = (r ->> 'task_id')::uuid and kind = 'retry' and title = 'أعد المحاولة' and due_at = at and done_at is null and cancelled_at is null;
  if n <> 1 then raise exception 'FAIL retry task'; end if;
  select count(*) into n from public.tasks where lead_id = l1 and kind = 'meeting' and cancelled_at is null;
  if n <> 1 then raise exception 'FAIL meeting cancelled'; end if;
  select stage, next_action_at into st, nxt from public.leads where id = l1;
  if st <> 'contacted' then raise exception 'FAIL stage changed %', st; end if;
  if nxt <> at then raise exception 'FAIL next_action_at %', nxt; end if;
  -- no task is ever deleted
  select count(*) into n from public.tasks where lead_id = l1;
  if n <> 5 then raise exception 'FAIL task count %', n; end if;

  -- twice: the same task moves, no duplicate
  r2 := public.postpone_lead(jsonb_build_object('lead_id', l1, 'retry_at', at + interval '1 day'));
  if r2 ->> 'task_id' <> r ->> 'task_id' then raise exception 'FAIL second retry task %', r2; end if;
  if jsonb_array_length(r2 -> 'cancelled_task_ids') <> 0 then raise exception 'FAIL cancelled twice %', r2; end if;
  select count(*) into n from public.tasks where lead_id = l1 and kind = 'retry' and cancelled_at is null;
  if n <> 1 then raise exception 'FAIL duplicate retry %', n; end if;
  select next_action_at into nxt from public.leads where id = l1;
  if nxt <> at + interval '1 day' then raise exception 'FAIL moved next_action_at %', nxt; end if;

  -- a past time, a closed lead and «عدم التواصل» are refused
  begin
    perform public.postpone_lead(jsonb_build_object('lead_id', l1, 'retry_at', now() - interval '1 hour'));
    raise exception 'FAIL past retry accepted';
  exception when invalid_parameter_value then null;
  end;
  begin
    perform public.postpone_lead(jsonb_build_object('lead_id', l1));
    raise exception 'FAIL missing retry accepted';
  exception when invalid_parameter_value then null;
  end;
  perform public.mark_lost(jsonb_build_object('lead_id', l2, 'reason', 'no_reply'));
  begin
    perform public.postpone_lead(jsonb_build_object('lead_id', l2, 'retry_at', at));
    raise exception 'FAIL lost lead postponed';
  exception when invalid_parameter_value then null;
  end;
  perform public.set_stage(jsonb_build_object('lead_id', l2, 'stage', 'contacted'));
  update public.leads set do_not_contact = true where id = l2;
  begin
    perform public.postpone_lead(jsonb_build_object('lead_id', l2, 'retry_at', at));
    raise exception 'FAIL dnc lead postponed';
  exception when invalid_parameter_value then null;
  end;
  begin
    perform public.postpone_lead(jsonb_build_object('lead_id', 'a2a2a2a2-0000-4000-8000-0000000000ff', 'retry_at', at));
    raise exception 'FAIL unknown lead';
  exception when no_data_found then null;
  end;
end $$;

-- the other user cannot postpone A's lead
select set_config('request.jwt.claims', '{"sub": "b2000000-0000-4000-8000-0000000000b2"}', true);
do $$
begin
  begin
    perform public.postpone_lead(jsonb_build_object('lead_id', 'a2a2a2a2-0000-4000-8000-000000000001', 'retry_at', now() + interval '30 days'));
    raise exception 'FAIL B postponed A lead';
  exception when no_data_found then null;
  end;
end $$;

select set_config('request.jwt.claims', '{"sub": "a2000000-0000-4000-8000-0000000000a2"}', true);
do $$
declare n integer;
begin
  select count(*) into n from public.tasks where lead_id = 'a2a2a2a2-0000-4000-8000-000000000001' and kind = 'retry';
  if n <> 1 then raise exception 'FAIL B touched A tasks %', n; end if;
end $$;

-- anon has no access
reset role;
set local role anon;
do $$
begin
  begin
    perform public.postpone_lead('{}'::jsonb);
    raise exception 'FAIL anon executed postpone_lead';
  exception when insufficient_privilege then null;
  end;
end $$;
reset role;

select 'POSTPONE_OK' as result;
rollback;
