-- ══════════════════════════════════════════════════════════════
-- أصولُ البناء — 0003 : الدوالُّ المنادَاة
--
-- وكلُّ دالّةٍ هنا `security definer` — أي **تتخطّى السياسات**. فكلٌّ
-- منها تُعيد بناءَ الجدار في أوّل سطرٍ فيها.
--
--     ما يتخطّى الحارسَ يحمل حراستَه معه.
-- ══════════════════════════════════════════════════════════════

-- ─────────── ترقيمٌ لكلِّ جهةٍ يُصفَّر كلَّ سنة ───────────
create or replace function app.next_number(p_partner uuid)
returns text language plpgsql security definer set search_path = public, app as $$
declare
  v_year   int  := extract(year from (now() at time zone 'Asia/Riyadh'))::int;
  v_scope  uuid := coalesce(p_partner, '00000000-0000-0000-0000-000000000000'::uuid);
  v_prefix text;
  v_n      int;
begin
  -- الزيادةُ في جملةٍ واحدة: طلبان متزامنان لا يأخذان رقمًا واحدًا،
  -- لأنّ الثاني ينتظر قفلَ الصفّ. وقراءةٌ ثمّ كتابةٌ في جملتين تُصدر
  -- الرقمَ مرّتين — ورقمٌ مكرّرٌ في فاتورةٍ ضريبيّةٍ ليس هيّنًا.
  insert into public.quote_counters as c (scope_id, year, n)
       values (v_scope, v_year, 1)
  on conflict (scope_id, year) do update set n = c.n + 1
    returning c.n into v_n;

  if p_partner is null then
    v_prefix := coalesce(nullif(
      (select value->>'quote_prefix' from public.settings where key = 'quote_doc'), ''), 'OSB');
  else
    v_prefix := coalesce(nullif(
      (select prefix from public.partner_profiles where user_id = p_partner), ''),
      'PRT' || substr(replace(p_partner::text, '-', ''), 1, 4));
  end if;

  return format('%s-%s-%s', upper(v_prefix), v_year, lpad(v_n::text, 4, '0'));
end $$;

-- ولا تُنادى من متصفّح. والمنحُ الجامعُ في 0002 يُعطي التنفيذَ للجميع
-- — وهذا فخُّ «المنحِ الجامع»: فيُستثنى صراحةً كلُّ ما يغيّر حالة،
-- وإلّا استنزف فضوليٌّ عدّادَ الشركاء وترك في الترقيم ثقوبًا.
revoke execute on function app.next_number(uuid) from anon, authenticated, public;

-- ─────────── لقطةُ العلامة (الثابتُ ③) ───────────
-- لقطةٌ لا إشارة: المفتاحُ الأجنبيُّ إلى `partner_profiles` يتبع
-- التبدّل، واللقطةُ لا تتبعه.
create or replace function app.brand_snapshot(p_partner uuid)
returns jsonb language sql stable security definer set search_path = public as $$
  select jsonb_build_object(
    'company_ar', p.company_ar,
    'company_en', coalesce(nullif(p.company_en,''), p.company_ar),
    'logo_url',   p.logo_url,
    'vat_number', p.vat_number,
    'cr_number',  p.cr_number,
    'address_ar', p.address_ar,
    'address_en', coalesce(nullif(p.address_en,''), p.address_ar),
    'bank_info',  p.bank_info,
    'phone',      p.phone,
    'email',      p.email,
    'terms_ar',   p.terms_ar,
    'terms_en',   p.terms_en,
    'color',      p.color,
    'accent',     p.accent,
    'sealed_at',  now())
  from public.partner_profiles p where p.user_id = p_partner
$$;
revoke execute on function app.brand_snapshot(uuid) from anon, authenticated, public;

-- ─────────── الإصدار ───────────
--
-- ثلاثةُ أشياءَ تقع معًا أو لا تقع: الرقمُ، والختمُ الزمنيّ، ولقطةُ
-- العلامة. ولو تُركت لثلاثِ كتاباتٍ من الواجهة لوقع بينها انقطاعٌ
-- فصدر عرضٌ بلا رقم، أو حمل رقمًا ولم يُختم فأُعيد ترقيمُه.
--
-- وتُعيد `jsonb` لا `returns table` — لأنّ أسماءَ أعمدةِ الخرج تُظلّل
-- أعمدةَ الجدول داخل الجسم فيسقط الاستعلامُ بسببٍ لا يظهر في النصّ.
create or replace function public.issue_quote(p_quote uuid)
returns jsonb language plpgsql security definer
set search_path = public, app, auth as $$
declare
  q       public.quotes;
  r       text := app.role();
  v_num   text;
  v_brand jsonb := '{}'::jsonb;
begin
  select * into q from public.quotes where quotes.id = p_quote;
  if not found then raise exception 'عرضٌ غير موجود'; end if;

  -- الجدارُ يُعاد بناؤه هنا: الدالّةُ definer فلا تمرّ بالسياسات ولا
  -- بالمُقيِّدة، ولا شيءَ يحميها إلّا هذه الأسطر.
  if r = 'admin' then
    if q.kind = 'cust' then
      raise exception 'عملاءُ الشريك ليسوا لنا: عرضُ الشريك يُصدره الشريك';
    end if;
  elsif r = 'partner' then
    if q.partner_id <> auth.uid() or q.kind <> 'cust' then
      raise exception 'ليس عرضَك';
    end if;
    -- ولا يبيع الشريكُ قبل أن تُسعّر أصولُ توريدَه: بلا تكلفةٍ لا
    -- معنى لحدّ الهامش، فيبيع بأقلَّ من كلفته وهو لا يدري.
    if not exists (select 1 from public.quotes s
                    where s.id = q.supply_id and s.issued_at is not null) then
      raise exception 'لم تُسعّر أصولُ طلبَ التوريد بعد';
    end if;
  else
    raise exception 'الإصدارُ للمدير في عروض أصول، وللشريك في عرضه لعميله';
  end if;

  -- إصدارٌ مكرّرٌ لا يُصدر رقمًا ثانيًا: يُردّ ما كان.
  if q.issued_at is not null then
    return jsonb_build_object('id', q.id, 'number', q.number,
                              'issued_at', q.issued_at, 'brand', q.brand,
                              'token', q.token, 'already', true);
  end if;

  v_num := app.next_number(case when q.kind = 'cust' then q.partner_id else null end);
  if q.kind = 'cust' then
    v_brand := coalesce(app.brand_snapshot(q.partner_id), '{}'::jsonb);
    if v_brand = '{}'::jsonb then
      raise exception 'لا هويّةَ للشريك تُختم على العرض — أكمِل ملفَّك أوّلًا';
    end if;
  end if;

  -- الرايةُ تُرفع لجملةٍ واحدةٍ ثمّ تُنزل. والمُحفِّزُ لا يأذن بغيرها.
  perform set_config('app.sealing', 'on', true);
  update public.quotes
     set number    = v_num,
         issued_at = now(),
         brand     = v_brand,
         stage     = 'sent',
         log       = q.log || jsonb_build_object(
                       'at', now(), 'by', auth.uid(), 'event', 'تمّ إصدارُ العرض')
   where quotes.id = p_quote;
  perform set_config('app.sealing', '', true);

  select * into q from public.quotes where quotes.id = p_quote;
  return jsonb_build_object('id', q.id, 'number', q.number,
                            'issued_at', q.issued_at, 'brand', q.brand,
                            'token', q.token, 'already', false);
end $$;

revoke all on function public.issue_quote(uuid) from public, anon;
grant execute on function public.issue_quote(uuid) to authenticated;

-- ─────────── الصفحةُ العامّةُ للعرض ───────────
--
-- البابُ الوحيدُ الذي يُخرج عرضًا بلا جلسة. وشرطاه: رمزٌ من اثنين
-- وثلاثين حرفًا لا يُخمَّن، وعرضٌ صدر. ولا يُخرج قائمةً ألبتّة — فمن
-- لا يملك الرمزَ لا يعرف أنّ العرضَ موجود.
--
-- ويُخرج `unit_price` ولا يُخرج `cost_price` أبدًا: تكلفةُ الشريك على
-- بنده ربحُه، والعميلُ هو من يفتح الرابط.
create or replace function public.quote_by_token(p_token text)
returns jsonb language plpgsql stable security definer
set search_path = public, app as $$
declare q public.quotes; v_items jsonb; v_tot record;
begin
  if p_token !~ '^[0-9a-f]{32}$' then return null; end if;

  select * into q from public.quotes
   where quotes.token = p_token and quotes.issued_at is not null;
  if not found then return null; end if;

  select coalesce(jsonb_agg(jsonb_build_object(
           'line_no', i.line_no, 'name_ar', i.name_ar, 'name_en', i.name_en,
           'qty', i.qty, 'unit_price', coalesce(p.unit_price, 0))
         order by i.line_no), '[]'::jsonb)
    into v_items
    from public.quote_items i
    left join public.quote_prices p on p.item_id = i.id
   where i.quote_id = q.id;

  select * into v_tot from public.quote_totals t where t.quote_id = q.id;

  return jsonb_build_object(
    'number', q.number, 'issued_at', q.issued_at, 'lang', q.lang,
    'kind', q.kind, 'stage', q.stage,
    -- علامةٌ فارغةٌ تعني عرضَ أصول، ومملوءةٌ تعني علامةَ الشريك المختومة.
    'brand', case when q.brand = '{}'::jsonb then null else q.brand end,
    'customer', jsonb_build_object('name', q.customer_name, 'phone', q.customer_phone),
    'items', v_items,
    'note', q.note, 'terms_ar', q.terms_ar, 'terms_en', q.terms_en,
    'validity_days', q.validity_days,
    'totals', to_jsonb(v_tot) - 'quote_id');
end $$;

revoke all on function public.quote_by_token(text) from public;
grant execute on function public.quote_by_token(text) to anon, authenticated;

-- ─────────── قبولُ العرض ورفضُه من صفحته العامّة ───────────
-- العميلُ لا جلسةَ له، فالرمزُ هو إذنُه. ولا يُحرّك إلّا من 'sent'
-- أو 'negotiation' — فعرضٌ مقبولٌ لا يُرفض بضغطةٍ ثانية.
create or replace function public.decide_quote(p_token text, p_accept boolean)
returns jsonb language plpgsql security definer
set search_path = public, app as $$
declare q public.quotes;
begin
  if p_token !~ '^[0-9a-f]{32}$' then raise exception 'رمزٌ غير صالح'; end if;
  select * into q from public.quotes
   where quotes.token = p_token and quotes.issued_at is not null;
  if not found then raise exception 'عرضٌ غير موجود'; end if;
  if q.stage not in ('sent','negotiation') then
    return jsonb_build_object('stage', q.stage, 'changed', false);
  end if;

  perform set_config('app.sealing', 'on', true);
  update public.quotes
     set stage = (case when p_accept then 'won' else 'lost' end)::public.quote_stage,
         log   = q.log || jsonb_build_object(
                   'at', now(), 'event',
                   case when p_accept then 'قبِل العميلُ العرض' else 'رفض العميلُ العرض' end)
   where quotes.id = q.id;
  perform set_config('app.sealing', '', true);

  return jsonb_build_object('stage', case when p_accept then 'won' else 'lost' end,
                            'changed', true);
end $$;

revoke all on function public.decide_quote(text, boolean) from public;
grant execute on function public.decide_quote(text, boolean) to anon, authenticated;

-- ─────────── إعدادٌ سرّيّ: يُكتب ولا يُقرأ ───────────
-- ودمجٌ لا استبدال — ما لا تعرفه الدالّةُ لا تمحُه.
create or replace function public.set_secret(p_key text, p_patch jsonb)
returns boolean language plpgsql security definer
set search_path = public, app as $$
begin
  if not app.is_admin() then raise exception 'للمدير وحده'; end if;
  if p_key not like '%\_secret' then
    raise exception 'هذه الدالّةُ للأسرار وحدها (مفتاحٌ ينتهي بـ_secret)';
  end if;
  insert into public.settings (key, value) values (p_key, p_patch)
  on conflict (key) do update
     set value = public.settings.value || p_patch, updated_at = now();
  return true;
end $$;

revoke all on function public.set_secret(text, jsonb) from public, anon;
grant execute on function public.set_secret(text, jsonb) to authenticated;

-- ─────────── ملفٌّ لكلِّ داخلٍ جديد ───────────
-- دورُه 'customer' ابتداءً، والترقيةُ قرارٌ لا تلقائيّة: أخطرُ ما في
-- نظامِ أدوارٍ أن يُمنح الدورُ بلا قائل.
create or replace function app.handle_new_user() returns trigger
language plpgsql security definer set search_path = public, auth as $$
begin
  insert into public.profiles (id, role, full_name, email, phone)
  values (new.id, 'customer',
          coalesce(new.raw_user_meta_data->>'full_name', ''),
          new.email,
          coalesce(new.raw_user_meta_data->>'phone', ''))
  on conflict (id) do nothing;
  return new;
end $$;

drop trigger if exists trg_new_user on auth.users;
create trigger trg_new_user after insert on auth.users
  for each row execute function app.handle_new_user();
