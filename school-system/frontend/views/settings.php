<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

// صفحة الإعدادات الرئيسية — بيانات المدرسة + لمحة حالة النظام.
// التنقّل بين أقسام الإعدادات يتم من الشريط الجانبي (يستولي عليه سياق الإعدادات).
$s       = sch_settings();
$missing = SCH_Activator::missing_tables();
$sch_cur = SCH_Years::current();
?>
<h1 class="sch-title"><?php echo sch_icon('cog', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('بيانات المدرسة', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('تظهر هذه البيانات على البطاقات والشهادات والتقارير المطبوعة، وفي رأس كل شاشة.', 'school-system'); ?></p>

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

<div class="sch-set2">
    <div class="sch-set2__main">
        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('cog', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('الملف التعريفي', 'school-system'); ?></h2>
                    <p><?php esc_html_e('اسم المدرسة ووسائل التواصل والرقم الوزاري.', 'school-system'); ?></p>
                </div>
            </header>
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
    </div>

    <aside class="sch-set2__side">
        <div class="sch-card">
            <header class="sch-set__head">
                <span class="sch-set__ic"><?php echo sch_icon('shield', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <div>
                    <h2><?php esc_html_e('حالة النظام', 'school-system'); ?></h2>
                    <p><?php esc_html_e('لمحة سريعة على سلامة التركيب.', 'school-system'); ?></p>
                </div>
            </header>
            <div class="sch-set__stat">
                <div class="sch-set__row">
                    <span><?php esc_html_e('إصدار النظام', 'school-system'); ?></span>
                    <b dir="ltr"><?php echo esc_html(SCH_VERSION); ?></b>
                </div>
                <div class="sch-set__row">
                    <span><?php esc_html_e('قاعدة البيانات', 'school-system'); ?></span>
                    <?php if ($missing === []) : ?>
                        <b class="is-ok"><?php echo sch_icon('check', 14); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e('سليمة', 'school-system'); ?></b>
                    <?php else : ?>
                        <b class="is-bad"><?php echo esc_html(sprintf(
                            /* translators: %d: عدد الجداول */
                            _n('جدول ناقص', '%d جداول ناقصة', count($missing), 'school-system'),
                            count($missing)
                        )); ?></b>
                    <?php endif; ?>
                </div>
                <div class="sch-set__row">
                    <span><?php esc_html_e('السنة النشطة', 'school-system'); ?></span>
                    <b><?php echo esc_html($sch_cur->name ?? '—'); ?></b>
                </div>
            </div>
        </div>
    </aside>
</div>
