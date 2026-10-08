-- create_lead_from_visit: كل شيء في معاملة واحدة، بصلاحية المستخدم، وبلا تكرار عند الإعادة.
begin;
insert into auth.users (id, email) values ('c0000000-0000-4000-8000-00000000000c', 'rpc-c@maidani.test'),
                                          ('d0000000-0000-4000-8000-00000000000d', 'rpc-d@maidani.test');

do $$
declare n integer; w text;
begin
  select item ->> 'weakness' into w
  from public.activity_types t, jsonb_array_elements(t.checklist -> 'general') item
  where t.owner_id = 'c0000000-0000-4000-8000-00000000000c' and t.name = 'مطعم' and item ->> 'id' = 's3';
  if w is distinct from 'لا ينشر فيديو أو ريلز' then raise exception 'FAIL weakness phrase %', w; end if;
end $$;

set local role authenticated;
select set_config('request.jwt.claims', '{"sub": "c0000000-0000-4000-8000-00000000000c"}', true);

select public.create_lead_from_visit(jsonb_build_object(
  'id', 'cccccccc-0000-4000-8000-000000000001',
  'business_name', '  مطعم ريدان ',
  'activity_type_id', (select id from public.activity_types where name = 'مطعم'),
  'contact_name', 'خالد العتيبي', 'contact_role', 'owner', 'phone_e164', '966551234567', 'is_decision_maker', true,
  'best_contact_time', 'evening', 'wa_consent', true, 'lat', 21.6, 'lng', 39.2,
  'score', 48, 'priority', 'hot', 'expected_value', 2500,
  'key_observation', 'أطباقهم ممتازة وحسابهم بدون فيديو', 'notes', 'المنيو قديم',
  'answers', '{"g1": "yes", "s3": "no", "r1": "yes"}'::jsonb, 'weaknesses', '["s3"]'::jsonb,
  'services', jsonb_build_array(
    jsonb_build_object('service_id', (select id from public.services where name = 'تصوير وإنتاج فيديو وريلز'), 'status', 'suggested', 'note', 'لأنه: لا ينشر فيديو أو ريلز'),
    jsonb_build_object('service_id', (select id from public.services where name = 'إدارة الحملات الإعلانية'), 'status', 'dropped'))
));

do $$
declare n integer; r jsonb;
begin
  select count(*) into n from public.leads where id = 'cccccccc-0000-4000-8000-000000000001' and business_name = 'مطعم ريدان' and stage = 'visited' and source = 'visit' and next_action_at is not null;
  if n <> 1 then raise exception 'FAIL lead not saved as visited'; end if;
  select count(*) into n from public.visits where lead_id = 'cccccccc-0000-4000-8000-000000000001' and key_observation = 'أطباقهم ممتازة وحسابهم بدون فيديو';
  if n <> 1 then raise exception 'FAIL visit'; end if;
  select count(*) into n from public.assessments where lead_id = 'cccccccc-0000-4000-8000-000000000001' and score = 48;
  if n <> 1 then raise exception 'FAIL assessment'; end if;
  select count(*) into n from public.lead_services where lead_id = 'cccccccc-0000-4000-8000-000000000001';
  if n <> 2 then raise exception 'FAIL services %', n; end if;
  select count(*) into n from public.tasks where lead_id = 'cccccccc-0000-4000-8000-000000000001' and kind = 'first_message' and done_at is null;
  if n <> 1 then raise exception 'FAIL first message task'; end if;
  select count(*) into n from public.stage_history where lead_id = 'cccccccc-0000-4000-8000-000000000001' and to_stage = 'visited';
  if n <> 1 then raise exception 'FAIL stage history'; end if;

  -- retry with the same id: nothing duplicated
  r := public.create_lead_from_visit(jsonb_build_object('id', 'cccccccc-0000-4000-8000-000000000001', 'business_name', 'مطعم ريدان'));
  if (r ->> 'existing')::boolean is not true then raise exception 'FAIL retry not detected'; end if;
  select count(*) into n from public.tasks where lead_id = 'cccccccc-0000-4000-8000-000000000001';
  if n <> 1 then raise exception 'FAIL retry duplicated tasks'; end if;

  -- invalid data rolls everything back
  begin
    perform public.create_lead_from_visit(jsonb_build_object('business_name', 'محل', 'phone_e164', '0551234567'));
    raise exception 'FAIL bad phone accepted';
  exception when check_violation then null;
  end;
end $$;

-- another user cannot use C's activity or services through the RPC
select set_config('request.jwt.claims', '{"sub": "d0000000-0000-4000-8000-00000000000d"}', true);
do $$
begin
  begin
    perform public.create_lead_from_visit(jsonb_build_object('business_name', 'محل د',
      'activity_type_id', (select id from public.activity_types t where t.owner_id = 'c0000000-0000-4000-8000-00000000000c' limit 1)));
  exception when foreign_key_violation then null;
  end;
  -- D sees nothing of C's lead even by id
  if exists (select 1 from public.leads where id = 'cccccccc-0000-4000-8000-000000000001') then
    raise exception 'FAIL D sees C lead';
  end if;
  -- reusing C's lead id from D must not reveal or touch it
  begin
    perform public.create_lead_from_visit(jsonb_build_object('id', 'cccccccc-0000-4000-8000-000000000001', 'business_name', 'محل د'));
    raise exception 'FAIL D created a lead with C id';
  exception when unique_violation then null;
  end;
end $$;

reset role;
select 'RPC_OK' as result;
rollback;
