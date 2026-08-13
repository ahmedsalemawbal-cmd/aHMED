<?php
/**
 * قالب تطبيق ولي الأمر.
 *
 * يحمل الشريط العلوي ودوّار الأبناء والشريط السفلي، ويُدرج الشاشة بينها.
 * كل شاشة تفترض أن `$sch_data` و`$sch_view` معرّفان من `SCH_App::render()`.
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$p_user    = wp_get_current_user();
$p_kids    = (array) ($sch_data['children'] ?? []);
$p_kid_id  = (int) ($sch_data['id'] ?? 0);
$p_view    = (string) ($sch_view ?? 'child');
$p_bare    = in_array($p_view, ['login', 'install', 'denied'], true);
$p_unread  = $p_bare ? 0 : SCH_Comms::unread_count(get_current_user_id());
$p_file    = SCH_PATH . 'frontend/app/' . $p_view . '.php';

// الاسم الأول وحده: الرباعي يُقطع بثلاث نقاط ولا يفيد أحدًا.
$p_first = trim((string) (explode(' ', trim((string) $p_user->display_name))[0] ?? ''));
$p_hour  = (int) current_time('G');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F5F3F0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="robots" content="noindex, nofollow">
    <?php /* منع وميض السمة: تُضبط قبل أول رسم. تطبيق المستهلك يتبع الجهاز افتراضيًا. */ ?>
    <script>(function(){try{var s=localStorage.getItem('sch-theme');var t=s==='light'?'light':(s==='dark'?'dark':(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'));document.documentElement.setAttribute('data-theme',t);var m=document.querySelector('meta[name=theme-color]');if(m){m.content=t==='dark'?'#0C100E':'#F5F3F0';}}catch(e){}})();</script>

    <title><?php echo esc_html(sch_settings('school_name', get_bloginfo('name'))); ?></title>

    <link rel="apple-touch-icon" href="<?php echo esc_url(SCH_URL . 'assets/icon-192.png'); ?>">
    <link rel="manifest" href="<?php echo esc_url(SCH_App::url('manifest.webmanifest')); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/parent.css')); ?>">
</head>

<body class="p-body<?php echo esc_attr($p_bare ? ' p-body--auth' : ''); ?>">

<a class="p-skip" href="#p-content"><?php esc_html_e('تخطَّ إلى المحتوى', 'school-system'); ?></a>

<?php if ($p_bare) : ?>

    <?php require file_exists($p_file) ? $p_file : SCH_PATH . 'frontend/app/login.php'; ?>

<?php else : ?>

    <div class="p-app">

        <header class="p-top">
            <a class="p-who" href="<?php echo esc_url(SCH_App::url('account')); ?>">
                <span class="p-who__pic">
                    <?php if (SCH_App::avatar_url()) : ?>
                        <img src="<?php echo esc_url(SCH_App::avatar_url()); ?>" alt="" width="40" height="40">
                    <?php else : ?>
                        <?php echo sch_avatar_svg(mb_substr((string) $p_user->display_name, 0, 1), 40); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php endif; ?>
                </span>
                <span class="p-who__t">
                    <b><?php echo esc_html(sprintf(
                        /* translators: %s: الاسم الأول */
                        $p_hour < 12 ? __('صباح الخير %s', 'school-system') : __('مساء الخير %s', 'school-system'),
                        $p_first
                    )); ?></b>
                    <span><?php echo esc_html(sprintf(
                        /* translators: %d: عدد الأبناء */
                        _n('%d ابن', '%d أبناء', count($p_kids), 'school-system'),
                        count($p_kids)
                    )); ?></span>
                </span>
            </a>

            <div class="p-icons">
                <button type="button" class="p-ico p-theme" aria-label="<?php esc_attr_e('تبديل الوضع الفاتح/الداكن', 'school-system'); ?>">
                    <svg class="p-theme__moon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="p-theme__sun" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>
                <a class="p-ico" href="<?php echo esc_url(SCH_App::url('messages')); ?>"
                   aria-label="<?php esc_attr_e('الرسائل', 'school-system'); ?>">
                    <?php echo sch_icon('mail', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>
                <a class="p-ico" href="<?php echo esc_url(SCH_App::url('alerts')); ?>"
                   aria-label="<?php esc_attr_e('الإشعارات', 'school-system'); ?>">
                    <?php echo sch_icon('clock', 19); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php if ($p_unread > 0) : ?>
                        <span class="p-ico__dot"></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <?php if ($p_kids !== []) : ?>
            <!-- السحب بالإصبع يبدّل الابن ويستقر عند بطاقة تمامًا -->
            <nav class="p-kids" id="p-kids" aria-label="<?php esc_attr_e('الأبناء', 'school-system'); ?>">
                <?php foreach ($p_kids as $p_kid) :
                    $p_on   = (int) $p_kid->id === $p_kid_id;
                    $p_cls  = SCH_Students::current_class((int) $p_kid->id);
                    $p_pic  = !empty($p_kid->photo_file) ? SCH_App::photo_url((int) $p_kid->id) : '';
                    // لون ثابت لكل ابن: يُعرَف بالعين قبل قراءة الاسم.
                    $p_tone = (int) $p_kid->id % 6; ?>
                    <a class="p-kid p-kid--<?php echo esc_attr((string) $p_tone); ?><?php echo esc_attr($p_on ? ' is-on' : ''); ?>"
                       href="<?php echo esc_url(SCH_App::url(in_array($p_view, ['child', 'log', 'invoices', 'card'], true) ? $p_view : 'child', (int) $p_kid->id)); ?>">
                        <span class="p-kid__pic">
                            <?php if ($p_pic) : ?>
                                <img src="<?php echo esc_url($p_pic); ?>" alt="" width="54" height="54" loading="lazy">
                            <?php else : ?>
                                <?php echo sch_avatar_svg(mb_substr((string) $p_kid->full_name, 0, 1), 54); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <?php endif; ?>
                        </span>
                        <b class="p-kid__n"><?php echo esc_html($p_kid->first_name ?: $p_kid->full_name); ?></b>
                        <span class="p-kid__c"><?php echo esc_html($p_cls ? SCH_Classes::label($p_cls) : ''); ?></span>
                        <span class="p-kid__s"><?php echo esc_html(SCH_Custody::state_label($p_kid->custody_state ?? 'home')); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <main class="p-main" id="p-content">
            <?php require file_exists($p_file) ? $p_file : SCH_PATH . 'frontend/app/child.php'; ?>
        </main>

        <nav class="p-tabs" aria-label="<?php esc_attr_e('التنقّل', 'school-system'); ?>">
            <?php foreach ([
                'child'    => [__('اليوم', 'school-system'),   'home'],
                'log'      => [__('السجل', 'school-system'),   'list'],
                'invoices' => [__('الرسوم', 'school-system'),  'wallet'],
                'card'     => [__('البطاقة', 'school-system'), 'badge'],
            ] as $p_slug => $p_tab) : ?>
                <a class="p-tab<?php echo esc_attr($p_view === $p_slug ? ' is-on' : ''); ?>"
                   href="<?php echo esc_url(SCH_App::url($p_slug, $p_kid_id)); ?>">
                    <?php echo sch_icon($p_tab[1], 19); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <span><?php echo esc_html($p_tab[0]); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <script>
    (function () {
      var rail = document.getElementById('p-kids');
      if (!rail) { return; }

      /* السحب وحده يحرّك الشريط ولا يبدّل. فنُصغي لاستقراره:
         البطاقة التي في الوسط تُبرَز فورًا، ثم يُنتقل إليها. */
      var timer = null;
      var active = rail.querySelector('.p-kid.is-on');

      function middle() {
        var mid = rail.getBoundingClientRect().left + rail.clientWidth / 2;
        var best = null, gap = Infinity;

        rail.querySelectorAll('.p-kid').forEach(function (card) {
          var r = card.getBoundingClientRect();
          var d = Math.abs((r.left + r.width / 2) - mid);
          if (d < gap) { gap = d; best = card; }
        });

        return best;
      }

      if (active) {
        var t = rail.getBoundingClientRect();
        var k = active.getBoundingClientRect();
        rail.scrollBy({ left: (k.left + k.width / 2) - (t.left + t.width / 2), behavior: 'auto' });
      }

      rail.addEventListener('scroll', function () {
        clearTimeout(timer);

        var near = middle();
        if (near) {
          rail.querySelectorAll('.p-kid').forEach(function (c) {
            c.classList.toggle('is-on', c === near);
          });
        }

        timer = setTimeout(function () {
          var pick = middle();
          if (pick && pick !== active && pick.href) {
            active = pick;
            window.location.href = pick.href;
          }
        }, 360);
      }, { passive: true });
    })();

    /* تبديل السمة بالتفويض — يحفظ الاختيار ويحدّث لون شريط المتصفح */
    document.addEventListener('click', function (e) {
      var b = e.target.closest('.p-theme');
      if (!b) { return; }
      var r = document.documentElement;
      var n = r.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      r.setAttribute('data-theme', n);
      try { localStorage.setItem('sch-theme', n); } catch (_) {}
      var m = document.querySelector('meta[name=theme-color]');
      if (m) { m.content = n === 'dark' ? '#0C100E' : '#F5F3F0'; }
    });

    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register(<?php echo wp_json_encode(SCH_App::url('sw.js')); ?>).catch(function () {});
      });
    }
    </script>

<?php endif; ?>

<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
</body>
</html>
