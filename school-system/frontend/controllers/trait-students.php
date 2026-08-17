<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * الطلاب وأولياء الأمور والملفات والشهادات
 *
 * سمة (trait) تُدمَج في `SCH_Dashboard` — فالمعالجات تبقى كما كانت تمامًا:
 * `private static`، تنادَى بـ`self::`، وتُسجَّل في `actions()` المركزي حيث
 * يُفرض النونس ثم الصلاحية قبل أي منها. **نُقلت ولم تُغيَّر.**
 *
 * لإضافة فعل: دالة `do_*` هنا + سطر في `SCH_Dashboard::actions()`. لا مسار ثانٍ.
 */
trait SCH_Ctl_Students
{
    private static function do_add_student(array $d, int $id): array|WP_Error
    {
        $created = SCH_Students::create($d);
        if (is_wp_error($created)) {
            return $created;
        }

        if (!empty($d['class_id'])) {
            SCH_Students::enroll((int) $created['id'], absint($d['class_id']));
        }

        return $created;
    }

    private static function do_register_student(array $d, int $id): array|WP_Error
    {
        return SCH_Enrollment::register($d, $_FILES);
    }

    private static function do_update_student(array $d, int $id): bool|WP_Error
    {
        return SCH_Students::update($id, $d);
    }

    private static function do_enroll_student(array $d, int $id): bool|WP_Error
    {
        return SCH_Students::enroll($id, absint($d['class_id'] ?? 0));
    }

    private static function do_upload_doc(array $d, int $id): array|WP_Error
    {
        $student_id = absint($d['student_id'] ?? 0) ?: $id;
        $type       = sanitize_key((string) ($d['doc_type'] ?? ''));

        if (empty($_FILES['doc']['tmp_name'])) {
            return sch_api_error('no_file', __('اختر ملفًا.', 'school-system'), 422);
        }

        $result = SCH_Enrollment::save_doc($student_id, $type, $_FILES['doc']);

        return is_wp_error($result) ? $result : ['id' => $result, 'goto_id' => $student_id];
    }

    private static function do_delete_doc(array $d, int $id): bool
    {
        return SCH_Enrollment::delete_doc(absint($d['doc_id'] ?? 0));
    }

    private static function do_save_health(array $d, int $id): bool|WP_Error
    {
        return SCH_Enrollment::save_health(absint($d['student_id'] ?? 0) ?: $id, $d);
    }

    /** إعادة موظف موقوف للعمل — الموظف يُوقَف لا يُحذف، فإعادته بضغطة. */
    /** توليد بيانات دخول الطالب وعرضها — أو إنشاء حسابه إن كان ناقصًا. */
    private static function do_student_login(array $d, int $id): array|WP_Error
    {
        $student_id = absint($d['student_id'] ?? 0);
        $account    = SCH_Enrollment::reset_student_password($student_id);

        if (is_wp_error($account)) {
            return $account;
        }

        self::stash_credentials([
            'name'     => SCH_Enrollment::full_name(SCH_Students::get($student_id)),
            'login'    => $account['login'],
            'password' => $account['password'],
        ]);

        return ['cred' => 1, 'goto_id' => $student_id];
    }

    private static function do_add_guardian(array $d, int $id): array|WP_Error
    {
        $created = SCH_Guardians::create($d);
        if (is_wp_error($created)) {
            return $created;
        }

        $student_id = absint($d['student_id'] ?? 0) ?: $id;
        if ($student_id > 0) {
            SCH_Guardians::link((int) $created['user_id'], $student_id, (string) ($d['relation'] ?? 'father'), true);
        }

        return $created;
    }

    private static function do_update_guardian(array $d, int $id): bool|WP_Error
    {
        return SCH_Guardians::update($id ?: absint($d['guardian_id'] ?? 0), $d);
    }

    private static function do_link_guardian(array $d, int $id): bool|WP_Error
    {
        $guardian = SCH_Guardians::get(absint($d['guardian_id'] ?? 0));
        if (!$guardian) {
            return sch_api_error('not_found', __('ولي الأمر غير موجود.', 'school-system'), 404);
        }

        return SCH_Guardians::link(
            (int) $guardian->user_id,
            $id ?: absint($d['student_id'] ?? 0),
            (string) ($d['relation'] ?? 'father'),
            !empty($d['is_primary'])
        );
    }

    private static function do_unlink_guardian(array $d, int $id): bool
    {
        return SCH_Guardians::unlink(absint($d['guardian_user_id'] ?? 0), $id ?: absint($d['student_id'] ?? 0));
    }

    /** إضافة ابن لولي أمر بالرقم الأكاديمي. */
    private static function do_add_child(array $d, int $id): bool|WP_Error
    {
        $guardian = SCH_Guardians::get($id ?: absint($d['guardian_id'] ?? 0));
        if (!$guardian) {
            return sch_api_error('not_found', __('ولي الأمر غير موجود.', 'school-system'), 404);
        }

        $student = SCH_Students::get_by_academic((string) ($d['academic_no'] ?? ''));
        if (!$student) {
            return sch_api_error('student_not_found', __('لا يوجد طالب بهذا الرقم.', 'school-system'), 404);
        }

        return SCH_Guardians::link(
            (int) $guardian->user_id,
            (int) $student->id,
            (string) ($d['relation'] ?? 'father'),
            !empty($d['is_primary'])
        );
    }

    /** تعليم البطاقات كمطبوعة — الطباعة نفسها تتم في المتصفح. */
    private static function do_print_badges(array $d, int $id): array
    {
        $ids = array_map('absint', explode(',', (string) ($d['ids'] ?? '')));
        $n   = SCH_Students::mark_printed($ids);

        return ['msg' => sprintf(
            /* translators: %d: عدد البطاقات */
            _n('عُلّمت بطاقة واحدة كمطبوعة', 'عُلّمت %d بطاقة كمطبوعة', $n, 'school-system'),
            $n
        )];
    }

    /** تعديل بيانات موظف — الصلاحيات لها شاشتها المستقلة. */
    private static function do_issue_cert(array $d, int $id): array|WP_Error
    {
        $result = SCH_Certificates::issue($d);

        if (is_wp_error($result)) {
            return $result;
        }

        return ['msg' => sprintf(
            /* translators: %s: الرقم التسلسلي */
            __('صدرت الشهادة برقم %s', 'school-system'),
            $result['serial']
        )];
    }

    /** إصدار جماعي — الوكيل يمنح ثلاثين شهادة بضغطة لا بثلاثين. */
    private static function do_issue_certs_bulk(array $d, int $id): array|WP_Error
    {
        $ids  = array_values(array_filter(array_map('absint', explode(',', (string) ($d['ids'] ?? '')))));
        $type = sanitize_key((string) ($d['type'] ?? ''));

        if ($ids === []) {
            return sch_api_error('empty', __('لم تحدد طلابًا.', 'school-system'), 422);
        }

        if (count($ids) > 300) {
            return sch_api_error('too_many', __('حدّد ٣٠٠ طالب أو أقل في المرة الواحدة.', 'school-system'), 422);
        }

        $done = 0;

        foreach ($ids as $student_id) {
            $r = SCH_Certificates::issue([
                'student_id' => $student_id,
                'type'       => $type,
                'template'   => (string) ($d['template'] ?? ''),
                'reason'     => (string) ($d['reason'] ?? ''),
            ]);

            if (!is_wp_error($r)) {
                $done++;
            }
        }

        return ['msg' => sprintf(
            /* translators: %s: العدد */
            _n('صدرت شهادة واحدة', 'صدرت %s شهادات', $done, 'school-system'),
            number_format_i18n($done)
        )];
    }
}
