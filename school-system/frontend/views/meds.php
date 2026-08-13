<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

global $wpdb;

$doses   = SCH_Medication::today_doses();
$pending = $wpdb->get_results(
    "SELECT m.*, s.full_name FROM " . sch_table('medications') . " m
     INNER JOIN " . sch_table('students') . " s ON s.id = m.student_id
     WHERE m.status = 'pending' ORDER BY m.id"
) ?: [];
?>
<h1 class="sch-title"><?php echo sch_icon('clock', 22); ?><?php esc_html_e('أدوية الطلاب', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('لا يُعطى الطفل دواء بلا إذن سارٍ من وليّه. وكل إعطاء يُسجَّل باسم من أعطاه.', 'school-system'); ?></p>

<?php if ($pending !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('أذونات بانتظار المراجعة', 'school-system'); ?></h2>
    <?php foreach ($pending as $m) : ?>
        <div class="sch-alert">
            <div class="sch-alert__head">
                <strong><?php echo esc_html($m->full_name); ?></strong>
                <span class="sch-badge"><?php echo esc_html($m->med_name . ' · ' . $m->dose); ?></span>
            </div>
            <span class="sch-sub" dir="ltr"><?php echo esc_html($m->start_date . ' → ' . $m->end_date . ' · ' . $m->times_csv); ?></span>
            <?php if ($m->file_stored) : ?>
                <a class="sch-thumb" target="_blank" rel="noopener"
                   href="<?php echo esc_url(SCH_Files::url('rx', (int) $m->id)); ?>">
                    <?php echo sch_icon('image', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php esc_html_e('فتح صورة الوصفة', 'school-system'); ?>
                </a>
            <?php endif; ?>

            <div class="sch-toolbar sch-mt">
                <form method="post">
                    <?php wp_nonce_field('sch_approve_med', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="approve_med">
                    <input type="hidden" name="med_id" value="<?php echo esc_attr((string) $m->id); ?>">
                    <button class="sch-btn"><?php esc_html_e('اعتماد', 'school-system'); ?></button>
                </form>
                <form method="post" class="sch-toolbar" onsubmit="return confirm('سيُرفض إذن الدواء ويصل إشعار لولي الأمر. متابعة؟');">
                    <?php wp_nonce_field('sch_reject_med', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="reject_med">
                    <input type="hidden" name="med_id" value="<?php echo esc_attr((string) $m->id); ?>">
                    <input type="text" name="reason" placeholder="<?php esc_attr_e('سبب الرفض', 'school-system'); ?>">
                    <button class="sch-link-danger"><?php esc_html_e('رفض', 'school-system'); ?></button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="sch-card">
    <h2><?php esc_html_e('جرعات اليوم', 'school-system'); ?></h2>

    <?php if ($doses === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد جرعات اليوم', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الموعد', 'school-system'); ?></th>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الدواء', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($doses as $d) : ?>
                    <tr>
                        <td class="sch-mono" dir="ltr"><?php echo esc_html($d->due_time); ?></td>
                        <td class="sch-name">
                            <?php echo esc_html($d->full_name); ?>
                            <?php if ($d->allergies) : ?>
                                <span class="sch-badge"><?php echo esc_html(__('حساسية:', 'school-system') . ' ' . $d->allergies); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($d->med_name . ' · ' . $d->dose); ?></td>
                        <td>
                            <?php if ($d->given_at) : ?>
                                <span class="sch-badge sch-badge--ok" dir="ltr"><?php echo esc_html(substr((string) $d->given_at, 11, 5)); ?></span>
                            <?php else : ?>
                                <span class="sch-badge"><?php esc_html_e('بانتظار', 'school-system'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$d->given_at) : ?>
                                <form method="post">
                                    <?php wp_nonce_field('sch_give_dose', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="give_dose">
                                    <input type="hidden" name="dose_id" value="<?php echo esc_attr((string) $d->id); ?>">
                                    <button class="sch-btn sch-btn--quiet"><?php esc_html_e('أُعطي', 'school-system'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
