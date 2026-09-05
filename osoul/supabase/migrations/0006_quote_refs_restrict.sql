-- ══════════════════════════════════════════════════════════════
-- أصولُ البناء — 0006 : مراجعُ العرض تُقيَّد ولا تُفرَّغ
--
-- كُتبت المفاتيحُ الأجنبيّةُ في 0001 بـ`on delete set null`، وكشف
-- فحصُ الجدار أنّها **خطأٌ بنيويٌّ لا تفصيلَ إعداد**:
--
--   ① حذفُ شريكٍ يُفرِّغ `quotes.partner_id`، فيصطدم بالقيد
--      `quotes_partner_required` الذي يشترط مالكًا لكلّ عرضٍ ليس
--      من عروض أصول.
--   ② ولو نجا من القيد لَصدمه المُحفِّزُ `app.quote_kind_frozen`
--      الذي يمنع تبديلَ صاحب العرض بعد إنشائه.
--   ③ والأخطرُ لو نجا منهما: صفُّ 'cust' بلا مالكٍ **يسقط عنه
--      الجدار** — إذ شرطُه `partner_id = auth.uid()`، وقيمةٌ فارغةٌ
--      لا تساوي أحدًا. فيبقى في القاعدة صفٌّ فيه بياناتُ عميلِ
--      شريكٍ لا حارسَ له، ينتظر سياسةً أوسعَ تُكتب يومًا.
--
-- والعرضُ **وثيقةٌ ضريبيّة**: له رقمٌ متسلسلٌ وختمٌ زمنيّ، ويُسلَّم
-- إلى جهاتٍ حكوميّة. فما تشير إليه لا يُحذف من تحتها.
--
--     ما لا يُمحى أثرُه لا يُمحى أصلُه.
--
-- والبديلُ عمليٌّ لا نظريّ: الحسابُ **يُعطَّل** (`is_active = false`)
-- ولا يُحذف — وقد كان هذا مقصودَ العمود من أوّله.
-- ══════════════════════════════════════════════════════════════

alter table public.quotes drop constraint if exists quotes_partner_id_fkey;
alter table public.quotes add constraint quotes_partner_id_fkey
  foreign key (partner_id) references public.profiles(id) on delete restrict;

alter table public.quotes drop constraint if exists quotes_rep_id_fkey;
alter table public.quotes add constraint quotes_rep_id_fkey
  foreign key (rep_id) references public.profiles(id) on delete restrict;

alter table public.quotes drop constraint if exists quotes_branch_id_fkey;
alter table public.quotes add constraint quotes_branch_id_fkey
  foreign key (branch_id) references public.profiles(id) on delete restrict;

alter table public.quotes drop constraint if exists quotes_customer_uid_fkey;
alter table public.quotes add constraint quotes_customer_uid_fkey
  foreign key (customer_uid) references public.profiles(id) on delete restrict;

-- و`created_by` تبقى `set null`: أثرٌ إداريٌّ لا طرفٌ في الوثيقة،
-- ولا يتعلّق بها جدارٌ ولا قيد.
alter table public.quotes drop constraint if exists quotes_created_by_fkey;
alter table public.quotes add constraint quotes_created_by_fkey
  foreign key (created_by) references public.profiles(id) on delete set null;

-- وجهاتُ اتّصالِ الشريك تُحذف بحذفه (`cascade` كما في 0001): هي ملكُه
-- لا وثيقةٌ صدرت، وبقاؤها بلا مالكٍ يُسقط عنها الجدارَ نفسَه.
