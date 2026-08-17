<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$staff = SCH_Deputy::staff_sheet();

$sch_order = ['present', 'late', 'absent'];

// عدّادات اليوم — تُحدَّث حيًّا مع كل تعليم في المتصفح.
$sch_c = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'none' => 0];
foreach ($staff as $sch_s) {
    if ((int) $sch_s->on_leave > 0) {
        $sch_c['leave']++;
    } elseif ($sch_s->status && isset($sch_c[$sch_s->status])) {
        $sch_c[$sch_s->status]++;
    } else {
        $sch_c['none']++;
    }
}
?>
<h1 class="sch-title"><?php echo sch_icon('check', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('حضور المعلمين', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('البوابة رصدت الأغلب برمز البطاقة — غيّر الاستثناءات من القائمة ثم احفظ. رصد الغياب يولّد مقترحات الاحتياط تلقائيًا.', 'school-system'); ?></p>

<div class="sch-att-sum" aria-live="polite">
    <span class="sch-wpill sch-wpill--present is-live"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('حاضر', 'school-system'); ?></span><b class="sch-wpill__n" data-c="present"><?php echo esc_html(number_format_i18n($sch_c['present'])); ?></b></span>
    <span class="sch-wpill sch-wpill--late is-live"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('متأخر', 'school-system'); ?></span><b class="sch-wpill__n" data-c="late"><?php echo esc_html(number_format_i18n($sch_c['late'])); ?></b></span>
    <span class="sch-wpill sch-wpill--absent"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('غائب', 'school-system'); ?></span><b class="sch-wpill__n" data-c="absent"><?php echo esc_html(number_format_i18n($sch_c['absent'])); ?></b></span>
    <span class="sch-wpill sch-wpill--leave"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('إجازة', 'school-system'); ?></span><b class="sch-wpill__n" data-c="leave"><?php echo esc_html(number_format_i18n($sch_c['leave'])); ?></b></span>
    <span class="sch-wpill sch-wpill--none"><span class="sch-wpill__dot"></span><span class="sch-wpill__t"><?php esc_html_e('لم يُرصد', 'school-system'); ?></span><b class="sch-wpill__n" data-c="none"><?php echo esc_html(number_format_i18n($sch_c['none'])); ?></b></span>
</div>

<?php if ($staff === []) : ?>
    <div class="sch-blank">
        <span class="sch-blank__ic"><?php echo sch_icon('check', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <strong><?php esc_html_e('لا يوجد موظفون', 'school-system'); ?></strong>
    </div>
<?php else : ?>
    <form method="post" id="sch-staff-form">
        <?php wp_nonce_field('sch_mark_staff_bulk', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="mark_staff_bulk">

        <div class="sch-card">
            <div class="sch-table-wrap">
                <table class="sch-table">
                    <thead><tr>
                        <th><?php esc_html_e('الموظف', 'school-system'); ?></th>
                        <th><?php esc_html_e('الوظيفة', 'school-system'); ?></th>
                        <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                        <th><?php esc_html_e('التحديث اليدوي', 'school-system'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($staff as $s) :
                        $on_leave = (int) $s->on_leave > 0;
                        $status   = (string) ($s->status ?? '');
                        $ctime    = $s->checked_at ? substr((string) $s->checked_at, 11, 5) : '';
                        $live     = in_array($status, ['present', 'late'], true);
                        $lbl      = $status !== '' ? (SCH_Deputy::STAFF_STATUSES[$status] ?? '') : '';
                        if ($live && $ctime !== '') {
                            $lbl .= ' · ' . $ctime;
                        }
                        ?>
                        <tr<?php echo $on_leave ? ' data-fixed="leave"' : ''; ?>>
                            <td class="sch-name"><?php echo esc_html((string) $s->display_name); ?></td>
                            <td class="sch-sub"><?php echo esc_html($s->job_title ?: '—'); ?></td>
                            <td>
                                <?php
                                if ($on_leave) {
                                    echo sch_wave_pill('leave', __('إجازة معتمدة', 'school-system')); // phpcs:ignore WordPress.Security.EscapeOutput
                                } else {
                                    echo $status !== ''
                                        ? sch_wave_pill($status, $lbl, -1, $live)  // phpcs:ignore WordPress.Security.EscapeOutput
                                        : sch_wave_pill('none');                    // phpcs:ignore WordPress.Security.EscapeOutput
                                }
                                ?>
                            </td>
                            <td class="sch-att-cell">
                                <?php if ($on_leave) : ?>
                                    <span class="sch-sub"><?php esc_html_e('إجازة معتمدة — لا تُعدّل هنا', 'school-system'); ?></span>
                                <?php else : ?>
                                    <span class="sch-att-pickw" data-v="<?php echo esc_attr($status); ?>">
                                        <select class="sch-att-pick" name="status[<?php echo esc_attr((string) $s->user_id); ?>]"
                                                aria-label="<?php echo esc_attr(sprintf(
                                                    /* translators: %s: اسم الموظف */
                                                    __('تحديث حالة %s', 'school-system'),
                                                    (string) $s->display_name
                                                )); ?>">
                                            <option value="" <?php selected($status, ''); ?>><?php esc_html_e('— حدّث —', 'school-system'); ?></option>
                                            <?php foreach ($sch_order as $key) : ?>
                                                <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html(SCH_Deputy::STAFF_STATUSES[$key]); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sch-att-save sch-noprint">
            <span class="sch-att-save__n" id="sch-staff-count"></span>
            <button class="sch-btn"><?php esc_html_e('حفظ الكشف', 'school-system'); ?></button>
        </div>
    </form>

    <script>
    (function () {
      var form = document.getElementById('sch-staff-form');
      var cnt  = document.getElementById('sch-staff-count');
      var sum  = document.querySelector('.sch-att-sum');
      if (!form) { return; }

      var AR = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
      function ar(n) { return String(n).split('').map(function (d) { return AR[+d] || d; }).join(''); }
      var LBL = { present:'حاضر', late:'متأخر', absent:'غائب' };
      var rows = Array.prototype.slice.call(form.querySelectorAll('tbody tr'));

      function repaint(tr, v) {
        tr.querySelector('.sch-att-pickw').setAttribute('data-v', v);
        var pill = tr.querySelector('.sch-wpill');
        if (!pill) { return; }
        var st = v || 'none';
        var live = (st === 'present' || st === 'late');
        pill.className = 'sch-wpill sch-wpill--' + st + (live ? ' is-live' : '');
        pill.querySelector('.sch-wpill__t').textContent = v ? LBL[v] : 'لم يُرصد';
      }

      function recount() {
        var c = { present:0, late:0, absent:0, leave:0, none:0 };
        rows.forEach(function (tr) {
          if (tr.dataset.fixed === 'leave') { c.leave++; return; }
          var v = tr.querySelector('.sch-att-pick').value;
          if (v) { c[v]++; } else { c.none++; }
        });
        Object.keys(c).forEach(function (k) {
          var el = sum.querySelector('[data-c="' + k + '"]');
          if (el) { el.textContent = ar(c[k]); }
        });
        var markable = rows.filter(function (tr) { return tr.dataset.fixed !== 'leave'; }).length;
        cnt.textContent = ar(markable - c.none) + ' / ' + ar(markable) + ' مرصود';
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
