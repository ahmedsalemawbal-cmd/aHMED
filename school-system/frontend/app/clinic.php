<?php
/** الصحة المدرسية — الترتيب حسب الخطورة لا حسب التاريخ. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$student = SCH_Students::get($id);

if (!$student) { require SCH_PATH . 'frontend/app/home.php'; return; }

$health = SCH_Enrollment::health($id);
$meds   = SCH_Medication::of_student($id);
$visits = SCH_Clinic::of_student($id);
?>

<a class="p-back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>">
    <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php esc_html_e('رجوع', 'school-system'); ?>
</a>

<h1 class="p-h1"><?php esc_html_e('الصحة المدرسية', 'school-system'); ?></h1>

<!-- الحساسية وفصيلة الدم أولًا: هما ما يُقرأ في الطوارئ -->
<div class="p-vital">
    <div class="p-vital__x">
        <span><?php esc_html_e('الحساسية', 'school-system'); ?></span>
        <b><?php echo esc_html($health->allergies ?? '' ?: __('لا توجد', 'school-system')); ?></b>
    </div>
    <div class="p-vital__x">
        <span><?php esc_html_e('فصيلة الدم', 'school-system'); ?></span>
        <b><?php echo esc_html($health->blood_type ?? '' ?: __('غير محددة', 'school-system')); ?></b>
    </div>
</div>

<?php if (!empty($health->chronic)) : ?>
    <div class="p-note">
        <b><?php esc_html_e('أمراض مزمنة', 'school-system'); ?></b>
        <span><?php echo esc_html((string) $health->chronic); ?></span>
    </div>
<?php endif; ?>

<h2 class="p-h2"><?php esc_html_e('أذونات الأدوية', 'school-system'); ?></h2>

<?php if ($meds === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('heart', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا أدوية مسجّلة', 'school-system'); ?></b>
        <p><?php esc_html_e('اطلب إذنًا إن كان ابنك يحتاج دواءً في المدرسة.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="p-list">
        <?php foreach ($meds as $med) : ?>
            <div class="p-row">
                <span class="p-row__i"><?php echo sch_icon('heart', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <span class="p-row__t">
                    <b><?php echo esc_html((string) $med->drug_name); ?></b>
                    <span><?php echo esc_html(trim((string) $med->dose . ' · ' . (string) $med->times)); ?></span>
                </span>
                <span class="p-tag p-tag--<?php echo esc_attr(($med->status ?? '') === 'approved' ? 'ok' : 'warn'); ?>">
                    <?php echo esc_html(SCH_Medication::STATUSES[$med->status] ?? ''); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($visits !== []) : ?>
    <h2 class="p-h2"><?php esc_html_e('الزيارات الصحية', 'school-system'); ?></h2>
    <div class="p-list">
        <?php foreach (array_slice($visits, 0, 10) as $v) : ?>
            <div class="p-row">
                <span class="p-row__t">
                    <b><?php echo esc_html((string) $v->complaint); ?></b>
                    <span><?php echo esc_html((string) ($v->action ?? '')); ?></span>
                </span>
                <span class="p-row__e"><?php echo esc_html(wp_date('j M', strtotime((string) $v->created_at))); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
