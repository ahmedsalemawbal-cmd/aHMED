<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$kpi      = SCH_Insights::overview();
$risk     = SCH_Insights::at_risk(30);
$by_class = SCH_Insights::class_attendance();
$trend    = SCH_Insights::collection_trend();
$peak     = max(array_map(static fn (object $t): float => (float) $t->total, $trend) ?: [1.0]);
?>

<div class="sch-stats">
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format($kpi['attendance_pct'], 1)); ?>%</span>
        <span class="sch-stat__label"><?php esc_html_e('حضور اليوم', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($kpi['absent_today'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('غياب اليوم', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format($kpi['collection_pct'], 1)); ?>%</span>
        <span class="sch-stat__label"><?php esc_html_e('نسبة التحصيل', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($kpi['riders'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('مشترك في النقل', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($kpi['open_work'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('أوامر صيانة مفتوحة', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(number_format_i18n($kpi['open_incidents'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('بلاغات سلامة مفتوحة', 'school-system'); ?></span>
    </div>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('طلاب يحتاجون متابعة', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('مبني على ثلاثة مؤشرات: الحضور، الأداء، السداد. القائمة أداة متابعة لا حكم على الطالب.', 'school-system'); ?></p>

    <?php if ($risk === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا يوجد طلاب ضمن مؤشرات المتابعة', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الصف', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحضور', 'school-system'); ?></th>
                    <th><?php esc_html_e('المعدل', 'school-system'); ?></th>
                    <th><?php esc_html_e('المؤشرات', 'school-system'); ?></th>
                    <th><?php esc_html_e('الأولوية', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($risk as $r) : ?>
                    <tr>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(SCH_Dashboard::url('students', $r->id)); ?>"><?php echo esc_html($r->full_name); ?></a>
                        </td>
                        <td><?php echo esc_html($r->class ?: '—'); ?></td>
                        <td><?php echo esc_html(number_format($r->attendance, 1)); ?>%</td>
                        <td><?php echo $r->avg_percent !== null ? esc_html(number_format($r->avg_percent, 1) . '%') : '—'; ?></td>
                        <td class="sch-sub"><?php echo esc_html(implode(' · ', $r->reasons)); ?></td>
                        <td>
                            <span class="sch-badge <?php echo $r->level === 'high' ? '' : 'sch-badge--muted'; ?>">
                                <?php echo esc_html($r->level === 'high' ? __('عاجل', 'school-system') : ($r->level === 'medium' ? __('متوسط', 'school-system') : __('منخفض', 'school-system'))); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($by_class !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('حضور الشعب — الأدنى أولًا', 'school-system'); ?></h2>
    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th><?php esc_html_e('الشعبة', 'school-system'); ?></th>
                <th><?php esc_html_e('أيام مرصودة', 'school-system'); ?></th>
                <th><?php esc_html_e('نسبة الحضور', 'school-system'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($by_class as $c) :
                $pct = (int) $c->marked > 0 ? round((int) $c->present / (int) $c->marked * 100, 1) : 0; ?>
                <tr>
                    <td class="sch-name"><?php echo esc_html(($c->grade_level ?? '') . ' / ' . ($c->section ?? '')); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) $c->marked)); ?></td>
                    <td>
                        <div class="sch-bar sch-bar--inline"><span style="width: <?php echo esc_attr((string) $pct); ?>%"></span></div>
                        <span class="sch-sub"><?php echo esc_html(number_format($pct, 1)); ?>%</span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($trend !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('التحصيل الشهري', 'school-system'); ?></h2>
    <div class="sch-spark">
        <?php foreach ($trend as $t) : ?>
            <div class="sch-spark__col">
                <span class="sch-spark__bar" style="height: <?php echo esc_attr((string) max(4, (int) round((float) $t->total / $peak * 100))); ?>%"></span>
                <span class="sch-spark__label" dir="ltr"><?php echo esc_html($t->period); ?></span>
                <span class="sch-spark__val"><?php echo esc_html(number_format((float) $t->total, 0)); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
