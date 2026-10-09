-- mark_replied / set_meeting / mark_won / mark_lost / set_stage / do_not_contact trigger
begin;
insert into auth.users (id, email) values ('a1000000-0000-4000-8000-0000000000a1', 'stage-a@maidani.test'),
                                          ('b1000000-0000-4000-8000-0000000000b1', 'stage-b@maidani.test');
set local role authenticated;
select set_config('request.jwt.claims', '{"sub": "a1000000-0000-4000-8000-0000000000a1"}', true);

-- two leads from visits, the first one with its first message sent
select public.create_lead_from_visit(jsonb_build_object(
  'id', 'a1a1a1a1-0000-4000-8000-000000000001', 'business_name', 'مطعم ريدان', 'phone_e164', '966551234567',
  'activity_type_id', (select id from public.activity_types where name = 'مطعم'), 'score', 48, 'priority', 'warm',
  'key_observation', 'ملاحظة'));
select public.create_lead_from_visit(jsonb_build_object(
  'id', 'a1a1a1a1-0000-4000-8000-000000000002', 'business_name', 'كافيه نسمة', 'phone_e164', '966551234568',
  'activity_type_id', (select id from public.activity_types where name = 'كافيه'), 'score', 60, 'priority', 'cold',
  'key_observation', 'ملاحظة'));
insert into public.messages (id, lead_id, kind, body, status, tone, generated_by)
values ('a1a1a1a1-0000-4000-8000-0000000000e1', 'a1a1a1a1-0000-4000-8000-000000000001', 'first', 'نص', 'draft', 'friendly', 'ai');
select public.confirm_message_sent(jsonb_build_object(
  'message_id', 'a1a1a1a1-0000-4000-8000-0000000000e1',
  'followups', jsonb_build_array(
    jsonb_build_object('kind', 'followup_3', 'title', 'متابعة أولى', 'due_at', now() + interval '3 days'),
    jsonb_build_object('kind', 'followup_7', 'title', 'متابعة ثانية وأخيرة', 'due_at', now() + interval '7 days'))));

do $$
declare
  r jsonb; n integer; st text; pr text; nxt timestamptz; l1 uuid := 'a1a1a1a1-0000-4000-8000-000000000001';
  l2 uuid := 'a1a1a1a1-0000-4000-8000-000000000002';
begin
  -- العميل رد
  r := public.mark_replied(jsonb_build_object('lead_id', l1));
  select stage, priority, next_action_at into st, pr, nxt from public.leads where id = l1;
  if st <> 'replied' or pr <> 'hot' then raise exception 'FAIL replied stage/priority % %', st, pr; end if;
  if jsonb_array_length(r -> 'cancelled_task_ids') <> 2 then raise exception 'FAIL replied cancelled %', r; end if;
  select count(*) into n from public.tasks where lead_id = l1 and kind in ('followup_3', 'followup_7') and cancelled_at is not null and done_at is null;
  if n <> 2 then raise exception 'FAIL follow-ups not cancelled'; end if;
  select count(*) into n from public.tasks where lead_id = l1 and kind = 'schedule_meeting' and done_at is null and cancelled_at is null;
  if n <> 1 then raise exception 'FAIL schedule_meeting %', n; end if;
  if nxt is null or nxt > now() + interval '1 minute' then raise exception 'FAIL next_action_at %', nxt; end if;
  select count(*) into n from public.messages where id = 'a1a1a1a1-0000-4000-8000-0000000000e1' and status = 'replied' and replied_at is not null;
  if n <> 1 then raise exception 'FAIL message not replied'; end if;
  -- a second reply does not add another «حدد اجتماعاً»
  perform public.mark_replied(jsonb_build_object('lead_id', l1));
  select count(*) into n from public.tasks where lead_id = l1 and kind = 'schedule_meeting';
  if n <> 1 then raise exception 'FAIL duplicate schedule_meeting %', n; end if;

  -- اجتماع
  r := public.set_meeting(jsonb_build_object('lead_id', l1, 'at', now() + interval '2 days', 'title', 'اجتماع في المحل'));
  select stage, next_action_at into st, nxt from public.leads where id = l1;
  if st <> 'meeting' then raise exception 'FAIL meeting stage %', st; end if;
  select count(*) into n from public.tasks where lead_id = l1 and kind = 'schedule_meeting' and done_at is not null;
  if n <> 1 then raise exception 'FAIL schedule_meeting not closed'; end if;
  select count(*) into n from public.tasks where lead_id = l1 and kind = 'meeting' and title = 'اجتماع في المحل' and done_at is null;
  if n <> 1 then raise exception 'FAIL meeting task'; end if;
  if nxt < now() + interval '1 day' then raise exception 'FAIL meeting next_action_at %', nxt; end if;
  begin
    perform public.set_meeting(jsonb_build_object('lead_id', l1));
    raise exception 'FAIL meeting without time accepted';
  exception when invalid_parameter_value then null;
  end;

  -- إغلاق
  begin
    perform public.mark_won(jsonb_build_object('lead_id', l1, 'value', 2500, 'billing', 'yearly'));
    raise exception 'FAIL bad billing accepted';
  exception when invalid_parameter_value then null;
  end;
  perform public.mark_won(jsonb_build_object('lead_id', l1, 'value', 2500, 'billing', 'monthly'));
  select stage, next_action_at into st, nxt from public.leads where id = l1;
  if st <> 'won' or nxt is not null then raise exception 'FAIL won % %', st, nxt; end if;
  select count(*) into n from public.leads where id = l1 and won_value = 2500 and won_billing = 'monthly';
  if n <> 1 then raise exception 'FAIL won value'; end if;
  begin
    perform public.mark_replied(jsonb_build_object('lead_id', l1));
    raise exception 'FAIL replied on a won lead';
  exception when invalid_parameter_value then null;
  end;

  -- خسارة مع «أعد المحاولة» لعميلين معاً
  begin
    perform public.mark_lost(jsonb_build_object('lead_ids', jsonb_build_array(l2), 'reason', 'cheap'));
    raise exception 'FAIL bad reason accepted';
  exception when invalid_parameter_value then null;
  end;
  r := public.mark_lost(jsonb_build_object('lead_ids', jsonb_build_array(l1, l2), 'reason', 'not_now', 'note', ' بعد رمضان ',
                                           'retry_at', now() + interval '30 days'));
  if (r ->> 'updated')::integer <> 2 then raise exception 'FAIL lost count %', r; end if;
  select count(*) into n from public.leads where id in (l1, l2) and stage = 'lost' and lost_reason = 'not_now' and lost_note = 'بعد رمضان' and won_value is not null = (id = l1);
  if n <> 2 then raise exception 'FAIL lost rows'; end if;
  select count(*) into n from public.tasks where lead_id = l2 and kind = 'first_message' and cancelled_at is not null;
  if n <> 1 then raise exception 'FAIL lost did not cancel open tasks'; end if;
  select count(*) into n from public.tasks where lead_id in (l1, l2) and kind = 'retry' and done_at is null and cancelled_at is null;
  if n <> 2 then raise exception 'FAIL retry tasks %', n; end if;
  select next_action_at into nxt from public.leads where id = l2;
  if nxt < now() + interval '29 days' then raise exception 'FAIL lost next_action_at %', nxt; end if;

  -- تغيير مباشر يمسح سبب الخسارة وقيمة الإغلاق
  begin
    perform public.set_stage(jsonb_build_object('lead_id', l2, 'stage', 'won'));
    raise exception 'FAIL set_stage to won accepted';
  exception when invalid_parameter_value then null;
  end;
  r := public.set_stage(jsonb_build_object('lead_ids', jsonb_build_array(l1, l2), 'stage', 'proposal'));
  if (r ->> 'updated')::integer <> 2 then raise exception 'FAIL set_stage count %', r; end if;
  select count(*) into n from public.leads where id in (l1, l2) and stage = 'proposal' and lost_reason is null and lost_note is null and won_value is null;
  if n <> 2 then raise exception 'FAIL set_stage did not clear'; end if;
  select count(*) into n from public.stage_history where lead_id = l1;
  if n < 6 then raise exception 'FAIL stage history rows %', n; end if;

  -- «عدم التواصل» يلغي المتابعات المفتوحة
  update public.leads set do_not_contact = true where id = l2;
  select count(*) into n from public.tasks where lead_id = l2 and done_at is null and cancelled_at is null;
  if n <> 0 then raise exception 'FAIL dnc left open tasks %', n; end if;
  select next_action_at into nxt from public.leads where id = l2;
  if nxt is not null then raise exception 'FAIL dnc next_action_at %', nxt; end if;
end $$;

-- the other user cannot act on A's leads
select set_config('request.jwt.claims', '{"sub": "b1000000-0000-4000-8000-0000000000b1"}', true);
do $$
declare r jsonb;
begin
  begin
    perform public.mark_replied(jsonb_build_object('lead_id', 'a1a1a1a1-0000-4000-8000-000000000001'));
    raise exception 'FAIL B replied for A';
  exception when no_data_found then null;
  end;
  begin
    perform public.mark_lost(jsonb_build_object('lead_id', 'a1a1a1a1-0000-4000-8000-000000000002', 'reason', 'price'));
    raise exception 'FAIL B lost A lead';
  exception when no_data_found then null;
  end;
  r := public.set_stage(jsonb_build_object('lead_id', 'a1a1a1a1-0000-4000-8000-000000000002', 'stage', 'visited'));
  if (r ->> 'updated')::integer <> 0 then raise exception 'FAIL B changed A stage'; end if;
end $$;

select set_config('request.jwt.claims', '{"sub": "a1000000-0000-4000-8000-0000000000a1"}', true);
do $$
declare st text;
begin
  select stage into st from public.leads where id = 'a1a1a1a1-0000-4000-8000-000000000002';
  if st <> 'proposal' then raise exception 'FAIL A lead changed by B: %', st; end if;
end $$;

select 'STAGE_OK' as result;
rollback;
