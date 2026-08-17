<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$classes  = SCH_Classes::list();
$class_id = isset($_GET['class_id']) ? absint($_GET['class_id']) : (int) ($classes[0]->id ?? 0);
$date     = isset($_GET['d']) ? (sch_sanitize_date(wp_unslash((string) $_GET['d'])) ?? current_time('Y-m-d')) : current_time('Y-m-d');
$sheet    = $class_id > 0 ? SCH_Attendance::sheet($class_id, $date) : [];
$is_today = $date === current_time('Y-m-d');

$sch_order = ['present', 'late', 'absent', 'excused'];

// عدّادات هذه الشعبة — تُحدَّث حيًّا مع كل تعليم في المتصفح.
$sch_c = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'none' => 0];
foreach ($sheet as $sch_r) {
    $sch_s = $sch_r->status;
    if ($sch_s === null) {
        $sch_c['none']++;
    } elseif (isset($sch_c[$sch_s])) {
        $sch_c[$sch_s]++;
    }
}

$sch_method = ['qr' => __('بوابة', 'school-system'), 'transport' => __('الباص', 'school-system'), 'manual' => __('يدوي', 'school-system')];
?>
<h1 class="sch-title"><?php echo sch_icon('check', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('حضور الطلاب', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('البوابة رصدت الأغلب — لا عليك إلا الاستثناءات. غيّر الحالة من القائمة ثم احفظ.', 'school-system'); ?></p>

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

        <div class="sch-card">
            <div class="sch-table-wrap">
                <table class="sch-table">
                    <thead><tr>
                        <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                        <th><?php esc_html_e('المصدر', 'school-system'); ?></th>
                        <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                        <th><?php esc_html_e('التحديث اليدوي', 'school-system'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($sheet as $row) :
                        $status = (string) ($row->status ?? '');
                        $ctime  = $row->checked_in_at ? substr((string) $row->checked_in_at, 11, 5) : '';
                        $src    = $sch_method[(string) ($row->method ?? '')] ?? '—';
                        if ($src !== '—' && $ctime !== '' && in_array((string) $row->method, ['qr'], true)) {
                            $src .= ' · ' . $ctime;
                        }
                        // شريحة الحالة الحالية
                        $live = $is_today && in_array($status, ['present', 'late'], true);
                        $lbl  = $status !== '' ? (SCH_Attendance::STATUSES[$status] ?? '') : '';
                        if ($live && $ctime !== '') {
                            $lbl .= ' · ' . $ctime;
                        }
                        ?>
                        <tr>
                            <td class="sch-name"><?php echo esc_html((string) $row->full_name); ?></td>
                            <td class="sch-sub"><?php echo esc_html($src); ?></td>
                            <td>
                                <?php echo $status !== ''
                                    ? sch_wave_pill($status, $lbl, -1, $live)  // phpcs:ignore WordPress.Security.EscapeOutput
                                    : sch_wave_pill('none');                    // phpcs:ignore WordPress.Security.EscapeOutput
                                ?>
                            </td>
                            <td class="sch-att-cell">
                                <span class="sch-att-pickw" data-v="<?php echo esc_attr($status); ?>">
                                    <select class="sch-att-pick" name="status[<?php echo esc_attr((string) $row->id); ?>]"
                                            aria-label="<?php echo esc_attr(sprintf(
                                                /* translators: %s: اسم الطالب */
                                                __('تحديث حالة %s', 'school-system'),
                                                (string) $row->full_name
                                            )); ?>">
                                        <option value="" <?php selected($status, ''); ?>><?php esc_html_e('— حدّث —', 'school-system'); ?></option>
                                        <?php foreach ($sch_order as $key) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html(SCH_Attendance::STATUSES[$key]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sch-att-save sch-noprint">
            <span class="sch-att-save__n" id="sch-att-count"></span>
            <button class="sch-btn" id="sch-att-submit"><?php esc_html_e('حفظ الكشف', 'school-system'); ?></button>
        </div>
    </form>

    <script>
    (function () {
      var form = document.getElementById('sch-att-form');
      var sum  = document.getElementById('sch-att-sum');
      var cnt  = document.getElementById('sch-att-count');
      if (!form) { return; }

      var AR = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
      function ar(n) { return String(n).split('').map(function (d) { return AR[+d] || d; }).join(''); }
      var LBL = { present:'حاضر', late:'متأخر', absent:'غائب', excused:'بعذر' };
      var TODAY = <?php echo $is_today ? 'true' : 'false'; ?>;
      var rows = Array.prototype.slice.call(form.querySelectorAll('tbody tr'));

      function repaint(tr, v) {
        tr.querySelector('.sch-att-pickw').setAttribute('data-v', v);
        var pill = tr.querySelector('.sch-wpill');
        if (!pill) { return; }
        var st = v || 'none';
        var live = TODAY && (st === 'present' || st === 'late');
        pill.className = 'sch-wpill sch-wpill--' + st + (live ? ' is-live' : '');
        pill.querySelector('.sch-wpill__t').textContent = v ? LBL[v] : 'لم يُرصد';
      }

      function recount() {
        var c = { present:0, late:0, absent:0, excused:0, none:0 };
        rows.forEach(function (tr) {
          var v = tr.querySelector('.sch-att-pick').value;
          if (v) { c[v]++; } else { c.none++; }
        });
        Object.keys(c).forEach(function (k) {
          var el = sum.querySelector('[data-c="' + k + '"]');
          if (el) { el.textContent = ar(c[k]); }
        });
        cnt.textContent = ar(rows.length - c.none) + ' / ' + ar(rows.length) + ' مرصود';
      }

      form.addEventListener('change', function (e) {
        var sel = e.target.closest('.sch-att-pick');
        if (!sel) { return; }
        repaint(sel.closest('tr'), sel.value);
        recount();
      });

      recount();
    })();
    </script>
<?php endif; ?>
