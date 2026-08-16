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
    <?php /* منع وميض السمة: تُضبط قبل أول رسم. الافتراضي فاتح — هوية SaaS. */ ?>
    <script>(function(){try{var t=localStorage.getItem('sch-theme');document.documentElement.setAttribute('data-theme',t==='dark'?'dark':'light');}catch(e){document.documentElement.setAttribute('data-theme','light');}})();</script>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html(get_bloginfo('name')); ?> — <?php esc_html_e('نظام المدرسة', 'school-system'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
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

    <?php
    $sch_open  = SCH_Alerts::open_count();
    $sch_school = sch_settings('school_name', get_bloginfo('name'));
    // عنوان الشاشة الحالية للمسار العلوي (breadcrumb)
    $sch_title = __('لوحة التحكم', 'school-system');
    foreach ($sch_groups as $sch_g) {
        foreach ($sch_g['sections'] as $sch_s => $sch_m) {
            if ($sch_s === $sch_current) { $sch_title = (string) $sch_m[0]; }
        }
    }
    ?>

    <!-- التنقّل بشريط جانبي (يمين): مجموعات وأقسام تُقرأ رأسيًا،
         والشاشة تحتفظ بعمق ثابت للمسار والأدوات في الأعلى. -->
    <div class="sch-app">
        <aside class="sch-side" id="sch-side">
            <a class="sch-side__brand" href="<?php echo esc_url(SCH_Dashboard::url()); ?>">
                <span class="sch-side__mark"><?php echo esc_html(mb_substr($sch_school, 0, 1)); ?></span>
                <span class="sch-side__bt">
                    <b><?php echo esc_html($sch_school); ?></b>
                    <span><?php esc_html_e('لوحة الإدارة', 'school-system'); ?></span>
                </span>
            </a>

            <nav class="sch-side__nav" aria-label="<?php esc_attr_e('الأقسام', 'school-system'); ?>">
                <?php $sch_i = 0; foreach ($sch_groups as $sch_key => $sch_g) : $sch_i++; ?>
                    <div class="sch-side__grp" data-grp="<?php echo esc_attr((string) $sch_key); ?>">
                        <button type="button" class="sch-side__h" aria-expanded="true" aria-controls="sch-grp-<?php echo esc_attr((string) $sch_i); ?>">
                            <?php echo sch_icon((string) $sch_g['icon'], 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <span><?php echo esc_html($sch_g['label']); ?></span>
                            <svg class="sch-side__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="sch-side__links" id="sch-grp-<?php echo esc_attr((string) $sch_i); ?>"><div class="sch-side__linksin">
                        <?php foreach ($sch_g['sections'] as $sch_slug => $sch_meta) : ?>
                            <a class="sch-side__link<?php echo $sch_slug === $sch_current ? ' is-on' : ''; ?>"
                               href="<?php echo esc_url(SCH_Dashboard::url($sch_slug)); ?>">
                                <span><?php echo esc_html((string) $sch_meta[0]); ?></span>
                                <?php if ($sch_slug === 'alerts' && $sch_open > 0) : ?>
                                    <span class="sch-side__badge"><?php echo esc_html((string) $sch_open); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        </div></div>
                    </div>
                <?php endforeach; ?>
            </nav>
            <?php /* استعادة حالة طيّ المجموعات قبل رسم بقية الصفحة — بلا وميض */ ?>
            <script>(function(){try{var s=JSON.parse(localStorage.getItem('sch-side-collapsed')||'[]');document.querySelectorAll('.sch-side__grp').forEach(function(g){if(s.indexOf(g.getAttribute('data-grp'))>-1){g.classList.add('is-collapsed');var h=g.querySelector('.sch-side__h');if(h)h.setAttribute('aria-expanded','false');}});}catch(e){}})();</script>

            <div class="sch-side__user">
                <i><?php echo esc_html(mb_substr($sch_user_ob->display_name, 0, 1)); ?></i>
                <span class="sch-side__ut">
                    <b><?php echo esc_html($sch_user_ob->display_name); ?></b>
                    <span><?php esc_html_e('حساب الإدارة', 'school-system'); ?></span>
                </span>
                <a class="sch-side__out" href="<?php echo esc_url(SCH_Dashboard::url('logout')); ?>"
                   aria-label="<?php esc_attr_e('خروج', 'school-system'); ?>">
                    <?php echo sch_icon('route', 16); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>
            </div>
        </aside>

        <div class="sch-main">
            <header class="sch-topbar">
                <button type="button" class="sch-burger" id="sch-burger" aria-label="<?php esc_attr_e('القائمة', 'school-system'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>
                <nav class="sch-crumb" aria-label="<?php esc_attr_e('المسار', 'school-system'); ?>">
                    <span><?php echo esc_html($sch_school); ?></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    <b><?php echo esc_html($sch_title); ?></b>
                </nav>

                <span class="sch-top__sp"></span>

                <span class="sch-topbar__date"><?php echo esc_html(wp_date('l، j F')); ?></span>

                <a class="sch-top__ic<?php echo $sch_open > 0 ? ' has-dot' : ''; ?>"
                   href="<?php echo esc_url(SCH_Dashboard::url('alerts')); ?>"
                   aria-label="<?php esc_attr_e('الإنذارات', 'school-system'); ?>">
                    <?php echo sch_icon('clock', 17); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>

                <button type="button" class="sch-theme" aria-label="<?php esc_attr_e('تبديل الوضع الفاتح/الداكن', 'school-system'); ?>">
                    <svg class="sch-theme__moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="sch-theme__sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>
            </header>

            <main class="sch-content" id="sch-main-content">
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
            </main>
        </div>
    </div>
    <div class="sch-scrim" id="sch-scrim" hidden></div>

<?php endif; ?>

<?php /* تبديل السمة بالتفويض — بلا معالج مضمّن، فيصمد أمام أي CSP */ ?>
<script>document.addEventListener('click',function(e){var b=e.target.closest('.sch-theme');if(b){var r=document.documentElement;var n=r.getAttribute('data-theme')==='dark'?'light':'dark';r.setAttribute('data-theme',n);try{localStorage.setItem('sch-theme',n);}catch(_){}return;}
var hh=e.target.closest('.sch-side__h');if(hh){var gg=hh.closest('.sch-side__grp');if(gg){var col=gg.classList.toggle('is-collapsed');hh.setAttribute('aria-expanded',col?'false':'true');try{var k=gg.getAttribute('data-grp'),s=JSON.parse(localStorage.getItem('sch-side-collapsed')||'[]');s=s.filter(function(x){return x!==k;});if(col)s.push(k);localStorage.setItem('sch-side-collapsed',JSON.stringify(s));}catch(_){}}return;}
var bg=e.target.closest('.sch-burger'),sc=e.target.closest('.sch-scrim');var side=document.getElementById('sch-side'),scrim=document.getElementById('sch-scrim');
if(bg&&side){side.classList.add('is-open');if(scrim)scrim.hidden=false;return;}
if((sc||(!e.target.closest('.sch-side')&&side&&side.classList.contains('is-open')&&window.innerWidth<=980))&&side){side.classList.remove('is-open');if(scrim)scrim.hidden=true;}});</script>
<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
</body>
</html>
