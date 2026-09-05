-- بذورُ الإعدادات — القيمُ مأخوذةٌ من `inc/settings.php` كما هي،
-- فلا يتبدّل على الزائر شيءٌ يوم التحويل.
--
-- و`on conflict do nothing` لا `do update`: هذه بذرةٌ لا مصدرُ حقيقة.
-- فمتى عدّل المالكُ رقمَ جوّالٍ من اللوحة، لم تُعِده هجرةٌ تُشغَّل ثانيةً
-- إلى ما كان.
--
--     البذرةُ تُنبت مرّةً ولا تدهس ما نبت.

insert into public.settings (key, value) values
  ('general', jsonb_build_object(
     'company_name_ar','شركة أصول البناء للصناعة',
     'company_name_en','Osoul Albinaa Industrial Co.',
     'whatsapp','966556847029',
     'email','info@osoulalbinaa.com',
     'phone_primary','+966 563 627 063',
     'phone_secondary','+966 11 810 8717',
     'hours_ar','السبت — الخميس | 8:00 ص — 5:00 م',
     'hours_en','Sat – Thu | 8:00 AM – 5:00 PM')),

  ('contact', jsonb_build_object(
     'address_ar','المملكة العربية السعودية — جدة، المدينة الصناعية الثالثة',
     'address_en','Saudi Arabia — Jeddah, 3rd Industrial City',
     'maps_embed','https://www.google.com/maps?q=21.1401875,39.3309375&hl=ar&z=16&output=embed',
     'instagram','', 'linkedin','', 'twitter','')),

  ('brand', jsonb_build_object(
     'color','#00344F', 'accent','#0074A4',
     'logo_ar','https://osoulalbinaa.com/wp-content/uploads/2026/07/1.png',
     'logo_en','https://osoulalbinaa.com/wp-content/uploads/2026/07/2.png',
     'favicon','')),

  -- بادئةُ ترقيم أصول تُقرأ من هنا في `app.next_number`.
  ('quote_doc', jsonb_build_object(
     'quote_prefix','OSB',
     'vat_number','311325076500003',
     'cr_number','4030495019',
     'vat_rate', 15,
     'validity_days', 30,
     'national_address_ar','جدة — المدينة الصناعية الثالثة، المملكة العربية السعودية',
     'national_address_en','Jeddah — 3rd Industrial City, Saudi Arabia',
     'terms_ar', E'• الأسعار بالريال السعودي ولا تشمل ضريبة القيمة المضافة (تُضاف 15%).\n• هذا العرض ساري للمدة الموضحة أعلاه.\n• التسليم والتركيب حسب الاتفاق.',
     'terms_en', E'• Prices are in SAR and exclude VAT (15% added).\n• This quotation is valid for the period shown above.\n• Delivery and installation as agreed.')),

  -- الأسرارُ تُبذر فارغةً ليوجد الصفُّ فتكتب فيه `set_secret` بالدمج.
  -- ولا يقرؤها أحدٌ من متصفّح: سياسةُ القراءة تستثني `%_secret`.
  ('google_secret', jsonb_build_object('client_id','', 'client_secret','')),
  ('mail_secret',   jsonb_build_object('smtp_relay_key',''))
on conflict (key) do nothing;
