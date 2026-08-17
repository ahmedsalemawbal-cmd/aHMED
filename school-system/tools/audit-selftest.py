#!/usr/bin/env python3
"""
اختبار الفحوص نفسها: لكل فحص **نُحدث عطله ثم نتأكد أنه أمسكه**.

فحص لا يُختبر هكذا لا قيمة له — قد يكون صامتًا لأن الكود سليم، أو صامتًا
لأنه معطوب، ولا طريقة للتمييز. وهذا ما حدث فعلًا: النسخة الأولى من
‎TPL_UNBALANCED‎ كانت بتعبير نمطي فأنتجت 119 نتيجة خاطئة على كود سليم.

الاستعمال:  python3 tools/audit-selftest.py [-v]
الخروج:     0 كل الفحوص تمسك عطلها · 1 فحص واحد أو أكثر لا يمسك
"""
from __future__ import annotations

import argparse
import os
import re
import shutil
import subprocess
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def sub(path: str, old: str, new: str, count: int = 1):
    """يستبدل نصًّا في ملف — ويرفع خطأً إن لم يجد ما يستبدله."""
    def apply(root: str):
        p = os.path.join(root, path)
        s = open(p, encoding='utf-8').read()
        if old not in s:
            raise AssertionError(f'نص التعطيل غير موجود في {path}: {old[:60]!r}')
        open(p, 'w', encoding='utf-8').write(s.replace(old, new, count))
    return apply


def append(path: str, text: str):
    def apply(root: str):
        with open(os.path.join(root, path), 'a', encoding='utf-8') as fh:
            fh.write(text)
    return apply


def write(path: str, text: str):
    def apply(root: str):
        p = os.path.join(root, path)
        os.makedirs(os.path.dirname(p), exist_ok=True)
        open(p, 'w', encoding='utf-8').write(text)
    return apply


# اسم الفحص ← عطل يجب أن يمسكه
FAULTS: dict[str, callable] = {
    'TPL_UNBALANCED': sub(
        'frontend/views/students.php',
        '<?php endif; ?>', '<?php  ?>'),

    'TAG_BROKEN': sub(
        'frontend/views/classes.php',
        '<?php endforeach; ?>', '<?php endforeach; '),   # وسم لا يُغلق فينكسر النحو

    'SCHEMA_DUP_COLUMN': sub(
        'includes/class-activator.php',
        'CREATE TABLE {$p}students (\n            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,',
        'CREATE TABLE {$p}students (\n            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            full_name     VARCHAR(190)    NOT NULL,'),

    'SQL_UNKNOWN_COLUMN': sub(
        'frontend/class-teacher.php',
        'c.homeroom_teacher_id = %d', 'c.homeroom_user_id = %d'),

    'ACTION_NO_HANDLER': sub(
        'frontend/views/partials/enroll-form.php',
        'value="register_student"', 'value="regsiter_student"'),

    'ACTION_METHOD_MISSING': sub(
        'frontend/class-dashboard.php',
        'private static function do_add_student(', 'private static function do_add_studnt('),

    'FORM_NO_NONCE': write(
        'frontend/views/_selftest.php',
        '<?php defined(\'ABSPATH\') || exit; ?>\n'
        '<form method="post"><input type="text" name="x" aria-label="x"><button>go</button></form>\n'),

    'SECTION_NO_VIEW': sub(
        'frontend/class-dashboard.php',
        "'overview'   => ['نظرة عامة',       'sch_view_students',",
        "'overvieww'  => ['نظرة عامة',       'sch_view_students',"),

    'ROUTE_NOT_FLUSHED': sub(
        'includes/class-loader.php',
        "add_action('init', [self::class, 'maybe_flush_rewrites'], 99);",
        "add_action('init', [self::class, 'maybe_flush_rewrites'], 10);"),

    'LINK_DEAD_PARAM': sub(
        'frontend/class-app.php',
        "add_query_arg('sch_avatar', '1'", "add_query_arg('sch_avatarx', '1'"),

    'CSS_DUP_LAYOUT': append(
        'assets/shared-ui.css',
        '\n.sch-selftest-dup { display: flex; }\n.sch-selftest-dup { position: fixed; }\n'),

    'CSS_VAR_UNDEFINED': append(
        'assets/shared-ui.css', '\n.sch-selftest-var { color: var(--sch-nope-nope); }\n'),

    'CSS_FONT_HARDCODED': append(
        'assets/shared-ui.css', "\n.sch-selftest-font { font-family: 'Comic Sans MS', sans-serif; }\n"),

    'EXTERNAL_HOST': append(
        'assets/shared-ui.css', '\n/* @import url(https://evil.example.org/x.css) */\n'),

    'SCH_TOKEN_HOME': append(
        'assets/gate.css', '\n:root { --sch-selftest-token: #fff; }\n'),

    'MODAL_UNBALANCED': sub(
        'frontend/views/classes.php', 'SCH_Modal::close(', 'SCH_Modal::clos3('),

    'IMG_NO_ALT': write(
        'frontend/views/_selftest_img.php',
        '<?php defined(\'ABSPATH\') || exit; ?>\n<img src="x.png" width="10" height="10">\n'),

    'FIELD_UNLABELLED': write(
        'frontend/views/_selftest_field.php',
        '<?php defined(\'ABSPATH\') || exit; ?>\n<input type="text" name="nameless">\n'),

    'PHYSICAL_PROPS': append(
        'assets/shared-ui.css', '\n.sch-selftest-phys { margin-right: 8px; text-align: right; }\n'),

    'FILE_NOT_LOADED': write(
        'modules/selftest/class-selftest.php',
        "<?php\ndeclare(strict_types=1);\nfinal class SCH_SelfTest { }\n"),

    'CLASS_UNDEFINED': append(
        'frontend/views/classes.php', "\n<?php SCH_NoSuchClassHere::boom(); ?>\n"),

    'FUNC_UNDEFINED': append(
        'frontend/views/classes.php', "\n<?php sch_no_such_function_here(); ?>\n"),

    'TABLE_UNKNOWN': append(
        'frontend/views/classes.php', "\n<?php echo sch_table('no_such_table_here'); ?>\n"),

    'TABLE_UNUSED': sub(
        'includes/class-activator.php',
        'CREATE TABLE {$p}visitors (', 'CREATE TABLE {$p}visitorz ('),

    'VERSION_SYNC': sub(
        'school-system.php', "define('SCH_VERSION', '8.3.0')", "define('SCH_VERSION', '9.9.9')"),

    'LIST_SHAPE': sub(
        'frontend/views/perms.php',
        "$staff = SCH_Staff::list(['status' => 'active'])['items'];",
        "$staff = SCH_Staff::list(['status' => 'active']);"),

    'MONEY_RAW': append(
        'frontend/views/finance.php', "\n<?php echo number_format(12.5, 2); ?>\n"),

    'CAP_ORPHAN': sub(
        'frontend/class-dashboard.php',
        "'students'   => ['الطلاب',          'sch_view_students',",
        "'students'   => ['الطلاب',          'sch_never_granted_cap',"),

    'ROLE_NO_HOME': sub(
        'includes/class-activator.php',
        "'caps'  => ['read', 'sch_drive_trip'],",
        "'caps'  => ['read', 'sch_learn'],"),   # صلاحية لا قسم لها ولا تطبيق

    'ROLE_NO_CAPS': sub(
        'includes/class-activator.php',
        "'caps'  => ['read', 'sch_drive_trip'],",
        "'caps'  => ['read'],"),

    'APP_NONCE_UNKNOWN': sub(
        'frontend/gate/visitors.php', "wp_nonce_field('sch_gate_visitor'", "wp_nonce_field('totally_unknown_nonce'"),

    'PREPARE_ARITY': sub(
        'frontend/class-student.php',
        "WHERE student_id = %d AND att_date >= %s AND status IN ('absent','excused')",
        "WHERE student_id = %d AND att_date >= %s AND note = %s AND status IN ('absent','excused')"),
}

SKIP_REASON = {
    'MODAL_NO_OPENER': 'يعتمد على وجود زر في نفس الملف — يُختبر يدويًا',
}


def run_check(root: str, name: str) -> tuple[int, str]:
    res = subprocess.run(
        [sys.executable, 'audit.py', '-o', name],
        cwd=root, capture_output=True, text=True,
        env={**os.environ, 'NO_COLOR': '1'}, timeout=300)
    return res.returncode, res.stdout + res.stderr


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument('-v', '--verbose', action='store_true')
    args = ap.parse_args()

    names = sorted(n for n in FAULTS if n not in SKIP_REASON)
    passed, failed, noisy = [], [], []

    with tempfile.TemporaryDirectory(prefix='sch-selftest-') as tmp:
        clean = os.path.join(tmp, 'clean')
        shutil.copytree(ROOT, clean, ignore=shutil.ignore_patterns('.git', 'node_modules'))

        # ١) على كود سليم: الفحص يجب أن يصمت (لا يُبلّغ عن اسمه)
        for name in names:
            code, out = run_check(clean, name)
            if re.search(r'^\s*' + name + r'\b', out, re.M):
                noisy.append(name)
                if args.verbose:
                    print(f'  {name}: يُبلّغ على كود سليم\n{out[:400]}')

        # ٢) مع العطل: الفحص يجب أن يمسكه
        for name in names:
            work = os.path.join(tmp, 'w_' + name)
            shutil.copytree(clean, work)
            try:
                FAULTS[name](work)
            except AssertionError as exc:
                failed.append((name, f'تعذّر إحداث العطل: {exc}'))
                continue
            code, out = run_check(work, name)
            if re.search(r'^\s*' + name + r'\b', out, re.M):
                passed.append(name)
                if args.verbose:
                    print(f'  ✓ {name}')
            else:
                failed.append((name, 'أُحدث العطل ولم يُمسَك'))
                if args.verbose:
                    print(f'  ✗ {name}\n{out[:500]}')
            shutil.rmtree(work, ignore_errors=True)

    print()
    print(f'يمسك عطله      : {len(passed)}/{len(names)}')
    if noisy:
        print(f'يُبلّغ على كود سليم: {len(noisy)} — {", ".join(noisy)}')
    for name, why in failed:
        print(f'  ✗ {name}: {why}')
    for name, why in SKIP_REASON.items():
        print(f'  – {name}: {why}')

    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
