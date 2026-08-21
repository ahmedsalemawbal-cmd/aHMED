<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/**
 * النسخ الاحتياطي والاستعادة.
 *
 * الشاشة تجيب سؤالين لا واحدًا: **هل بياناتي محفوظة؟** و**كيف أستعيدها؟**
 * والأوّل يُجاب بنظرةٍ على أعلى الشاشة لا بقراءة قائمة — فمن يفتحها قلقًا
 * يريد الطمأنينة في ثانية، ومن يفتحها بعد كارثة يريد الزرّ.
 */

$sch_zip    = class_exists('ZipArchive');
$sch_list   = $sch_zip ? SCH_Backup::all() : [];
$sch_last   = $sch_list[0] ?? null;
$sch_age    = $sch_last !== null
    ? (int) floor((strtotime(current_time('mysql')) - strtotime($sch_last['at'])) / DAY_IN_SECONDS)
    : -1;

/* أسباب النسخ — تُترجَم عند العرض، والمفاتيح ثابتة تُبحث ولا تتغيّر بالترجمة. */
$sch_why = [
    'manual'          => __('يدويّة', 'school-system'),
    'before_upgrade'  => __('قبل ترقية النظام', 'school-system'),
    'before_rollover' => __('قبل ترحيل السنة', 'school-system'),
    'before_restore'  => __('قبل استعادةٍ سابقة', 'school-system'),
];
?>
<h1 class="sch-title"><?php echo sch_icon('shield', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('النسخ الاحتياطي', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('كشف الطلاب وسجلّ الحضور والعهدة والفواتير — في ملفٍّ واحد يُحفَظ خارج الخادم.', 'school-system'); ?></p>

<?php if (!$sch_zip) : ?>
    <div class="sch-notice sch-notice--error">
        <strong><?php esc_html_e('الخادم لا يدعم ZipArchive', 'school-system'); ?></strong>
        <div class="sch-sub"><?php esc_html_e('النسخ الاحتياطي معطَّل حتى تُفعَّل إضافة zip في PHP — راجع مزوّد الاستضافة.', 'school-system'); ?></div>
    </div>
<?php else : ?>

<?php /* ═════ الطمأنينة أوّلًا: متى آخر نسخة، ولونها يقول الجواب ═════ */ ?>
<?php /* حالتان لا ثلاث: `--error` و`--ok` هما المعرَّفتان، ونسخةٌ مضى
         عليها أسبوع تُعامَل معاملة الغياب — لأن أسبوعًا من الحضور والرسوم
         ضاع فعلًا. ولا يُخترَع صنفٌ ثالث لما يقوله الموجود. */ ?>
<div class="sch-notice sch-notice--<?php echo esc_attr($sch_age < 0 || $sch_age > 7 ? 'error' : 'ok'); ?>">
    <strong>
        <?php if ($sch_age < 0) : ?>
            <?php esc_html_e('لا توجد نسخة احتياطية واحدة', 'school-system'); ?>
        <?php elseif ($sch_age === 0) : ?>
            <?php esc_html_e('آخر نسخة: اليوم', 'school-system'); ?>
        <?php else : ?>
            <?php echo esc_html(sprintf(
                /* translators: %s: عدد الأيام */
                _n('آخر نسخة: قبل يوم', 'آخر نسخة: قبل %s أيام', $sch_age, 'school-system'),
                number_format_i18n($sch_age)
            )); ?>
        <?php endif; ?>
    </strong>
    <div class="sch-sub">
        <?php echo esc_html($sch_age < 0
            ? __('كل ما في النظام على قاعدةٍ واحدة. خذ نسخةً الآن.', 'school-system')
            : __('نزّلها واحفظها خارج الخادم — نسخةٌ على الخادم نفسه تضيع معه.', 'school-system')); ?>
    </div>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('خذ نسخة الآن', 'school-system'); ?></h2>
    <p class="sch-sub">
        <?php esc_html_e('تشمل كل جداول النظام وملفّات الطلاب المحميّة — الصور والوصفات والتقارير. ويأخذ النظام نسخةً تلقائية قبل كل ترقية وقبل ترحيل السنة.', 'school-system'); ?>
    </p>

    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar sch-mt">
        <?php wp_nonce_field('sch_backup_now', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="backup_now">
        <button class="sch-btn"><?php esc_html_e('أنشئ نسخة', 'school-system'); ?></button>
    </form>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('النسخ المحفوظة', 'school-system'); ?></h2>

    <?php if ($sch_list === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا نسخ بعد', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('التاريخ', 'school-system'); ?></th>
                    <th><?php esc_html_e('السبب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصفوف', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحجم', 'school-system'); ?></th>
                    <th class="sch-col--lg"><?php esc_html_e('', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($sch_list as $sch_i => $sch_b) : ?>
                    <tr>
                        <td class="sch-name" dir="ltr"><?php echo esc_html(mysql2date('j F Y · H:i', $sch_b['at'])); ?></td>
                        <td><?php echo esc_html($sch_why[$sch_b['why']] ?? $sch_b['why']); ?></td>
                        <td class="sch-mono" dir="ltr"><?php echo esc_html(number_format_i18n($sch_b['rows'])); ?></td>
                        <td class="sch-mono" dir="ltr"><?php echo esc_html(size_format($sch_b['size'])); ?></td>
                        <td>
                            <span class="sch-head__acts">
                                <?php /* التنزيل عبر `SCH_Files` — المجلّد محميّ، ولا يُخدَم بايتٌ قبل فحص الصلاحية */ ?>
                                <a class="sch-btn sch-btn--quiet sch-btn--sm"
                                   href="<?php echo esc_url(SCH_Files::url('backup', $sch_i + 1)); ?>">
                                    <?php esc_html_e('تنزيل', 'school-system'); ?>
                                </a>

                                <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
                                    <?php wp_nonce_field('sch_backup_delete', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="backup_delete">
                                    <input type="hidden" name="file" value="<?php echo esc_attr($sch_b['file']); ?>">
                                    <button class="sch-btn sch-btn--quiet sch-btn--sm"
                                            data-confirm="<?php esc_attr_e('حذف هذه النسخة نهائيًّا؟', 'school-system'); ?>">
                                        <?php esc_html_e('حذف', 'school-system'); ?>
                                    </button>
                                </form>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($sch_list !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('استعادة نسخة', 'school-system'); ?></h2>

    <?php
    /*
     * الاستعادة تُقال كما هي: **تمحو كل شيء الآن** وتضع مكانه ما في النسخة.
     * ولا تُطلب بنقرةٍ واحدة — تُكتب `RESET` باليد، وهي القاعدة نفسها التي
     * تحمي إعادة توليد مفتاح الإشعارات منذ v9.4: الفعل الذي يمحو عمل شهورٍ
     * يجب أن يمرّ بلحظة وعيٍ لا بلحظة تسرّع.
     *
     * ويأخذ النظام نسخةً من الحاضر قبل أن يمحوه — فمن استعاد بالخطأ يعود.
     */
    ?>
    <p class="sch-sub">
        <?php esc_html_e('الاستعادة تمحو كل بيانات النظام الآن وتضع مكانها ما في النسخة. ويأخذ النظام نسخةً من الحاضر قبل ذلك تلقائيًّا، فإن استعدتَ بالخطأ تعود.', 'school-system'); ?>
    </p>

    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-mt">
        <?php wp_nonce_field('sch_backup_restore', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="backup_restore">

        <div class="sch-toolbar">
            <select name="file" required aria-label="<?php esc_attr_e('النسخة', 'school-system'); ?>">
                <?php foreach ($sch_list as $sch_b) : ?>
                    <option value="<?php echo esc_attr($sch_b['file']); ?>">
                        <?php echo esc_html(mysql2date('j F Y · H:i', $sch_b['at'])
                            . ' — ' . ($sch_why[$sch_b['why']] ?? $sch_b['why'])
                            . ' · ' . sprintf(
                                /* translators: %s: عدد الصفوف */
                                __('%s صفًّا', 'school-system'),
                                number_format_i18n($sch_b['rows'])
                            )); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="confirm" required dir="ltr" autocomplete="off"
                   placeholder="<?php esc_attr_e('اكتب RESET', 'school-system'); ?>"
                   aria-label="<?php esc_attr_e('اكتب RESET لتأكيد الاستعادة', 'school-system'); ?>">

            <button class="sch-btn sch-btn--danger"
                    data-confirm="<?php esc_attr_e('ستُمحى كل البيانات الحالية ويحلّ محلّها ما في النسخة. متأكّد؟', 'school-system'); ?>">
                <?php esc_html_e('استعادة', 'school-system'); ?>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php endif; ?>
