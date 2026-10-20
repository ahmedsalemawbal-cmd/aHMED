# Prompt for Claude Code — Shatara Recruitment website

You are building the production website for "شركة شطارة للاستقدام" (Shatara Recruitment), a licensed Saudi recruitment company. The attached folder is the approved design (HTML prototypes). Convert it into a real, clean, deployable website, push it to a NEW GitHub repository, deploy it, and give me the live preview link.

## Source files in this folder
- `Home.dc.html` — home page (main reference)
- `CVs.dc.html` — CV listing with filters + CV detail modal
- `Request.dc.html` — new recruitment request form
- `About.dc.html` — about us
- `Header.dc.html`, `Footer.dc.html` — shared header / footer / floating WhatsApp / mobile bottom bar
- `assets/logo-gold.png`, `assets/logo-white.png` — logo
- `assets/hero-1.jpg`, `assets/hero-2.jpg`, `assets/images/*.webp` — all site images (hero-3, about-team, job-1…job-9)
- `uploads/Shatara-Website-Content.docx` — the approved Arabic copy (source of truth for text)

The .dc.html files use inline styles + a small template syntax (`{{ }}`, `<sc-for>`, `<sc-if>`) and a logic class at the bottom holding the data arrays (slides, services, jobs, countries, steps, FAQs, CVs). Read them to extract exact copy, colors, spacing, layout, and data. Do NOT ship the prototype runtime (support.js, image-slot.js, .dc.html) — rebuild properly.

## Tech stack
- Astro (latest) + Tailwind CSS, static output, TypeScript.
- Small interactive parts (hero slider, FAQ accordion, CV filters + modal, forms, mobile menu, services dropdown) as lightweight vanilla JS or Astro islands — no heavy frameworks.
- Pages: `/` (home), `/about`, `/cvs`, `/request`. Keep data (CVs, jobs, countries, FAQs) in `src/data/*.ts`.

## Design rules (must match the prototype exactly)
- Arabic, `dir="rtl"`, `lang="ar"`. Font: **Cairo** (Google Fonts, weights 400–800) everywhere.
- Colors: primary petrol `#0B4F6C`, dark `#083B51`, gold (from logo) `#BCA24E`, gold text `#8C7530`, light gold `#D9C68A`, background `#F6F9FA`, ink `#0E2A36`, muted `#516874`, borders `#E3EBEE`, WhatsApp `#1FA855`.
- Icons: Phosphor Icons (duotone for feature icons, regular for UI).
- Signature details to keep: arch-shaped image frames with offset gold outline, gold diamond ornaments above section titles, inner gold hairline frame on dark panels, pill buttons with subtle gold gradient, "أكثر من 10 سنوات في التنمية" seal on the hero, sector cards overlapping the hero bottom, large circular flags with gold ring, outlined gold 01–06 numbers.
- Fully responsive: desktop 1440px and mobile 390px both must look like the prototype. Step timeline horizontal on desktop, vertical on mobile.
- Header: sticky; top bar (address, email, customer-service phone, social icons) on desktop only; nav: الرئيسية • من نحن • خدماتنا (dropdown) • الدول المتاحة • الأسئلة الشائعة • اتصل بنا; language toggle عربي / EN; prominent gold button "طلب استقدام". No login button. No "السير الذاتية" item in header or footer nav.
- Floating WhatsApp button on desktop; fixed bottom bar on mobile with: اتصل • واتساب • احجز الآن.
- NO animated counters or statistics anywhere.
- Keep any text inside square brackets `[ ]` as visible placeholders (license no., CR no., address, working hours, warranty period).

## Contact data
- Customer service / WhatsApp: 0533420302 (wa.me/966533420302)
- Management: 0505627753, 0533213031
- Email: shatara.recruitment@gmail.com

## Forms
- Contact form and Request form: client-side validation, success message exactly as in the prototype. Wire submission to Formspree (leave `FORMSPREE_ID` as an env/config placeholder) and fall back to opening WhatsApp with the form content prefilled if not configured.
- Request page reads `?cv=SH-1040` and shows the selected CV.

## SEO & quality
- Title: "شركة شطارة للاستقدام | استقدام عمالة منزلية ومهنية في [المدينة]" and the meta description from the docx.
- Open Graph tags, favicon from the logo, sitemap, robots.txt.
- Optimize images (Astro `<Image>`, WebP/AVIF, lazy loading, correct aspect ratios, `object-fit: cover`).
- Lighthouse ≥ 90 on mobile for performance, accessibility, SEO. Semantic HTML, alt text in Arabic, keyboard-accessible accordion/slider/modal.

## Deliverables — do all of this
1. Create a new public GitHub repository named `shatara-website` and push the project with a clear README (Arabic + English: how to run, edit content, and deploy).
2. Deploy with GitHub Pages using a GitHub Actions workflow (set Astro `site`/`base` correctly), OR Vercel if available — whichever gives a working public URL fastest.
3. Verify the live site loads on desktop and mobile, all pages and links work, images show, no console errors.
4. Reply with: the GitHub repo URL, the live preview URL, and a short list of remaining placeholders the client must fill.
