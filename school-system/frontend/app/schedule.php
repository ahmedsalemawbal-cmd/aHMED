<?php
/**
 * الجدول الدراسي — أسبوع كامل في شبكة واحدة.
 *
 * الأب لا يسأل «ما حصص الأحد؟» بل «أين ابني الآن ومتى ينصرف» — فالأسبوع
 * يُقرأ دفعةً واحدة كما يُعلَّق على الثلاجة، لا يومًا يومًا في ستّ بطاقات
 * تُمرَّر. وشريط «الآن» فوق الشبكة يجيب السؤال قبل أن تُقرأ خانة واحدة.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id      = (int) ($sch_data['id'] ?? 0);
$class   = SCH_Students::current_class($id);
$grid    = $class ? SCH_Timetable::grid((int) $class->id) : [];
$exams   = $class ? SCH_Assessment::upcoming_exams((int) $class->id) : [];
$w       = (int) current_time('w');
$day_now = $w === 0 ? 1 : ($w <= 4 ? $w + 1 : 0);

// اسم الابن من قائمة الأبناء المحمَّلة أصلًا في القالب — بلا استعلام
// ثانٍ لاسمٍ بين أيدينا.
$sch_kid = '';
foreach ((array) ($sch_data['children'] ?? []) as $sch_c) {
    if ((int) $sch_c->id === $id) {
        $sch_kid = (string) ($sch_c->first_name ?: $sch_c->full_name);
        break;
    }
}
$sch_meta = implode(' · ', array_filter([$sch_kid, $class ? SCH_Classes::label($class) : '']));

/**
 * المواقيت غير مخزَّنة: الجدول يحفظ **رقم الحصة** لا ساعتها، ولا دالّة
 * في الطبقة تُرجع ساعات اليوم. فتُشتق هنا من بداية دوامٍ ثابتة وطولِ
 * حصة ثابت — قيمةٌ معقولة تُقرأ، ولا يُخترع لها استدعاء لا وجود له.
 * ويوم تُخزَّن المواقيت تُستبدل هذه الأسطر الثلاثة وحدها.
 */
$sch_open    = 7 * 60 + 30;  // بداية اليوم ٧:٣٠
$sch_len     = 50;           // طول الحصة بالدقائق
$sch_brk_at  = 3;            // الفسحة بعد الحصة الثالثة
$sch_brk_len = 30;

$sch_at = static function (int $period) use ($sch_open, $sch_len, $sch_brk_at, $sch_brk_len): int {
    $m = $sch_open + ($period - 1) * $sch_len;
    return $period > $sch_brk_at ? $m + $sch_brk_len : $m;
};

// الساعة بأرقام اللغة، ودقيقتان دائمًا: «٩:٥» تُقرأ رقمًا لا وقتًا.
$sch_clock = static function (int $m): string {
    $h   = intdiv($m, 60) % 24;
    $h12 = $h % 12 === 0 ? 12 : $h % 12;
    $mm  = $m % 60;

    return number_format_i18n($h12) . ':'
        . ($mm < 10 ? number_format_i18n(0) . number_format_i18n($mm) : number_format_i18n($mm));
};

// خانةٌ بعرض خُمس الشاشة لا تتّسع لاسم رباعي، والاسم الأول يكفي للتعرّف.
// و«أ.» تُتخطّى لأنها لقبٌ لا اسم.
$sch_first = static function (string $name): string {
    foreach (preg_split('/\s+/', trim($name)) ?: [] as $sch_part) {
        if ($sch_part !== '' && !str_ends_with($sch_part, '.')) {
            return $sch_part;
        }
    }

    return trim($name);
};

// الحصة الجارية الآن — لحلقة الخانة ولشريط «الآن» معًا.
$sch_min    = (int) current_time('G') * 60 + (int) current_time('i');
$sch_now_no = 0;
for ($sch_n = 1; $sch_n <= SCH_Timetable::PERIODS; $sch_n++) {
    $sch_s = $sch_at($sch_n);
    if ($sch_min >= $sch_s && $sch_min < $sch_s + $sch_len) {
        $sch_now_no = $sch_n;
        break;
    }
}
$sch_live = ($sch_now_no > 0 && !empty($grid[$day_now][$sch_now_no])) ? $grid[$day_now][$sch_now_no] : null;

// لا صفَّ لحصّةٍ فارغة في الأسبوع كلّه: الشبكة تعرض ما هو مجدوَل فعلًا.
$sch_rows = [];
for ($sch_n = 1; $sch_n <= SCH_Timetable::PERIODS; $sch_n++) {
    foreach (array_keys(SCH_Timetable::DAYS) as $sch_d) {
        if (!empty($grid[$sch_d][$sch_n])) {
            $sch_rows[] = $sch_n;
            break;
        }
    }
}
$sch_last = $sch_rows === [] ? 0 : (int) end($sch_rows);
?>

<header class="p-hd">
    <a class="p-hd__back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>"
       aria-label="<?php esc_attr_e('رجوع', 'school-system'); ?>">
        <?php echo sch_icon('chev', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>
    <h1 class="p-h1 p-hd__t"><?php esc_html_e('الجدول الدراسي', 'school-system'); ?></h1>
    <?php if ($sch_meta !== '') : ?>
        <span class="p-hd__m"><?php echo esc_html($sch_meta); ?></span>
    <?php endif; ?>
</header>

<?php if ($sch_live) : ?>
    <?php /* النقطة تنبض فيُعرَف أن السطر حيٌّ الآن لا مكتوبٌ من أمس */ ?>
    <div class="p-now">
        <span class="p-now__d" aria-hidden="true"></span>
        <span class="p-now__x"><?php echo esc_html($sch_live->teacher_name
            ? sprintf(
                /* translators: 1: المادة 2: اسم المعلم 3: وقت انتهاء الحصة */
                __('الآن: %1$s — %2$s · حتى %3$s', 'school-system'),
                (string) $sch_live->subject_name,
                (string) $sch_live->teacher_name,
                $sch_clock($sch_at($sch_now_no) + $sch_len)
            )
            : sprintf(
                /* translators: 1: المادة 2: وقت انتهاء الحصة */
                __('الآن: %1$s · حتى %2$s', 'school-system'),
                (string) $sch_live->subject_name,
                $sch_clock($sch_at($sch_now_no) + $sch_len)
            )); ?></span>
    </div>
<?php endif; ?>

<?php if ($sch_rows === []) : ?>

    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('calendar', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لم يُعتمد الجدول بعد', 'school-system'); ?></b>
        <p><?php esc_html_e('يظهر هنا فور اعتماده من المدرسة.', 'school-system'); ?></p>
    </div>

<?php else : ?>

    <div class="p-tt">
        <div class="p-tt__hd">
            <?php /* خانة فارغة بإزاء عمود المواقيت حتى تصطفّ الأيام فوق خاناتها */ ?>
            <span aria-hidden="true"></span>
            <?php foreach (SCH_Timetable::DAYS as $sch_dn => $sch_dl) : ?>
                <span class="p-tt__d<?php echo esc_attr($sch_dn === $day_now ? ' is-now' : ''); ?>">
                    <?php echo esc_html($sch_dl); ?>
                </span>
            <?php endforeach; ?>
        </div>

        <div class="p-tt__g">
            <?php /* فسحةٌ فوق أوّل حصة ليست فسحة: يومٌ يبدأ بالحصة الرابعة
                     لا فاصل فيه بعدُ، فتُعدّ مؤدّاةً قبل أن تُرسم. */ ?>
            <?php $sch_brk_done = (int) reset($sch_rows) > $sch_brk_at; ?>
            <?php foreach ($sch_rows as $sch_n) : ?>

                <?php /* الفسحة صفٌّ يعبر الشبكة كلّها: هي فاصل اليوم لا حصّة فيه */ ?>
                <?php if (!$sch_brk_done && $sch_n > $sch_brk_at) : ?>
                    <span class="p-tt__brk"><?php echo esc_html(sprintf(
                        /* translators: %s: وقت بداية الفسحة */
                        __('الفسحة · %s', 'school-system'),
                        $sch_clock($sch_at($sch_brk_at) + $sch_len)
                    )); ?></span>
                    <?php $sch_brk_done = true; ?>
                <?php endif; ?>

                <span class="p-tt__t"><span class="p-nm"><?php echo esc_html($sch_clock($sch_at($sch_n))); ?></span></span>

                <?php foreach (SCH_Timetable::DAYS as $sch_d => $sch_dl) : ?>
                    <?php $sch_slot = $grid[$sch_d][$sch_n] ?? null; ?>
                    <?php if (!$sch_slot) : ?>
                        <?php /* الخانة الفارغة تبقى قائمةً: حذفها يزحزح ما بعدها يومًا كاملًا */ ?>
                        <span class="p-tt__c p-tt__c--off" aria-hidden="true"></span>
                    <?php else : ?>
                        <?php
                        /**
                         * الخانة تعرض الاسم الأول وحده لضيقها، والاسم الكامل — وهو ما
                         * كان يُعرض قبل الشبكة — يبقى في التلميح فلا يضيع من الشاشة.
                         */
                        $sch_tip = implode(' · ', array_filter([
                            $sch_dl,
                            sprintf(
                                /* translators: %s: رقم الحصة */
                                __('الحصة %s', 'school-system'),
                                number_format_i18n($sch_n)
                            ),
                            $sch_clock($sch_at($sch_n)),
                            (string) $sch_slot->teacher_name,
                        ]));
                        ?>
                        <?php // نغمة ثابتة لكل مادة تُشتق من معرّفها، فتُعرَف بالعين قبل قراءة اسمها ?>
                        <span class="p-tt__c p-tt__c--<?php echo esc_attr((string) ((int) $sch_slot->subject_id % 5)); ?><?php echo esc_attr($sch_d === $day_now && $sch_n === $sch_now_no ? ' is-now' : ''); ?>"
                              title="<?php echo esc_attr($sch_tip); ?>">
                            <b><?php echo esc_html((string) $sch_slot->subject_name); ?></b>
                            <?php if ($sch_slot->teacher_name) : ?>
                                <span><?php echo esc_html($sch_first((string) $sch_slot->teacher_name)); ?></span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php endforeach; ?>
        </div>
    </div>

    <?php /* الانصراف آخر ما يُقرأ لأنه آخر ما يحدث — ومشتقٌّ من نهاية آخر حصة */ ?>
    <div class="p-out">
        <span class="p-out__k"><?php esc_html_e('الانصراف', 'school-system'); ?></span>
        <span class="p-out__v p-nm"><?php echo esc_html($sch_clock($sch_at($sch_last) + $sch_len)); ?></span>
    </div>

<?php endif; ?>

<?php /* الاختبارات القادمة ليست في لوح الأسبوع، لكنها بيان الشاشة نفسها:
         الأب الذي يفتح الجدول يسأل «ماذا ينتظره» — فتُقرأ بعده لا قبله. */ ?>
<?php if ($exams !== []) : ?>
    <div class="p-sect"><h2 class="p-sect__h"><?php esc_html_e('اختبارات قادمة', 'school-system'); ?></h2></div>
    <div class="p-list">
        <?php foreach ($exams as $e) : ?>
            <div class="p-row">
                <span class="p-date">
                    <b><?php echo esc_html(wp_date('j', strtotime((string) $e->exam_date))); ?></b>
                    <em><?php echo esc_html(wp_date('M', strtotime((string) $e->exam_date))); ?></em>
                </span>
                <span class="p-row__t">
                    <b><?php echo esc_html((string) $e->title); ?></b>
                    <span><?php echo esc_html((string) $e->subject_name); ?></span>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
