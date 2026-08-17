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
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/shared-ui.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/dashboard.css')); ?>">
</head>
<body class="sch-body<?php echo $sch_is_login || $sch_is_hub ? ' sch-body--auth' : ''; ?>">
<a class="sch-skip" href="#sch-main-content"><?php esc_html_e('تخطَّ إلى المحتوى', 'school-system'); ?></a>


<?php if ($sch_is_login || $sch_is_hub) : ?>

    <?php require $file; ?>

<?php else : ?>

    <?php
    $sch_groups   = SCH_Dashboard::groups();
    $sch_in_set   = SCH_Dashboard::is_settings($sch_current);
    $sch_setnav   = SCH_Dashboard::settings_nav();
    $sch_set_home = SCH_Dashboard::settings_home();
    $sch_user_ob  = wp_get_current_user();
    ?>

    <?php
    $sch_open   = SCH_Alerts::open_count();
    $sch_school = sch_settings('school_name', get_bloginfo('name'));
    // عنوان الشاشة الحالية للمسار العلوي (breadcrumb) — من تعريف القسم مباشرة،
    // فأقسام الإعدادات لم تعد ضمن مجموعات التنقّل الرئيسي.
    $sch_secs  = SCH_Dashboard::sections();
    $sch_title = isset($sch_secs[$sch_current])
        ? (string) $sch_secs[$sch_current][0]
        : __('لوحة التحكم', 'school-system');
    ?>

    <!-- التنقّل بشريط جانبي (يمين): مجموعات وأقسام تُقرأ رأسيًا،
         والشاشة تحتفظ بعمق ثابت للمسار والأدوات في الأعلى. -->
    <div class="sch-app" id="sch-app">
        <aside class="sch-side<?php echo $sch_in_set ? ' sch-side--set' : ''; ?>" id="sch-side">
            <?php /* طيّ الشريط إلى عمود أيقونات — الحالة محفوظة في localStorage.
                     لا يظهر في سياق الإعدادات: تنقّله نصّي بلا أيقونات فلا يُقرأ مطويًّا. */ ?>
            <?php if (!$sch_in_set) : ?>
                <button type="button" class="sch-side__rail" id="sch-rail"
                        aria-controls="sch-side" aria-expanded="true"
                        aria-label="<?php esc_attr_e('طيّ الشريط الجانبي', 'school-system'); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                </button>
            <?php endif; ?>

            <?php /* علامة بلا نصّ: الحرف زينة، والاسم يُنطق من aria-label — أيقونة
                     بلا اسم تُعرَف بالتجربة لا بالنظر (درس v3.4.2). */ ?>
            <a class="sch-side__brand" href="<?php echo esc_url(SCH_Dashboard::url()); ?>"
               title="<?php echo esc_attr($sch_school); ?>"
               aria-label="<?php echo esc_attr($sch_school); ?>">
                <span class="sch-side__mark" aria-hidden="true"><?php echo esc_html(mb_substr($sch_school, 0, 1)); ?></span>
            </a>

            <?php /* بطاقة الحساب تحت العلامة مباشرة — كأنظمة SaaS الكبرى */ ?>
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
            <div class="sch-side__sep"></div>

            <?php if ($sch_in_set) : /* ── سياق الإعدادات: يستولي على الشريط الجانبي ── */ ?>
                <?php $sch_back = current_user_can('sch_view_students') ? SCH_Dashboard::url('overview') : SCH_Dashboard::url(); ?>
                <a class="sch-side__back" href="<?php echo esc_url($sch_back); ?>"
                   title="<?php esc_attr_e('رجوع إلى النظام', 'school-system'); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    <span><?php esc_html_e('رجوع إلى النظام', 'school-system'); ?></span>
                </a>
                <div class="sch-side__settl">
                    <?php echo sch_icon('cog', 20); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <b><?php esc_html_e('الإعدادات', 'school-system'); ?></b>
                </div>
                <nav class="sch-side__nav" aria-label="<?php esc_attr_e('الإعدادات', 'school-system'); ?>">
                    <?php foreach ($sch_setnav as $sch_gl => $sch_items) : ?>
                        <div class="sch-side__seg">
                            <span class="sch-side__segl"><?php echo esc_html((string) $sch_gl); ?></span>
                            <?php foreach ($sch_items as $sch_slug => $sch_meta) : ?>
                                <a class="sch-side__link<?php echo $sch_slug === $sch_current ? ' is-on' : ''; ?>"
                                   href="<?php echo esc_url(SCH_Dashboard::url($sch_slug)); ?>">
                                    <span><?php echo esc_html((string) $sch_meta[0]); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </nav>

            <?php else : /* ── التنقّل الرئيسي للعمل اليومي ── */ ?>
                <nav class="sch-side__nav" aria-label="<?php esc_attr_e('الأقسام', 'school-system'); ?>">
                    <?php $sch_i = 0; $sch_parea = ''; foreach ($sch_groups as $sch_key => $sch_g) : $sch_i++;
                        $sch_area = (string) explode('|', (string) $sch_key)[0];
                        $sch_area_new = ($sch_area !== $sch_parea && $sch_i > 1);
                        $sch_parea = $sch_area; ?>
                        <div class="sch-side__grp<?php echo $sch_area_new ? ' is-area' : ''; ?>" data-grp="<?php echo esc_attr((string) $sch_key); ?>">
                            <button type="button" class="sch-side__h" aria-expanded="true" aria-controls="sch-grp-<?php echo esc_attr((string) $sch_i); ?>"
                                    title="<?php echo esc_attr((string) $sch_g['label']); ?>">
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
                <?php /* استعادة حالة طيّ المجموعات وطيّ الشريط قبل رسم بقية الصفحة — بلا وميض */ ?>
                <script>(function(){try{var s=JSON.parse(localStorage.getItem('sch-side-collapsed')||'[]');document.querySelectorAll('.sch-side__grp').forEach(function(g){if(s.indexOf(g.getAttribute('data-grp'))>-1){g.classList.add('is-collapsed');var h=g.querySelector('.sch-side__h');if(h)h.setAttribute('aria-expanded','false');}});}catch(e){}
try{if(localStorage.getItem('sch-rail')==='1'&&window.matchMedia('(min-width:981px)').matches){var a=document.getElementById('sch-app');if(a){a.classList.add('is-rail');var r=document.getElementById('sch-rail');if(r)r.setAttribute('aria-expanded','false');}}}catch(e){}})();</script>

                <?php if ($sch_set_home !== '') : /* مدخل الإعدادات المثبّت أسفل التنقّل — كأنظمة SaaS */ ?>
                    <a class="sch-side__pin" href="<?php echo esc_url(SCH_Dashboard::url($sch_set_home)); ?>"
                       title="<?php esc_attr_e('الإعدادات', 'school-system'); ?>">
                        <?php echo sch_icon('cog', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        <span><?php esc_html_e('الإعدادات', 'school-system'); ?></span>
                        <svg class="sch-side__pinch" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

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

                <button type="button" class="sch-cmdk-open" id="sch-cmdk-open" aria-label="<?php esc_attr_e('بحث وتنقّل سريع', 'school-system'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <span><?php esc_html_e('ابحث أو انتقل…', 'school-system'); ?></span>
                    <kbd>Ctrl K</kbd>
                </button>

                <span class="sch-top__sp"></span>

                <span class="sch-topbar__date"><?php echo esc_html(wp_date('l، j F')); ?></span>

                <?php
                $sch_bell_items = sch_pending_items();
                $sch_bell_rows  = array_values(array_filter($sch_bell_items, static fn (array $i): bool => (int) $i['n'] > 0));
                $sch_bell_n     = array_sum(array_map(static fn (array $i): int => (int) $i['n'], $sch_bell_items));
                ?>
                <div class="sch-bell" id="sch-bell">
                    <button type="button" class="sch-top__ic sch-bell__btn<?php echo $sch_bell_n > 0 ? ' has-dot' : ''; ?>"
                            id="sch-bell-btn" aria-haspopup="true" aria-expanded="false"
                            aria-label="<?php esc_attr_e('ما ينتظرك', 'school-system'); ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    </button>
                    <div class="sch-bell__drop" id="sch-bell-drop" hidden>
                        <div class="sch-bell__h">
                            <b><?php esc_html_e('ما ينتظرك', 'school-system'); ?></b>
                            <span><?php echo esc_html(number_format_i18n($sch_bell_n)); ?></span>
                        </div>
                        <?php if ($sch_bell_rows === []) : ?>
                            <div class="sch-bell__empty">
                                <?php echo esc_html($sch_bell_items === []
                                    ? __('لا صلاحيات متابعة لديك.', 'school-system')
                                    : __('كل شيء تحت السيطرة — لا مهمة عاجلة.', 'school-system')); ?>
                            </div>
                        <?php else : ?>
                            <?php foreach ($sch_bell_rows as $sch_br) : ?>
                                <a class="sch-bell__row" href="<?php echo esc_url($sch_br['url']); ?>">
                                    <span class="sch-bell__n sch-bell__n--<?php echo esc_attr($sch_br['level']); ?>"><?php echo esc_html(number_format_i18n((int) $sch_br['n'])); ?></span>
                                    <span class="sch-bell__t">
                                        <strong><?php echo esc_html($sch_br['title']); ?></strong>
                                        <span><?php echo esc_html($sch_br['hint']); ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

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
                    // المعالجات تُرجع رسالة تقول ما جرى بعدده («صدرت ٢٨ شهادة»)،
                    // وكانت تُمرَّر في الرابط ثم تُرمى ويُطبع «تم الحفظ.» العامة.
                    $sch_ok = isset($_GET['msg'])
                        ? sanitize_text_field(wp_unslash((string) $_GET['msg']))
                        : __('تم الحفظ.', 'school-system');

                    echo '<div class="sch-toast" data-toast role="status">' . esc_html($sch_ok) . '</div>';
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
    <?php
    // بيانات لوحة الأوامر: كل قسم يملكه المستخدم (مُصفّى بالصلاحية في groups()).
    $sch_cmdk = [];
    foreach ($sch_groups as $sch_cg) {
        foreach ($sch_cg['sections'] as $sch_cs => $sch_cm) {
            $sch_cmdk[] = [
                'label' => (string) $sch_cm[0],
                'group' => (string) $sch_cg['label'],
                'url'   => SCH_Dashboard::url((string) $sch_cs),
            ];
        }
    }
    // أقسام الإعدادات ما زالت قابلة للوصول عبر ⌘K رغم خروجها من التنقّل الرئيسي.
    foreach ($sch_setnav as $sch_sgl => $sch_sitems) {
        foreach ($sch_sitems as $sch_cs => $sch_cm) {
            $sch_cmdk[] = [
                'label' => (string) $sch_cm[0],
                'group' => __('الإعدادات', 'school-system') . ' · ' . (string) $sch_sgl,
                'url'   => SCH_Dashboard::url((string) $sch_cs),
            ];
        }
    }
    ?>
    <div class="sch-cmdk" id="sch-cmdk" hidden data-items="<?php echo esc_attr(wp_json_encode($sch_cmdk, JSON_UNESCAPED_UNICODE)); ?>">
        <div class="sch-cmdk__box" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('لوحة الأوامر', 'school-system'); ?>">
            <div class="sch-cmdk__in">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="sch-cmdk-input" autocomplete="off" spellcheck="false"
                       placeholder="<?php esc_attr_e('اكتب اسم قسم للانتقال…', 'school-system'); ?>"
                       aria-label="<?php esc_attr_e('بحث وتنقّل', 'school-system'); ?>">
                <kbd>Esc</kbd>
            </div>
            <div class="sch-cmdk__list" id="sch-cmdk-list" role="listbox"></div>
        </div>
    </div>

    <div class="sch-scrim" id="sch-scrim" hidden></div>

<?php endif; ?>

<?php /* تبديل السمة بالتفويض — بلا معالج مضمّن، فيصمد أمام أي CSP */ ?>
<script>document.addEventListener('click',function(e){var b=e.target.closest('.sch-theme');if(b){var r=document.documentElement;var n=r.getAttribute('data-theme')==='dark'?'light':'dark';r.setAttribute('data-theme',n);try{localStorage.setItem('sch-theme',n);}catch(_){}return;}
var rl=e.target.closest('.sch-side__rail');var ap=document.getElementById('sch-app');
if(rl&&ap){var on=ap.classList.toggle('is-rail');rl.setAttribute('aria-expanded',on?'false':'true');try{localStorage.setItem('sch-rail',on?'1':'0');}catch(_){}return;}
var hh=e.target.closest('.sch-side__h');if(hh){var gg=hh.closest('.sch-side__grp');
/* في وضع العمود: الضغط على أيقونة يفتح الشريط على مجموعتها بدل طيّها */
if(ap&&ap.classList.contains('is-rail')){ap.classList.remove('is-rail');try{localStorage.setItem('sch-rail','0');}catch(_){}var rb=document.getElementById('sch-rail');if(rb)rb.setAttribute('aria-expanded','true');
if(gg&&gg.classList.contains('is-collapsed')){gg.classList.remove('is-collapsed');hh.setAttribute('aria-expanded','true');try{var k2=gg.getAttribute('data-grp'),s2=JSON.parse(localStorage.getItem('sch-side-collapsed')||'[]');localStorage.setItem('sch-side-collapsed',JSON.stringify(s2.filter(function(x){return x!==k2;})));}catch(_){}}
return;}
if(gg){var col=gg.classList.toggle('is-collapsed');hh.setAttribute('aria-expanded',col?'false':'true');try{var k=gg.getAttribute('data-grp'),s=JSON.parse(localStorage.getItem('sch-side-collapsed')||'[]');s=s.filter(function(x){return x!==k;});if(col)s.push(k);localStorage.setItem('sch-side-collapsed',JSON.stringify(s));}catch(_){}}return;}
var nm=e.target.closest('[data-ns-more]');if(nm){var sec=nm.closest('.sch-ns'),w=sec&&sec.querySelector('[data-ns-morewrap]');if(w){var hid=w.hasAttribute('hidden');w.toggleAttribute('hidden',!hid);nm.setAttribute('aria-expanded',hid?'true':'false');}return;}
var nd=e.target.closest('[data-ns-done]');if(nd){var box=nd.parentElement.querySelector('.sch-ns__chips');if(box){var hid2=box.hasAttribute('hidden');box.toggleAttribute('hidden',!hid2);nd.setAttribute('aria-expanded',hid2?'true':'false');nd.closest('.sch-ns__done').classList.toggle('is-open',hid2);}return;}
var bg=e.target.closest('.sch-burger'),sc=e.target.closest('.sch-scrim');var side=document.getElementById('sch-side'),scrim=document.getElementById('sch-scrim');
if(bg&&side){side.classList.add('is-open');if(scrim)scrim.hidden=false;return;}
if((sc||(!e.target.closest('.sch-side')&&side&&side.classList.contains('is-open')&&window.innerWidth<=980))&&side){side.classList.remove('is-open');if(scrim)scrim.hidden=true;}});</script>
<?php /* لوحة الأوامر ⌘K + جرس «ما ينتظرك» — تنقّل فوري وتجميع المهام المعلّقة */ ?>
<script>
(function () {
  var root = document.getElementById('sch-cmdk');
  if (root) {
    var input = document.getElementById('sch-cmdk-input');
    var list  = document.getElementById('sch-cmdk-list');
    var items = [];
    try { items = JSON.parse(root.dataset.items || '[]'); } catch (e) { items = []; }
    var sel = 0;
    var NONE = '<?php echo esc_js(__('لا نتائج', 'school-system')); ?>';

    function esc(s) { return String(s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }

    function render(q) {
      q = (q || '').trim().toLowerCase();
      var view = q === '' ? items.slice(0, 60) : items.filter(function (it) {
        return (it.label + ' ' + it.group).toLowerCase().indexOf(q) > -1;
      });
      sel = 0;
      if (!view.length) { list.innerHTML = '<div class="sch-cmdk__empty">' + esc(NONE) + '</div>'; return; }
      list.innerHTML = view.map(function (it, i) {
        return '<a class="sch-cmdk__row' + (i === 0 ? ' is-sel' : '') + '" href="' + encodeURI(it.url) + '">' +
          '<span class="sch-cmdk__lbl">' + esc(it.label) + '</span>' +
          '<span class="sch-cmdk__grp">' + esc(it.group) + '</span></a>';
      }).join('');
    }
    function openK() { root.hidden = false; document.body.classList.add('sch-cmdk-on'); input.value = ''; render(''); setTimeout(function () { input.focus(); }, 20); }
    function closeK() { root.hidden = true; document.body.classList.remove('sch-cmdk-on'); }
    function move(d) {
      var rows = list.querySelectorAll('.sch-cmdk__row'); if (!rows.length) { return; }
      if (rows[sel]) { rows[sel].classList.remove('is-sel'); }
      sel = (sel + d + rows.length) % rows.length;
      rows[sel].classList.add('is-sel'); rows[sel].scrollIntoView({ block: 'nearest' });
    }

    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); root.hidden ? openK() : closeK(); return; }
      if (root.hidden) { return; }
      if (e.key === 'Escape') { e.preventDefault(); closeK(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter') { e.preventDefault(); var r = list.querySelectorAll('.sch-cmdk__row')[sel]; if (r) { window.location.href = r.getAttribute('href'); } }
    });
    input.addEventListener('input', function () { render(input.value); });
    root.addEventListener('click', function (e) { if (e.target === root) { closeK(); } });
    var opener = document.getElementById('sch-cmdk-open');
    if (opener) { opener.addEventListener('click', openK); }
  }

  var bell = document.getElementById('sch-bell');
  var bbtn = document.getElementById('sch-bell-btn');
  var drop = document.getElementById('sch-bell-drop');
  if (bell && bbtn && drop) {
    bbtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var willOpen = drop.hasAttribute('hidden');
      drop.toggleAttribute('hidden', !willOpen);
      bbtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!drop.hasAttribute('hidden') && !bell.contains(e.target)) {
        drop.setAttribute('hidden', ''); bbtn.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
</script>
<?php /* التوست: مكوّن مصمَّم بالكامل في shared-ui وبلا استعمال واحد حتى الآن.
         يظهر ثم ينسحب، ويُنظَّف الرابط فلا يعود بالتحديث. */ ?>
<script>
(function () {
  var t = document.querySelector('[data-toast]');
  if (!t) { return; }
  requestAnimationFrame(function () { t.classList.add('is-on'); });
  setTimeout(function () { t.classList.remove('is-on'); }, 4200);
  try {
    var u = new URL(window.location.href);
    if (u.searchParams.has('ok')) {
      u.searchParams.delete('ok'); u.searchParams.delete('msg');
      window.history.replaceState({}, '', u.toString());
    }
  } catch (e) {}
})();
</script>
<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
<script src="<?php echo esc_url(sch_asset('assets/select.js')); ?>" defer></script>
</body>
</html>
