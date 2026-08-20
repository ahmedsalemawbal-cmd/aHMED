<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * حضور المعلمين — كشف اليوم وتغطية حصص الغائبين.
 *
 * الشاشة تجيب سؤالين لا سؤالًا واحدًا: **من حضر؟** و**من يغطّي حصص من
 * لم يحضر؟** والثاني هو ما يجعلها شاشة وكيلٍ لا كشف حضور: رصد الغياب
 * وحده يترك فصلًا بلا معلم، والنظام يملك الجدول والنصاب والإجازات فليس
 * عذرًا أن يترك الوكيل يبحث.
 *
 * الرصد آليّ بالأصل: الموظف يمسح بطاقته عند البوابة كالطالب تمامًا،
 * فيُحسب تأخّره من وقت المسح. ودور الشاشة أن تُري الاستثناء — من لم
 * يُمسح بعد — لا أن تطلب رصد ستين موظفًا بيد.
 *
 * والتصفية والبحث والصفحات كلها في المتصفّح على كشفٍ واحد مُصيَّر:
 * مدرسة واحدة موظفوها عشرات، وطلبُ صفحةٍ جديدة لكل ضغطة شريحة يجعل
 * العدّادات تومض ويقطع على الوكيل عمله. أما لوح التغطية فمن الخادم
 * (`?cover=`) لأن ترشيح البدلاء استعلامٌ لكل حصة، ولا يُدفع ثمنه إلا
 * حين يُفتح اللوح فعلًا.
 */

$sch_date  = current_time('Y-m-d');
$sch_sheet = SCH_Deputy::staff_day($sch_date);
$sch_total = count($sch_sheet);
$sch_gaps  = SCH_Deputy::open_cover_count($sch_sheet);

// أسماء الحالات كما في التصميم — «بانتظار» حالةٌ قائمة بذاتها لا صفر عدّاد.
$sch_lbl = [
    'present' => __('حاضر', 'school-system'),
    'late'    => __('متأخر', 'school-system'),
    'absent'  => __('غائب', 'school-system'),
    'leave'   => __('إجازة', 'school-system'),
    'none'    => __('بانتظار', 'school-system'),
];

$sch_n     = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'none' => 0];
$sch_depts = [];

foreach ($sch_sheet as $sch_row) {
    $sch_n[(string) $sch_row->state]++;

    $sch_d = trim((string) ($sch_row->department ?? ''));
    if ($sch_d !== '') {
        $sch_depts[$sch_d] = true;
    }
}

$sch_depts = array_keys($sch_depts);
sort($sch_depts);

// حالة الشاشة تعود في الرابط بعد كل حفظ — فلا يُعاد الوكيل لأعلى القائمة.
$sch_f    = isset($_GET['f']) ? sanitize_key(wp_unslash((string) $_GET['f'])) : 'all';
$sch_q    = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
$sch_dept = isset($_GET['dept']) ? sanitize_text_field(wp_unslash((string) $_GET['dept'])) : '';
$sch_pg   = isset($_GET['pg']) ? max(1, absint($_GET['pg'])) : 1;
$sch_open = isset($_GET['cover']) ? absint($_GET['cover']) : 0;

if (!array_key_exists($sch_f, $sch_lbl) && $sch_f !== 'all') {
    $sch_f = 'all';
}

$sch_url  = SCH_Dashboard::url('staff-day');
$sch_done = $sch_n['none'] === 0;

/** حقول الحالة المخفية — تُطبع داخل كل نموذج فيعود المستخدم إلى مكانه. */
$sch_keep = static function () use ($sch_f, $sch_q, $sch_dept, $sch_pg, $sch_open): void {
    $fields = ['f' => $sch_f, 'q' => $sch_q, 'dept' => $sch_dept, 'pg' => (string) $sch_pg, 'cover' => (string) $sch_open];

    foreach ($fields as $key => $value) {
        if ($value === '' || $value === '0' || ($key === 'f' && $value === 'all') || ($key === 'pg' && $value === '1')) {
            continue;
        }
        printf(
            '<input type="hidden" name="keep[%s]" value="%s" data-keep="%s">',
            esc_attr($key),
            esc_attr($value),
            esc_attr($key)
        );
    }
};

// اليوم بالعربية — «الخميس ٢٠ أغسطس».
$sch_today_txt = wp_date('l j F', strtotime($sch_date . ' 12:00:00'));
?>

<header class="sch-sd__hd">
    <div class="sch-sd__ttl">
        <h1 class="sch-title"><?php echo sch_icon('user-check', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('حضور المعلمين', 'school-system'); ?></h1>
        <p class="sch-sub"><?php esc_html_e('البوابة رصدت الأغلب برمز البطاقة — غيّر الاستثناءات من القائمة ثم احفظ. رصد الغياب يولّد مقترحات الاحتياط تلقائيًا.', 'school-system'); ?></p>
    </div>

    <div class="sch-sd__acts">
        <div class="sch-sd__now">
            <span class="sch-sd__day"><?php echo esc_html($sch_today_txt); ?></span>
            <span class="sch-sd__sep" aria-hidden="true"></span>
            <span class="sch-sd__clock" id="sch-sd-clock" dir="ltr">--:--:--</span>
        </div>

        <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg('sch_export', '1', $sch_url)); ?>"><?php esc_html_e('تصدير الكشف', 'school-system'); ?></a>

        <form method="post" class="sch-sd__closef">
            <?php wp_nonce_field('sch_close_staff_day', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="close_staff_day">
            <?php $sch_keep(); ?>
            <button class="sch-btn" <?php disabled($sch_done); ?> data-confirm="<?php esc_attr_e('سيُرصد غياب كل من لم يُمسح بعد. متابعة؟', 'school-system'); ?>">
                <?php echo $sch_done ? esc_html__('الكشف مُغلق', 'school-system') : esc_html__('إغلاق الكشف', 'school-system'); ?>
            </button>
        </form>
    </div>
</header>

<div class="sch-sd__bar">
    <div class="sch-sd__find">
        <label class="sch-sr" for="sch-sd-q"><?php esc_html_e('ابحث في الكشف', 'school-system'); ?></label>
        <input type="search" id="sch-sd-q" value="<?php echo esc_attr($sch_q); ?>"
               placeholder="<?php esc_attr_e('ابحث بالاسم أو الوظيفة أو رقم البطاقة', 'school-system'); ?>">
        <span class="sch-sd__findic" aria-hidden="true"><?php echo sch_icon('search', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
    </div>

    <div class="sch-sd__tabs" role="group" aria-label="<?php esc_attr_e('تصفية بالحالة', 'school-system'); ?>">
        <button type="button" class="sch-sd__tab<?php echo $sch_f === 'all' ? ' is-on' : ''; ?>" data-f="all" aria-pressed="<?php echo $sch_f === 'all' ? 'true' : 'false'; ?>">
            <span class="sch-sd__tdot sch-sd__tdot--all" aria-hidden="true"></span>
            <span class="sch-sd__tlbl"><?php esc_html_e('الكل', 'school-system'); ?></span>
            <span class="sch-sd__tn" data-n="all"><?php echo esc_html(number_format_i18n($sch_total)); ?></span>
        </button>
        <?php foreach ($sch_lbl as $sch_key => $sch_text) : ?>
            <button type="button" class="sch-sd__tab<?php echo $sch_f === $sch_key ? ' is-on' : ''; ?>" data-f="<?php echo esc_attr($sch_key); ?>" aria-pressed="<?php echo $sch_f === $sch_key ? 'true' : 'false'; ?>">
                <span class="sch-sd__tdot sch-sd__tdot--<?php echo esc_attr($sch_key); ?>" aria-hidden="true"></span>
                <span class="sch-sd__tlbl"><?php echo esc_html($sch_text); ?></span>
                <span class="sch-sd__tn" data-n="<?php echo esc_attr($sch_key); ?>"><?php echo esc_html(number_format_i18n($sch_n[$sch_key])); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <label class="sch-sr" for="sch-sd-dept"><?php esc_html_e('تصفية بالقسم', 'school-system'); ?></label>
    <select id="sch-sd-dept" class="sch-sd__dept">
        <option value=""><?php esc_html_e('كل الأقسام', 'school-system'); ?></option>
        <?php foreach ($sch_depts as $sch_d) : ?>
            <option value="<?php echo esc_attr($sch_d); ?>" <?php selected($sch_dept, $sch_d); ?>><?php echo esc_html($sch_d); ?></option>
        <?php endforeach; ?>
    </select>

    <span class="sch-sd__gap<?php echo $sch_gaps > 0 ? ' is-bad' : ' is-ok'; ?>">
        <span class="sch-sd__gapt"><?php esc_html_e('حصص بلا معلم', 'school-system'); ?></span>
        <b class="sch-sd__gapn"><?php echo esc_html(number_format_i18n($sch_gaps)); ?></b>
    </span>
</div>

<?php if ($sch_sheet === []) : ?>
    <div class="sch-blank">
        <span class="sch-blank__ic"><?php echo sch_icon('users', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <strong><?php esc_html_e('لا يوجد موظفون نشطون', 'school-system'); ?></strong>
        <span><?php esc_html_e('أضف الموظفين من شاشة «الموظفون» ليظهر كشف اليوم.', 'school-system'); ?></span>
    </div>
<?php else : ?>

<form method="post" id="sch-sd-form">
    <?php wp_nonce_field('sch_mark_staff_bulk', '_sch_nonce'); ?>
    <input type="hidden" name="sch_action" value="mark_staff_bulk">
    <?php $sch_keep(); ?>

    <div class="sch-sd__card">
        <div class="sch-sd__sel" id="sch-sd-sel" hidden>
            <span class="sch-sd__seln" id="sch-sd-seln"></span>
            <div class="sch-sd__selacts">
                <?php foreach (['present', 'late', 'absent', 'leave'] as $sch_key) : ?>
                    <button class="sch-sd__mark sch-sd__mark--<?php echo esc_attr($sch_key); ?>" name="status" value="<?php echo esc_attr($sch_key); ?>">
                        <span class="sch-sd__tdot sch-sd__tdot--<?php echo esc_attr($sch_key); ?>" aria-hidden="true"></span><?php echo esc_html($sch_lbl[$sch_key]); ?>
                    </button>
                <?php endforeach; ?>
                <button type="button" class="sch-sd__clear" data-clear><?php esc_html_e('إلغاء التحديد', 'school-system'); ?></button>
            </div>
        </div>

        <div class="sch-sd__scroll">
            <div class="sch-sd__row sch-sd__row--head">
                <span class="sch-sd__cb">
                    <input type="checkbox" id="sch-sd-all">
                    <label class="sch-sr" for="sch-sd-all"><?php esc_html_e('تحديد المعروض', 'school-system'); ?></label>
                </span>
                <span class="sch-sd__th"><?php esc_html_e('الموظف', 'school-system'); ?></span>
                <span class="sch-sd__th"><?php esc_html_e('القسم', 'school-system'); ?></span>
                <span class="sch-sd__th"><?php esc_html_e('البصمة', 'school-system'); ?></span>
                <span class="sch-sd__th"><?php esc_html_e('الحالة', 'school-system'); ?></span>
                <span class="sch-sd__th"><?php esc_html_e('حصص اليوم', 'school-system'); ?></span>
                <span class="sch-sd__th"><?php esc_html_e('التغطية', 'school-system'); ?></span>
            </div>

            <?php foreach ($sch_sheet as $sch_row) :
                $sch_uid   = (int) $sch_row->user_id;
                $sch_state = (string) $sch_row->state;
                $sch_name  = (string) $sch_row->display_name;
                $sch_time  = $sch_row->checked_at ? substr((string) $sch_row->checked_at, 11, 5) : '';

                // النجمة تقول «هذا قرار إنسان لا مسح بطاقة» — والفرق يهمّ
                // حين يُراجَع الكشف بعد أسبوع.
                $sch_pill = $sch_lbl[$sch_state] . ($sch_row->manual && $sch_state !== 'none' ? '*' : '');
                ?>
                <div class="sch-sd__row<?php echo $sch_state === 'absent' ? ' is-absent' : ''; ?>"
                     data-row
                     data-state="<?php echo esc_attr($sch_state); ?>"
                     data-dept="<?php echo esc_attr((string) ($sch_row->department ?? '')); ?>"
                     data-find="<?php echo esc_attr(mb_strtolower($sch_name . ' ' . (string) ($sch_row->job_title ?? '') . ' ' . (string) ($sch_row->employee_no ?? ''))); ?>">

                    <span class="sch-sd__cb">
                        <input type="checkbox" name="ids[]" value="<?php echo esc_attr((string) $sch_uid); ?>"
                               id="sch-sd-p<?php echo esc_attr((string) $sch_uid); ?>" data-pick>
                        <label class="sch-sr" for="sch-sd-p<?php echo esc_attr((string) $sch_uid); ?>">
                            <?php echo esc_html(sprintf(
                                /* translators: %s: اسم الموظف */
                                __('تحديد %s', 'school-system'),
                                $sch_name
                            )); ?>
                        </label>
                    </span>

                    <span class="sch-sd__who">
                        <span class="sch-sd__av" aria-hidden="true"><?php echo esc_html(mb_substr(trim($sch_name), 0, 1)); ?></span>
                        <span class="sch-sd__whot">
                            <span class="sch-sd__name"><?php echo esc_html($sch_name); ?></span>
                            <span class="sch-sd__no" dir="ltr"><?php echo esc_html((string) ($sch_row->employee_no ?: '—')); ?></span>
                        </span>
                    </span>

                    <span class="sch-sd__dep"><?php echo esc_html((string) ($sch_row->job_title ?: ($sch_row->department ?: '—'))); ?></span>

                    <span class="sch-sd__time<?php echo $sch_time === '' ? ' is-off' : ''; ?>" dir="ltr"><?php echo esc_html($sch_time !== '' ? $sch_time : '—'); ?></span>

                    <span class="sch-sd__st">
                        <?php echo sch_wave_pill($sch_state, $sch_pill, -1, in_array($sch_state, ['present', 'late'], true)); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </span>

                    <span class="sch-sd__lsn" dir="ltr"><?php echo esc_html($sch_row->lessons > 0 ? number_format_i18n($sch_row->lessons) : '—'); ?></span>

                    <span class="sch-sd__cov">
                        <?php if ($sch_row->needs && $sch_row->cover_open > 0) : ?>
                            <span class="sch-sd__covn"><?php echo esc_html(sprintf(
                                /* translators: %s: عدد الحصص */
                                __('%s بلا معلم', 'school-system'),
                                number_format_i18n($sch_row->cover_open)
                            )); ?></span>
                            <a class="sch-btn sch-btn--sm" href="<?php echo esc_url(add_query_arg('cover', $sch_uid, $sch_url)); ?>">
                                <?php echo $sch_row->cover_done > 0 ? esc_html__('إكمال', 'school-system') : esc_html__('تعيين بديل', 'school-system'); ?>
                            </a>
                        <?php elseif ($sch_row->needs) : ?>
                            <span class="sch-sd__ok" aria-hidden="true"></span>
                            <span class="sch-sd__okt"><?php echo esc_html($sch_row->cover_by
                                ? sprintf(
                                    /* translators: %s: اسم البديل */
                                    __('غُطّيت · %s', 'school-system'),
                                    (string) $sch_row->cover_by
                                )
                                : __('غُطّيت', 'school-system')); ?></span>
                            <a class="sch-btn sch-btn--quiet sch-btn--sm" href="<?php echo esc_url(add_query_arg('cover', $sch_uid, $sch_url)); ?>"><?php esc_html_e('تعديل', 'school-system'); ?></a>
                        <?php else : ?>
                            <span class="sch-sd__none">—</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>

            <div class="sch-sd__empty" id="sch-sd-empty" hidden>
                <strong><?php esc_html_e('لا نتائج مطابقة', 'school-system'); ?></strong>
                <span><?php esc_html_e('جرّب اسمًا آخر أو أعد ضبط التصفية.', 'school-system'); ?></span>
            </div>
        </div>

        <div class="sch-sd__foot">
            <span class="sch-sd__range" id="sch-sd-range"></span>
            <div class="sch-sd__pages" id="sch-sd-pages"></div>
        </div>
    </div>
</form>

<?php endif; ?>

<?php
// ---------- لوح التغطية ----------
$sch_panel = $sch_open > 0 ? SCH_Deputy::coverage($sch_open, $sch_date) : null;

if ($sch_panel && $sch_panel['teacher']) :
    $sch_t     = $sch_panel['teacher'];
    $sch_tname = (string) $sch_t->display_name;
    $sch_tst   = (string) ($sch_t->status ?? '');
    ?>
<div class="sch-sd__veil">
    <a class="sch-sd__veilx" href="<?php echo esc_url($sch_url); ?>" aria-label="<?php esc_attr_e('إغلاق اللوح', 'school-system'); ?>"></a>

    <aside class="sch-sd__panel" role="dialog" aria-modal="true" aria-labelledby="sch-sd-ptitle">
        <div class="sch-sd__phd">
            <div class="sch-sd__pttl">
                <strong id="sch-sd-ptitle"><?php echo esc_html(sprintf(
                    /* translators: %s: اسم المعلم */
                    __('تغطية حصص %s', 'school-system'),
                    $sch_tname
                )); ?></strong>
                <span><?php echo esc_html(trim(
                    (string) ($sch_t->job_title ?: '') . ' · '
                    . ($sch_tst !== '' ? (SCH_Deputy::STAFF_STATUSES[$sch_tst] ?? $sch_tst) : __('لم يُرصد', 'school-system'))
                    . ' ' . __('اليوم', 'school-system'),
                    ' ·'
                )); ?></span>
            </div>
            <a class="sch-sd__px" href="<?php echo esc_url($sch_url); ?>" aria-label="<?php esc_attr_e('إغلاق', 'school-system'); ?>"><?php echo sch_icon('x', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
        </div>

        <div class="sch-sd__psum">
            <span><?php echo esc_html(sprintf(
                /* translators: 1: الحصص بلا بديل 2: مجموع الحصص */
                __('%1$s من %2$s حصص بلا بديل', 'school-system'),
                number_format_i18n($sch_panel['open']),
                number_format_i18n($sch_panel['total'])
            )); ?></span>

            <form method="post">
                <?php wp_nonce_field('sch_cover_auto', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="cover_auto">
                <input type="hidden" name="teacher_id" value="<?php echo esc_attr((string) $sch_open); ?>">
                <?php $sch_keep(); ?>
                <button class="sch-btn sch-btn--quiet sch-btn--sm" <?php disabled($sch_panel['open'] === 0); ?>><?php esc_html_e('تعيين تلقائي للكل', 'school-system'); ?></button>
            </form>
        </div>

        <div class="sch-sd__plist">
            <?php if ($sch_panel['rows'] === []) : ?>
                <div class="sch-sd__pempty">
                    <strong><?php esc_html_e('لا حصص لهذا الموظف اليوم', 'school-system'); ?></strong>
                    <span><?php esc_html_e('غيابه لا يترك فصلًا بلا معلم — لا تغطية مطلوبة.', 'school-system'); ?></span>
                </div>
            <?php endif; ?>

            <?php foreach ($sch_panel['rows'] as $sch_c) : ?>
                <div class="sch-sd__slot">
                    <div class="sch-sd__slothd">
                        <span class="sch-sd__slott">
                            <strong><?php echo esc_html(trim(
                                (string) ($sch_c->subject_name ?: __('حصة', 'school-system'))
                                . ' — ' . (string) $sch_c->grade_level . '/' . (string) $sch_c->section
                            )); ?></strong>
                            <span><?php
                            if ($sch_c->assigned) {
                                esc_html_e('مُغطّاة', 'school-system');
                            } elseif ($sch_c->candidates === []) {
                                esc_html_e('لا يوجد معلم متاح — راجع الجدول', 'school-system');
                            } else {
                                echo esc_html(sprintf(
                                    /* translators: %s: عدد المرشحين */
                                    __('%s معلمًا متاحًا في هذا الوقت', 'school-system'),
                                    number_format_i18n(count($sch_c->candidates))
                                ));
                            }
                            ?></span>
                        </span>
                        <span class="sch-sd__slotw" dir="ltr"><?php echo esc_html($sch_c->time); ?></span>
                    </div>

                    <?php if ($sch_c->assigned) : ?>
                        <div class="sch-sd__done">
                            <span class="sch-sd__ok" aria-hidden="true"></span>
                            <span class="sch-sd__donet"><?php echo esc_html((string) $sch_c->substitute_name); ?></span>
                            <form method="post">
                                <?php wp_nonce_field('sch_cover_unassign', '_sch_nonce'); ?>
                                <input type="hidden" name="sch_action" value="cover_unassign">
                                <input type="hidden" name="sub_id" value="<?php echo esc_attr((string) $sch_c->sub_id); ?>">
                                <?php $sch_keep(); ?>
                                <button class="sch-sd__undo"><?php esc_html_e('إلغاء', 'school-system'); ?></button>
                            </form>
                        </div>
                    <?php elseif ($sch_c->candidates !== []) : ?>
                        <form method="post" class="sch-sd__pick">
                            <?php wp_nonce_field('sch_cover_assign', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="cover_assign">
                            <input type="hidden" name="teacher_id" value="<?php echo esc_attr((string) $sch_open); ?>">
                            <input type="hidden" name="class_id" value="<?php echo esc_attr((string) $sch_c->class_id); ?>">
                            <input type="hidden" name="period_no" value="<?php echo esc_attr((string) $sch_c->period_no); ?>">
                            <?php $sch_keep(); ?>

                            <label class="sch-sr" for="sch-sd-c<?php echo esc_attr((string) $sch_c->class_id . '-' . $sch_c->period_no); ?>">
                                <?php esc_html_e('اختر البديل', 'school-system'); ?>
                            </label>
                            <select name="sub_teacher_id" id="sch-sd-c<?php echo esc_attr((string) $sch_c->class_id . '-' . $sch_c->period_no); ?>">
                                <?php foreach ($sch_c->candidates as $sch_cand) : ?>
                                    <option value="<?php echo esc_attr((string) $sch_cand->user_id); ?>">
                                        <?php echo esc_html((string) $sch_cand->display_name . ' — ' . ((int) $sch_cand->same_subject > 0
                                            ? __('نفس التخصص', 'school-system')
                                            : ((string) ($sch_cand->department ?: $sch_cand->job_title ?: __('متاح', 'school-system'))))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="sch-btn sch-btn--sm"><?php esc_html_e('تعيين', 'school-system'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sch-sd__pft">
            <a class="sch-btn sch-btn--block" href="<?php echo esc_url($sch_url); ?>"><?php esc_html_e('اعتماد التغطية', 'school-system'); ?></a>
            <form method="post">
                <?php wp_nonce_field('sch_cover_notify', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="cover_notify">
                <input type="hidden" name="teacher_id" value="<?php echo esc_attr((string) $sch_open); ?>">
                <?php $sch_keep(); ?>
                <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إشعار البدلاء', 'school-system'); ?></button>
            </form>
        </div>
    </aside>
</div>
<?php endif; ?>

<script>
(function () {
  var form = document.getElementById('sch-sd-form');
  if (!form) { return; }

  var AR    = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
  var SIZE  = 25;
  var rows  = Array.prototype.slice.call(form.querySelectorAll('[data-row]'));
  var sel   = document.getElementById('sch-sd-sel');
  var seln  = document.getElementById('sch-sd-seln');
  var range = document.getElementById('sch-sd-range');
  var pages = document.getElementById('sch-sd-pages');
  var blank = document.getElementById('sch-sd-empty');
  var qBox  = document.getElementById('sch-sd-q');
  var dept  = document.getElementById('sch-sd-dept');
  var tabs  = Array.prototype.slice.call(document.querySelectorAll('.sch-sd__tab'));

  function ar(n) { return String(n).split('').map(function (d) { return /\d/.test(d) ? AR[+d] : d; }).join(''); }

  /* السهم زرٌّ مثل الأرقام لا شكلٌ آخر — والمعطَّل يبقى مكانه فلا يقفز
     الشريط تحت الإصبع حين تصل الصفحة الأولى أو الأخيرة. */
  function arrow(glyph, to, off) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'sch-sd__page sch-sd__page--arrow';
    b.textContent = glyph;
    b.dataset.p = String(to);
    b.disabled = off;
    b.setAttribute('aria-label', glyph === '›' ? 'الصفحة السابقة' : 'الصفحة التالية');
    return b;
  }

  var state = {
    f: (document.querySelector('.sch-sd__tab.is-on') || {}).dataset ?
       document.querySelector('.sch-sd__tab.is-on').dataset.f : 'all',
    q: qBox ? qBox.value.trim().toLowerCase() : '',
    d: dept ? dept.value : '',
    p: <?php echo esc_html((string) ($sch_pg - 1)); ?>
  };

  /* الحالة تُكتب في حقول النموذج المخفية، فتعود مع التحويل بعد الحفظ. */
  function stash() {
    var map = { f: state.f === 'all' ? '' : state.f, q: state.q, dept: state.d, pg: state.p > 0 ? String(state.p + 1) : '' };
    Object.keys(map).forEach(function (k) {
      form.querySelectorAll('[data-keep="' + k + '"]').forEach(function (el) { el.remove(); });
      if (!map[k]) { return; }
      var el = document.createElement('input');
      el.type = 'hidden';
      el.name = 'keep[' + k + ']';
      el.value = map[k];
      el.setAttribute('data-keep', k);
      form.appendChild(el);
    });
  }

  function matches(tr) {
    return (state.f === 'all' || tr.dataset.state === state.f)
        && (state.d === '' || tr.dataset.dept === state.d)
        && (state.q === '' || tr.dataset.find.indexOf(state.q) >= 0);
  }

  function paint() {
    var hits = rows.filter(matches);
    var last = Math.max(1, Math.ceil(hits.length / SIZE));
    if (state.p > last - 1) { state.p = last - 1; }
    if (state.p < 0) { state.p = 0; }

    var from = state.p * SIZE;
    var to   = from + SIZE;

    rows.forEach(function (tr) { tr.hidden = true; });
    hits.slice(from, to).forEach(function (tr) { tr.hidden = false; });

    if (blank) { blank.hidden = hits.length !== 0; }

    if (range) {
      range.textContent = hits.length
        ? 'عرض ' + ar(from + 1) + '–' + ar(Math.min(hits.length, to)) + ' من ' + ar(hits.length) + ' موظفًا'
        : 'لا صفوف';
    }

    if (pages) {
      pages.textContent = '';
      if (last > 1) {
        pages.appendChild(arrow('›', state.p - 1, state.p === 0));
        for (var i = 0; i < last; i++) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'sch-sd__page' + (i === state.p ? ' is-on' : '');
          b.textContent = ar(i + 1);
          b.dataset.p = String(i);
          pages.appendChild(b);
        }
        pages.appendChild(arrow('‹', state.p + 1, state.p === last - 1));
      }
    }

    stash();
    count();
  }

  /* المخفيّ لا يُحسب ولا يُرسل: تحديدٌ ثم تصفيةٌ كان سيرصد من لا يراه. */
  function count() {
    var on = 0, seen = 0;
    rows.forEach(function (tr) {
      var cb = tr.querySelector('[data-pick]');
      if (!cb) { return; }
      cb.disabled = tr.hidden;
      if (tr.hidden) { return; }
      seen++;
      if (cb.checked) { on++; }
    });

    if (sel) { sel.hidden = on === 0; }
    if (seln) { seln.textContent = 'محدد ' + ar(on) + ' موظفًا'; }

    var all = document.getElementById('sch-sd-all');
    if (all) {
      all.checked = seen > 0 && on === seen;
      all.indeterminate = on > 0 && on < seen;
    }
  }

  /* «تحديد المعروض» يخصّ الصفحة المعروضة لا الكشف كلّه: زرٌّ يحدّد أربعة
     وستين وأنت ترى خمسة وعشرين يجعل ضغطةً واحدة ترصد من لم تنظر إليه. */
  var master = document.getElementById('sch-sd-all');
  if (master) {
    master.addEventListener('change', function () {
      var v = master.checked;
      rows.forEach(function (tr) {
        if (tr.hidden) { return; }
        var cb = tr.querySelector('[data-pick]');
        if (cb) { cb.checked = v; }
      });
      count();
    });
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('is-on'); x.setAttribute('aria-pressed', 'false'); });
      t.classList.add('is-on');
      t.setAttribute('aria-pressed', 'true');
      state.f = t.dataset.f;
      state.p = 0;
      paint();
    });
  });

  if (qBox) {
    qBox.addEventListener('input', function () {
      state.q = qBox.value.trim().toLowerCase();
      state.p = 0;
      paint();
    });
  }

  if (dept) {
    dept.addEventListener('change', function () {
      state.d = dept.value;
      state.p = 0;
      paint();
    });
  }

  if (pages) {
    pages.addEventListener('click', function (e) {
      var b = e.target.closest('.sch-sd__page');
      if (!b) { return; }
      state.p = +b.dataset.p;
      paint();
    });
  }

  if (sel) {
    sel.addEventListener('click', function (e) {
      if (!e.target.closest('[data-clear]')) { return; }
      rows.forEach(function (tr) {
        var cb = tr.querySelector('[data-pick]');
        if (cb) { cb.checked = false; }
      });
      var all = document.getElementById('sch-sd-all');
      if (all) { all.checked = false; all.indeterminate = false; }
      count();
    });
  }

  /* التحديد المشترك (Shift للمدى) يعمل بلا `change`، فيُعاد العدّ بعد النقر. */
  document.addEventListener('change', count);
  document.addEventListener('click', function () { window.setTimeout(count, 0); });

  paint();
})();

/* الساعة الحيّة — تُكتب بأرقام لاتينية لأنها في خانة أرقام جدولية. */
(function () {
  var box = document.getElementById('sch-sd-clock');
  if (!box) { return; }

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function tick() {
    var d = new Date();
    box.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }

  tick();
  window.setInterval(tick, 1000);
})();
</script>
