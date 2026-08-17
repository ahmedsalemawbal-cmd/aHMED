<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_years   = SCH_Years::all();
$sch_current = SCH_Years::current_id();
$sch_from    = isset($_GET['ry_from']) ? absint($_GET['ry_from']) : $sch_current;
$sch_to      = isset($_GET['ry_to']) ? absint($_GET['ry_to']) : 0;

$sch_plan = $sch_from > 0 ? SCH_Rollover::plan($sch_from) : [];

// تجميع الصفوف حسب (المرحلة|الصف) لخريطة الترقية
$sch_grades = [];
foreach ($sch_plan as $sch_c) {
    $k = $sch_c->stage . '|' . $sch_c->grade_level;
    if (!isset($sch_grades[$k])) {
        $sch_grades[$k] = ['stage' => (string) $sch_c->stage, 'grade' => (string) $sch_c->grade_level, 'students' => 0];
    }
    $sch_grades[$k]['students'] += (int) $sch_c->students;
}
?>
<h1 class="sch-title"><?php echo sch_icon('calendar', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('ترقية العام الدراسي', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('افتح عامًا جديدًا ورقِّ الطلاب دفعةً واحدة — بدل إعادة تسجيل كل طالب يدويًا.', 'school-system'); ?></p>

<div class="sch-notice">
    <?php esc_html_e('عملية كبيرة تُنشئ تسجيلات العام الجديد ولا تمسّ العام الحالي. جرّبها على نسخة احتياطية أولًا، وهي آمنة للإعادة — من رُقّي لا يُكرَّر.', 'school-system'); ?>
</div>

<div class="sch-card sch-noprint">
    <form method="get" class="sch-toolbar">
        <input type="hidden" name="sch_section" value="rollover">
        <label class="sch-field">
            <span><?php esc_html_e('من سنة', 'school-system'); ?></span>
            <select name="ry_from" aria-label="<?php esc_attr_e('من سنة', 'school-system'); ?>">
                <?php foreach ($sch_years as $y) : ?>
                    <option value="<?php echo esc_attr((string) $y->id); ?>" <?php selected($sch_from, (int) $y->id); ?>><?php echo esc_html($y->name); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="sch-field">
            <span><?php esc_html_e('إلى سنة', 'school-system'); ?></span>
            <select name="ry_to" aria-label="<?php esc_attr_e('إلى سنة', 'school-system'); ?>">
                <option value=""><?php esc_html_e('اختر…', 'school-system'); ?></option>
                <?php foreach ($sch_years as $y) : ?>
                    <option value="<?php echo esc_attr((string) $y->id); ?>" <?php selected($sch_to, (int) $y->id); ?>><?php echo esc_html($y->name); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('معاينة', 'school-system'); ?></button>
    </form>
</div>

<?php if (count($sch_years) < 2) : ?>
    <div class="sch-empty">
        <strong><?php esc_html_e('تحتاج سنتين دراسيتين', 'school-system'); ?></strong>
        <p><?php esc_html_e('أنشئ سنة العام القادم من الإعدادات ثم عُد لهذه الشاشة.', 'school-system'); ?></p>
    </div>
<?php elseif ($sch_from > 0 && $sch_to > 0 && $sch_from === $sch_to) : ?>
    <div class="sch-notice sch-notice--error"><?php esc_html_e('سنة المصدر والهدف متطابقتان — اختر سنتين مختلفتين.', 'school-system'); ?></div>
<?php elseif ($sch_from > 0 && $sch_to > 0 && $sch_grades !== []) : ?>
    <form method="post" class="sch-card" onsubmit="return confirm('<?php esc_attr_e('تنفيذ الترقية الآن؟ تأكّد أنك اخترت السنة الصحيحة.', 'school-system'); ?>');">
        <?php wp_nonce_field('sch_run_rollover', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="run_rollover">
        <input type="hidden" name="from_year" value="<?php echo esc_attr((string) $sch_from); ?>">
        <input type="hidden" name="to_year" value="<?php echo esc_attr((string) $sch_to); ?>">

        <h2><?php esc_html_e('خريطة الترقية', 'school-system'); ?></h2>
        <p class="sch-sub"><?php esc_html_e('لكل صف: اكتب اسمه في العام القادم (مثلًا «الأول» ← «الثاني»)، أو علّم «يتخرّج» للصف المنتهي فلا يُرقَّى.', 'school-system'); ?></p>

        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('المرحلة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصف الحالي', 'school-system'); ?></th>
                    <th><?php esc_html_e('طلاب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصف في العام القادم', 'school-system'); ?></th>
                    <th><?php esc_html_e('يتخرّج', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($sch_grades as $sch_k => $sch_g) : ?>
                    <tr>
                        <td><?php echo esc_html(SCH_Classes::STAGES[$sch_g['stage']] ?? $sch_g['stage']); ?></td>
                        <td class="sch-name"><?php echo esc_html($sch_g['grade']); ?></td>
                        <td><?php echo esc_html(number_format_i18n($sch_g['students'])); ?></td>
                        <td>
                            <input type="text" name="map[<?php echo esc_attr($sch_k); ?>]"
                                   value="<?php echo esc_attr($sch_g['grade']); ?>"
                                   aria-label="<?php esc_attr_e('الصف في العام القادم', 'school-system'); ?>">
                        </td>
                        <td>
                            <label class="sch-ck">
                                <input type="checkbox" name="graduate[<?php echo esc_attr($sch_k); ?>]" value="1">
                                <span></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button class="sch-btn sch-mt"><?php esc_html_e('تنفيذ الترقية', 'school-system'); ?></button>
    </form>
<?php elseif ($sch_from > 0 && $sch_to > 0) : ?>
    <div class="sch-empty"><strong><?php esc_html_e('لا صفوف في سنة المصدر', 'school-system'); ?></strong></div>
<?php endif; ?>
