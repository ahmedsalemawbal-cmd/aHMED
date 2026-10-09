-- confirm_message_sent / undo_message_sent
begin;
insert into auth.users (id, email) values ('e0000000-0000-4000-8000-00000000000e', 'msg-e@maidani.test'),
                                          ('f0000000-0000-4000-8000-00000000000f', 'msg-f@maidani.test');
set local role authenticated;
select set_config('request.jwt.claims', '{"sub": "e0000000-0000-4000-8000-00000000000e"}', true);

select public.create_lead_from_visit(jsonb_build_object(
  'id', 'eeeeeeee-0000-4000-8000-000000000001', 'business_name', 'مطعم ريدان', 'phone_e164', '966551234567',
  'activity_type_id', (select id from public.activity_types where name = 'مطعم'), 'score', 48, 'priority', 'hot',
  'key_observation', 'ملاحظة'));
insert into public.messages (id, lead_id, kind, body, status, tone, generated_by, template_id)
values ('eeeeeeee-0000-4000-8000-000000000002', 'eeeeeeee-0000-4000-8000-000000000001', 'first', 'مسودة', 'draft', 'friendly', 'fallback',
        (select id from public.message_templates where kind = 'first'));

select set_config('test.r', public.confirm_message_sent(jsonb_build_object(
  'message_id', 'eeeeeeee-0000-4000-8000-000000000002', 'body', 'النص النهائي', 'tone', 'short',
  'followups', jsonb_build_array(
    jsonb_build_object('kind', 'followup_3', 'title', 'متابعة أولى', 'due_at', now() + interval '3 days'),
    jsonb_build_object('kind', 'followup_7', 'title', 'متابعة ثانية وأخيرة', 'due_at', now() + interval '7 days'))))::text, true);

do $$
declare n integer; r jsonb := current_setting('test.r')::jsonb; st text; nxt timestamptz;
begin
  select count(*) into n from public.messages where id = 'eeeeeeee-0000-4000-8000-000000000002' and status = 'sent' and sent_at is not null and body = 'النص النهائي' and tone = 'short';
  if n <> 1 then raise exception 'FAIL message not sent'; end if;
  select stage, next_action_at into st, nxt from public.leads where id = 'eeeeeeee-0000-4000-8000-000000000001';
  if st <> 'contacted' then raise exception 'FAIL stage %', st; end if;
  select count(*) into n from public.tasks where lead_id = 'eeeeeeee-0000-4000-8000-000000000001' and kind in ('followup_3', 'followup_7') and done_at is null;
  if n <> 2 then raise exception 'FAIL followups %', n; end if;
  select count(*) into n from public.tasks where lead_id = 'eeeeeeee-0000-4000-8000-000000000001' and kind = 'first_message' and done_at is not null;
  if n <> 1 then raise exception 'FAIL first_message task not closed'; end if;
  if nxt < now() + interval '2 days' then raise exception 'FAIL next_action_at %', nxt; end if;
  select usage_count into n from public.message_templates where kind = 'first';
  if n <> 1 then raise exception 'FAIL template usage %', n; end if;
  if (r ->> 'stage_before') <> 'visited' then raise exception 'FAIL stage_before'; end if;
  -- confirming twice changes nothing
  r := public.confirm_message_sent(jsonb_build_object('message_id', 'eeeeeeee-0000-4000-8000-000000000002',
        'followups', jsonb_build_array(jsonb_build_object('kind', 'followup_3', 'title', 'x', 'due_at', now()))));
  if (r ->> 'already_sent')::boolean is not true then raise exception 'FAIL double confirm'; end if;
  select count(*) into n from public.tasks where lead_id = 'eeeeeeee-0000-4000-8000-000000000001';
  if n <> 3 then raise exception 'FAIL double confirm created tasks: %', n; end if;
  -- only follow-up kinds are accepted
  begin
    perform public.confirm_message_sent(jsonb_build_object('message_id', gen_random_uuid()));
    raise exception 'FAIL unknown message accepted';
  exception when no_data_found then null;
  end;
end $$;

-- the other user cannot confirm or undo E's message
select set_config('request.jwt.claims', '{"sub": "f0000000-0000-4000-8000-00000000000f"}', true);
do $$
begin
  begin
    perform public.undo_message_sent(jsonb_build_object('message_id', 'eeeeeeee-0000-4000-8000-000000000002'));
    raise exception 'FAIL F undid E message';
  exception when no_data_found then null;
  end;
end $$;

-- undo restores everything
select set_config('request.jwt.claims', '{"sub": "e0000000-0000-4000-8000-00000000000e"}', true);
do $$
declare n integer; st text; r jsonb := current_setting('test.r')::jsonb;
begin
  perform public.undo_message_sent(r || jsonb_build_object('message_id', 'eeeeeeee-0000-4000-8000-000000000002'));
  select count(*) into n from public.messages where id = 'eeeeeeee-0000-4000-8000-000000000002' and status = 'draft' and sent_at is null;
  if n <> 1 then raise exception 'FAIL undo message'; end if;
  select stage into st from public.leads where id = 'eeeeeeee-0000-4000-8000-000000000001';
  if st <> 'visited' then raise exception 'FAIL undo stage %', st; end if;
  select count(*) into n from public.tasks where lead_id = 'eeeeeeee-0000-4000-8000-000000000001' and done_at is null and cancelled_at is null;
  if n <> 1 then raise exception 'FAIL undo left % open tasks', n; end if;
  select count(*) into n from public.tasks where lead_id = 'eeeeeeee-0000-4000-8000-000000000001' and cancelled_at is not null;
  if n <> 2 then raise exception 'FAIL undo did not cancel the follow-ups: %', n; end if;
  select count(*) into n from public.tasks where lead_id = 'eeeeeeee-0000-4000-8000-000000000001' and kind = 'first_message' and done_at is null;
  if n <> 1 then raise exception 'FAIL first task not reopened'; end if;
  select usage_count into n from public.message_templates where kind = 'first';
  if n <> 0 then raise exception 'FAIL template usage after undo %', n; end if;
  -- stage history keeps both moves
  select count(*) into n from public.stage_history where lead_id = 'eeeeeeee-0000-4000-8000-000000000001';
  if n <> 3 then raise exception 'FAIL history %', n; end if;
end $$;

-- do_not_contact: no follow-ups even if the client sends them
update public.leads set do_not_contact = true where id = 'eeeeeeee-0000-4000-8000-000000000001';
do $$
declare n integer;
begin
  perform public.confirm_message_sent(jsonb_build_object('message_id', 'eeeeeeee-0000-4000-8000-000000000002',
    'followups', jsonb_build_array(jsonb_build_object('kind', 'followup_3', 'title', 'x', 'due_at', now() + interval '3 days'))));
  select count(*) into n from public.tasks where lead_id = 'eeeeeeee-0000-4000-8000-000000000001' and kind = 'followup_3' and cancelled_at is null;
  if n <> 0 then raise exception 'FAIL followups for do_not_contact'; end if;
end $$;

reset role;
select 'MSG_OK' as result;
rollback;
