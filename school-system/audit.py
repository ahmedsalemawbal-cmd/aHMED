#!/usr/bin/env python3
"""
الفحص الآلي لنظام المدرسة — يُشغَّل من جذر الإضافة:

    python3 audit.py            # كل الفحوص
    python3 audit.py --list     # أسماء الفحوص
    python3 audit.py -o NAME    # فحص واحد
    python3 audit.py --strict   # التحذير يُفشِل أيضًا

كل فحص هنا **أُثبت بإحداث عطله ثم إزالته** — لا فحص يُقبل بلا ذلك،
وإلا لم نعرف إن كان يمسك شيئًا أصلًا.

الخروج: 0 نظيف · 1 أخطاء · 2 استعمال خاطئ.
"""
from __future__ import annotations

import argparse
import collections
import os
import re
import sys

ROOT = os.path.dirname(os.path.abspath(__file__))

# ---------------------------------------------------------------- الأدوات

RESET, BOLD, RED, YEL, GRN, DIM = '\033[0m', '\033[1m', '\033[31m', '\033[33m', '\033[32m', '\033[2m'
if not sys.stdout.isatty() or os.environ.get('NO_COLOR'):
    RESET = BOLD = RED = YEL = GRN = DIM = ''

Finding = collections.namedtuple('Finding', 'check level file line msg')
FINDINGS: list[Finding] = []
CHECKS: dict[str, callable] = {}


def check(name: str):
    def deco(fn):
        CHECKS[name] = fn
        return fn
    return deco


def fail(name, path, line, msg):
    FINDINGS.append(Finding(name, 'error', rel(path), line, msg))


def warn(name, path, line, msg):
    FINDINGS.append(Finding(name, 'warn', rel(path), line, msg))


def rel(p: str) -> str:
    return os.path.relpath(p, ROOT) if p else '-'


_CACHE: dict[str, str] = {}


def read(path: str) -> str:
    if path not in _CACHE:
        with open(path, encoding='utf-8', errors='replace') as fh:
            _CACHE[path] = fh.read()
    return _CACHE[path]


def files(*exts: str, skip=('assets/fonts',)) -> list[str]:
    out = []
    for base, dirs, names in os.walk(ROOT):
        dirs[:] = [d for d in dirs if d not in ('.git', 'node_modules', 'vendor', 'languages')]
        for n in sorted(names):
            if n.endswith(exts):
                p = os.path.join(base, n)
                if not any(s in rel(p) for s in skip):
                    out.append(p)
    return out


def php() -> list[str]:
    return files('.php')


def css() -> list[str]:
    return files('.css')


def lineno(src: str, pos: int) -> int:
    return src[:pos].count('\n') + 1


def mask_php(html: str) -> str:
    """
    يُقنّع وسوم ‎<?php … ?>‎ بمسافات بنفس الطول.

    قاعدة ملزمة من CLAUDE.md: لا تعديل ولا فحص نمطي على HTML داخل PHP قبل
    التقنيع — ‎[^>]*‎ يتوقف عند ‎>‎ داخل وسم PHP فيقطع الوسم. أفسد هذا ١٨ حقلًا
    في جلسة سابقة. والتقنيع يحفظ المواضع فتبقى أرقام الأسطر صحيحة.
    """
    html = strip_php_comments(html)
    return re.sub(r'<\?(?:php|=).*?\?>', lambda m: ' ' * len(m.group(0)), html, flags=re.S)


def strip_php_comments(s: str) -> str:
    """يُقنّع تعليقات PHP بمسافات — ‎<img>‎ داخل شرح ليس صورةً بلا ‎alt‎."""
    s = re.sub(r'/\*.*?\*/', lambda m: ' ' * len(m.group(0)), s, flags=re.S)
    return re.sub(r'(?m)//[^\n]*', lambda m: ' ' * len(m.group(0)), s)


def strip_css_comments(s: str) -> str:
    return re.sub(r'/\*.*?\*/', lambda m: ' ' * len(m.group(0)), s, flags=re.S)


# ---------------------------------------------------------------- PHP: البنية

_TOKENS: dict | None = None


def tokens() -> dict:
    """
    تحليل كل ملفات PHP بمحلّل PHP نفسه (‎tools/php-tokens.php‎).

    التعبير النمطي لا يميّز ‎if (…):‎ من ‎$a ? b : c‎ من ‎case 'x':‎ من نقطتين
    داخل نص — النسخة النمطية من هذا الفحص أنتجت 119 نتيجة خاطئة على كود سليم.
    """
    global _TOKENS
    if _TOKENS is not None:
        return _TOKENS

    import json
    import subprocess

    helper = os.path.join(ROOT, 'tools/php-tokens.php')
    if not os.path.exists(helper):
        _TOKENS = {}
        return _TOKENS
    try:
        res = subprocess.run(['php', helper, *php()], capture_output=True, text=True, timeout=180)
        _TOKENS = json.loads(res.stdout) if res.stdout.strip() else {}
    except Exception:
        _TOKENS = {}
    return _TOKENS


@check('TPL_UNBALANCED')
def c_tpl_unbalanced():
    """‎if (…) :‎ بلا ‎endif‎ = خطأ فادح وصفحة بيضاء بلا أي دليل.

    الصيغة البديلة وحدها تُحسب — القوس المعقوف يُغلق نفسه ويمسكه ‎php -l‎.
    """
    data = tokens()
    if not data:
        warn('TPL_UNBALANCED', '', 0, 'تعذّر تشغيل php — الفحص لم يجرِ')
        return
    PAIR = {'if': 'endif', 'foreach': 'endforeach', 'for': 'endfor',
            'while': 'endwhile', 'switch': 'endswitch'}
    for path, info in data.items():
        alt, ends = info.get('alt') or {}, info.get('ends') or {}
        for kw, end in PAIR.items():
            opened, closed = int(alt.get(kw, 0)), int(ends.get(end, 0))
            if opened != closed:
                fail('TPL_UNBALANCED', path, 0,
                     f'{kw}(…): مفتوحة {opened} و{end} مكتوبة {closed}')


@check('TAG_BROKEN')
def c_tag_broken():
    """وسم أو قوس لا يُغلق — علامة أن تعديلًا نصّيًا أفسد الملف.

    الحكم بـ‎php -l‎ لا بعدّ الوسوم: عدّ ‎<?php‎ مقابل ‎?>‎ لا يكشف الوسم
    المكسور (فحين يُحذف ‎?>‎ يصير ما بعده كودًا، فينقص العدّان معًا)،
    والمحلّل نفسه هو من يعرف. وخطأ نحوي واحد = صفحة بيضاء بلا أي دليل.
    """
    import subprocess
    for p in php():
        try:
            res = subprocess.run(['php', '-l', p], capture_output=True, text=True, timeout=30)
        except Exception:
            warn('TAG_BROKEN', p, 0, 'تعذّر تشغيل php -l')
            return
        if res.returncode != 0:
            first = (res.stdout + res.stderr).strip().split('\n')[0]
            fail('TAG_BROKEN', p, 0, first[:200])


@check('SCHEMA_DUP_COLUMN')
def c_schema_dup_column():
    """عمود مكرر في ‎CREATE TABLE‎ يجعل MySQL يرفض الجدول كله بصمت.

    وقع في ‎students‎ (‎user_id‎ مرتين) فلم يُسجَّل طالب واحد، والرسالة عامة.
    """
    TYPE = (r'bigint|int|varchar|text|longtext|mediumtext|date|datetime|timestamp|time|'
            r'decimal|tinyint|smallint|char|enum|float|double|json|blob')
    for p in php():
        src = read(p)
        for m in re.finditer(r'CREATE TABLE\s+\{?\$p\}?(\w+)\s*\((.*?)\n\s*\)\s*(?:\{?\$charset\}?|ENGINE)',
                             src, re.S):
            table, body = m.group(1), m.group(2)
            cols = re.findall(r'^\s{2,}`?(\w+)`?\s+(?:' + TYPE + r')\b', body, re.M | re.I)
            for col, n in collections.Counter(cols).items():
                if n > 1:
                    fail('SCHEMA_DUP_COLUMN', p, lineno(src, m.start()),
                         f'الجدول {table}: العمود «{col}» مكرر ×{n}')


@check('SQL_UNKNOWN_COLUMN')
def c_sql_unknown_column():
    """عمود يُستعلم عنه ولا يوجد في ‎CREATE TABLE‎.

    كان ‎SCH_Teacher::my_classes()‎ تستعلم عن ‎c.homeroom_user_id‎ والعمود
    ‎homeroom_teacher_id‎ — فيفشل الاستعلام كاملًا ويرى المعلم شاشة بلا فصل،
    ولا أثر إلا في سجل التصحيح.
    """
    TYPE = (r'bigint|int|varchar|text|longtext|mediumtext|date|datetime|timestamp|time|'
            r'decimal|tinyint|smallint|char|enum|float|double|json|blob')
    schema: dict[str, set[str]] = {}
    for p in php():
        src = read(p)
        for m in re.finditer(r'CREATE TABLE\s+\{?\$p\}?(\w+)\s*\((.*?)\n\s*\)\s*(?:\{?\$charset\}?|ENGINE)',
                             src, re.S):
            schema[m.group(1)] = set(
                re.findall(r'^\s{2,}`?(\w+)`?\s+(?:' + TYPE + r')\b', m.group(2), re.M | re.I))
    if not schema:
        return

    # النطاق: **استعلام واحد**. النافذة الثابتة كانت تتعدّى الاستعلام للتالي
    # فتربط الاسم المستعار بجدول استعلام آخر — خمس نتائج خاطئة على كود سليم.
    CALL = re.compile(r'\$wpdb->(?:prepare|get_results|get_row|get_var|get_col|query)\s*\(')
    for p in php():
        src = read(p)
        for call in CALL.finditer(src):
            i, depth = call.end(), 1
            while i < len(src) and depth:
                if src[i] == '(':
                    depth += 1
                elif src[i] == ')':
                    depth -= 1
                i += 1
            body = src[call.end():i - 1]

            aliases: dict[str, str] = {}
            for b in re.finditer(r"sch_table\(\s*'(\w+)'\s*\)\s*\.\s*['\"]\s*(\w{1,3})\b", body):
                if b.group(1) in schema:
                    aliases[b.group(2)] = b.group(1)
            if not aliases:
                continue

            for ref in re.finditer(r'\b([a-z]{1,3})\.(\w+)\b', body):
                alias, col = ref.group(1), ref.group(2)
                table = aliases.get(alias)
                if not table:
                    continue
                if col in schema[table] or col.lower() == 'id':
                    continue
                fail('SQL_UNKNOWN_COLUMN', p, lineno(src, call.start() + ref.start()),
                     f'{alias}.{col} — لا وجود لـ«{col}» في جدول {table}')


@check('PREPARE_ARITY')
def c_prepare_arity():
    """عدد ‎%s/%d/%f‎ في ‎prepare()‎ لا يطابق عدد الوسائط.

    التقسيم على أول فاصلة **خاطئ**: نص SQL نفسه فيه فواصل
    (‎SET a = 1, b = 2‎)، فيُقتطع النص فيُعدّ عنصر تنسيق واحد بدل اثنين.
    الصحيح: مسح يحترم علامات النص والأقواس، والفاصلة العليا وحدها تفصل.
    """
    def split_args(body: str) -> list[str]:
        """يقسم وسائط النداء على الفواصل العليا فقط — خارج نص وخارج قوس."""
        parts, buf = [], ''
        depth, quote, esc = 0, '', False
        for ch in body:
            if esc:
                buf += ch
                esc = False
                continue
            if quote:
                buf += ch
                if ch == '\\':
                    esc = True
                elif ch == quote:
                    quote = ''
                continue
            if ch in '"\'':
                quote = ch
                buf += ch
                continue
            if ch in '([':
                depth += 1
            elif ch in ')]':
                depth -= 1
            if ch == ',' and depth == 0:
                parts.append(buf)
                buf = ''
                continue
            buf += ch
        if buf.strip():
            parts.append(buf)
        return parts

    def literals(expr: str) -> str:
        """يجمع محتوى النصوص الحرفية في التعبير — ويتجاهل نداءات الدوال."""
        return ''.join(m.group(2) for m in re.finditer(r'([\'"])(.*?)(?<!\\)\1', expr, re.S))

    for p in php():
        src = read(p)
        for m in re.finditer(r'\$wpdb->prepare\s*\(', src):
            i, depth = m.end(), 1
            while i < len(src) and depth:
                if src[i] == '(':
                    depth += 1
                elif src[i] == ')':
                    depth -= 1
                i += 1
            body = src[m.end():i - 1]

            args = split_args(body)
            if len(args) < 1:
                continue

            fmt = literals(args[0])
            holes = len(re.findall(r'%[sdf]', fmt))
            given = len([a for a in args[1:] if a.strip()])

            # نداء بمصفوفة مفكوكة أو %% حرفية: لا حكم قاطع
            if '...' in body or '%%' in fmt:
                continue
            if holes and holes != given:
                fail('PREPARE_ARITY', p, lineno(src, m.start()),
                     f'{holes} عنصر تنسيق مقابل {given} وسيطًا')


# ---------------------------------------------------------------- الأفعال والنونس

def _dashboard_registry() -> dict[str, tuple[str, str]]:
    src = read(os.path.join(ROOT, 'frontend/class-dashboard.php'))
    return {m.group(1): (m.group(2), m.group(3)) for m in
            re.finditer(r"'([a-z_0-9]+)'\s*=>\s*\['([a-z_]+)',\s*'([a-z_0-9]+)'\]", src)}


@check('ACTION_NO_HANDLER')
def c_action_no_handler():
    """نموذج يرسل ‎sch_action‎ لا يعرفه السجل — يُعيدك بصمت بلا أن يفعل شيئًا."""
    reg = _dashboard_registry()
    for p in php():
        src = read(p)
        for m in re.finditer(
                r"name=[\"']sch_action[\"'][^>]{0,120}?value=[\"']([a-z_0-9]+)[\"']"
                r"|value=[\"']([a-z_0-9]+)[\"'][^>]{0,120}?name=[\"']sch_action[\"']", src):
            act = m.group(1) or m.group(2)
            if act not in reg:
                fail('ACTION_NO_HANDLER', p, lineno(src, m.start()),
                     f'sch_action="{act}" ولا مدخل له في actions()')


@check('ACTION_METHOD_MISSING')
def c_action_method_missing():
    """فعل مسجَّل ومعالجه غير معرَّف — خطأ فادح عند أول إرسال."""
    dash = os.path.join(ROOT, 'frontend/class-dashboard.php')
    src = read(dash)
    defined = set(re.findall(r'function\s+([a-z_0-9]+)\s*\(', src))
    for act, (_cap, meth) in _dashboard_registry().items():
        if meth not in defined:
            fail('ACTION_METHOD_MISSING', dash, 0, f'الفعل «{act}» معالجه {meth}() غير معرَّف')


@check('FORM_NO_NONCE')
def c_form_no_nonce():
    """نموذج POST بلا نونس — الموجّه يرفضه، فالميزة لا تعمل أبدًا."""
    for p in php():
        if '/views/' not in rel(p) and '/app/' not in rel(p) and '/modules/' not in rel(p) \
                and not re.search(r'/(teacher|gate|driver|student)/', rel(p)):
            continue
        src = read(p)
        for m in re.finditer(r'<form\b.*?</form>', src, re.S):
            blk = m.group(0)
            if not re.search(r'method=[\"\']post[\"\']', blk, re.I):
                continue
            if 'nonce' in blk or '_wpnonce' in blk:
                continue
            # نموذج يُرسل بجافاسكربت لمسار REST يحمل نونس wp_rest
            if 'rest_url' in blk or 'js-' in blk:
                continue
            fail('FORM_NO_NONCE', p, lineno(src, m.start()), 'نموذج POST بلا wp_nonce_field')


@check('CAP_ORPHAN')
def c_cap_orphan():
    """صلاحية يطلبها النظام ولا يملكها أي دور ‎sch_*‎ — قسم لا يفتحه إلا المدير."""
    act = os.path.join(ROOT, 'includes/class-activator.php')
    src = read(act)
    role_caps: set[str] = set()
    for m in re.finditer(r"'caps'\s*=>\s*\[(.*?)\]", src, re.S):
        role_caps |= set(re.findall(r"'(sch_[a-z_]+)'", m.group(1)))

    dash = read(os.path.join(ROOT, 'frontend/class-dashboard.php'))
    needed = set(re.findall(r"^\s*'[a-z0-9\-]+'\s*=>\s*\['[^']*',\s*'(sch_\w+)'", dash, re.M))
    for cap in sorted(needed - role_caps):
        warn('CAP_ORPHAN', act, 0, f'{cap} — لا دور sch_* يملكها (المدير وحده)')


# ---------------------------------------------------------------- التوجيه والأقسام

@check('SECTION_NO_VIEW')
def c_section_no_view():
    """قسم مسجَّل في ‎SECTIONS‎ بلا ملف عرض — 404 لمن يفتحه من التنقّل."""
    dash = os.path.join(ROOT, 'frontend/class-dashboard.php')
    src = read(dash)
    for m in re.finditer(r"^\s*'([a-z0-9\-]+)'\s*=>\s*\['[^']*',\s*'sch_\w+'", src, re.M):
        slug = m.group(1)
        if not os.path.exists(os.path.join(ROOT, 'frontend/views', slug + '.php')):
            fail('SECTION_NO_VIEW', dash, lineno(src, m.start()),
                 f'القسم «{slug}» بلا frontend/views/{slug}.php')


@check('ROUTE_NOT_FLUSHED')
def c_route_not_flushed():
    """واجهة تسجّل قواعد توجيه ولا شيء يفرّغها = 404 دائم على تثبيت نظيف.

    كان التفريغ داخل ‎SCH_Dashboard::add_rewrite()‎ على ‎init/10‎، أي قبل أن
    تسجّل بقية الواجهات قواعدها — فست واجهات من سبع تُرجع 404.
    """
    adders = [p for p in php() if 'add_rewrite_rule' in read(p)]
    flushers = [p for p in php() if 'flush_rewrite_rules' in read(p)]
    if not adders:
        return
    late = [p for p in flushers
            if re.search(r"add_action\(\s*'init'[^)]*?,\s*(9\d|[1-9]\d{2,})\s*\)", read(p))]
    if not late:
        fail('ROUTE_NOT_FLUSHED', adders[0], 0,
             f'{len(adders)} ملفًا يسجّل قواعد توجيه، ولا تفريغ على init بأولوية متأخرة (90+)')


@check('LINK_DEAD_PARAM')
def c_link_dead_param():
    """رابط بمعامل ‎sch_*‎ لا أحد يقرأه — نصف ميزة تبدو عاملة."""
    read_params: set[str] = set()
    for p in php():
        src = read(p)
        read_params |= set(re.findall(r"\$_GET\[\s*'(sch_\w+)'", src))
        read_params |= set(re.findall(r"isset\(\s*\$_GET\[\s*'(sch_\w+)'", src))
    for p in php():
        src = read(p)
        for m in re.finditer(r"add_query_arg\(\s*'(sch_\w+)'", src):
            if m.group(1) not in read_params:
                fail('LINK_DEAD_PARAM', p, lineno(src, m.start()),
                     f'المعامل «{m.group(1)}» يُبنى ولا يُقرأ في أي مكان')


# ---------------------------------------------------------------- الأدوار والواجهات

@check('ROLE_NO_CAPS')
def c_role_no_caps():
    """دور بلا صلاحية واحدة — يوجد في القائمة ولا يفتح شيئًا."""
    act = os.path.join(ROOT, 'includes/class-activator.php')
    src = read(act)
    for m in re.finditer(r"'(sch_\w+)'\s*=>\s*\[\s*'label'.*?'caps'\s*=>\s*\[(.*?)\]", src, re.S):
        if not set(re.findall(r"'(sch_[a-z_]+)'", m.group(2))):
            fail('ROLE_NO_CAPS', act, lineno(src, m.start()), f'الدور {m.group(1)} بلا صلاحية sch_* واحدة')


@check('ROLE_NO_HOME')
def c_role_no_home():
    """دور بلا داشبورد وبلا تطبيق — يدخل ويسقط على «لا تملك صلاحية»."""
    act = read(os.path.join(ROOT, 'includes/class-activator.php'))
    dash = read(os.path.join(ROOT, 'frontend/class-dashboard.php'))
    routed = set(re.findall(r"\['(sch_[a-z_]+)',\s*\[SCH_\w+::class", dash))
    section_caps = set(re.findall(r"^\s*'[a-z0-9\-]+'\s*=>\s*\['[^']*',\s*'(sch_\w+)'", dash, re.M))
    for m in re.finditer(r"'(sch_\w+)'\s*=>\s*\[\s*'label'.*?'caps'\s*=>\s*\[(.*?)\]", act, re.S):
        role, caps = m.group(1), set(re.findall(r"'(sch_[a-z_]+)'", m.group(2)))
        if caps and not (caps & section_caps) and not (caps & routed):
            fail('ROLE_NO_HOME', os.path.join(ROOT, 'includes/class-activator.php'), 0,
                 f'الدور {role} بلا قسم في الداشبورد وبلا تطبيق يُوجَّه إليه')


@check('APP_NONCE_UNKNOWN')
def c_app_nonce_unknown():
    """نونس باسم لا يعرفه أي معالج — النموذج لا يفعل شيئًا بصمت.

    وقع مع ‎sch_login‎ فتعذّر الدخول: كل واجهة لها عقدها
    (‎sch_action‎ · ‎sch_app_action‎ · ‎sch_student_*‎ · ‎sch_teacher_*‎ …).
    """
    verified: set[str] = set()
    for p in php():
        src = read(p)
        verified |= set(re.findall(r"wp_verify_nonce\([^,]+,\s*'([a-z_0-9]+)'", src))
        verified |= set(re.findall(r"check_admin_referer\(\s*'([a-z_0-9]+)'", src))
        # نونس مبني بالتركيب: 'sch_' . $action
        verified |= {'sch_' + a for a in _dashboard_registry()}
        verified |= set(re.findall(r"'(sch_[a-z_0-9]+)'\s*\.\s*\$", src))
    dynamic = bool(re.search(r"wp_verify_nonce\([^,]+,\s*'sch_'\s*\.", ' '.join(
        read(p) for p in php())))
    for p in php():
        src = read(p)
        for m in re.finditer(r"wp_nonce_field\(\s*'([a-z_0-9]+)'", src):
            name = m.group(1)
            if name in verified:
                continue
            if dynamic and name.startswith('sch_'):
                continue
            fail('APP_NONCE_UNKNOWN', p, lineno(src, m.start()),
                 f'النونس «{name}» لا يتحقق منه أي معالج')


# ---------------------------------------------------------------- CSS

@check('CSS_DUP_LAYOUT')
def c_css_dup_layout():
    """صنف واحد يُعرَّف بخصائص تخطيط في أكثر من موضع.

    ‎.scha-track‎ كان ‎display: flex‎ للدوّار و‎position: fixed‎ لشاشة التتبع،
    فاختفت بطاقات الأبناء وبدت كأنها محذوفة. ووقع ثانيةً مع ‎.scha-kid‎.
    """
    LAYOUT = {'display', 'position', 'grid-template-columns', 'grid-template-areas',
              'flex-direction', 'float', 'grid-area', 'inset'}
    seen: dict[str, list[tuple[str, int, set[str]]]] = collections.defaultdict(list)
    for p in css():
        src = strip_css_comments(read(p))
        for m in re.finditer(r'([^{}@]+)\{([^{}]*)\}', src):
            sel = ' '.join(m.group(1).split())
            if not sel or ':root' in sel:
                continue
            props = {d.split(':')[0].strip() for d in m.group(2).split(';') if ':' in d}
            hit = props & LAYOUT
            if not hit:
                continue
            for one in (s.strip() for s in sel.split(',')):
                # الصنف المفرد وحده: المركّبات والحالات تخصيص مقصود
                if re.fullmatch(r'\.[a-z0-9_-]+', one):
                    seen[one].append((p, lineno(src, m.start()), hit))
    for sel, places in sorted(seen.items()):
        if len(places) > 1:
            where = ' · '.join(f'{rel(f)}:{ln}' for f, ln, _ in places)
            props = ', '.join(sorted(set().union(*(h for _, _, h in places))))
            warn('CSS_DUP_LAYOUT', places[0][0], places[0][1],
                 f'{sel} معرَّف {len(places)} مرات بخصائص تخطيط ({props}) — {where}')


@check('CSS_VAR_UNDEFINED')
def c_css_var_undefined():
    """رمز يُستعمل بلا تعريف وبلا قيمة بديلة — لون أو خلفية تختفي بصمت."""
    defined, used = set(), []
    for p in css():
        src = strip_css_comments(read(p))
        defined |= set(re.findall(r'(--[a-z0-9-]+)\s*:', src))
        for m in re.finditer(r'var\(\s*(--[a-z0-9-]+)\s*([,)])', src):
            used.append((p, lineno(src, m.start()), m.group(1), m.group(2)))
    # رموز تُضبط داخل style="" في PHP
    inline = set()
    for p in php():
        inline |= set(re.findall(r'(--[a-z0-9-]+)\s*:', read(p)))
    for p, ln, name, nxt in used:
        if name in defined or name in inline:
            continue
        if nxt == ',':
            continue  # له قيمة بديلة
        fail('CSS_VAR_UNDEFINED', p, ln, f'var({name}) بلا تعريف وبلا بديل')


@check('CSS_FONT_HARDCODED')
def c_css_font_hardcoded():
    """اسم عائلة خط مكتوب مباشرة — عائلتان تتباعدان بين الواجهات.

    القاعدة: عائلة واحدة، ورمزها في ‎shared-ui.css‎ وحده.
    """
    for p in css() + php():
        if rel(p) == 'assets/fonts.css':
            continue
        src = read(p)
        for m in re.finditer(r"font-family\s*:\s*([^;}\n]+)", src):
            val = m.group(1)
            if 'var(' in val or 'inherit' in val or 'system-ui' == val.strip():
                continue
            if re.search(r"['\"][A-Z]", val) and '--sch-font' not in val:
                warn('CSS_FONT_HARDCODED', p, lineno(src, m.start()),
                     f'عائلة مكتوبة مباشرة: {val.strip()[:50]}')


@check('EXTERNAL_HOST')
def c_external_host():
    """**مورد** يُحمَّل من خادم خارجي — يسقط إن رُشِّحت الشبكة، ويُسرّب زيارة.

    خطوط جوجل كانت في اثني عشر موضعًا، وLeaflet من unpkg بلا بديل محلي.
    والرابط الذي **يفتحه المستخدم** (خرائط للسائق، فيديو مضمَّن) ليس مورَدًا:
    لا يُحمَّل مع الصفحة ولا يكسرها إن حُجب.
    """
    ALLOW = ('youtube-nocookie.com', 'player.vimeo.com', 'www.w3.org', 'schema.org')
    RESOURCE = re.compile(
        r'(?:<script[^>]*\bsrc=|<link[^>]*\bhref=|@import\s+|\burl\(|\bfetch\(|'
        r'wp_enqueue_(?:script|style)\([^)]*?)["\']?\s*(https?://[a-z0-9.\-]+\.[a-z]{2,}[^"\'\s)]*)',
        re.I)
    for p in php() + css() + files('.js'):
        src = read(p)
        for m in RESOURCE.finditer(src):
            url = m.group(1)
            if any(a in url for a in ALLOW):
                continue
            host = re.sub(r'^https?://', '', url).split('/')[0]
            if host in ('localhost', 'example.com', 'example.test'):
                continue
            warn('EXTERNAL_HOST', p, lineno(src, m.start()),
                 f'مورد خارجي يُحمَّل من {host}')


@check('SCH_TOKEN_HOME')
def c_sch_token_home():
    """رمز ‎--sch-*‎ معرَّف خارج ‎shared-ui.css‎ — تعريفان بقيمتين يكسران الوضع الداكن.

    ‎parent.css‎ و‎admin.css‎ مستقلّان بنظاميهما (موثّق) فيُستثنيان.
    """
    HOME = 'assets/shared-ui.css'
    # طبقات موثّقة لها نظامها: parent/admin مستقلّان (v5.0)، و dashboard.css
    # يحمل طبقة تخصيص تُحمَّل بعد shared-ui فتحكم صفحة الداشبورد وحدها (v6.0).
    # الفحص يبقى لكشف **ملف جديد** يبدأ بتعريف رموز --sch-* من نفسه.
    INDEPENDENT = ('assets/parent.css', 'assets/admin.css', 'assets/dashboard.css')
    for p in css():
        r = rel(p)
        if r == HOME or r in INDEPENDENT:
            continue
        src = strip_css_comments(read(p))
        for m in re.finditer(r'(--sch-[a-z0-9-]+)\s*:', src):
            warn('SCH_TOKEN_HOME', p, lineno(src, m.start()),
                 f'{m.group(1)} معرَّف خارج {HOME}')


# ---------------------------------------------------------------- النوافذ والوصولية

@check('MODAL_UNBALANCED')
def c_modal_unbalanced():
    """‎open()‎ بلا ‎close()‎ يترك النافذة في DOM فتُخفي ما بعدها."""
    for p in php():
        src = read(p)
        o = len(re.findall(r'SCH_Modal::open\(', src))
        c = len(re.findall(r'SCH_Modal::close\(', src))
        if o != c:
            fail('MODAL_UNBALANCED', p, 0, f'SCH_Modal::open ×{o} مقابل close ×{c}')


@check('MODAL_NO_OPENER')
def c_modal_no_opener():
    """نافذة بلا زر يفتحها = ميزة غير قابلة للوصول."""
    for p in php():
        src = read(p)
        for m in re.finditer(r"SCH_Modal::open\(\s*'([a-z0-9_-]+)'", src):
            mid = m.group(1)
            if f"'{mid}'" not in src.replace(m.group(0), '', 1) and f'"{mid}"' not in src:
                warn('MODAL_NO_OPENER', p, lineno(src, m.start()),
                     f'النافذة «{mid}» بلا زر يفتحها في نفس الملف')


@check('FIELD_UNLABELLED')
def c_field_unlabelled():
    """حقل بلا ‎label‎ ولا ‎aria-label‎ ولا ‎placeholder‎ — لا يعرف أحد ما يُكتب فيه."""
    for p in php():
        src = read(p)
        masked = mask_php(src)
        for m in re.finditer(r'<(input|select|textarea)\b([^>]*)>', masked):
            tag, attrs_masked = m.group(1), m.group(2)
            attrs = src[m.start(2):m.end(2)]  # الأصل بمواضعه
            if re.search(r'type=[\"\'](hidden|submit|button|checkbox|radio|image)[\"\']', attrs):
                continue
            if re.search(r'aria-label|placeholder|aria-labelledby|title=', attrs):
                continue
            ident = re.search(r'\bid=[\"\']([^\"\']+)[\"\']', attrs)
            if ident and f'for="{ident.group(1)}"' in src:
                continue
            # <label> يلفّ الحقل
            before = masked[max(0, m.start() - 400):m.start()]
            if before.count('<label') > before.count('</label>'):
                continue
            warn('FIELD_UNLABELLED', p, lineno(src, m.start()), f'<{tag}> بلا اسم مقروء')


@check('IMG_NO_ALT')
def c_img_no_alt():
    """صورة بلا ‎alt‎."""
    for p in php():
        src = read(p)
        for m in re.finditer(r'<img\b([^>]*)>', mask_php(src)):
            if 'alt=' not in src[m.start(1):m.end(1)]:
                fail('IMG_NO_ALT', p, lineno(src, m.start()), '<img> بلا alt')


@check('PHYSICAL_PROPS')
def c_physical_props():
    """خاصية اتجاهية فيزيائية في واجهة RTL — تنقلب في الاتجاه الآخر.

    القاعدة: الخصائص المنطقية فقط (‎margin-inline-start‎ لا ‎margin-right‎).
    """
    BAD = re.compile(r'(?<![-\w])(margin|padding|border)-(left|right)\s*:'
                     r'|(?<![-\w])text-align\s*:\s*(left|right)\b'
                     r'|(?<![-\w])(left|right)\s*:\s*(?!auto)')
    for p in css():
        src = strip_css_comments(read(p))
        for m in BAD.finditer(src):
            warn('PHYSICAL_PROPS', p, lineno(src, m.start()),
                 f'خاصية فيزيائية: {m.group(0).strip()}')


# ---------------------------------------------------------------- التحميل والاتساق

@check('FILE_NOT_LOADED')
def c_file_not_loaded():
    """ملف صنف لا يُحمَّل من ‎SCH_Loader‎ — أصنافه غير موجودة عند الاستعمال."""
    loader = read(os.path.join(ROOT, 'includes/class-loader.php'))
    entry = read(os.path.join(ROOT, 'school-system.php'))
    for p in php():
        r = rel(p)
        base = os.path.basename(r)
        if not base.startswith('class-'):
            continue
        if r in ('includes/class-loader.php', 'includes/class-activator.php',
                 'admin/class-admin.php'):
            continue
        if r not in loader and r not in entry:
            fail('FILE_NOT_LOADED', p, 0, f'{r} لا يُحمَّل من class-loader.php')


@check('CLASS_UNDEFINED')
def c_class_undefined():
    """صنف ‎SCH_*‎ يُستعمل ولا يُعرَّف في أي ملف."""
    defined, used = set(), []
    for p in php():
        src = read(p)
        defined |= set(re.findall(r'^\s*(?:final\s+)?(?:abstract\s+)?class\s+(SCH_\w+)', src, re.M))
    for p in php():
        src = read(p)
        for m in re.finditer(r'\b(SCH_[A-Za-z]\w*)::', src):
            used.append((p, lineno(src, m.start()), m.group(1)))
    for p, ln, name in used:
        if name not in defined:
            fail('CLASS_UNDEFINED', p, ln, f'{name} غير معرَّف في أي ملف')


@check('FUNC_UNDEFINED')
def c_func_undefined():
    """دالة ‎sch_*‎ تُنادى ولا تُعرَّف."""
    defined, used = set(), []
    for p in php():
        src = read(p)
        defined |= set(re.findall(r'^\s*function\s+(sch_\w+)\s*\(', src, re.M))
    for p in php():
        src = read(p)
        for m in re.finditer(r'(?<![\w:$>])(sch_\w+)\s*\(', src):
            used.append((p, lineno(src, m.start()), m.group(1)))
    for p, ln, name in used:
        if name not in defined:
            fail('FUNC_UNDEFINED', p, ln, f'{name}() غير معرَّفة')


@check('TABLE_UNKNOWN')
def c_table_unknown():
    """جدول يُستعمل ولا يُنشأ — كل عملية عليه تفشل برسالة عامة."""
    created, used = set(), []
    for p in php():
        src = read(p)
        created |= set(re.findall(r'CREATE TABLE \{\$p\}(\w+) \(', src))
    for p in php():
        src = read(p)
        for m in re.finditer(r"sch_table\(\s*'([a-z_0-9]+)'", src):
            used.append((p, lineno(src, m.start()), m.group(1)))
    if not created:
        return
    for p, ln, t in used:
        if t not in created:
            fail('TABLE_UNKNOWN', p, ln, f'الجدول «{t}» يُستعمل ولا يُنشأ في المفعّل')


@check('TABLE_UNUSED')
def c_table_unused():
    """جدول يُنشأ ولا يُستعمل — إما ميزة ناقصة أو بقية محذوفة."""
    created, used = set(), set()
    for p in php():
        src = read(p)
        created |= set(re.findall(r'CREATE TABLE \{\$p\}(\w+) \(', src))
        used |= set(re.findall(r"sch_table\(\s*'([a-z_0-9]+)'", src))
    act = os.path.join(ROOT, 'includes/class-activator.php')
    for t in sorted(created - used):
        warn('TABLE_UNUSED', act, 0, f'الجدول «{t}» يُنشأ ولا يُستعمل في سطر واحد')


@check('VERSION_SYNC')
def c_version_sync():
    """رقم النسخة في الترويسة و‎SCH_VERSION‎ يجب أن يتطابقا.

    ولا تُسلَّم نسخة بلا تحريك الرقم: الأصول موسومة به والترقية تُفرّغ الكاش.
    """
    p = os.path.join(ROOT, 'school-system.php')
    src = read(p)
    head = re.search(r'^\s*\*\s*Version:\s*([0-9.]+)', src, re.M)
    const = re.search(r"define\(\s*'SCH_VERSION'\s*,\s*'([0-9.]+)'", src)
    if head and const and head.group(1) != const.group(1):
        fail('VERSION_SYNC', p, lineno(src, const.start()),
             f'الترويسة {head.group(1)} و SCH_VERSION {const.group(1)}')


@check('LIST_SHAPE')
def c_list_shape():
    """‎list()‎ التي ترجع ‎['items'=>…,'total'=>…]‎ تُستعمل كقائمة مسطّحة.

    وقع في شاشة الصلاحيات: ‎foreach‎ على المُغلِّف فقُرئت خاصية على مصفوفة
    ثم على عدد — تحذيران في كل فتح للشاشة.
    """
    wrapped = set()
    for p in php():
        src = read(p)
        for m in re.finditer(r'class\s+(SCH_\w+)', src):
            tail = src[m.end():]
            body = re.search(r'function list\(.*?\n    \}', tail, re.S)
            if body and "return ['items'" in body.group(0):
                wrapped.add(m.group(1))
    if not wrapped:
        return
    for p in php():
        src = read(p)
        for m in re.finditer(r'\$(\w+)\s*=\s*(SCH_\w+)::list\((?:[^()]|\([^()]*\))*\)\s*;', src):
            var, cls = m.group(1), m.group(2)
            if cls not in wrapped:
                continue
            after = src[m.end():]
            if f"${var}['items']" in after or f"${var}['total']" in after:
                continue
            if re.search(r'foreach\s*\(\s*\$' + var + r'\s+as\b', after) or \
               re.search(r'count\(\s*\$' + var + r'\s*\)', after):
                fail('LIST_SHAPE', p, lineno(src, m.start()),
                     f'${var} = {cls}::list() يُستعمل مسطَّحًا وهو ["items","total"]')


@check('MONEY_RAW')
def c_money_raw():
    """مبلغ بـ‎number_format‎ مباشرة في واجهة — القاعدة: ‎sch_money()‎ وحدها."""
    for p in php():
        if '/views/' not in rel(p) and '/app/' not in rel(p):
            continue
        src = read(p)
        for m in re.finditer(r'(?<!_i18n)\bnumber_format\s*\(', src):
            # number_format_i18n() للأعداد لا للمبالغ — مسموحة
            if src[max(0, m.start() - 5):m.start() + 20].find('number_format_i18n') != -1:
                continue
            # النسبة والعدد ليسا مبلغًا: صفر منازل عشرية، أو ٪ في نفس السطر
            ls = src.rfind('\n', 0, m.start()) + 1
            line = src[ls:src.find('\n', m.start())]
            if '٪' in line or '%' in line or re.search(r'number_format\([^,)]+,\s*0\s*\)', line):
                continue
            warn('MONEY_RAW', p, lineno(src, m.start()),
                 'number_format() مباشرة — استعمل sch_money()')


# ---------------------------------------------------------------- التشغيل

def main() -> int:
    ap = argparse.ArgumentParser(add_help=True, description='الفحص الآلي لنظام المدرسة')
    ap.add_argument('--list', action='store_true', help='اطبع أسماء الفحوص واخرج')
    ap.add_argument('-o', '--only', action='append', metavar='NAME', help='شغّل فحصًا واحدًا')
    ap.add_argument('--strict', action='store_true', help='التحذير يُفشِل أيضًا')
    ap.add_argument('--quiet', action='store_true', help='الملخص وحده')
    args = ap.parse_args()

    if args.list:
        for n in sorted(CHECKS):
            doc = (CHECKS[n].__doc__ or '').strip().split('\n')[0]
            print(f'  {n:24} {doc}')
        return 0

    names = args.only or sorted(CHECKS)
    unknown = [n for n in names if n not in CHECKS]
    if unknown:
        print(f'فحص غير معروف: {", ".join(unknown)}', file=sys.stderr)
        return 2

    for n in names:
        try:
            CHECKS[n]()
        except Exception as exc:  # فحص ينكسر لا يُسكت الباقي
            FINDINGS.append(Finding(n, 'error', '-', 0, f'الفحص نفسه انكسر: {exc!r}'))

    errors = [f for f in FINDINGS if f.level == 'error']
    warns = [f for f in FINDINGS if f.level == 'warn']

    if not args.quiet:
        for level, group, colour, label in (('error', errors, RED, 'خطأ'), ('warn', warns, YEL, 'تحذير')):
            if not group:
                continue
            print(f'\n{BOLD}{colour}{label} ({len(group)}){RESET}')
            for f in sorted(group, key=lambda x: (x.check, x.file, x.line)):
                where = f'{f.file}:{f.line}' if f.line else f.file
                print(f'  {colour}{f.check:22}{RESET} {DIM}{where}{RESET}\n      {f.msg}')

    print()
    if errors:
        print(f'{RED}{BOLD}✗ {len(errors)} خطأ{RESET} · {YEL}{len(warns)} تحذير{RESET} '
              f'· {len(names)} فحصًا')
        return 1
    if warns and args.strict:
        print(f'{YEL}{BOLD}✗ {len(warns)} تحذير{RESET} (‎--strict‎) · {len(names)} فحصًا')
        return 1
    print(f'{GRN}{BOLD}✓ نظيف{RESET} · {YEL}{len(warns)} تحذير{RESET} · {len(names)} فحصًا')
    return 0


if __name__ == '__main__':
    sys.exit(main())
