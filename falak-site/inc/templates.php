<?php
/**
 * محمّل القوالب + الأجزاء المشتركة.
 * يُرسم القالب المناسب في wp_footer، ويُنقل بالجافاسكربت أعلى الصفحة (كامل العرض)
 * على غرار إضافة أصول، ليعمل مع القوالب البسيطة.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once FALAK_DIR . 'inc/templates/home.php';
require_once FALAK_DIR . 'inc/templates/about.php';
require_once FALAK_DIR . 'inc/templates/programs.php';
require_once FALAK_DIR . 'inc/templates/sections.php';

/**
 * رسم قالب الصفحة الحالية.
 */
add_action( 'wp_footer', 'falak_render_page', 5 );
function falak_render_page() {
	$key = falak_current_page_key();
	if ( '' === $key ) {
		return;
	}
	echo '<main id="falak-page" class="falak-wrap" role="main">';
	switch ( $key ) {
		case 'home':
			falak_tpl_home();
			break;
		case 'about':
			falak_tpl_about();
			break;
		case 'programs':
			falak_tpl_programs();
			break;
		case 'teachers':
			falak_tpl_teachers();
			break;
		case 'gallery':
			falak_tpl_gallery();
			break;
		case 'faq':
			falak_tpl_faq();
			break;
		case 'contact':
			falak_tpl_contact();
			break;
		case 'enroll':
			falak_tpl_enroll();
			break;
		case 'review':
			falak_tpl_review();
			break;
		case 'dashboard':
			falak_tpl_dashboard();
			break;
	}
	echo '</main>';
}

/**
 * فئة body لصفحة الداش بورد (لإخفاء الترويسة/التذييل وإظهار واجهة التطبيق).
 */
add_filter( 'body_class', function ( $classes ) {
	$key = falak_current_page_key();
	if ( 'dashboard' === $key ) {
		$classes[] = 'falak-dashboard';
	} elseif ( '' !== $key ) {
		$classes[] = 'falak-page-' . $key;
	}
	return $classes;
} );

/* ─────────────────────────────────────────
   أجزاء مشتركة
───────────────────────────────────────────*/

/**
 * رأس الصفحة الداخلية.
 */
function falak_page_head( $title, $subtitle = '' ) {
	$home = home_url( '/' );
	?>
	<section class="fk-phead">
		<div class="fk-phead-inner fk-reveal">
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( $subtitle ) : ?><p><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
			<nav class="fk-crumb" aria-label="مسار التنقّل">
				<a href="<?php echo esc_url( $home ); ?>">الرئيسية</a>
				<?php falak_icon( 'chevron', 14 ); ?>
				<span><?php echo esc_html( $title ); ?></span>
			</nav>
		</div>
	</section>
	<?php
}

/**
 * شريط الدعوة للتسجيل (يُستخدم أسفل عدّة صفحات).
 */
function falak_cta_band( $title = null, $text = null ) {
	$home  = home_url( '/' );
	$title = $title ?: 'ابدأ رحلة أبنائك <span>التعليمية</span> معنا';
	$text  = $text ?: 'سجّل الآن وانضم إلى أسرة مدرسة الفلك المنير — المقاعد محدودة لكل مرحلة لضمان جودة التعليم والمتابعة.';
	?>
	<section class="fk-cta">
		<div class="fk-cta-inner fk-reveal">
			<h2><?php echo wp_kses_post( $title ); ?></h2>
			<p><?php echo esc_html( $text ); ?></p>
			<div class="fk-cta-btns">
				<a href="<?php echo esc_url( $home . 'enroll/' ); ?>" class="fk-btn fk-btn-g"><?php falak_icon( 'sparkle', 18 ); ?> سجّل الآن</a>
				<a href="<?php echo esc_url( falak_wa_url( 'السلام عليكم، أرغب في التسجيل بمدرسة الفلك المنير' ) ); ?>" class="fk-btn fk-btn-p" target="_blank" rel="noopener"><?php falak_icon( 'whatsapp', 18 ); ?> تواصل واتساب</a>
			</div>
		</div>
	</section>
	<?php
}

/**
 * شريط الإحصائيات (أرقام متصاعدة).
 */
function falak_stats_band() {
	$stats = array(
		array( falak_opt( 'stat_students' ), 'طالب وطالبة' ),
		array( falak_opt( 'stat_grads' ), 'خريج ومتخرّجة' ),
		array( falak_opt( 'stat_teachers' ), 'معلم ومعلمة' ),
		array( falak_opt( 'stat_years' ), 'سنة من العطاء التعليمي' ),
	);
	?>
	<section class="fk-numbers">
		<div class="fk-num-grid">
			<?php foreach ( $stats as $s ) : $n = (int) preg_replace( '/\D+/', '', (string) $s[0] ); ?>
				<div class="fk-num fk-reveal">
					<div class="n"><span data-count="<?php echo esc_attr( $n ); ?>">0</span>+</div>
					<div class="l"><?php echo esc_html( $s[1] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * بطاقات البرامج (تُستخدم في الرئيسية وصفحة البرامج).
 *
 * @param int $limit عدد البطاقات (0 = الكل).
 */
function falak_programs_cards( $limit = 0 ) {
	$programs = falak_get_programs();
	if ( $limit > 0 ) {
		$programs = array_slice( $programs, 0, $limit );
	}
	$home = home_url( '/' );
	?>
	<div class="fk-prog-grid">
		<?php foreach ( $programs as $p ) : ?>
			<article class="fk-prog-card fk-reveal">
				<div class="fk-prog-top">
					<?php if ( ! empty( $p['badge'] ) ) : ?><span class="fk-prog-badge"><?php echo esc_html( $p['badge'] ); ?></span><?php endif; ?>
					<span class="fk-prog-ic"><?php falak_icon( $p['icon'], 34 ); ?></span>
				</div>
				<div class="fk-prog-body">
					<h3 class="fk-prog-h"><?php echo esc_html( $p['title'] ); ?></h3>
					<p class="fk-prog-d"><?php echo esc_html( $p['excerpt'] ); ?></p>
					<?php if ( ! empty( $p['tags'] ) ) : ?>
						<div class="fk-prog-meta">
							<?php foreach ( $p['tags'] as $tag ) : ?>
								<span class="fk-prog-tag"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<a href="<?php echo esc_url( $home . 'enroll/' ); ?>" class="fk-prog-link">سجّل في البرنامج <?php falak_icon( 'arrow-l', 16 ); ?></a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
}
