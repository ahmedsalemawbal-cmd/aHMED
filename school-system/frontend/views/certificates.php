<?php
/**
 * الشهادات.
 *
 * النظام يبحث عن المستحقين بدل الوكيل — وهذا ما يجعل الميزة تُستخدم فعلًا
 * لا تُنسى بعد أسبوع.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$preview = isset($_GET['cert']) ? absint($_GET['cert']) : 0;

// طباعة دفعة: ورقة لكل شهادة، وتفتح نافذة الطباعة تلقائيًا.
if (isset($_GET['sheet'])) {
    $sch_batch = SCH_Certificates::search([
        'student_id'  => isset($_GET['st']) ? absint($_GET['st']) : 0,
        'class_id'    => isset($_GET['class_id']) ? absint($_GET['class_id']) : 0,
        'stage'       => isset($_GET['stage']) ? sanitize_key(wp_unslash((string) $_GET['stage'])) : '',
        'grade_level' => isset($_GET['g']) ? sanitize_text_field(wp_unslash((string) $_GET['g'])) : '',
        'type'        => isset($_GET['type']) ? sanitize_key(wp_unslash((string) $_GET['type'])) : '',
        'from'        => isset($_GET['from']) ? sch_sanitize_date($_GET['from']) : '',
        'to'          => isset($_GET['to']) ? sch_sanitize_date($_GET['to']) : '',
        // خمسون في الصفحة لا ثلاثمئة: القالب المزخرف ~٧٠ كيلوبايت، فثلاثمئة
        // شهادة ≈ ٢١ ميغابايت HTML يتجمّد المتصفح قبل أن يصل أمر الطباعة.
        'limit'       => SCH_Certificates::PRINT_BATCH,
        'offset'      => isset($_GET['off']) ? absint($_GET['off']) : 0,
    ]);

    $sch_off  = isset($_GET['off']) ? absint($_GET['off']) : 0;
    $sch_more = (count($sch_batch) === SCH_Certificates::PRINT_BATCH);
?>
    <div class="sch-head sch-noprint">
        <div>
            <h1 class="sch-title"><?php esc_html_e('طباعة الشهادات', 'school-system'); ?></h1>
            <p class="sch-sub"><?php echo esc_html(sprintf(
                /* translators: 1: من 2: إلى */
                __('شهادات %1$s–%2$s — ورقة لكل واحدة', 'school-system'),
                number_format_i18n($sch_off + 1),
                number_format_i18n($sch_off + count($sch_batch))
            )); ?></p>
        </div>
        <div class="sch-head__acts">
            <?php if ($sch_more) : ?>
                <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg(
                    'off',
                    $sch_off + SCH_Certificates::PRINT_BATCH
                )); ?>"><?php echo esc_html(sprintf(
                    /* translators: %s: العدد */
                    __('التالية %s', 'school-system'),
                    number_format_i18n(SCH_Certificates::PRINT_BATCH)
                )); ?></a>
            <?php endif; ?>
            <button type="button" class="sch-btn" onclick="window.print()">
                <?php echo sch_icon('print', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php esc_html_e('طباعة', 'school-system'); ?>
            </button>
        </div>
    </div>

    <?php if ($sch_batch === []) : ?>
        <div class="sch-blank sch-noprint">
            <strong><?php esc_html_e('لا شهادات تطابق التصفية', 'school-system'); ?></strong>
        </div>
    <?php endif; ?>

    <?php foreach ($sch_batch as $sch_one) : ?>
        <div class="sch-cert sch-cert--sheet">
            <?php echo SCH_Certificates::svg($sch_one); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </div>
    <?php endforeach; ?>

    <?php /* الطباعة التلقائية للدفعات الصغيرة وحدها — دفعة كاملة تُطبع بالخطأ
             ورقٌ لا يُستعاد. فوق العشرين يضغط الموظف الزرّ بنفسه. */ ?>
    <?php if (count($sch_batch) > 0 && count($sch_batch) <= 20 && $sch_off === 0) : ?>
        <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
    <?php endif; ?>
    <?php
    return;
}

if ($preview > 0) {
    $sch_c = SCH_Certificates::get($preview);

    if ($sch_c) : ?>
        <a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('certificates')); ?>">
            <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php esc_html_e('الشهادات', 'school-system'); ?>
        </a>

        <div class="sch-head sch-noprint">
            <div>
                <h1 class="sch-title"><?php echo esc_html((string) $sch_c->title); ?></h1>
                <p class="sch-sub"><?php echo esc_html(SCH_Enrollment::full_name($sch_c) . ' · ' . $sch_c->serial); ?></p>
            </div>
            <button type="button" class="sch-btn" onclick="window.print()">
                <?php echo sch_icon('print', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php esc_html_e('طباعة', 'school-system'); ?>
            </button>
        </div>

        <div class="sch-cert">
            <?php echo SCH_Certificates::svg($sch_c); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </div>
        <?php return;
    endif;
}

$sch_recent = SCH_Certificates::search([
    'student_id'  => isset($_GET['st']) ? absint($_GET['st']) : 0,
    'class_id'    => isset($_GET['class_id']) ? absint($_GET['class_id']) : 0,
    'stage'       => isset($_GET['stage']) ? sanitize_key(wp_unslash((string) $_GET['stage'])) : '',
    'type'        => isset($_GET['type']) ? sanitize_key(wp_unslash((string) $_GET['type'])) : '',
    'from'        => isset($_GET['from']) ? sch_sanitize_date($_GET['from']) : '',
    'to'          => isset($_GET['to']) ? sch_sanitize_date($_GET['to']) : '',
    'limit'       => 120,
]);
$sch_sugg   = SCH_Certificates::suggestions();
// قائمة الأسماء للقوائم المنسدلة: تُستدعى مرة لا مرة لكل قائمة،
// و`with => false` تُطفئ الضمّ فلا نجلب شعبة وولي أمر لا نعرضهما.
$sch_pick = SCH_Students::list(['status' => 'active', 'per_page' => 400, 'with' => false])['items'];

// ═══ الإصدار الجماعي حسب النطاق ═══
// الإصدار واحدًا واحدًا لا يصلح لمدرسة: صفّ من ثلاثين يعني ثلاثين نافذة.
// النطاق يُختار (مرحلة · صف · شعبة) فتُعرض قائمته للمراجعة ثم تُصدر دفعةً.
// والحالة كلها في معاملات الرابط: يُشارَك ويعمل معه زر الرجوع.
$bt = isset($_GET['bt']) ? sanitize_key(wp_unslash((string) $_GET['bt'])) : '';
$bs = isset($_GET['bs']) ? sanitize_key(wp_unslash((string) $_GET['bs'])) : '';
$bg = isset($_GET['bg']) ? sanitize_text_field(wp_unslash((string) $_GET['bg'])) : '';
$bc = isset($_GET['bc']) ? absint($_GET['bc']) : 0;

$sch_scoped = ($bt !== '' && isset(SCH_Certificates::TYPES[$bt]));
$sch_roster = [];
$sch_held   = [];
$sch_scopel = '';

if ($sch_scoped) {
    $sch_roster = SCH_Students::list([
        'status'      => 'active',
        'stage'       => $bs,
        'grade_level' => $bg,
        'class_id'    => $bc,
        // ٣٠٠ حدّ العملية الجماعية في النظام — نعرض ما يمكن إصداره فعلًا
        // لا أربعمئة يرفض المعالج آخرها بلا سبب مفهوم.
        'per_page'    => 300,
    ])['items'];

    $sch_held = SCH_Certificates::holders($bt);

    // وصف النطاق بالكلمات لا بالمعرّفات — الشعبة الخطأ تُرى قبل أن تُصدَر
    $sch_bits = [];
    if ($bs !== '') { $sch_bits[] = SCH_Classes::STAGES[$bs] ?? $bs; }
    if ($bg !== '') { $sch_bits[] = $bg; }
    if ($bc > 0) {
        foreach (SCH_Classes::list() as $sch_cx) {
            if ((int) $sch_cx->id === $bc) { $sch_bits[] = SCH_Classes::label($sch_cx); }
        }
    }
    $sch_scopel = $sch_bits === [] ? __('كل المدرسة', 'school-system') : implode(' / ', $sch_bits);
}
?>

<?php
// ═══ شاشتان في واحدة ═══
// **المكتبة** تعرض القوالب كما تُطبع فعلًا، و**الصادرة** تعرض ما صدر منها.
// وأول رؤية للوثيقة كانت تأتي بعد الإصدار — والمكتبة تجعلها قبله.
$sch_view = (isset($_GET['view']) && $_GET['view'] === 'issued') ? 'issued' : 'library';
$sch_cat  = isset($_GET['cat']) ? sanitize_key(wp_unslash((string) $_GET['cat'])) : '';
$sch_url  = SCH_Dashboard::url('certificates');

if (!isset(SCH_Certificates::CATEGORIES[$sch_cat])) {
    $sch_cat = '';
}

$sch_use = SCH_Certificates::template_usage();

// عدّاد كل تصنيف — الشريحة بلا رقم لا تقول كم وراءها
$sch_ccount = [];
foreach (SCH_Certificates::TEMPLATES as $sch_k => $sch_t) {
    $sch_ccount[$sch_t['cat']] = ($sch_ccount[$sch_t['cat']] ?? 0) + 1;
}

ob_start(); ?>
<span class="sch-vsw" role="group" aria-label="<?php esc_attr_e('عرض الشهادات', 'school-system'); ?>">
    <a class="sch-vsw__b<?php echo $sch_view === 'library' ? ' is-on' : ''; ?>" href="<?php echo esc_url($sch_url); ?>"><?php esc_html_e('المكتبة', 'school-system'); ?></a>
    <a class="sch-vsw__b<?php echo $sch_view === 'issued' ? ' is-on' : ''; ?>" href="<?php echo esc_url(add_query_arg('view', 'issued', $sch_url)); ?>"><?php esc_html_e('الصادرة', 'school-system'); ?></a>
</span>
<?php
$sch_tools = (string) ob_get_clean();

SCH_Modal::head(
    __('الشهادات', 'school-system'),
    $sch_view === 'library'
        ? sprintf(
            /* translators: %s: عدد القوالب */
            __('%s قالبًا جاهزًا للطباعة والإرسال · تصل تطبيق ولي الأمر فور إصدارها', 'school-system'),
            number_format_i18n(count(SCH_Certificates::TEMPLATES))
        )
        : sprintf(
            /* translators: %s: العدد */
            __('%s شهادة صادرة', 'school-system'),
            number_format_i18n(count($sch_recent))
        ),
    'sch-issue-cert',
    __('إصدار شهادة', 'school-system'),
    'award',
    $sch_tools
);
?>

<?php if ($sch_view === 'library') : ?>
    <!-- ═══ مكتبة القوالب — الوثيقة الحقيقية لا مربّع تدرّج ═══ -->
    <div class="sch-gal__bar">
        <div class="sch-gal__cats">
            <a class="sch-gal__cat<?php echo $sch_cat === '' ? ' is-on' : ''; ?>" href="<?php echo esc_url($sch_url); ?>">
                <span><?php esc_html_e('الكل', 'school-system'); ?></span>
                <b><?php echo esc_html(number_format_i18n(count(SCH_Certificates::TEMPLATES))); ?></b>
            </a>
            <?php foreach (SCH_Certificates::CATEGORIES as $sch_ck => $sch_cl) : ?>
                <a class="sch-gal__cat<?php echo $sch_cat === $sch_ck ? ' is-on' : ''; ?>" href="<?php echo esc_url(add_query_arg('cat', $sch_ck, $sch_url)); ?>">
                    <span><?php echo esc_html($sch_cl); ?></span>
                    <b><?php echo esc_html(number_format_i18n($sch_ccount[$sch_ck] ?? 0)); ?></b>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="sch-gal__find">
            <label class="sch-sr" for="sch-gal-q"><?php esc_html_e('ابحث في القوالب', 'school-system'); ?></label>
            <input type="search" id="sch-gal-q" placeholder="<?php esc_attr_e('ابحث في القوالب…', 'school-system'); ?>">
        </div>
    </div>

    <div class="sch-gal__grid" id="sch-gal-grid">
        <?php foreach (SCH_Certificates::TEMPLATES as $sch_key => $sch_t) :
            if ($sch_cat !== '' && $sch_t['cat'] !== $sch_cat) { continue; }
            $sch_n = (int) ($sch_use[$sch_key] ?? 0); ?>
            <article class="sch-gal__card" data-tpl="<?php echo esc_attr($sch_key); ?>"
                     data-find="<?php echo esc_attr(mb_strtolower($sch_t['name'] . ' ' . ($sch_t['tags'] ?? '') . ' ' . (SCH_Certificates::CATEGORIES[$sch_t['cat']] ?? ''))); ?>">
                <div class="sch-gal__pv"><?php echo SCH_Certificates::preview_svg($sch_key); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
                <div class="sch-gal__meta">
                    <span class="sch-gal__t">
                        <b><?php echo esc_html($sch_t['name']); ?></b>
                        <em><?php echo esc_html($sch_n > 0 ? sprintf(
                            /* translators: %s: عدد مرات الاستخدام */
                            __('استُخدم %s مرة', 'school-system'),
                            number_format_i18n($sch_n)
                        ) : __('لم يُستخدم بعد', 'school-system')); ?></em>
                    </span>
                    <span class="sch-gal__cx"><?php echo esc_html(SCH_Certificates::CATEGORIES[$sch_t['cat']] ?? ''); ?></span>
                </div>
                <button type="button" class="sch-gal__use" data-use="<?php echo esc_attr($sch_key); ?>">
                    <?php echo esc_html(sprintf(
                        /* translators: %s: اسم القالب */
                        __('استخدام %s', 'school-system'),
                        $sch_t['name']
                    )); ?>
                </button>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="sch-blank" id="sch-gal-empty" hidden>
        <strong><?php esc_html_e('لا قوالب مطابقة', 'school-system'); ?></strong>
        <span><?php esc_html_e('جرّب اسمًا آخر أو اختر تصنيفًا مختلفًا.', 'school-system'); ?></span>
    </div>
<?php endif; ?>

<?php if ($sch_view === 'issued') : ?>

<?php foreach ($sch_sugg as $sch_type => $sch_students) :
    if ($sch_students === []) { continue; }
    [$sch_label, , $sch_tpl] = SCH_Certificates::TYPES[$sch_type]; ?>

    <!-- النظام يبحث عن المستحقين: الوكيل يراجع ويضغط، لا يبحث -->
    <section class="sch-sugg">
        <header class="sch-sugg__h">
            <span class="sch-sugg__i"><?php echo sch_icon('award', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <span>
                <b><?php echo esc_html(sprintf(
                    /* translators: 1: العدد 2: نوع الشهادة */
                    __('%1$s طالبًا يستحقون شهادة %2$s', 'school-system'),
                    number_format_i18n(count($sch_students)),
                    $sch_label
                )); ?></b>
                <em><?php esc_html_e('راجعهم ثم أصدرها لمن تختار', 'school-system'); ?></em>
            </span>
        </header>

        <form method="post" class="sch-sugg__form" data-sugg>
            <?php wp_nonce_field('sch_issue_certs_bulk', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="issue_certs_bulk">
            <input type="hidden" name="type" value="<?php echo esc_attr($sch_type); ?>">
            <input type="hidden" name="template" value="<?php echo esc_attr($sch_tpl); ?>">
            <input type="hidden" name="ids" data-ids>

            <div class="sch-sugg__list">
                <?php foreach ($sch_students as $sch_s) : ?>
                    <label class="sch-pill">
                        <?php /* تبدأ فارغة: الاختيار فعل واعٍ. وصولها محدَّدة مسبقًا كان
                                 يعني إصدار عشرات الشهادات بضغطة واحدة بلا مراجعة. */ ?>
                        <input type="checkbox" value="<?php echo esc_attr((string) $sch_s->id); ?>" data-pick-s>
                        <span>
                            <b><?php echo esc_html((string) $sch_s->full_name); ?></b>
                            <em><?php echo esc_html(trim((string) $sch_s->grade_level . ' / ' . (string) $sch_s->section)); ?><?php
                                echo isset($sch_s->pct) ? ' · ' . esc_html(number_format((float) $sch_s->pct, 1)) . '٪' : ''; ?></em>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="sch-sugg__foot">
                <button type="button" class="sch-btn sch-btn--quiet" data-pick-all
                        aria-pressed="false"><?php esc_html_e('تحديد الكل', 'school-system'); ?></button>
                <span class="sch-sugg__n" data-count></span>
                <?php /* الزرّ يحمل العدد نصًّا فلا يُضغط بالعادة العضلية */ ?>
                <button class="sch-btn" data-issue><?php esc_html_e('إصدار', 'school-system'); ?></button>
            </div>
        </form>
    </section>
<?php endforeach; ?>

<h2 class="sch-h2"><?php esc_html_e('الشهادات الصادرة', 'school-system'); ?></h2>

<?php
$sch_f = [
    'st'       => isset($_GET['st']) ? absint($_GET['st']) : 0,
    'class_id' => isset($_GET['class_id']) ? absint($_GET['class_id']) : 0,
    'stage'    => isset($_GET['stage']) ? sanitize_key(wp_unslash((string) $_GET['stage'])) : '',
    'type'     => isset($_GET['type']) ? sanitize_key(wp_unslash((string) $_GET['type'])) : '',
    'from'     => isset($_GET['from']) ? sch_sanitize_date($_GET['from']) : '',
    'to'       => isset($_GET['to']) ? sch_sanitize_date($_GET['to']) : '',
];
$sch_on = array_filter($sch_f);
?>

<!-- التصفية والطباعة معًا: تصفّي ثم تطبع ما صفّيته، لا قائمتين -->
<form method="get" class="sch-card sch-filters">
    <input type="hidden" name="sch_section" value="certificates">

    <div class="sch-filters__row">
        <div class="sch-field">
            <label for="k-type"><?php esc_html_e('نوع الشهادة', 'school-system'); ?></label>
            <select id="k-type" name="type">
                <option value=""><?php esc_html_e('كل الأنواع', 'school-system'); ?></option>
                <?php foreach (SCH_Certificates::TYPES as $sch_slug => $sch_meta) : ?>
                    <option value="<?php echo esc_attr($sch_slug); ?>" <?php selected($sch_f['type'], $sch_slug); ?>>
                        <?php echo esc_html((string) $sch_meta[0]); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sch-field">
            <label for="k-stage"><?php esc_html_e('المرحلة', 'school-system'); ?></label>
            <select id="k-stage" name="stage">
                <option value=""><?php esc_html_e('كل المراحل', 'school-system'); ?></option>
                <?php foreach (SCH_Classes::STAGES as $sch_slug => $sch_label) : ?>
                    <option value="<?php echo esc_attr($sch_slug); ?>" <?php selected($sch_f['stage'], $sch_slug); ?>>
                        <?php echo esc_html($sch_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sch-field">
            <label for="k-class"><?php esc_html_e('الشعبة', 'school-system'); ?></label>
            <select id="k-class" name="class_id">
                <option value=""><?php esc_html_e('كل الشعب', 'school-system'); ?></option>
                <?php foreach (SCH_Classes::list() as $sch_cl) : ?>
                    <option value="<?php echo esc_attr((string) $sch_cl->id); ?>" <?php selected($sch_f['class_id'], (int) $sch_cl->id); ?>>
                        <?php echo esc_html(SCH_Classes::label($sch_cl)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sch-field">
            <label for="k-st"><?php esc_html_e('طالب بعينه', 'school-system'); ?></label>
            <select id="k-st" name="st">
                <option value=""><?php esc_html_e('كل الطلاب', 'school-system'); ?></option>
                <?php foreach ($sch_pick as $sch_stu) : ?>
                    <option value="<?php echo esc_attr((string) $sch_stu->id); ?>" <?php selected($sch_f['st'], (int) $sch_stu->id); ?>>
                        <?php echo esc_html(SCH_Enrollment::full_name($sch_stu)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sch-field">
            <label for="k-from"><?php esc_html_e('من تاريخ', 'school-system'); ?></label>
            <input id="k-from" type="date" name="from" value="<?php echo esc_attr((string) $sch_f['from']); ?>">
        </div>

        <div class="sch-field">
            <label for="k-to"><?php esc_html_e('إلى تاريخ', 'school-system'); ?></label>
            <input id="k-to" type="date" name="to" value="<?php echo esc_attr((string) $sch_f['to']); ?>">
        </div>

        <div class="sch-filters__acts">
            <button class="sch-btn sch-btn--quiet"><?php esc_html_e('تصفية', 'school-system'); ?></button>
            <?php if ($sch_on !== []) : ?>
                <a class="sch-btn" href="<?php echo esc_url(add_query_arg(array_merge($sch_on, ['sheet' => 1]), SCH_Dashboard::url('certificates'))); ?>">
                    <?php echo sch_icon('print', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php esc_html_e('طباعة المصفّى', 'school-system'); ?>
                </a>
                <a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(SCH_Dashboard::url('certificates')); ?>">
                    <?php esc_html_e('مسح', 'school-system'); ?>
                </a>
            <?php else : ?>
                <a class="sch-btn" href="<?php echo esc_url(add_query_arg('sheet', 1, SCH_Dashboard::url('certificates'))); ?>">
                    <?php echo sch_icon('print', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php esc_html_e('طباعة الكل', 'school-system'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($sch_recent === []) : ?>
    <div class="sch-blank">
        <span class="sch-blank__ic"><?php echo sch_icon('award', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <strong><?php esc_html_e('لا شهادات بعد', 'school-system'); ?></strong>
        <p><?php esc_html_e('أصدر أول شهادة بالزر أعلى الشاشة، أو من الاقتراحات.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                <th><?php esc_html_e('الشهادة', 'school-system'); ?></th>
                <th class="sch-col--lg"><?php esc_html_e('الشعبة', 'school-system'); ?></th>
                <th><?php esc_html_e('الرقم', 'school-system'); ?></th>
                <th class="sch-col--xl"><?php esc_html_e('التاريخ', 'school-system'); ?></th>
                <th class="sch-col--xl"><?php esc_html_e('أصدرها', 'school-system'); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($sch_recent as $sch_r) :
                    $sch_gone = ((string) ($sch_r->status ?? 'valid') === 'revoked');
                    // اللقطة المجمَّدة تُعرض كما طُبعت، لا صفّ الطالب اليوم
                    $sch_kl = trim(((string) ($sch_r->grade_snapshot ?? '') !== ''
                        ? (string) $sch_r->grade_snapshot . ' / ' . (string) $sch_r->section_snapshot
                        : (string) $sch_r->grade_level . ' / ' . (string) $sch_r->section), ' /');
                    $sch_by = (int) ($sch_r->issued_by ?? 0);
                    ?>
                    <tr<?php echo $sch_gone ? ' class="is-revoked"' : ''; ?>>
                        <td class="sch-name"><?php echo esc_html((string) ($sch_r->name_snapshot ?: $sch_r->full_name)); ?></td>
                        <td>
                            <?php echo esc_html(SCH_Certificates::TYPES[$sch_r->type][0] ?? ''); ?>
                            <?php if ($sch_gone) : ?>
                                <span class="sch-badge sch-badge--danger" title="<?php echo esc_attr((string) ($sch_r->revoke_reason ?? '')); ?>">
                                    <?php esc_html_e('ملغاة', 'school-system'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="sch-col--lg"><?php echo esc_html($sch_kl); ?></td>
                        <td class="sch-mono"><?php echo esc_html((string) $sch_r->serial); ?></td>
                        <td class="sch-col--xl"><?php echo esc_html(wp_date('j M Y', strtotime((string) $sch_r->issued_at))); ?></td>
                        <td class="sch-col--xl"><?php
                            // من أخطأ يُعرَف: القيمة مخزَّنة منذ البداية ولم تكن تُعرض
                            echo esc_html($sch_by > 0 ? (get_userdata($sch_by)->display_name ?? '—') : '—');
                        ?></td>
                        <td>
                            <div class="sch-rowacts">
                                <a href="<?php echo esc_url(add_query_arg('cert', (int) $sch_r->id, SCH_Dashboard::url('certificates'))); ?>"
                                   title="<?php esc_attr_e('عرض وطباعة', 'school-system'); ?>"
                                   aria-label="<?php esc_attr_e('عرض وطباعة', 'school-system'); ?>">
                                    <?php echo sch_icon('print', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                </a>
                                <?php if (!$sch_gone) : ?>
                                    <button type="button" data-modal-open="sch-revoke-<?php echo esc_attr((string) $sch_r->id); ?>"
                                            title="<?php esc_attr_e('إلغاء الشهادة', 'school-system'); ?>"
                                            aria-label="<?php esc_attr_e('إلغاء الشهادة', 'school-system'); ?>">
                                        <?php echo sch_icon('x', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php /* نافذة إلغاء لكل شهادة سارية — السبب إلزامي، والحذف ممنوع:
             الحذف يزوّر الدفتر والإلغاء يوثّقه. */ ?>
    <?php foreach ($sch_recent as $sch_rv) :
        if ((string) ($sch_rv->status ?? 'valid') === 'revoked') { continue; } ?>
        <?php SCH_Modal::open(
            'sch-revoke-' . (int) $sch_rv->id,
            __('إلغاء شهادة', 'school-system'),
            (string) $sch_rv->serial . ' · ' . (string) ($sch_rv->name_snapshot ?: $sch_rv->full_name)
        ); ?>
            <form method="post">
                <?php wp_nonce_field('sch_revoke_cert', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="revoke_cert">
                <input type="hidden" name="cert_id" value="<?php echo esc_attr((string) $sch_rv->id); ?>">

                <p class="sch-sub"><?php esc_html_e('تبقى الشهادة في السجل مختومة «ملغاة»، ويصل ولي الأمر خبر الإلغاء.', 'school-system'); ?></p>

                <div class="sch-field">
                    <label for="rv-<?php echo esc_attr((string) $sch_rv->id); ?>"><?php esc_html_e('سبب الإلغاء', 'school-system'); ?></label>
                    <textarea id="rv-<?php echo esc_attr((string) $sch_rv->id); ?>" name="reason" rows="3" required
                              placeholder="<?php esc_attr_e('صدرت لشعبة خطأ · خطأ في الاسم · قرار إداري', 'school-system'); ?>"></textarea>
                </div>

                <div class="sch-modal__foot">
                    <button type="button" class="sch-btn sch-btn--quiet" data-modal-close><?php esc_html_e('تراجع', 'school-system'); ?></button>
                    <button class="sch-btn sch-btn--danger"><?php esc_html_e('إلغاء الشهادة', 'school-system'); ?></button>
                </div>
            </form>
        <?php SCH_Modal::close(); ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php endif; // نهاية عرض «الصادرة» ?>

<?php SCH_Modal::open('sch-issue-cert', __('إصدار شهادة', 'school-system'), __('تصل تطبيق ولي الأمر فور إصدارها', 'school-system')); ?>
    <?php /* مدخل واحد لا اثنان: قائمة تختار «طالب واحد» أو «مجموعة»،
             وتُفتح حقول ما اخترته وحدها. الباقي (النوع والعنوان والسبب
             والقالب) مشترك بين الحالتين فلا يُكتب مرتين. */ ?>
    <form method="post" data-issue-form>
        <?php /* لكل فعل نونسه — والمعطَّل لا يُرسل، فيصل الخادم نونس الوضع المختار وحده */ ?>
        <span data-when="one"><?php wp_nonce_field('sch_issue_cert', '_sch_nonce'); ?></span>
        <span data-when="many" hidden>
            <input type="hidden" name="_sch_nonce" disabled
                   value="<?php echo esc_attr(wp_create_nonce('sch_issue_certs_scope')); ?>">
        </span>
        <input type="hidden" name="sch_action" value="issue_cert" data-act>

        <div class="sch-grid">
            <div class="sch-field sch-field--wide">
                <label for="c-mode"><?php esc_html_e('الإصدار لـ', 'school-system'); ?></label>
                <select id="c-mode" data-mode>
                    <option value="one"><?php esc_html_e('طالب واحد', 'school-system'); ?></option>
                    <option value="many"><?php esc_html_e('مجموعة — صف أو شعبة أو مرحلة', 'school-system'); ?></option>
                </select>
            </div>

            <div class="sch-field" data-when="one">
                <label for="c-student"><?php esc_html_e('الطالب', 'school-system'); ?></label>
                <select id="c-student" name="student_id" required>
                    <?php foreach ($sch_pick as $sch_st) : ?>
                        <option value="<?php echo esc_attr((string) $sch_st->id); ?>">
                            <?php echo esc_html(SCH_Enrollment::full_name($sch_st)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field" data-when="many" hidden>
                <label for="c-stage"><?php esc_html_e('المرحلة', 'school-system'); ?></label>
                <select id="c-stage" name="scope_stage" disabled>
                    <option value=""><?php esc_html_e('كل المراحل', 'school-system'); ?></option>
                    <?php foreach (SCH_Classes::STAGES as $sch_k => $sch_l) : ?>
                        <option value="<?php echo esc_attr($sch_k); ?>"><?php echo esc_html($sch_l); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field" data-when="many" hidden>
                <label for="c-class"><?php esc_html_e('الشعبة', 'school-system'); ?></label>
                <select id="c-class" name="scope_class" disabled>
                    <option value=""><?php esc_html_e('كل الشعب', 'school-system'); ?></option>
                    <?php foreach (SCH_Classes::list() as $sch_cl) : ?>
                        <option value="<?php echo esc_attr((string) $sch_cl->id); ?>">
                            <?php echo esc_html(SCH_Classes::label($sch_cl)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="c-type"><?php esc_html_e('نوع الشهادة', 'school-system'); ?></label>
                <select id="c-type" name="type" required>
                    <?php foreach (SCH_Certificates::TYPES as $sch_slug => $sch_meta) : ?>
                        <option value="<?php echo esc_attr($sch_slug); ?>"
                                data-title="<?php echo esc_attr((string) $sch_meta[1]); ?>"
                                data-tpl="<?php echo esc_attr((string) $sch_meta[2]); ?>">
                            <?php echo esc_html((string) $sch_meta[0]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="c-title"><?php esc_html_e('العنوان على الشهادة', 'school-system'); ?></label>
                <input id="c-title" type="text" name="title" maxlength="120">
            </div>
        </div>

        <div class="sch-field">
            <label for="c-reason"><?php esc_html_e('السبب — يظهر على الشهادة', 'school-system'); ?></label>
            <input id="c-reason" type="text" name="reason" maxlength="255"
                   placeholder="<?php esc_attr_e('لتفوّقه في مادة الرياضيات وحصوله على المركز الأول', 'school-system'); ?>">
        </div>

        <?php
        // مكتبة القوالب: تصنيفات وبحث ومعاينة حقيقية بالقالب نفسه.
        // «الأكثر استخدامًا» يُشتق من شهادات هذه المدرسة لا من قائمة نكتبها —
        // فالترتيب تاريخُها لا ذوقُنا.
        $sch_used = SCH_Certificates::template_usage();
        $sch_cats = SCH_Certificates::CATEGORIES;
        ?>
        <h3 class="sch-lbl"><?php esc_html_e('القالب', 'school-system'); ?></h3>

        <div class="sch-lib" data-lib>
            <div class="sch-lib__bar">
                <div class="sch-lib__cats" role="tablist">
                    <button type="button" class="sch-lib__cat is-on" data-cat="" role="tab" aria-selected="true">
                        <?php esc_html_e('الكل', 'school-system'); ?>
                        <span><?php echo esc_html(number_format_i18n(count(SCH_Certificates::TEMPLATES))); ?></span>
                    </button>
                    <?php if (array_sum($sch_used) > 0) : ?>
                        <button type="button" class="sch-lib__cat" data-cat="__used" role="tab" aria-selected="false">
                            <?php esc_html_e('الأكثر استخدامًا', 'school-system'); ?>
                        </button>
                    <?php endif; ?>
                    <?php foreach ($sch_cats as $sch_ck => $sch_cl) :
                        $sch_n = count(array_filter(
                            SCH_Certificates::TEMPLATES,
                            static fn (array $t): bool => (string) ($t['cat'] ?? '') === $sch_ck
                        ));
                        if ($sch_n === 0) { continue; } ?>
                        <button type="button" class="sch-lib__cat" data-cat="<?php echo esc_attr($sch_ck); ?>" role="tab" aria-selected="false">
                            <?php echo esc_html($sch_cl); ?>
                            <span><?php echo esc_html(number_format_i18n($sch_n)); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="search" class="sch-lib__q" data-lib-q
                       placeholder="<?php esc_attr_e('ابحث في القوالب…', 'school-system'); ?>"
                       aria-label="<?php esc_attr_e('بحث في القوالب', 'school-system'); ?>">
            </div>

            <div class="sch-lib__grid">
                <?php foreach (SCH_Certificates::TEMPLATES as $sch_slug => $sch_t) : ?>
                    <label class="sch-lib__item"
                           data-cat="<?php echo esc_attr((string) ($sch_t['cat'] ?? '')); ?>"
                           data-used="<?php echo esc_attr((string) ($sch_used[$sch_slug] ?? 0)); ?>"
                           data-find="<?php echo esc_attr((string) $sch_t['name'] . ' ' . (string) ($sch_t['tags'] ?? '')); ?>">
                        <input type="radio" name="template" value="<?php echo esc_attr($sch_slug); ?>"
                               <?php checked($sch_slug, 'royal'); ?>>
                        <?php /* المصغّرة تُرسَم في «الصادرة» وحدها: في «المكتبة»
                                 القوالب معروضة بحجمها الكامل خلف النافذة، ورسمها
                                 مرّتين يضاعف وزن الصفحة (٢٧٢ كيلوبايت مضغوطة)
                                 مقابل صفر معلومة جديدة. */ ?>
                        <?php if ($sch_view !== 'library') : ?>
                            <span class="sch-lib__thumb">
                                <?php echo SCH_Certificates::preview_svg($sch_slug); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            </span>
                        <?php endif; ?>
                        <span class="sch-lib__meta">
                            <b><?php echo esc_html((string) $sch_t['name']); ?></b>
                            <?php if ((int) ($sch_used[$sch_slug] ?? 0) > 0) : ?>
                                <em><?php echo esc_html(sprintf(
                                    /* translators: %s: العدد */
                                    _n('استُخدم مرة', 'استُخدم %s مرات', (int) $sch_used[$sch_slug], 'school-system'),
                                    number_format_i18n((int) $sch_used[$sch_slug])
                                )); ?></em>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="sch-lib__none" data-lib-none hidden><?php esc_html_e('لا قالب يطابق بحثك.', 'school-system'); ?></p>
        </div>

        <p class="sch-sub" data-mode-note hidden>
            <?php esc_html_e('سيُصدَر لكل طالب في النطاق المختار، ويُتخطّى من ناله هذا العام — ويصل إشعار إلى ولي أمر كل واحد.', 'school-system'); ?>
        </p>
        <button class="sch-btn" data-issue-btn><?php esc_html_e('إصدار الشهادة', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>

<script>
(function () {
  /* ═══ مكتبة القوالب ═══
     البحث في المتصفّح: القوالب ثمانية وعشرون مُصيَّرة أصلًا، وطلبُ صفحةٍ
     جديدة لكل حرف يُكتب يجعل الاختيار أبطأ من التمرير. */
  (function () {
    var grid = document.getElementById('sch-gal-grid');
    var q    = document.getElementById('sch-gal-q');
    var none = document.getElementById('sch-gal-empty');
    if (!grid) { return; }

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.sch-gal__card'));

    if (q) {
      q.addEventListener('input', function () {
        var v = q.value.trim().toLowerCase();
        var n = 0;
        cards.forEach(function (c) {
          var hit = v === '' || c.dataset.find.indexOf(v) >= 0;
          c.hidden = !hit;
          if (hit) { n++; }
        });
        if (none) { none.hidden = n !== 0; }
      });
    }

    /* «استخدام القالب» يفتح نافذة الإصدار وقد اختير فيها — لا شاشة ثانية
       يُعاد فيها اختيار ما اختاره المستخدم للتوّ. */
    grid.addEventListener('click', function (e) {
      var b = e.target.closest('[data-use]');
      if (!b) { return; }

      var radio = document.querySelector('[name="template"][value="' + b.dataset.use + '"]');
      if (radio) { radio.checked = true; }

      var opener = document.querySelector('[data-modal-open="sch-issue-cert"]');
      if (opener) { opener.click(); }

      /* الشريحة تتبع القالب المختار فيراه المستخدم في مكانه لا مخفيًّا
         تحت تصنيفٍ آخر. */
      var wrap = radio ? radio.closest('[data-cat]') : null;
      var cat  = wrap ? wrap.dataset.cat : '';
      var chip = document.querySelector('.sch-lib__cat[data-cat="' + cat + '"]');
      if (chip) { chip.click(); }
      if (wrap && wrap.scrollIntoView) { wrap.scrollIntoView({ block: 'center' }); }
    });
  })();

  /* نوع الشهادة يملأ العنوان ويختار قالبه — الوكيل يكتب أقل */
  var type = document.getElementById('c-type');
  var title = document.getElementById('c-title');

  if (type && title) {
    function fill() {
      var o = type.options[type.selectedIndex];
      if (!o) { return; }
      title.value = o.dataset.title || '';
      var tpl = document.querySelector('[name="template"][value="' + o.dataset.tpl + '"]');
      if (tpl) { tpl.checked = true; }
    }
    type.addEventListener('change', fill);
    fill();
  }

  /* وضع الإصدار: طالب واحد أو مجموعة — الحقول تتبع الاختيار.
     والمعطَّلة لا تُرسل أصلًا (disabled) فلا يصل الخادم نطاقٌ لم يُختَر. */
  document.querySelectorAll('[data-issue-form]').forEach(function (form) {
    var mode = form.querySelector('[data-mode]');
    var act  = form.querySelector('[data-act]');
    var note = form.querySelector('[data-mode-note]');
    var btn  = form.querySelector('[data-issue-btn]');
    if (!mode) { return; }

    function apply() {
      var many = mode.value === 'many';

      form.querySelectorAll('[data-when]').forEach(function (box) {
        var on = (box.dataset.when === (many ? 'many' : 'one'));
        box.hidden = !on;
        box.querySelectorAll('select, input').forEach(function (f) {
          f.disabled = !on;
          if (f.hasAttribute('required') || f.dataset.req) {
            f.dataset.req = '1';
            f.required = on && box.dataset.when === 'one';
          }
        });
      });

      if (act)  { act.value = many ? 'issue_certs_scope' : 'issue_cert'; }
      if (note) { note.hidden = !many; }
      if (btn)  { btn.textContent = many ? 'إصدار للمجموعة' : 'إصدار الشهادة'; }
    }

    mode.addEventListener('change', apply);
    apply();
  });

  /* مكتبة القوالب: تصنيف + بحث. الفلترة محليّة — القوالب اثنا عشر لا مئة. */
  document.querySelectorAll('[data-lib]').forEach(function (lib) {
    var items = Array.prototype.slice.call(lib.querySelectorAll('.sch-lib__item'));
    var cats  = Array.prototype.slice.call(lib.querySelectorAll('.sch-lib__cat'));
    var q     = lib.querySelector('[data-lib-q]');
    var none  = lib.querySelector('[data-lib-none]');
    var cat   = '';

    function norm(s) { return (s || '').toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه'); }

    function run() {
      var term = norm(q ? q.value.trim() : '');
      var shown = 0;

      items.forEach(function (it) {
        var okCat = !cat
          || (cat === '__used' && parseInt(it.dataset.used || '0', 10) > 0)
          || it.dataset.cat === cat;
        var okQ = !term || norm(it.dataset.find).indexOf(term) > -1;
        var on = okCat && okQ;
        it.hidden = !on;
        if (on) { shown++; }
      });

      if (none) { none.hidden = shown > 0; }
    }

    cats.forEach(function (b) {
      b.addEventListener('click', function () {
        cat = b.dataset.cat || '';
        cats.forEach(function (x) {
          var on = x === b;
          x.classList.toggle('is-on', on);
          x.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        run();
      });
    });

    if (q) { q.addEventListener('input', run); }
    run();
  });

  /* الاقتراحات: العدّاد يتحرك والقائمة تُرسل كمعرّفات */
  document.querySelectorAll('[data-sugg]').forEach(function (form) {
    var out  = form.querySelector('[data-ids]');
    var num  = form.querySelector('[data-count]');
    var go   = form.querySelector('[data-issue]');
    var all  = form.querySelector('[data-pick-all]');
    var boxes = form.querySelectorAll('[data-pick-s]');

    function sync() {
      var on = form.querySelectorAll('[data-pick-s]:checked');
      out.value = Array.prototype.map.call(on, function (c) { return c.value; }).join(',');
      num.textContent = on.length ? (on.length + ' من ' + boxes.length) : 'لم يُحدَّد أحد';
      go.disabled = on.length === 0;
      /* الزرّ يقول ما سيفعله بعدده — لا كلمة «تأكيد» مجرّدة */
      go.textContent = on.length ? ('أصدر ' + on.length + ' شهادة') : 'إصدار';
      if (all) {
        var full = on.length === boxes.length && boxes.length > 0;
        all.setAttribute('aria-pressed', full ? 'true' : 'false');
        all.textContent = full ? 'إلغاء التحديد' : 'تحديد الكل';
      }
    }

    if (all) {
      all.addEventListener('click', function () {
        var full = all.getAttribute('aria-pressed') === 'true';
        Array.prototype.forEach.call(boxes, function (b) { b.checked = !full; });
        sync();
      });
    }

    form.addEventListener('change', sync);
    sync();
  });
})();
</script>
