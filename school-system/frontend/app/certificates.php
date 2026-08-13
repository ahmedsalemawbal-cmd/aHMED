<?php
/** شهادات الابن — يراها الأب ويحفظها ويرسلها لأهله. */

declare(strict_types=1);
defined('ABSPATH') || exit;

$id    = (int) ($sch_data['id'] ?? 0);
$one   = isset($_GET['c']) ? absint($_GET['c']) : 0;
$items = SCH_Certificates::of_student($id);
?>

<a class="p-back" href="<?php echo esc_url(SCH_App::url('child', $id)); ?>">
    <?php echo sch_icon('chev', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <?php esc_html_e('رجوع', 'school-system'); ?>
</a>

<?php if ($one > 0) :
    $cert = SCH_Certificates::get($one);

    if ($cert && (int) $cert->student_id === $id) : ?>
        <h1 class="p-h1"><?php echo esc_html((string) $cert->title); ?></h1>
        <p class="p-sub"><?php echo esc_html((string) $cert->serial); ?></p>

        <div class="p-cert">
            <?php echo SCH_Certificates::svg($cert); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </div>

        <button type="button" class="p-btn p-tap" onclick="window.print()">
            <?php esc_html_e('حفظ أو طباعة', 'school-system'); ?>
        </button>
        <?php return;
    endif;
endif; ?>

<h1 class="p-h1"><?php esc_html_e('الشهادات', 'school-system'); ?></h1>

<?php if ($items === []) : ?>
    <div class="p-empty">
        <span class="p-empty__i"><?php echo sch_icon('award', 24); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <b><?php esc_html_e('لا شهادات بعد', 'school-system'); ?></b>
        <p><?php esc_html_e('تظهر هنا فور منح المدرسة أي شهادة لابنك.', 'school-system'); ?></p>
    </div>
<?php else : ?>
    <div class="p-certs">
        <?php foreach ($items as $c) : ?>
            <a class="p-certcard p-certcard--<?php echo esc_attr((string) $c->template); ?> p-tap"
               href="<?php echo esc_url(add_query_arg('c', (int) $c->id, SCH_App::url('certificates', $id))); ?>">
                <span class="p-certcard__i"><?php echo sch_icon('award', 20); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <span class="p-certcard__t">
                    <b><?php echo esc_html((string) $c->title); ?></b>
                    <span><?php echo esc_html(SCH_Certificates::TYPES[$c->type][0] ?? ''); ?></span>
                </span>
                <span class="p-certcard__d"><?php echo esc_html(wp_date('j M', strtotime((string) $c->issued_at))); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
