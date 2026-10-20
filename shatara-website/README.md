# موقع شركة شطارة للاستقدام — Shatara Recruitment Website

موقع ثنائي اللغة (عربي / English) مبني من التصميم المعتمد كما هو، صفحات ثابتة سريعة بدون أي مكتبات خارجية.
A bilingual (Arabic / English) static website built one-to-one from the approved design, with no runtime dependencies.

---

## العربية

### محتويات المجلد
| المسار | الوصف |
|---|---|
| `design/` | ملفات التصميم الأصلية كما وصلت بالضبط (النماذج، الصور، ملف المحتوى). مرجع فقط — لا تُرفع على الاستضافة. |
| `src/data/content.ar.mjs` | **كل النصوص العربية** للموقع. |
| `src/data/content.en.mjs` | **كل النصوص الإنجليزية** (بنفس ترتيب الملف العربي). |
| `src/data/site.mjs` | أرقام التواصل، البريد، روابط التواصل الاجتماعي، رابط الموقع، إعداد Formspree. |
| `src/data/cvs.mjs` | السير الذاتية (بيانات تجريبية من التصميم — تُستبدل بالحقيقية). |
| `src/pages/`، `src/layout.mjs` | قوالب الصفحات والهيدر والفوتر. |
| `src/styles/site.css`، `src/scripts/site.js` | التنسيق والتفاعل (السلايدر، الأسئلة، الفلاتر، النماذج). |
| `public/assets/` | الصور والأعلام والأيقونات بعد ضغطها. |
| `dist/` | **الموقع الجاهز للرفع** على أي استضافة. |

### الصفحات
- العربية: `index.html` (الرئيسية)، `about.html` (من نحن)، `cvs.html` (السير الذاتية)، `request.html` (طلب استقدام)
- الإنجليزية: نفس الصفحات داخل `en/` — زر «عربي / EN» في الهيدر ينقل بين اللغتين لنفس الصفحة.
- صفحة الطلب تقرأ رقم السيرة من الرابط، مثل: `request.html?cv=SH-1040`.

### التشغيل والتعديل
يحتاج Node.js الإصدار 18 أو أحدث فقط (لا يوجد `npm install`).
```bash
npm run build     # يبني الموقع داخل dist/
npm run preview   # يعرضه على http://localhost:4321
```
لتعديل أي نص: عدّل `src/data/content.ar.mjs` (والمقابل في `content.en.mjs`) ثم شغّل `npm run build`.

### النماذج (تواصل معنا + طلب استقدام)
- إن وُضع معرّف Formspree في `FORMSPREE_ID` (في `src/data/site.mjs` أو كمتغير بيئة عند البناء) تُرسل النماذج إلى بريدكم.
- إن لم يُوضع، يفتح الموقع واتساب على الرقم 0533420302 مع تعبئة بيانات النموذج تلقائياً.
- في الحالتين تظهر رسالة النجاح كما في التصميم.

### النشر
ارفع محتويات مجلد `dist/` كما هي على أي استضافة (GitHub Pages، Netlify، Vercel، أو استضافة عادية).
قبل الإطلاق ضع رابط الموقع الحقيقي: `SITE_URL=https://example.com npm run build` (يُستخدم في خريطة الموقع وروابط المشاركة).

### بيانات يجب على العميل تعبئتها (موجودة بين [ ] في الموقع)
- رقم ترخيص وزارة الموارد البشرية، ورقم السجل التجاري.
- العنوان والمدينة + رابط خرائط Google، وساعات العمل، والمدينة في عنوان الصفحة.
- مدة الضمان بعد الوصول.
- روابط حسابات التواصل (إنستغرام، X، سناب شات، تيك توك).
- ملف الملف التعريفي PDF، وصفحات سياسة الخصوصية والشروط والأحكام، وصفحة «كل الأسئلة»، وصفحات «اقرأ المزيد» للخدمات.
- صورتا صفحة «من نحن» (المكتب، فريق العمل)، وصور المرشحين وفيديوهاتهم وملفات PDF للسير الذاتية.
- السير الذاتية الحقيقية بدلاً من البيانات التجريبية، ومعرّف Formspree.
- النص الإنجليزي ترجمة جاهزة للمراجعة قبل الاعتماد.

---

## English

### What's here
| Path | Purpose |
|---|---|
| `design/` | The original design files exactly as delivered (prototypes, images, content brief). Reference only — not deployed. |
| `src/data/content.ar.mjs` | **All Arabic copy.** |
| `src/data/content.en.mjs` | **All English copy** (same keys as the Arabic file). |
| `src/data/site.mjs` | Phone numbers, email, social links, site URL and the Formspree setting. |
| `src/data/cvs.mjs` | CV listings (sample data from the design — replace with real candidates). |
| `src/pages/`, `src/layout.mjs` | Page, header and footer templates. |
| `src/styles/site.css`, `src/scripts/site.js` | Styles and behaviour (hero slider, FAQ, filters, CV dialog, forms). |
| `public/assets/` | Optimized images, flags and icons. |
| `dist/` | **The finished site, ready to upload** to any host. |

### Pages
- Arabic (default, RTL): `index.html`, `about.html`, `cvs.html`, `request.html`
- English (LTR): the same pages under `en/`. The "عربي / EN" switch in the header jumps to the same page in the other language.
- The request page reads the chosen CV from the URL, e.g. `request.html?cv=SH-1040`.

### Run and edit
Requires Node.js 18+ only — there are no packages to install.
```bash
npm run build     # builds the site into dist/
npm run preview   # serves dist/ at http://localhost:4321
```
To change text, edit `src/data/content.ar.mjs` / `content.en.mjs`, then run `npm run build`.

### Forms
- Set `FORMSPREE_ID` (in `src/data/site.mjs`, or as an environment variable at build time) to deliver the contact and request forms through Formspree.
- Without it, submitting opens WhatsApp (0533420302) with the form content pre-filled.
- Either way the success message from the design is shown.

### Deploy
Upload the contents of `dist/` to any static host (GitHub Pages, Netlify, Vercel or regular hosting).
Before launch, build with the real domain so the sitemap, canonical and share links are correct:
`SITE_URL=https://example.com npm run build`.

### Notes on the build
- Text, colours, sizes and layout are taken directly from the design files; Arabic pages match the prototypes (same layout and page heights) at 1440px and 390px.
- Fixes where the prototype itself broke: the About page photo mosaic overflowed the page (the logo tile now stays inside its 6:5 box); the header was wider than a phone screen (below 480px the header request button is left to the fixed bottom bar, and below 360px only the logo shows); the dropdown arrows on select fields now render as designed.
- Icons are inline Phosphor SVGs, flags are local SVGs and images are WebP, so the site makes no requests to third-party CDNs except Google Fonts (Cairo).
- SEO: per-page titles and descriptions, canonical + hreflang (ar/en), Open Graph image, favicon, `sitemap.xml`, `robots.txt` and Organization structured data.

### Placeholders the client still needs to provide
Everything shown in square brackets on the site, plus: license and CR numbers, address + Google Maps link, working hours and the city in the page title, warranty period, social media links, the company-profile PDF, privacy/terms pages, the "All questions" page and service detail pages, the two About-page photos, candidate photos/videos/PDFs, real CVs, a Formspree ID, and a review of the English translation.
