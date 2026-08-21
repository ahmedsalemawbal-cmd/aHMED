<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * نداء الانصراف — عند الحارس.
 *
 * الحلقة كانت مقطوعة عند طرفها الأخير: الأب يضغط «أنا عند البوابة» في
 * تطبيقه، و`SCH_Pickup::board()` تعرف اسم ابنه — **ولا لوحةَ في `/gate`
 * ولا رابطَ إليها ولا إشعار**. واللوحة الموجودة في الداشبورد
 * (`/dashboard/pickup`) لا يفتحها الحارس: هو يعمل على تطبيق البوابة ملء
 * الشاشة، وبوّابة الدخول تُرسله إليه لا إلى الداشبورد.
 *
 * وهذه اللوحة **قشرةٌ على المحرّك نفسه** لا محرّكٌ ثانٍ: `board()` هي التي
 * تُقرأ، والأفعال الثلاثة هي أفعال الداشبورد نفسها بنونساتها.
 */

$sch_calls = SCH_Pickup::board();
?>
<a class="schg-back" href="<?php echo esc_url(SCH_Gate::url()); ?>">
    <?php esc_html_e('‹ المسح', 'school-system'); ?>
</a>

<h1 class="schg-h1"><?php esc_html_e('نداء الانصراف', 'school-system'); ?></h1>
<p class="schg-sub">
    <?php esc_html_e('الآباء الواقفون عند البوابة الآن — بأقدمهم أولًا. أحضِر الطالب وسلّمه، والنظام يسجّل خروجه باسم من استلمه.', 'school-system'); ?>
</p>

<?php if (isset($_GET['err'])) : ?>
    <div class="schg-alert schg-alert--bad">
        <?php echo esc_html(match (sanitize_key((string) $_GET['err'])) {
            'nonce'         => __('انتهت صلاحية الصفحة — حدّثها ثم أعد المحاولة.', 'school-system'),
            'gone'          => __('هذا النداء أُغلق من قبل.', 'school-system'),
            'not_authorized'=> __('من ينتظره غير مخوَّل باستلامه — راجع الإدارة قبل تسليمه.', 'school-system'),
            'bad_transition'=> __('حالة الطالب لا تسمح بالتسليم الآن.', 'school-system'),
            default         => __('تعذّر تنفيذ العملية.', 'school-system'),
        }); ?>
    </div>
<?php endif; ?>

<?php if ($sch_calls === []) : ?>
    <div class="schg-empty">
        <strong><?php esc_html_e('لا نداءات الآن', 'school-system'); ?></strong>
        <?php esc_html_e('يظهر الاسم هنا لحظة ضغط وليّ الأمر على «أنا عند البوابة».', 'school-system'); ?>
    </div>
<?php else : ?>
    <div class="schg-calls">
        <?php foreach ($sch_calls as $sch_c) :
            // الانتظار بالدقائق: الأب الذي وقف عشر دقائق أولى بمن وصل الآن.
            $sch_wait = max(0, (int) floor(((int) current_time('timestamp') - strtotime((string) $sch_c->created_at)) / 60));
            ?>
            <article class="schg-call<?php echo $sch_wait >= 10 ? ' is-slow' : ''; ?><?php echo $sch_c->status === 'onway' ? ' is-onway' : ''; ?>">
                <span class="schg-call__no" dir="ltr"><?php echo esc_html((string) $sch_wait); ?><em><?php esc_html_e('د', 'school-system'); ?></em></span>

                <div class="schg-call__b">
                    <b><?php echo esc_html((string) $sch_c->full_name); ?></b>
                    <span><?php echo esc_html(trim((string) $sch_c->grade_level . ' / ' . (string) $sch_c->section)); ?></span>
                    <span class="schg-call__who">
                        <?php echo esc_html(sprintf(
                            /* translators: %s: اسم وليّ الأمر */
                            __('ينتظره: %s', 'school-system'),
                            (string) $sch_c->caller_name
                        )); ?>
                    </span>
                    <?php if ($sch_c->note) : ?>
                        <span class="schg-call__note"><?php echo esc_html((string) $sch_c->note); ?></span>
                    <?php endif; ?>
                </div>

                <div class="schg-call__acts">
                    <?php if ($sch_c->status === 'open') : ?>
                        <form method="post">
                            <?php wp_nonce_field('sch_gate_pickup', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_gate_action" value="pickup_ack">
                            <input type="hidden" name="call_id" value="<?php echo esc_attr((string) $sch_c->id); ?>">
                            <button class="schg-mini"><?php esc_html_e('في الطريق', 'school-system'); ?></button>
                        </form>
                    <?php else : ?>
                        <span class="schg-pill schg-pill--warn"><?php esc_html_e('في الطريق', 'school-system'); ?></span>
                    <?php endif; ?>

                    <?php /* التسليم يمرّ بآلة العهدة — فيُرفض إن لم يكن المستلم مخوَّلًا */ ?>
                    <form method="post">
                        <?php wp_nonce_field('sch_gate_pickup', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_gate_action" value="pickup_done">
                        <input type="hidden" name="call_id" value="<?php echo esc_attr((string) $sch_c->id); ?>">
                        <button class="schg-mini schg-mini--go"><?php esc_html_e('سُلّم', 'school-system'); ?></button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('sch_gate_pickup', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_gate_action" value="pickup_drop">
                        <input type="hidden" name="call_id" value="<?php echo esc_attr((string) $sch_c->id); ?>">
                        <button class="schg-mini schg-mini--x"
                                data-confirm="<?php esc_attr_e('إلغاء النداء؟ يصل وليّ الأمر إشعارٌ بذلك.', 'school-system'); ?>">
                            <?php esc_html_e('إلغاء', 'school-system'); ?>
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
/*
 * الشاشة تُحدّث نفسها كل عشرين ثانية: جهاز البوابة يبقى مفتوحًا عليها،
 * والحارس يحمل طفلًا بيدٍ فلا يُطالَب بضغطة تحديث.
 */
?>
<script>
setTimeout(function () { location.reload(); }, 20000);
</script>
