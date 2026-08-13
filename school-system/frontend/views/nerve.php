<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$invisible = SCH_Nerve::invisible_students();
$times     = SCH_Nerve::response_times();
$metrics   = SCH_Nerve::school_metrics();
?>
<h1 class="sch-title"><?php echo sch_icon('award', 22); ?><?php esc_html_e('المتابعة العميقة', 'school-system'); ?></h1>

<!-- الأرقام التي تُقال للمدرسة — كلها محسوبة لا مقدَّرة -->
<div class="sch-stats">
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($metrics['detected'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('حالة اكتشفها النظام', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($metrics['handled'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('عولجت', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($metrics['improved'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('تحسّنت بعد المتابعة', 'school-system'); ?></span>
    </div>
    <div class="sch-stat<?php echo $metrics['invisible'] > 0 ? ' sch-stat--warn' : ''; ?>">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($metrics['invisible'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('طالب بلا التفات', 'school-system'); ?></span>
    </div>
</div>

<!-- الطالب غير المرئي: كل الأنظمة ترصد المشاغب، وهذه ترصد المنسي -->
<div class="sch-card">
    <h2><?php esc_html_e('طلاب لم يُذكروا منذ ثلاثة أسابيع', 'school-system'); ?></h2>
    <p class="sch-sub">
        <?php esc_html_e('في كل فصل طفل لا يشاغب فيُعاقَب ولا يتفوق فيُكرَّم. هذه قائمة انتباه لا قائمة اتهام — لا تظهر للمعلم ولا لولي الأمر.', 'school-system'); ?>
    </p>

    <?php if ($invisible === []) : ?>
        <div class="sch-empty">
            <strong><?php esc_html_e('لا يوجد طالب بلا التفات', 'school-system'); ?></strong>
            <?php esc_html_e('كل طالب ذُكر مرة على الأقل خلال ثلاثة أسابيع.', 'school-system'); ?>
        </div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصف', 'school-system'); ?></th>
                    <th><?php esc_html_e('متوسط زملائه', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($invisible as $s) : ?>
                    <tr>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(SCH_Dashboard::url('students', (int) $s->id)); ?>">
                                <?php echo esc_html(SCH_Enrollment::full_name($s)); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html(trim((string) ($s->grade_level ?? '') . ' / ' . (string) ($s->section ?? ''))); ?></td>
                        <td><?php echo esc_html(number_format((float) $s->peer_average, 1)); ?></td>
                        <td class="sch-sub"><?php esc_html_e('صفر تفاعل', 'school-system'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- زمن الاستجابة: وسيط لا قائمة أفراد -->
<div class="sch-card">
    <h2><?php esc_html_e('زمن الاستجابة', 'school-system'); ?></h2>
    <p class="sch-sub">
        <?php esc_html_e('من ظهور الإشارة إلى أول تصرف. يُعرض كوسيط لا كقائمة أفراد — الهدف كشف الاختناق لا محاسبة شخص.', 'school-system'); ?>
    </p>

    <dl class="sch-dl">
        <div><dt><?php esc_html_e('الأخصائية', 'school-system'); ?></dt>
            <dd><?php echo esc_html(SCH_Nerve::humanize($times['specialist'])); ?></dd></div>
        <div><dt><?php esc_html_e('الصحة المدرسية', 'school-system'); ?></dt>
            <dd><?php echo esc_html(SCH_Nerve::humanize($times['clinic'])); ?></dd></div>
        <div><dt><?php esc_html_e('الإنذارات', 'school-system'); ?></dt>
            <dd><?php echo esc_html(SCH_Nerve::humanize($times['alerts'])); ?></dd></div>
    </dl>
</div>

<h2 class="sch-h2"><?php esc_html_e('المؤشرات', 'school-system'); ?></h2>
<?php require SCH_PATH . 'frontend/views/insights.php'; ?>
