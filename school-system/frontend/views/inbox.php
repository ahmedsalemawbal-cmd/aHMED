<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

// صندوقان في شاشة واحدة: الملف كان مكررًا بكلمة مختلفة، ونسختان تتباعدان.
$route = isset($_GET['t']) && $_GET['t'] === 'clinic' ? 'clinic' : 'specialist';
$queue = SCH_Notes::queue($route);
$stale = 3;
?>
<h1 class="sch-title"><?php echo sch_icon('user', 22); ?><?php esc_html_e('صندوق المتابعة', 'school-system'); ?></h1>

<div class="sch-seg">
    <a class="<?php echo esc_attr($route === 'specialist' ? 'is-on' : ''); ?>"
       href="<?php echo esc_url(SCH_Dashboard::url('inbox')); ?>"><?php esc_html_e('الأخصائية', 'school-system'); ?></a>
    <a class="<?php echo esc_attr($route === 'clinic' ? 'is-on' : ''); ?>"
       href="<?php echo esc_url(add_query_arg('t', 'clinic', SCH_Dashboard::url('inbox'))); ?>"><?php esc_html_e('الصحة المدرسية', 'school-system'); ?></a>
</div>
<p class="sch-sub"><?php esc_html_e('ما رفعه المعلمون، الأقدم أولًا. قابل الطالب أولًا ثم قرّر: تعالج، أو تتواصل، أو الاثنان.', 'school-system'); ?></p>

<?php if ($queue === []) : ?>
    <div class="sch-card">
        <div class="sch-empty">
            <strong><?php esc_html_e('الصندوق فارغ', 'school-system'); ?></strong>
            <?php esc_html_e('لا توجد ملاحظات بانتظار المعالجة.', 'school-system'); ?>
        </div>
    </div>
<?php else : ?>
    <?php foreach ($queue as $n) :
        $age = (int) floor((time() - strtotime((string) $n->created_at)) / DAY_IN_SECONDS); ?>
        <div class="sch-alert<?php echo $age >= $stale ? ' sch-alert--high' : ''; ?>">
            <div class="sch-alert__head">
                <strong>
                    <a href="<?php echo esc_url(SCH_Dashboard::url('students', (int) $n->student_id)); ?>">
                        <?php echo esc_html($n->full_name); ?>
                    </a>
                </strong>
                <span class="sch-badge<?php echo $n->status === 'in_progress' ? ' sch-badge--muted' : ''; ?>">
                    <?php echo esc_html(SCH_Notes::STATUSES[$n->status] ?? ''); ?>
                </span>
            </div>

            <span class="sch-sub"><?php echo esc_html($n->body ?: ''); ?></span>
            <span class="sch-sub">
                <?php echo esc_html(sprintf(
                    /* translators: 1: اسم المعلم 2: الشعبة */
                    __('%1$s · %2$s', 'school-system'),
                    (string) ($n->author_name ?: '—'),
                    trim((string) ($n->grade_level ?? '') . ' / ' . (string) ($n->section ?? ''))
                )); ?>
                <span dir="ltr"><?php echo esc_html(substr((string) $n->created_at, 0, 16)); ?></span>
                <?php if ($age >= $stale) : ?>
                    <span class="sch-badge"><?php echo esc_html(sprintf(
                        /* translators: %d: عدد الأيام */
                        _n('مضى %d يوم', 'مضى %d أيام', $age, 'school-system'),
                        $age
                    )); ?></span>
                <?php endif; ?>
            </span>

            <?php if ($n->parent_response) : ?>
                <div class="sch-notice sch-mt">
                    <strong><?php echo esc_html(match ($n->parent_response) {
                        'knows'      => __('ولي الأمر يعرف السبب', 'school-system'),
                        'contact_me' => __('ولي الأمر يطلب التواصل', 'school-system'),
                        default      => __('ولي الأمر لا يعرف السبب', 'school-system'),
                    }); ?></strong>
                    <?php if ($n->parent_body) : ?>
                        <div class="sch-sub"><?php echo esc_html($n->parent_body); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="sch-toolbar sch-mt">
                <?php if ($n->status === 'open') : ?>
                    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>">
                        <?php wp_nonce_field('sch_take_note', '_sch_nonce'); ?>
                        <input type="hidden" name="sch_action" value="take_note">
                        <input type="hidden" name="note_id" value="<?php echo esc_attr((string) $n->id); ?>">
                        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('قابلتُ الطالب', 'school-system'); ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-mt">
                <?php wp_nonce_field('sch_contact_parent', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="contact_parent">
                <input type="hidden" name="note_id" value="<?php echo esc_attr((string) $n->id); ?>">
                <div class="sch-toolbar">
                    <input type="text" name="message" placeholder="<?php esc_attr_e('رسالة لولي الأمر — بصياغتك', 'school-system'); ?>">
                    <button class="sch-btn sch-btn--quiet"><?php esc_html_e('تواصل', 'school-system'); ?></button>
                </div>
            </form>

            <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-mt">
                <?php wp_nonce_field('sch_close_note', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="close_note">
                <input type="hidden" name="note_id" value="<?php echo esc_attr((string) $n->id); ?>">
                <div class="sch-toolbar">
                    <input type="text" name="resolution" required
                           placeholder="<?php esc_attr_e('ما فعلته — يُقاس أثره بعد أسبوعين', 'school-system'); ?>">
                    <button class="sch-btn"><?php esc_html_e('إغلاق', 'school-system'); ?></button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
