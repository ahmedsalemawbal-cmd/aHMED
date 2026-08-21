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
    <meta name="theme-color" content="#F1F4F3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="robots" content="noindex, nofollow">
    <?php /* منع وميض السمة: تُضبط قبل أول رسم. تطبيق المستهلك يتبع الجهاز افتراضيًا. */ ?>
    <?php /* منع الوميض: يُضبط قبل أول رسم. ومن لم يختر يتبع جهازه —
             فالوسم لا يُكتب أصلًا، وCSS يقرّر باستعلام الوسائط. */ ?>
    <script>(function(){try{var s=localStorage.getItem('sch-theme');var r=document.documentElement;
if(s==='dark'||s==='light'){r.setAttribute('data-theme',s);}
var d=s==='dark'||(!s&&window.matchMedia('(prefers-color-scheme: dark)').matches);
var m=document.querySelector('meta[name=theme-color]');if(m){m.content=d?'#12131c':'#f3f4fb';}}catch(e){}})();</script>

    <title><?php echo esc_html(sch_settings('school_name', get_bloginfo('name'))); ?></title>
    <?php $sch_fav = SCH_Brand::favicon(); ?>
    <?php if ($sch_fav !== '') : ?>
        <link rel="icon" href="<?php echo esc_url($sch_fav); ?>">
        <link rel="apple-touch-icon" href="<?php echo esc_url($sch_fav); ?>">
    <?php endif; ?>

    <link rel="apple-touch-icon" href="<?php echo esc_url(SCH_URL . 'assets/icon-192.png'); ?>">
    <link rel="manifest" href="<?php echo esc_url(SCH_App::url('manifest.webmanifest')); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
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
                        /* translators: %s: عدد الأبناء */
                        _n('%s ابن', '%s أبناء', count($p_kids), 'school-system'),
                        number_format_i18n(count($p_kids))
                    )); ?></span>
                </span>
            </a>

            <div class="p-icons">
                <?php /* أيقونتان لا ثلاث كما في التصميم: تبديل المظهر صفٌّ في
                         «حسابي» — وهو إعداد يُضبط مرّة، لا زرّ يزاحم الرسائل
                         والإشعارات في رأس كل شاشة. */ ?>
                <a class="p-ico" href="<?php echo esc_url(SCH_App::url('messages')); ?>"
                   aria-label="<?php esc_attr_e('الرسائل', 'school-system'); ?>">
                    <?php echo sch_icon('chat', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>
                <a class="p-ico" href="<?php echo esc_url(SCH_App::url('alerts')); ?>"
                   aria-label="<?php esc_attr_e('الإشعارات', 'school-system'); ?>">
                    <?php echo sch_icon('bell', 18); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php if ($p_unread > 0) : ?>
                        <span class="p-ico__dot"></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <?php /* الدوّار لا يظهر إلا في شاشةٍ تخصّ ابنًا بعينه.
                 «حسابي» و«الرسائل» و«الإشعارات» و«الاستلام» عن الأسرة كلها،
                 ودوّارٌ فيها يعرض اختيارًا لا يغيّر شيئًا — والاختيار الذي
                 لا يفعل شيئًا يُعلِّم أن الدوّار لا يفعل شيئًا. */ ?>
        <?php if ($p_kids !== [] && !in_array($p_view, ['account', 'pickers', 'alerts', 'messages', 'install'], true)) : ?>
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
            <?php SCH_App::flash(); // شريط الرسالة — مرّةً هنا لا في كل شاشة ?>
            <?php require file_exists($p_file) ? $p_file : SCH_PATH . 'frontend/app/child.php'; ?>
        </main>

        <nav class="p-tabs" aria-label="<?php esc_attr_e('التنقّل', 'school-system'); ?>">
            <?php foreach ([
                'child'    => [__('اليوم', 'school-system'),   'home'],
                'log'      => [__('السجل', 'school-system'),   'chart'],
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

    /* تبديل السمة بالتفويض — يحفظ الاختيار ويحدّث لون شريط المتصفح.
       والقلب يُحسب من **الوضع الظاهر** لا من الوسم: من لم يختر بعدُ لا
       وسم له، فقراءة الوسم وحدها كانت تعطيه «فاتح» ولو كان جهازه داكنًا،
       فتحتاج ضغطتين ليصير فاتحًا. */
    document.addEventListener('click', function (e) {
      var b = e.target.closest('.p-theme');
      if (!b) { return; }
      var r = document.documentElement;
      var cur = r.getAttribute('data-theme');
      var dark = cur === 'dark'
        || (!cur && window.matchMedia('(prefers-color-scheme: dark)').matches);
      var n = dark ? 'light' : 'dark';
      r.setAttribute('data-theme', n);
      try { localStorage.setItem('sch-theme', n); } catch (_) {}
      var m = document.querySelector('meta[name=theme-color]');
      if (m) { m.content = n === 'dark' ? '#12131c' : '#f3f4fb'; }
    });

    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        // نطاق عامل الخدمة يُشتقّ من **مجلّد السكربت**، و`url()` تُلحق شرطة
        // مائلة — فـ`/app/sw.js/` نطاقُه `/app/sw.js/` أي **لا صفحة واحدة**:
        // لا عملَ بلا شبكة، و`serviceWorker.ready` لا تُحلّ فيتجمّد الدفع.
        navigator.serviceWorker.register(<?php echo wp_json_encode(untrailingslashit(SCH_App::url('sw.js'))); ?>, { scope: <?php echo wp_json_encode(SCH_App::url()); ?> }).catch(function () {});
      });
    }
    </script>

<?php endif; ?>

<?php SCH_Push::boot(); // الإشعارات الفورية — بعد تسجيل عامل الخدمة ?>
<script src="<?php echo esc_url(sch_asset('assets/list-tools.js')); ?>" defer></script>
</body>
</html>
