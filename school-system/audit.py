#!/usr/bin/env python3
"""فحص ثابت شامل لإضافة School System — يكشف ما يمكن كشفه بلا تشغيل PHP."""

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
        if '/node_modules' in root:
            continue
        for n in sorted(files):
            if n.endswith('.php'):
                yield os.path.join(root, n)


sources = {p: open(p, encoding='utf-8').read() for p in php_files()}


# ---------- 1) توازن الأقواس وعلامات PHP ----------
def strip_noise(code):
    """يزيل النصوص ثم التعليقات — الأقواس داخلها ليست بنية كود.

    الترتيب مهم: لو أزلنا التعليقات أولًا، فإن '#0F1720' داخل نص
    سيُقرأ كبداية تعليق فتضيع بقية السطر.
    """
    code = re.sub(r"<<<'?(\w+)'?\n.*?\n\1;", '""', code, flags=re.S)   # heredoc / nowdoc
    code = re.sub(r"'(?:\\.|[^'\\])*'", "''", code)
    code = re.sub(r'"(?:\\.|[^"\\])*"', '""', code)
    code = re.sub(r'/\*.*?\*/', '', code, flags=re.S)
    code = re.sub(r'//[^\n]*', '', code)
    return code


for path, raw in sources.items():
    rel = os.path.relpath(path, ROOT)
    src = strip_noise(raw)
    if src.count('{') != src.count('}'):
        add('BRACES', f'{rel}: {{ = {src.count("{")} , }} = {src.count("}")}')
    if src.count('(') != src.count(')'):
        add('PARENS', f'{rel}: ( = {src.count("(")} , ) = {src.count(")")}')
    if not raw.lstrip().startswith('<?php'):
        add('OPEN_TAG', rel)


# ---------- 2) الأصناف: التعريف والاستخدام ----------
class_defs = {}   # name -> path
class_methods = defaultdict(set)
class_consts = defaultdict(set)

for path, src in sources.items():
    for m in re.finditer(r'^\s*(?:final\s+)?class\s+(\w+)', src, re.M):
        name = m.group(1)
        class_defs[name] = path
        body = src[m.end():]
        for mm in re.finditer(r'function\s+(\w+)\s*\(', body):
            class_methods[name].add(mm.group(1))
        for cc in re.finditer(r'^\s*(?:public\s+)?const\s+(\w+)', body, re.M):
            class_consts[name].add(cc.group(1))

for path, src in sources.items():
    rel = os.path.relpath(path, ROOT)
    for m in re.finditer(r'\b(SCH_\w+)::(\w+)', src):
        cls, member = m.group(1), m.group(2)
        if cls not in class_defs:
            add('CLASS_MISSING', f'{rel}: {cls}')
            continue
        if member != 'class' and member not in class_methods[cls] and member not in class_consts[cls]:
            add('MEMBER_MISSING', f'{rel}: {cls}::{member}')


# ---------- 3) الدوال العامة ----------
func_defs = set()
for src in sources.values():
    for m in re.finditer(r'^function\s+(sch_\w+)\s*\(', src, re.M):
        func_defs.add(m.group(1))

for path, src in sources.items():
    rel = os.path.relpath(path, ROOT)
    for m in re.finditer(r'(?<![\w:>$])(sch_\w+)\s*\(', src):
        fn = m.group(1)
        if fn not in func_defs and fn not in {'sch_now', 'sch_table'}:
            add('FUNC_MISSING', f'{rel}: {fn}()')


# ---------- 4) الأقسام ↔ ملفات العرض ----------
dash = sources[os.path.join(ROOT, 'frontend', 'class-dashboard.php')]
sections_block = re.search(r'private const SECTIONS = \[(.*?)\n    \];', dash, re.S).group(1)
sections = re.findall(r"'([\w]*)'\s*=>\s*\[", sections_block)

views_dir = os.path.join(ROOT, 'frontend', 'views')
views = {f[:-4] for f in os.listdir(views_dir) if f.endswith('.php')}

for s in sections:
    if s and s not in views:
        add('VIEW_MISSING', f'القسم "{s}" بلا ملف عرض frontend/views/{s}.php')


app_src = sources[os.path.join(ROOT, 'frontend', 'class-app.php')]
app_sections = re.findall(r"'([\w]*)'", re.search(r'private const SECTIONS = \[(.*?)\];', app_src, re.S).group(1))
app_views = {f[:-4] for f in os.listdir(os.path.join(ROOT, 'frontend', 'app')) if f.endswith('.php')}
for s in app_sections:
    name = 'home' if s == '' else s
    if name not in app_views:
        add('APP_VIEW_MISSING', f'قسم التطبيق "{s}" بلا ملف frontend/app/{name}.php')


drv_views = {f[:-4] for f in os.listdir(os.path.join(ROOT, 'frontend', 'driver')) if f.endswith('.php')}
for need in ('layout', 'login', 'home', 'trip'):
    if need not in drv_views:
        add('DRIVER_VIEW_MISSING', f'frontend/driver/{need}.php')


# ---------- 5) الجداول ----------
activator = sources[os.path.join(ROOT, 'includes', 'class-activator.php')]
created = set(re.findall(r'CREATE TABLE \{\$p\}(\w+)', activator))

used_tables = set()
for src in sources.values():
    used_tables |= set(re.findall(r"sch_table\('(\w+)'\)", src))

for t in sorted(used_tables - created):
    add('TABLE_MISSING', t)


# ---------- 6) الأفعال: النونس ↔ المعالج ----------
actions_block = re.search(r'private static function actions\(\): array\s*\{(.*?)\n    \}', dash, re.S).group(1)
registered = dict(re.findall(r"'(\w+)'\s*=>\s*\['(\w+)',\s*'(\w+)'\]", actions_block) and
                  [(a, h) for a, _c, h in re.findall(r"'(\w+)'\s*=>\s*\['(\w+)',\s*'(\w+)'\]", actions_block)])

dash_methods = set(re.findall(r'private static function (do_\w+)', dash))
for action, handler in registered.items():
    if handler not in dash_methods:
        add('HANDLER_MISSING', f'{action} → {handler}()')

for path, src in sources.items():
    rel = os.path.relpath(path, ROOT)
    for m in re.finditer(r"name=\"sch_action\" value=\"(\w+)\"", src):
        if m.group(1) not in registered:
            add('ACTION_UNREGISTERED', f'{rel}: {m.group(1)}')

# كل نموذج فيه sch_action لازم نونس بنفس الاسم
for path, src in sources.items():
    rel = os.path.relpath(path, ROOT)
    forms = re.findall(r'<form[^>]*method="post".*?</form>', src, re.S)
    for form in forms:
        act = re.search(r'name="sch_action" value="(\w+)"', form)
        non = re.search(r"wp_nonce_field\('sch_(\w+)'", form)
        if act and not non:
            add('NONCE_MISSING', f'{rel}: {act.group(1)}')
        elif act and non and act.group(1) != non.group(1):
            add('NONCE_MISMATCH', f'{rel}: action={act.group(1)} nonce=sch_{non.group(1)}')


# ---------- 7) الصلاحيات ----------
caps_registered = set(re.findall(r"'(sch_[a-z_]+)'", activator))
caps_used = set()
for src in sources.values():
    caps_used |= set(re.findall(r"current_user_can\('(sch_\w+)'\)", src))
caps_used |= set(re.findall(r"=>\s*\['([\w]+)',\s*'\w+'\]", actions_block))
caps_used |= set(re.findall(r"'\w*'\s*=>\s*\['[^']*',\s*'(sch_\w+)'", sections_block))

for c in sorted(caps_used - caps_registered):
    if c not in {'read'}:
        add('CAP_UNREGISTERED', c)


# ---------- 8) الملفات المُحمّلة ----------
loader = sources[os.path.join(ROOT, 'includes', 'class-loader.php')]
main = sources[os.path.join(ROOT, 'school-system.php')]
for cls, path in sorted(class_defs.items()):
    rel = os.path.relpath(path, ROOT).replace(os.sep, '/')
    if rel.startswith(('admin/', 'includes/')):
        continue
    if rel not in loader:
        add('NOT_LOADED', f'{cls} في {rel}')


# ---------- 9) تهريب المخرجات في القوالب ----------
for path, src in sources.items():
    rel = os.path.relpath(path, ROOT)
    rel_slash = rel.replace(os.sep, '/')
    if '/views/' not in rel_slash and not rel_slash.startswith(('frontend/app/', 'frontend/driver/')):
        continue
    for m in re.finditer(r'<\?php\s+echo\s+([^;]{1,200});\s*(?://[^\n]*)?\?>', src):
        frag = m.group(1).strip()
        if frag.startswith(("esc_", "sch_icon", "sch_avatar_svg", "sch_money_html", "sch_attendance_calendar", "sch_wave_pill", "SCH_QR::svg", "SCH_Certificates::svg", "SCH_Certificates::preview_svg", "$qr", "wp_", "'", '"')):
            continue
        # التعبير الشرطي آمن إذا كان كل فرع منه نصًا حرفيًا أو مهرَّبًا
        branches = re.split(r'\?|:', frag.split('?', 1)[1]) if '?' in frag else [frag]
        if all(b.strip().startswith(("'", '"', 'esc_', 'sch_icon')) or b.strip() == '' for b in branches):
            continue
        add('UNESCAPED', f'{rel}: echo {frag[:60]}')


# ---------- 10) عناصر prepare النائبة ----------
def split_args(text):
    depth, cur, out, quote = 0, '', [], None
    i = 0
    while i < len(text):
        ch = text[i]
        if quote:
            cur += ch
            if ch == '\\':
                cur += text[i + 1] if i + 1 < len(text) else ''
                i += 2
                continue
            if ch == quote:
                quote = None
        elif ch in "'\"":
            quote = ch
            cur += ch
        elif ch in '([':
            depth += 1
            cur += ch
        elif ch in ')]':
            depth -= 1
            cur += ch
        elif ch == ',' and depth == 0:
            out.append(cur)
            cur = ''
        else:
            cur += ch
        i += 1
    if cur.strip():
        out.append(cur)
    return out


for path, src in sources.items():
    rel = os.path.relpath(path, ROOT)
    for m in re.finditer(r'\$wpdb->prepare\(', src):
        start = m.end()
        depth, i, quote = 1, start, None
        while i < len(src) and depth > 0:
            ch = src[i]
            if quote:
                if ch == '\\':
                    i += 2
                    continue
                if ch == quote:
                    quote = None
            elif ch in "'\"":
                quote = ch
            elif ch == '(':
                depth += 1
            elif ch == ')':
                depth -= 1
            i += 1
        inner = src[start:i - 1]
        args = split_args(inner)
        if not args:
            continue
        sql = args[0].strip()

        # استعلام مخزَّن في متغير لا يمكن تحليله ثابتًا — نتخطاه بدل الإبلاغ الكاذب
        if re.fullmatch(r'\$\w+', sql):
            continue

        holders = len(re.findall(r'(?<!%)%[dsfF]', sql))
        supplied = len(args) - 1
        if supplied and '...' in inner:
            continue
        if holders != supplied:
            snippet = re.sub(r'\s+', ' ', sql.strip())[:60]
            add('PREPARE_ARGS', f'{rel}: %{holders} مقابل {supplied} وسيط — {snippet}')


# ---------- التقرير ----------

# ---------- الوصولية والسلامة البصرية ----------
# ما لا يمسكه فحص البنية: حقل بلا تسمية، وسم مكسور، زر أيقونة بلا اسم.
# هذه ليست تفاصيل — هي ما يجعل الموظف يشتكي أو لا يشتكي.

PHP_TAG = re.compile(r'<\?php.*?\?>', re.S)


def mask_php(src):
    """نُقنّع وسوم PHP بمسافات: المواضع تبقى صحيحة والمحتوى لا يخدع التعبير النمطي."""
    return PHP_TAG.sub(lambda m: ' ' * len(m.group(0)), src)


for path in glob.glob(os.path.join(ROOT, 'frontend', '**', '*.php'), recursive=True) + \
            glob.glob(os.path.join(ROOT, 'admin', '**', '*.php'), recursive=True):
    rel = os.path.relpath(path, ROOT)
    src = open(path, encoding='utf-8').read()
    masked = mask_php(src)

    # وسم لا يُغلق قبل الوسم التالي — علامة تعديل نصّي أفسد الملف.
    # وقع هذا مع <body> حين أُدرج رابط التخطي داخله بدل بعده.
    for m in re.finditer(r'<(body|html|head|input|select|textarea|button|a|form|link|meta)\b', masked):
        close = masked.find('>', m.start())
        nxt = masked.find('<', m.start() + 1)

        if close == -1:
            add('TAG_BROKEN', f'{rel}: <{m.group(1)}> بلا إغلاق')
        elif nxt != -1 and nxt < close and '<!' not in src[m.start():close]:
            add('TAG_BROKEN', f'{rel}: <{m.group(1)}> يُفتح ولا يُغلق قبل الوسم التالي')

    # حقل إدخال بلا أي تسمية: لا label ولا aria-label ولا placeholder
    labelled = set(re.findall(r'<label[^>]*for="([^"]+)"', masked))
    for m in re.finditer(r'<(input|select|textarea)\b([^>]*)>', masked):
        a = m.group(2)
        if re.search(r'type="(hidden|submit|checkbox|radio)"', a):
            continue
        if 'aria-label' in a or 'placeholder' in a:
            continue
        idm = re.search(r'id="([^"]*)"', a)
        if idm and idm.group(1).strip() and idm.group(1) in labelled:
            continue
        add('FIELD_UNLABELLED', f'{rel}: <{m.group(1)}>')

    # نموذج POST بلا نونس
    for m in re.finditer(r'<form[^>]*method="post"[^>]*>(.{0,1200}?)</form>', src, re.S):
        if 'wp_nonce_field' not in m.group(1) and 'rest_url' not in m.group(0):
            add('FORM_NO_NONCE', rel)


# ---------- كتلة HTML داخل فرع شرطي بالخطأ ----------
# إدراج كتلة بين `if` و`elseif` يجعلها تظهر في حالة واحدة فقط.
# وقع هذا مع البوابات الأربع: أُدرجت داخل فرع «في إجازة» فلم تظهر أبدًا.

for path in glob.glob(os.path.join(ROOT, 'frontend', '**', '*.php'), recursive=True):
    rel = os.path.relpath(path, ROOT)
    src = open(path, encoding='utf-8').read()

    for m in re.finditer(r'<\?php if \([^)]*\) : \?>(.*?)<\?php (?:elseif|else)', src, re.S):
        block = m.group(1)

        # وسم مفتوح على مستوى أعلى داخل الفرع يعني كتلة كاملة لا سطرًا
        if re.search(r'<div class="scha-(quad|two|short)"', block):
            add('BLOCK_IN_BRANCH', f'{rel}: كتلة رئيسية داخل فرع شرطي — تظهر في حالة واحدة فقط')


# ---------- عقد الأفعال في التطبيقات ----------
# لكل واجهة عقدها: الداشبورد `sch_action`، وتطبيق ولي الأمر `sch_app_action`
# مع `sch_app_login`. اسم مخترَع لا يعرفه المعالج يجعل النموذج لا يفعل شيئًا
# بصمت — وقع هذا مع `sch_login` فتعذّر الدخول.

_APP_CONTRACTS = {
    'frontend/app':     ('frontend/class-app.php',     ['sch_app_action', 'sch_app_login']),
    'frontend/student': ('frontend/class-student.php', ['sch_student_action', 'sch_student_login']),
    'frontend/teacher': ('frontend/class-teacher.php', ['sch_teacher_action', 'sch_teacher_login']),
    'frontend/driver':  ('frontend/class-driver.php',  ['sch_driver_action', 'sch_driver_login']),
    'frontend/gate':    ('frontend/class-gate.php',    ['sch_gate_action', 'sch_gate_login']),
}

for _dir, (_cls, _allowed) in _APP_CONTRACTS.items():
    _cls_path = os.path.join(ROOT, _cls)
    if not os.path.exists(_cls_path):
        continue

    _handler = open(_cls_path, encoding='utf-8').read()

    for _path in glob.glob(os.path.join(ROOT, _dir, '*.php')):
        _rel = os.path.relpath(_path, ROOT)
        _src = open(_path, encoding='utf-8').read()

        for _m in re.finditer(r'name="(sch_[a-z_]+)"', _src):
            _field = _m.group(1)

            if _field in _allowed:
                continue

            # حقل إرسال يبدأ بـsch_ ولا يعرفه المعالج
            if _field.startswith('sch_') and f"'{_field}'" not in _handler and f'"{_field}"' not in _handler:
                add('APP_FIELD_UNKNOWN', f'{_rel}: الحقل «{_field}» لا يعرفه {os.path.basename(_cls)}')

        for _m in re.finditer(r"wp_nonce_field\('([a-z_]+)'", _src):
            if f"'{_m.group(1)}'" not in _handler:
                add('APP_NONCE_UNKNOWN', f'{_rel}: النونس «{_m.group(1)}» لا يُتحقَّق منه في {os.path.basename(_cls)}')


# ---------- كل دور له وجهة ----------
# دور بلا مجال في الداشبورد وبلا تطبيق يسقط على 403 بعد الدخول.
# وقع هذا مع حارس البوابة: التوجيه كان يعرف وليّ الأمر والسائق وحدهما.

_act_src = open(os.path.join(ROOT, 'includes', 'class-activator.php'), encoding='utf-8').read()
_dash_src = open(os.path.join(ROOT, 'frontend', 'class-dashboard.php'), encoding='utf-8').read()

_APP_CAPS = ['sch_scan_gate', 'sch_drive_trip', 'sch_student_app',
             'sch_view_own_children', 'sch_manage_attendance']

_section_caps = set(re.findall(r"^\s{8}'[a-z-]+'\s*=> \['[^']*',\s*'(\w+)'", _dash_src, re.M))

for _m in re.finditer(r"'(sch_\w+)' => \[\s*'label' => __\('([^']+)'.*?'caps'\s*=> \[(.*?)\],", _act_src, re.S):
    _role, _label = _m.group(1), _m.group(2)
    _caps = re.findall(r"'(\w+)'", _m.group(3))

    if not _caps:
        add('ROLE_NO_CAPS', f'{_label} ({_role}) بلا صلاحيات')
        continue

    _has_dash = any(_c in _section_caps for _c in _caps)
    _has_app  = any(_c in _APP_CAPS for _c in _caps)

    if not _has_dash and not _has_app:
        add('ROLE_NO_HOME', f'{_label} ({_role}) بلا داشبورد وبلا تطبيق — يسقط على 403')

    # الصلاحية تفتح تطبيقًا لكن كتلة التوجيه لا تذكرها — فيسقط صاحبها على 403
    _route_block = re.search(r'من لا داشبورد له.*?\n\s*\}', _dash_src, re.S)
    _routes = _route_block.group(0) if _route_block else ''

    for _c in _caps:
        if _c in _APP_CAPS and not _has_dash and f"'{_c}'" not in _routes:
            add('ROLE_NO_ROUTE', f'{_label}: التوجيه لا يعرف {_c} — يسقط على 403')


# ---------- رابط يشير لشاشة غير موجودة ----------
# زر يبني رابطًا بـ`add_query_arg('edit', …)` بينما لا شاشة تقرأ `$_GET['edit']`
# يبدو عاملًا ولا يفعل شيئًا. وقع هذا مع زر تعديل الموظف.

_LINK_RE = re.compile(r"add_query_arg\('(\w+)',.{0,90}?SCH_Dashboard::url\('([a-z-]+)'", re.S)

for path in glob.glob(os.path.join(ROOT, 'frontend', 'views', '*.php')):
    rel = os.path.relpath(path, ROOT)
    src = open(path, encoding='utf-8').read()

    for m in _LINK_RE.finditer(src):
        _param, _target = m.group(1), m.group(2)

        if _param in ('user_id', 'class_id', 'student_id', 'inv', 'view', 'unprinted', 't', 'st'):
            continue

        _target_file = os.path.join(ROOT, 'frontend', 'views', _target + '.php')

        if not os.path.exists(_target_file):
            continue

        _target_src = open(_target_file, encoding='utf-8').read()

        # المعاملات التي يخدمها الموجّه (ملفات وصور) تُلتقط قبل القالب
        _router = open(os.path.join(ROOT, 'frontend', 'class-dashboard.php'), encoding='utf-8').read()
        _served = re.findall(r"isset\(\$_GET\['(\w+)'\]\)", _router)

        if f"$_GET['{_param}']" in _target_src or _param in _served:
            continue

        add('LINK_DEAD_PARAM', f'{rel}: رابط بـ«{_param}» إلى {_target} — لا القالب ولا الموجّه يقرأه')


# ---------- توازن بنية التحكّم في القوالب ----------
# `if (…) :` بلا `endif` يوقف PHP بخطأ فادح تظهر معه صفحة بيضاء.
# وقع هذا حين تركت عملية استبدال شرطًا مكررًا في شاشة الطلاب.
# نفحص كل وسم PHP على حدة: الصيغة البديلة (`:`) وحدها تُحسب،
# فالقوس المعقوف يُغلق نفسه ولا يحتاج `endif`.

for path in glob.glob(os.path.join(ROOT, 'frontend', '**', '*.php'), recursive=True):
    rel = os.path.relpath(path, ROOT)

    if os.path.basename(path) == 'layout.php':
        continue

    src = open(path, encoding='utf-8').read()
    counts = {}

    for _blk in re.findall(r'<\?php(.*?)\?>', src, re.S):
        _b = re.sub(r"'(?:[^'\\]|\\.)*'", "''", _blk)
        _b = re.sub(r'"(?:[^"\\]|\\.)*"', '""', _b)
        _b = re.sub(r'//[^\n]*', '', _b)

        # وسم ينتهي بـ`:` بلا قوس معقوف = صيغة بديلة تحتاج end*
        for _kw in ('if', 'foreach', 'for', 'while', 'switch'):
            for _m in re.finditer(
                r'(?<![a-z_])' + _kw + r'\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)\s*(:|\{)', _b
            ):
                if _m.group(1) == ':':
                    counts[_kw] = counts.get(_kw, 0) + 1

        for _kw in ('if', 'foreach', 'for', 'while', 'switch'):
            _pat = r'\bend' + _kw + (r'\b(?!each)' if _kw == 'for' else r'\b')
            counts[_kw] = counts.get(_kw, 0) - len(re.findall(_pat, _b))

    for _kw, _n in counts.items():
        if _n != 0:
            add('TPL_UNBALANCED', f'{rel}: {_kw} غير متوازن ({_n:+d})')

_MODAL_BTN = re.compile(r"SCH_Modal::(?:button|head)\((.*?)\);", re.S)
_MODAL_ID = re.compile(r"'([\w-]+)'")

# ---------- سلامة النوافذ ----------
# `open()` بلا `close()` يترك النافذة مفتوحة في DOM فتُخفي ما بعدها.

for path in glob.glob(os.path.join(ROOT, 'frontend', 'views', '*.php')):
    rel = os.path.relpath(path, ROOT)
    src = open(path, encoding='utf-8').read()

    _o = src.count('SCH_Modal::open')
    _c = src.count('SCH_Modal::close')

    if _o != _c:
        add('MODAL_UNBALANCED', f'{rel}: open {_o} / close {_c}')

    # زر بلا نافذة، أو نافذة بلا زر يفتحها
    _btns = set(_MODAL_ID.findall(' '.join(_MODAL_BTN.findall(src))))
    _mods = set(re.findall(r"SCH_Modal::open\('([\w-]+)'", src))

    for _m in _mods - _btns:
        add('MODAL_NO_OPENER', f'{rel}: النافذة «{_m}» بلا زر يفتحها')


# ---------- أولوية لون الروابط ----------
# `.sch-body a { color }` أولويتها (صنف + عنصر) تغلب أي صنف وحده مهما جاء
# بعدها — فالأولوية تسبق ترتيب المصدر. وقع هذا ثلاث مرات: مع الأزرار،
# ثم شريط التنقّل، ثم الشريحة الثانية — والنتيجة نص بلون الهوية حيث لا يُقرأ.

_A_COLOR_SELS = []

for _css in ('assets/dashboard.css', 'assets/parent.css', 'assets/shared-ui.css'):
    _path = os.path.join(ROOT, _css)

    if not os.path.exists(_path):
        continue

    _src = open(_path, encoding='utf-8').read()

    # هل في الملف قاعدة عامة تلوّن الروابط؟
    if not re.search(r'\.\w[\w-]*\s+a\s*\{[^}]*\bcolor\s*:', _src):
        continue

    # كل قاعدة بصنف واحد تضبط `color` على عنصر يُستعمل كرابط
    for _m in re.finditer(r'^\.([\w-]+)(?:[:.][\w-]+)*\s*\{([^}]*)\}', _src, re.M | re.S):
        _cls, _body = _m.group(1), _m.group(2)

        if not re.search(r'(?<!-)\bcolor\s*:', _body):
            continue

        # هل هذا الصنف يُستعمل على وسم <a> في القوالب؟
        for _tpl in glob.glob(os.path.join(ROOT, 'frontend', '**', '*.php'), recursive=True):
            _t = open(_tpl, encoding='utf-8').read()

            if not re.search(r'<a[^>]{0,200}class="[^"]*\b' + re.escape(_cls) + r'\b', _t):
                continue

            # مُصلَح إن وُجدت قاعدة أقوى **بنفس اللواحق** — لا أي قاعدة للصنف،
            # فقاعدة `:hover` المُصلَحة لا تُصلح القاعدة الأساسية.
            _suffix = re.escape(_m.group(0)[len('.') + len(_cls):_m.group(0).find('{')].strip())

            if re.search(
                r'\.\w[\w-]*\s+a\.' + re.escape(_cls) + _suffix + r'\s*[,{][^{]*\{[^}]*\bcolor\s*:',
                _src, re.S
            ):
                break

            add('LINK_COLOR_WEAK', f'{_css}: «.{_cls}» تلوّن رابطًا بأولوية أضعف من قاعدة الروابط — اكتبها `.sch-body a.{_cls}`')
            break


# ---------- مخطط قاعدة البيانات ----------
# عمود مكرر في CREATE TABLE يجعل MySQL يرفض الجدول كله، فلا يُنشأ أصلًا،
# فتفشل كل عملية عليه برسالة عامة «تعذّر الحفظ» لا تدل على السبب.
# وقع هذا في جدول الطلاب: user_id معرَّف مرتين، فلم يُسجَّل طالب واحد.

_act = open(os.path.join(ROOT, 'includes', 'class-activator.php'), encoding='utf-8').read()

for m in re.finditer(r'CREATE TABLE \{\$p\}(\w+) \((.*?)\) \{\$charset\}', _act, re.S):
    _tbl, _body = m.group(1), m.group(2)

    _cols = [c for c in re.findall(r'^\s+(\w+)\s+[A-Z]', _body, re.M)
             if c.upper() not in ('PRIMARY', 'UNIQUE', 'KEY', 'INDEX', 'CONSTRAINT', 'FOREIGN')]

    for _c in set(_cols):
        if _cols.count(_c) > 1:
            add('SCHEMA_DUP_COLUMN', f'{_tbl}: العمود «{_c}» معرَّف {_cols.count(_c)} مرات')

    _keys = re.findall(r'(?:UNIQUE )?KEY (\w+)', _body)
    for _k in set(_keys):
        if _keys.count(_k) > 1:
            add('SCHEMA_DUP_KEY', f'{_tbl}: المفتاح «{_k}» مكرر')

    # عمود في مفتاح وغير معرَّف
    for _km in re.finditer(r'KEY \w+ \(([^)]+)\)', _body):
        for _kc in [x.strip().strip('`') for x in _km.group(1).split(',')]:
            _kc = re.sub(r'\(\d+\)$', '', _kc)
            if _kc and _kc not in _cols:
                add('SCHEMA_KEY_ORPHAN', f'{_tbl}: مفتاح على عمود غير موجود «{_kc}»')


# ---------- كتل CSS مكررة حرفيًا ----------
# تعديل نصّي يطبَّق على كل التطابقات بدل الأول يكرّر الكتلة ويُسقط شروطها.
# وقع هذا فتكرّر عمود الشريط الجانبي وسقط شرط الاتجاه، فظهر عمود أسود
# في الجهة الخاطئة من الشاشة.

for path in glob.glob(os.path.join(ROOT, 'assets', '*.css')):
    rel = os.path.relpath(path, ROOT)
    src = re.sub(r'/\*.*?\*/', '', open(path, encoding='utf-8').read(), flags=re.S)

    blocks = {}
    # إطارات الحركة ليست محددات: from/to تتكرر بطبيعتها في كل حركة
    src = re.sub(r'@keyframes[^{]*\{(?:[^{}]|\{[^{}]*\})*\}', '', src, flags=re.S)

    for m in re.finditer(r'(?:^|\})\s*([^{}@]{3,120}?)\s*\{([^{}]{20,})\}', src, re.M | re.S):
        sel = ' '.join(m.group(1).split())
        body = ' '.join(m.group(2).split())

        if sel in ('from', 'to') or re.fullmatch(r'\d+%', sel):
            continue

        key = sel + '|' + body
        blocks[key] = blocks.get(key, 0) + 1

    for key, n in blocks.items():
        if n > 1:
            add('CSS_DUPE', f'{rel}: «{key.split("|")[0]}» مكررة حرفيًا {n} مرات')


# ---------- تصادم أسماء الأصناف في CSS ----------
# صنف واحد يُعرَّف مرتين بخصائص تخطيط متعارضة يطمس أحدهما الآخر بصمت:
# الكود يبدو سليمًا والبطاقة تنهار في الشاشة. وقع هذا مع .scha-kid
# فانهار دوّار تبديل الأبناء وبدا كأنه محذوف.

LAYOUT_PROPS = {'display', 'flex-direction', 'position', 'width', 'height',
                'grid-template-columns', 'flex'}

css_layout = {}   # rel => {sel: props}

for path in glob.glob(os.path.join(ROOT, 'assets', '*.css')):
    rel = os.path.relpath(path, ROOT)
    src = re.sub(r'/\*.*?\*/', '', open(path, encoding='utf-8').read(), flags=re.S)

    # نحذف كتل @media بمحتواها حتى لا تُحسب تعريفاتها تصادمًا
    src = re.sub(r'@media[^{]*\{(?:[^{}]|\{[^{}]*\})*\}', '', src, flags=re.S)

    seen = {}

    for m in re.finditer(r'(^|\})\s*([^{}@]+?)\s*\{([^{}]*)\}', src, re.M | re.S):
        sel, body = m.group(2).strip(), m.group(3)

        if not re.fullmatch(r'\.[A-Za-z0-9_-]+', sel):
            continue

        props = {p.split(':')[0].strip() for p in body.split(';') if ':' in p} & LAYOUT_PROPS
        if not props:
            continue

        # أي تعريفين لنفس الصنف يحملان خصائص تخطيط يتصادمان — ولو اختلفت الخاصية.
        # .scha-track كان «display: flex» في موضع و«position: fixed» في آخر،
        # فتحوّل شريط الأبناء إلى طبقة بلا ارتفاع واختفت البطاقات.
        if sel in seen:
            add('CSS_CLASH', f'{rel}: {sel} معرَّف مرتين بخصائص تخطيط '
                             f'({", ".join(sorted(seen[sel]))} ثم {", ".join(sorted(props))})')

        seen[sel] = seen.get(sel, set()) | props

    css_layout[rel] = seen


# ---------- اسم مكوّن مسروق من الأساس المشترك ----------
# الفحص أعلاه يفحص كل ملف وحده، فملفان يُحمَّلان في الصفحة نفسها يمرّان نظيفَين.
# وقع هذا مع «.sch-rail»: اسم «السكة» في shared-ui.css استُعمل ثانيةً لزرّ طيّ
# الشريط في dashboard.css، والصفحة تحمّل الاثنين.
#
# وإعادة تعريف الصنف وحدها ليست عطلًا — الداشبورد يُعيد تلوين مكوّنات مشتركة
# عمدًا (.sch-blank__ic بمقاس ورموز الداشبورد). العطل حين يكون الاسم **لمكوّن
# آخر**، وعلامته أن الأساس يعلّق على الاسم عناصر زائفة أو أبناء: تلك القواعد
# تبقى سارية فتحقن المكوّن الجديد بما ليس منه — «السكة» تحقن شريطها المتدرّج
# داخل زرّ دائري 26px.
BASE_CSS = 'assets/shared-ui.css'
STANDALONE_CSS = {'assets/parent.css', 'assets/admin.css'}

base_path = os.path.join(ROOT, BASE_CSS)
base_sels = css_layout.get(BASE_CSS, {})

if os.path.exists(base_path):
    base_src = re.sub(r'/\*.*?\*/', '', open(base_path, encoding='utf-8').read(), flags=re.S)

    for rel, sels in css_layout.items():
        if rel == BASE_CSS or rel in STANDALONE_CSS:
            continue

        for sel in sels:
            if sel not in base_sels:
                continue

            cls = re.escape(sel)
            # هل يعلّق الأساس على هذا الاسم عنصرًا زائفًا أو ابنًا؟
            leaks = re.findall(rf'{cls}\s*(::?[a-z-]+|>\s*\.[A-Za-z0-9_-]+)', base_src)
            leaks = sorted({l.strip() for l in leaks if l.strip() not in (':hover', ':focus', ':active')})

            if leaks:
                add('CSS_CLASH_X', f'{sel} معرَّف في {BASE_CSS} و{rel} معًا، والأساس يعلّق '
                                   f'عليه ({", ".join(leaks[:3])}) — والصفحة تحمّل الملفين، '
                                   f'فتُحقن قواعد الأساس في مكوّن الداشبورد. غيّر الاسم.')


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
