<?php
/**
 * من يحقّ له استلام أبنائي.
 *
 * «خالته اليوم فقط» حالةٌ تتكرّر كل أسبوع في كل مدرسة، وتُحلّ اليوم
 * باتصال هاتفي والحارس يقرّر بعينه. هنا يفوّض وليّ الأمر بنفسه، فيصير
 * التفويض سجلًّا: من أذن، ولمن، وإلى متى — ويراه الحارس عند البوابة.
 *
 * **والقائمة بالأشخاص لا بالأبناء.** الطبقة تُجيب «من يستلم هذا الطفل؟»
 * لأن الحارس يسأل هكذا؛ أمّا الأب فيسأل «من المخوَّلون عندي؟» — والعرض
 * بالأبناء يكرّر الأب والأم في كل بطاقة، فتُقرأ القائمة أطول ممّا هي.
 * فتُقلَب هنا: صفٌّ لكل شخص، ونطاقه مكتوب فيه.
 *
 * وقيدان مبنيّان لا اختياريّان:
 * 1. **لا يفوّض إلا في طفلٍ يحقّ له هو استلامه** — وإلا صار التفويض
 *    بابًا خلفيًّا على `can_pickup` كلّه، يفوّض به من لا يملك.
 * 2. **أقصى مدّة أسبوع** — التفويض المفتوح يتحوّل صلاحيةً دائمة بلا
 *    قرار، ومن يحتاج الدوام يُضاف وليَّ أمر بصلاحية الاستلام.
 * وكلاهما مُنفَّذ في `SCH_Custody` لا هنا: الحارس في الطبقة لا في الشاشة.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_kids = SCH_Students::children_of(get_current_user_id());
$sch_err  = sanitize_key((string) ($_GET['err'] ?? ''));

$sch_errors = [
    'no_person' => __('اكتب اسم من ستفوّضه.', 'school-system'),
    'no_until'  => __('حدّد وقت انتهاء التفويض.', 'school-system'),
    'past'      => __('وقت الانتهاء يجب أن يكون في المستقبل.', 'school-system'),
    'too_long'  => __('أقصى مدّة للتفويض أسبوع.', 'school-system'),
    'not_yours' => __('لا تملك صلاحية الاستلام لهذا الطالب، فلا تفوّض فيه.', 'school-system'),
];

/**
 * قلبُ الجدول: من الطفل ← مخوَّلوه، إلى الشخص ← الأبناء الذين يستلمهم.
 * والتفويض المؤقّت يبقى صفًّا مستقلًّا لأنه يخصّ طفلًا واحدًا بوقته.
 */
$sch_people = [];

foreach ($sch_kids as $sch_kid) {
    $sch_first = (string) ($sch_kid->first_name ?: $sch_kid->full_name);

    foreach (SCH_Custody::pickers((int) $sch_kid->id) as $sch_p) {
        $sch_key = (string) $sch_p['key'];

        if (!isset($sch_people[$sch_key])) {
            $sch_people[$sch_key] = $sch_p + ['kids' => []];
        }

        $sch_people[$sch_key]['kids'][] = $sch_first;
    }
}

// الدائم أولًا: هو الأصل، والمؤقّت استثناء يُقرأ بعده
uasort($sch_people, static function (array $a, array $b): int {
    return [$a['source'] === 'delegate', $a['name']] <=> [$b['source'] === 'delegate', $b['name']];
});

$sch_count = count($sch_kids);

/**
 * قيمتا الحقل الزمني تُبنيان بـ`format` لا بـ`wp_date`.
 *
 * `wp_date` للعرض على الإنسان: تترجم اسم الشهر واليوم. وقيمة
 * `datetime-local` يقرأها المتصفّح لا الأب — وشكلها `Y-m-d\TH:i`
 * بأرقام لاتينية حصرًا، فأيّ توطين فيها يجعل المتصفّح يهمل القيمة
 * ويفتح الحقل فارغًا بلا رسالة.
 */
$sch_now   = new DateTimeImmutable('now', wp_timezone());
$sch_start = $sch_now->modify('+6 hours')->format('Y-m-d\TH:i');
$sch_cap   = $sch_now->modify('+7 days')->format('Y-m-d\TH:i');
?>

<header class="p-sub__h">
    <a class="p-chip" href="<?php echo esc_url(SCH_App::url('account')); ?>"
       aria-label="<?php esc_attr_e('رجوع إلى حسابي', 'school-system'); ?>">
        <?php echo sch_icon('chev', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>
    <h1 class="p-h1"><?php esc_html_e('من يحقّ له الاستلام', 'school-system'); ?></h1>
</header>

<p class="p-sub"><?php esc_html_e('لن تُسلّم المدرسة أيًّا من أبنائك إلا لمن في هذه القائمة، بعد مطابقة الهوية.', 'school-system'); ?></p>

<?php if ($sch_err && isset($sch_errors[$sch_err])) : ?>
    <div class="p-flash p-flash--bad"><?php echo esc_html($sch_errors[$sch_err]); ?></div>
<?php endif; ?>

<?php $sch_i = -1; ?>
<?php foreach ($sch_people as $sch_p) :
    $sch_tone      = ++$sch_i % 6;
    $sch_kid_names = array_values(array_unique($sch_p['kids']));

    /* «الثلاثة» أقصر من «سامي ولمى وجود» وأدلّ: الأب يعرف أنهم ثلاثة.
       وما لا يشمل الجميع يُسمّى بأسمائه — الغموض هنا يفتح بابًا. */
    $sch_scope = count($sch_kid_names) === $sch_count && $sch_count > 1
        ? (match ($sch_count) {
            2       => __('الاثنان', 'school-system'),
            3       => __('الثلاثة', 'school-system'),
            4       => __('الأربعة', 'school-system'),
            5       => __('الخمسة', 'school-system'),
            default => __('جميع الأبناء', 'school-system'),
        })
        : (count($sch_kid_names) === 1
            ? $sch_kid_names[0]
            /* «سامي ولمى» لا «سامي، لمى»: الواو تصل في العربية، والفاصلة تعدّ */
            : implode('، ', array_slice($sch_kid_names, 0, -1)) . ' و' . end($sch_kid_names));

    $sch_temp_row = $sch_p['source'] === 'delegate';

    /* «اليوم فقط حتى ٣:٠٠» أقصر من تاريخ كامل وأدلّ على ما يعني الأب —
       وأكثر التفويضات ينتهي في يومه، فالتاريخ فيه تكرارٌ لِـ«اليوم». */
    $sch_when = '';

    if ($sch_temp_row) {
        $sch_until = strtotime((string) $sch_p['until']) ?: time();
        $sch_when  = wp_date('Y-m-d', $sch_until) === wp_date('Y-m-d')
            ? sprintf(
                /* translators: %s: وقت انتهاء التفويض */
                __('اليوم فقط حتى %s', 'school-system'),
                wp_date('g:i a', $sch_until)
            )
            : sprintf(
                /* translators: %s: تاريخ ووقت انتهاء التفويض */
                __('حتى %s', 'school-system'),
                wp_date('j M · g:i a', $sch_until)
            );
    }
    ?>

    <article class="p-pk<?php echo $sch_temp_row ? ' p-pk--temp' : ''; ?> p-pk--t<?php echo esc_attr((string) $sch_tone); ?>">
        <span class="p-pk__av">
            <?php echo sch_avatar_svg(mb_substr((string) $sch_p['name'], 0, 1), 44); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </span>

        <span class="p-pk__t">
            <b><?php echo esc_html($sch_p['name']); ?></b>
            <em>
                <?php echo esc_html(sprintf(
                    /* translators: 1: صلة القرابة، 2: نطاق الأبناء، 3: مدّة التفويض إن كان مؤقّتًا */
                    __('%1$s · %2$s%3$s', 'school-system'),
                    $sch_p['relation'],
                    $sch_scope,
                    $sch_when !== '' ? ' · ' . $sch_when : ''
                )); ?>
            </em>
        </span>

        <?php if ($sch_temp_row) : ?>
            <span class="p-tag p-tag--warn"><?php esc_html_e('مؤقّت', 'school-system'); ?></span>

            <?php /* لا يُلغى إلا تفويض أنشأتَه أنت — والطبقة تتحقّق ثانيةً */ ?>
            <form method="post" class="p-pk__x">
                <?php wp_nonce_field('sch_app_delegate', '_sch_nonce'); ?>
                <input type="hidden" name="sch_app_action" value="delegate_revoke">
                <input type="hidden" name="delegation_id" value="<?php echo esc_attr(substr((string) $sch_p['key'], 2)); ?>">
                <button type="submit" class="p-pk__xb" aria-label="<?php esc_attr_e('إلغاء التفويض', 'school-system'); ?>">
                    <?php echo sch_icon('x', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </button>
            </form>
        <?php else : ?>
            <span class="p-tag p-tag--ok"><?php esc_html_e('دائم', 'school-system'); ?></span>
        <?php endif; ?>
    </article>

<?php endforeach; ?>

<?php
/* لا يُفوَّض إلا في ابنٍ يحقّ لك أنت استلامه — والقائمة تُبنى هنا حتى
   لا يعرض النموذج ابنًا سيرفضه الخادم بعد ملء الحقول. */
$sch_own = [];

foreach ($sch_kids as $sch_kid) {
    if (SCH_Custody::may_pick_up((int) $sch_kid->id, 'g:' . get_current_user_id())) {
        $sch_own[] = $sch_kid;
    }
}
?>

<?php if ($sch_own !== []) : ?>
    <details class="p-pk__add">
        <summary>
            <?php echo sch_icon('plus', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php esc_html_e('إضافة تفويض مؤقّت', 'school-system'); ?>
        </summary>

        <form method="post" class="p-card p-form">
            <?php wp_nonce_field('sch_app_delegate', '_sch_nonce'); ?>
            <input type="hidden" name="sch_app_action" value="delegate_add">

            <div class="p-field">
                <label for="p-d-kid"><?php esc_html_e('عن أيّ ابن', 'school-system'); ?></label>
                <select class="p-in" id="p-d-kid" name="student_id" required>
                    <?php foreach ($sch_own as $sch_kid) : ?>
                        <option value="<?php echo esc_attr((string) $sch_kid->id); ?>">
                            <?php echo esc_html(trim(
                                ($sch_kid->first_name ?: $sch_kid->full_name)
                                . ' — ' . (string) $sch_kid->grade_level
                                . ((string) $sch_kid->section !== '' ? ' / ' . (string) $sch_kid->section : '')
                            )); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="p-field">
                <label for="p-d-name"><?php esc_html_e('الاسم كما في الهوية', 'school-system'); ?></label>
                <input class="p-in" id="p-d-name" type="text" name="person_name" required
                       placeholder="<?php esc_attr_e('نورة سعد المطيري', 'school-system'); ?>">
            </div>

            <div class="p-form__row">
                <div class="p-field p-field--half">
                    <label for="p-d-rel"><?php esc_html_e('صلة القرابة', 'school-system'); ?></label>
                    <input class="p-in" id="p-d-rel" type="text" name="relation"
                           placeholder="<?php esc_attr_e('الخالة', 'school-system'); ?>">
                </div>
                <div class="p-field p-field--half">
                    <label for="p-d-id"><?php esc_html_e('رقم الهوية', 'school-system'); ?></label>
                    <input class="p-in" id="p-d-id" type="text" name="id_number" inputmode="numeric">
                </div>
            </div>

            <div class="p-field">
                <label for="p-d-until"><?php esc_html_e('حتى متى', 'school-system'); ?></label>
                <input class="p-in" id="p-d-until" type="datetime-local" name="valid_until" required
                       value="<?php echo esc_attr($sch_start); ?>"
                       max="<?php echo esc_attr($sch_cap); ?>">
            </div>

            <p class="p-pk__note">
                <?php esc_html_e('الحارس يرى الاسم ووقت الانتهاء، ويطابق الهوية قبل التسليم. وأقصى مدّة أسبوع.', 'school-system'); ?>
            </p>

            <button type="submit" class="p-btn p-btn--brand p-tap"><?php esc_html_e('تفويض', 'school-system'); ?></button>
        </form>
    </details>
<?php endif; ?>

<?php if ($sch_kids === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('user', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا أبناء مرتبطون بحسابك', 'school-system'); ?></b>
        <p><?php esc_html_e('راجع إدارة المدرسة لربط أبنائك بحسابك.', 'school-system'); ?></p>
    </div>
<?php endif; ?>
