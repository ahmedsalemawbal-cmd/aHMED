<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

// الإشعارات الفورية — تصل جوال ولي الأمر والتطبيق مغلق.
// ثلاث خطوات مرتّبة: مفتاح ← تفعيل من الجوال ← إشعار تجريبي يُثبت أنها تعمل.
$sch_ready   = SCH_Push::ready();
$sch_on      = (bool) sch_settings('notify_push', true);
$sch_devices = $sch_ready ? SCH_Push::device_count() : 0;
$sch_mine    = $sch_ready ? count(SCH_Push::subs_of(get_current_user_id())) : 0;

// الخطوة الحالية: ما لم يكتمل بعد.
$sch_step = !$sch_ready ? 1 : ($sch_devices === 0 ? 2 : 3);
?>
<h1 class="sch-title"><?php echo sch_icon('bell', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('الإشعارات الفورية', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('رسالة تصل جوال ولي الأمر والتطبيق مغلق — بلا فايربيز وبلا اشتراك شهري، والمحتوى مشفَّر لا يقرؤه وسيط.', 'school-system'); ?></p>

<div class="sch-set2">
    <div class="sch-set2__main">

        <?php /* الخطوة ١ — المفتاح. مرة واحدة في عمر النظام. */ ?>
        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('lock', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('١ · مفتاح المدرسة', 'school-system'); ?></h2>
                    <p><?php esc_html_e('يُولَّد مرة واحدة ولا يغادر الخادم. به يعرف المتصفح أن الإشعار من مدرستكم أنتم.', 'school-system'); ?></p>
                </div>
            </header>

            <?php if (!$sch_ready) : ?>
                <p class="sch-sub"><?php esc_html_e('لم يُولَّد بعد — فلا إشعار يخرج. اضغط الزر مرة واحدة.', 'school-system'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('sch_push_keys', '_sch_nonce'); ?>
                    <input type="hidden" name="sch_action" value="push_keys">
                    <button class="sch-btn sch-mt"><?php esc_html_e('توليد المفتاح', 'school-system'); ?></button>
                </form>
            <?php else : ?>
                <div class="sch-set__stat">
                    <div class="sch-set__row">
                        <span><?php esc_html_e('الحالة', 'school-system'); ?></span>
                        <b class="is-ok"><?php echo sch_icon('check', 14); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('جاهز', 'school-system'); ?></b>
                    </div>
                    <div class="sch-set__row">
                        <span class="sch-sub"><?php esc_html_e('المفتاح العام', 'school-system'); ?></span>
                        <code class="sch-code" dir="ltr"><?php echo esc_html(mb_substr(SCH_Push::public_key(), 0, 22) . '…'); ?></code>
                    </div>
                </div>

                <?php /* إعادة التوليد تُبطل كل جهاز مشترك — فتُخفى خلف كلمة تُكتب. */ ?>
                <details class="sch-mt">
                    <summary class="sch-sub"><?php esc_html_e('إعادة توليد المفتاح', 'school-system'); ?></summary>
                    <div class="sch-notice sch-notice--error sch-mt">
                        <strong><?php esc_html_e('هذا يُبطل كل الأجهزة المشتركة.', 'school-system'); ?></strong>
                        <p><?php esc_html_e('كل ولي أمر سيتوقف عن استقبال الإشعارات حتى يفتح التطبيق ويفعّلها من جديد — ولن يُنبَّه أحد. لا تفعلها إلا إن تسرّب المفتاح.', 'school-system'); ?></p>
                    </div>
                    <form method="post">
                        <?php wp_nonce_field('sch_push_keys', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="push_keys">
                        <label class="sch-field">
                            <span><?php esc_html_e('اكتب RESET للتأكيد', 'school-system'); ?></span>
                            <input type="text" name="confirm_reset" dir="ltr" autocomplete="off"
                                   aria-label="<?php esc_attr_e('تأكيد إعادة توليد المفتاح', 'school-system'); ?>">
                        </label>
                        <button class="sch-btn sch-btn--danger sch-mt"><?php esc_html_e('إعادة التوليد', 'school-system'); ?></button>
                    </form>
                </details>
            <?php endif; ?>
        </div>

        <?php /* الخطوة ٢ — الجهاز. لا يُفعَّل من هنا: الإذن يُمنح على الجوال نفسه. */ ?>
        <div class="sch-card sch-mt">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('bus', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('٢ · تفعيل الأجهزة', 'school-system'); ?></h2>
                    <p><?php esc_html_e('كل جهاز يُفعَّل من نفسه — لا يُفعَّل من هنا ولا من أي شاشة إدارة.', 'school-system'); ?></p>
                </div>
            </header>

            <p class="sch-sub"><?php esc_html_e('يفتح ولي الأمر التطبيق على جواله، ويضغط «تفعيل الإشعارات» في شاشة حسابه، فيسأله المتصفح ويأذن. هذا هو كل ما عليه فعله.', 'school-system'); ?></p>

            <div class="sch-set__stat sch-mt">
                <div class="sch-set__row">
                    <span><?php esc_html_e('أجهزة مشتركة الآن', 'school-system'); ?></span>
                    <b dir="ltr"><?php echo esc_html(number_format_i18n($sch_devices)); ?></b>
                </div>
                <div class="sch-set__row">
                    <span><?php esc_html_e('أجهزتك أنت', 'school-system'); ?></span>
                    <b dir="ltr"><?php echo esc_html(number_format_i18n($sch_mine)); ?></b>
                </div>
            </div>

            <?php if ($sch_ready) : ?>
                <?php /* الزر نفسه الذي يراه ولي الأمر — يعمل هنا أيضًا فيجرّبه المدير على جهازه. */ ?>
                <button type="button" class="sch-btn sch-btn--quiet sch-mt" data-sch-push>
                    <?php echo sch_icon('bell', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <span data-sch-push-label><?php esc_html_e('تفعيل الإشعارات على هذا الجهاز', 'school-system'); ?></span>
                </button>
            <?php endif; ?>
        </div>

        <?php /* الخطوة ٣ — الدليل. */ ?>
        <div class="sch-card sch-mt">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('check', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('٣ · إشعار تجريبي', 'school-system'); ?></h2>
                    <p><?php esc_html_e('يصلك أنت وحدك — الدليل الوحيد أن السلسلة كاملة تعمل قبل أن يُعتمد عليها.', 'school-system'); ?></p>
                </div>
            </header>
            <form method="post">
                <?php wp_nonce_field('sch_push_test', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="push_test">
                <button class="sch-btn"<?php disabled(!$sch_ready); ?>><?php esc_html_e('أرسل لي إشعارًا الآن', 'school-system'); ?></button>
            </form>
        </div>
    </div>

    <aside class="sch-set2__side">
        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('activity', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('حيث نحن الآن', 'school-system'); ?></h2>
                    <p><?php esc_html_e('ما بقي حتى تعمل الإشعارات لكل الأسر.', 'school-system'); ?></p>
                </div>
            </header>
            <ol class="sch-steps">
                <li class="sch-steps__i<?php echo esc_attr($sch_step > 1 ? ' is-done' : ' is-now'); ?>">
                    <span class="sch-steps__n"><?php echo esc_html($sch_step > 1 ? '✓' : '١'); ?></span>
                    <span><?php esc_html_e('توليد المفتاح', 'school-system'); ?></span>
                </li>
                <li class="sch-steps__i<?php echo esc_attr($sch_step > 2 ? ' is-done' : ($sch_step === 2 ? ' is-now' : '')); ?>">
                    <span class="sch-steps__n"><?php echo esc_html($sch_step > 2 ? '✓' : '٢'); ?></span>
                    <span><?php esc_html_e('أول جهاز يفعّلها', 'school-system'); ?></span>
                </li>
                <li class="sch-steps__i<?php echo esc_attr($sch_step === 3 ? ' is-now' : ''); ?>">
                    <span class="sch-steps__n"><?php esc_html_e('٣', 'school-system'); ?></span>
                    <span><?php esc_html_e('إشعار تجريبي يصل', 'school-system'); ?></span>
                </li>
            </ol>
        </div>

        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('cog', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('القناة', 'school-system'); ?></h2>
                    <p><?php esc_html_e('إيقافها لا يمسّ المفاتيح ولا الاشتراكات — يوقف الإرسال فقط.', 'school-system'); ?></p>
                </div>
            </header>
            <form method="post">
                <?php wp_nonce_field('sch_push_channel', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="push_channel">
                <label class="sch-check">
                    <input type="checkbox" name="notify_push" value="1"<?php checked($sch_on); ?>>
                    <?php esc_html_e('إرسال الإشعارات الفورية', 'school-system'); ?>
                </label>
                <p class="sch-sub"><?php esc_html_e('البريد يبقى مستقلًّا: من له جهاز وبريد يصله الاثنان — لا يُقامَر على وصول رسالة تخصّ طفلًا.', 'school-system'); ?></p>
                <button class="sch-btn sch-btn--quiet sch-mt"><?php esc_html_e('حفظ', 'school-system'); ?></button>
            </form>
        </div>

        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('shield', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('لماذا لا فايربيز', 'school-system'); ?></h2>
                </div>
            </header>
            <p class="sch-sub"><?php esc_html_e('المعيار المفتوح يعمل على أندرويد وسطح المكتب وعلى آيفون ١٦٫٤+ للتطبيق المثبَّت — ولا يربط ربح النظام بفاتورة تكبر مع نجاحه. والرسالة مشفَّرة من الخادم إلى الجهاز: خادم الدفع يرى صندوقًا مغلقًا لا اسم طفل.', 'school-system'); ?></p>
        </div>
    </aside>
</div>
