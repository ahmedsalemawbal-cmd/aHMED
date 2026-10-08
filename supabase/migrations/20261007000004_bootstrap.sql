-- ميداني · بيانات البداية لكل مستخدم جديد
-- عند إنشاء حساب في auth.users يُنشأ له: الملف الشخصي، والأنشطة الأربعة ببنود التقييم
-- وأوزانها (docs/brief.md)، وكتالوج خدمات تجريبي (decisions §4: الأسعار غير محسومة)،
-- وقالب احتياطي لكل نوع رسالة.

create or replace function public.bootstrap_owner(p_owner uuid, p_full_name text default '')
returns void
language plpgsql
security definer
set search_path = ''
as $$
declare
  general jsonb := '[
    {"id": "g1",   "label": "التقييم 4.3 أو أعلى",                          "weight": 10, "group": "خرائط قوقل"},
    {"id": "g2",   "label": "عدد مراجعات كافٍ مقارنة بالمنافسين",            "weight": 5,  "group": "خرائط قوقل"},
    {"id": "g3",   "label": "يرد على المراجعات",                            "weight": 5,  "group": "خرائط قوقل"},
    {"id": "g4",   "label": "صور حديثة ومعلومات مكتملة",                    "weight": 5,  "group": "خرائط قوقل"},
    {"id": "s1",   "label": "حساب نشط، آخر نشر خلال 14 يوماً",               "weight": 10, "group": "السوشيال ميديا"},
    {"id": "s2",   "label": "جودة التصوير والمحتوى",                        "weight": 10, "group": "السوشيال ميديا"},
    {"id": "s3",   "label": "ينشر فيديو أو ريلز",                           "weight": 10, "group": "السوشيال ميديا"},
    {"id": "s4",   "label": "هوية بصرية موحدة",                             "weight": 5,  "group": "السوشيال ميديا"},
    {"id": "ad",   "label": "يعلن حالياً إعلانات مدفوعة",                    "weight": 10, "group": "الإعلانات والموقع والواتساب"},
    {"id": "web",  "label": "موقع أو صفحة هبوط أو رابط طلب وحجز",           "weight": 10, "group": "الإعلانات والموقع والواتساب"},
    {"id": "wa",   "label": "واتساب أعمال برد سريع",                        "weight": 5,  "group": "الإعلانات والموقع والواتساب"},
    {"id": "shop", "label": "لوحة وهوية واضحة، ومنيو أو قائمة أسعار مصورة", "weight": 10, "group": "داخل المحل"},
    {"id": "off",  "label": "عروض أو برنامج ولاء",                          "weight": 5,  "group": "داخل المحل"}
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

revoke execute on function public.bootstrap_owner(uuid, text) from public, anon, authenticated;

create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
  perform public.bootstrap_owner(new.id, coalesce(new.raw_user_meta_data ->> 'full_name', ''));
  return new;
end;
$$;

revoke execute on function public.handle_new_user() from public, anon, authenticated;

create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();
