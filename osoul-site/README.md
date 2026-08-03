# Osoul Albinaa Site — WordPress plugin (v2)

A maintainable, performance- and security-oriented rebuild of the original
single ~2,500-line WPCode / `functions.php` snippet that powers the
[osoulalbinaa.com](https://osoulalbinaa.com) front-end (bilingual AR/EN header,
footer, home, products, about, services, projects, blog, contact, privacy, a
quote-list, and the product catalog).

It is a drop-in **replacement** for that snippet — same look, same pages, same
URLs — but split into modules, with the always-loaded CSS/JS extracted into
cacheable files, the request globals hardened, and a **real server-side
quote/contact backend** added.

---

## Installation

1. Copy the `osoul-site/` folder into `wp-content/plugins/`.
2. In **WP Admin → Plugins**, activate **“Osoul Albinaa Site”**.
   - On activation it creates the required pages and flushes rewrite rules.
3. **Remove the old WPCode snippet / `functions.php` block.**
   Running both at once would double every output. The plugin reuses the same
   function names and the `OSOUL_*` guards, so if both load, the plugin wins and
   the snippet's duplicate definitions are skipped — but you should still delete
   the old snippet to avoid confusion.
4. Visit **WP Admin → Quote Leads** to see incoming form submissions.

> No build step. Plain PHP/CSS/JS. Requires WordPress 5.8+ and PHP 7.4+.

---

## What changed vs. the original snippet

### 1. Structure — one file → modules
```
osoul-site/
├── osoul-site.php            # plugin bootstrap / module loader
├── inc/
│   ├── helpers.php           # secure URL builder, slug parsing, bilingual helper
│   ├── security.php          # hardening, attachment/404 fixes, headers
│   ├── catalog.php           # product catalog DATA (no output)
│   ├── pages.php             # create/clean up WP pages, hide demo post
│   ├── performance.php       # enqueue assets, dequeue bloat, resource hints
│   ├── i18n.php              # tiny pre-paint language bootstrap
│   ├── seo.php               # meta, canonical, hreflang, schema.org
│   ├── forms.php             # NEW server-side lead capture (CPT + email)
│   ├── header.php            # header markup
│   ├── footer.php            # footer + drawer + cookie + lightbox markup
│   ├── templates.php         # page-template loader
│   └── templates/
│       ├── products.php      # catalog portal, archives, product detail
│       ├── sections.php      # services, single service, projects, blog, contact, privacy
│       ├── home.php          # home (hero + sections)
│       └── about.php         # about page
└── assets/
    ├── css/osoul.css         # ALL global CSS (header, footer, lang, drawer, …)
    └── js/osoul.js           # ALL global JS (lang, drawer, forms, animations, …)
```

### 2. Performance
- **~1,500 lines of CSS and ~600 lines of JS were inlined on every page.** They
  now live in `assets/css/osoul.css` and `assets/js/osoul.js`, enqueued with a
  `filemtime()` version string so the browser caches them and only re-downloads
  when they actually change.
- Google Fonts were being loaded **two or three times**; now loaded once with
  `preconnect`.
- Page-specific CSS (e.g. the product-detail styles) now loads **only on that
  page** instead of on every page via `wp_head`.
- Kept the good parts of the original: emoji removal, Gutenberg block-CSS
  dequeue, `?ver=` stripping, DNS-prefetch/preconnect hints.

### 3. NEW — server-side quote & contact backend (`inc/forms.php`)
The original forms only opened WhatsApp or a `mailto:` link, so **if the visitor
closed that app, the lead was lost forever.** Now:
- Every contact form / quote-list / quick-quote submission is **also POSTed to a
  REST endpoint** (`/wp-json/osoul/v1/submit`) and stored as a private
  `osoul_lead` custom post type **before** the WhatsApp hand-off.
- The site admin gets an **email** for each lead (with a `Reply-To` of the
  customer when they left an email).
- Protected by a `wp_rest` **nonce**, a **honeypot** field, and a lightweight
  **per-IP rate limit** (5 / 10 min).
- View/manage submissions under **WP Admin → Quote Leads**.
- Hook `do_action( 'osoul_lead_received', $id, $meta )` to forward leads to a
  CRM / Zapier / SMS, and filter `osoul_lead_notify_email` to change the
  notification address.

The WhatsApp/email buttons still work exactly as before — server capture is
fire-and-forget, so the legacy flow is never blocked even if the API is down.

### 4. Security
- **Host-header injection fixed.** The original echoed raw
  `$_SERVER['HTTP_HOST']` / `REQUEST_URI` into `<link rel="canonical">` and
  `og:url` — an attacker-controllable, cache/SEO-poisoning vector. All URLs are
  now rebuilt from the trusted `home_url()` via `osoul_current_url()`.
- All form input is sanitised server-side (`sanitize_text_field`,
  `sanitize_email`, `sanitize_textarea_field`, …) and escaped on output.
- Added safe security headers (`X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`).
- Kept XML-RPC disabled and the generator/version fingerprints removed.

### 5. SEO
- Added **`hreflang`** alternates (`ar`, `en`, `x-default`) — missing before.
- Added `twitter:card`.
- Same titles, descriptions, Open Graph and schema.org graph as before, but the
  language for titles/locale is read from the sanitised `osoul_lang` cookie.
- **Favicon / Google site icon (`inc/favicon.php`)** — the site ran on a
  near-blank theme and relied entirely on WordPress' "Site Icon" for the icon
  Google shows next to the result. When that was unset (or the uploaded icon had
  a non-ASCII/Arabic filename, which Google's favicon fetcher handles poorly)
  the brand icon disappeared and Google fell back to the generic globe. The
  module now emits **one clean, complete favicon `<link>` set** in `<head>`
  (icon + apple-touch-icon + shortcut icon + msapplication tile + theme-color),
  stands WordPress' own duplicate output down, and answers the `/favicon.ico`
  probe. The icon URL resolves as: **Site Settings → “Favicon”** (paste a clean,
  square, ASCII-named PNG — the recommended fix) → WordPress Site Icon →
  `logo_icon`. Override programmatically with the `osoul_favicon_url` filter.

---

## How the bilingual system works
- A tiny inline script in `<head>` (`inc/i18n.php`) applies the saved language to
  `<html>` **before first paint** (no flash of the wrong language) and syncs an
  `osoul_lang` cookie so PHP can read the preference.
- The full switcher (`osoulSwitchLang`, placeholder swaps, button state) lives in
  the cached `assets/js/osoul.js`.
- Markup uses `data-lang="ar"` / `data-lang="en"` pairs; the
  `osoul_bilingual( $ar, $en )` PHP helper emits them with proper escaping.

---

### 6. Admin-managed contact info (`inc/settings.php`)
Phone numbers, WhatsApp, email, address, working hours, social URLs and the
Google-Maps embed are no longer hard-coded — manage them under
**WP Admin → Quote Leads → Site Settings**. Templates read them through
`osoul_opt()`, and the defaults equal the original hard-coded values, so nothing
changes until you edit a field. The WhatsApp number flows to every form/quote
button automatically (via `osoulData.whatsapp`).

### 7. On-site contact submission
The contact page now has a primary **“Send Request”** button that submits
**on-site** to the REST endpoint and shows an inline success/error message
(form clears on success) — no app switch required. WhatsApp remains as a
secondary **“or via WhatsApp”** button. If the endpoint is unreachable it falls
back to WhatsApp gracefully.

### 8. Products dashboard (manage products from the admin)
Products are no longer hard-coded — they live in an editable `osoul_product`
post type and are managed from a dedicated admin page **“منتجات الموقع / Site
Products”** (top-level menu). A staff member can:
- **Add / edit / delete** products with a simple form (Arabic + English name,
  short line, full description, technical specs, badge, certificates).
- **Pick the image from the media library** (no pasting URLs).
- Choose the **group** (Doors / Gypsum / Strut) and the **slug** (the product
  page URL).

On first activation the **28 built-in products are imported automatically** so
they're immediately editable, and the site looks unchanged. Anything added or
edited shows up instantly in the catalog, the matching archive page and its own
product page (`/your-slug/`). Storage lives in `inc/products-cpt.php`; the UI in
`inc/products-admin.php`; the built-in seed data in `inc/catalog.php`.

> Capability: anyone who can `edit_posts` (Editors and above) can manage
> products — so you can give the staff member an **Editor** account.

### 9. Quotation system (RFQ → price → quote → send → track)
A lightweight quotation/CRM flow built on the plugin:

- **Customer:** the quote-list drawer now has a **"Send Quote Request"** form
  (name / phone / email). Submitting stores the request as an `osoul_quote`
  (stage *New*), prefilled with each product's default unit price, and emails
  the team. (WhatsApp/email stay as alternatives.)
- **Staff dashboard — `/dashboard`:** a standalone branded page with its own
  **login** (email + password). Staff see the requests list with **filters**
  (stage + search). Opening a request shows the items with **quantities already
  filled** from the customer's order; the agent enters the **unit price** per
  piece → line totals, **discount**, **15% VAT** and grand total compute live.
  They set validity + terms and click **"Issue quote"** → a sequential number
  (`OSB-2026-0001`) and a secure token are generated.
- **Send:** the dashboard gives a **WhatsApp** button (message + quote link),
  an **email** button, and a preview link.
- **Public quote — `/quote/{token}`:** a branded, **bilingual** (AR/EN toggle),
  **print-ready** quotation the customer opens from the link — with a
  **"Download / Print PDF"** button (browser save-as-PDF) and **Accept /
  Decline** buttons that move the deal to *Won* / *Lost* automatically.
- **Pipeline:** New → Pricing → Quote Sent → Negotiation → Won → Lost, editable
  from the dashboard.

**Setup for the employee account:**
1. **Users → Add New**, set the email/password, and choose the role
   **"موظف عروض الأسعار"** (created by the plugin). They can manage quotes only.
2. They log in at **`/dashboard`** (not wp-admin) and work from there.

> Requires **pretty permalinks** (Settings → Permalinks → anything but “Plain”)
> for `/dashboard` and `/quote/...` to resolve. Company info, VAT/CR numbers,
> bank accounts and terms shown on the quote come from
> **Quote Leads → Site Settings** (placeholders are filled in — edit them).
> Files: `inc/quotes-cpt.php`, `inc/quotes-rfq.php`, `inc/quotes-dashboard.php`,
> `inc/quotes-public.php`.

---

### 10. Partner Portal — white-label reseller (`inc/partners.php`, `inc/partners-app.php`)
A fourth portal actor beside admin / branch / rep: an **external partner**
(contractor, real-estate company, …) that buys Osoul products at a **cost**
price and re-sells them to its **own** customers under its **own** brand.

**Flow**
1. **Join** at `/partner-register` (company name, VAT, CR, bank, logo). The
   account is created **pending** (blocked) and the admin is emailed.
2. **Approve** under **WP Admin → الشركاء (Partners)** — the partner gets a
   one-time invite link (same secure mechanism as branches) to set a password.
   The admin also sets the partner's **numbering prefix** and an optional
   **minimum-margin %**.
3. **Request products** — the partner picks products + quantities in its
   `/dashboard` (prices hidden, like a rep). This creates a **supply order**.
4. **Osoul prices the cost** — the supply order appears in the normal quotes
   dashboard (source = *شريك/partner*); the admin prices it and issues it. **No
   new admin UI needed.**
5. **Partner issues a white-label quote** — from the priced supply order the
   partner sets a **sell price per line** (sees cost / price / profit live),
   enters the **end-customer's** details, and issues a quotation that renders at
   `/quote/{token}` under the **partner's** identity (logo, name, VAT, CR, bank).

**Two records = a privacy wall.** The *supply order* (`_osoul_kind='supply'`)
is what Osoul sees; its "customer" is the partner company and it holds **no
end-customer data**. The *customer quote* (`_osoul_kind='cust'`) holds the
end-customer's details and the sell prices and is **excluded from every Osoul
query** (see `osoul_quote_exclude_cust_clause()`), so **Osoul can never see a
partner's customers** — enforced structurally, not just hidden in the UI.

**White-label** is driven by an immutable brand snapshot (`_osoul_brand`)
stamped on the customer quote at issue time; `quotes-public.php` prefers it over
the global Site Settings, so historical quotes keep their branding. Each partner
has its **own** quote-number sequence + prefix (e.g. `SHK-2026-0001`),
independent of Osoul's `OSB-` counter. The partner manages its own identity
under **Dashboard → هويّتي**: it **uploads** its logo (media upload, not a URL)
and picks its **brand colours** (primary + accent) with a live quote preview.
Those colours drive both the partner's dashboard theme and — snapshotted at
issue time — the colours of every quote it renders for its customers. The
partner dashboard is a self-contained, brand-themed, responsive UI
(`osoul_partner_css()`, driven by the `--pp-primary` / `--pp-accent` variables).

---

### 11. Employee Webmail — read & send company email from `/dashboard`
A fifth dashboard actor beside admin / branch / rep / partner: an internal
**employee** whose `/dashboard` is a full, elegant webmail client bound to that
person's own Hostinger mailbox. Built for the company's per-staff mailboxes
(e.g. `sales@osoulalbinaa.com`).

**Flow**
1. **Add the employee** under **WP Admin → بريد الموظفين (Employee Mailboxes)** —
   name + email + a login password. The account is created active immediately.
2. **The employee signs in** at **`/dashboard`** with that email + password (the
   same shared login screen as the sales portal — no separate URL).
3. **They link their mailbox once** on first login: they enter only their email
   address and its password. Server settings default to Hostinger
   (`imap.hostinger.com:993` SSL / `smtp.hostinger.com:465` SSL), so there is
   nothing technical to configure — an "advanced" panel can override the hosts if
   ever needed. The credentials are verified (IMAP + SMTP) before saving.
4. **They send & receive** from a Gmail/Outlook-style, bilingual (AR/EN),
   light/dark, RTL/LTR 3-pane client: folders with unread badges, message list,
   reading pane, compose / reply / reply-all / forward, drafts, search,
   attachments (send + download), star, mark read/unread, delete (→ Trash),
   move, a signature, and recipient autocomplete from past correspondents.

**Privacy.** Each mailbox is strictly private to its owner — every REST route is
scoped to the current user, so no employee can read another's mail. The site
owner manages the *account* (add / suspend / reset the login password / unlink /
delete) but has **no path into a mailbox** and never reads anyone's email.

**Security.** The mailbox password must be reversible (IMAP/SMTP need the
plaintext each connection), so it is stored with **AES-256-GCM** using a random
key generated once and kept out of autoload (`osoul_mail_secret`); it is never
stored or logged in the clear. Received HTML is sanitised server-side and
rendered inside a script-less sandboxed iframe.

**No server extension required.** The IMAP client (`inc/mail-engine.php`,
`Osoul_IMAP` + `Osoul_MIME`) is pure PHP over TLS sockets — it does **not** need
the PHP `imap` extension to be enabled — so it works on any Hostinger plan.
Sending uses the PHPMailer that ships with WordPress and files a copy into the
Sent folder. Files: `inc/employees.php` (role + admin manager + credential
vault), `inc/mail-engine.php` (IMAP/MIME/SMTP engine), `inc/webmail-rest.php`
(`osoul/v1/mail/*` API), `inc/webmail-app.php` + `assets/css/webmail.css` +
`assets/js/webmail.js` (the client).

> Requires **pretty permalinks** for `/dashboard` to resolve (same as the sales
> portal). PHP needs `openssl` (credential vault) and outbound access to the
> mail host — both standard on Hostinger.

## Customising
- **Contact info (phone/WhatsApp/email/address/hours/social/map):**
  **WP Admin → Quote Leads → Site Settings** (no code).
- **Products:** **WP Admin → منتجات الموقع (Site Products)** — add/edit/delete,
  no code. `inc/catalog.php` only holds the one-time seed defaults.
- **Global look:** `assets/css/osoul.css` (design tokens are at the top).
- **Notification email:** filter `osoul_lead_notify_email`.

## Notes / next steps
- A few promotional `wa.me` CTA links inside the home/about/services bodies are
  still static links (they're decorative call-to-actions, not the main contact
  paths, which are all settings-driven). They can be wired to `osoul_wa_url()`
  too if desired.
