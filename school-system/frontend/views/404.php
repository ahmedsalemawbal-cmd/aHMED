<?php declare(strict_types=1); defined('ABSPATH') || exit; ?>
<div class="sch-empty">
    <strong><?php esc_html_e('الصفحة غير موجودة', 'school-system'); ?></strong>
    <a class="sch-link-quiet" href="<?php echo esc_url(SCH_Dashboard::url()); ?>"><?php esc_html_e('العودة للرئيسية', 'school-system'); ?></a>
</div>
