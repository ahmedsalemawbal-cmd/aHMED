<?php
/**
 * الطلاب — القائمة والتسجيل في شاشة واحدة.
 *
 * التسجيل نافذة تُفتح فوق القائمة لا شاشة تنقلك عنها: الموظف الذي يسجّل
 * طالبًا متأخرًا يريد العودة لمكانه، لا أن يبحث عن طريقه إليه.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$classes = SCH_Classes::list();
$routes  = SCH_Routes::all();
$can_health = current_user_can('sch_view_health');

$q        = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
$stage    = isset($_GET['stage']) ? sanitize_key(wp_unslash((string) $_GET['stage'])) : '';
$grade    = isset($_GET['g']) ? sanitize_text_field(wp_unslash((string) $_GET['g'])) : '';
$class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
$view     = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : '';

$list = SCH_Students::list([
    'search'      => $q,
    'status'      => 'active',
    'stage'       => $stage,
    'grade_level' => $grade,
    'class_id'    => $class_id,
    'view'        => $view,
    'per_page'    => 100,
]);

$total = (int) ($list['total'] ?? count($list['items']));

// الصفوف المتاحة داخل المرحلة المختارة — لا نعرض صفوفًا لا طلاب فيها.
$grades = [];
foreach ($classes as $c) {
    if ($stage === '' || $c->stage === $stage) {
        $grades[(string) $c->grade_level] = (string) $c->grade_level;
    }
}
ksort($grades);
?>

<?php
SCH_Modal::head(
    __('الطلاب', 'school-system'),
    sprintf(
        /* translators: %s: عدد الطلاب */
        __('%s طالبًا نشطًا', 'school-system'),
        number_format_i18n($total)
    ),
    $classes !== [] ? 'sch-enroll' : '',
    __('تسجيل طالب', 'school-system')
);
?>

<?php SCH_Views::render('students', $view); ?>

<p class="sch-noprint">
    <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg('sch_export', 'csv')); ?>">
        <?php echo sch_icon('upload', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <?php esc_html_e('تصدير CSV', 'school-system'); ?>
    </a>
</p>

<!-- المرشّحات في بطاقة واحدة: يبحث الموظف بما يعرفه لا بما نرتّبه له -->
<form method="get" class="sch-card sch-filters">
    <input type="hidden" name="sch_section" value="students">

    <div class="sch-filters__row">
        <div class="sch-field sch-field--grow">
            <label for="f-s"><?php esc_html_e('بحث', 'school-system'); ?></label>
            <input id="f-s" type="search" name="s" value="<?php echo esc_attr($q); ?>"
                   placeholder="<?php esc_attr_e('الاسم أو الرقم الأكاديمي أو رقم الهوية', 'school-system'); ?>">
        </div>

        <div class="sch-field">
            <label for="f-stage"><?php esc_html_e('المرحلة', 'school-system'); ?></label>
            <select id="f-stage" name="stage">
                <option value=""><?php esc_html_e('كل المراحل', 'school-system'); ?></option>
                <?php foreach (SCH_Classes::STAGES as $sch_slug => $sch_label) : ?>
                    <option value="<?php echo esc_attr($sch_slug); ?>" <?php selected($stage, $sch_slug); ?>>
                        <?php echo esc_html($sch_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sch-field">
            <label for="f-g"><?php esc_html_e('الصف', 'school-system'); ?></label>
            <select id="f-g" name="g">
                <option value=""><?php esc_html_e('كل الصفوف', 'school-system'); ?></option>
                <?php foreach ($grades as $sch_g) : ?>
                    <option value="<?php echo esc_attr($sch_g); ?>" <?php selected($grade, $sch_g); ?>>
                        <?php echo esc_html($sch_g); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sch-field">
            <label for="f-c"><?php esc_html_e('الشعبة', 'school-system'); ?></label>
            <select id="f-c" name="class_id">
                <option value=""><?php esc_html_e('كل الشعب', 'school-system'); ?></option>
                <?php foreach ($classes as $c) : ?>
                    <?php if ($stage === '' || $c->stage === $stage) : ?>
                        <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class_id, (int) $c->id); ?>>
                            <?php echo esc_html(SCH_Classes::label($c)); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sch-filters__acts">
            <button class="sch-btn"><?php esc_html_e('تصفية', 'school-system'); ?></button>
            <?php if ($q !== '' || $stage !== '' || $grade !== '' || $class_id > 0 || $view !== '') : ?>
                <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(SCH_Dashboard::url('students')); ?>">
                    <?php esc_html_e('مسح', 'school-system'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($list['items'] === []) : ?>
    <div class="sch-blank">
        <span class="sch-blank__ic"><?php echo sch_icon('users', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <strong><?php esc_html_e('لا طلاب هنا', 'school-system'); ?></strong>
        <p><?php echo esc_html($q !== '' || $stage !== '' || $grade !== '' || $class_id > 0
            ? __('لا نتائج تطابق التصفية. جرّب مسحها.', 'school-system')
            : __('سجّل أول طالب لتبدأ.', 'school-system')); ?></p>
    </div>
<?php else : ?>
    <div class="sch-card sch-noprint">
        <div class="sch-pickbar">
            <?php SCH_Bulk::pick_all(); ?>
            <span><?php esc_html_e('تحديد الكل', 'school-system'); ?></span>
            <span class="sch-sub"><?php esc_html_e('أو اضغط أولها ثم Shift مع آخرها', 'school-system'); ?></span>
        </div>
    </div>

    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th class="sch-th-pick"><?php SCH_Bulk::pick_all(); ?></th>
                <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                <th><?php esc_html_e('الشعبة', 'school-system'); ?></th>
                <th class="sch-col--lg"><?php esc_html_e('ولي الأمر', 'school-system'); ?></th>
                <th class="sch-col--xl"><?php esc_html_e('النقل', 'school-system'); ?></th>
                <th><?php esc_html_e('الحالة الآن', 'school-system'); ?></th>
                <th></th>
            </tr></thead>

            <tbody>
                <?php foreach ($list['items'] as $st) : ?>
                    <tr data-row>
                        <td class="sch-th-pick"><?php SCH_Bulk::pick((int) $st->id); ?></td>

                        <td class="sch-name">
                            <a href="<?php echo esc_url(SCH_Dashboard::url('students', (int) $st->id)); ?>">
                                <?php echo esc_html(SCH_Enrollment::full_name($st)); ?>
                            </a>
                            <span class="sch-sub sch-block sch-mono"><?php echo esc_html((string) $st->academic_no); ?></span>
                        </td>

                        <td>
                            <?php echo esc_html($st->cls_grade
                                ? trim((string) $st->cls_grade . ' / ' . (string) $st->cls_section)
                                : '—'); ?>
                        </td>

                        <td class="sch-col--lg"><?php echo esc_html((string) ($st->guardian_name ?: '—')); ?></td>

                        <td class="sch-col--xl"><?php echo esc_html((string) ($st->route_name ?: '—')); ?></td>

                        <td>
                            <span class="sch-badge sch-badge--muted">
                                <?php echo esc_html(SCH_Custody::state_label((string) $st->custody_state)); ?>
                            </span>
                        </td>

                        <td>
                            <div class="sch-rowacts">
                                <a href="<?php echo esc_url(SCH_Dashboard::url('students', (int) $st->id)); ?>"
                                   title="<?php esc_attr_e('الملف', 'school-system'); ?>"
                                   aria-label="<?php esc_attr_e('الملف', 'school-system'); ?>">
                                    <?php echo sch_icon('badge', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                </a>
                                <a href="<?php echo esc_url(SCH_Dashboard::url('badges')); ?>"
                                   title="<?php esc_attr_e('البطاقة', 'school-system'); ?>"
                                   aria-label="<?php esc_attr_e('البطاقة', 'school-system'); ?>">
                                    <?php echo sch_icon('print', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php SCH_Bulk::bar('students'); ?>

<?php if ($classes !== []) : ?>
    <?php SCH_Modal::open('sch-enroll', __('تسجيل طالب جديد', 'school-system'), __('أربع خطوات — والملف يُكمَل لاحقًا', 'school-system')); ?>
        <?php require SCH_PATH . 'frontend/views/partials/enroll-form.php'; ?>
    <?php SCH_Modal::close(); ?>
<?php endif; ?>
