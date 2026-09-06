-- ══════════════════════════════════════════════════════════════
-- مَن يرى ماذا — يُفحص في القاعدة لا في الواجهة
--
--     psql "$DATABASE_URL" -f osoul/supabase/tests/wall.sql
--
-- ولمَ هنا لا في المتصفّح؟ لأنّ فحوصَ الواجهة **تُحاكي** طلباتِ
-- الخادم فتردّ ما نُمليه عليها، فلا تكشف عطبًا في سياسةِ وصولٍ أبدًا
-- مهما كثرت. ولا يكشفه إلّا استعلامٌ ينتحل هويّةَ مستخدمٍ حقيقيٍّ
-- ويرى ما يراه.
--
--     حجبُ الحاوية لا يحجب ما فيها.
--
-- ويُنشئ الفحصُ مشهدَه بنفسِه — خمسةَ حساباتٍ حقيقيّةً في `auth.users`
-- لا صفوفًا مصطنعةً في `profiles`، لأنّ الفحصَ الذي يُنشئ ملفًّا بلا
-- مستخدمٍ يفحص جدولًا لا نظامًا. ثمّ يُلغي كلَّ ما فعل بـ`rollback`،
-- فلا يترك أثرًا وإن تعثّر في منتصفه.
--
-- ويقيس الحجبَ **والكشفَ** جميعًا: النقصانُ عطبٌ كالزيادة، فمديرٌ
-- لا يرى عملَه لا يعمل.
-- ══════════════════════════════════════════════════════════════

\set ON_ERROR_STOP on
begin;

do $$
declare
  admin_id uuid; rep_id uuid; br_id uuid; pa uuid; pb uuid;
  sup uuid; q_cust uuid; q_pb uuid; q_open uuid; it uuid; n bigint;
  ids uuid[]; u uuid;
begin
  ids := array[gen_random_uuid(), gen_random_uuid(), gen_random_uuid(),
               gen_random_uuid(), gen_random_uuid()];
  foreach u in array ids loop
    insert into auth.users (instance_id, id, aud, role, email, encrypted_password,
                            email_confirmed_at, created_at, updated_at,
                            raw_app_meta_data, raw_user_meta_data)
    values ('00000000-0000-0000-0000-000000000000', u, 'authenticated',
            'authenticated', 'wall+' || u || '@test.local', 'x', now(), now(), now(),
            '{}'::jsonb, '{}'::jsonb);
  end loop;

  -- المُحفِّزُ `trg_new_user` أنشأ لكلٍّ ملفًّا بدور 'customer'، والترقيةُ
  -- يمنعها `app.role_frozen`. فيُحذف الملفُّ ويُدرَج بدوره — والإدراجُ
  -- لا يمرّ بذلك المُحفِّز. (وهذا نفسُه دليلٌ على أنّ المُحفِّزَ يعمل.)
  delete from public.profiles where id = any(ids);
  insert into public.profiles (id, role, full_name, is_active, approved) values
    (ids[1], 'admin',   'مدير أصول',     true, true),
    (ids[3], 'branch',  'فرع جدة',       true, true),
    (ids[2], 'rep',     'مندوب المنطقة', true, true),
    (ids[4], 'partner', 'شركة الشريك أ', true, true),
    (ids[5], 'partner', 'شركة الشريك ب', true, true);
  update public.profiles set branch_id = ids[3] where id = ids[2];
  admin_id := ids[1]; rep_id := ids[2]; br_id := ids[3]; pa := ids[4]; pb := ids[5];

  insert into public.partner_profiles (user_id, company_ar) values
    (pa, 'شركة الشريك أ'), (pb, 'شركة الشريك ب');

  -- المشهد: طلبُ توريدٍ مُصدَر، وعرضا شريكين لعميلَيهما، وعرضُ مندوبٍ
  -- مُسعَّرٌ لم يُصدَر بعد.
  insert into public.quotes (kind, source, partner_id, customer_name)
       values ('supply','partner', pa, 'شركة الشريك أ') returning id into sup;
  perform set_config('app.sealing','on', true);
  update public.quotes set issued_at = now(), number = 'TST-SUP-0001' where id = sup;
  perform set_config('app.sealing','', true);

  insert into public.quotes (kind, source, partner_id, supply_id, customer_name, customer_phone)
       values ('cust','partner', pa, sup, 'عميلُ الشريك السرّيّ', '0500000001')
    returning id into q_cust;
  insert into public.quotes (kind, source, partner_id, supply_id, customer_name)
       values ('cust','partner', pb, sup, 'عميلُ الشريك ب') returning id into q_pb;
  insert into public.quotes (kind, source, rep_id, branch_id, customer_name)
       values ('osoul','rep', rep_id, br_id, 'عميلُ أصول') returning id into q_open;
  insert into public.quote_items (quote_id, line_no, name_ar, qty)
       values (q_open, 1, 'باب حديد مقاوم للحريق', 4) returning id into it;
  insert into public.quote_prices (item_id, quote_id, unit_price) values (it, q_open, 1750.00);
  insert into public.quote_money (quote_id, discount_amount) values (q_open, 0);
  insert into public.contacts (owner_partner_id, ckey, display_name)
       values (pa, '0500000001', 'عميلُ الشريك السرّيّ');

  -- ══ الهجومُ ① — المديرُ يحاول قراءةَ عملاء الشريك ══
  -- ولا يُسأل عن عرضٍ بعينه بل عن **الصنف كلِّه**: السؤالُ عن صفٍّ
  -- واحدٍ يمرّ لو صحّت السياسةُ لذلك الصفّ وحده.
  perform set_config('role','authenticated', true);
  perform set_config('request.jwt.claims',
    json_build_object('sub', admin_id, 'role','authenticated')::text, true);
  select count(*) into n from public.quotes where kind = 'cust';
  if n <> 0 then raise exception '✗ ①أ المديرُ يرى % عرضًا للشركاء مع عملائهم', n; end if;
  select count(*) into n from public.quotes where customer_phone = '0500000001';
  if n <> 0 then raise exception '✗ ①ب اسمُ عميل الشريك يظهر للمدير'; end if;
  select count(*) into n from public.quote_items i
    join public.quotes q on q.id = i.quote_id where q.kind = 'cust';
  if n <> 0 then raise exception '✗ ①ج بنودُ عرض الشريك تظهر للمدير'; end if;
  select count(*) into n from public.quote_totals where quote_id = q_cust;
  if n <> 0 then raise exception '✗ ①د منظرُ المجاميع يسرّب عرضَ الشريك'; end if;
  select count(*) into n from public.contacts where owner_partner_id is not null;
  if n <> 0 then raise exception '✗ ①هـ قائمةُ عملاء الشريك تظهر للمدير'; end if;
  select count(*) into n from public.quotes where id = q_open;
  if n <> 1 then raise exception '✗ ①و المديرُ لا يرى عرضَ أصول — حُجب عنه ما له'; end if;
  select count(*) into n from public.quote_prices where quote_id = q_open;
  if n <> 1 then raise exception '✗ ①ز المديرُ لا يرى السعرَ ليسعّر'; end if;

  -- ══ الهجومُ ② — مندوبٌ يقرأ سعرًا قبل الإصدار ══
  perform set_config('request.jwt.claims',
    json_build_object('sub', rep_id, 'role','authenticated')::text, true);
  select count(*) into n from public.quotes where id = q_open;
  if n <> 1 then raise exception '✗ ②أ المندوبُ لا يرى عرضَه هو'; end if;
  select count(*) into n from public.quote_items where quote_id = q_open;
  if n <> 1 then raise exception '✗ ②ب المندوبُ لا يرى بنودَ عرضِه'; end if;
  select count(*) into n from public.quote_prices where quote_id = q_open;
  if n <> 0 then raise exception '✗ ②ج المندوبُ يرى السعرَ قبل الإصدار'; end if;
  select count(*) into n from public.quote_money where quote_id = q_open;
  if n <> 0 then raise exception '✗ ②د المندوبُ يرى الخصمَ قبل الإصدار'; end if;
  select count(*) into n from public.quote_totals where quote_id = q_open;
  if n <> 0 then raise exception '✗ ②هـ منظرُ المجاميع يسرّب المالَ للمندوب'; end if;
  select count(*) into n from public.product_prices;
  if n <> 0 then raise exception '✗ ②و المندوبُ يرى قائمةَ أسعار المنتجات'; end if;

  -- ثمّ يُصدَر العرضُ فيُفتح المالُ لصاحبه — ولولا هذا الشطرُ لكان
  -- الفحصُ يقيس جدارًا مسدودًا لا جدارًا يعمل.
  perform set_config('role','postgres', true);
  perform set_config('request.jwt.claims','', true);
  perform set_config('app.sealing','on', true);
  update public.quotes set issued_at = now(), number = 'TST-OSB-0001' where id = q_open;
  perform set_config('app.sealing','', true);
  perform set_config('role','authenticated', true);
  perform set_config('request.jwt.claims',
    json_build_object('sub', rep_id, 'role','authenticated')::text, true);
  select count(*) into n from public.quote_prices where quote_id = q_open;
  if n <> 1 then raise exception '✗ ②ز المندوبُ لا يرى السعرَ بعد الإصدار'; end if;

  -- ══ الهجومُ ③ — شريكٌ يقرأ عروضَ شريكٍ آخر ══
  perform set_config('request.jwt.claims',
    json_build_object('sub', pa, 'role','authenticated')::text, true);
  select count(*) into n from public.quotes where partner_id = pb;
  if n <> 0 then raise exception '✗ ③أ الشريكُ (أ) يرى % عرضًا للشريك (ب)', n; end if;
  select count(*) into n from public.quotes where id = q_pb;
  if n <> 0 then raise exception '✗ ③ب الشريكُ (أ) يرى عرضَ (ب) بمعرّفه'; end if;
  select count(*) into n from public.contacts where owner_partner_id = pb;
  if n <> 0 then raise exception '✗ ③ج الشريكُ (أ) يرى جهاتِ (ب)'; end if;
  select count(*) into n from public.partner_profiles where user_id = pb;
  if n <> 0 then raise exception '✗ ③د الشريكُ (أ) يرى هويّةَ (ب) التجاريّة'; end if;
  select count(*) into n from public.quotes where id = q_cust;
  if n <> 1 then raise exception '✗ ③هـ الشريكُ لا يرى عرضَه هو — الجدارُ يفصل ولا يخنق'; end if;

  -- ══ ④ سرُّ البريد: لا يقرؤه أحدٌ ولا المدير ══
  perform set_config('request.jwt.claims',
    json_build_object('sub', admin_id, 'role','authenticated')::text, true);
  begin
    select count(*) into n from public.employee_mailbox_secrets;
    if n > 0 then raise exception '✗ ④ المديرُ يقرأ كلماتِ مرورِ البريد'; end if;
  exception when insufficient_privilege then null;  -- المنحةُ منزوعة: قفلٌ ثانٍ
  end;

  perform set_config('role','postgres', true);
  perform set_config('request.jwt.claims','', true);

  -- ══ ⑤ لقطةُ العلامة تُختم ولا تُفكّ ══
  begin
    update public.quotes set brand = '{"company_ar":"علامةٌ مبدَّلة"}'::jsonb where id = sup;
    raise exception '✗ ⑤أ العلامةُ بُدّلت بعد الإصدار';
  exception when check_violation then null; end;
  begin
    update public.quotes set number = 'TST-HACK-9999' where id = sup;
    raise exception '✗ ⑤ب الرقمُ بُدّل بعد الإصدار';
  exception when check_violation then null; end;
  begin
    insert into public.quotes (kind, source, partner_id, customer_name, number, issued_at)
         values ('supply','partner', pa, 'التفاف', 'TST-FAKE-0001', now());
    raise exception '✗ ⑤ج أُصدر عرضٌ بكتابةِ عمودٍ لا بـissue_quote';
  exception when check_violation then null; end;
  begin
    update public.quotes set kind = 'osoul' where id = q_cust;
    raise exception '✗ ⑤د نوعُ العرض قُلب فسقط الجدارُ عن صفٍّ فيه عميلُ شريك';
  exception when check_violation then null; end;

  -- ══ ⑥ ما تشير إليه وثيقةٌ صادرةٌ لا يُحذف من تحتها ══
  -- (هذا الفحصُ هو الذي كشف أنّ `on delete set null` تُفرِّغ مالكَ
  --  صفِّ 'cust' فيسقط عنه الجدار — فصارت `restrict` في 0006.)
  begin
    delete from auth.users where id = pa;
    raise exception '✗ ⑥ حُذف شريكٌ له عروضٌ صادرة';
  exception when foreign_key_violation then null; end;

  raise notice '✅ الجدارُ قائم · المالُ محجوبٌ حتّى الإصدار · العلامةُ مختومة · لا حذفَ تحت وثيقة';
end $$;

rollback;
