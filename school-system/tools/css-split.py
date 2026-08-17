#!/usr/bin/env python3
"""
تقسيم ‎assets/dashboard.css‎ إلى طبقات، وإعادة تجميعها.

    python3 tools/css-split.py split     يقسّم مرة واحدة (يُشغَّل مرةً في العمر)
    python3 tools/css-split.py build     يجمع الأجزاء في dashboard.css
    python3 tools/css-split.py check     يتحقق أن التجميع يطابق الملف الحالي

**لماذا:** الملف 4048 سطرًا و144 KB، مبنيّ من إحدى وثلاثين طبقة تاريخية
(v5.0 ثم v6.0 ثم v7.1 … حتى v8.3) كل واحدة تُضيف وتطمس بعض ما قبلها جزئيًا.
تعديل زر واحد فيه يعني قراءة أربعة آلاف سطر لمعرفة أين يُطمس. وبعد التقسيم
يصير لكل شاشة ملفها، والتعديل يلمس ملفًا من بضع عشرات الأسطر.

**الأمان:** التقسيم **نصّي بحت** — لا سطر يُغيَّر ولا يُعاد ترتيبه.
و‎check‎ يتحقق أن ‎build‎ يُنتج نفس البايتات تمامًا. فإن اختلف بايت واحد
فشل الأمر، ولا يُبنى شيء.
"""
from __future__ import annotations

import hashlib
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TARGET = os.path.join(ROOT, 'assets/dashboard.css')
PARTS = os.path.join(ROOT, 'assets/dashboard')
ORDER = os.path.join(PARTS, '_order.txt')

BANNER = re.compile(r'^/\* =+\s*$')


def slug(title: str, n: int) -> str:
    """اسم ملف من عنوان القسم — عربي مسموح، والرقم يحفظ الترتيب."""
    t = title.strip(' *·—-')
    t = re.sub(r'\s*\(v[\d.]+\)\s*$', '', t)          # يحذف «(v7.2)»
    t = re.sub(r'\s+v[\d.]+\s*$', '', t)
    t = re.sub(r'[^\w؀-ۿ]+', '-', t).strip('-')
    return f'{n:02d}-{t[:48] or "قسم"}.css'


def split() -> int:
    src = open(TARGET, encoding='utf-8').read()
    lines = src.splitlines(keepends=True)

    starts = [i for i, ln in enumerate(lines) if BANNER.match(ln)]
    if not starts:
        print('لا فواصل أقسام — لا تقسيم', file=sys.stderr)
        return 1
    if starts[0] != 0:
        starts.insert(0, 0)

    os.makedirs(PARTS, exist_ok=True)
    names = []
    for k, s in enumerate(starts):
        e = starts[k + 1] if k + 1 < len(starts) else len(lines)
        title = lines[s + 1].strip(' */\n') if s + 1 < len(lines) else f'قسم {k}'
        name = slug(title, k)
        # لا اسمين متطابقين
        base, i = name, 1
        while name in names:
            name = base[:-4] + f'-{i}.css'
            i += 1
        names.append(name)
        with open(os.path.join(PARTS, name), 'w', encoding='utf-8') as fh:
            fh.write(''.join(lines[s:e]))

    with open(ORDER, 'w', encoding='utf-8') as fh:
        fh.write('# ترتيب التجميع — **الترتيب يحمل معنى**: الطبقة اللاحقة\n'
                 '# تطمس ما قبلها. لا تُعِد ترتيب السطور بلا سبب مفهوم.\n'
                 '# يُولَّد dashboard.css من هذه القائمة عبر: tools/css-split.py build\n')
        for n in names:
            fh.write(n + '\n')

    print(f'{len(names)} جزءًا في assets/dashboard/')
    return 0


def parts_in_order() -> list[str]:
    out = []
    with open(ORDER, encoding='utf-8') as fh:
        for ln in fh:
            ln = ln.strip()
            if ln and not ln.startswith('#'):
                out.append(os.path.join(PARTS, ln))
    return out


def assemble() -> str:
    return ''.join(open(p, encoding='utf-8').read() for p in parts_in_order())


def build() -> int:
    if not os.path.exists(ORDER):
        return 0                      # لم يُقسَّم بعد — لا شيء يُبنى
    out = assemble()
    old = open(TARGET, encoding='utf-8').read() if os.path.exists(TARGET) else ''
    if out != old:
        open(TARGET, 'w', encoding='utf-8').write(out)
        print(f'بُني dashboard.css من {len(parts_in_order())} جزءًا')
    return 0


def check() -> int:
    if not os.path.exists(ORDER):
        print('لم يُقسَّم بعد')
        return 0
    out = assemble()
    old = open(TARGET, encoding='utf-8').read()
    a, b = hashlib.sha256(out.encode()).hexdigest(), hashlib.sha256(old.encode()).hexdigest()
    if a == b:
        print(f'مطابق تمامًا ({len(out)} بايت · {len(parts_in_order())} جزءًا)')
        return 0
    print(f'اختلاف!\n  الأجزاء : {a[:16]} ({len(out)} بايت)\n  الملف   : {b[:16]} ({len(old)} بايت)',
          file=sys.stderr)
    return 1


if __name__ == '__main__':
    cmd = sys.argv[1] if len(sys.argv) > 1 else 'check'
    sys.exit({'split': split, 'build': build, 'check': check}.get(cmd, check)())
