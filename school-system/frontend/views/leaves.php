<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$pending = SCH_StudentLeave::pending();
?>
<h1 class="sch-title"><?php echo sch_icon('check', 22); ?><?php esc_html_e('إجازات الطلاب', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('الاعتماد يُسكت إنذار الصباح، ويعفي الباص من الانتظار، ويرصد «غياب بعذر» تلقائيًا.', 'school-system'); ?></p>

<?php SCH_Views::render('leaves'); ?>

<?php if ($pending !== []) : ?>
    <div class="sch-pickbar">
        <?php SCH_Bulk::pick_all(); ?>
        <span><?php esc_html_e('تحديد الكل', 'school-system'); ?></span>
        <span class="sch-sub"><?php esc_html_e('أو اضغط أولها ثم Shift مع آخرها لتحديد المدى', 'school-system'); ?></span>
    </div>
<?php endif; ?>

<?php if ($pending === []) : ?>
    <div class="sch-card">
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد طلبات', 'school-system'); ?></strong></div>
    </div>
<?php else : ?>
    <?php foreach ($pending as $l) : ?>
        <div class="sch-alert">
            <div class="sch-alert__head">
                <?php SCH_Bulk::pick((int) $l->id); ?>
                <strong>
                    <a href="<?php echo esc_url(SCH_Dashboard::url('students', (int) $l->student_id)); ?>">
                        <?php echo esc_html($l->full_name); ?>
                    </a>
                </strong>
                <span class="sch-badge"><?php echo esc_html(SCH_StudentLeave::REASONS[$l->reason_code] ?? ''); ?></span>
            </div>

            <span class="sch-sub" dir="ltr">
                <?php echo esc_html($l->start_date . ($l->days > 1 ? ' → ' . $l->end_date : '')); ?>
            </span>
            <span class="sch-sub">
                <?php echo esc_html(trim((string) $l->grade_level . ' / ' . (string) $l->section)); ?>
                <?php echo $l->note ? esc_html(' · ' . $l->note) : ''; ?>
            </span>
            <?php if ($l->file_stored) : ?>
                <a class="sch-thumb" target="_blank" rel="noopener"
                   href="<?php echo esc_url(SCH_Files::url('leave', (int) $l->id)); ?>">
                    <?php echo sch_icon('invoice', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php esc_html_e('فتح التقرير الطبي', 'school-system'); ?>
                </a>
            <?php endif; ?>

            <div class="sch-toolbar sch-mt">
                <form method="post">
                    <?php wp_nonce_field('sch_decide_leave_s', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="decide_leave_s">
                    <input type="hidden" name="leave_id" value="<?php echo esc_attr((string) $l->id); ?>">
                    <input type="hidden" name="decision" value="approved">
                    <button class="sch-btn"><?php esc_html_e('اعتماد', 'school-system'); ?></button>
                </form>

                <form method="post" class="sch-toolbar">
                    <?php wp_nonce_field('sch_decide_leave_s', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="decide_leave_s">
                    <input type="hidden" name="leave_id" value="<?php echo esc_attr((string) $l->id); ?>">
                    <input type="hidden" name="decision" value="rejected">
                    <input type="text" name="decision_note" required
                           placeholder="<?php esc_attr_e('سبب الرفض — إجباري', 'school-system'); ?>">
                    <button class="sch-link-danger"><?php esc_html_e('رفض', 'school-system'); ?></button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php SCH_Bulk::bar('leaves'); ?>
