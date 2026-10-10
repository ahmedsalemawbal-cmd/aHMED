# خطة تنفيذ «ميداني» — المرحلة 1

المصادر: `CLAUDE.md` ← `docs/decisions.md` (تتقدّم) ← `docs/brief.md` ← `docs/screens.md` ← `design/**`.
كل بند عليه علامة **[مقترح · Qn]** غير مكتوب في الملفات، ولن يُنفَّذ قبل حسمه في `docs/QUESTIONS.md`.

الحالة: ☐ لم يبدأ · ◐ جزئي · ☑ تحقق.

---

## 1. البيانات

### 1.1 أعمدة مشتركة في كل جدول
`id uuid pk default gen_random_uuid()`، `owner_id uuid not null default auth.uid() references auth.users`، `created_at timestamptz default now()`، `updated_at timestamptz default now()` (يُحدَّث بـ trigger).
RLS مفعّل على كل جدول، و4 سياسات (select/insert/update/delete) شرطها `owner_id = auth.uid()`، ومع insert/update يكون الشرط `with check (owner_id = auth.uid())`.
المفاتيح الأجنبية بين الجداول تُفحص بنفس المالك: trigger يرفض ربط سجل بـ `lead_id` يملكه مستخدم آخر.

### 1.2 الجداول (13 جدولاً بعد حذف `audit_reports`)

| الجدول | الحقول (عدا المشتركة) | ملاحظات |
|---|---|---|
| `profiles` | `full_name`, `brand_name`, `phone`, `signature`, `default_tone` | المفتاح `id = auth.uid()` (owner_id = id). تُنشأ تلقائياً عند أول دخول. إضافات **[مقترح · Q1، Q2، Q8]**: `weekly_visit_goal int default 20`, `theme`, `vat_enabled bool default false`, `vat_rate numeric null` |
| `activity_types` | `name`, `icon`, `checklist jsonb`, `default_service_ids uuid[]` | `checklist` = `{ general: [{id,label,weight,group}], specific: [{id,label}] }` البنود الخاصة بلا وزن |
| `leads` | `business_name`, `activity_type_id`, `contact_name`, `contact_role`, `phone_e164`, `is_decision_maker`, `best_contact_time`, `wa_consent`, `do_not_contact`, `lat`, `lng`, `address`, `maps_url`, `instagram_url`, `source`, `stage`, `score`, `priority`, `expected_value`, `next_action_at`, `lost_reason` | إضافات **[مقترح · Q3، Q11]**: `won_value`, `won_billing`, `stage_changed_at`, `last_contact_at`. `quotes.public_token` وقيمة `qr` محذوفتان |
| `visits` | `lead_id`, `visited_at`, `notes`, `key_observation` (≤ 90 حرفاً), `outcome`, `next_step`, `lat`, `lng` | |
| `visit_media` | `visit_id`, `storage_path`, `kind` | bucket خاص `visit-media`، المسار `{owner_id}/{visit_id}/{file}`، سياسات Storage بنفس المالك، والعرض بروابط موقّعة |
| `assessments` | `lead_id`, `visit_id`, `answers jsonb`, `score`, `weaknesses jsonb` | `answers` = `{ [itemId]: 'yes'|'partial'|'no' }`. `weaknesses` = معرّفات البنود التي إجابتها لا أو جزئي |
| `services` | `name`, `description`, `price_from`, `billing`, `activity_type_ids uuid[]`, `is_active` | إضافة **[مقترح · Q12]**: `weakness_item_ids text[]` لربط نقطة الضعف بالخدمة |
| `lead_services` | `lead_id`, `service_id`, `status`, `note` | `note` = سبب الاقتراح («لأنه: لا ينشر فيديو») |
| `messages` | `lead_id`, `kind`, `body`, `status`, `template_id`, `sent_at`, `replied_at` | إضافات **[مقترح · Q10]**: `tone`, `task_id`, `generated_by` (`ai` / `fallback`) |
| `message_templates` | `name`, `activity_type_id`, `stage`, `body`, `usage_count`, `reply_count` | المتغيرات: `{contact_name}`, `{business_name}`, `{observation}`, `{sender_name}` |
| `tasks` | `lead_id`, `title`, `kind`, `due_at`, `done_at` | إضافة: `cancelled_at` لإلغاء المتابعات عند الرد **[مقترح · Q5]** |
| `stage_history` | `lead_id`, `from_stage`, `to_stage`, `changed_at` | يُكتب بـ trigger على `leads.stage`، فلا يمكن تغيير مرحلة بدون سجل |
| `quotes` | `lead_id`, `items jsonb`, `total`, `status`, `valid_until` | بعد decisions §2 يُحذف `public_token`. إضافات **[مقترح · Q7]**: `number` (`Q-YYYY-NNN` فريد لكل مالك)، `discount_pct`، `validity_days`، `notes`، `monthly_total`، `once_total`، `vat_amount`، `sent_at` |

### 1.3 القيم المغلقة (CHECK constraints)

| الحقل | القيم | المصدر |
|---|---|---|
| `leads.stage` | `not_visited, visited, contacted, replied, meeting, proposal, won, lost` | brief |
| `leads.source` | `visit, import` | brief بعد حذف `qr` (decisions §1) |
| `leads.contact_role` | `owner, manager, employee` | brief |
| `leads.priority` | `hot, warm, cold` | brief |
| `messages.kind` | `first, followup_3, followup_7, custom` | brief |
| `messages.status` | `draft, sent, replied` | brief |
| `services.billing` | `monthly, one_time` | brief |
| `leads.best_contact_time` | `morning, afternoon, evening` | تصميم NewLead1 (الصباح، بعد العصر، المساء) |
| `quotes.discount_pct` | `0, 5, 10, 15` | decisions §2 |
| `quotes.validity_days` | `7, 14, 30` | decisions §2 |
| `assessments.answers[*]` | `yes, partial, no` | brief + TriSelect |
| `leads.lost_reason` | `price, not_now, has_marketer, no_reply, not_interested, other` **[مقترح · Q6]** | التدفق 4 (السعر، لا يحتاج الآن، عنده مسوّق، لم يرد، غير مهتم، أخرى) |
| `profiles.default_tone` / `messages.tone` | `friendly, formal, short` **[مقترح · Q10]** | تصميم Message (ودّي، رسمي، مختصر) |
| `tasks.kind` | `first_message, followup_3, followup_7, schedule_meeting, meeting, quote_followup, retry, custom` **[مقترح · Q5]** | غير محدد في المستند |
| `lead_services.status` | `suggested, dropped` **[مقترح · Q12]** | غير محدد |
| `quotes.status` | `draft, sent` **[مقترح · Q7]** | غير محدد |
| `visit_media.kind` | `photo, video, audio` | brief (صور وفيديو وملاحظات صوتية) |
| `leads.won_billing` | `monthly, one_time` **[مقترح · Q3]** | التدفق 3 |

### 1.4 منطق محسوب (في `src/lib/`، بدوال نقية مختبرة)

| القاعدة | التعريف |
|---|---|
| الدرجة | مجموع: نعم = الوزن، جزئي = الوزن ÷ 2، لا = 0، بلا إجابة = 0. البنود الخاصة بالنشاط لا تدخل. تُقرّب لأقرب عدد صحيح. الأوزان الافتراضية مجموعها 100 |
| نقاط الضعف | كل بند عام إجابته لا أو جزئي، مرتبة بالوزن تنازلياً |
| الأولوية | الدرجة < 50 وصاحب قرار ← `hot`؛ 50–69، أو < 50 وليس صاحب قرار ← `warm`؛ ≥ 70 ← `cold`. الرد يجعلها `hot` (فتح التقرير أُلغي) |
| الجوال | `05XXXXXXXX` (10 أرقام) ← `9665XXXXXXXX`. يقبل المسافات والشرطات و`+966` و`966` والأرقام العربية الهندية، ويرفض غيرها برسالة «الرقم ناقص. اكتب 10 أرقام تبدأ بـ 05» |
| التكرار | نفس `phone_e164`، أو نفس اسم المحل بعد التطبيع وضمن مسافة **[مقترح · Q9: 150 متراً]** |
| رابط واتساب | `https://wa.me/<phone_e164>?text=<encodeURIComponent(body)>`، ولا يُبنى لعميل عليه `do_not_contact` |
| المتابعات | عند تأكيد «نعم، أرسلتها»: `followup_3` بعد 3 أيام و`followup_7` بعد 7 أيام من وقت التأكيد، بتوقيت الرياض، ولا تُنشأ مع `do_not_contact` |
| اقتراح الخدمات | الخدمات النشطة المرتبطة بنقاط الضعف (Q12) ضمن نشاط العميل، ثم `default_service_ids` للنشاط، بدون تكرار |
| عرض السعر | مجموع البند = الكمية × سعر الوحدة؛ شهري ومرة واحدة منفصلان؛ الخصم على الاثنين؛ الضريبة فقط إن كانت مفعّلة في الإعدادات؛ رقم `Q-YYYY-NNN` تسلسلي لكل سنة ومالك |
| الركود | أكثر من 7 أيام منذ `stage_changed_at` في مرحلة مفتوحة |

### 1.5 الهجرات والـ seed
- `supabase/migrations/0001_schema.sql`: الجداول والقيود والفهارس.
- `0002_rls.sql`: RLS والسياسات وسياسات Storage.
- `0003_triggers.sql`: `updated_at`، وسجل المراحل، وفحص تطابق المالك، وترقيم العروض.
- `0004_bootstrap.sql`: دالة `bootstrap_owner()` (security definer) تُنشئ للمستخدم الجديد ملفه الشخصي وأنشطته الأربعة (مطعم، كافيه، مركز أسنان، أخرى) ببنود التقييم الـ 13 وأوزانها والبنود الخاصة، وخدمات الكتالوج الست من brief بأسعار تجريبية، وقالباً احتياطياً لكل نوع رسالة.
- `tests-db/supabase-stub.sql` + `tests-db/rls.sql`: اختبار المخطط و RLS على Postgres محلي (`npm run test:db`).
- `tests-api/rls.test.ts`: اختبار RLS على مشروع Supabase نفسه عبر الـ API بمستخدمَين حقيقيين (`npm run test:rls`).
- بيانات تجريبية للعملاء على كل المراحل تُضاف مع 1b لحساب الاختبار فقط.

### 1.6 ما أُضيف أثناء التنفيذ (بدون تغيير في المعنى)
- `message_templates.kind` (`first, followup_3, followup_7, custom`) لاختيار القالب الاحتياطي لكل نوع رسالة.
- `messages.generated_by` يقبل أيضاً `manual` (رسالة كتبها المستخدم).
- `profiles` بلا سياسة إنشاء أو حذف: يُنشأ بالـ trigger عند إنشاء الحساب.
- مشروع Supabase: `maidani` (eu-central-1). الهجرات مطبّقة بنفس ملفات `supabase/migrations/`.
- Realtime على `leads, tasks, messages, visits, quotes` (هجرة 6) لظهور العميل فوراً في الداشبورد.
- «عملاء حارّون» = أولوية `hot` في المراحل `replied, meeting, proposal` (يطابق Main و DeskToday بعد حذف «فتح التقرير»).
- الأسبوع يبدأ الأحد بتوقيت الرياض، و«حتى الخميس» نهاية أسبوع العمل.
- المتابعات في أفضل وقت للتواصل: الصباح 10:00، بعد العصر 16:00، المساء 19:00، وبدونه 10:00.
- الخدمات المقترحة: من نقاط الضعف أولاً ثم خدمات النشاط المعتادة، وأول 3 محددة مسبقاً (كما في NewLead4).
- الأحجام بالبكسل الموجودة في ملفات التصميم نفسها (مثل 56px لزر «عميل جديد»، 15px لبعض النصوص) تُستخدم كما هي؛ الألوان كلها من tokens فقط ويفحصها `npm run lint`.
- `bundle.css` في طبقة CSS `components` حتى تستطيع أدوات Tailwind تعديله؛ شكله لم يتغيّر.
- كل بند تقييم عام يحمل «weakness» (صيغته كنقطة ضعف) في `activity_types.checklist` (هجرة 7).
- حفظ العميل الجديد عبر RPC `create_lead_from_visit` بمعرّف من الواجهة: لا يتكرر العميل عند إعادة المحاولة.
- العميل الجديد يحصل على مهمة «أرسل الرسالة الأولى» بموعد الحفظ، فيظهر في «اليوم» حتى تُرسل.
- مهمة «أرسل ريل العينة خلال 24 ساعة» مؤجلة للمرحلة 2 كما في المستند (C3)؛ لا تظهر في الخطوة 3.
- بطاقة الموقع تعرض الدقة بدل العنوان: لا مزود عناوين بلا خرائط (decisions §3).
- تأكيد الإرسال عبر RPC `confirm_message_sent` في معاملة واحدة (هجرة 8): الرسالة `sent`، إغلاق مهمة الرسالة، المرحلة `contacted` للأولى فقط، المتابعتان، `last_contact_at`. و`undo_message_sent` لزر «تراجع» يلغي المتابعات (`cancelled_at`) ولا يحذفها.
- «هل أرسلت؟» تظهر عند العودة للتطبيق (`visibilitychange` أو `blur/focus`)، أو بعد 2.5 ثانية إن لم تغادر الصفحة، وتُتذكَّر 12 ساعة إن أُغلق التطبيق في الأثناء.
- المسودة تُحفظ عند مغادرة المحرر ونسخها وفتح الواتساب، فتظهر معدّلة في «اليوم» وتعود عند فتح الشاشة مرة أخرى بدل توليد جديد.
- حدّ الأسطر في العدّاد حسب النوع: الأولى 5، المتابعة الأولى 4، الثانية 3، والرسالة الحرة بلا حد.
- مزود الذكاء الاصطناعي: محوّل Anthropic فقط، لا يعمل إلا بالأسرار `AI_PROVIDER=anthropic` و`AI_API_KEY` (النموذج الافتراضي `claude-opus-5-5`، ويمكن تغييره بـ `AI_MODEL`). بدونها القالب الاحتياطي دائماً **[Q4]**.
- إجراءات المراحل (هجرة 9) كلها RPC بمعاملة واحدة: «العميل رد» يجعل آخر رسالة مرسلة `replied` والعميل حاراً، ويلغي الرسالة الأولى والمتابعات و«أعد المحاولة» المفتوحة، وينشئ «حدد اجتماعاً» بموعد الآن إن لم يوجد اجتماع مفتوح. الاجتماع يغلق «حدد اجتماعاً» ويلغي المتابعات. الإغلاق يلغي مهام البيع المفتوحة. الخسارة تلغي كل المهام المفتوحة، و«أعد المحاولة» مهمة الساعة 10:00 صباحاً. التغيير المباشر للمراحل الأربع البسيطة يمسح سبب الخسارة وقيمة الإغلاق.
- «العميل رد» غير متاح لعميل في «تم الإغلاق» أو «خسارة»: غيّر المرحلة أولاً.
- تفعيل «عدم التواصل» يلغي مهام الرسائل المفتوحة تلقائياً (trigger).
- قائمة العملاء تُحمَّل كاملة (مستخدم واحد، مئات العملاء) ويجري البحث والفلترة والترتيب في المتصفح؛ حالة القائمة في الرابط (`?q=&stage=&sort=`) فيعمل بحث رأس الديسكتوب والرجوع. الجدول 25 صفاً في الصفحة.
- التصدير CSV بترميز UTF-8 مع BOM، والجوال بصيغة 05…، وأي خانة تبدأ بـ `= + - @` تُسبق بفاصلة عليا حتى لا تُنفَّذ كمعادلة.

---

## 2. الشاشات والمسارات

| المسار | الشاشة | التصميم | المرحلة |
|---|---|---|---|
| `/login` | الدخول | Login | 0 |
| `/` | اليوم | Main / DeskToday | 1b |
| `/leads/new/:step(1-4)` | معالج عميل جديد | NewLead1–4 | 1c |
| `/leads/:id/message` | الرسالة + «هل أرسلت؟» | Message / SentConfirm | 1d |
| `/leads` | قائمة العملاء | Leads / DeskLeads | 1e |
| `/leads/:id` | صفحة العميل | Lead / DeskLead | 1e |
| `/pipeline` | Pipeline | Pipeline / DeskPipeline | 1f |
| `/tasks` | المهام | Tasks | 1f |
| `/leads/:id/quotes/new` و `/quotes/:id` | محرر عرض السعر | Quote / DeskQuote | 1g |
| `/quotes/:id/preview` | المعاينة والمشاركة | QuotePreview | 1g |
| `/quotes` | قائمة العروض (رابط الشريط الجانبي) | غير مصممة **[Q15]** | 1g |
| `/more` | المزيد / الإعدادات | Settings | 1h |
| `/settings/profile`, `/settings/services`, `/settings/activities`, `/settings/goal` | الإعدادات الفرعية | غير مصممة، بنفس الأسلوب | 1h |
| `/dev/ui` | معرض المكونات بالوضعين | — | 1a |

أُلغيت (decisions): تقرير العميل، التسجيل عبر QR، عرض السعر العام، الخريطة ووضع الجولة وتبديل قائمة/خريطة. خارج المرحلة 1: لوحة الأرقام، وضع العرض، الإدخال الصوتي (زر معطّل «قريباً»).

### الهيكل
- `AppShell`: أقل من 1024px شريط سفلي (`BottomNav`: اليوم، العملاء، عميل جديد بارز، Pipeline، المزيد)؛ من 1024px شريط جانبي يميناً بعرض `--sidebar-w` (`DeskSidebar`: اليوم، العملاء، Pipeline، المهام، لوحة الأرقام، عروض الأسعار، الإعدادات) مع زر «عميل جديد» أعلى الصفحة.
- `OfflineBanner` ثابت أعلى الشاشة عند `navigator.onLine === false`.
- `ToastHost` فوق الشريط السفلي على الجوال، وأعلى يسار الصفحة على الديسكتوب.
- `RequireAuth` يحمي كل المسارات عدا `/login`.

### المكونات
- **نظام التصميم** (`src/components/ui/`، مطابقة لـ `index.d.ts` و`bundle.css`): `Button`, `TextField`, `StageBadge` + `STAGES`, `PriorityBadge`, `LeadCard`, `ScoreBar`, `TriSelect`, `BottomSheet`, `Toast`, `EmptyState`, و`Icon` الداخلي.
- **مكونات التطبيق** (`src/components/app/`): `AppShell`, `BottomNav`, `DeskSidebar`, `OfflineBanner`, `ToastHost`, `Skeleton`, `ErrorRetry`, `WizardHeader` (تقدم 4 خطوات), `StickyFooter`, `SegmentedControl`, `ChipGroup`, `ActivityGrid`, `ServiceCheckCard`, `StageStrip`, `KanbanColumn`, `TaskRow`, `PostponeSheet`, `ChangeStageSheet`, `LostSheet`, `RepliedSheet`, `MeetingSheet`, `WonSheet`, `SentConfirmSheet` (في `src/features/message/`)، `QuoteSheetPreview`, `Timeline`.

### Edge Functions
| الدالة | المدخل | العمل | الأسرار |
|---|---|---|---|
| `generate-message` | `{ lead_id, kind, tone, template_id? }` مع JWT المستخدم | تقرأ بصلاحية المستخدم (RLS) الملف الشخصي والعميل وآخر زيارة وأهم نقطتي ضعف وأول خدمتين، وتبني الطلب بقواعد الرسالة الأولى (3–5 أسطر، سلام واسم، ملاحظة محددة، فائدة، سؤال واحد، بدون أسعار ولا وعود، لهجة سعودية بيضاء)، وتستدعي المزوّد، وتتحقق من عدد الأسطر، وتحفظ المسودة في `messages`. عند أي فشل تعيد القالب الاحتياطي مع `generated_by='fallback'` | `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL` **[Q4]** |

لا توجد دوال أخرى في المرحلة 1؛ PDF يُولَّد في المتصفح (decisions §2)، والتصدير CSV في المتصفح.

---

## 3. مصفوفة التتبع: المتطلب ← الملف ← التحقق

اختصارات التحقق: **U** = Vitest، **E** = Playwright بعرض 390، **R** = اختبار RLS على Postgres، **V** = مقارنة بصرية مع ملف التصميم، **S** = فحص الحالات الخمس.

| # | المتطلب | الملف المنفّذ | التحقق | الحالة |
|---|---|---|---|---|
| 0.1 | Vite + React + TS strict + Tailwind | `package.json`, `tsconfig.*.json`, `vite.config.ts`, `src/styles/index.css` (Tailwind 4 `@theme inline`) | `npm run typecheck && lint && build` بلا تحذيرات | ☑ |
| 0.2 | RTL و`lang="ar"`، خصائص منطقية فقط | `index.html`، `scripts/check-design-rules.mjs` (جزء من `npm run lint`) | قاعدة lint + E (`login.spec`: dir/lang) | ☑ |
| 0.3 | خط IBM Plex Sans Arabic 400/700 | `index.html` | E: الخط المحسوب على body | ☑ |
| 0.4 | tokens.css مصدر الألوان، وTailwind مربوط بالمتغيرات، ولا hex في المكونات | `src/styles/tokens.css`, `src/styles/components.css` (= bundle.css), `src/styles/index.css` | `check-design-rules.mjs` يمنع hex/rgb + E: لون الزر و`--radius-md` من tokens | ☑ |
| 0.5 | أرقام لاتينية و`tabular-nums` | `components.css` (body tabular-nums)؛ `src/lib/format.ts` في 1b | U | ◐ |
| 0.6 | عميل Supabase من متغيرات البيئة، و`.env.example` فارغ | `src/lib/supabase.ts`, `src/lib/env.ts`, `src/lib/database.types.ts`, `.env.example` | build + `.env.local` في `.gitignore` (تحقق `git check-ignore`) | ☑ |
| 0.7 | كل الجداول والقيم المغلقة بعد decisions | `supabase/migrations/20261007000001_schema.sql` | R: `qr`، `05…`، خصم 20%، إجابة خارج القائمة كلها ترفض | ☑ |
| 0.8 | RLS على كل جدول `owner_id = auth.uid()` | `…02_rls.sql`, `…03_triggers.sql` (تطابق المالك), `…05_indexes.sql` | R محلياً ☑ (`npm run test:db`، واختبار طفرة يثبت أنه يكشف التسريب). على Supabase: 51 سياسة ومستشار الأمان بلا ملاحظات RLS ☑؛ اختبار API بمستخدمَين (`npm run test:rls`) ☐ بانتظار فتح الشبكة | ◐ |
| 0.9 | seed بأنشطة وخدمات وبنود تقييم | `…04_bootstrap.sql` (trigger على auth.users) | R: 4 أنشطة، 13 بنداً مجموعها 100، 6 خدمات، 3 قوالب | ☑ |
| 0.10 | الدخول ببريد وكلمة مرور بدون تسجيل جديد، وحماية الصفحات | `src/routes/Login.tsx`, `src/auth/*` | E: التحويل إلى /login، النصوص والترتيب، 48px، خطأ التحقق، بدون اتصال، رسالة تعذّر الاتصال ☑؛ الدخول الفعلي ☐ بانتظار فتح الشبكة | ◐ |
| 0.11 | PWA قابل للتثبيت | `vite-plugin-pwa`, `public/icons/*` (`scripts/make-icons.mjs`) | E: manifest (ar/rtl/standalone/192/512/maskable) و`sw.js` | ☑ |
| 1a | 10 مكونات بنفس props و`bundle.css` | `src/components/ui/*`، `src/components/app/toast/*`، `src/styles/app.css`، `/dev/ui` | U: تطابق مع `bundle.js` (59 حالة تقارن HTML بعد توحيد المعرّفات) + سلوك (TriSelect، BottomSheet وتركيزها، TextField، Toast 4 ث والأخطاء لا تُحذف) ☑ · E: الأسهم، Escape وعودة التركيز، الألوان في الوضعين، 48px مع اختبار ذاتي يثبت أن الفحص يكتشف الصغير ☑ · مراجعة متعددة الوكلاء (28 وكيلاً): 15 ملاحظة مؤكدة أُصلحت كلها | ☑ |
| 1b.1 | شريط سفلي 5 عناصر / شريط جانبي يميناً | `BottomNav.tsx`, `DeskSidebar.tsx`, `AppShell.tsx`, `DeskHeader.tsx` | E: العناصر الخمسة بالترتيب والعنصر الحالي؛ الشريط الجانبي يمين الشاشة بالعدّادات واسم المستخدم والعلامة ☑ | ☑ |
| 1b.2 | اليوم: 3 عدادات، هدف الأسبوع، متأخرة warning، متابعات اليوم بواتساب، عملاء حارّون | `src/routes/Today.tsx`, `src/data/today.ts`, `src/lib/dates.ts`, `src/lib/postpone.ts`, `src/data/realtime.ts` | U: منطق التقسيم والتواريخ والجمع العربي ☑ · E (Supabase مُحاكى): ترتيب الأقسام والنصوص، واتساب/اتصال، التأجيل، الجدول لا يفيض عند 1440، 48px، لا تمرير أفقي ☑ · الحالات الخمس ☑ · بيانات حقيقية ☐ بانتظار الشبكة | ◐ |
| 1b.3 | الحالة الفارغة «انزل للميدان» + «عميل جديد» | `Today.tsx` | E ☑ | ☑ |
| 1c.1 | معالج 4 خطوات بشريط تقدم، و«التالي» ثابت أسفل الشاشة | `src/features/new-lead/*` | E: الخطوات الأربع بالنصوص والترتيب، 48px في كل خطوة ☑ · V: لقطات الخطوات مطابقة للتصميم ☑ | ☑ |
| 1c.2 | حفظ مسودة تلقائي عند كل خطوة | `src/features/new-lead/draft.ts` | U ☑ + E: إعادة التحميل تستعيد الخطوة والإجابات، «ابدأ من جديد» يمسحها ☑ | ☑ |
| 1c.3 | تحويل 05 ← 9665 ورسالة الرقم غير الصحيح | `src/lib/phone.ts` | U ☑ (الأرقام العربية، +966، 00966، المسافات) · E: رسالة الرقم الناقص، والإرسال بـ 9665 ☑ | ☑ |
| 1c.4 | كشف التكرار (الرقم، أو الاسم في نفس الموقع) | `src/lib/duplicates.ts`, `src/data/catalog.ts` (`useLeadIndex`) | U ☑ (150 م، توحيد الإملاء العربي) · E: الرقم المكرر يمنع المتابعة مع رابط المحل و«محل آخر لنفس المالك، تابع» ☑ | ☑ |
| 1c.5 | الدرجة مباشرة: نعم = الوزن، جزئي = النصف، لا = 0 | `src/lib/scoring.ts` | U ☑ (مثال NewLead2 = 48) · E: الشريط يتحدث مع كل إجابة ☑ | ☑ |
| 1c.6 | البنود الخاصة ثنائية ولا تدخل في الدرجة | `scoring.ts`, `TriSelect binary` | U ☑ · E ☑ | ☑ |
| 1c.7 | نقاط الضعف واقتراح الخدمات مع السبب | `src/lib/scoring.ts`, `src/lib/suggest.ts`, `features/new-lead/payload.ts`، عبارة الضعف لكل بند (هجرة 7) | U ☑ · E: أول 3 محددة مع السبب، والقيمة المتوقعة تتبعها ☑ · R ☑ | ☑ |
| 1c.8 | الأولوية حسب القواعد | `src/lib/priority.ts`, `payload.ts` | U ☑ · E (تُرسل hot للمالك بدرجة 13) ☑ | ☑ |
| 1c.9 | التقاط الموقع (lat, lng) | `Step1Details.tsx` | E (موقع مزيّف): «تم التقاط الموقع · الدقة ± 12 م» ويُرسل lat/lng ☑ | ☑ |
| 1c.10 | رفع صور وفيديو إلى Storage خاص | `src/data/create-lead.ts` | R (سياسات Storage) ☑ · E: الرفع إلى `{owner}/{visit}/…` وصف `visit_media` ☑ · رفع حقيقي ☐ بانتظار الشبكة | ◐ |
| 1c.11 | الإدخال الصوتي معطّل «قريباً» | `Step3Notes.tsx` | E (الزر معطّل) ☑ | ☑ |
| 1c.12 | الحفظ: العميل + الزيارة + التقييم + الخدمات، والمرحلة `visited` | RPC `create_lead_from_visit` (هجرة 7)، `src/data/create-lead.ts` | R: معاملة واحدة، مهمة «أرسل الرسالة الأولى»، لا تكرار عند الإعادة، رفض الرقم الخاطئ، عزل المستخدم الآخر ☑ · E: محتوى الطلب، الحفظ المؤجل بدون اتصال، الخطأ وإعادة المحاولة بنفس المعرّف ☑ · حفظ حقيقي ☐ بانتظار الشبكة | ◐ |
| 1d.1 | `generate-message` بمدخلات وقواعد المستند، والمفتاح من الأسرار | `supabase/functions/generate-message/index.ts`, `supabase/functions/_shared/message.ts` | U ☑ (`message.test.ts`: الطلب، التحقق من الأسطر والأسعار والإيموجي والروابط والسؤال، القوالب) + `deno check`/`deno lint` ☑ + منشورة (v1) | ◐ (توليد حقيقي ينتظر الأسرار [Q4]) |
| 1d.2 | قالب احتياطي عند الفشل مع رسالة «تعذّر توليد الرسالة. استخدمنا القالب الجاهز بدلاً منها.» | الدالة + `src/data/messages.ts` (`localDrafts` عند تعذّر الوصول للدالة) | U ☑ + E ☑ (الدالة معطّلة، وبدون اتصال) | ☑ |
| 1d.3 | النبرة، عدّاد الأسطر، أعد الصياغة، نسخ | `src/features/message/Message.tsx`, `text.ts` | V(Message) ☑ + S ☑ + E ☑ + U ☑ | ☑ |
| 1d.4 | تنبيه عند غياب موافقة الواتساب، وإخفاء الزر مع `do_not_contact` | `Message.tsx` (C6: «سجّلت موافقته») | E ☑ (بلا موافقة، طلب عدم التواصل، بلا رقم) | ☑ |
| 1d.5 | رابط wa.me فقط | `src/lib/whatsapp.ts`, `Message.tsx` | U ☑ + E ☑ (`href` بالنص المعدّل) | ☑ |
| 1d.6 | «هل أرسلت؟» بعد العودة؛ `sent_at` بعد التأكيد فقط | `src/features/message/SentConfirmSheet.tsx`, `useReturnPrompt.ts` | U ☑ (العودة، الاحتياط، التذكّر) + E ☑ («ليس بعد» لا يسجّل شيئاً، إعادة الفتح) + R ☑ | ☑ |
| 1d.7 | المرحلة `contacted` ومتابعتان بعد 3 و7 أيام | RPC `confirm_message_sent` / `undo_message_sent`, `src/lib/followups.ts` | U ☑ + R ☑ (`tests-db/rpc_message_sent.sql`) + E ☑ (مواعيد المتابعتين، «تراجع») | ☑ |
| 1e.1 | قائمة: بحث بالاسم أو الرقم، شرائح المراحل بالعدد، فلاتر، ترتيب | `src/routes/Leads.tsx`, `src/features/leads/*`, `src/lib/leads-list.ts` | U ☑ + V(Leads) ☑ + S ☑ + E ☑ (`e2e/leads.spec.ts`) | ☑ |
| 1e.2 | جدول ديسكتوب بأعمدة قابلة للترتيب وتحديد متعدد (تغيير مرحلة، تصدير) | `DeskLeads.tsx`, `DeskLeadsTable.tsx`, `useSelectionActions.tsx`, `src/lib/csv.ts` | V(DeskLeads) ☑ + U ☑ (CSV) + E ☑ (تغيير مرحلة، خسارة، تصدير) | ☑ |
| 1e.3 | سحب البطاقة على الجوال (واتساب، زيارة جديدة) | `src/features/leads/SwipeActions.tsx`, `swipe.ts` | U ☑ + E ☑ (لمس) | ☑ |
| 1e.4 | صفحة العميل: رأس، الخطوة القادمة، تبويبات، عمودان على الديسكتوب | `src/routes/Lead.tsx`, `src/features/lead/*` | V(Lead, DeskLead) ☑ + S ☑ + E ☑ (`e2e/lead.spec.ts`) | ☑ |
| 1e.5 | الخط الزمني من الزيارات والرسائل و`stage_history` | `src/lib/timeline.ts` | U ☑ (الدمج والترتيب وعدم التكرار) | ☑ |
| 1e.6 | العميل رد: يلغي المتابعات، `hot`، ويقترح «حدد اجتماعاً» | RPC `mark_replied` (هجرة 9), `RepliedSheet` | R ☑ + E ☑ | ☑ |
| 1e.7 | اجتماع بتاريخ ← مهمة تذكير | RPC `set_meeting`, `MeetingSheet` | R ☑ + E ☑ | ☑ |
| 1e.8 | الإغلاق بقيمة ونوع | RPC `mark_won`, `WonSheet` | R ☑ + E ☑ | ☑ |
| 1e.9 | خسارة بسبب وتاريخ «أعد المحاولة» ← مهمة مؤجلة | `LostSheet`, RPC `mark_lost` | R ☑ + E ☑ | ☑ |
| 1e.10 | تعديل بيانات العميل (منها صاحب القرار وطلب عدم التواصل) | `src/features/lead-edit/*`, `src/data/update-lead.ts`, trigger `leads_dnc_cancel_tasks` | U ☑ + E ☑ (`e2e/lead-edit.spec.ts`) + R ☑ | ☑ |
| 1e.11 | زيارة جديدة لعميل مسجّل: تقييم وملاحظات وخدمات | `src/features/new-visit/*`, RPC `add_visit` (هجرة 10) | U ☑ + R ☑ (`tests-db/rpc_add_visit.sql`) + E ☑ (`e2e/new-visit.spec.ts`) | ☑ |
| 1f.1 | كانبان بسحب وإفلات، عدد ومجموع لكل عمود، الخسارة مطوية | `src/routes/Pipeline.tsx` | V(DeskPipeline) | ☐ |
| 1f.2 | الجوال: شريط مراحل وقائمة المرحلة وتغيير المرحلة من نافذة سفلية | `Pipeline.tsx` | V(Pipeline) + E | ☐ |
| 1f.3 | تمييز الراكد أكثر من 7 أيام | `src/lib/stale.ts` | U | ☐ |
| 1f.4 | كل تغيير مرحلة يُسجَّل في `stage_history` | trigger | R | ☐ |
| 1f.5 | المهام: متأخرة / اليوم / قادمة، إنجاز، زر الإجراء المناسب | `src/routes/Tasks.tsx` | V(Tasks) + S | ☐ |
| 1f.6 | التأجيل: غداً، بعد 3 أيام، تاريخ | `PostponeSheet`, `src/lib/postpone.ts` | U ☑ + E (من «اليوم» على الديسكتوب) ☑ | ◐ |
| 1f.7 | بعد متابعة اليوم السابع بلا رد: اقتراح تأجيل 30 يوماً أو خسارة | `src/lib/followups.ts` | U ☑ | ◐ |
| 1g.1 | الخدمات من الكتالوج، كمية، سعر، خصم 0/5/10/15، صلاحية 7/14/30، ملاحظات | `src/routes/QuoteEditor.tsx` | V(Quote, DeskQuote) | ☐ |
| 1g.2 | مجموع شهري ومرة واحدة مباشرة، والضريبة إعداد غير مفعّل افتراضياً | `src/lib/quote.ts` | U ☑ (مثال Quote.dc.html: 2,070 / 1,800 / 430) | ◐ |
| 1g.3 | رقم `Q-YYYY-NNN` | trigger + `quote.ts` | U + R | ☐ |
| 1g.4 | PDF عربي RTL في المتصفح | `src/lib/pdf.ts` **[Q13]** | E (حجم الملف > 0، صفحة واحدة) + V(QuotePreview) | ☐ |
| 1g.5 | مشاركة عبر `navigator.share({ files })` وتنزيل بديل | `QuotePreview.tsx` | E (share مزيّف) | ☐ |
| 1g.6 | «هل أرسلت العرض؟» ← `proposal` + «تابع رد العرض» بعد 3 أيام | RPC `confirm_quote_sent` | U + R + E | ☐ |
| 1h.1 | الملف الشخصي (الاسم، العلامة، التوقيع، النبرة) | `settings/Profile.tsx` | S + E | ☐ |
| 1h.2 | الخدمات: إضافة وتعديل وإيقاف وربط بالأنشطة | `settings/Services.tsx` | S | ☐ |
| 1h.3 | الأنشطة: إضافة نشاط وتعديل بنوده وأوزانها | `settings/Activities.tsx` | U (مجموع الأوزان) + S | ☐ |
| 1h.4 | الهدف الأسبوعي، المظهر (فاتح/داكن/تلقائي) | `settings/Goal.tsx`, `src/lib/theme.ts` | U + V | ☐ |
| 1h.5 | تصدير CSV (UTF-8 BOM ليفتح العربي في Excel) | `src/lib/csv.ts` | U | ☐ |
| X.1 | التدفق الكامل 390px: الدخول ← عميل جديد ← التقييم ← الرسالة ← تأكيد ← القائمة بمرحلة contacted | `e2e/main-flow.spec.ts` (يتحقق من المرحلة والمهام عبر API حتى تُبنى القائمة في 1e) | E على Supabase الحقيقي **[Q14]**: مكتوب، يفشل هنا عند الدخول لأن الشبكة تمنع `*.supabase.co` | ◐ |
| X.2 | الحالات الخمس لكل شاشة | `Skeleton`, `EmptyState`, `ErrorRetry`, `OfflineBanner`, `Toast` | `e2e/states.spec.ts` (اعتراض الشبكة) | ☐ |
| X.3 | مساحة لمس 48px | كل الأزرار، `app.css` (توسيع منطقة اللمس دون تغيير الشكل) | `e2e/helpers.ts` `expectTouchTargets` على كل شاشة + اختبار ذاتي | ◐ |
| X.4 | معيار المرحلة 1: عميل ورسالته في أقل من دقيقتين، وظهوره فوراً في الداشبورد | E + Supabase Realtime على `leads` | قياس زمن التدفق في E | ☐ |
| X.5 | متناسق مع الجوال والآيباد والكمبيوتر: الشريط الجانبي على الديسكتوب في كل الصفحات حتى «عميل جديد» | `src/components/app/FlowFrame.tsx`, `AppShell.tsx` | E ☑ (`e2e/responsive.spec.ts`: 390، 820، 1024، 1440) | ☑ |

---

## 4. المكتبات المقترحة

| المكتبة | السبب |
|---|---|
| `react`, `react-dom`, `react-router-dom` | الواجهة والتوجيه |
| `@supabase/supabase-js` | قاعدة البيانات والدخول والملفات والدوال |
| `@tanstack/react-query` | التحميل والخطأ وإعادة المحاولة والتخزين المؤقت للحالات الخمس |
| `tailwindcss`, `postcss`, `autoprefixer` | التنسيق المطلوب في الستاك |
| `vite-plugin-pwa` | manifest و service worker |
| `lucide-react` | أيقونات الشاشات والأنشطة (مقترحة في design-system.md)، وتُستورد أيقونة بأيقونة |
| مكتبة PDF | **[Q13]** |
| تطوير: `vitest`, `@testing-library/react`, `jsdom`, `@playwright/test`, `eslint` + `typescript-eslint` + `eslint-plugin-react-hooks`, `pg` (لاختبار RLS) | بوابة الجودة |

السحب والإفلات بـ HTML5 Drag and Drop الأصلي كما في التصميم، بدون مكتبة.

---

## 5. ملاحظة على المستودع
المستودع فيه مجلد `osoul-site/` (إضافة ووردبريس لموقع آخر). سأبقيه كما هو وأستثنيه من lint و TypeScript، وأبني «ميداني» في جذر المستودع **[Q16]**.
