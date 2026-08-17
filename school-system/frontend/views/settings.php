<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$s      = sch_settings();
$years  = SCH_Years::all();
$missing = SCH_Activator::missing_tables();

/** قراءة إعداد مع افتراضه. */
$val = static fn (string $k, string $def): string => (string) ($s[$k] ?? '') !== '' ? (string) $s[$k] : $def;

// روابط الشريط الجانبي للإعدادات — مجموعات كأنظمة العالم الكبرى.
$sch_nav = [
    __('المدرسة', 'school-system') => [
        ['set-school', __('بيانات المدرسة', 'school-system'), 'cog'],
        ['set-years',  __('السنوات الدراسية', 'school-system'), 'calendar'],
    ],
    __('اليوم الدراسي', 'school-system') => [
        ['set-timing', __('مواعيد الحضور', 'school-system'), 'clock'],
        ['set-alerts', __('الإنذارات والتنبيهات', 'school-system'), 'bell'],
    ],
    __('النظام', 'school-system') => [
        ['set-status', __('حالة النظام', 'school-system'), 'shield'],
    ],
];
?>
<h1 class="sch-title"><?php echo sch_icon('cog', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('الإعدادات', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('كل ما يضبط سلوك النظام في مكان واحد منظّم — بيانات المدرسة، السنة، مواعيد الحضور، وحدود الإنذارات.', 'school-system'); ?></p>

<?php if ($missing !== []) : ?>
    <div class="sch-notice sch-notice--error">
        <strong><?php echo esc_html(sprintf(
            /* translators: %d: عدد الجداول */
            _n('جدول واحد لم يُنشأ في قاعدة البيانات', '%d جداول لم تُنشأ في قاعدة البيانات', count($missing), 'school-system'),
            count($missing)
        )); ?></strong>
        <p><?php esc_html_e('كل عملية على هذه الجداول ستفشل. حدّث الإضافة أو راجع صلاحيات قاعدة البيانات.', 'school-system'); ?></p>
        <code dir="ltr"><?php echo esc_html(implode(' · ', $missing)); ?></code>
    </div>
<?php endif; ?>

<div class="sch-set">
    <nav class="sch-set__rail" aria-label="<?php esc_attr_e('أقسام الإعدادات', 'school-system'); ?>">
        <?php foreach ($sch_nav as $sch_group => $sch_links) : ?>
            <div class="sch-set__grp">
                <span class="sch-set__glabel"><?php echo esc_html($sch_group); ?></span>
                <?php foreach ($sch_links as [$sch_id, $sch_label, $sch_ic]) : ?>
                    <a class="sch-set__link" href="#<?php echo esc_attr($sch_id); ?>" data-set-link="<?php echo esc_attr($sch_id); ?>">
                        <?php echo sch_icon($sch_ic, 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        <span><?php echo esc_html($sch_label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="sch-set__main">

        <!-- بيانات المدرسة -->
        <section id="set-school" class="sch-set__sec" data-set-sec>
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('cog', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('بيانات المدرسة', 'school-system'); ?></h2>
                    <p><?php esc_html_e('تظهر على البطاقات والشهادات والتقارير المطبوعة.', 'school-system'); ?></p>
                </div>
            </header>
            <div class="sch-card">
                <form method="post">
                    <?php wp_nonce_field('sch_save_settings', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="save_settings">
                    <div class="sch-grid">
                        <label class="sch-field"><span><?php esc_html_e('اسم المدرسة', 'school-system'); ?></span>
                            <input type="text" name="school_name" aria-label="<?php esc_attr_e('اسم المدرسة', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['school_name'] ?? '')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('جوال المدرسة', 'school-system'); ?></span>
                            <input type="tel" name="school_phone" dir="ltr" aria-label="<?php esc_attr_e('جوال المدرسة', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['school_phone'] ?? '')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('البريد الإلكتروني', 'school-system'); ?></span>
                            <input type="email" name="school_email" dir="ltr" aria-label="<?php esc_attr_e('البريد الإلكتروني', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['school_email'] ?? '')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('الرقم الوزاري', 'school-system'); ?></span>
                            <input type="text" name="moe_id" dir="ltr" aria-label="<?php esc_attr_e('الرقم الوزاري', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['moe_id'] ?? '')); ?>"></label>
                        <label class="sch-field sch-field--wide"><span><?php esc_html_e('العنوان', 'school-system'); ?></span>
                            <input type="text" name="address" aria-label="<?php esc_attr_e('العنوان', 'school-system'); ?>" value="<?php echo esc_attr((string) ($s['address'] ?? '')); ?>"></label>
                    </div>
                    <button class="sch-btn sch-mt"><?php esc_html_e('حفظ البيانات', 'school-system'); ?></button>
                </form>
            </div>
        </section>

        <!-- السنوات الدراسية -->
        <section id="set-years" class="sch-set__sec" data-set-sec>
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('calendar', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('السنوات الدراسية', 'school-system'); ?></h2>
                    <p><?php esc_html_e('كل شعبة وتسجيل مرتبط بسنة. السنة النشطة هي التي تعمل عليها الشاشات.', 'school-system'); ?></p>
                </div>
            </header>
            <div class="sch-card">
                <?php if ($years === []) : ?>
                    <div class="sch-empty"><strong><?php esc_html_e('لا توجد سنة دراسية', 'school-system'); ?></strong>
                        <p><?php esc_html_e('أنشئ السنة الحالية لتبدأ في إضافة الشعب والطلاب.', 'school-system'); ?></p></div>
                <?php else : ?>
                    <div class="sch-table-wrap">
                        <table class="sch-table">
                            <thead><tr>
                                <th><?php esc_html_e('السنة', 'school-system'); ?></th>
                                <th><?php esc_html_e('من', 'school-system'); ?></th>
                                <th><?php esc_html_e('إلى', 'school-system'); ?></th>
                                <th></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($years as $y) : ?>
                                <tr>
                                    <td class="sch-name"><?php echo esc_html($y->name); ?></td>
                                    <td dir="ltr"><?php echo esc_html($y->start_date); ?></td>
                                    <td dir="ltr"><?php echo esc_html($y->end_date); ?></td>
                                    <td>
                                        <?php if ($y->is_current) : ?>
                                            <span class="sch-badge sch-badge--ok"><?php esc_html_e('السنة النشطة', 'school-system'); ?></span>
                                        <?php else : ?>
                                            <form method="post">
                                                <?php wp_nonce_field('sch_set_year', '_sch_nonce'); ?>
                                                <input type="hidden" name="sch_action" value="set_year">
                                                <input type="hidden" name="year_id" value="<?php echo esc_attr((string) $y->id); ?>">
                                                <button class="sch-btn sch-btn--quiet"><?php esc_html_e('تفعيل', 'school-system'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <form method="post" class="sch-mt">
                    <?php wp_nonce_field('sch_add_year', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="add_year">
                    <div class="sch-grid">
                        <label class="sch-field"><span><?php esc_html_e('اسم السنة', 'school-system'); ?></span>
                            <input type="text" name="name" placeholder="1447 / 1448" required></label>
                        <label class="sch-field"><span><?php esc_html_e('تاريخ البداية', 'school-system'); ?></span>
                            <input type="date" name="start_date" aria-label="<?php esc_attr_e('تاريخ البداية', 'school-system'); ?>" required></label>
                        <label class="sch-field"><span><?php esc_html_e('تاريخ النهاية', 'school-system'); ?></span>
                            <input type="date" name="end_date" aria-label="<?php esc_attr_e('تاريخ النهاية', 'school-system'); ?>" required></label>
                    </div>
                    <button class="sch-btn sch-mt"><?php esc_html_e('إضافة سنة', 'school-system'); ?></button>
                </form>
            </div>
        </section>

        <!-- مواعيد الحضور -->
        <section id="set-timing" class="sch-set__sec" data-set-sec>
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('clock', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('مواعيد الحضور', 'school-system'); ?></h2>
                    <p><?php esc_html_e('منها يُحسب التأخّر تلقائيًا عند المسح: من تجاوز الموعد + التسامح يُسجَّل «متأخرًا».', 'school-system'); ?></p>
                </div>
            </header>
            <div class="sch-card">
                <form method="post">
                    <?php wp_nonce_field('sch_save_timing', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="save_timing">
                    <div class="sch-grid">
                        <label class="sch-field"><span><?php esc_html_e('بدء دوام الطالب', 'school-system'); ?></span>
                            <input type="time" name="attendance_student_in" dir="ltr" aria-label="<?php esc_attr_e('بدء دوام الطالب', 'school-system'); ?>" value="<?php echo esc_attr($val('attendance_student_in', '07:30')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('بدء دوام الموظف', 'school-system'); ?></span>
                            <input type="time" name="attendance_staff_in" dir="ltr" aria-label="<?php esc_attr_e('بدء دوام الموظف', 'school-system'); ?>" value="<?php echo esc_attr($val('attendance_staff_in', '07:00')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('دقائق التسامح', 'school-system'); ?></span>
                            <input type="number" name="attendance_grace" min="0" max="120" aria-label="<?php esc_attr_e('دقائق التسامح', 'school-system'); ?>" value="<?php echo esc_attr($val('attendance_grace', '10')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('نهاية الدوام', 'school-system'); ?></span>
                            <input type="time" name="attendance_day_end" dir="ltr" aria-label="<?php esc_attr_e('نهاية الدوام', 'school-system'); ?>" value="<?php echo esc_attr($val('attendance_day_end', '13:00')); ?>"></label>
                    </div>
                    <button class="sch-btn sch-mt"><?php esc_html_e('حفظ المواعيد', 'school-system'); ?></button>
                </form>
            </div>
        </section>

        <!-- الإنذارات -->
        <section id="set-alerts" class="sch-set__sec" data-set-sec>
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('bell', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('الإنذارات والتنبيهات', 'school-system'); ?></h2>
                    <p><?php esc_html_e('حدود الحارس الآلي: متى يُنبَّه على من لم يصعد الباص، أو لم يدخل، أو لم يُرصد حضوره.', 'school-system'); ?></p>
                </div>
            </header>
            <div class="sch-card">
                <form method="post">
                    <?php wp_nonce_field('sch_save_alerts', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="save_alerts">
                    <div class="sch-grid">
                        <label class="sch-field"><span><?php esc_html_e('آخر موعد لصعود الباص', 'school-system'); ?></span>
                            <input type="time" name="alert_bus_no_board_at" dir="ltr" aria-label="<?php esc_attr_e('آخر موعد لصعود الباص', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_bus_no_board_at', '06:50')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('يجب الوصول للمدرسة قبل', 'school-system'); ?></span>
                            <input type="time" name="alert_bus_arrival_by" dir="ltr" aria-label="<?php esc_attr_e('يجب الوصول للمدرسة قبل', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_bus_arrival_by', '07:45')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('يجب رصد الحضور قبل', 'school-system'); ?></span>
                            <input type="time" name="alert_attendance_by" dir="ltr" aria-label="<?php esc_attr_e('يجب رصد الحضور قبل', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_attendance_by', '09:00')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('وقت الانصراف', 'school-system'); ?></span>
                            <input type="time" name="alert_dismissal_at" dir="ltr" aria-label="<?php esc_attr_e('وقت الانصراف', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_dismissal_at', '13:00')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('تنبيه «ما زال في المدرسة» بعد (دقيقة)', 'school-system'); ?></span>
                            <input type="number" name="alert_still_at_school" min="0" max="180" aria-label="<?php esc_attr_e('تنبيه ما زال في المدرسة بعد كم دقيقة', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_still_at_school', '30')); ?>"></label>
                        <label class="sch-field"><span><?php esc_html_e('مهلة إحالة العيادة (دقيقة)', 'school-system'); ?></span>
                            <input type="number" name="alert_referral_minutes" min="1" max="120" aria-label="<?php esc_attr_e('مهلة إحالة العيادة بالدقائق', 'school-system'); ?>" value="<?php echo esc_attr($val('alert_referral_minutes', '10')); ?>"></label>
                    </div>
                    <button class="sch-btn sch-mt"><?php esc_html_e('حفظ الحدود', 'school-system'); ?></button>
                </form>
            </div>
        </section>

        <!-- حالة النظام -->
        <section id="set-status" class="sch-set__sec" data-set-sec>
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('shield', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('حالة النظام', 'school-system'); ?></h2>
                    <p><?php esc_html_e('نظرة سريعة على سلامة التركيب — تُغنيك عن البحث في الأماكن الخطأ.', 'school-system'); ?></p>
                </div>
            </header>
            <div class="sch-card">
                <div class="sch-set__stat">
                    <div class="sch-set__row">
                        <span><?php esc_html_e('إصدار النظام', 'school-system'); ?></span>
                        <b dir="ltr"><?php echo esc_html(SCH_VERSION); ?></b>
                    </div>
                    <div class="sch-set__row">
                        <span><?php esc_html_e('قاعدة البيانات', 'school-system'); ?></span>
                        <?php if ($missing === []) : ?>
                            <b class="is-ok"><?php echo sch_icon('check', 14); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('كل الجداول سليمة', 'school-system'); ?></b>
                        <?php else : ?>
                            <b class="is-bad"><?php echo esc_html(sprintf(
                                /* translators: %d: عدد الجداول */
                                _n('جدول ناقص', '%d جداول ناقصة', count($missing), 'school-system'),
                                count($missing)
                            )); ?></b>
                        <?php endif; ?>
                    </div>
                    <?php $sch_cur = SCH_Years::current(); ?>
                    <div class="sch-set__row">
                        <span><?php esc_html_e('السنة النشطة', 'school-system'); ?></span>
                        <b><?php echo esc_html($sch_cur->name ?? '—'); ?></b>
                    </div>
                </div>
            </div>
        </section>

    </div>
</div>

<script>
(function () {
  var links = Array.prototype.slice.call(document.querySelectorAll('[data-set-link]'));
  var secs  = Array.prototype.slice.call(document.querySelectorAll('[data-set-sec]'));
  if (!links.length || !('IntersectionObserver' in window)) { return; }

  function activate(id) {
    links.forEach(function (a) { a.classList.toggle('is-active', a.getAttribute('data-set-link') === id); });
  }

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) { if (e.isIntersecting) { activate(e.target.id); } });
  }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

  secs.forEach(function (s) { io.observe(s); });

  links.forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var t = document.getElementById(a.getAttribute('data-set-link'));
      if (t) { t.scrollIntoView({ behavior: 'smooth', block: 'start' }); activate(t.id); }
    });
  });
})();
</script>
