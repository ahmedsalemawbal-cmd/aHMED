<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_q = [
    'search'      => isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '',
    'object_type' => isset($_GET['obj']) ? sanitize_key((string) $_GET['obj']) : '',
    'from'        => isset($_GET['from']) ? sch_sanitize_date($_GET['from']) ?? '' : '',
    'to'          => isset($_GET['to']) ? sch_sanitize_date($_GET['to']) ?? '' : '',
    'page'        => isset($_GET['pg']) ? max(1, absint($_GET['pg'])) : 1,
    'per_page'    => 50,
];

$sch_res   = SCH_Audit::query($sch_q);
$sch_rows  = $sch_res['items'];
$sch_total = $sch_res['total'];
$sch_pages = (int) ceil($sch_total / 50);
$sch_types = SCH_Audit::object_types();
$sch_base  = SCH_Dashboard::url('audit');
?>
<h1 class="sch-title"><?php esc_html_e('سجل النظام', 'school-system'); ?></h1>
<p class="sch-sub"><?php echo esc_html(sprintf(
    /* translators: %s: عدد العمليات */
    __('%s عملية مسجّلة — غير قابلة للتعديل أو الحذف.', 'school-system'),
    number_format_i18n($sch_total)
)); ?></p>

<div class="sch-card sch-noprint">
    <form method="get" class="sch-toolbar">
        <input type="hidden" name="sch_section" value="audit">
        <input type="search" name="q" value="<?php echo esc_attr($sch_q['search']); ?>"
               placeholder="<?php esc_attr_e('بحث في العملية…', 'school-system'); ?>">

        <select name="obj" aria-label="<?php esc_attr_e('نوع الهدف', 'school-system'); ?>">
            <option value="">‏<?php esc_html_e('كل الأهداف', 'school-system'); ?></option>
            <?php foreach ($sch_types as $sch_t) : ?>
                <option value="<?php echo esc_attr($sch_t); ?>" <?php selected($sch_q['object_type'], $sch_t); ?>><?php echo esc_html($sch_t); ?></option>
            <?php endforeach; ?>
        </select>

        <input type="date" name="from" value="<?php echo esc_attr($sch_q['from']); ?>" aria-label="<?php esc_attr_e('من تاريخ', 'school-system'); ?>">
        <input type="date" name="to" value="<?php echo esc_attr($sch_q['to']); ?>" aria-label="<?php esc_attr_e('إلى تاريخ', 'school-system'); ?>">

        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('تصفية', 'school-system'); ?></button>
        <?php if ($sch_q['search'] !== '' || $sch_q['object_type'] !== '' || $sch_q['from'] !== '' || $sch_q['to'] !== '') : ?>
            <a class="sch-chipbtn" href="<?php echo esc_url($sch_base); ?>"><?php esc_html_e('مسح', 'school-system'); ?></a>
        <?php endif; ?>
        <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg('sch_export', 'csv')); ?>"><?php esc_html_e('تصدير CSV', 'school-system'); ?></a>
    </form>
</div>

<div class="sch-card">
    <?php if ($sch_rows === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا نتائج مطابقة', 'school-system'); ?></strong></div>
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
                <?php foreach ($sch_rows as $r) : ?>
                    <tr>
                        <td dir="ltr"><?php echo esc_html($r->created_at); ?></td>
                        <td class="sch-name"><?php echo esc_html($r->display_name ?: '—'); ?></td>
                        <td class="sch-name"><?php echo esc_html(SCH_Audit::label((string) $r->action)); ?></td>
                        <td dir="ltr"><?php echo esc_html($r->object_type ? $r->object_type . '#' . $r->object_id : '—'); ?></td>
                        <td dir="ltr"><?php echo esc_html($r->ip ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($sch_pages > 1) : ?>
            <div class="sch-pager sch-noprint">
                <?php if ($sch_q['page'] > 1) : ?>
                    <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg('pg', $sch_q['page'] - 1)); ?>"><?php esc_html_e('السابق', 'school-system'); ?></a>
                <?php endif; ?>
                <span class="sch-sub"><?php echo esc_html(sprintf(
                    /* translators: 1: الصفحة الحالية 2: عدد الصفحات */
                    __('صفحة %1$s من %2$s', 'school-system'),
                    number_format_i18n($sch_q['page']),
                    number_format_i18n($sch_pages)
                )); ?></span>
                <?php if ($sch_q['page'] < $sch_pages) : ?>
                    <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg('pg', $sch_q['page'] + 1)); ?>"><?php esc_html_e('التالي', 'school-system'); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
