<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * قراءة سجل التدقيق — بفلترة وصفحات.
 *
 * الكتابة عبر sch_audit() وحدها، وهذا الصنف للقراءة فقط: السجل لا يُعدَّل ولا
 * يُحذف. وُجد لينقل استعلام الشاشة من العرض إلى طبقة البيانات (لا SQL في القوالب).
 */
final class SCH_Audit
{
    /**
     * @param array{search?:string,object_type?:string,object_id?:int,from?:string,to?:string,page?:int,per_page?:int} $args
     * @return array{items:array<int,object>,total:int}
     */
    public static function query(array $args = []): array
    {
        global $wpdb;

        $where  = ['1=1'];
        $params = [];

        if (!empty($args['search'])) {
            $where[]  = 'a.action LIKE %s';
            $params[] = '%' . $wpdb->esc_like((string) $args['search']) . '%';
        }
        if (!empty($args['object_type'])) {
            $where[]  = 'a.object_type = %s';
            $params[] = (string) $args['object_type'];
        }
        // سجل هدفٍ بعينه — «سجل التغييرات» في ملفّ الطالب: من غيّر ماذا ومتى.
        if (!empty($args['object_id'])) {
            $where[]  = 'a.object_id = %d';
            $params[] = (int) $args['object_id'];
        }
        if (!empty($args['from'])) {
            $where[]  = 'a.created_at >= %s';
            $params[] = (string) $args['from'] . ' 00:00:00';
        }
        if (!empty($args['to'])) {
            $where[]  = 'a.created_at <= %s';
            $params[] = (string) $args['to'] . ' 23:59:59';
        }

        $clause   = implode(' AND ', $where);
        $per_page = min(200, max(1, (int) ($args['per_page'] ?? 50)));
        $page     = max(1, (int) ($args['page'] ?? 1));
        $offset   = ($page - 1) * $per_page;

        $table = sch_table('audit_log');

        $count_sql = "SELECT COUNT(*) FROM {$table} a WHERE {$clause}";
        $total     = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, ...$params) : $count_sql);

        $sql   = "SELECT a.*, u.display_name FROM {$table} a
                  LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
                  WHERE {$clause} ORDER BY a.id DESC LIMIT %d OFFSET %d";
        $args2 = array_merge($params, [$per_page, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, ...$args2)) ?: [];

        return ['items' => $items, 'total' => $total];
    }

    /** أنواع الأهداف الموجودة فعلًا — لقائمة الفلترة. @return string[] */
    public static function object_types(): array
    {
        global $wpdb;

        $rows = $wpdb->get_col(
            'SELECT DISTINCT object_type FROM ' . sch_table('audit_log') . "
             WHERE object_type IS NOT NULL AND object_type <> '' ORDER BY object_type"
        );

        return array_map('strval', $rows ?: []);
    }

    /**
     * اسم الفعل بالعربية — «سجل التغييرات» يُقرأ لا يُفكّ.
     *
     * السجل يُكتب بمفاتيح إنجليزية ثابتة عمدًا (تُبحث وتُفلتر ولا تتغيّر مع
     * الترجمة)، والترجمة تحدث عند العرض وحده. وما لا اسم له يظهر بمفتاحه —
     * فعلٌ جديد بلا تسمية يُقرأ ناقصًا ولا يختفي.
     */
    public static function label(string $action): string
    {
        $map = [
            'student.registered'         => __('إنشاء ملف الطالب', 'school-system'),
            'student.created'            => __('إضافة طالب', 'school-system'),
            'student.updated'            => __('تعديل بيانات الطالب', 'school-system'),
            'student.password_reset'     => __('توليد كلمة مرور جديدة للطالب', 'school-system'),
            'guardian.linked'            => __('ربط وليّ أمر', 'school-system'),
            'guardian.unlinked'          => __('فكّ ارتباط وليّ أمر', 'school-system'),
            'pickup.right_changed'       => __('تغيير حقّ الاستلام', 'school-system'),
            'pickup.delegated'           => __('تفويض استلام مؤقّت', 'school-system'),
            'pickup.delegation_revoked'  => __('سحب تفويض الاستلام', 'school-system'),
            'pickup.called'              => __('نداء انصراف', 'school-system'),
            'pickup.delivered'           => __('تسليم الطالب', 'school-system'),
            'custody.rejected'           => __('رُفض انتقال في سلسلة العهدة', 'school-system'),
            'doc.uploaded'               => __('رفع مستند', 'school-system'),
            'doc.deleted'                => __('حذف مستند', 'school-system'),
            'health.updated'             => __('تعديل السجل الصحي', 'school-system'),
            'health.viewed'              => __('اطّلاع على السجل الصحي', 'school-system'),
            'clinic.visit'               => __('زيارة العيادة', 'school-system'),
            'note.created'               => __('تسجيل ملاحظة', 'school-system'),
            'alert.opened'               => __('فتح إنذار', 'school-system'),
            'certificate.issued'         => __('إصدار شهادة', 'school-system'),
            'certificate.revoked'        => __('إلغاء شهادة', 'school-system'),
            'invoice.issued'             => __('إصدار فاتورة', 'school-system'),
            'student.enrolled'           => __('نقل بين الشُّعب', 'school-system'),
        ];

        return $map[$action] ?? $action;
    }
}
