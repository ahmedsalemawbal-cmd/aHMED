<?php
/**
 * تقرير الروضة اليومي.
 *
 * التقرير يومٌ واحد يُقرأ لا جدولٌ يُفحص: أربع حقائق عن طفلٍ لا يحكي —
 * ماذا أكل، وكم نام، وكيف كان مزاجه، وماذا عمل. فصُفَّت كما تُسأل:
 * الوجبة والنوم رقمان في بطاقتين متجاورتين، والمزاج **وجوهٌ تُرى**
 * لا كلمة في صفٍّ رمادي، والنشاط جملة تُقرأ.
 *
 * و`SCH_KG::history` تُرجع أربعة عشر يومًا لا يومًا واحدًا: أحدثها
 * يُعرض بهيئته الكاملة كما في التصميم، وما قبله تحت «أيام سابقة»
 * بالبنية نفسها. حذف الأيام السابقة كان سيُلغي قيمة الاستدعاء نفسه —
 * والأب يقارن أسبوعًا بأسبوع، فيرى النوم يقصر أو المزاج يتكدّر.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id   = (int) ($sch_data['id'] ?? 0);
$days = SCH_KG::history($id, 14);

/**
 * سطر الرأس: الطفل · شعبته · تاريخ أحدث تقرير.
 *
 * التاريخ في الرأس لا فوق كل بطاقة، فالتقرير المعروض واحدٌ في الأصل
 * وتكرار تاريخه ثلاث مرّات في شاشةٍ واحدة ضجيج. وكل جزء مشروطٌ بوجوده
 * فلا يبقى فاصلٌ معلّق حين ينقص أحدها.
 *
 * والاسم يُؤخذ من قائمة الأبناء المحمَّلة أصلًا في القالب — بلا استعلام
 * ثانٍ لاسمٍ بين أيدينا.
 */
$sch_kid = '';
foreach ((array) ($sch_data['children'] ?? []) as $sch_c) {
    if ((int) $sch_c->id === $id) {
        $sch_kid = (string) ($sch_c->first_name ?: $sch_c->full_name);
        break;
    }
}

$sch_class = SCH_Students::current_class($id);

$sch_bits = array_filter([
    $sch_kid,
    $sch_class ? SCH_Classes::label($sch_class) : '',
    $days !== [] ? (string) wp_date('l j M', strtotime((string) $days[0]->log_date)) : '',
]);
?>

<header class="p-hd">
    <a class="p-hd__back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>"
       aria-label="<?php esc_attr_e('رجوع', 'school-system'); ?>">
        <?php echo sch_icon('chev', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>
    <span class="p-hd__c">
        <h1 class="p-h1 p-hd__t"><?php esc_html_e('التقرير اليومي', 'school-system'); ?></h1>
        <?php if ($sch_bits !== []) : ?>
            <span class="p-hd__s"><?php echo esc_html(implode(' · ', $sch_bits)); ?></span>
        <?php endif; ?>
    </span>
</header>

<?php if ($days === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('sun', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا تقارير بعد', 'school-system'); ?></b>
        <p><?php esc_html_e('تكتبه المعلمة في نهاية كل يوم.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <?php foreach ($days as $sch_i => $day) :
        /**
         * نغمة الوجبة تُشتق من مفتاحها لا تُكتب.
         * «لم يأكل» بلون «أكل كل الوجبة» يجعل اللون زينةً لا خبرًا،
         * والأب يقرأ اللون قبل الكلمة.
         */
        $sch_meal_tone = match ((string) $day->meal) {
            'all', 'most' => 'ok',
            'some'        => 'warn',
            'none'        => 'bad',
            default       => 'mute',
        };
        ?>

        <?php if ($sch_i === 1) : ?>
            <div class="p-sect"><h2 class="p-sect__h"><?php esc_html_e('أيام سابقة', 'school-system'); ?></h2></div>
        <?php endif; ?>

        <?php if ($sch_i > 0) : ?>
            <b class="p-h2"><?php echo esc_html(wp_date('l · j M', strtotime((string) $day->log_date))); ?></b>
        <?php endif; ?>

        <!-- الوجبة والنوم: حقيقتان مستقلّتان، فبطاقتان لا صفّان في جدول -->
        <div class="p-kgtop">
            <div class="p-kgt p-kgt--<?php echo esc_attr($sch_meal_tone); ?>">
                <span class="p-kgt__i"><?php echo sch_icon('leaf', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <span class="p-kgt__l"><?php esc_html_e('الوجبة', 'school-system'); ?></span>
                <span class="p-kgt__v"><?php echo esc_html(SCH_KG::MEALS[(string) $day->meal] ?? '—'); ?></span>
            </div>

            <div class="p-kgt">
                <span class="p-kgt__i"><?php echo sch_icon('clock', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <span class="p-kgt__l"><?php esc_html_e('النوم', 'school-system'); ?></span>
                <?php if ($day->nap_minutes) : ?>
                    <?php /* الرقم ووحدته كتلة واحدة: «٤٥» في سطر و«دقيقة» في آخر تبدو رقمين */ ?>
                    <span class="p-kgt__v p-amt">
                        <span class="p-nm"><?php echo esc_html(number_format_i18n((int) $day->nap_minutes)); ?></span>
                        <i><?php esc_html_e('دقيقة', 'school-system'); ?></i>
                    </span>
                <?php else : ?>
                    <span class="p-kgt__v">—</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- المزاج: الأربعة كلها معروضة والمسجَّل وحده مضاء — الوجه يُقرأ قبل الكلمة -->
        <div class="p-card">
            <b class="p-kg__t"><?php esc_html_e('المزاج', 'school-system'); ?></b>
            <?php
            /**
             * الوجوه الأربعة صورةٌ واحدة لا أربع كلمات.
             *
             * قارئ الشاشة كان ينطق «سعيد هادئ متعب منزعج» متتابعةً بلا ما يقول
             * أيّها المسجَّل — فالإضاءة لونٌ وحلقة، وكلاهما لا يُنطق. و`role="img"`
             * يجعل المجموعة عنصرًا واحدًا اسمُه المزاج المسجَّل وحده، والثلاثة
             * الباقية سياقٌ بصريّ كأعمدة الرسم الفارغة.
             */
            $sch_mood_now = SCH_KG::MOODS[(string) $day->mood] ?? '';
            ?>
            <div class="p-moods" role="img"
                 aria-label="<?php echo esc_attr($sch_mood_now !== '' ? $sch_mood_now : __('لم يُسجَّل المزاج', 'school-system')); ?>">
                <?php foreach (SCH_KG::MOODS as $sch_mood => $sch_mood_label) : ?>
                    <span class="p-mood<?php echo esc_attr((string) $day->mood === $sch_mood ? ' is-on' : ''); ?>">
                        <span class="p-face p-face--<?php echo esc_attr($sch_mood); ?>"><i></i></span>
                        <span class="p-mood__n"><?php echo esc_html($sch_mood_label); ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($day->activities) : ?>
            <div class="p-card">
                <b class="p-kg__t"><?php esc_html_e('نشاط اليوم', 'school-system'); ?></b>
                <p class="p-kg__p"><?php echo esc_html((string) $day->activities); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($day->note) : ?>
            <?php /* ما كتبته المعلمة لوليّ الأمر: بلون التنبيه لأنه غالبًا يطلب فعلًا */ ?>
            <div class="p-kgn">
                <span class="p-kgn__i"><?php echo sch_icon('chat', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <span class="p-kgn__t">
                    <b><?php esc_html_e('ملاحظة المعلّمة', 'school-system'); ?></b>
                    <span><?php echo esc_html((string) $day->note); ?></span>
                </span>
            </div>
        <?php endif; ?>

    <?php endforeach; ?>
<?php endif; ?>
