<?php
/**
 * الطلاب — القائمة والتسجيل في شاشة واحدة.
 *
 * التسجيل نافذة تُفتح فوق القائمة لا شاشة تنقلك عنها: الموظف الذي يسجّل
 * طالبًا متأخرًا يريد العودة لمكانه، لا أن يبحث عن طريقه إليه.
 *
 * والشاشة تجيب سؤال الصباح قبل سؤال الأرشيف: **من داخل المدرسة الآن، ومن
 * تأخّر أو غاب، ومن ليس له وليّ أمر مرتبط.** الشرائح تقول العدد وتفتحه،
 * والصفّ يفتح لوحًا يجيب أكثر ما يُسأل عنه بلا مغادرة القائمة.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$classes = SCH_Classes::list();
$routes  = SCH_Routes::all();
// نموذج التسجيل المشترك يقرأ هذا المتغيّر — وحذفه يترك حقل السجل الصحي
// يُبنى على متغيّرٍ غير معرَّف.
$can_health = current_user_can('sch_view_health');

$q        = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
$stage    = isset($_GET['stage']) ? sanitize_key(wp_unslash((string) $_GET['stage'])) : '';
$grade    = isset($_GET['g']) ? sanitize_text_field(wp_unslash((string) $_GET['g'])) : '';
$class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : 0;
$view     = isset($_GET['view']) ? sanitize_key(wp_unslash((string) $_GET['view'])) : '';
$state    = isset($_GET['now']) ? sanitize_key(wp_unslash((string) $_GET['now'])) : '';

if (!in_array($state, ['in', 'flag', 'nog'], true)) {
    $state = '';
}

$per_page = 25;

$list = SCH_Students::list([
    'search'      => $q,
    'status'      => 'active',
    'stage'       => $stage,
    'grade_level' => $grade,
    'class_id'    => $class_id,
    'view'        => $view,
    'state'       => $state,
    'per_page'    => $per_page,
    'page'        => SCH_Table::page(),
] + SCH_Table::order_args());

$total  = (int) ($list['total'] ?? count($list['items']));
$counts = SCH_Students::today_counts();
$base   = SCH_Dashboard::url('students');
$dirty  = ($q !== '' || $stage !== '' || $grade !== '' || $class_id > 0 || $view !== '' || $state !== '');

// الصفوف المتاحة داخل المرحلة المختارة — لا نعرض صفوفًا لا طلاب فيها.
$grades = [];
foreach ($classes as $c) {
    if ($stage === '' || $c->stage === $stage) {
        $grades[(string) $c->grade_level] = (string) $c->grade_level;
    }
}
ksort($grades);

// شرائح الرأس: الإجمالي وثلاثة أسئلةٍ تُفتح بالضغط. و«بلا وليّ أمر» أهمّها —
// طالبٌ بلا حساب مرتبط لا يصل عنه إشعارٌ واحد، وهي الشريحة التي تُصلَح فعلًا.
$sch_chips = [
    ['key' => '',     'label' => __('إجمالي', 'school-system'),       'n' => $counts['total'], 'tone' => 'all'],
    ['key' => 'in',   'label' => __('داخل المدرسة', 'school-system'), 'n' => $counts['in'],    'tone' => 'in'],
    ['key' => 'flag', 'label' => __('غياب وتأخّر', 'school-system'),  'n' => $counts['late'] + $counts['absent'], 'tone' => 'flag'],
    ['key' => 'nog',  'label' => __('بلا وليّ أمر', 'school-system'), 'n' => $counts['nog'],   'tone' => 'nog'],
];

/** رابط الشاشة مع تغيير معامل واحد وإسقاط الترقيم واللوح. */
$sch_link = static function (array $changes) use ($base): string {
    $args = $_GET;
    unset($args['sch_dash'], $args['sch_section'], $args['sch_id'], $args['paged'], $args['open']);

    foreach ($changes as $k => $v) {
        if ($v === '' || $v === 0) {
            unset($args[$k]);
        } else {
            $args[$k] = (string) $v;
        }
    }

    return add_query_arg(array_map('sanitize_text_field', array_map('strval', $args)), $base);
};

// سهم الفرز على الاسم — الرأس زرٌّ لا خليّة جدول، فيُبنى هنا.
$sch_ob   = isset($_GET['orderby']) ? sanitize_key(wp_unslash((string) $_GET['orderby'])) : '';
$sch_dir  = (isset($_GET['order']) && strtoupper((string) $_GET['order']) === 'DESC') ? 'DESC' : 'ASC';
$sch_on   = ($sch_ob === 'name');
$sch_next = ($sch_on && $sch_dir === 'ASC') ? 'DESC' : 'ASC';
?>

<?php
$sch_tools = SCH_Views::menu('students', $view)
    . '<a class="sch-btn sch-btn--quiet sch-noprint" href="' . esc_url(add_query_arg('sch_export', 'csv')) . '">'
    . sch_icon('upload', 15) . esc_html__('تصدير CSV', 'school-system') . '</a>'
    . '<a class="sch-btn sch-btn--quiet sch-noprint" href="' . esc_url(SCH_Dashboard::url('import')) . '">'
    . sch_icon('download', 15) . esc_html__('استيراد', 'school-system') . '</a>';

SCH_Modal::head(
    __('الطلاب', 'school-system'),
    '',
    $classes !== [] ? 'sch-enroll' : '',
    __('تسجيل طالب', 'school-system'),
    'plus',
    $sch_tools
);
?>

<div class="sch-stu__chips">
    <?php foreach ($sch_chips as $sch_i => $sch_c) : ?>
        <a class="sch-stu__chip sch-stu__chip--<?php echo esc_attr($sch_c['tone']); ?><?php echo $state === $sch_c['key'] ? ' is-on' : ''; ?>"
           href="<?php echo esc_url($sch_link(['now' => $sch_c['key']])); ?>"
           aria-current="<?php echo $state === $sch_c['key'] ? 'true' : 'false'; ?>">
            <span class="sch-stu__dot" aria-hidden="true"></span>
            <span class="sch-stu__cl"><?php echo esc_html($sch_c['label']); ?></span>
            <b><?php echo esc_html(number_format_i18n($sch_c['n'])); ?></b>
        </a>
        <?php if ($sch_i < count($sch_chips) - 1) : ?>
            <span class="sch-stu__csep" aria-hidden="true"></span>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- شريط أدوات واحد: بحثٌ وثلاث قوائم — لا بطاقة تصفية بارتفاع نصف شاشة -->
<form method="get" class="sch-stu__bar" id="sch-stu-filters">
    <input type="hidden" name="sch_section" value="students">
    <?php if ($state !== '') : ?>
        <input type="hidden" name="now" value="<?php echo esc_attr($state); ?>">
    <?php endif; ?>

    <div class="sch-stu__find">
        <label class="sch-sr" for="f-s"><?php esc_html_e('بحث', 'school-system'); ?></label>
        <input id="f-s" type="search" name="s" value="<?php echo esc_attr($q); ?>"
               placeholder="<?php esc_attr_e('الاسم أو الرقم الأكاديمي أو رقم الهوية', 'school-system'); ?>">
        <span class="sch-stu__findic" aria-hidden="true"><?php echo sch_icon('search', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
    </div>

    <label class="sch-sr" for="f-stage"><?php esc_html_e('المرحلة', 'school-system'); ?></label>
    <select id="f-stage" name="stage" data-go>
        <option value=""><?php esc_html_e('كل المراحل', 'school-system'); ?></option>
        <?php foreach (SCH_Classes::STAGES as $sch_slug => $sch_label) : ?>
            <option value="<?php echo esc_attr($sch_slug); ?>" <?php selected($stage, $sch_slug); ?>>
                <?php echo esc_html($sch_label); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="sch-sr" for="f-g"><?php esc_html_e('الصف', 'school-system'); ?></label>
    <select id="f-g" name="g" data-go>
        <option value=""><?php esc_html_e('كل الصفوف', 'school-system'); ?></option>
        <?php foreach ($grades as $sch_g) : ?>
            <option value="<?php echo esc_attr($sch_g); ?>" <?php selected($grade, $sch_g); ?>>
                <?php echo esc_html($sch_g); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="sch-sr" for="f-c"><?php esc_html_e('الشعبة', 'school-system'); ?></label>
    <select id="f-c" name="class_id" data-go>
        <option value=""><?php esc_html_e('كل الشعب', 'school-system'); ?></option>
        <?php foreach ($classes as $c) : ?>
            <?php if ($stage === '' || $c->stage === $stage) : ?>
                <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class_id, (int) $c->id); ?>>
                    <?php echo esc_html(SCH_Classes::label($c)); ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>

    <button class="sch-btn sch-btn--quiet sch-stu__go"><?php esc_html_e('تصفية', 'school-system'); ?></button>

    <?php if ($dirty) : ?>
        <a class="sch-stu__clear" href="<?php echo esc_url($base); ?>"><?php esc_html_e('إزالة التصفية', 'school-system'); ?></a>
    <?php endif; ?>
</form>

<div class="sch-stu__wrap">
    <?php /* شريط التحديد والأفعال — المحرّك الجماعي نفسه (`SCH_Bulk`) في
             موضعه من التصميم: داخل رأس البطاقة لا شريطًا طافيًا فوق الشاشة. */ ?>
    <div class="sch-stu__pick sch-noprint">
        <label class="sch-stu__all">
            <?php SCH_Bulk::pick_all(); ?>
            <span><?php esc_html_e('تحديد الكل', 'school-system'); ?></span>
        </label>
        <span class="sch-stu__hint"><?php esc_html_e('اضغط صفًّا لعرض ملف الطالب · Shift للتحديد المتّصل', 'school-system'); ?></span>
        <?php SCH_Bulk::bar('students'); ?>
    </div>

    <div class="sch-stu__scroll">
        <div class="sch-stu__grid">
            <div class="sch-stu__row sch-stu__row--head">
                <span></span>
                <a class="sch-stu__sort<?php echo $sch_on ? ' is-on' : ''; ?>"
                   href="<?php echo esc_url($sch_link(['orderby' => 'name', 'order' => $sch_next])); ?>">
                    <?php esc_html_e('الطالب', 'school-system'); ?>
                    <span aria-hidden="true"><?php echo esc_html($sch_on ? ($sch_dir === 'ASC' ? '▲' : '▼') : '⇅'); ?></span>
                </a>
                <span class="sch-stu__th"><?php esc_html_e('الشعبة', 'school-system'); ?></span>
                <span class="sch-stu__th"><?php esc_html_e('ولي الأمر', 'school-system'); ?></span>
                <span class="sch-stu__th"><?php esc_html_e('النقل', 'school-system'); ?></span>
                <span class="sch-stu__th"><?php esc_html_e('الحالة الآن', 'school-system'); ?></span>
                <span class="sch-stu__th"><?php esc_html_e('إجراء', 'school-system'); ?></span>
            </div>

            <?php foreach ($list['items'] as $st) :
                $sch_id   = (int) $st->id;
                $sch_name = SCH_Enrollment::full_name($st);
                $sch_now  = SCH_Students::now_state($st);
                ?>
                <div class="sch-stu__row<?php echo $sch_now['key'] === 'absent' ? ' is-absent' : ''; ?>" data-row>
                    <span class="sch-stu__cb"><?php SCH_Bulk::pick($sch_id); ?></span>

                    <span class="sch-stu__who">
                        <span class="sch-stu__av" aria-hidden="true"><?php echo esc_html(mb_substr(trim($sch_name), 0, 1)); ?></span>
                        <span class="sch-stu__whot">
                            <a class="sch-stu__name" href="<?php echo esc_url(add_query_arg('open', $sch_id)); ?>">
                                <?php echo esc_html($sch_name); ?>
                            </a>
                            <span class="sch-stu__no sch-mono" dir="ltr"><?php echo esc_html((string) $st->academic_no); ?></span>
                        </span>
                    </span>

                    <span class="sch-stu__sec"><?php echo esc_html($st->cls_grade
                        ? trim((string) $st->cls_grade . ' / ' . (string) $st->cls_section)
                        : '—'); ?></span>

                    <span class="sch-stu__g">
                        <?php if ((string) ($st->guardian_name ?? '') !== '') : ?>
                            <b><?php echo esc_html((string) $st->guardian_name); ?></b>
                            <?php if ((string) ($st->guardian_phone ?? '') !== '') : ?>
                                <em class="sch-mono" dir="ltr"><?php echo esc_html((string) $st->guardian_phone); ?></em>
                            <?php endif; ?>
                        <?php else : ?>
                            <?php /* «—» تقول «لا بيانات»، والحقيقة أخطر: بلا وليّ أمر
                                     مرتبط لا يصل إشعارٌ واحد عن هذا الطالب. */ ?>
                            <a class="sch-stu__link" href="<?php echo esc_url(SCH_Dashboard::url('guardians')); ?>">
                                <?php esc_html_e('ربط وليّ أمر', 'school-system'); ?>
                            </a>
                        <?php endif; ?>
                    </span>

                    <span class="sch-stu__bus<?php echo (string) ($st->route_name ?? '') === '' ? ' is-off' : ''; ?>">
                        <?php echo esc_html((string) ($st->route_name ?? '') ?: '—'); ?>
                    </span>

                    <span class="sch-stu__now sch-stu__now--<?php echo esc_attr($sch_now['key']); ?>">
                        <span class="sch-stu__nowd" aria-hidden="true"></span>
                        <span><?php echo esc_html($sch_now['label']); ?></span>
                    </span>

                    <span class="sch-stu__acts">
                        <a href="<?php echo esc_url(SCH_Dashboard::url('badges')); ?>"
                           title="<?php esc_attr_e('بطاقة الطالب', 'school-system'); ?>"
                           aria-label="<?php esc_attr_e('بطاقة الطالب', 'school-system'); ?>">
                            <?php echo sch_icon('badge', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('student', $sch_id, SCH_Dashboard::url('certificates'))); ?>"
                           title="<?php esc_attr_e('إصدار شهادة', 'school-system'); ?>"
                           aria-label="<?php esc_attr_e('إصدار شهادة', 'school-system'); ?>">
                            <?php echo sch_icon('award', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </a>
                        <a href="<?php echo esc_url(SCH_Dashboard::url('students', $sch_id)); ?>"
                           title="<?php esc_attr_e('ملف الطالب', 'school-system'); ?>"
                           aria-label="<?php esc_attr_e('ملف الطالب', 'school-system'); ?>">
                            <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </a>
                    </span>
                </div>
            <?php endforeach; ?>

            <?php if ($list['items'] === []) : ?>
                <div class="sch-stu__empty">
                    <strong><?php echo esc_html($dirty
                        ? __('لا طلاب مطابقين', 'school-system')
                        : __('لا طلاب بعد', 'school-system')); ?></strong>
                    <span><?php echo esc_html($dirty
                        ? __('جرّب اسمًا آخر أو أزل التصفية.', 'school-system')
                        : __('سجّل أول طالب لتبدأ.', 'school-system')); ?></span>
                    <?php if ($dirty) : ?>
                        <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url($base); ?>">
                            <?php esc_html_e('إزالة التصفية', 'school-system'); ?>
                        </a>
                    <?php elseif ($classes !== []) : ?>
                        <button type="button" class="sch-btn" data-modal-open="sch-enroll">
                            <?php esc_html_e('سجّل أول طالب', 'school-system'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $sch_pages = max(1, (int) ceil($total / $per_page));
    $sch_page  = min(max(1, SCH_Table::page()), $sch_pages);
    $sch_from  = $total === 0 ? 0 : (($sch_page - 1) * $per_page) + 1;
    $sch_to    = min($total, $sch_page * $per_page);
    ?>
    <div class="sch-stu__foot">
        <span class="sch-stu__range"><?php echo esc_html($total > 0 ? sprintf(
            /* translators: 1: من 2: إلى 3: المجموع */
            __('عرض %1$s–%2$s من %3$s طالبًا', 'school-system'),
            number_format_i18n($sch_from),
            number_format_i18n($sch_to),
            number_format_i18n($total)
        ) : __('لا صفوف', 'school-system')); ?></span>

        <?php if ($sch_pages > 1) : ?>
            <div class="sch-stu__pages">
                <a class="sch-stu__page sch-stu__page--arrow<?php echo $sch_page <= 1 ? ' is-off' : ''; ?>"
                   href="<?php echo esc_url($sch_link(['paged' => max(1, $sch_page - 1)])); ?>"
                   aria-label="<?php esc_attr_e('الصفحة السابقة', 'school-system'); ?>">›</a>
                <?php for ($sch_p = 1; $sch_p <= $sch_pages; $sch_p++) : ?>
                    <a class="sch-stu__page<?php echo $sch_p === $sch_page ? ' is-on' : ''; ?>"
                       href="<?php echo esc_url($sch_link(['paged' => $sch_p])); ?>"><?php echo esc_html(number_format_i18n($sch_p)); ?></a>
                <?php endfor; ?>
                <a class="sch-stu__page sch-stu__page--arrow<?php echo $sch_page >= $sch_pages ? ' is-off' : ''; ?>"
                   href="<?php echo esc_url($sch_link(['paged' => min($sch_pages, $sch_page + 1)])); ?>"
                   aria-label="<?php esc_attr_e('الصفحة التالية', 'school-system'); ?>">‹</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// ═══ لوح ملف الطالب ═══
// الصفّ كان يفتح ملفًّا كاملًا في شاشةٍ أخرى، فالموظف الذي يريد رقم وليّ
// الأمر وحده يخرج من قائمته ثم يعود إليها. واللوح يجيب أكثر الأسئلة على
// مكانه، والملف الكامل يبقى بزرٍّ فيه.
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
    $sch_dnow   = SCH_Students::now_state($sch_one);
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
                    <span class="sch-stu__now sch-stu__now--<?php echo esc_attr($sch_dnow['key']); ?>">
                        <span class="sch-stu__nowd" aria-hidden="true"></span>
                        <span><?php echo esc_html($sch_dnow['label']); ?></span>
                    </span>
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

            <section class="sch-stu__sec2">
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

            <section class="sch-stu__sec2">
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

            <section class="sch-stu__sec2">
                <h3><?php esc_html_e('بيانات التسجيل', 'school-system'); ?></h3>
                <dl class="sch-stu__fields">
                    <?php
                    $sch_rows = [
                        __('رقم الطالب', 'school-system')    => (string) ($sch_one->student_no ?? ''),
                        __('رقم الهوية', 'school-system')    => (string) ($sch_one->national_id ?? ''),
                        __('الجنسية', 'school-system')       => (string) ($sch_one->nationality ?? ''),
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

<?php if ($classes !== []) : ?>
    <?php SCH_Modal::open('sch-enroll', __('تسجيل طالب جديد', 'school-system'), __('أربع خطوات — والملف يُكمَل لاحقًا', 'school-system')); ?>
        <?php require SCH_PATH . 'frontend/views/partials/enroll-form.php'; ?>
    <?php SCH_Modal::close(); ?>
<?php endif; ?>

<script>
/* القوائم تُصفّي عند الاختيار — وزرّ «تصفية» يبقى في الصفحة لمن لا
   جافاسكربت عنده، ويُخفى هنا فلا يبدو زرًّا لا لزوم له. */
(function () {
  var form = document.getElementById('sch-stu-filters');
  if (!form) { return; }

  form.querySelectorAll('[data-go]').forEach(function (s) {
    s.addEventListener('change', function () { form.submit(); });
  });

  var go = form.querySelector('.sch-stu__go');
  if (go) { go.hidden = true; }
})();
</script>
