<?php
/**
 * من يحقّ له استلام أبنائي.
 *
 * «خالته اليوم فقط» حالةٌ تتكرّر كل أسبوع في كل مدرسة، وتُحلّ اليوم
 * باتصال هاتفي والحارس يقرّر بعينه. هنا يفوّض وليّ الأمر بنفسه، فيصير
 * التفويض سجلًّا: من أذن، ولمن، وإلى متى — ويراه الحارس عند البوابة.
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

$sch_errors = [
    'no_person'  => __('اكتب اسم من ستفوّضه.', 'school-system'),
    'no_until'   => __('حدّد وقت انتهاء التفويض.', 'school-system'),
    'past'       => __('وقت الانتهاء يجب أن يكون في المستقبل.', 'school-system'),
    'too_long'   => __('أقصى مدّة للتفويض أسبوع.', 'school-system'),
    'not_yours'  => __('لا تملك صلاحية الاستلام لهذا الطالب، فلا تفوّض فيه.', 'school-system'),
];
?>

<a class="p-back" href="<?php echo esc_url(SCH_App::url('account')); ?>">
    <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php esc_html_e('حسابي', 'school-system'); ?>
</a>

<h1 class="p-h1"><?php esc_html_e('من يحقّ له الاستلام', 'school-system'); ?></h1>
<p class="p-sub"><?php esc_html_e('لن تُسلّم المدرسة أيًّا من أبنائك لمن ليس في هذه القائمة، بعد مطابقة الهوية.', 'school-system'); ?></p>

<?php if ($sch_err && isset($sch_errors[$sch_err])) : ?>
    <div class="p-flash p-flash--bad"><?php echo esc_html($sch_errors[$sch_err]); ?></div>
<?php endif; ?>

<?php foreach ($sch_kids as $sch_kid) :
    $sch_id      = (int) $sch_kid->id;
    $sch_pickers = SCH_Custody::pickers($sch_id);
    $sch_mine    = SCH_Custody::may_pick_up($sch_id, 'g:' . get_current_user_id());
    ?>

    <section class="p-pk">
        <header class="p-pk__h">
            <span class="p-pk__pic">
                <?php if ($sch_kid->photo_file) : ?>
                    <img src="<?php echo esc_url(SCH_App::photo_url($sch_id)); ?>" alt="" width="38" height="38" loading="lazy">
                <?php else : ?>
                    <?php echo sch_avatar_svg(mb_substr((string) $sch_kid->full_name, 0, 1), 38); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <?php endif; ?>
            </span>
            <b><?php echo esc_html($sch_kid->first_name ?: $sch_kid->full_name); ?></b>
            <em><?php echo esc_html(trim((string) $sch_kid->grade_level . ' / ' . (string) $sch_kid->section, ' /')); ?></em>
        </header>

        <ul class="p-pk__list">
            <?php foreach ($sch_pickers as $sch_p) : ?>
                <li class="p-pk__row p-pk__row--<?php echo esc_attr($sch_p['source']); ?>">
                    <span class="p-pk__av"><?php echo sch_avatar_svg(mb_substr((string) $sch_p['name'], 0, 1), 34); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>

                    <?php /* «حتى متى» في سطر الصلة لا في شارة: الشارة تزاحم الاسم
                             على السطر نفسه، فينكسر اسم ثلاثيّ على ثلاثة أسطر. */ ?>
                    <span class="p-pk__t">
                        <b><?php echo esc_html($sch_p['name']); ?></b>
                        <em>
                            <?php echo esc_html($sch_p['source'] === 'guardian'
                                ? $sch_p['relation']
                                : sprintf(
                                    /* translators: 1: صلة القرابة، 2: تاريخ ووقت الانتهاء */
                                    __('%1$s · حتى %2$s', 'school-system'),
                                    $sch_p['relation'],
                                    wp_date('j F، g:i a', strtotime((string) $sch_p['until']))
                                )); ?>
                        </em>
                    </span>

                    <?php if ($sch_p['source'] === 'guardian') : ?>
                        <span class="p-tag p-tag--ok"><?php esc_html_e('دائم', 'school-system'); ?></span>
                    <?php else : ?>
                        <?php /* لا يُلغى إلا تفويض أنشأتَه أنت — والطبقة تتحقّق ثانيةً */ ?>
                        <form method="post" class="p-pk__x">
                            <?php wp_nonce_field('sch_app_delegate', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_app_action" value="delegate_revoke">
                            <input type="hidden" name="delegation_id" value="<?php echo esc_attr(substr((string) $sch_p['key'], 2)); ?>">
                            <button type="submit" class="p-pk__xb" aria-label="<?php esc_attr_e('إلغاء التفويض', 'school-system'); ?>">
                                <?php echo sch_icon('x', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($sch_mine) : ?>
            <details class="p-pk__add">
                <summary class="p-tap">
                    <?php echo sch_icon('plus', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php esc_html_e('تفويض مؤقّت', 'school-system'); ?>
                </summary>

                <form method="post" class="p-form">
                    <?php wp_nonce_field('sch_app_delegate', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_app_action" value="delegate_add">
                    <input type="hidden" name="student_id" value="<?php echo esc_attr((string) $sch_id); ?>">

                    <div class="p-field">
                        <label for="p-dg-<?php echo esc_attr((string) $sch_id); ?>"><?php esc_html_e('الاسم كما في الهوية', 'school-system'); ?></label>
                        <input class="p-in" id="p-dg-<?php echo esc_attr((string) $sch_id); ?>" type="text" name="person_name" required
                               placeholder="<?php esc_attr_e('نورة سعد المطيري', 'school-system'); ?>">
                    </div>

                    <div class="p-form__row">
                        <div class="p-field p-field--half">
                            <label for="p-dr-<?php echo esc_attr((string) $sch_id); ?>"><?php esc_html_e('صلة القرابة', 'school-system'); ?></label>
                            <input class="p-in" id="p-dr-<?php echo esc_attr((string) $sch_id); ?>" type="text" name="relation"
                                   placeholder="<?php esc_attr_e('الخالة', 'school-system'); ?>">
                        </div>
                        <div class="p-field p-field--half">
                            <label for="p-di-<?php echo esc_attr((string) $sch_id); ?>"><?php esc_html_e('رقم الهوية', 'school-system'); ?></label>
                            <input class="p-in" id="p-di-<?php echo esc_attr((string) $sch_id); ?>" type="text" name="id_number" inputmode="numeric">
                        </div>
                    </div>

                    <div class="p-field">
                        <label for="p-du-<?php echo esc_attr((string) $sch_id); ?>"><?php esc_html_e('حتى متى', 'school-system'); ?></label>
                        <input class="p-in" id="p-du-<?php echo esc_attr((string) $sch_id); ?>" type="datetime-local" name="valid_until" required
                               value="<?php echo esc_attr($sch_start); ?>"
                               max="<?php echo esc_attr($sch_cap); ?>">
                    </div>

                    <p class="p-pk__note">
                        <?php esc_html_e('الحارس يرى الاسم والصورة ووقت الانتهاء، ويطابق الهوية قبل التسليم.', 'school-system'); ?>
                    </p>

                    <button type="submit" class="p-btn p-tap"><?php esc_html_e('تفويض', 'school-system'); ?></button>
                </form>
            </details>
        <?php else : ?>
            <p class="p-pk__note"><?php esc_html_e('لا تملك صلاحية الاستلام لهذا الطالب، فلا يمكنك التفويض فيه. راجع إدارة المدرسة.', 'school-system'); ?></p>
        <?php endif; ?>
    </section>

<?php endforeach; ?>

<?php if (!$sch_kids) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('user', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا أبناء مرتبطون بحسابك', 'school-system'); ?></b>
        <p><?php esc_html_e('راجع إدارة المدرسة لربط أبنائك بحسابك.', 'school-system'); ?></p>
    </div>
<?php endif; ?>
