-- ميداني · «زيارة جديدة» لعميل مسجّل (المرحلة 1e).
-- الزيارة والتقييم وتحديث العميل وخدماته في معاملة واحدة بصلاحية المستخدم (RLS).
-- تقبل معرّف الزيارة من الواجهة، فإعادة الإرسال بعد انقطاع الشبكة لا تكرر الزيارة.
-- مراجع العميل والخدمات تُفحص بنفس المالك عبر triggers هجرة 3 (enforce_same_owner).

create or replace function public.add_visit(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  v_visit uuid := coalesce(nullif(p ->> 'id', '')::uuid, gen_random_uuid());
  v_lead uuid := nullif(p ->> 'lead_id', '')::uuid;
  v_key text := nullif(btrim(p ->> 'key_observation'), '');
  v_score integer := (p ->> 'score')::integer;
  v_now timestamptz := now();
  l public.leads%rowtype;
  v_prev public.visits%rowtype;
  v_task uuid;
  s jsonb;
begin
  -- إعادة إرسال زيارة محفوظة: لا شيء يتكرر
  select * into v_prev from public.visits where id = v_visit;
  if found then
    if v_prev.lead_id is distinct from v_lead then
      raise exception 'الزيارة مسجّلة لعميل آخر' using errcode = '22023';
    end if;
    select * into l from public.leads where id = v_prev.lead_id;
    return jsonb_build_object('lead_id', l.id, 'visit_id', v_visit, 'score', l.score, 'priority', l.priority,
                              'stage', l.stage, 'stage_before', l.stage, 'task_id', null, 'existing', true);
  end if;

  select * into l from public.leads where id = v_lead;
  if not found then
    raise exception 'العميل غير موجود' using errcode = 'P0002';
  end if;
  if v_key is null then
    raise exception 'اكتب الملاحظة الأبرز' using errcode = '22023';
  end if;
  if v_score is null then
    raise exception 'الدرجة مفقودة' using errcode = '22023';
  end if;

  insert into public.visits (id, lead_id, visited_at, notes, key_observation, lat, lng)
  values (v_visit, l.id, v_now, nullif(btrim(p ->> 'notes'), ''), v_key,
          (p ->> 'lat')::double precision, (p ->> 'lng')::double precision);

  insert into public.assessments (lead_id, visit_id, answers, score, weaknesses)
  values (l.id, v_visit, coalesce(p -> 'answers', '{}'::jsonb), v_score, coalesce(p -> 'weaknesses', '[]'::jsonb));

  -- الدرجة والأولوية والقيمة المتوقعة من هذه الزيارة. المرحلة تتقدم من «لم يُزر» فقط.
  update public.leads
  set score = v_score,
      priority = coalesce(nullif(p ->> 'priority', ''), priority),
      expected_value = case when p ? 'expected_value' then (p ->> 'expected_value')::numeric else expected_value end,
      stage = case when stage = 'not_visited' then 'visited' else stage end,
      last_contact_at = v_now
  where id = l.id;

  -- الخدمات: المحددة «suggested» والملغاة «dropped». السبب القديم يبقى إن لم يُرسل سبب جديد.
  for s in select value from jsonb_array_elements(coalesce(p -> 'services', '[]'::jsonb)) loop
    insert into public.lead_services (lead_id, service_id, status, note)
    values (l.id, (s ->> 'service_id')::uuid, coalesce(nullif(s ->> 'status', ''), 'suggested'), nullif(btrim(s ->> 'note'), ''))
    on conflict (lead_id, service_id) do update
      set status = excluded.status,
          note = coalesce(excluded.note, public.lead_services.note);
  end loop;

  -- أول زيارة لمحل «لم يُزر»: الخطوة التالية الرسالة الأولى، كما بعد حفظ عميل جديد
  if l.stage = 'not_visited' and not l.do_not_contact
     and not exists (select 1 from public.tasks t
                     where t.lead_id = l.id and t.kind = 'first_message' and t.done_at is null and t.cancelled_at is null) then
    insert into public.tasks (lead_id, title, kind, due_at)
    values (l.id, 'أرسل الرسالة الأولى', 'first_message', v_now)
    returning id into v_task;
  end if;

  perform public.refresh_next_action(l.id);

  return jsonb_build_object('lead_id', l.id, 'visit_id', v_visit, 'score', v_score,
                            'priority', (select priority from public.leads where id = l.id),
                            'stage', case when l.stage = 'not_visited' then 'visited' else l.stage end,
                            'stage_before', l.stage, 'task_id', v_task, 'existing', false);
end;
$$;

revoke execute on function public.add_visit(jsonb) from public, anon;
grant execute on function public.add_visit(jsonb) to authenticated;
