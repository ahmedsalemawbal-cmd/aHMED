-- ============================================================
-- 0003 — الأدوار والباقات وإعدادات المنصّة
-- ============================================================

insert into public.roles (key, name_ar, blurb_ar, sort, is_system) values
  ('principal',      'مدير المدرسة',  'حقيبة المدير · الخطط · محاضر الاجتماعات · التقارير', 1, true),
  ('vice_principal', 'وكيل المدرسة',  'ملفّات الوكالة · الجداول · المناوبة',                2, true),
  ('teacher',        'معلّم',          'ملفّ الإنجاز · سجلّ المتابعة · التحضير · الاختبارات', 3, true),
  ('activity_leader','رائد النشاط',   'خطط الأنشطة · السجلّات · التقارير',                  4, true),
  ('counselor',      'موجّه طلابيّ',   'ملفّات التوجيه · الحالات · البرامج الوقائية',        5, true),
  ('general',        'ملفّات عامّة',    'يراها الجميع مهما كان دورهم',                       6, true)
on conflict (key) do update set name_ar = excluded.name_ar, blurb_ar = excluded.blurb_ar, sort = excluded.sort;

-- template_categories الفارغة تعني كلّ الفئات — لا لا شيء
insert into public.plans (key, name_ar, account_type, price_sar, period_months, seats,
                          template_categories, noor_enabled, ai_quota_monthly, features_ar,
                          is_active, is_default, sort) values
  ('school_annual', 'باقة المدرسة', 'school', 749, 12, 10, '{}', true, 500,
    array['10 مقاعد — بينها حساب المدير','أدوارٌ حرّة: وكيل · معلّم · مشرف · موجّه',
          'كلّ فئات القوالب — 71 ملفًّا','جداول نور بلا حدّ','500 تحسين بالذكاء شهريًّا',
          'ترويسة المدرسة وشعارها','فاتورة واحدة للمدرسة','دعم عبر واتساب'],
    true, true, 1),
  ('teacher_annual', 'باقة المعلّم', 'teacher', 99, 12, 1, '{teacher,general}', true, 100,
    array['مقعد واحد باسمك','ملفّات دورك والملفّات العامّة','جداول نور بلا حدّ',
          '100 تحسين بالذكاء شهريًّا','ترويسة باسمك على كلّ ملفّ','دعم عبر واتساب'],
    true, true, 2)
on conflict (key) do update set
  name_ar = excluded.name_ar, price_sar = excluded.price_sar, seats = excluded.seats,
  template_categories = excluded.template_categories, ai_quota_monthly = excluded.ai_quota_monthly,
  features_ar = excluded.features_ar;

insert into public.platform_settings (key, value) values
  ('general', jsonb_build_object(
     'platform_name','مِداد', 'tagline','منصّة الملفّات المدرسية والجداول',
     'whatsapp','966500000000', 'email','support@midad.sa',
     'working_hours','الأحد — الخميس · 9 صباحًا إلى 5 مساءً')),
  ('payment_public', jsonb_build_object(
     'payments_enabled', false, 'beneficiary','مؤسسة مِداد لتقنية المعلومات',
     'bank','مصرف الراجحي', 'iban','SA0000000000000000000000',
     'tax_rate', 0, 'tax_number','', 'show_tax', false)),
  ('payment_secret', jsonb_build_object('moyasar_key','')),
  ('ai', jsonb_build_object('provider','anthropic','model','claude-sonnet-4-5',
                            'monthly_cap_calls', 20000, 'enabled', true)),
  ('ai_secret', jsonb_build_object('api_key','')),
  ('trial', jsonb_build_object('days', 7, 'watermark_text','نسخة تجريبية — مِداد'))
on conflict (key) do nothing;
