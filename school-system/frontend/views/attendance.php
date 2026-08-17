<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$classes  = SCH_Classes::list();
$class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : (int) ($classes[0]->id ?? 0);
$date     = isset($_GET['d']) ? (sch_sanitize_date(wp_unslash((string) $_GET['d'])) ?? current_time('Y-m-d')) : current_time('Y-m-d');
$sheet    = $class_id > 0 ? SCH_Attendance::sheet($class_id, $date) : [];
$is_today = $date === current_time('Y-m-d');

// ترتيب الشرائح: حاضر ← متأخر ← غائب ← بعذر (لا ترتيب الثابت)
$sch_order = ['present', 'late', 'absent', 'excused'];

// عدّادات هذه الشعبة — تُحدَّث حيًّا في المتصفح مع كل تعليم.
$sch_c = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'none' => 0];
foreach ($sheet as $sch_r) {
    $sch_s = $sch_r->status;
    if ($sch_s === null) {
        $sch_c['none']++;
    } elseif (isset($sch_c[$sch_s])) {
        $sch_c[$sch_s]++;
    }
}

$sch_method_labels = [
    'qr'        => __('بوابة', 'school-system'),
    'transport' => __('الباص', 'school-system'),
    'manual'    => __('يدوي', 'school-system'),
];
?>
<h1 class="sch-title"><?php echo sch_icon('check', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('الحضور اليومي', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('المسح هو الأصل وأنت المصحّح — البوابة رصدت أغلبهم، فلا عليك إلا الاستثناءات.', 'school-system'); ?></p>

<!-- ملخّص حيّ بالموجات — يتغيّر مع كل تعليم -->
<div class="sch-att-sum" id="sch-att-sum" aria-live="polite">
    <span class="sch-wpill sch-wpill--present<?php echo $is_today ? ' is-live' : ''; ?>"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('حاضر', 'school-system'); ?></span><b class="sch-wpill__n" data-c="present"><?php echo esc_html(number_format_i18n($sch_c['present'])); ?></b></span>
    <span class="sch-wpill sch-wpill--late<?php echo $is_today ? ' is-live' : ''; ?>"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('متأخر', 'school-system'); ?></span><b class="sch-wpill__n" data-c="late"><?php echo esc_html(number_format_i18n($sch_c['late'])); ?></b></span>
    <span class="sch-wpill sch-wpill--absent"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('غائب', 'school-system'); ?></span><b class="sch-wpill__n" data-c="absent"><?php echo esc_html(number_format_i18n($sch_c['absent'])); ?></b></span>
    <span class="sch-wpill sch-wpill--excused"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('بعذر', 'school-system'); ?></span><b class="sch-wpill__n" data-c="excused"><?php echo esc_html(number_format_i18n($sch_c['excused'])); ?></b></span>
    <span class="sch-wpill sch-wpill--none"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('لم يُرصد', 'school-system'); ?></span><b class="sch-wpill__n" data-c="none"><?php echo esc_html(number_format_i18n($sch_c['none'])); ?></b></span>
</div>

<div class="sch-card sch-noprint">
    <form method="get" class="sch-toolbar">
        <input type="hidden" name="sch_section" value="attendance">
        <label class="sch-field">
            <span><?php esc_html_e('الشعبة', 'school-system'); ?></span>
            <select name="class_id" aria-label="<?php esc_attr_e('الشعبة', 'school-system'); ?>">
                <?php foreach ($classes as $c) : ?>
                    <option value="<?php echo esc_attr((string) $c->id); ?>" <?php selected($class_id, (int) $c->id); ?>>
                        <?php echo esc_html(SCH_Classes::label($c)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="sch-field">
            <span><?php esc_html_e('التاريخ', 'school-system'); ?></span>
            <input type="date" name="d" value="<?php echo esc_attr($date); ?>" aria-label="<?php esc_attr_e('التاريخ', 'school-system'); ?>">
        </label>
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('عرض الكشف', 'school-system'); ?></button>
    </form>
</div>

<?php if ($sch_c['none'] > 0 && $is_today) : ?>
    <div class="sch-notice">
        <strong><?php echo esc_html(sprintf(
            /* translators: %d: عدد الطلاب */
            _n('لم يُرصد دخول %d طالب — راجعه', 'لم يُرصد دخول %d طلاب — راجعهم', $sch_c['none'], 'school-system'),
            $sch_c['none']
        )); ?></strong>
        <div class="sch-sub"><?php esc_html_e('انظر أمامك وحدّد الموجود من الغائب — أو علّم غير المرصودين حاضرين بضغطة.', 'school-system'); ?></div>
    </div>
<?php endif; ?>

<?php if ($sheet === []) : ?>
    <div class="sch-blank">
        <span class="sch-blank__ic"><?php echo sch_icon('check', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <strong><?php esc_html_e('لا يوجد طلاب في هذه الشعبة', 'school-system'); ?></strong>
        <p><?php esc_html_e('سجّل طلابًا في الشعبة أولًا من صفحة الطلاب.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <form method="post" id="sch-att-form">
        <?php wp_nonce_field('sch_mark_attendance', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="mark_attendance">
        <input type="hidden" name="att_date" value="<?php echo esc_attr($date); ?>">

        <div class="sch-att-bar sch-noprint">
            <button type="button" class="sch-chipbtn" id="sch-fill-present">
                <?php echo sch_icon('check', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php esc_html_e('علّم غير المرصودين حاضرين', 'school-system'); ?>
            </button>
            <span class="sch-sub"><?php esc_html_e('ثم اقلب الاستثناءات فقط — الأسرع في الرصد اليومي.', 'school-system'); ?></span>
        </div>

        <div class="sch-att" id="sch-att">
            <?php foreach ($sheet as $row) :
                $init   = mb_substr((string) $row->full_name, 0, 1);
                $method = (string) ($row->method ?? '');
                $mlabel = $sch_method_labels[$method] ?? '';
                $ctime  = $row->checked_in_at ? substr((string) $row->checked_in_at, 11, 5) : '';
                $late   = (int) ($row->minutes_late ?? 0); ?>
                <div class="sch-att-row<?php echo $row->status === null ? ' is-unmarked' : ''; ?>">
                    <span class="sch-att__av" aria-hidden="true"><?php echo esc_html($init); ?></span>

                    <span class="sch-att__id">
                        <b class="sch-att__nm"><?php echo esc_html((string) $row->full_name); ?></b>
                        <span class="sch-att__meta">
                            <?php if ($mlabel !== '') : ?>
                                <span class="sch-att__src sch-att__src--<?php echo esc_attr($method); ?>">
                                    <?php echo esc_html($mlabel); ?><?php echo $ctime !== '' ? ' · ' . esc_html($ctime) : ''; ?>
                                </span>
                            <?php else : ?>
                                <span class="sch-att__src sch-att__src--none"><?php esc_html_e('لم يُرصد', 'school-system'); ?></span>
                            <?php endif; ?>
                            <?php if ($late > 0) : ?>
                                <span class="sch-att__late"><?php echo esc_html(sprintf(
                                    /* translators: %s: عدد الدقائق */
                                    __('متأخر %s د', 'school-system'),
                                    number_format_i18n($late)
                                )); ?></span>
                            <?php endif; ?>
                        </span>
                    </span>

                    <span class="sch-att-seg" role="radiogroup" aria-label="<?php echo esc_attr(sprintf(
                        /* translators: %s: اسم الطالب */
                        __('حالة %s', 'school-system'),
                        (string) $row->full_name
                    )); ?>">
                        <?php foreach ($sch_order as $key) : ?>
                            <label class="sch-att-seg__o sch-att-seg__o--<?php echo esc_attr($key); ?>">
                                <input type="radio"
                                       name="status[<?php echo esc_attr((string) $row->id); ?>]"
                                       value="<?php echo esc_attr($key); ?>"
                                       <?php checked($row->status, $key); ?>>
                                <span><?php echo esc_html(SCH_Attendance::STATUSES[$key]); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sch-att-save sch-noprint">
            <span class="sch-att-save__n" id="sch-att-count"></span>
            <button class="sch-btn" id="sch-att-submit"><?php esc_html_e('حفظ الكشف', 'school-system'); ?></button>
        </div>
    </form>

    <script>
    (function () {
      var wrap = document.getElementById('sch-att');
      var sum  = document.getElementById('sch-att-sum');
      var cnt  = document.getElementById('sch-att-count');
      var fill = document.getElementById('sch-fill-present');
      if (!wrap) { return; }

      var AR = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
      function ar(n) { return String(n).split('').map(function (d) { return AR[+d] || d; }).join(''); }

      var rows = Array.prototype.slice.call(wrap.querySelectorAll('.sch-att-row'));

      function recount() {
        var c = { present:0, late:0, absent:0, excused:0, none:0 };
        rows.forEach(function (r) {
          var on = r.querySelector('input[type=radio]:checked');
          if (on) { c[on.value]++; } else { c.none++; }
          r.classList.toggle('is-unmarked', !on);
        });
        Object.keys(c).forEach(function (k) {
          var el = sum.querySelector('[data-c="' + k + '"]');
          if (el) { el.textContent = ar(c[k]); }
        });
        var marked = rows.length - c.none;
        cnt.textContent = ar(marked) + ' / ' + ar(rows.length) + ' مرصود';
      }

      wrap.addEventListener('change', recount);

      if (fill) {
        fill.addEventListener('click', function () {
          rows.forEach(function (r) {
            if (!r.querySelector('input[type=radio]:checked')) {
              var p = r.querySelector('input[value="present"]');
              if (p) { p.checked = true; }
            }
          });
          recount();
        });
      }

      recount();
    })();
    </script>
<?php endif; ?>
