<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$year    = SCH_Years::current();
$classes = SCH_Classes::list();
?>
<?php
SCH_Modal::head(
    __('الصفوف والشعب', 'school-system'),
    sprintf(_n('%s شعبة', '%s شعب', count($classes), 'school-system'), number_format_i18n(count($classes))),
    'sch-add-class',
    __('إضافة شعبة', 'school-system')
);
?>

<?php if (!$year) : ?>
    <div class="sch-notice sch-notice--error">
        <?php esc_html_e('لا توجد سنة دراسية نشطة. أنشئ سنة من الإعدادات أولًا.', 'school-system'); ?>
        <a href="<?php echo esc_url(SCH_Dashboard::url('settings')); ?>"><?php esc_html_e('الإعدادات', 'school-system'); ?></a>
    </div>
<?php else : ?>

<div class="sch-card">
    <h2><?php echo esc_html(sprintf(__('شعب السنة %s', 'school-system'), $year->name)); ?></h2>

    <?php if ($classes === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا توجد شعب بعد', 'school-system'); ?></strong>
            <?php esc_html_e('أنشئ أول شعبة بالزر أعلى الشاشة، ثم سجّل الطلاب فيها.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('المرحلة', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصف', 'school-system'); ?></th>
                    <th><?php esc_html_e('الشعبة', 'school-system'); ?></th>
                    <th><?php esc_html_e('المسجَّلون', 'school-system'); ?></th>
                    <th><?php esc_html_e('السعة', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($classes as $c) :
                    $full = (int) $c->enrolled >= (int) $c->capacity; ?>
                    <tr>
                        <td><?php echo esc_html(SCH_Classes::STAGES[$c->stage] ?? $c->stage); ?></td>
                        <td class="sch-name"><?php echo esc_html($c->grade_level); ?></td>
                        <td><?php echo esc_html($c->section); ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('class_id', (int) $c->id, SCH_Dashboard::url('students'))); ?>">
                                <?php echo esc_html(number_format_i18n((int) $c->enrolled)); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo esc_html(number_format_i18n((int) $c->capacity)); ?>
                            <?php if ($full) : ?><span class="sch-badge"><?php esc_html_e('مكتملة', 'school-system'); ?></span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $c->enrolled === 0) : ?>
                                <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" onsubmit="return confirm('<?php esc_attr_e('حذف هذه الشعبة؟', 'school-system'); ?>');">
                                    <?php wp_nonce_field('sch_delete_class', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="delete_class">
                                    <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $c->id); ?>">
                                    <button class="sch-link-danger"><?php esc_html_e('حذف', 'school-system'); ?></button>
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


<?php endif; ?>

<?php SCH_Modal::open('sch-add-class', __('إضافة شعبة', 'school-system'), __('المرحلة تحدد مشرفها تلقائيًا', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('إضافة شعبة', 'school-system'); ?></h2>
    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
        <?php wp_nonce_field('sch_add_class', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_class">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="c-stage"><?php esc_html_e('المرحلة', 'school-system'); ?></label>
                <select id="c-stage" name="stage" required>
                    <?php foreach (SCH_Classes::STAGES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="c-grade"><?php esc_html_e('الصف', 'school-system'); ?></label>
                <input id="c-grade" type="text" name="grade_level" placeholder="<?php esc_attr_e('الرابع', 'school-system'); ?>" required>
            </div>
            <div class="sch-field">
                <label for="c-section"><?php esc_html_e('الشعبة', 'school-system'); ?></label>
                <input id="c-section" type="text" name="section" placeholder="<?php esc_attr_e('أ', 'school-system'); ?>" required>
            </div>
            <div class="sch-field">
                <label for="c-cap"><?php esc_html_e('السعة القصوى', 'school-system'); ?></label>
                <input id="c-cap" type="number" name="capacity" value="30" min="1" max="100">
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('حفظ الشعبة', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
