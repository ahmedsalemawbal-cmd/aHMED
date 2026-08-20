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

$sch_pick_status = isset($_GET['st']) ? sanitize_key(wp_unslash((string) $_GET['st'])) : 'active';
if (!in_array($sch_pick_status, ['active', 'transferred', 'withdrawn', 'graduated'], true)) {
    $sch_pick_status = 'active';
}

$list = SCH_Students::list([
    'search'      => $q,
    'status'      => $sch_pick_status,
    'stage'       => $stage,
    'grade_level' => $grade,
    'class_id'    => $class_id,
    'view'        => $view,
    'per_page'    => 25,
    'page'        => SCH_Table::page(),
] + SCH_Table::order_args());

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
// صفّ واحد: العنوان · منسدلة العروض · التصدير · الاستيراد · الفعل الأساسي.
// كانت ثلاثة أسطر تدفع الجدول تحت الطيّة.
$sch_tools = SCH_Views::menu('students', $view)
    . '<a class="sch-btn sch-btn--quiet sch-noprint" href="' . esc_url(add_query_arg('sch_export', 'csv')) . '">'
    . sch_icon('upload', 15)
    . esc_html__('تصدير CSV', 'school-system')
    . '</a>'
    . '<a class="sch-btn sch-btn--quiet sch-noprint" href="' . esc_url(SCH_Dashboard::url('import')) . '">'
    . sch_icon('download', 15)
    . esc_html__('استيراد', 'school-system')
    . '</a>';

SCH_Modal::head(
    __('الطلاب', 'school-system'),
    '',
    $classes !== [] ? 'sch-enroll' : '',
    __('تسجيل طالب', 'school-system'),
    'plus',
    $sch_tools
);

// ═══ شرائح الحالة ═══
// «٥ طالبًا نشطًا» جملةٌ تُقرأ ولا يُضغط عليها. والشرائح تقول العدد وتصفّي
// به معًا — والصفر يبقى معروضًا فلا تتغيّر خريطة الشاشة بين زيارتين.
$sch_counts = SCH_Students::status_counts();
$sch_status = isset($_GET['st']) ? sanitize_key(wp_unslash((string) $_GET['st'])) : 'active';

if (!isset($sch_counts[$sch_status])) {
    $sch_status = 'active';
}

$sch_slabels = [
    'active'      => __('نشط', 'school-system'),
    'transferred' => __('منقول', 'school-system'),
    'withdrawn'   => __('منسحب', 'school-system'),
    'graduated'   => __('متخرّج', 'school-system'),
];
?>

<div class="sch-stu__chips" role="group" aria-label="<?php esc_attr_e('تصفية بحالة القيد', 'school-system'); ?>">
    <?php foreach ($sch_slabels as $sch_k => $sch_l) : ?>
        <a class="sch-stu__chip sch-stu__chip--<?php echo esc_attr($sch_k); ?><?php echo $sch_status === $sch_k ? ' is-on' : ''; ?>"
           href="<?php echo esc_url(add_query_arg('st', $sch_k, SCH_Dashboard::url('students'))); ?>"
           aria-current="<?php echo $sch_status === $sch_k ? 'true' : 'false'; ?>">
            <span class="sch-stu__dot" aria-hidden="true"></span>
            <span><?php echo esc_html($sch_l); ?></span>
            <b><?php echo esc_html(number_format_i18n($sch_counts[$sch_k])); ?></b>
        </a>
    <?php endforeach; ?>
</div>

<!-- المرشّحات في بطاقة واحدة: يبحث الموظف بما يعرفه لا بما نرتّبه له -->
<form method="get" class="sch-card sch-filters">
    <input type="hidden" name="sch_section" value="students">
    <?php /* الشريحة تُحمَل مع التصفية: من صفّى «المنسحبين» ثم بحث باسم لا
             يُعاد إلى «النشطين» بلا أن يطلب. */ ?>
    <input type="hidden" name="st" value="<?php echo esc_attr($sch_status); ?>">

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
        <?php $sch_filtered = ($q !== '' || $stage !== '' || $grade !== '' || $class_id > 0); ?>
        <p><?php echo esc_html($sch_filtered
            ? __('لا نتائج تطابق التصفية. جرّب مسحها.', 'school-system')
            : __('سجّل أول طالب لتبدأ.', 'school-system')); ?></p>
        <?php /* حالة فارغة تقول «افعل» تحتاج ما يُضغط — والفاتح معرَّف في هذا الملف */ ?>
        <?php if ($sch_filtered) : ?>
            <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(SCH_Dashboard::url('students')); ?>">
                <?php esc_html_e('مسح التصفية', 'school-system'); ?>
            </a>
        <?php elseif ($classes !== []) : ?>
            <button type="button" class="sch-btn" data-modal-open="sch-enroll">
                <?php esc_html_e('سجّل أول طالب', 'school-system'); ?>
            </button>
        <?php endif; ?>
    </div>
<?php else : ?>
    <?php /* كانت بطاقةً كاملة بارتفاع ١٣٠ بكسل لسطرٍ واحد — تدفع الجدول
             تحت الطيّة مقابل جملةٍ تُقرأ مرة. صارت شريطًا فوق الجدول داخل
             البطاقة نفسها. */ ?>
    <div class="sch-stu__wrap">
        <div class="sch-stu__pick sch-noprint">
            <label class="sch-stu__all">
                <?php SCH_Bulk::pick_all(); ?>
                <span><?php esc_html_e('تحديد الكل', 'school-system'); ?></span>
            </label>
            <span class="sch-stu__hint"><?php esc_html_e('اضغط صفًّا لفتح ملف الطالب · Shift للتحديد المتّصل', 'school-system'); ?></span>
        </div>

    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th class="sch-th-pick"><?php SCH_Bulk::pick_all(); ?></th>
                <?php echo SCH_Table::th(__('الطالب', 'school-system'), 'name'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php echo SCH_Table::th(__('الشعبة', 'school-system'), 'grade'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
                            <?php /* الحرف الأول يسبق الاسم: العين تمسك موضع الصفّ
                                     بلونٍ وشكل قبل أن تقرأ أربع كلمات متشابهة. */ ?>
                            <span class="sch-stu__who">
                                <span class="sch-stu__av" aria-hidden="true"><?php
                                    echo esc_html(mb_substr(trim(SCH_Enrollment::full_name($st)), 0, 1));
                                ?></span>
                                <span class="sch-stu__whot">
                                    <?php /* الاسم يفتح اللوح لا الشاشة الكاملة: أكثر
                                             ما يُسأل عنه (وليّ الأمر · الرسوم · الحضور)
                                             يُجاب على مكانه، والملف الكامل بزرٍّ فيه. */ ?>
                                    <a href="<?php echo esc_url(add_query_arg('open', (int) $st->id)); ?>">
                                        <?php echo esc_html(SCH_Enrollment::full_name($st)); ?>
                                    </a>
                                    <span class="sch-sub sch-block sch-mono"><?php echo esc_html((string) $st->academic_no); ?></span>
                                </span>
                            </span>
                        </td>

                        <td>
                            <?php echo esc_html($st->cls_grade
                                ? trim((string) $st->cls_grade . ' / ' . (string) $st->cls_section)
                                : '—'); ?>
                        </td>

                        <td class="sch-col--lg">
                            <?php if ((string) ($st->guardian_name ?? '') !== '') : ?>
                                <span class="sch-stu__g">
                                    <b><?php echo esc_html((string) $st->guardian_name); ?></b>
                                    <?php if ((string) ($st->guardian_phone ?? '') !== '') : ?>
                                        <em class="sch-mono" dir="ltr"><?php echo esc_html((string) $st->guardian_phone); ?></em>
                                    <?php endif; ?>
                                </span>
                            <?php else : ?>
                                <?php /* «—» تقول «لا بيانات»، والحقيقة أخطر: بلا وليّ أمر
                                         مرتبط لا يصل إشعارٌ واحد عن هذا الطالب. */ ?>
                                <a class="sch-stu__link" href="<?php echo esc_url(SCH_Dashboard::url('guardians')); ?>">
                                    <?php esc_html_e('اربط وليَّ أمر', 'school-system'); ?>
                                </a>
                            <?php endif; ?>
                        </td>

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
                                <a href="<?php echo esc_url(add_query_arg('student', (int) $st->id, SCH_Dashboard::url('certificates'))); ?>"
                                   title="<?php esc_attr_e('إصدار شهادة', 'school-system'); ?>"
                                   aria-label="<?php esc_attr_e('إصدار شهادة', 'school-system'); ?>">
                                    <?php echo sch_icon('award', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div>

    <?php echo SCH_Table::pager($total, 25); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php endif; ?>

<?php
// ═══ لوح ملف الطالب ═══
// الصفّ يفتح ملفًّا كاملًا في شاشةٍ أخرى، فالموظف الذي يريد رقم وليّ الأمر
// وحده يخرج من قائمته ثم يعود إليها. واللوح يجيب أكثر الأسئلة على مكانه،
// والملف الكامل يبقى بزرٍّ فيه.
//
// ومن الخادم لا من المتصفّح: نصاب الحضور والرسوم وآخر الأحداث استعلاماتٌ
// لكل طالب، ولا يُدفع ثمنها إلا حين يُفتح اللوح على واحدٍ بعينه.
$sch_open = isset($_GET['open']) ? absint($_GET['open']) : 0;
$sch_one  = $sch_open > 0 ? SCH_Students::get($sch_open) : null;

if ($sch_one) :
    $sch_name  = SCH_Enrollment::full_name($sch_one);
    $sch_gs    = SCH_Guardians::of_student($sch_open);
    $sch_g1    = $sch_gs[0] ?? null;
    $sch_rate  = SCH_Attendance::rate($sch_open);
    $sch_due   = 0.0;

    foreach (SCH_Finance::of_student($sch_open) as $sch_inv) {
        $sch_due += (float) ($sch_inv->balance ?? 0);
    }

    $sch_events = SCH_Enrollment::timeline($sch_open, 5);
    $sch_klass  = SCH_Students::current_class($sch_open);
    ?>
<div class="sch-stu__veil">
    <a class="sch-stu__veilx" href="<?php echo esc_url(remove_query_arg('open')); ?>"
       aria-label="<?php esc_attr_e('إغلاق اللوح', 'school-system'); ?>"></a>

    <aside class="sch-stu__panel" role="dialog" aria-modal="true" aria-labelledby="sch-stu-ptitle">
        <div class="sch-stu__phd">
            <span class="sch-stu__pav" aria-hidden="true"><?php echo esc_html(mb_substr(trim($sch_name), 0, 1)); ?></span>
            <span class="sch-stu__pt">
                <strong id="sch-stu-ptitle"><?php echo esc_html($sch_name); ?></strong>
                <span>
                    <em class="sch-mono" dir="ltr"><?php echo esc_html((string) $sch_one->academic_no); ?></em>
                    <em><?php echo esc_html($sch_klass ? SCH_Classes::label($sch_klass) : __('بلا شعبة', 'school-system')); ?></em>
                    <em><?php echo esc_html(SCH_Custody::state_label((string) ($sch_one->custody_state ?? ''))); ?></em>
                </span>
            </span>
            <a class="sch-stu__px" href="<?php echo esc_url(remove_query_arg('open')); ?>"
               aria-label="<?php esc_attr_e('إغلاق', 'school-system'); ?>"><?php echo sch_icon('x', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
        </div>

        <div class="sch-stu__pbody">
            <div class="sch-stu__metrics">
                <span class="sch-stu__m">
                    <em><?php esc_html_e('الحضور — ٦٠ يومًا', 'school-system'); ?></em>
                    <b><?php echo esc_html(number_format_i18n($sch_rate, 1) . '٪'); ?></b>
                </span>
                <span class="sch-stu__m">
                    <em><?php esc_html_e('المتبقي من الرسوم', 'school-system'); ?></em>
                    <b<?php echo $sch_due > 0 ? ' class="is-due"' : ''; ?>><?php echo esc_html(sch_money($sch_due)); ?></b>
                </span>
                <span class="sch-stu__m">
                    <em><?php esc_html_e('النقل المدرسي', 'school-system'); ?></em>
                    <b><?php echo esc_html((string) ($sch_one->route_name ?? '') ?: __('لا يشترك', 'school-system')); ?></b>
                </span>
            </div>

            <section class="sch-stu__sec">
                <h3><?php esc_html_e('ولي الأمر', 'school-system'); ?></h3>
                <?php if ($sch_g1) : ?>
                    <div class="sch-stu__grow">
                        <span class="sch-stu__gav" aria-hidden="true"><?php echo esc_html(mb_substr(trim((string) $sch_g1->display_name), 0, 1)); ?></span>
                        <span class="sch-stu__gt">
                            <b><?php echo esc_html((string) $sch_g1->display_name); ?></b>
                            <em class="sch-mono" dir="ltr"><?php echo esc_html((string) ($sch_g1->user_login ?? '')); ?></em>
                        </span>
                        <a class="sch-btn sch-btn--quiet sch-btn--sm" href="<?php echo esc_url(SCH_Dashboard::url('messages')); ?>">
                            <?php esc_html_e('مراسلة', 'school-system'); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <div class="sch-stu__warn">
                        <span><?php esc_html_e('لا حساب وليّ أمر مرتبط — الإشعارات لا تصل.', 'school-system'); ?></span>
                        <a class="sch-btn sch-btn--sm" href="<?php echo esc_url(SCH_Dashboard::url('guardians')); ?>">
                            <?php esc_html_e('ربط', 'school-system'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <section class="sch-stu__sec">
                <h3><?php esc_html_e('آخر الأحداث', 'school-system'); ?></h3>
                <?php if ($sch_events === []) : ?>
                    <p class="sch-sub"><?php esc_html_e('لا أحداث مسجّلة بعد.', 'school-system'); ?></p>
                <?php else : ?>
                    <ul class="sch-stu__ev">
                        <?php foreach ($sch_events as $sch_e) : ?>
                            <li>
                                <span class="sch-stu__evd" aria-hidden="true"></span>
                                <span><?php echo esc_html((string) $sch_e['title']); ?></span>
                                <em class="sch-mono" dir="ltr"><?php echo esc_html(substr((string) $sch_e['when'], 0, 16)); ?></em>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="sch-stu__sec">
                <h3><?php esc_html_e('بيانات التسجيل', 'school-system'); ?></h3>
                <dl class="sch-stu__fields">
                    <?php
                    $sch_rows = [
                        __('رقم الطالب', 'school-system')  => (string) ($sch_one->student_no ?? ''),
                        __('رقم الهوية', 'school-system')  => (string) ($sch_one->national_id ?? ''),
                        __('الجنسية', 'school-system')     => (string) ($sch_one->nationality ?? ''),
                        __('تاريخ الميلاد', 'school-system') => (string) ($sch_one->birth_date ?? ''),
                    ];
                    foreach ($sch_rows as $sch_k => $sch_v) :
                        ?>
                        <div>
                            <dt><?php echo esc_html($sch_k); ?></dt>
                            <dd><?php echo esc_html($sch_v !== '' ? $sch_v : '—'); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        </div>

        <div class="sch-stu__pft">
            <a class="sch-btn sch-btn--block" href="<?php echo esc_url(SCH_Dashboard::url('students', $sch_open)); ?>">
                <?php esc_html_e('الملف الكامل', 'school-system'); ?>
            </a>
            <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(SCH_Dashboard::url('badges')); ?>"><?php esc_html_e('بطاقة', 'school-system'); ?></a>
            <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg('student', $sch_open, SCH_Dashboard::url('certificates'))); ?>"><?php esc_html_e('شهادة', 'school-system'); ?></a>
        </div>
    </aside>
</div>
<?php endif; ?>

<?php SCH_Bulk::bar('students'); ?>

<?php if ($classes !== []) : ?>
    <?php SCH_Modal::open('sch-enroll', __('تسجيل طالب جديد', 'school-system'), __('أربع خطوات — والملف يُكمَل لاحقًا', 'school-system')); ?>
        <?php require SCH_PATH . 'frontend/views/partials/enroll-form.php'; ?>
    <?php SCH_Modal::close(); ?>
<?php endif; ?>
