#!/usr/bin/env python3
"""فحص بنيويّ لمداد — ما يمكن كشفه بلا تشغيل PHP.

وأوّل فحصٍ فيه هو سبب وجوده: **لا `$wpdb` خارج البوّابة**. منصّة اشتراكات
تنهار بسطرٍ واحد ينسى `WHERE tenant_id`، والانضباط لا يحرس ألف سطر — البنية
تحرسها، وهذا الملفّ يُثبت أن البنية قائمة.
"""

import glob
import os
import re
import sys
from collections import defaultdict

ROOT = os.path.dirname(os.path.abspath(__file__))
issues = []


def add(kind, msg):
    issues.append((kind, msg))


def php_files():
    for root, _, files in os.walk(ROOT):
        if '/node_modules' in root or '/vendor' in root:
            continue
        for n in sorted(files):
            if n.endswith('.php'):
                yield os.path.join(root, n)


sources = {p: open(p, encoding='utf-8').read() for p in php_files()}
rel = lambda p: os.path.relpath(p, ROOT)


def strip_noise(code):
    """يزيل النصوص ثم التعليقات — الأقواس داخلها ليست بنية كود."""
    code = re.sub(r"<<<'?(\w+)'?\n.*?\n\1;", '""', code, flags=re.S)
    code = re.sub(r"'(?:\\.|[^'\\])*'", "''", code)
    code = re.sub(r'"(?:\\.|[^"\\])*"', '""', code)
    code = re.sub(r'/\*.*?\*/', '', code, flags=re.S)
    code = re.sub(r'//[^\n]*', '', code)
    return code


# ─────────────────── ① البوّابة: لا $wpdb خارجها ───────────────────
#
# هذا الفحص هو عمود مداد. `MDD_DB` تحقن `tenant_id` في كل استعلام، فإن
# فتح ملفٌّ آخر `$wpdb` مباشرةً سقط العزل عند ذلك السطر وحده — ولا يُكتشف
# إلّا حين يرى مشتركٌ بيانات غيره.
#
# ويُستثنى ملفّان: البوّابة نفسها، والمفعّل (ينشئ الجداول ويزرعها قبل أن
# يوجد مستأجر أصلًا).

GATE = 'includes/class-db.php'
SETUP = 'includes/class-activator.php'

for path, src in sources.items():
    r = rel(path)

    if r in (GATE, SETUP) or r.startswith('tools/'):
        continue

    code = strip_noise(src)

    for hit in re.findall(r'\$wpdb\s*->\s*\w+', code):
        add('DB_BYPASS', f'{r}: {hit} — لا $wpdb خارج {GATE}. استعمل MDD_DB.')

    if re.search(r'\bglobal\s+\$wpdb\b', code):
        add('DB_BYPASS', f'{r}: global $wpdb — لا $wpdb خارج {GATE}.')


# ─────────────────── ② كل جدولٍ يعرف صاحبه ───────────────────
#
# جدولٌ بلا `tenant_id` لا يمكن عزله مهما احترست الشفرة. وجدولٌ به بلا
# فهرسٍ عليه يمسح نفسه كلَّه لكل مشتركٍ يفتح شاشة — وكل استعلامٍ في مداد
# يبدأ بهذا الشرط.

GLOBAL_TABLES = {'roles', 'plans', 'templates'}
SELF_TABLE = 'tenants'

act = sources.get(os.path.join(ROOT, SETUP), '')
created = []

for m in re.finditer(r'CREATE TABLE \{\$p\}(\w+) \((.*?)\n        \) \{\$charset\};', act, re.S):
    name, body = m.group(1), m.group(2)
    created.append(name)

    if name in GLOBAL_TABLES or name == SELF_TABLE:
        continue

    if not re.search(r'^\s*tenant_id\s+BIGINT', body, re.M):
        add('TENANT_MISSING', f'{name}: بلا عمود tenant_id — لا يمكن عزله.')
        continue

    if not re.search(r'KEY\s+\w+\s*\(\s*tenant_id', body):
        add('TENANT_UNINDEXED', f'{name}: tenant_id بلا فهرس — مسحٌ كامل لكل قراءة.')

# عمودٌ مكرّر يجعل MySQL يرفض الجدول كلَّه بلا رسالةٍ تدلّ (درس أُصول v2.12.2)
for m in re.finditer(r'CREATE TABLE \{\$p\}(\w+) \((.*?)\n        \) \{\$charset\};', act, re.S):
    cols = re.findall(r'^\s{12}(\w+)\s+[A-Z]', m.group(2), re.M)
    dup = {c for c in cols if cols.count(c) > 1}

    if dup:
        add('SCHEMA_DUP_COLUMN', f'{m.group(1)}: عمودٌ مكرّر {sorted(dup)} — MySQL يرفض الجدول كلَّه.')

used = set()
for src in sources.values():
    used |= set(re.findall(r"MDD_DB::(?:rows|one|first|count|exists|insert|update|delete|across)\(\s*'(\w+)'", src))
    used |= set(re.findall(r"MDD_DB::table\('(\w+)'\)", src))

for t in sorted(used - set(created)):
    add('TABLE_MISSING', f'جدول «{t}» يُستعمل ولا يُنشأ في {SETUP}.')


# ─────────────────── ③ أساسيّات الملفّ ───────────────────

for path, src in sources.items():
    r = rel(path)
    code = strip_noise(src)

    if code.count('{') != code.count('}'):
        add('BRACES', f'{r}: {{ = {code.count("{")} , }} = {code.count("}")}')
    if code.count('(') != code.count(')'):
        add('PARENS', f'{r}: ( = {code.count("(")} , ) = {code.count(")")}')
    if not src.lstrip().startswith('<?php'):
        add('OPEN_TAG', r)
    if 'declare(strict_types=1)' not in src:
        add('NO_STRICT', f'{r}: بلا declare(strict_types=1)')
    if r != 'midad.php' and not re.search(r"defined\(\s*['\"]ABSPATH['\"]\s*\)", src):
        add('NO_GUARD', f'{r}: بلا حارس ABSPATH — يُفتح مباشرةً من المتصفّح.')


# ─────────────────── ④ الأصناف والدوالّ ───────────────────

class_defs = {}
class_methods = defaultdict(set)
class_consts = defaultdict(set)

for path, src in sources.items():
    for m in re.finditer(r'^\s*(?:final\s+)?class\s+(\w+)', src, re.M):
        name = m.group(1)
        class_defs[name] = path
        body = src[m.end():]
        for mm in re.finditer(r'function\s+(\w+)\s*\(', body):
            class_methods[name].add(mm.group(1))
        for cc in re.finditer(r'^\s*(?:public\s+|private\s+|protected\s+)?const\s+(\w+)', body, re.M):
            class_consts[name].add(cc.group(1))

for path, src in sources.items():
    r = rel(path)

    for m in re.finditer(r'\b(MDD_\w+)::(\w+)', src):
        cls, member = m.group(1), m.group(2)

        if cls not in class_defs:
            add('CLASS_MISSING', f'{r}: {cls}')
            continue

        if member != 'class' and member not in class_methods[cls] and member not in class_consts[cls]:
            add('MEMBER_MISSING', f'{r}: {cls}::{member}')

func_defs = set()
for src in sources.values():
    func_defs |= set(re.findall(r'^function\s+(mdd_\w+)\s*\(', src, re.M))

for path, src in sources.items():
    r = rel(path)

    for m in re.finditer(r'(?<![\w:>$])(mdd_\w+)\s*\(', src):
        if m.group(1) not in func_defs:
            add('FUNC_MISSING', f'{r}: {m.group(1)}()')


# ─────────────────── ⑤ المحمّل يعرف كل ملفّ ───────────────────

loader = sources.get(os.path.join(ROOT, 'includes/class-loader.php'), '')
listed = set(re.findall(r"'([\w/\-]+\.php)'", loader))
listed.add('includes/class-loader.php')

for path in sources:
    r = rel(path)

    if r == 'midad.php' or r.startswith('tools/') or r.startswith('languages/'):
        continue

    if r not in listed:
        add('NOT_LOADED', f'{r}: ملفٌّ لا يذكره المحمّل — شفرةٌ لا تعمل.')


# ─────────────────── ⑥ تهريب المخرجات في القوالب ───────────────────

for path, src in sources.items():
    r = rel(path)

    if '/views/' not in r and '/site/' not in r:
        continue

    for m in re.finditer(r'<\?=\s*(.+?)\s*\?>', src):
        add('UNESCAPED', f'{r}: <?= … ?> بلا تهريب — استعمل esc_html/esc_attr/esc_url.')

    for m in re.finditer(r'<\?php\s+echo\s+(?!esc_|wp_kses|MDD_|mdd_icon)([^;]{1,60});', src):
        add('UNESCAPED', f'{r}: echo {m.group(1).strip()[:40]}… بلا تهريب')


# ─────────────────── ⑦ توازن القوالب ───────────────────
#
# `if (…) :` بلا `endif` يوقف PHP بخطأٍ فادح — صفحةٌ بيضاء بلا أيّ دليل.

for path, src in sources.items():
    r = rel(path)
    opens = closes = 0

    for tag in re.findall(r'<\?php(.*?)(?:\?>|$)', src, re.S):
        code = strip_noise('<?php' + tag)
        opens += len(re.findall(r'\b(?:if|foreach|for|while|switch)\s*\(.*?\)\s*:', code))
        closes += len(re.findall(r'\b(?:endif|endforeach|endfor|endwhile|endswitch)\s*;', code))

    if opens != closes:
        add('TPL_UNBALANCED', f'{r}: صيغة بديلة مفتوحة {opens} ومغلقة {closes}')


# ─────────────────── النتيجة ───────────────────

if not issues:
    print('✅ لا توجد ملاحظات — كل الفحوص نظيفة.')
    sys.exit(0)

grouped = defaultdict(list)
for kind, msg in issues:
    grouped[kind].append(msg)

for kind in sorted(grouped):
    print(f'\n■ {kind} ({len(grouped[kind])})')
    for msg in grouped[kind][:25]:
        print(f'   - {msg}')
    if len(grouped[kind]) > 25:
        print(f'   … و{len(grouped[kind]) - 25} أخرى')

print(f'\nالمجموع: {len(issues)}')
sys.exit(1)
