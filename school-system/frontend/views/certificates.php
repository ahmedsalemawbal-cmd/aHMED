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
        'limit'       => 300,
    ]);
    
?>
    <div class="sch-head sch-noprint">
        <div>
            <h1 class="sch-title"><?php esc_html_e('طباعة الشهادات', 'school-system'); ?></h1>
            <p class="sch-sub"><?php echo esc_html(sprintf(
                /* translators: %s: العدد */
                __('%s شهادة — ورقة لكل واحدة', 'school-system'),
                number_format_i18n(count($sch_batch))
            )); ?></p>
        </div>
        <button type="button" class="sch-btn" onclick="window.print()">
            <?php echo sch_icon('print', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php esc_html_e('طباعة', 'school-system'); ?>
        </button>
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

    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
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
?>

<?php
SCH_Modal::head(
    __('الشهادات', 'school-system'),
    sprintf(
        /* translators: %s: العدد */
        __('%s شهادة صادرة', 'school-system'),
        number_format_i18n(count($sch_recent))
    ),
    'sch-issue-cert',
    __('إصدار شهادة', 'school-system'),
    'award'
);
?>

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
                <th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($sch_recent as $sch_r) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html((string) $sch_r->full_name); ?></td>
                        <td><?php echo esc_html(SCH_Certificates::TYPES[$sch_r->type][0] ?? ''); ?></td>
                        <td class="sch-col--lg"><?php echo esc_html(trim((string) $sch_r->grade_level . ' / ' . (string) $sch_r->section)); ?></td>
                        <td class="sch-mono"><?php echo esc_html((string) $sch_r->serial); ?></td>
                        <td class="sch-col--xl"><?php echo esc_html(wp_date('j M Y', strtotime((string) $sch_r->issued_at))); ?></td>
                        <td>
                            <div class="sch-rowacts">
                                <a href="<?php echo esc_url(add_query_arg('cert', (int) $sch_r->id, SCH_Dashboard::url('certificates'))); ?>"
                                   title="<?php esc_attr_e('عرض وطباعة', 'school-system'); ?>"
                                   aria-label="<?php esc_attr_e('عرض وطباعة', 'school-system'); ?>">
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

<?php SCH_Modal::open('sch-issue-cert', __('إصدار شهادة', 'school-system'), __('تصل تطبيق ولي الأمر فور إصدارها', 'school-system')); ?>
    <form method="post">
        <?php wp_nonce_field('sch_issue_cert', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="issue_cert">

        <div class="sch-grid">
            <div class="sch-field">
                <label for="c-student"><?php esc_html_e('الطالب', 'school-system'); ?></label>
                <select id="c-student" name="student_id" required>
                    <?php foreach ($sch_pick as $sch_st) : ?>
                        <option value="<?php echo esc_attr((string) $sch_st->id); ?>">
                            <?php echo esc_html(SCH_Enrollment::full_name($sch_st)); ?>
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

        <h3 class="sch-lbl"><?php esc_html_e('القالب', 'school-system'); ?></h3>

        <div class="sch-tpls">
            <?php foreach (SCH_Certificates::TEMPLATES as $sch_slug => $sch_label) : ?>
                <label class="sch-tpl sch-tpl--<?php echo esc_attr($sch_slug); ?>">
                    <input type="radio" name="template" value="<?php echo esc_attr($sch_slug); ?>"
                           <?php checked($sch_slug, 'royal'); ?>>
                    <span class="sch-tpl__v"></span>
                    <b><?php echo esc_html($sch_label); ?></b>
                </label>
            <?php endforeach; ?>
        </div>

        <button class="sch-btn"><?php esc_html_e('إصدار الشهادة', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>

<script>
(function () {
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
