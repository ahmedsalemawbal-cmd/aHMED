-- add_visit: زيارة جديدة لعميل مسجّل في معاملة واحدة، بلا تكرار عند الإعادة، وبصلاحية المستخدم.
begin;
insert into auth.users (id, email) values ('a2000000-0000-4000-8000-0000000000a2', 'visit-a@maidani.test'),
                                          ('b2000000-0000-4000-8000-0000000000b2', 'visit-b@maidani.test');
set local role authenticated;
select set_config('request.jwt.claims', '{"sub": "a2000000-0000-4000-8000-0000000000a2"}', true);

-- مطعم زاره من قبل (درجة 48، خدمتان: الفيديو مقترحة والإعلانات ملغاة)، وكافيه مستورد لم يُزر
select public.create_lead_from_visit(jsonb_build_object(
  'id', 'a2a2a2a2-0000-4000-8000-000000000001', 'business_name', 'مطعم ريدان', 'phone_e164', '966551234567',
  'activity_type_id', (select id from public.activity_types where name = 'مطعم'), 'contact_role', 'owner',
  'is_decision_maker', true, 'score', 48, 'priority', 'hot', 'expected_value', 1500, 'key_observation', 'ملاحظة أولى',
  'answers', '{"s3": "no"}'::jsonb, 'weaknesses', '["s3"]'::jsonb,
  'services', jsonb_build_array(
    jsonb_build_object('service_id', (select id from public.services where name = 'تصوير وإنتاج فيديو وريلز'), 'status', 'suggested', 'note', 'لأنه: لا ينشر فيديو أو ريلز'),
    jsonb_build_object('service_id', (select id from public.services where name = 'إدارة الحملات الإعلانية'), 'status', 'dropped', 'note', 'لأنه: لا يعلن حالياً'))));
insert into public.leads (id, business_name, activity_type_id, source, stage)
values ('a2a2a2a2-0000-4000-8000-000000000002', 'كافيه نسمة', (select id from public.activity_types where name = 'كافيه'), 'import', 'not_visited');

do $$
declare
  r jsonb; n integer; st text; pr text; sc integer; ev numeric; nt text; nxt timestamptz; lc timestamptz;
  l1 uuid := 'a2a2a2a2-0000-4000-8000-000000000001';
  l2 uuid := 'a2a2a2a2-0000-4000-8000-000000000002';
  v1 uuid := 'a2a2a2a2-0000-4000-8000-0000000000f1';
  v2 uuid := 'a2a2a2a2-0000-4000-8000-0000000000f2';
  s_video uuid := (select id from public.services where name = 'تصوير وإنتاج فيديو وريلز');
  s_ads uuid := (select id from public.services where name = 'إدارة الحملات الإعلانية');
  s_maps uuid := (select id from public.services where name = 'إدارة ملف خرائط قوقل والمراجعات');
  p jsonb;
begin
  p := jsonb_build_object(
    'id', v1, 'lead_id', l1, 'key_observation', ' صاروا ينشرون ريلز لكن بدون ردود على المراجعات ', 'notes', 'زيارة ثانية',
    'answers', '{"g1": "yes", "s3": "yes", "g3": "no"}'::jsonb, 'weaknesses', '["g3"]'::jsonb,
    'score', 72, 'priority', 'cold', 'expected_value', 1800,
    'services', jsonb_build_array(
      jsonb_build_object('service_id', s_maps, 'status', 'suggested', 'note', 'لأنه: لا يرد على مراجعات قوقل'),
      jsonb_build_object('service_id', s_ads, 'status', 'suggested', 'note', ''),
      jsonb_build_object('service_id', s_video, 'status', 'dropped', 'note', '')));

  -- زيارة ثانية: زيارة وتقييم جديدان، والعميل بالدرجة والأولوية والقيمة الجديدة
  r := public.add_visit(p);
  if (r ->> 'existing')::boolean is not false or (r ->> 'visit_id')::uuid <> v1 or (r ->> 'score')::integer <> 72 then
    raise exception 'FAIL add_visit result %', r;
  end if;
  select count(*) into n from public.visits where lead_id = l1;
  if n <> 2 then raise exception 'FAIL visits %', n; end if;
  select count(*) into n from public.visits where id = v1 and key_observation = 'صاروا ينشرون ريلز لكن بدون ردود على المراجعات' and notes = 'زيارة ثانية';
  if n <> 1 then raise exception 'FAIL visit row'; end if;
  select count(*) into n from public.assessments where visit_id = v1 and lead_id = l1 and score = 72 and answers ->> 'g3' = 'no' and weaknesses = '["g3"]'::jsonb;
  if n <> 1 then raise exception 'FAIL assessment'; end if;
  select stage, priority, score, expected_value, last_contact_at into st, pr, sc, ev, lc from public.leads where id = l1;
  if st <> 'visited' or pr <> 'cold' or sc <> 72 or ev <> 1800 then raise exception 'FAIL lead % % % %', st, pr, sc, ev; end if;
  if lc < now() - interval '1 minute' then raise exception 'FAIL last_contact_at %', lc; end if;

  -- الخدمات: إضافة الجديدة، وتحديث الحالة، والسبب القديم يبقى
  select count(*) into n from public.lead_services where lead_id = l1;
  if n <> 3 then raise exception 'FAIL services count %', n; end if;
  select status, note into st, nt from public.lead_services where lead_id = l1 and service_id = s_video;
  if st <> 'dropped' or nt <> 'لأنه: لا ينشر فيديو أو ريلز' then raise exception 'FAIL video % %', st, nt; end if;
  select status, note into st, nt from public.lead_services where lead_id = l1 and service_id = s_ads;
  if st <> 'suggested' or nt <> 'لأنه: لا يعلن حالياً' then raise exception 'FAIL ads % %', st, nt; end if;
  select status, note into st, nt from public.lead_services where lead_id = l1 and service_id = s_maps;
  if st <> 'suggested' or nt <> 'لأنه: لا يرد على مراجعات قوقل' then raise exception 'FAIL maps % %', st, nt; end if;

  -- إعادة الإرسال بنفس المعرّف: لا زيارة ولا تقييم ولا مهمة إضافية
  r := public.add_visit(p || jsonb_build_object('score', 10, 'priority', 'hot'));
  if (r ->> 'existing')::boolean is not true then raise exception 'FAIL retry not detected %', r; end if;
  select count(*) into n from public.visits where lead_id = l1;
  if n <> 2 then raise exception 'FAIL retry duplicated visit'; end if;
  select count(*) into n from public.assessments where lead_id = l1;
  if n <> 2 then raise exception 'FAIL retry duplicated assessment'; end if;
  select score, priority into sc, pr from public.leads where id = l1;
  if sc <> 72 or pr <> 'cold' then raise exception 'FAIL retry changed the lead % %', sc, pr; end if;
  begin
    perform public.add_visit(p || jsonb_build_object('lead_id', l2));
    raise exception 'FAIL visit id reused for another lead';
  exception when invalid_parameter_value then null;
  end;

  -- المرحلة لا تتراجع: عميل «تم الإرسال» يبقى كما هو
  perform public.set_stage(jsonb_build_object('lead_id', l1, 'stage', 'contacted'));
  r := public.add_visit(jsonb_build_object('lead_id', l1, 'key_observation', 'ثالثة', 'score', 30, 'priority', 'hot'));
  select stage, priority, expected_value into st, pr, ev from public.leads where id = l1;
  if st <> 'contacted' or pr <> 'hot' then raise exception 'FAIL contacted moved % %', st, pr; end if;
  if ev <> 1800 then raise exception 'FAIL expected_value cleared without being sent %', ev; end if;
  if r ->> 'stage' <> 'contacted' then raise exception 'FAIL result stage %', r; end if;

  -- «لم يُزر» ← «تمت الزيارة»، مع سجل المرحلة ومهمة الرسالة الأولى والإجراء القادم
  r := public.add_visit(jsonb_build_object('id', v2, 'lead_id', l2, 'key_observation', 'زيارة أولى', 'score', 40, 'priority', 'warm',
                                           'answers', '{"g1": "partial"}'::jsonb, 'weaknesses', '["g1"]'::jsonb));
  select stage, priority, next_action_at into st, pr, nxt from public.leads where id = l2;
  if st <> 'visited' or pr <> 'warm' then raise exception 'FAIL not_visited % %', st, pr; end if;
  if r ->> 'stage_before' <> 'not_visited' or r ->> 'task_id' is null then raise exception 'FAIL not_visited result %', r; end if;
  select count(*) into n from public.stage_history where lead_id = l2 and from_stage = 'not_visited' and to_stage = 'visited';
  if n <> 1 then raise exception 'FAIL stage history %', n; end if;
  select count(*) into n from public.tasks where lead_id = l2 and kind = 'first_message' and done_at is null and cancelled_at is null;
  if n <> 1 then raise exception 'FAIL first message task %', n; end if;
  if nxt is null or nxt > now() + interval '1 minute' then raise exception 'FAIL next_action_at %', nxt; end if;
  -- a second visit does not add another first-message task
  perform public.add_visit(jsonb_build_object('lead_id', l2, 'key_observation', 'ثانية', 'score', 45, 'priority', 'warm'));
  select count(*) into n from public.tasks where lead_id = l2 and kind = 'first_message';
  if n <> 1 then raise exception 'FAIL duplicate first message task %', n; end if;

  -- بيانات ناقصة: لا شيء يُحفظ
  begin
    perform public.add_visit(jsonb_build_object('lead_id', l1, 'key_observation', '  ', 'score', 50));
    raise exception 'FAIL empty observation accepted';
  exception when invalid_parameter_value then null;
  end;
  begin
    perform public.add_visit(jsonb_build_object('lead_id', l1, 'key_observation', 'ملاحظة', 'score', 50, 'answers', '{"g1": "maybe"}'::jsonb));
    raise exception 'FAIL bad answer accepted';
  exception when check_violation then null;
  end;
  begin
    perform public.add_visit(jsonb_build_object('lead_id', l1, 'key_observation', 'ملاحظة', 'score', 50,
      'services', jsonb_build_array(jsonb_build_object('service_id', s_maps, 'status', 'maybe'))));
    raise exception 'FAIL bad service status accepted';
  exception when check_violation then null;
  end;
  select count(*) into n from public.visits where lead_id = l1;
  if n <> 3 then raise exception 'FAIL failed calls left visits %', n; end if;
end $$;

-- the other user cannot add a visit to A's lead, reuse A's visit id or use A's services
-- (B cannot read A's services, so A's service id is kept in a setting first)
select set_config('test.a_service', (select id::text from public.services where name = 'صفحة هبوط'), true);
select set_config('request.jwt.claims', '{"sub": "b2000000-0000-4000-8000-0000000000b2"}', true);
select public.create_lead_from_visit(jsonb_build_object(
  'id', 'b2b2b2b2-0000-4000-8000-000000000001', 'business_name', 'محل ب', 'key_observation', 'ملاحظة'));
do $$
begin
  begin
    perform public.add_visit(jsonb_build_object('lead_id', 'a2a2a2a2-0000-4000-8000-000000000001', 'key_observation', 'من ب', 'score', 5, 'priority', 'hot'));
    raise exception 'FAIL B added a visit to A lead';
  exception when no_data_found then null;
  end;
  begin
    perform public.add_visit(jsonb_build_object('id', 'a2a2a2a2-0000-4000-8000-0000000000f1', 'lead_id', 'b2b2b2b2-0000-4000-8000-000000000001',
                                                'key_observation', 'من ب', 'score', 5));
    raise exception 'FAIL B reused A visit id';
  exception when unique_violation then null;
  end;
  begin
    perform public.add_visit(jsonb_build_object('lead_id', 'b2b2b2b2-0000-4000-8000-000000000001', 'key_observation', 'من ب', 'score', 5,
      'services', jsonb_build_array(jsonb_build_object('service_id', current_setting('test.a_service'), 'status', 'suggested'))));
    raise exception 'FAIL B linked A service';
  exception when foreign_key_violation then null;
  end;
  if current_setting('test.a_service', true) is null then raise exception 'FAIL A service id not kept'; end if;
  if exists (select 1 from public.visits where lead_id = 'a2a2a2a2-0000-4000-8000-000000000001') then
    raise exception 'FAIL B sees A visits';
  end if;
end $$;

select set_config('request.jwt.claims', '{"sub": "a2000000-0000-4000-8000-0000000000a2"}', true);
do $$
declare n integer; sc integer;
begin
  select count(*) into n from public.visits where lead_id = 'a2a2a2a2-0000-4000-8000-000000000001';
  if n <> 3 then raise exception 'FAIL A visits changed by B: %', n; end if;
  select score into sc from public.leads where id = 'a2a2a2a2-0000-4000-8000-000000000001';
  if sc <> 30 then raise exception 'FAIL A lead score changed by B: %', sc; end if;
end $$;

select 'ADD_VISIT_OK' as result;
rollback;
