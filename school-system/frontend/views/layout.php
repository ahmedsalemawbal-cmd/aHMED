<?php
/**
 * هيكل الداشبورد. مستند مستقل تمامًا — بلا قالب ثيم ولا شريط أدمن.
 * متاح: $sch_view, $sch_data, $file
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$sch_is_login = ($sch_view === 'login');
$sch_is_hub   = ($sch_view === 'hub');
$sch_user     = wp_get_current_user();
$sch_current  = (string) ($sch_data['section'] ?? '');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <?php /* منع وميض السمة: تُضبط قبل أول رسم. الافتراضي داكن — الهوية الأساسية. */ ?>
    <script>(function(){try{var t=localStorage.getItem('sch-theme');document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark');}catch(e){document.documentElement.setAttribute('data-theme','dark');}})();</script>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html(get_bloginfo('name')); ?> — <?php esc_html_e('نظام المدرسة', 'school-system'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/shared-ui.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/dashboard.css')); ?>">
</head>
<body class="sch-body<?php echo $sch_is_login || $sch_is_hub ? ' sch-body--auth' : ''; ?>">
<a class="sch-skip" href="#sch-main-content"><?php esc_html_e('تخطَّ إلى المحتوى', 'school-system'); ?></a>


<?php if ($sch_is_login || $sch_is_hub) : ?>

    <?php require $file; ?>

<?php else : ?>

    <?php
    $sch_groups  = SCH_Dashboard::groups();
    $sch_active  = SCH_Dashboard::group_of($sch_current);
    $sch_user_ob = wp_get_current_user();
    ?>

    <!-- التنقّل بالبلاطات العلوية: عشر مجموعات بدل عمود بخمسة وأربعين قسمًا،
         والشاشة تكسب عرض العمود كله. -->
    <header class="sch-top">
        <div class="sch-top__r">
            <a class="sch-brand" href="<?php echo esc_url(SCH_Dashboard::url()); ?>">
                <span class="sch-brand__mark"><?php echo esc_html(mb_substr(sch_settings('school_name', get_bloginfo('name')), 0, 2)); ?></span>
                <span class="sch-brand__t">
                    <b><?php echo esc_html(sch_settings('school_name', get_bloginfo('name'))); ?></b>
                    <span><?php esc_html_e('لوحة الإدارة', 'school-system'); ?></span>
                </span>
            </a>

            <span class="sch-top__sp"></span>

            <span class="sch-top__date"><?php echo esc_html(wp_date('l، j F Y')); ?></span>

            <?php $sch_open = SCH_Alerts::open_count(); ?>
            <a class="sch-top__ic<?php echo $sch_open > 0 ? ' has-dot' : ''; ?>"
               href="<?php echo esc_url(SCH_Dashboard::url('alerts')); ?>"
               aria-label="<?php esc_attr_e('الإنذارات', 'school-system'); ?>">
                <?php echo sch_icon('clock', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </a>

            <button type="button" class="sch-theme" aria-label="<?php esc_attr_e('تبديل الوضع الفاتح/الداكن', 'school-system'); ?>">
                <svg class="sch-theme__moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                <svg class="sch-theme__sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>

            <span class="sch-me">
                <i><?php echo esc_html(mb_substr($sch_user_ob->display_name, 0, 1)); ?></i>
                <b><?php echo esc_html($sch_user_ob->display_name); ?></b>
                <a href="<?php echo esc_url(SCH_Dashboard::url('logout')); ?>"
                   aria-label="<?php esc_attr_e('خروج', 'school-system'); ?>">
                    <?php echo sch_icon('route', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>
            </span>
        </div>

<!-- شريط واحد لا عشر بطاقات: المجموعات تنفصل بفراغ لا بحدود،
     والسطر الثاني حُذف (كان يكرّر «إدارة المدرسة» أربع مرات و«ERP» أربعًا). -->
        <nav class="sch-nav" aria-label="<?php esc_attr_e('المجموعات', 'school-system'); ?>">
            <?php
            $sch_prev_area = null;

            foreach ($sch_groups as $sch_key => $sch_g) :
                $sch_first = array_key_first($sch_g['sections']);
                $sch_area  = (string) $sch_g['area'];

                // فاصل رفيع عند تغيّر المجال — يجمع المتشابه بلا حدود حول كل عنصر
                if ($sch_prev_area !== null && $sch_area !== $sch_prev_area) : ?>
                    <span class="sch-nav__cut" aria-hidden="true"></span>
                <?php endif;
                $sch_prev_area = $sch_area; ?>

                <a class="sch-nav__i<?php echo $sch_key === $sch_active ? ' is-on' : ''; ?>"
                   href="<?php echo esc_url(SCH_Dashboard::url($sch_first)); ?>"
                   title="<?php echo esc_attr($sch_area); ?>">
                    <?php echo sch_icon($sch_g['icon'], 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <span><?php echo esc_html($sch_g['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (isset($sch_groups[$sch_active])) : ?>
            <!-- الشريحة الثانية أخف من الأولى: نص بخط تحته لا حبّات بحدود،
                 فلا تتنافس طبقتان متشابهتان على العين. -->
            <div class="sch-sub">
                <?php foreach ($sch_groups[$sch_active]['sections'] as $sch_slug => $sch_meta) : ?>
                    <a class="sch-sub__i<?php echo $sch_slug === $sch_current ? ' is-on' : ''; ?>"
                       href="<?php echo esc_url(SCH_Dashboard::url($sch_slug)); ?>">
                        <?php echo esc_html((string) $sch_meta[0]); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="sch-shell">

        <main class="sch-main">
            <div class="sch-content">
                <?php
                if (isset($_GET['err'])) {
                    $sch_msg = isset($_GET['msg'])
                        ? sanitize_text_field(wp_unslash((string) $_GET['msg']))
                        : __('تعذّر إتمام العملية. راجع الحقول وحاول مرة أخرى.', 'school-system');
                    echo '<div class="sch-notice sch-notice--error">' . esc_html($sch_msg) . '</div>';
                }
                if (isset($_GET['ok'])) {
                    echo '<div class="sch-notice sch-notice--ok">' . esc_html__('تم الحفظ.', 'school-system') . '</div>';
                }
                $sch_cred = isset($_GET['cred']) ? SCH_Dashboard::pull_credentials() : null;
                if ($sch_cred) : ?>
                    <div class="sch-notice sch-cred">
                        <strong><?php esc_html_e('بيانات دخول الحساب الجديد', 'school-system'); ?></strong>
                        <p class="sch-sub"><?php echo esc_html(sprintf(
                            /* translators: %s: اسم صاحب الحساب */
                            __('سلّمها الآن إلى %s. لن تظهر مرة أخرى، ولا يمكن استرجاعها لاحقًا.', 'school-system'),
                            $sch_cred['name']
                        )); ?></p>
                        <div class="sch-cred__row">
                            <span><?php esc_html_e('اسم المستخدم', 'school-system'); ?></span>
                            <code class="sch-code"><?php echo esc_html($sch_cred['login']); ?></code>
                        </div>
                        <div class="sch-cred__row">
                            <span><?php esc_html_e('كلمة المرور', 'school-system'); ?></span>
                            <code class="sch-code"><?php echo esc_html($sch_cred['password']); ?></code>
                        </div>
                    </div>
                <?php endif; ?>

                <?php require $file; ?>
            </div>
        </main>

    </div>

<?php endif; ?>

<?php /* تبديل السمة بالتفويض — بلا معالج مضمّن، فيصمد أمام أي CSP */ ?>
<script>document.addEventListener('click',function(e){var b=e.target.closest('.sch-theme');if(!b)return;var r=document.documentElement;var n=r.getAttribute('data-theme')==='dark'?'light':'dark';r.setAttribute('data-theme',n);try{localStorage.setItem('sch-theme',n);}catch(_){}});</script>
<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
</body>
</html>
