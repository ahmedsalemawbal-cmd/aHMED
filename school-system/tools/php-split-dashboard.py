#!/usr/bin/env python3
"""
نقل معالِجات الأفعال من ‎frontend/class-dashboard.php‎ إلى سمات (traits)
حسب المجال، في ‎frontend/controllers/‎.

**نقل ميكانيكي بحت — لا سطر يتغيّر داخل أي دالة.**

السمة في PHP تُدمج في الصنف عند الترجمة، فتبقى ‎self::‎ و‎private static‎
والسجل المركزي ‎actions()‎ كما هي حرفًا بحرف. لا وسيط جديد ولا تغيير في
مسار التنفيذ — ولذلك اختير هذا الأسلوب لا التركيب (composition):
التركيب كان سيتطلّب تمرير الحالة وتعديل 104 مدخلًا في السجل.

يُشغَّل مرة واحدة. بعده يعيش كل معالج في ملف مجاله.
"""
from __future__ import annotations

import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'frontend/class-dashboard.php')
OUT = os.path.join(ROOT, 'frontend/controllers')

# المجال ← (اسم السمة، الوصف، المعالجات)
GROUPS: dict[str, tuple[str, str, list[str]]] = {
    'students': ('SCH_Ctl_Students', 'الطلاب وأولياء الأمور والملفات والشهادات', [
        'do_add_student', 'do_register_student', 'do_update_student', 'do_enroll_student',
        'do_upload_doc', 'do_delete_doc', 'do_save_health', 'do_student_login',
        'do_add_guardian', 'do_update_guardian', 'do_link_guardian', 'do_unlink_guardian',
        'do_add_child', 'do_print_badges', 'do_issue_cert', 'do_issue_certs_bulk',
    ]),
    'academic': ('SCH_Ctl_Academic', 'الشعب والسنوات والمواد والاختبارات والجدول والمحتوى', [
        'do_add_class', 'do_delete_class', 'do_add_year', 'do_set_year',
        'do_add_subject', 'do_assign_subject', 'do_set_slot', 'do_clear_slot',
        'do_submit_timetable', 'do_publish_timetable', 'do_reopen_timetable',
        'do_add_exam', 'do_save_scores', 'do_add_content', 'do_content_status',
        'do_content_renew', 'do_add_homework', 'do_grade_homework', 'do_run_rollover',
    ]),
    'attendance': ('SCH_Ctl_Attendance', 'الحضور والملاحظات والإنذارات والعهدة', [
        'do_mark_attendance', 'do_mark_staff', 'do_mark_staff_bulk', 'do_assign_sub',
        'do_add_note', 'do_take_note', 'do_close_note', 'do_contact_parent',
        'do_ack_alert', 'do_close_alert', 'do_custody_manual', 'do_toggle_watch',
        'do_save_summary', 'do_approve_summary',
    ]),
    'finance': ('SCH_Ctl_Finance', 'الرسوم والفواتير والمحاسبة والرواتب والعقود', [
        'do_add_fee_plan', 'do_issue_invoice', 'do_record_payment', 'do_void_invoice',
        'do_refund_payment', 'do_add_account', 'do_add_journal', 'do_post_journal',
        'do_reverse_journal', 'do_add_contract', 'do_request_leave', 'do_decide_leave',
        'do_run_payroll', 'do_post_payroll',
    ]),
    'transport': ('SCH_Ctl_Transport', 'الباصات والمسارات والرحلات والاشتراكات', [
        'do_add_bus', 'do_add_route', 'do_add_stop', 'do_delete_stop',
        'do_subscribe', 'do_unsubscribe', 'do_open_trip', 'do_close_trip',
    ]),
    'services': ('SCH_Ctl_Services', 'العيادة والأدوية والمكتبة والزوار والسلامة والأصول', [
        'do_add_clinic', 'do_give_dose', 'do_approve_med', 'do_reject_med',
        'do_decide_student_leave', 'do_add_book', 'do_loan_book', 'do_return_book',
        'do_check_in', 'do_check_out', 'do_add_incident', 'do_close_incident',
        'do_add_asset', 'do_add_work', 'do_set_work', 'do_add_item', 'do_move_stock',
        'do_save_kg',
    ]),
    'system': ('SCH_Ctl_System', 'الموظفون والصلاحيات والإعدادات والاستيراد والجسر', [
        'do_add_employee', 'do_update_employee', 'do_suspend_employee', 'do_restore_employee',
        'do_print_staff_badges', 'do_save_perms', 'do_copy_perms', 'do_reset_perms',
        'do_save_settings', 'do_save_timing', 'do_save_alerts', 'merge_settings',
        'do_import', 'do_issue_key', 'do_revoke_key', 'do_send_message',
        'do_bulk', 'do_flow_mark',
    ]),
}

HEAD = """<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * {desc}
 *
 * سمة (trait) تُدمَج في `SCH_Dashboard` — فالمعالجات تبقى كما كانت تمامًا:
 * `private static`، تنادَى بـ`self::`، وتُسجَّل في `actions()` المركزي حيث
 * يُفرض النونس ثم الصلاحية قبل أي منها. **نُقلت ولم تُغيَّر.**
 *
 * لإضافة فعل: دالة `do_*` هنا + سطر في `SCH_Dashboard::actions()`. لا مسار ثانٍ.
 */
trait {trait}
{{
"""


def method_span(src: str, name: str) -> tuple[int, int] | None:
    """حدود دالة كاملة مع تعليقها التوثيقي — بعدّ الأقواس المعقوفة."""
    m = re.search(r'^([ \t]*)(private|public|protected)\s+static\s+function\s+'
                  + re.escape(name) + r'\s*\(', src, re.M)
    if not m:
        return None
    start = m.start()

    # ارجع للخلف: تعليق /** */ أو أسطر // الملاصقة
    head = src.rfind('\n\n', 0, start)
    block = src[head + 2:start] if head != -1 else ''
    if block.strip().startswith(('/**', '/*', '//')):
        start = head + 2

    # تقدّم حتى القوس المعقوف المتوازن
    i = src.index('{', m.end() - 1)
    depth, j = 0, i
    in_s, q, esc = False, '', False
    while j < len(src):
        ch = src[j]
        if in_s:
            if esc:
                esc = False
            elif ch == '\\':
                esc = True
            elif ch == q:
                in_s = False
        elif ch in '"\'':
            in_s, q = True, ch
        elif ch == '{':
            depth += 1
        elif ch == '}':
            depth -= 1
            if depth == 0:
                j += 1
                break
        j += 1
    while j < len(src) and src[j] == '\n':
        j += 1
    return start, j


def main() -> int:
    src = open(SRC, encoding='utf-8').read()
    before = set(re.findall(r'static\s+function\s+(\w+)', src))

    os.makedirs(OUT, exist_ok=True)
    spans, bodies = [], {}

    for key, (trait, desc, names) in GROUPS.items():
        chunks = []
        for n in names:
            sp = method_span(src, n)
            if sp is None:
                print(f'  ! غير موجودة: {n}', file=sys.stderr)
                continue
            spans.append(sp)
            chunks.append(src[sp[0]:sp[1]].rstrip('\n'))
        bodies[key] = (trait, desc, chunks)

    # احذف من الأطول للأقصر حتى لا تنزاح المواضع
    out = src
    for a, b in sorted(spans, key=lambda s: -s[0]):
        out = out[:a] + out[b:]

    # اكتب السمات
    for key, (trait, desc, chunks) in bodies.items():
        body = HEAD.format(trait=trait, desc=desc) + '\n\n'.join(chunks) + '\n}\n'
        open(os.path.join(OUT, f'trait-{key}.php'), 'w', encoding='utf-8').write(body)

    # أعلن السمات داخل الصنف
    uses = ''.join(f'    use {t};\n' for t, _, _ in
                   (bodies[k] for k in GROUPS))
    decl = ('final class SCH_Dashboard\n{\n'
            '    // معالِجات الأفعال موزّعة على سمات حسب المجال في\n'
            '    // frontend/controllers/. السمة تُدمَج عند الترجمة، فالسلوك\n'
            '    // والخصوصية و self:: كما هي — والسجل المركزي actions() هنا.\n'
            + uses + '\n')
    out = out.replace('final class SCH_Dashboard\n{\n', decl, 1)

    # نظّف الأسطر الفارغة الثلاثية الناتجة عن الحذف
    out = re.sub(r'\n{4,}', '\n\n\n', out)
    open(SRC, 'w', encoding='utf-8').write(out)

    # حمّل السمات قبل الصنف
    loader = os.path.join(ROOT, 'includes/class-loader.php')
    ls = open(loader, encoding='utf-8').read()
    reqs = ''.join(f"        require_once SCH_PATH . 'frontend/controllers/trait-{k}.php';\n"
                   for k in GROUPS)
    ls = ls.replace("        require_once SCH_PATH . 'frontend/class-dashboard.php';",
                    '        // السمات تُحمَّل قبل الصنف الذي يستعملها\n'
                    + reqs
                    + "        require_once SCH_PATH . 'frontend/class-dashboard.php';")
    open(loader, 'w', encoding='utf-8').write(ls)

    moved = sum(len(c) for _, _, c in bodies.values())
    print(f'نُقلت {moved} دالة إلى {len(GROUPS)} سمات')
    print(f'class-dashboard.php: {len(src.splitlines())} ← {len(out.splitlines())} سطرًا')
    return 0


if __name__ == '__main__':
    sys.exit(main())
