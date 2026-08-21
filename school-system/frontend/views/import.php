<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$errors = get_transient('sch_import_errors_' . get_current_user_id());
$errors = is_array($errors) ? $errors : [];
?>
<h1 class="sch-title"><?php esc_html_e('استيراد الطلاب', 'school-system'); ?></h1>

<div class="sch-card">
    <h2><?php esc_html_e('كيف يعمل', 'school-system'); ?></h2>
    <ol class="sch-steps">
        <li><?php esc_html_e('نزّل القالب وعبّئه في Excel، ثم احفظه بصيغة CSV UTF-8.', 'school-system'); ?></li>
        <li><?php esc_html_e('ارفعه بدون تأكيد أولًا — النظام يفحص كل سطر ويعرض الأخطاء.', 'school-system'); ?></li>
        <li><?php esc_html_e('بعد اختفاء الأخطاء، ارفعه مع تفعيل «تأكيد الاستيراد».', 'school-system'); ?></li>
    </ol>
    <p class="sch-sub"><?php esc_html_e('لا يوجد استيراد جزئي: إما ينجح الملف كاملًا أو يُرفض كاملًا.', 'school-system'); ?></p>
    <p><a class="sch-btn sch-btn--quiet" href="<?php echo esc_url(add_query_arg('sch_template', '1', SCH_Dashboard::url('import'))); ?>"><?php esc_html_e('تنزيل القالب', 'school-system'); ?></a></p>
</div>

<?php if ($errors !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('تقرير الأخطاء', 'school-system'); ?></h2>
    <ul class="sch-errors">
        <?php foreach ($errors as $e) : ?>
            <li><?php echo esc_html($e); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="sch-card">
    <h2><?php esc_html_e('رفع الملف', 'school-system'); ?></h2>
    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('sch_import_students', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="import_students">
        <div class="sch-field">
            <label for="csv"><?php esc_html_e('ملف CSV', 'school-system'); ?></label>
            <input id="csv" type="file" name="csv" accept=".csv,text/csv" required>
        </div>
        <label class="sch-check">
            <input type="checkbox" name="confirm" value="1">
            <?php esc_html_e('تأكيد الاستيراد (بدونها يتم الفحص فقط)', 'school-system'); ?>
        </label>
        <button class="sch-btn"><?php esc_html_e('رفع', 'school-system'); ?></button>
    </form>
</div>

<h2 class="sch-h2"><?php esc_html_e('الجسر — الاستيراد من نور', 'school-system'); ?></h2>
<p class="sch-sub">
    <?php esc_html_e('إضافة متصفح تقرأ الجدول المعروض أمامك في نور وترسله هنا. تقرأ ولا تكتب، وتعمل بضغطتك لا في الخلفية.', 'school-system'); ?>
</p>

<?php $sch_cred = SCH_Dashboard::pull_bridge_key(); ?>
<?php if ($sch_cred) : ?>
    <!-- يُعرض مرة واحدة: المفتاح مخزَّن مجزّأً ولا يمكن استرجاعه -->
    <div class="sch-keybox">
        <b><?php esc_html_e('مفتاح الربط — انسخه الآن', 'school-system'); ?></b>
        <p><?php esc_html_e('لن يظهر مرة أخرى. الصقه في الإضافة داخل المتصفح.', 'school-system'); ?></p>
        <code dir="ltr" id="sch-key"><?php echo esc_html((string) $sch_cred['key']); ?></code>
        <button type="button" class="sch-btn" id="sch-copy-key"><?php esc_html_e('نسخ', 'school-system'); ?></button>
    </div>

    <script>
    document.getElementById('sch-copy-key').addEventListener('click', function () {
      var el = document.getElementById('sch-key');
      navigator.clipboard.writeText(el.textContent.trim()).then(function () {
        document.getElementById('sch-copy-key').textContent = 'نُسخ ✓';
      });
    });
    </script>
<?php endif; ?>

<div class="sch-card">
    <?php $sch_keys = SCH_Bridge::list_keys(); ?>

    <?php if ($sch_keys === []) : ?>
        <p class="sch-sub"><?php esc_html_e('لا مفاتيح بعد.', 'school-system'); ?></p>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th><?php esc_html_e('الجهاز', 'school-system'); ?></th>
                    <th><?php esc_html_e('تنتهي في', 'school-system'); ?></th>
                    <th><?php esc_html_e('آخر استخدام', 'school-system'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($sch_keys as $sch_hash => $sch_meta) : ?>
                        <tr>
                            <td class="sch-name"><?php echo esc_html((string) $sch_meta['label']); ?></td>
                            <td><?php echo esc_html(substr((string) $sch_meta['expires'], 0, 10)); ?></td>
                            <td>
                                <?php echo esc_html($sch_meta['used']
                                    ? substr((string) $sch_meta['used'], 0, 16)
                                    : __('لم يُستخدم', 'school-system')); ?>
                            </td>
                            <td>
                                <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" onsubmit="return confirm('<?php esc_attr_e('إلغاء هذا المفتاح؟ سيتوقف الجهاز عن الإرسال فورًا.', 'school-system'); ?>');">
                                    <?php wp_nonce_field('sch_revoke_key', '_sch_nonce'); ?>
                                    <input type="hidden" name="sch_action" value="revoke_key">
                                    <input type="hidden" name="hash" value="<?php echo esc_attr((string) $sch_hash); ?>">
                                    <button class="sch-btn sch-btn--sm sch-btn--danger"><?php esc_html_e('إلغاء', 'school-system'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar">
        <?php wp_nonce_field('sch_issue_key', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="issue_key">
        <input type="text" name="label" maxlength="60"
               placeholder="<?php esc_attr_e('اسم الجهاز — جهاز الوكيل مثلًا', 'school-system'); ?>"
               aria-label="<?php esc_attr_e('اسم الجهاز', 'school-system'); ?>">
        <button class="sch-btn"><?php esc_html_e('إصدار مفتاح', 'school-system'); ?></button>
    </form>
</div>
