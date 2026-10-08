-- ميداني · عبارة نقطة الضعف لكل بند تقييم، ودالة حفظ الزيارة في معاملة واحدة.

-- 1) كل بند عام يحمل «weakness»: صيغته كنقطة ضعف («لا ينشر فيديو أو ريلز»)
--    تُعرض في صفحة العميل وسبباً للخدمة المقترحة. تُعدَّل من الإعدادات.
create or replace function public.bootstrap_owner(p_owner uuid, p_full_name text default '')
returns void
language plpgsql
security definer
set search_path = ''
as $$
declare
  general jsonb := '[
    {"id": "g1",   "label": "التقييم 4.3 أو أعلى",                          "weight": 10, "weakness": "تقييمه في قوقل أقل من 4.3", "group": "خرائط قوقل"},
    {"id": "g2",   "label": "عدد مراجعات كافٍ مقارنة بالمنافسين",            "weight": 5,  "weakness": "مراجعاته في قوقل قليلة", "group": "خرائط قوقل"},
    {"id": "g3",   "label": "يرد على المراجعات",                            "weight": 5,  "weakness": "لا يرد على مراجعات قوقل", "group": "خرائط قوقل"},
    {"id": "g4",   "label": "صور حديثة ومعلومات مكتملة",                    "weight": 5,  "weakness": "صوره ومعلوماته في قوقل ناقصة", "group": "خرائط قوقل"},
    {"id": "s1",   "label": "حساب نشط، آخر نشر خلال 14 يوماً",               "weight": 10, "weakness": "حسابه غير نشط", "group": "السوشيال ميديا"},
    {"id": "s2",   "label": "جودة التصوير والمحتوى",                        "weight": 10, "weakness": "التصوير والمحتوى ضعيف", "group": "السوشيال ميديا"},
    {"id": "s3",   "label": "ينشر فيديو أو ريلز",                           "weight": 10, "weakness": "لا ينشر فيديو أو ريلز", "group": "السوشيال ميديا"},
    {"id": "s4",   "label": "هوية بصرية موحدة",                             "weight": 5,  "weakness": "هويته البصرية غير موحدة", "group": "السوشيال ميديا"},
    {"id": "ad",   "label": "يعلن حالياً إعلانات مدفوعة",                    "weight": 10, "weakness": "لا يعلن حالياً", "group": "الإعلانات والموقع والواتساب"},
    {"id": "web",  "label": "موقع أو صفحة هبوط أو رابط طلب وحجز",           "weight": 10, "weakness": "بدون رابط طلب أو حجز", "group": "الإعلانات والموقع والواتساب"},
    {"id": "wa",   "label": "واتساب أعمال برد سريع",                        "weight": 5,  "weakness": "لا يستخدم واتساب أعمال", "group": "الإعلانات والموقع والواتساب"},
    {"id": "shop", "label": "لوحة وهوية واضحة، ومنيو أو قائمة أسعار مصورة", "weight": 10, "weakness": "اللوحة أو المنيو غير واضحة", "group": "داخل المحل"},
    {"id": "off",  "label": "عروض أو برنامج ولاء",                          "weight": 5,  "weakness": "بدون عروض أو برنامج ولاء", "group": "داخل المحل"}
  ]'::jsonb;
  svc_maps uuid; svc_social uuid; svc_video uuid; svc_ads uuid; svc_landing uuid; svc_identity uuid;
  act_restaurant uuid; act_cafe uuid; act_dental uuid; act_other uuid;
begin
  insert into public.profiles (id, owner_id, full_name)
  values (p_owner, p_owner, coalesce(p_full_name, ''))
  on conflict (id) do nothing;

  if exists (select 1 from public.activity_types where owner_id = p_owner) then
    return;
  end if;

  -- الخدمات: الأسماء من جدول «من نقطة الضعف إلى الخدمة»، والأسعار تجريبية
  insert into public.services (owner_id, name, description, price_from, billing, weakness_item_ids, sort_order)
  values (p_owner, 'إدارة ملف خرائط قوقل والمراجعات', 'تحديث الملف والصور والرد على المراجعات.', 800, 'monthly', array['g1', 'g2', 'g3', 'g4'], 1)
  returning id into svc_maps;
  insert into public.services (owner_id, name, description, price_from, billing, weakness_item_ids, sort_order)
  values (p_owner, 'إدارة حسابات السوشيال ميديا', 'خطة نشر شهرية وإدارة الحسابات.', 1200, 'monthly', array['s1'], 2)
  returning id into svc_social;
  insert into public.services (owner_id, name, description, price_from, billing, weakness_item_ids, sort_order)
  values (p_owner, 'تصوير وإنتاج فيديو وريلز', 'جلسات تصوير وريلز قصيرة أسبوعية.', 1500, 'monthly', array['s2', 's3'], 3)
  returning id into svc_video;
  insert into public.services (owner_id, name, description, price_from, billing, weakness_item_ids, sort_order)
  values (p_owner, 'إدارة الحملات الإعلانية', 'حملات مدفوعة تستهدف سكان الحي.', 1000, 'monthly', array['ad'], 4)
  returning id into svc_ads;
  insert into public.services (owner_id, name, description, price_from, billing, weakness_item_ids, sort_order)
  values (p_owner, 'صفحة هبوط', 'صفحة طلب أو حجز مع رابط واتساب.', 2000, 'one_time', array['web'], 5)
  returning id into svc_landing;
  insert into public.services (owner_id, name, description, price_from, billing, weakness_item_ids, sort_order)
  values (p_owner, 'تصميم هوية وقوالب', 'هوية بصرية وقوالب منشورات ولوحة ومنيو.', 1500, 'one_time', array['s4', 'shop'], 6)
  returning id into svc_identity;

  insert into public.activity_types (owner_id, name, icon, checklist, default_service_ids, sort_order)
  values (p_owner, 'مطعم', 'restaurant', jsonb_build_object('general', general, 'specific', '[
            {"id": "r1", "label": "صور احترافية للأطباق"},
            {"id": "r2", "label": "حضور في تطبيقات التوصيل"},
            {"id": "r3", "label": "منيو QR"}]'::jsonb),
          array[svc_video, svc_maps], 1)
  returning id into act_restaurant;
  insert into public.activity_types (owner_id, name, icon, checklist, default_service_ids, sort_order)
  values (p_owner, 'كافيه', 'cafe', jsonb_build_object('general', general, 'specific', '[
            {"id": "c1", "label": "محتوى يُظهر الأجواء"},
            {"id": "c2", "label": "عروض لأوقات الهدوء"},
            {"id": "c3", "label": "برنامج ولاء"}]'::jsonb),
          array[svc_video, svc_social, svc_ads], 2)
  returning id into act_cafe;
  insert into public.activity_types (owner_id, name, icon, checklist, default_service_ids, sort_order)
  values (p_owner, 'مركز أسنان', 'dental', jsonb_build_object('general', general, 'specific', '[
            {"id": "d1", "label": "رابط حجز موعد"},
            {"id": "d2", "label": "محتوى تعريفي بالأطباء والخدمات"}]'::jsonb),
          array[svc_landing, svc_ads, svc_video], 3)
  returning id into act_dental;
  insert into public.activity_types (owner_id, name, icon, checklist, default_service_ids, sort_order)
  values (p_owner, 'أخرى', 'store', jsonb_build_object('general', general, 'specific', '[]'::jsonb),
          '{}', 4)
  returning id into act_other;

  update public.services set activity_type_ids = array[act_restaurant, act_cafe, act_dental, act_other]
  where owner_id = p_owner;

  -- قوالب احتياطية تُستخدم عند فشل التوليد. المتغيرات:
  -- {contact_name} {business_name} {observation} {sender_name}
  insert into public.message_templates (owner_id, name, stage, kind, body) values
    (p_owner, 'أول تواصل — احتياطي', 'visited', 'first',
     E'السلام عليكم {contact_name}، معك {sender_name}، زرتكم اليوم في {business_name}.\nلاحظت {observation}.\nعندي فكرة بسيطة تخدمكم في هذي النقطة بالذات.\nيناسبك أرسل لك عينة مجانية؟'),
    (p_owner, 'متابعة أولى — احتياطي', 'contacted', 'followup_3',
     E'هلا {contact_name}، معك {sender_name} من زيارة {business_name}.\nجهزت لك فكرة محتوى قصيرة مبنية على اللي لاحظته في الزيارة.\nيناسبك أرسلها لك هنا؟'),
    (p_owner, 'متابعة ثانية — احتياطي', 'contacted', 'followup_7',
     E'{contact_name}، ما أبي أثقل عليك.\nلو حاب نكمل الكلام عن {business_name} أنا موجود في أي وقت.\nيناسبك نتواصل الأسبوع الجاي؟');
end;
$$;


update public.activity_types t
set checklist = jsonb_set(t.checklist, '{general}', coalesce((
  select jsonb_agg(
           case when e.item ? 'weakness' or w.text is null then e.item
                else e.item || jsonb_build_object('weakness', w.text) end
           order by e.ord)
  from jsonb_array_elements(t.checklist -> 'general') with ordinality as e(item, ord)
  left join (values
    ('g1', 'تقييمه في قوقل أقل من 4.3'),
    ('g2', 'مراجعاته في قوقل قليلة'),
    ('g3', 'لا يرد على مراجعات قوقل'),
    ('g4', 'صوره ومعلوماته في قوقل ناقصة'),
    ('s1', 'حسابه غير نشط'),
    ('s2', 'التصوير والمحتوى ضعيف'),
    ('s3', 'لا ينشر فيديو أو ريلز'),
    ('s4', 'هويته البصرية غير موحدة'),
    ('ad', 'لا يعلن حالياً'),
    ('web', 'بدون رابط طلب أو حجز'),
    ('wa', 'لا يستخدم واتساب أعمال'),
    ('shop', 'اللوحة أو المنيو غير واضحة'),
    ('off', 'بدون عروض أو برنامج ولاء')
  ) as w(id, text) on w.id = e.item ->> 'id'
), '[]'::jsonb));

-- 2) حفظ عميل جديد من الزيارة: العميل والزيارة والتقييم والخدمات ومهمة الرسالة الأولى
--    في معاملة واحدة. تعمل بصلاحية المستخدم (RLS)، وتقبل معرّفاً من الواجهة حتى
--    لا يتكرر العميل إذا أُعيد الإرسال بعد انقطاع الشبكة.
create or replace function public.create_lead_from_visit(p jsonb)
returns jsonb
language plpgsql
security invoker
set search_path = ''
as $$
declare
  v_lead uuid := coalesce(nullif(p ->> 'id', '')::uuid, gen_random_uuid());
  v_visit uuid;
  v_task uuid;
  v_now timestamptz := now();
  s jsonb;
begin
  if exists (select 1 from public.leads where id = v_lead) then
    select v.id into v_visit from public.visits v where v.lead_id = v_lead order by v.created_at limit 1;
    select t.id into v_task from public.tasks t where t.lead_id = v_lead and t.kind = 'first_message' order by t.created_at limit 1;
    return jsonb_build_object('lead_id', v_lead, 'visit_id', v_visit, 'task_id', v_task, 'existing', true);
  end if;

  insert into public.leads (
    id, business_name, activity_type_id, contact_name, contact_role, phone_e164, is_decision_maker,
    best_contact_time, wa_consent, lat, lng, address, source, stage, score, priority, expected_value,
    last_contact_at, next_action_at)
  values (
    v_lead, btrim(p ->> 'business_name'), nullif(p ->> 'activity_type_id', '')::uuid, nullif(btrim(p ->> 'contact_name'), ''),
    nullif(p ->> 'contact_role', ''), nullif(p ->> 'phone_e164', ''), coalesce((p ->> 'is_decision_maker')::boolean, false),
    nullif(p ->> 'best_contact_time', ''), coalesce((p ->> 'wa_consent')::boolean, false),
    (p ->> 'lat')::double precision, (p ->> 'lng')::double precision, nullif(btrim(p ->> 'address'), ''),
    'visit', 'visited', (p ->> 'score')::integer, nullif(p ->> 'priority', ''), (p ->> 'expected_value')::numeric,
    v_now, v_now);

  insert into public.visits (lead_id, visited_at, notes, key_observation, lat, lng)
  values (v_lead, v_now, nullif(btrim(p ->> 'notes'), ''), nullif(btrim(p ->> 'key_observation'), ''),
          (p ->> 'lat')::double precision, (p ->> 'lng')::double precision)
  returning id into v_visit;

  insert into public.assessments (lead_id, visit_id, answers, score, weaknesses)
  values (v_lead, v_visit, coalesce(p -> 'answers', '{}'::jsonb), coalesce((p ->> 'score')::integer, 0),
          coalesce(p -> 'weaknesses', '[]'::jsonb));

  for s in select value from jsonb_array_elements(coalesce(p -> 'services', '[]'::jsonb)) loop
    insert into public.lead_services (lead_id, service_id, status, note)
    values (v_lead, (s ->> 'service_id')::uuid, coalesce(s ->> 'status', 'suggested'), nullif(s ->> 'note', ''));
  end loop;

  insert into public.tasks (lead_id, title, kind, due_at)
  values (v_lead, 'أرسل الرسالة الأولى', 'first_message', v_now)
  returning id into v_task;

  return jsonb_build_object('lead_id', v_lead, 'visit_id', v_visit, 'task_id', v_task, 'existing', false);
end;
$$;

revoke execute on function public.create_lead_from_visit(jsonb) from public, anon;
grant execute on function public.create_lead_from_visit(jsonb) to authenticated;
