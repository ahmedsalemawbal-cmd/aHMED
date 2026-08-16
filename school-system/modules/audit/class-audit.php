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
     * @param array{search?:string,object_type?:string,from?:string,to?:string,page?:int,per_page?:int} $args
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
}
