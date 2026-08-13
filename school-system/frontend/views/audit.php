<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

global $wpdb;
$rows = $wpdb->get_results(
    'SELECT a.*, u.display_name FROM ' . sch_table('audit_log') . ' a
     LEFT JOIN ' . $wpdb->users . ' u ON u.ID = a.user_id
     ORDER BY a.id DESC LIMIT 200'
) ?: [];
?>
<h1 class="sch-title"><?php esc_html_e('سجل النظام', 'school-system'); ?></h1>

<div class="sch-card">
    <p class="sch-sub"><?php esc_html_e('آخر 200 عملية. السجل غير قابل للتعديل أو الحذف من الواجهة.', 'school-system'); ?></p>

    <?php if ($rows === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('السجل فارغ', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الوقت', 'school-system'); ?></th>
                    <th><?php esc_html_e('المستخدم', 'school-system'); ?></th>
                    <th><?php esc_html_e('العملية', 'school-system'); ?></th>
                    <th><?php esc_html_e('الهدف', 'school-system'); ?></th>
                    <th>IP</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r) : ?>
                    <tr>
                        <td dir="ltr"><?php echo esc_html($r->created_at); ?></td>
                        <td class="sch-name"><?php echo esc_html($r->display_name ?: '—'); ?></td>
                        <td dir="ltr"><?php echo esc_html($r->action); ?></td>
                        <td dir="ltr"><?php echo esc_html($r->object_type ? $r->object_type . '#' . $r->object_id : '—'); ?></td>
                        <td dir="ltr"><?php echo esc_html($r->ip ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
