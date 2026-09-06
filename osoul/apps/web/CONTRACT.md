# عقد بناء الشاشات — أصول البناء (اقرأه قبل كتابة أيّ صفحة)

## القواعد الإلزامية

1. **من اليمين بنيةً لا انعكاسًا**: `margin-inline-*` · `padding-inline-*` ·
   `inset-inline-*` · `text-align: start`. **لا تكتب `left`/`right` أبدًا.**
   والموقعُ ثلاثيُّ اللغة (عربي · إنجليزي · أردو)، والإنجليزيّةُ وحدها يساريّة —
   فالقاعدةُ المنطقيّةُ تخدم الثلاثَ بلا ورقةٍ ثانية.
2. **لا ألوان مكتوبة.** كلُّ لونٍ من الرموز: `var(--osl-accent)` ·
   `var(--osl-text-2)` · `var(--osl-line)` · `var(--osl-danger-fg)` …
   القائمةُ كاملةً في `src/ui/osoul.css` تحت `:root`.
3. **لا مسافةَ ولا نصفَ قطرٍ خارج السُّلَّم.** `var(--osl-s-1..10)` و`--osl-r-*`.
   قيمةٌ مكتوبةٌ بيدٍ تُقبل في مكانٍ واحد: `clamp()` للخطوط المتجاوبة.
4. **النصُّ يمرّ بـ`t()`** من `src/lib/i18n.ts`، ومفتاحُه النصُّ العربيُّ نفسُه:
   `t('لوحة القيادة')`. وحقولُ القاعدة الثنائيّةُ بـ`pick(row, 'name')`.
5. **الأرقامُ الغربيّةُ داخل `<span className="osl-num">`** — وإلّا انقلبت في
   النصّ العربيّ. والمبالغُ عبر `fmtMoney` والتواريخُ عبر `fmtDate`/`fmtShort`
   من `src/lib/format.ts`. **ولا تحسب مجموعَ عرضٍ بيدك: `quoteTotals()`.**
6. **لا نصَّ وهميًّا.** أسماءٌ حقيقيّة: شركة أصول البناء للصناعة · باب معدنيّ
   مقاومٌ للحريق · C-Stud 50 مم · جدة، المدينة الصناعية الثالثة.
7. **كلُّ شاشةٍ تعالج حالاتها الأربع**: تحميل (`Skeleton`) · فارغة
   (`EmptyState`) · خطأ (`ErrorState`) · بلا نتيجة.
8. **لا `!important`، ولا مكتبةَ مكوّناتٍ جديدة.**
9. **لا تحجب المالَ في الواجهة.** السعرُ الذي لا يحقّ للمستخدم لا يصله من
   القاعدة أصلًا (`quote_prices` جدولٌ منفصلٌ بسياسته). فإن رأيتَ نفسَك تكتب
   `role === 'admin' ? price : '—'` فالعطبُ في السياسة لا في الشاشة.

## المتاح لك

### `src/ui/kit.tsx`
`Container({wide})` · `Button({variant:'primary'|'secondary'|'soft'|'ghost'|'danger', size:'sm'|'lg', block, loading, icon})` ·
`Spinner` · `FullLoader` · `Field({label,help,error,required})` · `Input({error,ltr})` ·
`Textarea` · `Select` · `Card({pad})` · `Badge({tone})` · `Alert({tone})` ·
`EmptyState({title,line,action})` · `ErrorState({message,onRetry})` · `Skeleton({h,w})`

`tone`: `neutral | success | warn | danger | info | accent`

### `src/ui/icons.tsx`
`IcMenu IcClose IcSun IcMoon IcGlobe IcPhone IcMail IcWhatsapp IcChevron IcCheck IcCart IcDoor IcPanel IcStrut`
— كلُّها `({size?, className?})` وتتبع لونَ النصّ بـ`currentColor`.

### `src/lib/store.tsx`
```ts
const { session, profile, role, ready, lang, setLang, t, theme, setTheme, refresh, signOut } = useApp()
```
`role: 'admin'|'branch'|'rep'|'partner'|'customer'|'employee'|''`

### `src/lib/hooks.ts`
`useAsync(fn, deps) -> { data, loading, error, reload, setData }` · `useDebounced(v, ms)` · `useLocalState(key, initial)`

### `src/lib/supabase.ts`
`supabase` · `callFunction<T>(name, body)`

### `src/lib/format.ts`
`fmtMoney` `fmtNum` `fmtDate` `fmtShort` `fmtDateTime` `fmtRelative` `initials` `quoteTotals`

## أمثلةٌ مرجعيّةٌ موجودة — اقرأها قبل أن تكتب
- `src/pages/site/Home.tsx` — أقسامٌ وبطاقاتٌ وشبكة
- `src/pages/site/SiteLayout.tsx` — الرأسُ والتذييلُ ومبدّلا اللغة والسمة

## المسارُ الجديد يُسجَّل في موضعين
`src/App.tsx` (المسار) و`routes.json` (العنوانُ والوصفُ للرسم المسبق).
**ومسارٌ عامٌّ لا يُسجَّل في `routes.json` لا يُؤرشَف** — يعمل للزائر ولا يوجد لجوجل.
