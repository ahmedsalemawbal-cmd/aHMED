-- ══════════════════════════════════════════════════════════════
-- أصولُ البناء — 0002 : الدوالُّ والسياسات
--
-- والدرسُ المكتوبُ في 0013 من مِداد أصلُ هذا الملفّ كلِّه:
--
--     السياساتُ تُجمع بـ«أو» لا بـ«و».
--
-- فثلاثةُ أعرافٍ تُلتزم حرفيًّا:
--   ① لا `for all using (app.is_admin())` على جدولٍ فيه جدار. الكتابةُ
--      تُفصل عن القراءة دائمًا: أربعُ سياساتٍ لا واحدة.
--   ② ما لا يجوز نقضُه يُكتب `as restrictive` — فيُجمع بـ«و» ولا
--      يُبطله توسّعٌ لاحق. وهذا هو الجدار.
--   ③ ما يجب ألّا يُقرأ ألبتّة يُمنع بـ`revoke` لا بسياسة — لأنّ
--      **المِنَحَ تُجمع بـ«و» والسياساتِ بـ«أو»**. المنحةُ المنزوعةُ
--      قفلٌ ثانٍ حقيقيّ، والسياسةُ الثانيةُ ليست قفلًا.
-- ══════════════════════════════════════════════════════════════

-- ─────────────────── دوالُّ المساعدة ───────────────────
-- كلُّها `security definer` + `set search_path` — والثانيةُ ليست زينة:
-- بغيرها يضع مستدعٍ في `pg_temp` جدولًا اسمُه `profiles` فيه صفٌّ دورُه
-- `admin`، فتقرأ الدالّةُ ما كتبه بيده.

create or replace function app.role() returns text
language sql stable security definer set search_path = public, auth as $$
  select role::text from public.profiles where id = auth.uid()
$$;

create or replace function app.is_admin() returns boolean
language sql stable security definer set search_path = public, auth as $$
  select coalesce((select role = 'admin' from public.profiles where id = auth.uid()), false)
$$;

create or replace function app.branch_id() returns uuid
language sql stable security definer set search_path = public, auth as $$
  select branch_id from public.profiles where id = auth.uid()
$$;

-- والشريكُ لا يُعدّ حيًّا حتّى يعتمده المدير — وإلّا سجّل نفسَه وكتب
-- في القاعدة قبل أن يعرفه أحد.
create or replace function app.active() returns boolean
language sql stable security definer set search_path = public, auth as $$
  select coalesce((
    select p.is_active and (p.role <> 'partner' or p.approved)
      from public.profiles p where p.id = auth.uid()), false)
$$;

-- ══ الثابتُ ② — متى يُرى المال ══
-- شرطٌ واحدٌ في موضعٍ واحد، تستعمله سياستا الأسعار والمال جميعًا.
-- ولو كُرّر لتباعدا يومًا فسُدّ أحدُهما دون الآخر.
--
-- وهي `definer` فتتخطّى سياساتِ `quotes` — ولذلك **تُعيد بناءَ الجدار
-- في داخلها**. دالّةُ definer تنسى ما تحميه ثغرةٌ أوسعُ ممّا بُني قبلها.
--
--     ما يتخطّى الحارسَ يحمل حراستَه معه.
create or replace function app.money_visible(p_quote uuid) returns boolean
language sql stable security definer set search_path = public, auth as $$
  select exists (
    select 1 from public.quotes q
     where q.id = p_quote
       and case app.role()
             when 'admin'    then q.kind <> 'cust'
             when 'partner'  then q.partner_id = auth.uid()
                                  and (q.kind = 'cust' or q.issued_at is not null)
             when 'branch'   then q.branch_id    = auth.uid() and q.issued_at is not null
             when 'rep'      then q.rep_id       = auth.uid() and q.issued_at is not null
             when 'customer' then q.customer_uid = auth.uid() and q.issued_at is not null
             else false
           end)
$$;

grant usage on schema app to authenticated, anon;
grant execute on all functions in schema app to authenticated, anon;

-- ─────────────────── المُحفِّزات ───────────────────

create or replace function app.touch_updated_at() returns trigger
language plpgsql set search_path = '' as $$
begin new.updated_at = now(); return new; end $$;

do $$
declare t text;
begin
  foreach t in array array['profiles','partner_profiles','products','product_prices',
                           'quotes','quote_prices','quote_money','contacts',
                           'employee_mailboxes','settings'] loop
    execute format('drop trigger if exists trg_touch_%1$s on public.%1$s', t);
    execute format('create trigger trg_touch_%1$s before update on public.%1$s
                    for each row execute function app.touch_updated_at()', t);
  end loop;
end $$;

-- ══ الثابتُ ③ — العلامةُ تُختم ولا تُفكّ ══
--
-- وحراستُها شقّان، لأنّ شقًّا واحدًا لا يكفي:
--   ① قبل الإصدار: لا أحدَ يكتب `brand` ولا `number` ولا `issued_at`
--      بيده أصلًا — تُكتب من `public.issue_quote` وحدها، وعلامتُها
--      رايةٌ في الجلسة لا تُرفع إلّا داخلها.
--   ② بعد الإصدار: لا تُبدَّل ولو من الدالّة.
--
-- ولمَ الرايةُ لا اسمُ المستدعي؟ لأنّ `session_user` واحدٌ في الحالين،
-- والدالّةُ definer تشتغل باسم المالك — فيصير الشرطُ «كلُّ definer
-- مسموح»، وهذا أوسعُ من المقصود بكثير.
create or replace function app.quote_seal() returns trigger
language plpgsql security definer set search_path = public, auth as $$
declare sealing boolean := coalesce(current_setting('app.sealing', true), '') = 'on';
begin
  if tg_op = 'INSERT' then
    if not sealing and (new.issued_at is not null or new.number is not null
                        or new.brand <> '{}'::jsonb) then
      raise exception 'الإصدارُ والترقيمُ والعلامةُ تُختم بـissue_quote وحدها'
        using errcode = 'check_violation';
    end if;
    return new;
  end if;

  if old.issued_at is not null then
    if new.brand is distinct from old.brand then
      raise exception 'العلامةُ مختومةٌ بالإصدار فلا تُبدَّل'
        using errcode = 'check_violation'; end if;
    if new.number is distinct from old.number then
      raise exception 'رقمُ العرض لا يُبدَّل بعد الإصدار'
        using errcode = 'check_violation'; end if;
    if new.issued_at is distinct from old.issued_at then
      raise exception 'وقتُ الإصدار لا يُبدَّل'
        using errcode = 'check_violation'; end if;
  elsif not sealing then
    if new.issued_at is not null or new.number is not null
       or new.brand is distinct from old.brand then
      raise exception 'الإصدارُ يكون بـissue_quote لا بتعديلِ عمود'
        using errcode = 'check_violation';
    end if;
  end if;
  return new;
end $$;

drop trigger if exists trg_quote_seal on public.quotes;
create trigger trg_quote_seal before insert or update on public.quotes
  for each row execute function app.quote_seal();

-- ونوعُ العرض وصاحبُه يُثبتان مطلقًا — قبل الإصدار وبعده. فصفٌّ يُنشأ
-- 'osoul' ثمّ يُقلب 'cust' يهرب من عين أصول وهو لها؛ وصفٌّ يُقلب من
-- 'cust' إلى 'osoul' يجرّ عميلَ الشريك إليها.
create or replace function app.quote_kind_frozen() returns trigger
language plpgsql set search_path = '' as $$
begin
  if new.kind is distinct from old.kind then
    raise exception 'نوعُ العرض يُحدَّد عند الإنشاء ولا يُبدَّل'
      using errcode = 'check_violation';
  end if;
  if new.partner_id is distinct from old.partner_id then
    raise exception 'صاحبُ العرض يُحدَّد عند الإنشاء ولا يُبدَّل'
      using errcode = 'check_violation';
  end if;
  return new;
end $$;

drop trigger if exists trg_quote_kind on public.quotes;
create trigger trg_quote_kind before update on public.quotes
  for each row execute function app.quote_kind_frozen();

-- ولا يُرقّي أحدٌ نفسَه. `with check` تفحص الصفَّ الجديد ولا تقارنه
-- بالقديم — ومقارنةُ القديم بالجديد ليست من شأن RLS، بل عملُ مُحفِّز.
create or replace function app.role_frozen() returns trigger
language plpgsql security definer set search_path = public, auth as $$
begin
  if new.role is distinct from old.role and not app.is_admin() then
    raise exception 'الدورُ يمنحه المديرُ ولا يُنتحل' using errcode = 'check_violation';
  end if;
  if new.approved is distinct from old.approved and not app.is_admin() then
    raise exception 'الاعتمادُ من المدير' using errcode = 'check_violation';
  end if;
  if new.branch_id is distinct from old.branch_id
     and not app.is_admin() and app.role() <> 'branch' then
    raise exception 'الانتسابُ إلى الفرع يقرّره الفرعُ أو المدير'
      using errcode = 'check_violation';
  end if;
  return new;
end $$;

drop trigger if exists trg_role_frozen on public.profiles;
create trigger trg_role_frozen before update on public.profiles
  for each row execute function app.role_frozen();

-- ─────────────────── تشغيلُ الحماية ───────────────────
-- بلا استثناء. وجدولٌ يُنسى هنا مفتوحٌ للعالم بمفتاح anon، لا
-- «محميٌّ افتراضًا».
alter table public.profiles                 enable row level security;
alter table public.partner_profiles         enable row level security;
alter table public.products                 enable row level security;
alter table public.product_prices           enable row level security;
alter table public.quotes                   enable row level security;
alter table public.quote_items              enable row level security;
alter table public.quote_prices             enable row level security;
alter table public.quote_money              enable row level security;
alter table public.quote_counters           enable row level security;
alter table public.leads                    enable row level security;
alter table public.contacts                 enable row level security;
alter table public.contact_notes            enable row level security;
alter table public.chat_threads             enable row level security;
alter table public.chat_messages            enable row level security;
alter table public.employee_mailboxes       enable row level security;
alter table public.employee_mailbox_secrets enable row level security;
alter table public.settings                 enable row level security;

-- ══════════════════════════════════════════════════════════════
--  الثابتُ ① — جدارُ خصوصيّةِ الشركاء
-- ══════════════════════════════════════════════════════════════
-- اليومَ يُنفَّذ بشرطِ استعلامٍ يُضاف يدويًّا في كلّ استعلام
-- (`osoul_quote_exclude_cust_clause()`). وشرطٌ يُضاف يدويًّا يُنسى
-- يدويًّا. والقاعدةُ لا تنسى.
--
-- وسياسةٌ متساهلةٌ تقول «إلّا cust» لا تكفي: سياسةٌ تُكتب غدًا تقول
-- «المديرُ يرى كلّ شيء» تُجمع معها بـ«أو» فتنقضها. فتُكتب `restrictive`.
--
--     المتساهلةُ تُضيف، والمُقيِّدةُ تحكم.
--
-- والشرطُ `partner_id = auth.uid()` لا `role = 'partner'` — فلا يرى
-- شريكٌ عملاءَ شريكٍ آخر ولو انفتحت كلُّ سياسةٍ متساهلة.
--
-- و`with check` ليست تكرارًا: `INSERT` لا يُقيَّم فيه `using` ألبتّة،
-- فجدارٌ بلا `with check` يمنع القراءةَ ويُبقي البابَ مفتوحًا لإدراج
-- صفِّ 'cust' باسم شريكٍ آخر.

drop policy if exists quotes_partner_wall on public.quotes;
create policy quotes_partner_wall on public.quotes as restrictive for all
  using      (kind <> 'cust' or partner_id = auth.uid())
  with check (kind <> 'cust' or partner_id = auth.uid());

-- والجدارُ يمتدّ إلى كلِّ ما يتدلّى من العرض. وقد يُظنّ أنّ البنودَ
-- «لا مالَ فيها فلتُفتح» — وهي تكشف مزيجَ منتجاتِ عميلِ الشريك
-- وكميّاتِه، وهذا وحده يكفي منافسًا.
--
--     حجبُ الحاوية لا يحجب ما فيها.
--
-- والاستعلامُ الفرعيُّ على `quotes` تسري عليه سياساتُ `quotes` نفسُها،
-- فيرث التابعُ جدارَ رأسِه: ما لا يُرى رأسُه لا تُرى بنودُه.

drop policy if exists quote_items_wall on public.quote_items;
create policy quote_items_wall on public.quote_items as restrictive for all
  using      (exists (select 1 from public.quotes q where q.id = quote_items.quote_id))
  with check (exists (select 1 from public.quotes q where q.id = quote_items.quote_id));

drop policy if exists quote_prices_wall on public.quote_prices;
create policy quote_prices_wall on public.quote_prices as restrictive for all
  using      (exists (select 1 from public.quotes q where q.id = quote_prices.quote_id))
  with check (exists (select 1 from public.quotes q where q.id = quote_prices.quote_id));

drop policy if exists quote_money_wall on public.quote_money;
create policy quote_money_wall on public.quote_money as restrictive for all
  using      (exists (select 1 from public.quotes q where q.id = quote_money.quote_id))
  with check (exists (select 1 from public.quotes q where q.id = quote_money.quote_id));

-- وجهاتُ اتّصالِ الشريك خلف الجدار نفسِه — وهي أخطرُ من العروض:
-- العرضُ صفقةٌ واحدة، وجهةُ الاتّصال **قائمةُ عملائه** كلُّها.
drop policy if exists contacts_wall on public.contacts;
create policy contacts_wall on public.contacts as restrictive for all
  using      (owner_partner_id is null or owner_partner_id = auth.uid())
  with check (owner_partner_id is null or owner_partner_id = auth.uid());

drop policy if exists contact_notes_wall on public.contact_notes;
create policy contact_notes_wall on public.contact_notes as restrictive for all
  using      (exists (select 1 from public.contacts c where c.id = contact_notes.contact_id))
  with check (exists (select 1 from public.contacts c where c.id = contact_notes.contact_id));

-- ─────────────────── profiles ───────────────────
drop policy if exists profiles_read on public.profiles;
create policy profiles_read on public.profiles for select using (
  id = auth.uid()
  or app.is_admin()
  or (app.role() = 'branch' and profiles.branch_id = auth.uid())
);

drop policy if exists profiles_self_update on public.profiles;
create policy profiles_self_update on public.profiles for update
  using (id = auth.uid()) with check (id = auth.uid());

drop policy if exists profiles_insert on public.profiles;
create policy profiles_insert on public.profiles for insert with check (
  (id = auth.uid() and role in ('customer','partner'))
  or app.is_admin()
  or (app.role() = 'branch' and role = 'rep' and branch_id = auth.uid())
);

drop policy if exists profiles_admin_update on public.profiles;
create policy profiles_admin_update on public.profiles for update
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists profiles_branch_update on public.profiles;
create policy profiles_branch_update on public.profiles for update
  using      (app.role() = 'branch' and profiles.role = 'rep' and branch_id = auth.uid())
  with check (app.role() = 'branch' and profiles.role = 'rep' and branch_id = auth.uid());

drop policy if exists profiles_delete on public.profiles;
create policy profiles_delete on public.profiles for delete using (app.is_admin());

-- ─────────────────── partner_profiles ───────────────────
drop policy if exists partner_profiles_read on public.partner_profiles;
create policy partner_profiles_read on public.partner_profiles for select
  using (user_id = auth.uid() or app.is_admin());

drop policy if exists partner_profiles_insert on public.partner_profiles;
create policy partner_profiles_insert on public.partner_profiles for insert
  with check (user_id = auth.uid() or app.is_admin());

drop policy if exists partner_profiles_update on public.partner_profiles;
create policy partner_profiles_update on public.partner_profiles for update
  using      (user_id = auth.uid() or app.is_admin())
  with check (user_id = auth.uid() or app.is_admin());

drop policy if exists partner_profiles_delete on public.partner_profiles;
create policy partner_profiles_delete on public.partner_profiles for delete
  using (app.is_admin());

-- ─────────────────── المنتجاتُ وأسعارُها ───────────────────
-- الكتالوجُ عامّ: الموقعُ يعرضه لزائرٍ لم يسجّل. والسعرُ ليس منه.
drop policy if exists products_read on public.products;
create policy products_read on public.products for select
  using (is_active or app.is_admin());

drop policy if exists products_insert on public.products;
create policy products_insert on public.products for insert with check (app.is_admin());
drop policy if exists products_update on public.products;
create policy products_update on public.products for update
  using (app.is_admin()) with check (app.is_admin());
drop policy if exists products_delete on public.products;
create policy products_delete on public.products for delete using (app.is_admin());

-- «المندوبُ يتصفّح المنتجات بلا أسعار» — يُفرض بأنّ السعرَ جدولٌ لا
-- عمود. ولو كان عمودًا في `products` لقرأه بـ`?select=price` مهما
-- صنعت الواجهة، لأنّ RLS ترشّح الصفوفَ لا الأعمدة.
drop policy if exists product_prices_read on public.product_prices;
create policy product_prices_read on public.product_prices for select
  using (app.is_admin());
drop policy if exists product_prices_insert on public.product_prices;
create policy product_prices_insert on public.product_prices for insert
  with check (app.is_admin());
drop policy if exists product_prices_update on public.product_prices;
create policy product_prices_update on public.product_prices for update
  using (app.is_admin()) with check (app.is_admin());
drop policy if exists product_prices_delete on public.product_prices;
create policy product_prices_delete on public.product_prices for delete
  using (app.is_admin());

-- ─────────────────── العروض ───────────────────
-- سياسةُ قراءةٍ **واحدة** لا اثنتان: سياستان تُجمعان بـ«أو» فأوسعُهما
-- هي القانون. و`case` يجعل الجوابَ واحدًا لكلّ دور — فلا اتّحادَ فلا نقض.
drop policy if exists quotes_read on public.quotes;
create policy quotes_read on public.quotes for select using (
  case app.role()
    when 'admin'    then kind <> 'cust'
    when 'branch'   then kind <> 'cust' and branch_id    = auth.uid()
    when 'rep'      then kind <> 'cust' and rep_id       = auth.uid()
    when 'partner'  then                    partner_id   = auth.uid()
    when 'customer' then kind  = 'osoul' and customer_uid = auth.uid()
    else false
  end
);

drop policy if exists quotes_insert on public.quotes;
create policy quotes_insert on public.quotes for insert with check (
  app.active() and case app.role()
    when 'admin'    then kind <> 'cust'
    when 'branch'   then kind = 'osoul' and source = 'branch'
                         and branch_id = auth.uid() and rep_id is null
    when 'rep'      then kind = 'osoul' and source = 'rep'
                         and rep_id = auth.uid()
                         and branch_id is not distinct from app.branch_id()
    when 'partner'  then kind in ('supply','cust') and source = 'partner'
                         and partner_id = auth.uid()
    when 'customer' then kind = 'osoul' and source = 'web'
                         and customer_uid = auth.uid()
    else false
  end
);

-- والفرعُ والمندوبُ لا يعدّلان: يطلبان ويتابعان. وتغييرُ المرحلة بيد
-- المدير، لأنّ مندوبًا يقلب عرضَه 'won' يفسد الإحصاء.
drop policy if exists quotes_update on public.quotes;
create policy quotes_update on public.quotes for update
  using (case app.role()
           when 'admin'   then kind <> 'cust'
           when 'partner' then partner_id = auth.uid()
           else false end)
  with check (case app.role()
                when 'admin'   then kind <> 'cust'
                when 'partner' then partner_id = auth.uid()
                else false end);

drop policy if exists quotes_delete on public.quotes;
create policy quotes_delete on public.quotes for delete using (
  case app.role()
    when 'admin'   then kind <> 'cust'
    -- والشريكُ يحذف ما لم يُصدره فقط: عرضٌ سُلّم لعميلٍ لا يُمحى من
    -- تحته، والرقمُ الذي صدر لا يُعاد استعمالُه.
    when 'partner' then partner_id = auth.uid() and issued_at is null
    else false
  end
);

drop policy if exists quote_items_read on public.quote_items;
create policy quote_items_read on public.quote_items for select
  using (exists (select 1 from public.quotes q where q.id = quote_items.quote_id));

drop policy if exists quote_items_insert on public.quote_items;
create policy quote_items_insert on public.quote_items for insert with check (
  exists (select 1 from public.quotes q
           where q.id = quote_items.quote_id and q.issued_at is null)
  or app.is_admin());

drop policy if exists quote_items_update on public.quote_items;
create policy quote_items_update on public.quote_items for update
  using (exists (select 1 from public.quotes q
                  where q.id = quote_items.quote_id and q.issued_at is null)
         or app.is_admin())
  with check (exists (select 1 from public.quotes q where q.id = quote_items.quote_id));

drop policy if exists quote_items_delete on public.quote_items;
create policy quote_items_delete on public.quote_items for delete
  using (exists (select 1 from public.quotes q
                  where q.id = quote_items.quote_id and q.issued_at is null)
         or app.is_admin());

-- ══ الثابتُ ② في سطرٍ واحد ══
-- لا حجبَ في الواجهة: صفُّ السعر **غيرُ موجودٍ** في نظرِ المندوب حتّى
-- يُصدِر المديرُ العرض. ولو نادى `/rest/v1/quote_prices` بيده رجع
-- بمصفوفةٍ فارغة.
drop policy if exists quote_prices_read on public.quote_prices;
create policy quote_prices_read on public.quote_prices for select
  using (app.money_visible(quote_id));

drop policy if exists quote_prices_insert on public.quote_prices;
create policy quote_prices_insert on public.quote_prices for insert with check (
  exists (select 1 from public.quotes q where q.id = quote_prices.quote_id
           and ((app.is_admin() and q.kind <> 'cust')
             or (app.role() = 'partner' and q.partner_id = auth.uid() and q.kind = 'cust'))));

drop policy if exists quote_prices_update on public.quote_prices;
create policy quote_prices_update on public.quote_prices for update
  using (exists (select 1 from public.quotes q where q.id = quote_prices.quote_id
          and ((app.is_admin() and q.kind <> 'cust')
            or (app.role() = 'partner' and q.partner_id = auth.uid()
                and q.kind = 'cust' and q.issued_at is null))))
  with check (exists (select 1 from public.quotes q where q.id = quote_prices.quote_id
          and ((app.is_admin() and q.kind <> 'cust')
            or (app.role() = 'partner' and q.partner_id = auth.uid() and q.kind = 'cust'))));

drop policy if exists quote_prices_delete on public.quote_prices;
create policy quote_prices_delete on public.quote_prices for delete
  using (exists (select 1 from public.quotes q where q.id = quote_prices.quote_id
          and ((app.is_admin() and q.kind <> 'cust')
            or (app.role() = 'partner' and q.partner_id = auth.uid() and q.kind = 'cust'))));

drop policy if exists quote_money_read on public.quote_money;
create policy quote_money_read on public.quote_money for select
  using (app.money_visible(quote_id));

drop policy if exists quote_money_insert on public.quote_money;
create policy quote_money_insert on public.quote_money for insert with check (
  exists (select 1 from public.quotes q where q.id = quote_money.quote_id
           and ((app.is_admin() and q.kind <> 'cust')
             or (app.role() = 'partner' and q.partner_id = auth.uid() and q.kind = 'cust'))));

drop policy if exists quote_money_update on public.quote_money;
create policy quote_money_update on public.quote_money for update
  using (exists (select 1 from public.quotes q where q.id = quote_money.quote_id
          and ((app.is_admin() and q.kind <> 'cust')
            or (app.role() = 'partner' and q.partner_id = auth.uid() and q.kind = 'cust'))))
  with check (exists (select 1 from public.quotes q where q.id = quote_money.quote_id
          and ((app.is_admin() and q.kind <> 'cust')
            or (app.role() = 'partner' and q.partner_id = auth.uid() and q.kind = 'cust'))));

drop policy if exists quote_money_delete on public.quote_money;
create policy quote_money_delete on public.quote_money for delete
  using (app.is_admin() or (app.role() = 'partner'
    and exists (select 1 from public.quotes q where q.id = quote_money.quote_id
                 and q.partner_id = auth.uid() and q.kind = 'cust')));

-- ── منظرُ المجاميع ──
-- `security_invoker` ليست تفصيلًا: بدونها يُنفَّذ المنظرُ بصلاحيّة
-- مالكِه فيتخطّى الحمايةَ كلَّها — سطرٌ واحدٌ ينقض كلَّ ما فوقه،
-- ويقرأ به المندوبُ كلَّ مالٍ في القاعدة.
--
--     منظرٌ بلا invoker بابٌ في الجدار.
create or replace view public.quote_totals with (security_invoker = true) as
select p.quote_id,
       round(sum(p.unit_price * i.qty), 2)                    as subtotal,
       coalesce(m.discount_amount, 0)                         as discount,
       round(sum(p.unit_price * i.qty)
             - coalesce(m.discount_amount, 0), 2)             as taxable,
       round((sum(p.unit_price * i.qty) - coalesce(m.discount_amount, 0))
             * coalesce(m.vat_rate, 15) / 100.0, 2)           as vat,
       round((sum(p.unit_price * i.qty) - coalesce(m.discount_amount, 0))
             * (1 + coalesce(m.vat_rate, 15) / 100.0), 2)     as total
  from public.quote_prices p
  join public.quote_items  i on i.id = p.item_id
  left join public.quote_money m on m.quote_id = p.quote_id
 group by p.quote_id, m.discount_amount, m.vat_rate;

grant select on public.quote_totals to authenticated, anon;

create or replace view public.partner_margin with (security_invoker = true) as
select p.quote_id,
       round(sum((p.unit_price - p.cost_price) * i.qty), 2) as profit
  from public.quote_prices p
  join public.quote_items  i on i.id = p.item_id
  join public.quotes       q on q.id = p.quote_id and q.kind = 'cust'
 group by p.quote_id;

grant select on public.partner_margin to authenticated;

-- ── العدّاد: لا يُقرأ ولا يُكتب من متصفّحٍ ألبتّة ──
-- ولا سياسةَ له: جدولٌ محميٌّ بلا سياسة = صفرُ صفوفٍ للجميع. ثمّ
-- يُنزع المنحُ فوق ذلك، لأنّ **المنحَ يُجمع بـ«و»**: سياسةٌ تُكتب غدًا
-- بالخطأ لا تفتحه ما دامت المنحةُ منزوعة.
revoke all on public.quote_counters from anon, authenticated;

-- ─────────────────── الطلبات ───────────────────
-- نموذجُ الموقع يكتب بمفتاح anon — ولذلك الإدراجُ مفتوح. ولا قراءةَ
-- لأحدٍ سوى المدير وموظّفِ البريد: **من كتب لا يقرأ**.
drop policy if exists leads_insert on public.leads;
create policy leads_insert on public.leads for insert with check (true);
drop policy if exists leads_read on public.leads;
create policy leads_read on public.leads for select
  using (app.role() in ('admin','employee'));
drop policy if exists leads_update on public.leads;
create policy leads_update on public.leads for update
  using (app.is_admin()) with check (app.is_admin());
drop policy if exists leads_delete on public.leads;
create policy leads_delete on public.leads for delete using (app.is_admin());

-- ─────────────────── جهاتُ الاتّصال ───────────────────
drop policy if exists contacts_read on public.contacts;
create policy contacts_read on public.contacts for select using (
  case app.role()
    when 'admin'   then owner_partner_id is null
    when 'branch'  then owner_partner_id is null
    when 'rep'     then owner_partner_id is null
    when 'partner' then owner_partner_id = auth.uid()
    else false
  end
);

drop policy if exists contacts_insert on public.contacts;
create policy contacts_insert on public.contacts for insert with check (
  app.active() and case app.role()
    when 'admin'   then owner_partner_id is null
    when 'branch'  then owner_partner_id is null
    when 'rep'     then owner_partner_id is null
    when 'partner' then owner_partner_id = auth.uid()
    else false
  end);

drop policy if exists contacts_update on public.contacts;
create policy contacts_update on public.contacts for update
  using      (case app.role()
                when 'partner' then owner_partner_id = auth.uid()
                when 'admin'   then owner_partner_id is null
                when 'branch'  then owner_partner_id is null
                when 'rep'     then owner_partner_id is null
                else false end)
  with check (case app.role()
                when 'partner' then owner_partner_id = auth.uid()
                else owner_partner_id is null end);

drop policy if exists contacts_delete on public.contacts;
create policy contacts_delete on public.contacts for delete using (
  app.is_admin() and owner_partner_id is null);

drop policy if exists contact_notes_read on public.contact_notes;
create policy contact_notes_read on public.contact_notes for select
  using (exists (select 1 from public.contacts c where c.id = contact_notes.contact_id));
drop policy if exists contact_notes_insert on public.contact_notes;
create policy contact_notes_insert on public.contact_notes for insert with check (
  author_id = auth.uid()
  and exists (select 1 from public.contacts c where c.id = contact_notes.contact_id));
drop policy if exists contact_notes_update on public.contact_notes;
create policy contact_notes_update on public.contact_notes for update
  using (author_id = auth.uid()) with check (author_id = auth.uid());
drop policy if exists contact_notes_delete on public.contact_notes;
create policy contact_notes_delete on public.contact_notes for delete
  using (author_id = auth.uid() or app.is_admin());

-- ─────────────────── المحادثة ───────────────────
drop policy if exists chat_threads_read on public.chat_threads;
create policy chat_threads_read on public.chat_threads for select
  using (account_id = auth.uid() or app.is_admin());
drop policy if exists chat_threads_insert on public.chat_threads;
create policy chat_threads_insert on public.chat_threads for insert
  with check ((account_id = auth.uid() and app.active()) or app.is_admin());
drop policy if exists chat_threads_update on public.chat_threads;
create policy chat_threads_update on public.chat_threads for update
  using      (account_id = auth.uid() or app.is_admin())
  with check (account_id = auth.uid() or app.is_admin());
drop policy if exists chat_threads_delete on public.chat_threads;
create policy chat_threads_delete on public.chat_threads for delete using (app.is_admin());

drop policy if exists chat_messages_read on public.chat_messages;
create policy chat_messages_read on public.chat_messages for select
  using (exists (select 1 from public.chat_threads t where t.id = chat_messages.thread_id));
drop policy if exists chat_messages_insert on public.chat_messages;
-- ولا ينتحل أحدٌ جهةَ الردّ: صاحبُ الحساب لا يكتب رسالةً باسم الإدارة.
create policy chat_messages_insert on public.chat_messages for insert with check (
  sender_id = auth.uid()
  and exists (select 1 from public.chat_threads t where t.id = chat_messages.thread_id)
  and from_side = (case when app.is_admin() then 'admin' else 'account' end));
drop policy if exists chat_messages_update on public.chat_messages;
create policy chat_messages_update on public.chat_messages for update
  using (sender_id = auth.uid()) with check (sender_id = auth.uid());
drop policy if exists chat_messages_delete on public.chat_messages;
create policy chat_messages_delete on public.chat_messages for delete
  using (sender_id = auth.uid() or app.is_admin());

-- ─────────────────── بريدُ الموظّفين ───────────────────
drop policy if exists mailboxes_read on public.employee_mailboxes;
create policy mailboxes_read on public.employee_mailboxes for select
  using (user_id = auth.uid() or app.is_admin());
drop policy if exists mailboxes_insert on public.employee_mailboxes;
create policy mailboxes_insert on public.employee_mailboxes for insert
  with check (app.is_admin());
drop policy if exists mailboxes_update on public.employee_mailboxes;
create policy mailboxes_update on public.employee_mailboxes for update
  using      (app.is_admin() or user_id = auth.uid())
  with check (app.is_admin() or user_id = auth.uid());
drop policy if exists mailboxes_delete on public.employee_mailboxes;
create policy mailboxes_delete on public.employee_mailboxes for delete
  using (app.is_admin());

-- ══ السرُّ المكتوبُ غيرُ المقروء ══
-- **لا سياسةَ قراءةٍ ولا كتابةٍ ألبتّة.** جدولٌ محميٌّ بلا سياسة =
-- صفرُ صفوفٍ لكلّ أحد: لا الموظّف، ولا المدير، ولا anon. ويُنزع
-- المنحُ فوق ذلك.
--
--     قفلٌ من نوعين لا قفلان من نوع.
revoke all on public.employee_mailbox_secrets from anon, authenticated;

-- ─────────────────── الإعدادات ───────────────────
-- على نمط 0013 حرفيًّا: القراءةُ تُفصل عن الكتابة، ولا `for all`.
-- ولو بقيت `settings_admin for all` لأباحت ما منعته سياسةُ القراءة.
drop policy if exists settings_admin on public.settings;

drop policy if exists settings_read on public.settings;
create policy settings_read on public.settings for select using (
  key = any (array['general','brand','quote_doc','contact'])
  or (app.is_admin() and key not like '%\_secret')
);

drop policy if exists settings_insert on public.settings;
create policy settings_insert on public.settings for insert with check (app.is_admin());
drop policy if exists settings_update on public.settings;
create policy settings_update on public.settings for update
  using (app.is_admin()) with check (app.is_admin());
drop policy if exists settings_delete on public.settings;
create policy settings_delete on public.settings for delete using (app.is_admin());
