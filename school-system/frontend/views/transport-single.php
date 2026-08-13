<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$route = SCH_Routes::get((int) ($sch_data['id'] ?? 0));
if (!$route) {
    echo '<div class="sch-empty"><strong>' . esc_html__('المسار غير موجود', 'school-system') . '</strong></div>';
    return;
}

$stops    = SCH_Routes::stops((int) $route->id);
$riders   = SCH_Routes::riders((int) $route->id);
$students = SCH_Students::list(['status' => 'active', 'per_page' => 300]);
?>
<a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('transport')); ?>">&larr; <?php esc_html_e('كل المسارات', 'school-system'); ?></a>
<h1 class="sch-title"><?php echo esc_html($route->name); ?></h1>

<div class="sch-card">
    <h2><?php esc_html_e('تشغيل رحلة اليوم', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('فتح الرحلة يسمح للسائق بمسح الطلاب من تطبيقه.', 'school-system'); ?></p>
    <div class="sch-toolbar">
        <?php foreach (['morning' => __('رحلة الذهاب', 'school-system'), 'afternoon' => __('رحلة العودة', 'school-system')] as $dir => $label) : ?>
            <form method="post">
                <?php wp_nonce_field('sch_open_trip', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="open_trip">
                <input type="hidden" name="route_id" value="<?php echo esc_attr((string) $route->id); ?>">
                <input type="hidden" name="direction" value="<?php echo esc_attr($dir); ?>">
                <button class="sch-btn sch-btn--quiet"><?php echo esc_html($label); ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('نقاط التوقف', 'school-system'); ?></h2>
    <?php if ($stops === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد نقاط توقف', 'school-system'); ?></strong><?php esc_html_e('أضف النقاط بالترتيب الذي يمر به الباص.', 'school-system'); ?></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th>#</th>
                    <th><?php esc_html_e('النقطة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الوقت', 'school-system'); ?></th>
                    <th><?php esc_html_e('الإحداثيات', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($stops as $s) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $s->sequence); ?></td>
                        <td class="sch-name"><?php echo esc_html($s->name); ?></td>
                        <td dir="ltr"><?php echo esc_html($s->pickup_time ?: '—'); ?></td>
                        <td dir="ltr" class="sch-sub"><?php echo esc_html($s->lat ? $s->lat . ', ' . $s->lng : '—'); ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('<?php esc_attr_e('حذف النقطة؟', 'school-system'); ?>');">
                                <?php wp_nonce_field('sch_delete_stop', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="delete_stop">
                                <input type="hidden" name="stop_id" value="<?php echo esc_attr((string) $s->id); ?>">
                                <button class="sch-link-danger"><?php esc_html_e('حذف', 'school-system'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" class="sch-mt">
        <?php wp_nonce_field('sch_add_stop', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_stop">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="s-stopname"><?php esc_html_e('اسم النقطة', 'school-system'); ?></label>
                <input id="s-stopname" type="text" name="name" required>
            </div>
            <div class="sch-field">
                <label for="s-time"><?php esc_html_e('وقت المرور', 'school-system'); ?></label>
                <input id="s-time" type="time" name="pickup_time">
            </div>
            <div class="sch-field">
                <label for="s-lat"><?php esc_html_e('خط العرض', 'school-system'); ?></label>
                <input id="s-lat" type="text" name="lat" dir="ltr" placeholder="24.7136">
            </div>
            <div class="sch-field">
                <label for="s-lng"><?php esc_html_e('خط الطول', 'school-system'); ?></label>
                <input id="s-lng" type="text" name="lng" dir="ltr" placeholder="46.6753">
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('إضافة نقطة', 'school-system'); ?></button>
    </form>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('الطلاب المشتركون', 'school-system'); ?></h2>
    <?php if ($riders === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا يوجد مشتركون', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصف', 'school-system'); ?></th>
                    <th><?php esc_html_e('نقطة التوقف', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($riders as $s) : ?>
                    <tr>
                        <td class="sch-name"><a href="<?php echo esc_url(SCH_Dashboard::url('students', (int) $s->id)); ?>"><?php echo esc_html($s->full_name); ?></a></td>
                        <td><?php echo esc_html(trim(($s->grade_level ?: '') . ' ' . ($s->section ?: '')) ?: '—'); ?></td>
                        <td><?php echo esc_html($s->stop_name ?: '—'); ?></td>
                        <td>
                            <form method="post">
                                <?php wp_nonce_field('sch_unsubscribe', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="unsubscribe">
                                <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $s->id); ?>">
                                <button class="sch-link-danger"><?php esc_html_e('إلغاء الاشتراك', 'school-system'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" class="sch-mt">
        <?php wp_nonce_field('sch_subscribe', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="subscribe">
        <input type="hidden" name="route_id" value="<?php echo esc_attr((string) $route->id); ?>">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="sub-student"><?php esc_html_e('الطالب', 'school-system'); ?></label>
                <select id="sub-student" name="student_id" required>
                    <option value=""><?php esc_html_e('اختر طالبًا…', 'school-system'); ?></option>
                    <?php foreach ($students['items'] as $s) : ?>
                        <option value="<?php echo esc_attr((string) $s->id); ?>"><?php echo esc_html($s->full_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="sub-stop"><?php esc_html_e('نقطة التوقف', 'school-system'); ?></label>
                <select id="sub-stop" name="stop_id">
                    <option value=""><?php esc_html_e('غير محددة', 'school-system'); ?></option>
                    <?php foreach ($stops as $s) : ?>
                        <option value="<?php echo esc_attr((string) $s->id); ?>"><?php echo esc_html($s->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="sub-fee"><?php esc_html_e('رسوم النقل السنوية', 'school-system'); ?></label>
                <input id="sub-fee" type="number" name="fee_amount" step="0.01" min="0" value="0">
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('اشتراك', 'school-system'); ?></button>
    </form>
</div>
