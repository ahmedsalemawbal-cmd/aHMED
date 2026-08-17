<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$queue = SCH_Notes::queue('clinic');
?>
<h1 class="sch-title"><?php echo sch_icon('heart', 22); ?><?php esc_html_e('إحالات الصحة المدرسية', 'school-system'); ?></h1>
<p class="sch-sub"><?php esc_html_e('الإحالة ليست إشعارًا — لا يخرج شيء لولي الأمر حتى ترى الممرضة الطالب.', 'school-system'); ?></p>

<?php if ($queue === []) : ?>
    <div class="sch-card">
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد إحالات', 'school-system'); ?></strong></div>
    </div>
<?php else : ?>
    <?php foreach ($queue as $n) :
        $health  = current_user_can('sch_view_health') ? SCH_Enrollment::health((int) $n->student_id) : null;
        $minutes = (int) floor((time() - strtotime((string) $n->created_at)) / 60); ?>
        <div class="sch-alert<?php echo $minutes >= 10 ? ' sch-alert--critical' : ''; ?>">
            <div class="sch-alert__head">
                <strong><?php echo esc_html($n->full_name); ?></strong>
                <span class="sch-badge"><?php echo esc_html(sprintf(
                    /* translators: %d: عدد الدقائق */
                    _n('منذ %d دقيقة', 'منذ %d دقيقة', $minutes, 'school-system'),
                    $minutes
                )); ?></span>
            </div>

            <?php if ($health && ($health->allergies || $health->blood_type || $health->chronic)) : ?>
                <!-- أول ما يظهر للممرضة — هذه الأسطر قد تنقذ حياة -->
                <div class="sch-notice sch-notice--error">
                    <?php if ($health->allergies) : ?>
                        <strong><?php esc_html_e('حساسية:', 'school-system'); ?> <?php echo esc_html($health->allergies); ?></strong>
                    <?php endif; ?>
                    <div class="sch-sub">
                        <?php if ($health->blood_type) : ?>
                            <?php esc_html_e('فصيلة الدم:', 'school-system'); ?> <?php echo esc_html($health->blood_type); ?>
                        <?php endif; ?>
                        <?php if ($health->chronic) : ?>
                            · <?php echo esc_html($health->chronic); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <span class="sch-sub"><?php echo esc_html($n->body ?: ''); ?></span>
            <span class="sch-sub"><?php echo esc_html(sprintf(
                /* translators: %s: اسم المعلم */
                __('أحاله: %s', 'school-system'),
                (string) ($n->author_name ?: '—')
            )); ?></span>

            <div class="sch-toolbar sch-mt">
                <a class="sch-btn" href="<?php echo esc_url(add_query_arg([
                    'student_id' => (int) $n->student_id,
                    'note_id'    => (int) $n->id,
                ], SCH_Dashboard::url('clinic'))); ?>">
                    <?php esc_html_e('تسجيل الزيارة', 'school-system'); ?>
                </a>
            </div>

            <form method="post" class="sch-mt">
                <?php wp_nonce_field('sch_close_note', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="close_note">
                <input type="hidden" name="note_id" value="<?php echo esc_attr((string) $n->id); ?>">
                <div class="sch-toolbar">
                    <input type="text" name="resolution" required
                           placeholder="<?php esc_attr_e('النتيجة — بلا تشخيص ولا اسم مرض', 'school-system'); ?>">
                    <button class="sch-btn"><?php esc_html_e('إغلاق الإحالة', 'school-system'); ?></button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
