-- ══ علامةُ قالب الإنجاز ══
--
-- التطبيق يسأل: أيُّ قالبٍ هو ملفُّ إنجاز هذا الموظّف؟ و`role_keys` تدلّ
-- على **مَن** لا على **ماذا**: خطّةُ الوكيل ومحضرُ اجتماعه كلاهما موجّهٌ
-- إليه، وواحدٌ منهما فقط ملفُّ إنجاز.
--
-- ولمَ لا يُستدلّ بالعنوان («ملفّ إنجاز…»)؟ لأنّ الاستدلالَ بالنصّ يعمل
-- حتّى يُسمّي المالكُ قالبًا «سجلّ الإنجاز» أو «حقيبة المعلّم» — فيصمت
-- الزرّ ولا يُعرف لمَ.
--
--     ما يُبنى عليه قرارٌ يُصرَّح به، لا يُستنبَط من اسم.

alter table public.templates
  add column if not exists is_portfolio boolean not null default false;

comment on column public.templates.is_portfolio is
  'قالبُ ملفّ إنجازٍ سنويّ — يُركّبه الذكاء من شواهد الموظّف. مع role_keys يحدّد قالبَ كلّ فئة.';

-- والبحثُ يقع على المنشور وحده، فالفهرسُ جزئيّ: أصغرُ وأسرع.
create index if not exists templates_portfolio_idx
  on public.templates (is_portfolio, status)
  where is_portfolio;
