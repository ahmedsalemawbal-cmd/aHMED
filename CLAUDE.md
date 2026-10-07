# ميداني — نظام CRM ميداني لمستخدم واحد

نظام مبيعات ميداني لمسوّق واحد في حي الحمدانية: يسجّل المحل من الجوال أثناء الزيارة، يقيّم حضوره الرقمي، يولّد رسالة واتساب بالذكاء الاصطناعي، ويتابعه حتى الإغلاق.

## اقرأ قبل أي عمل

1. `docs/decisions.md` — تعديلات معتمدة **تتقدّم على المستند** عند التعارض.
2. `docs/brief.md` — موجز المشروع الكامل: الجداول، المراحل، الشاشات، قواعد التقييم والرسائل.
3. `docs/screens.md` — ما صُمّم من الشاشات وأين يوجد كل شيء.
4. `design/design-system.md` — قواعد نظام التصميم (ألوان، خط، مسافات، حالات، أيقونات).

## الستاك

- React + TypeScript + Vite + Tailwind، تطبيق PWA واحد للجوال والديسكتوب.
- Supabase: Postgres + RLS، Auth (بريد وكلمة مرور)، Storage (bucket خاص بروابط موقّعة)، Edge Functions.
- Edge Function `generate-message` تولّد الرسالة؛ مفتاح مزود الذكاء الاصطناعي في أسرار Supabase فقط ولا يصل للمتصفح.
- النشر: GitHub + Vercel.

## قواعد ثابتة

- الواجهة عربية بالكامل، `<html lang="ar" dir="rtl">`. استخدم الخصائص المنطقية (`ms-`, `me-`, `ps-`, `pe-`, `start`, `end`) لا `left/right`.
- الأرقام لاتينية 0–9 دائماً، و`tabular-nums` في الجداول والعدادات.
- الخط IBM Plex Sans Arabic من Google Fonts بوزنين فقط: 400 و700.
- كل جدول فيه `id, owner_id, created_at, updated_at`، وكل جدول محمي بسياسة RLS: `owner_id = auth.uid()`.
- الجوال يُخزَّن `phone_e164` بصيغة `9665XXXXXXXX` ويُحوَّل من `05XXXXXXXX` عند الحفظ.
- الواتساب: رابط `https://wa.me/<phone>?text=<encodeURIComponent(msg)>` فقط. لا WhatsApp API. الإرسال يُسجَّل فقط بعد تأكيد المستخدم «هل أرسلت؟».
- العميل لا يرى أي صفحة من النظام. لا صفحات عامة ولا روابط عامة.
- لا أسرار في الكود. المتغيرات في `.env.local` (`VITE_SUPABASE_URL`, `VITE_SUPABASE_ANON_KEY`) ولا يُرفع هذا الملف للمستودع.

## نظام التصميم في الكود

- `design/tokens.css` هو مصدر الألوان والمسافات: انسخه إلى `src/styles/tokens.css` واستورده عالمياً. الوضع الفاتح افتراضي، والداكن عبر `[data-theme="dark"]`.
- اربط Tailwind بالمتغيرات (مثلاً `colors: { surface: 'var(--surface)', ink: 'var(--ink)', action: 'var(--action)', 'stage-visited': 'var(--stage-visited)', … }`). لا تكتب ألواناً hex داخل المكونات.
- `design/components/bundle.js` و`bundle.css` و`index.d.ts` هي التنفيذ المرجعي لمكونات النظام (Button, TextField, StageBadge, PriorityBadge, LeadCard, ScoreBar, TriSelect, BottomSheet, Toast, EmptyState). أعد كتابتها كمكونات React + TypeScript في `src/components/ui/` بنفس الـ props والسلوك والشكل. إرشادات كل مكوّن في `design/components/<Name>.md`.
- `design/screens/*.dc.html` تصاميم الشاشات المعتمدة (صيغة خاصة بأداة التصميم: HTML مع `{{holes}}` و`<sc-for>` و`<sc-if>` و`<x-import>` للمكونات). استخدمها مرجعاً للتخطيط والنصوص والبيانات والسلوك، ولا تنسخ صيغتها.
- مساحة اللمس 48px حد أدنى، والحقول 16px نصاً على الجوال، والإجراء الرئيسي في الثلث السفلي.
- كل شاشة لها خمس حالات: تحميل (هياكل وهمية)، فارغة، خطأ مع إعادة المحاولة، بدون اتصال (شريط أعلى الشاشة)، نجاح (Toast).
- نصوص الواجهة قصيرة وبصيغة الأمر. رسالة الخطأ: ما حدث + الحل. بدون إيموجي.

## طريقة العمل

- نفّذ مرحلة واحدة في كل مرة حسب `prompts/`، واعرض الخطة قبل الكود.
- بعد كل دفعة: شغّل `npm run build` و`npm run lint` وتأكد أنها تمر، وجرّب الشاشة بعرض 390px.
- رسائل commit واضحة بالإنجليزية، وكل دفعة في commit مستقل.
- إذا احتجت قراراً غير موجود في المستند، اسأل ولا تخمّن.
