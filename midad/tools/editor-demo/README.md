# معاينة محرّر مِداد — نسخةٌ مستقلّة

ملفٌّ واحدٌ مكتفٍ بنفسه: يفتحه المالك أو معلّمٌ فيجرّب المحرّر بلا حسابٍ
ولا خادم. نفس محرّك التحرير ونفس شريط الأدوات ونفس الورقة الموجودة في
المنصّة — والفرق أنّ المحتوى في الذاكرة لا في القاعدة، والتحسين معروضٌ
ولا يُنفَّذ (لا مفتاح ذكاءٍ اصطناعيّ في نسخةٍ عامّة).

## البناء

يستعمل `node_modules` من تطبيق الويب — فلا تثبيتَ منفصلًا:

```bash
cd midad/tools/editor-demo
ln -sfn ../../apps/web/node_modules node_modules
npx vite build          # ← dist/index.html ملفٌّ واحد
```

`vite-plugin-singlefile` يُدرج الشيفرة والأنماط في الملفّ نفسه. الخطوط
وحدها من الشبكة (Google Fonts) ولها بديلٌ محلّيّ في سلسلة `font-family`.

## للنشر كصفحةٍ مستقلّة

الغلاف الخارجيّ يملك `<html>` و`<body>`، فالمحتوى يُستخرج بلا هذه الوسوم:

```python
import re, pathlib
s = pathlib.Path('dist/index.html').read_text()
title  = re.search(r'<title>(.*?)</title>', s, re.S).group(1)
style  = re.search(r'<style[^>]*>(.*?)</style>', s, re.S).group(1)
scripts = re.findall(r'<script[^>]*>(.*?)</script>', s, re.S)
out = [f'<title>{title}</title>', f'<style>\n{style}\n</style>',
       '<div id="root" lang="ar" dir="rtl"></div>']
out += [f'<script type="module">{sc}</script>' for sc in scripts]
pathlib.Path('midad-editor.html').write_text('\n'.join(out), encoding='utf-8')
```

الاتّجاه يُضبط على `body` في CSS لا على `<html>` — لأنّنا لا نصل إليه.

## ما يُختبر فيها

٣٠ فحصًا بمتصفّح حقيقيّ على ثلاثة مقاسات وفي الوضعين: الشريط، والجداول
الثلاثة بترويسة صفّين وخلايا ممتدّة، والقوائم المتفرّعة، وإضافة الصفوف،
ونافذة التحسين، والطباعة، وأنّ الورقة تبقى بيضاء في الوضع الداكن.
