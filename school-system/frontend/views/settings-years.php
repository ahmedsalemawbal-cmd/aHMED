<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

// السنوات الدراسية — كل شعبة وتسجيل مرتبط بسنة، والسنة النشطة هي محور الشاشات.
$years = SCH_Years::all();
?>
<h1 class="sch-title"><?php echo sch_icon('calendar', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('السنوات الدراسية', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('كل شعبة وتسجيل مرتبط بسنة. السنة النشطة هي التي تعمل عليها كل الشاشات.', 'school-system'); ?></p>

<div class="sch-card">
    <?php if ($years === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد سنة دراسية', 'school-system'); ?></strong>
            <p><?php esc_html_e('أنشئ السنة الحالية لتبدأ في إضافة الشعب والطلاب.', 'school-system'); ?></p></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('السنة', 'school-system'); ?></th>
                    <th><?php esc_html_e('من', 'school-system'); ?></th>
                    <th><?php esc_html_e('إلى', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($years as $y) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($y->name); ?></td>
                        <td dir="ltr"><?php echo esc_html($y->start_date); ?></td>
                        <td dir="ltr"><?php echo esc_html($y->end_date); ?></td>
                        <td>
                            <?php if ($y->is_current) : ?>
                                <span class="sch-badge sch-badge--ok"><?php esc_html_e('السنة النشطة', 'school-system'); ?></span>
                            <?php else : ?>
                                <form method="post">
                                    <?php wp_nonce_field('sch_set_year', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="set_year">
                                    <input type="hidden" name="year_id" value="<?php echo esc_attr((string) $y->id); ?>">
                                    <button class="sch-btn sch-btn--quiet"><?php esc_html_e('تفعيل', 'school-system'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" class="sch-mt">
        <?php wp_nonce_field('sch_add_year', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_year">
        <div class="sch-grid">
            <label class="sch-field"><span><?php esc_html_e('اسم السنة', 'school-system'); ?></span>
                <input type="text" name="name" placeholder="1447 / 1448" required></label>
            <label class="sch-field"><span><?php esc_html_e('تاريخ البداية', 'school-system'); ?></span>
                <input type="date" name="start_date" aria-label="<?php esc_attr_e('تاريخ البداية', 'school-system'); ?>" required></label>
            <label class="sch-field"><span><?php esc_html_e('تاريخ النهاية', 'school-system'); ?></span>
                <input type="date" name="end_date" aria-label="<?php esc_attr_e('تاريخ النهاية', 'school-system'); ?>" required></label>
        </div>
        <button class="sch-btn sch-mt"><?php esc_html_e('إضافة سنة', 'school-system'); ?></button>
    </form>
</div>
