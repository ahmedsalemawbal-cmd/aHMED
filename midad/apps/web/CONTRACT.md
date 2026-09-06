# عقد بناء الشاشات — مِداد (اقرأه قبل كتابة أيّ صفحة)

## القواعد الإلزامية
1. العربية من اليمين **بنيةً لا انعكاسًا**: استعمل `margin-inline-*`, `padding-inline-*`, `inset-inline-*`, `text-align: start`.
   لا تكتب `left`/`right` أبدًا في الأنماط.
2. **لا ألوان مكتوبة**. كلّ لون من الرموز: `var(--mdd-accent)`, `var(--mdd-text-2)`, `var(--mdd-border)`, `var(--mdd-danger-fg)` …
   (القائمة كاملة في `src/ui/midad.css` تحت `:root`).
3. الأرقام غربية (123) داخل `<span className="mdd-num">`.
4. المبالغ عبر `fmtMoney` والتواريخ عبر `fmtDate` / `fmtBoth` / `fmtRelative` من `src/lib/format.ts`.
5. لا نصّ وهميّ. أسماء حقيقية: مدرسة الأمل الابتدائية · أحمد سالم الغامدي · نورة عبدالله القحطاني · الرياض · جدة.
6. كلّ شاشة تعالج حالاتها: تحميل (`Skeleton*`) · فارغة (`EmptyState`) · خطأ (`ErrorState`) · بلا نتيجة.
7. لا `!important`، ولا مكتبات خارجية جديدة.
8. الجداول داخل `<div className="mdd-table-wrap mdd-table-wrap--cards">` وكلّ `<td>` له `data-label="اسم العمود"`
   حتى تتحوّل إلى بطاقات على الجوّال تلقائيًّا.

## ما هو متاح لك
### `src/ui/kit.tsx`
`Button({variant:'primary'|'secondary'|'soft'|'danger'|'danger-solid'|'ghost', size?:'sm'|'lg', block?, auto?, loading?, icon?})`
`IconButton({label})` · `Field({label,help,error})` · `Input({error?,ltr?})` · `Textarea` · `Select` ·
`SearchInput({value,onChange,placeholder})` · `Switch({checked,onChange,label})` · `Checkbox({checked,onChange})` ·
`Badge({tone:'neutral'|'success'|'warn'|'danger'|'info'|'accent', dot?})` · `Card` · `Stat({label,value,hint})` ·
`Alert({tone})` · `Tabs({tabs:[{key,label,count?}],value,onChange})` · `Chips(...)` · `Modal({open,onClose,title,footer,wide})` ·
`ConfirmModal({open,onClose,onConfirm,title,body,confirmLabel,danger,loading})` ·
`EmptyState({art?,title,line?,action?,extra?})` · `Skeleton` · `SkeletonCards` · `SkeletonRows` · `ErrorState({onRetry,message})` ·
`Progress({value,max,tone?})` · `Avatar({name,size})` · `PageHead({title,sub?,actions?})` · `CopyButton({text,label?})`

**ملاحظة:** `Button` عرضه كامل على الجوّال تلقائيًّا. مرّر `auto` للأزرار التي يجب أن تبقى بحجمها.

### `src/ui/icons.tsx`
IcHome IcLibrary IcFiles IcTable IcTeam IcUser IcSettings IcCard IcInvoice IcChart IcSearch IcPlus IcCheck IcClose
IcMenu IcChevron IcChevronDown IcBack IcSun IcMoon IcDownload IcCopy IcTrash IcEdit IcSpark IcEye IcEyeOff IcLock
IcLogout IcPrint IcClock IcAlert IcShield IcKey IcPuzzle IcBook IcSparkList IcHistory IcWhatsapp IcMail IcExternal
IcGrid IcList IcSpinner IcLogo — كلّها `({size?, className?})`.

### `src/lib/store.tsx`
```ts
const { session, profile, subscriber, plan, plans, roles, general, payment,
        trialDays, access, isAdmin, ready, theme, setTheme, refresh, signIn, signOut, toast } = useApp()
```
`access: 'loading'|'anon'|'trial'|'active'|'expired'|'suspended'|'member_suspended'`
`toast(msg)` أو `toast(msg,'danger')`.

### `src/lib/hooks.ts`
`useAsync(fn, deps) -> { data, loading, error, reload, setData }` · `useDebounced(v, ms)` · `useLocalState(key, initial)`

### `src/lib/data.ts`
`fetchTemplates` `fetchTemplateBySlug` `fetchDocuments(sid)` `fetchNoorTables(sid)` `fetchTeam(sid)`
`fetchInvoices(sid)` `aiUsageThisMonth(sid)` `templateLocked(tpl, plan)` `visibleForRole(tpl, roleKey)`

### `src/lib/supabase.ts`
`supabase` (عميل Supabase) · `callFunction<T>(name, body)` لنداء دوال الحافّة.

### `src/lib/format.ts`
`fmtDate` `fmtShort` `fmtHijri` `fmtBoth` `fmtRelative` `fmtMoney` `fmtNum` `daysBetween` `initials` `daysLabel` `greeting`

## أمثلة مرجعية موجودة — اقرأها قبل أن تكتب
- `src/pages/app/Dashboard.tsx` — بطاقات أرقام + حالة فارغة
- `src/pages/app/Library.tsx` — بحث + فلاتر + شبكة بطاقات + بلا نتيجة
- `src/pages/app/Editor.tsx` — أعقد شاشة: حفظ تلقائي + معاينة حيّة + نوافذ

## قاعدة البيانات (Supabase)
الجداول: `roles` `plans` `subscribers` `profiles` `platform_admins` `subscriptions` `invoices`
`templates` `documents` `link_keys` `noor_tables` `noor_ingest_log` `ai_usage` `platform_settings`
`audit_log` `contact_messages` `push_tokens`.
الأنواع الكاملة في `src/lib/types.ts` — استعملها ولا تخترع أسماء أعمدة.
سياسات RLS مفعّلة: المشترك يرى بيانات مشتركه فقط، والسوبر أدمن يرى كلّ شيء.
