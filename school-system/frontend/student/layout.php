<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$sch_is_login = ($sch_view === 'login');
$sch_student  = $sch_data['student'] ?? null;
$sch_mode     = $sch_student ? SCH_Student::mode($sch_student) : 'mid';
$sch_words    = SCH_Student::mode_labels($sch_mode);
$sch_current  = (string) ($sch_data['section'] ?? '');
?>
<!DOCTYPE html>
<?php /* شاشة الطالب داكنة بنظام رموزها الخاص (--s-*)؛ نثبّت سمة مكوّنات
   shared-ui على الفاتح لتبقى بطاقاتها كما صُمّمت تمامًا (بلا انقلاب حسب الجهاز). */ ?>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0B1220">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?php esc_attr_e('تعلّم', 'school-system'); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(SCH_URL . 'assets/icon-student-192.png'); ?>">
    <link rel="manifest" href="<?php echo esc_url(SCH_Student::url('manifest.webmanifest')); ?>">

    <title><?php esc_html_e('تعلّم', 'school-system'); ?></title>
    <?php $sch_fav = SCH_Brand::favicon(); ?>
    <?php if ($sch_fav !== '') : ?>
        <link rel="icon" href="<?php echo esc_url($sch_fav); ?>">
        <link rel="apple-touch-icon" href="<?php echo esc_url($sch_fav); ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/shared-ui.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/student.css')); ?>">
</head>
<body class="schs schs--<?php echo esc_attr($sch_mode); ?><?php echo $sch_is_login ? ' schs--auth' : ''; ?><?php echo $sch_view === 'pass' ? ' schs--pass' : ''; ?>"
      data-api="<?php echo esc_attr(rest_url(SCH_API_NS . '/')); ?>"
      data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
<a class="sch-skip" href="#sch-main-content"><?php esc_html_e('تخطَّ إلى المحتوى', 'school-system'); ?></a>


<?php if ($sch_view === 'pass') : ?>

    <?php require $file; ?>

<?php elseif ($sch_is_login) : ?>

    <?php require $file; ?>

<?php else : ?>

    <header class="schs-top">
        <a class="schs-me" href="<?php echo esc_url(SCH_Student::url('me')); ?>">
            <span class="schs-me__pic">
                <?php echo sch_avatar_svg(mb_substr((string) $sch_student->full_name, 0, 1), 36); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </span>
            <span class="schs-me__txt">
                <b><?php echo esc_html($sch_student->first_name ?: $sch_student->full_name); ?></b>
                <span><?php echo esc_html(trim((string) $sch_student->grade_level . ' / ' . (string) $sch_student->section)); ?></span>
            </span>
        </a>
        <a class="schs-qrbtn" href="<?php echo esc_url(SCH_Student::url('pass')); ?>"
           aria-label="<?php esc_attr_e('تصريحي', 'school-system'); ?>">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><path d="M14 14h2v2h-2zM18 14h2.5M14 18h2M18 18h2.5v2.5"/></svg>
        </a>
    </header>

    <main class="schs-main" id="sch-main-content">
        <?php
        if (isset($_GET['ok'])) {
            echo '<div class="schs-flash schs-flash--ok">' . esc_html__('أحسنت — وصلت إجابتك.', 'school-system') . '</div>';
        }
        if (isset($_GET['err'])) {
            // رمزٌ يُترجَم لا نصٌّ خام: ما في الرابط يكتبه من يصنع الرابط.
            echo '<div class="schs-flash schs-flash--bad">' . esc_html(match (sanitize_key((string) $_GET['err'])) {
                'empty_answer' => __('اكتب إجابتك أو أرفق رسمك.', 'school-system'),
                'too_big'      => __('الملف أكبر من خمسة ميجابايت — اختر صورة أصغر.', 'school-system'),
                'bad_type'     => __('يُقبل JPG أو PNG أو PDF فقط.', 'school-system'),
                'closed'       => __('أُغلق هذا الواجب.', 'school-system'),
                'nonce'        => __('انتهت صلاحية الصفحة — حدّثها ثم أعد الإرسال.', 'school-system'),
                default        => __('تعذّر الإرسال — أعد المحاولة.', 'school-system'),
            }) . '</div>';
        }
        require $file;
        ?>
    </main>

    <nav class="schs-tabs">
        <?php
        $sch_tabs = [
            ''         => [$sch_words['greet'],   'home'],
            'homework' => [$sch_words['hw'],      'list'],
            'library'  => [$sch_words['library'], 'book'],
            'play'     => [$sch_words['play'],    'award'],
        ];
        foreach ($sch_tabs as $slug => $tab) : ?>
            <a class="schs-tab<?php echo $slug === $sch_current ? ' is-on' : ''; ?>"
               href="<?php echo esc_url(SCH_Student::url($slug)); ?>">
                <?php echo sch_icon($tab[1], 21); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <span><?php echo esc_html($tab[0]); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

<?php endif; ?>

<?php
/*
 * `student.js` **خارج** الفروع.
 *
 * كان محمَّلًا في فرع `else` وحده، وشاشة التصريح (`pass`) لها فرعها الخاصّ
 * أوّلَ القالب — فالرمز المتجدّد يُرسَم مرة واحدة ويتجمّد: عدّاده لا يعمل،
 * ولا يُجدَّد بعد ثلاثين ثانية، فيصير بعد ٩٠ ثانية رمزًا ميتًا يُرفض عند
 * البوابة بلا أن يعرف الطالب لماذا. وهي الشاشة الوحيدة التي **تحتاجه**.
 */
?>
<?php if (!$sch_is_login) : ?>
    <script src="<?php echo esc_url(sch_asset('assets/student.js')); ?>" defer></script>
<?php endif; ?>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    // بلا `untrailingslashit` يصير النطاق `/student/sw.js/` — أي لا صفحة واحدة.
    navigator.serviceWorker.register(<?php echo wp_json_encode(untrailingslashit(SCH_Student::url('sw.js'))); ?>, { scope: <?php echo wp_json_encode(SCH_Student::url()); ?> });
  });
}
</script>

<?php SCH_Push::boot(); // الإشعارات الفورية — بعد تسجيل عامل الخدمة ?>
<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
</body>
</html>
