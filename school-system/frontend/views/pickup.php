<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

// لوحة نداء الانصراف — الآباء الواقفون عند البوابة الآن، بأقدمهم أولًا.
// الشاشة تُحدَّث نفسها كل عشرين ثانية: المشرفة تتركها مفتوحة على جهاز البوابة
// ولا تُطالَب بتحديثها يدويًا وهي تحمل طفلًا بيد.
$sch_calls = SCH_Pickup::board();
?>
<h1 class="sch-title"><?php echo sch_icon('bell', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('نداء الانصراف', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('وليّ الأمر يضغط في تطبيقه وهو في سيارته، فيظهر اسم ابنه هنا. أحضريه وسلّميه، والنظام يسجّل خروجه باسم من استلمه.', 'school-system'); ?></p>

<?php if ($sch_calls === []) : ?>
    <div class="sch-card sch-empty">
        <?php echo sch_icon('check', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <strong><?php esc_html_e('لا نداءات الآن', 'school-system'); ?></strong>
        <p><?php esc_html_e('تظهر هنا لحظة ضغط أي وليّ أمر على «أنا عند البوابة».', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="sch-calls">
        <?php foreach ($sch_calls as $sch_c) :
            $sch_wait = max(0, (int) floor(((int) current_time('timestamp') - strtotime((string) $sch_c->created_at)) / 60));
            $sch_slow = $sch_wait >= 10;
            ?>
            <article class="sch-call<?php echo esc_attr($sch_slow ? ' is-slow' : ''); ?><?php echo esc_attr($sch_c->status === 'onway' ? ' is-onway' : ''); ?>">
                <span class="sch-call__no"><?php echo esc_html(number_format_i18n($sch_wait)); ?><em><?php esc_html_e('دقيقة', 'school-system'); ?></em></span>

                <div class="sch-call__b">
                    <b><?php echo esc_html((string) $sch_c->full_name); ?></b>
                    <span><?php echo esc_html(trim((string) $sch_c->grade_level . ' / ' . (string) $sch_c->section)); ?></span>
                    <span class="sch-call__who">
                        <?php echo esc_html(sprintf(
                            /* translators: %s: اسم وليّ الأمر */
                            __('ينتظره: %s', 'school-system'),
                            (string) $sch_c->caller_name
                        )); ?>
                    </span>
                    <?php if ($sch_c->note) : ?>
                        <span class="sch-call__note"><?php echo esc_html((string) $sch_c->note); ?></span>
                    <?php endif; ?>
                </div>

                <div class="sch-call__acts">
                    <?php if ($sch_c->status === 'open') : ?>
                        <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
                            <?php wp_nonce_field('sch_pickup_ack', '_sch_nonce'); ?>
                            <input type="hidden" name="sch_action" value="pickup_ack">
                            <input type="hidden" name="call_id" value="<?php echo esc_attr((string) $sch_c->id); ?>">
                            <button class="sch-btn sch-btn--quiet"><?php esc_html_e('أنا في الطريق', 'school-system'); ?></button>
                        </form>
                    <?php else : ?>
                        <span class="sch-chip sch-chip--warn"><?php esc_html_e('في الطريق', 'school-system'); ?></span>
                    <?php endif; ?>

                    <?php /* التسليم يمرّ بآلة العهدة — فيُرفض إن لم يكن المستلم مخوَّلًا */ ?>
                    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
                        <?php wp_nonce_field('sch_pickup_done', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="pickup_done">
                        <input type="hidden" name="call_id" value="<?php echo esc_attr((string) $sch_c->id); ?>">
                        <button class="sch-btn"><?php esc_html_e('سُلّم', 'school-system'); ?></button>
                    </form>

                    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
                        <?php wp_nonce_field('sch_pickup_drop', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="pickup_drop">
                        <input type="hidden" name="call_id" value="<?php echo esc_attr((string) $sch_c->id); ?>">
                        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إلغاء', 'school-system'); ?></button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
/* تحديث هادئ كل عشرين ثانية — ويتوقّف إن كانت الشاشة مخفيّة أو المستخدم
   يملأ نموذجًا، فلا يُسحب من تحت يده ما يكتب. */
(function () {
  var t = null;

  function tick() {
    if (document.hidden || document.activeElement.tagName === 'INPUT') { return; }
    window.location.reload();
  }

  t = window.setInterval(tick, 20000);
  window.addEventListener('pagehide', function () { window.clearInterval(t); });
}());
</script>
