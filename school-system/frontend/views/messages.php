<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sent    = SCH_Comms::sent(30);
$classes = SCH_Classes::list();
?>
<h1 class="sch-title"><?php esc_html_e('الرسائل', 'school-system'); ?></h1>

<div class="sch-card">
    <h2><?php esc_html_e('رسالة جديدة', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('تصل كإشعار داخل التطبيق لكل من تختاره.', 'school-system'); ?></p>

    <form method="post">
        <?php wp_nonce_field('sch_send_message', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="send_message">

        <div class="sch-grid">
            <div class="sch-field">
                <label for="m-title"><?php esc_html_e('العنوان', 'school-system'); ?></label>
                <input id="m-title" type="text" name="title" required>
            </div>
            <div class="sch-field">
                <label for="m-audience"><?php esc_html_e('المستهدفون', 'school-system'); ?></label>
                <select id="m-audience" name="audience">
                    <?php foreach (SCH_Comms::AUDIENCES as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sch-field">
                <label for="m-class"><?php esc_html_e('الشعبة (عند اختيار شعبة)', 'school-system'); ?></label>
                <select id="m-class" name="audience_id">
                    <option value=""><?php esc_html_e('—', 'school-system'); ?></option>
                    <?php foreach ($classes as $c) : ?>
                        <option value="<?php echo esc_attr((string) $c->id); ?>"><?php echo esc_html(SCH_Classes::label($c)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="sch-field">
            <label for="m-body"><?php esc_html_e('نص الرسالة', 'school-system'); ?></label>
            <textarea id="m-body" name="body" rows="4" required></textarea>
        </div>

        <button class="sch-btn"><?php esc_html_e('إرسال', 'school-system'); ?></button>
    </form>
</div>

<div class="sch-card">
    <h2><?php esc_html_e('الرسائل المرسلة', 'school-system'); ?></h2>
    <?php if ($sent === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لم تُرسل رسائل بعد', 'school-system'); ?></strong></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('العنوان', 'school-system'); ?></th>
                    <th><?php esc_html_e('المستهدفون', 'school-system'); ?></th>
                    <th><?php esc_html_e('المرسل', 'school-system'); ?></th>
                    <th><?php esc_html_e('الوقت', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($sent as $m) : ?>
                    <tr>
                        <td class="sch-name"><?php echo esc_html($m->title); ?></td>
                        <td><?php echo esc_html(SCH_Comms::AUDIENCES[$m->audience] ?? $m->audience); ?></td>
                        <td><?php echo esc_html($m->display_name ?: '—'); ?></td>
                        <td dir="ltr"><?php echo esc_html($m->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
