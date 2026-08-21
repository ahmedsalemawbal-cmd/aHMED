<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * حضور الطلاب — شاشة البوابة.
 *
 * الرصد آلي: البطاقة تُمسح على البوابة فيُكتب الحضور وحده. ودور هذه الشاشة
 * أن تُري الموظف ما لم يحدث بعد — «بانتظار المسح» — لا أن تطلب منه رصد
 * أربعمئة طالب بيده. ولذلك الكشف على مستوى المدرسة لا على شعبة: الماسح لا
 * يعرف شعبة القادم إليه، والتصفية بالشعبة قرار عرض لا قرار استعلام.
 *
 * والعدّان — المرصود والمنتظر — يخرجان من `day_sheet()` نفسها لا من
 * `day_summary()`: لو جاء رقم من مصدر ورقم من آخر لاختلف ما يُعرض عمّا
 * سيُغلق، وزرّ الإغلاق لا رجعة فيه.
 */

$sch_date  = current_time('Y-m-d');
$sch_sheet = SCH_Attendance::day_sheet($sch_date);
$sch_feed  = SCH_Custody::recent(12);
$sch_last  = $sch_feed[0] ?? null;
$sch_total = count($sch_sheet);

$sch_n = ['present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0, 'none' => 0];

foreach ($sch_sheet as $sch_row) {
    $sch_key = (string) ($sch_row->status ?? '');
    $sch_n[isset($sch_n[$sch_key]) ? $sch_key : 'none']++;
}

// من دخل البوابة فعلًا: الحاضر والمتأخر كلاهما دخل — والتأخّر صفة دخول لا غياب.
$sch_in = $sch_n['present'] + $sch_n['late'];

/** نسبة حالة من الكشف كله — لعرض شريط التقدّم. */
$sch_pct = static function (int $count) use ($sch_total): string {
    return $sch_total > 0 ? (string) round($count * 100 / $sch_total, 2) : '0';
};

// أسماء الحالات كما في التصميم. «بانتظار المسح» حالة قائمة بذاتها لا صفر عدّاد.
$sch_lbl = [
    'present' => __('حاضر', 'school-system'),
    'late'    => __('متأخر', 'school-system'),
    'excused' => __('بعذر', 'school-system'),
    'absent'  => __('غائب', 'school-system'),
    'none'    => __('بانتظار المسح', 'school-system'),
];

// نغمة كل حدث في سجل المسح: قادم · مغادر · إدخال إداري.
$sch_tone = [
    'bus_board'  => 'in',
    'gate_in'    => 'in',
    'gate_out'   => 'out',
    'early_out'  => 'out',
    'bus_alight' => 'out',
    'manual'     => 'note',
    'correction' => 'note',
];

// أرقام اللغة — تُمرَّر للسكربت ليكتب الساعة الحيّة بأرقام الشاشة نفسها
// لا بأرقام لاتينية تقفز فوق ما رسمه الخادم.
$sch_digits = '';
for ($sch_i = 0; $sch_i < 10; $sch_i++) {
    $sch_digits .= number_format_i18n($sch_i);
}

// ساعة اللوح: `sch_clock` تُعطي HH:MM وحدهما، والثواني تُوطَّن بخريطة الأرقام
// نفسها — فلا تختلف أرقام الساعة عن أرقام الشاشة عند أول رسم.
// و`array_combine` ترمي ValueError في PHP 8 حين تختلف الأطوال — لا تُرجع false
// كما كانت في السابع، فحارس `is_array` لا يمسك شيئًا. والساعة زينة على اللوح،
// فلا يجوز أن تُسقط شاشة البوابة كلها لو شذّ توطين الأرقام.
$sch_pair = mb_strlen($sch_digits) === 10 ? mb_str_split($sch_digits) : [];
$sch_map  = $sch_pair === [] ? [] : array_combine(str_split('0123456789'), $sch_pair);
$sch_now  = strtr(current_time('H:i:s'), $sch_map);

// وقت آخر بطاقة مقروءة — «--:--» حين لم يُمسح شيء بعد.
$sch_last_at = $sch_last
    ? sch_clock(substr((string) $sch_last->occurred_at, 11, 5))
    : '--:--';
?>
<div class="sch-gate__head">
    <div class="sch-gate__ttlw">
        <h1 class="sch-gate__ttl"><?php esc_html_e('حضور الطلاب', 'school-system'); ?></h1>
        <div class="sch-gate__sub"><?php esc_html_e('الرصد آلي عبر مسح باركود البطاقة على البوابة. تتدخّل يدويًا في الاستثناءات فقط.', 'school-system'); ?></div>
    </div>

    <div class="sch-gate__tags">
        <div class="sch-gate__tag">
            <span class="sch-gate__pulse"></span>
            <span class="sch-gate__tagt"><?php esc_html_e('الماسح متصل', 'school-system'); ?></span>
            <span class="sch-gate__sep"></span>
            <span class="sch-gate__tagm"><?php esc_html_e('البوابة الرئيسية', 'school-system'); ?></span>
        </div>
        <div class="sch-gate__tag">
            <span class="sch-gate__tagm"><?php esc_html_e('جرس الحصة', 'school-system'); ?></span>
            <span class="sch-gate__mono"><?php echo esc_html(sch_clock(SCH_Attendance::expected_in())); ?></span>
        </div>
    </div>
</div>

<div class="sch-gate" id="sch-gate">
    <div class="sch-gate__main">

        <div class="sch-gate__sum">
            <div class="sch-gate__sumh">
                <div class="sch-gate__lead">
                    <span class="sch-gate__big"><?php echo esc_html(number_format_i18n($sch_in)); ?></span>
                    <span class="sch-gate__of">
                        <?php echo esc_html(sprintf(
                            /* translators: %s: عدد الطلاب في الكشف */
                            __('من %s طلاب دخلوا البوابة', 'school-system'),
                            number_format_i18n($sch_total)
                        )); ?>
                    </span>
                </div>

                <div class="sch-gate__keys">
                    <?php foreach (['present', 'late', 'excused', 'absent', 'none'] as $sch_k) : ?>
                        <div class="sch-gate__key sch-gate__key--<?php echo esc_attr($sch_k); ?>">
                            <span class="sch-gate__sq"></span>
                            <span class="sch-gate__keyt"><?php echo esc_html($sch_lbl[$sch_k]); ?></span>
                            <span class="sch-gate__keyn"><?php echo esc_html(number_format_i18n($sch_n[$sch_k])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sch-gate__bar">
                <?php foreach (['present', 'late', 'excused', 'absent'] as $sch_k) : ?>
                    <?php if ($sch_n[$sch_k] > 0) : ?>
                        <span class="sch-gate__seg sch-gate__seg--<?php echo esc_attr($sch_k); ?>"
                              style="width: <?php echo esc_attr($sch_pct($sch_n[$sch_k])); ?>%"></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sch-gate__filters">
            <div class="sch-gate__searchw">
                <input type="text" id="sch-gate-q" class="sch-gate__search" autocomplete="off"
                       placeholder="<?php esc_attr_e('ابحث باسم الطالب أو رقم بطاقته', 'school-system'); ?>">
                <span class="sch-gate__ic"><?php echo sch_icon('search', 14); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            </div>

            <?php
            $sch_chips = [
                'all'     => [__('الكل', 'school-system'), $sch_total],
                'present' => [$sch_lbl['present'], $sch_n['present']],
                'late'    => [$sch_lbl['late'], $sch_n['late']],
                'none'    => [$sch_lbl['none'], $sch_n['none']],
            ];
            foreach ($sch_chips as $sch_k => $sch_c) :
                ?>
                <button type="button" class="sch-gate__chip sch-gate__chip--<?php echo esc_attr($sch_k); ?><?php echo $sch_k === 'all' ? ' is-on' : ''; ?>"
                        data-sch-filter="<?php echo esc_attr($sch_k); ?>"
                        aria-pressed="<?php echo $sch_k === 'all' ? 'true' : 'false'; ?>">
                    <span class="sch-gate__dot"></span>
                    <span class="sch-gate__chipt"><?php echo esc_html($sch_c[0]); ?></span>
                    <span class="sch-gate__chipn"><?php echo esc_html(number_format_i18n($sch_c[1])); ?></span>
                </button>
            <?php endforeach; ?>

            <button type="button" class="sch-gate__toggle" id="sch-gate-mode" aria-pressed="false">
                <?php esc_html_e('رصد يدوي', 'school-system'); ?>
            </button>
        </div>

        <?php if ($sch_sheet === []) : ?>
            <div class="sch-blank">
                <span class="sch-blank__ic"><?php echo sch_icon('users', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <strong><?php esc_html_e('لا يوجد طلاب نشطون', 'school-system'); ?></strong>
                <p><?php esc_html_e('سجّل طلابًا أولًا من شاشة الطلاب، ثم تظهر بطاقاتهم هنا فور مسحها على البوابة.', 'school-system'); ?></p>
            </div>
        <?php else : ?>
            <?php /* نموذج واحد لكل الكشف: كل زرّ يحمل حالته باسمه وقيمته،
                     فلا يُرسَل إلا ما ضُغط عليه — والمعالج القائم يقرأه كما هو. */ ?>
            <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" id="sch-gate-form">
                <?php wp_nonce_field('sch_mark_attendance', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="mark_attendance">
                <input type="hidden" name="att_date" value="<?php echo esc_attr($sch_date); ?>">

                <div class="sch-gate__list" id="sch-gate-list">
                    <?php foreach ($sch_sheet as $sch_r) :
                        $sch_id   = (int) $sch_r->id;
                        $sch_name = (string) $sch_r->full_name;
                        $sch_st   = (string) ($sch_r->status ?? '');
                        $sch_st   = isset($sch_lbl[$sch_st]) && $sch_st !== 'none' ? $sch_st : 'none';
                        $sch_no   = (string) ($sch_r->academic_no ?? $sch_r->student_no ?? '');
                        $sch_at   = $sch_r->checked_in_at ? sch_clock(substr((string) $sch_r->checked_in_at, 11, 5)) : '';
                        $sch_at   = $sch_at !== '' ? $sch_at : '— : —';
                        $sch_ltr  = trim($sch_name) !== '' ? mb_substr(trim($sch_name), 0, 1) : '؟';
                        ?>
                        <article class="sch-gate__row" data-status="<?php echo esc_attr($sch_st); ?>"
                                 data-find="<?php echo esc_attr($sch_name . ' ' . $sch_no); ?>">
                            <span class="sch-gate__av" aria-hidden="true"><?php echo esc_html($sch_ltr); ?></span>

                            <span class="sch-gate__id">
                                <span class="sch-gate__name"><?php echo esc_html($sch_name); ?></span>
                                <span class="sch-gate__no"><?php echo esc_html($sch_no); ?></span>
                            </span>

                            <span class="sch-gate__meta">
                                <span class="sch-gate__st">
                                    <span class="sch-gate__dot"></span>
                                    <span><?php echo esc_html($sch_lbl[$sch_st]); ?></span>
                                </span>
                                <span class="sch-gate__time"><?php echo esc_html($sch_at); ?></span>
                            </span>

                            <span class="sch-gate__acts">
                                <button type="submit" class="sch-gate__act sch-gate__act--absent"
                                        name="status[<?php echo esc_attr((string) $sch_id); ?>]" value="absent">
                                    <?php esc_html_e('تسجيل غياب', 'school-system'); ?>
                                </button>
                                <button type="submit" class="sch-gate__act sch-gate__act--excused"
                                        name="status[<?php echo esc_attr((string) $sch_id); ?>]" value="excused">
                                    <?php esc_html_e('بعذر', 'school-system'); ?>
                                </button>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php /* اللوح الحيّ — يُصيَّر من الخادم ويُستبدل وحده كل بضع ثوانٍ. */ ?>
    <aside class="sch-gate__live" id="sch-gate-live">
        <div class="sch-gate__lh">
            <span class="sch-gate__lht">
                <span class="sch-gate__pulse"></span>
                <b><?php esc_html_e('المسح المباشر', 'school-system'); ?></b>
            </span>
            <span class="sch-gate__clock" id="sch-gate-clock"
                  data-t="<?php echo esc_attr(current_time('H:i:s')); ?>"
                  data-d="<?php echo esc_attr($sch_digits); ?>"><?php echo esc_html($sch_now); ?></span>
        </div>

        <div class="sch-gate__last">
            <span class="sch-gate__sweep" aria-hidden="true"></span>
            <span class="sch-gate__cap"><?php esc_html_e('آخر بطاقة مقروءة', 'school-system'); ?></span>
            <span class="sch-gate__lname">
                <?php echo $sch_last
                    ? esc_html((string) $sch_last->full_name)
                    : esc_html__('بانتظار أول مسح', 'school-system'); ?>
            </span>
            <span class="sch-gate__lrow">
                <span class="sch-gate__lt"><?php echo esc_html($sch_last_at); ?></span>
                <span class="sch-gate__lmeta">
                    <?php echo $sch_last
                        ? esc_html(SCH_Custody::CHECKPOINTS[(string) $sch_last->checkpoint] ?? (string) $sch_last->checkpoint)
                        : esc_html__('الماسح جاهز', 'school-system'); ?>
                </span>
            </span>
        </div>

        <div class="sch-gate__feedw">
            <span class="sch-gate__cap"><?php esc_html_e('سجل المسح', 'school-system'); ?></span>

            <?php if ($sch_feed === []) : ?>
                <div class="sch-gate__none"><?php esc_html_e('لم تُقرأ أي بطاقة بعد', 'school-system'); ?></div>
            <?php else : ?>
                <div class="sch-gate__feed">
                    <?php foreach ($sch_feed as $sch_e) :
                        $sch_cp = (string) $sch_e->checkpoint;
                        ?>
                        <div class="sch-gate__ev sch-gate__ev--<?php echo esc_attr($sch_tone[$sch_cp] ?? 'note'); ?>">
                            <span class="sch-gate__dot"></span>
                            <span class="sch-gate__evn"><?php echo esc_html((string) $sch_e->full_name); ?></span>
                            <span class="sch-gate__evl"><?php echo esc_html(SCH_Custody::CHECKPOINTS[$sch_cp] ?? $sch_cp); ?></span>
                            <span class="sch-gate__evt"><?php echo esc_html(sch_clock(substr((string) $sch_e->occurred_at, 11, 5))); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="sch-gate__foot">
            <div class="sch-gate__pend">
                <span><?php echo esc_html($sch_lbl['none']); ?></span>
                <b><?php echo esc_html(number_format_i18n($sch_n['none'])); ?></b>
            </div>

            <?php /* فعل لا رجعة فيه: يكتب غيابًا على كل من لم يُرصد ويُبلغ أهلهم. */ ?>
            <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" onsubmit="return confirm('<?php esc_attr_e('سيُسجَّل غياب كل من لم تُقرأ بطاقته اليوم ويصل أهلهم إشعار. لا رجعة. متابعة؟', 'school-system'); ?>');">
                <?php wp_nonce_field('sch_close_attendance', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="close_attendance">
                <button type="submit" class="sch-gate__close" <?php disabled($sch_n['none'], 0); ?>>
                    <?php esc_html_e('إغلاق الكشف وتسجيل الغياب', 'school-system'); ?>
                </button>
            </form>

            <span class="sch-gate__hint">
                <?php echo $sch_n['none'] > 0
                    ? esc_html(sprintf(
                        /* translators: %s: عدد الطلاب الذين لم تُرصد حالتهم */
                        _n(
                            'سيُسجَّل غياب طالب واحد ويُبلَّغ وليّ أمره.',
                            'سيُسجَّل غياب %s طلاب ويُبلَّغ أولياء الأمور.',
                            $sch_n['none'],
                            'school-system'
                        ),
                        number_format_i18n($sch_n['none'])
                    ))
                    : esc_html__('لم يبقَ أحد بلا رصد — الكشف مكتمل.', 'school-system'); ?>
            </span>
        </div>
    </aside>
</div>


<script>
/* الوضعان والمرشّحات والبحث في المتصفّح: الطلاب مُصيَّرون مرّة واحدة،
   والسكربت يُظهر ويُخفي — فلا إعادة تحميل بين ضغطة وأخرى.
   واللوح الحيّ يُجلب من الصفحة نفسها لا من مسار جديد: الصلاحية محسومة
   بالصفحة، ومسار REST إضافي يعني بابًا آخر يُحرَس. */
(function () {
  var list = document.getElementById('sch-gate-list');
  var box  = document.getElementById('sch-gate-q');
  var mode = document.getElementById('sch-gate-mode');
  var chips = document.querySelectorAll('[data-sch-filter]');
  var rows = list ? Array.prototype.slice.call(list.querySelectorAll('.sch-gate__row')) : [];
  var pick = 'all';

  function apply() {
    var term = (box && box.value ? box.value : '').trim().toLowerCase();

    rows.forEach(function (row) {
      var okState = pick === 'all' || row.getAttribute('data-status') === pick;
      var okTerm  = term === '' || (row.getAttribute('data-find') || '').toLowerCase().indexOf(term) > -1;
      row.hidden = !(okState && okTerm);
    });
  }

  Array.prototype.forEach.call(chips, function (chip) {
    chip.addEventListener('click', function () {
      pick = chip.getAttribute('data-sch-filter');

      Array.prototype.forEach.call(chips, function (c) {
        var on = c === chip;
        c.classList.toggle('is-on', on);
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
      });

      apply();
    });
  });

  if (box) { box.addEventListener('input', apply); }

  if (mode && list) {
    mode.addEventListener('click', function () {
      var on = !list.classList.contains('is-cards');
      list.classList.toggle('is-cards', on);
      mode.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  /* ── الساعة الحيّة ── */
  var secs = -1;
  var digits = [];

  function readClock() {
    var el = document.getElementById('sch-gate-clock');
    if (!el) { return null; }

    var parts = (el.getAttribute('data-t') || '').split(':');
    digits = (el.getAttribute('data-d') || '0123456789').split('');
    secs = (+parts[0] || 0) * 3600 + (+parts[1] || 0) * 60 + (+parts[2] || 0);
    return el;
  }

  function two(n) {
    var s = n < 10 ? '0' + n : '' + n;
    return s.replace(/\d/g, function (c) { return digits[+c] || c; });
  }

  function tick() {
    var el = document.getElementById('sch-gate-clock');
    if (!el || secs < 0) { return; }

    secs = (secs + 1) % 86400;
    el.textContent = two(Math.floor(secs / 3600)) + ':' + two(Math.floor(secs / 60) % 60) + ':' + two(secs % 60);
  }

  /* ── تحديث اللوح وحده ── */
  function refresh() {
    if (document.hidden) { return; }

    window.fetch(window.location.href, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
      .then(function (html) {
        var live = document.getElementById('sch-gate-live');
        var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('sch-gate-live');

        /* لا يُسحب اللوح من تحت يد من يضغط زرّه في هذه اللحظة. */
        if (live && fresh && !live.contains(document.activeElement)) {
          live.innerHTML = fresh.innerHTML;
          readClock();
        }
      })
      .catch(function () {});
  }

  readClock();
  var beat = window.setInterval(tick, 1000);
  var pull = window.setInterval(refresh, 8000);

  window.addEventListener('pagehide', function () {
    window.clearInterval(beat);
    window.clearInterval(pull);
  });
}());
</script>
