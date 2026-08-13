<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$s     = sch_settings();
$years = SCH_Years::all();
?>
<h1 class="sch-title"><?php esc_html_e('الإعدادات', 'school-system'); ?></h1>

<?php $sch_missing = SCH_Activator::missing_tables(); ?>
<?php if ($sch_missing !== []) : ?>
    <div class="sch-notice sch-notice--error">
        <strong><?php echo esc_html(sprintf(
            /* translators: %d: عدد الجداول */
            _n('جدول واحد لم يُنشأ في قاعدة البيانات', '%d جداول لم تُنشأ في قاعدة البيانات', count($sch_missing), 'school-system'),
            count($sch_missing)
        )); ?></strong>
        <p><?php esc_html_e('كل عملية على هذه الجداول ستفشل. حدّث الإضافة أو راجع صلاحيات قاعدة البيانات.', 'school-system'); ?></p>
        <code dir="ltr"><?php echo esc_html(implode(' · ', $sch_missing)); ?></code>
    </div>
<?php endif; ?>

<div class="sch-card">
    <h2><?php esc_html_e('السنوات الدراسية', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('كل شعبة وتسجيل مرتبط بسنة. السنة النشطة هي التي تعمل عليها الشاشات.', 'school-system'); ?></p>

    <?php if ($years === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا توجد سنة دراسية', 'school-system'); ?></strong>
            <?php esc_html_e('أنشئ السنة الحالية لتبدأ في إضافة الشعب والطلاب.', 'school-system'); ?>
        </div>
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
            <div class="sch-field">
                <label for="y-name"><?php esc_html_e('اسم السنة', 'school-system'); ?></label>
                <input id="y-name" type="text" name="name" placeholder="1447 / 1448" required>
            </div>
            <div class="sch-field">
                <label for="y-start"><?php esc_html_e('تاريخ البداية', 'school-system'); ?></label>
                <input id="y-start" type="date" name="start_date" required>
            </div>
            <div class="sch-field">
                <label for="y-end"><?php esc_html_e('تاريخ النهاية', 'school-system'); ?></label>
                <input id="y-end" type="date" name="end_date" required>
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('إضافة سنة', 'school-system'); ?></button>
    </form>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('بيانات المدرسة', 'school-system'); ?></h2>
    <form method="post">
        <?php wp_nonce_field('sch_save_settings', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="save_settings">
        <div class="sch-grid">
            <div class="sch-field">
                <label for="st-name"><?php esc_html_e('اسم المدرسة', 'school-system'); ?></label>
                <input id="st-name" type="text" name="school_name" value="<?php echo esc_attr((string) ($s['school_name'] ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="st-phone"><?php esc_html_e('جوال المدرسة', 'school-system'); ?></label>
                <input id="st-phone" type="tel" name="school_phone" dir="ltr" value="<?php echo esc_attr((string) ($s['school_phone'] ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="st-email"><?php esc_html_e('البريد الإلكتروني', 'school-system'); ?></label>
                <input id="st-email" type="email" name="school_email" dir="ltr" value="<?php echo esc_attr((string) ($s['school_email'] ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="st-moe"><?php esc_html_e('الرقم الوزاري', 'school-system'); ?></label>
                <input id="st-moe" type="text" name="moe_id" dir="ltr" value="<?php echo esc_attr((string) ($s['moe_id'] ?? '')); ?>">
            </div>
            <div class="sch-field">
                <label for="st-addr"><?php esc_html_e('العنوان', 'school-system'); ?></label>
                <input id="st-addr" type="text" name="address" value="<?php echo esc_attr((string) ($s['address'] ?? '')); ?>">
            </div>
        </div>
        <button class="sch-btn"><?php esc_html_e('حفظ', 'school-system'); ?></button>
    </form>
</div>
