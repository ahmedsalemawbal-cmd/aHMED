<?php
/**
 * Product pages: catalog portal, the three archive pages, single product detail.
 *
 * The shared product CSS that the legacy code printed on *every* page (wp_head)
 * is now emitted only on the product pages that actually use it.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Product-page CSS — emitted once, only on a product-related page.
 */
function osoul_products_page_css() {
	?>
<style id="osoul-products-css">
.opp{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#E9F2F8}
body.lang-en .opp{direction:ltr}
.opp-hero,.odp-hero,.odps-hero{background:#00344F;padding:80px 60px 64px;position:relative;overflow:hidden}
.opp-hero::before,.odp-hero::before,.odps-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#0074A4,transparent)}
.opp-hero-inner,.odp-hero-inner,.odps-hero-inner{position:relative;z-index:1;max-width:860px}
.opp-tag,.odp-tag{font-size:11px;letter-spacing:5px;color:#0074A4;text-transform:uppercase;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px}
.opp-tag::before,.odp-tag::before{content:'';width:28px;height:1px;background:#0074A4}
.opp-h1,.odp-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(32px,5vw,54px);font-weight:700;color:#fff;line-height:1.15;margin-bottom:14px}
.opp-h1 i,.odp-h1 i{font-style:italic;color:#0074A4}
.opp-sub{font-size:15px;color:rgba(255,255,255,.58);max-width:540px;line-height:1.65;font-weight:300}
.opp-body{max-width:1280px;margin:0 auto;padding:60px 48px 80px}
.opp-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.opp-card{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,52,79,.06);transition:all .3s;direction:rtl}
body.lang-en .opp-card{direction:ltr}
.opp-card:hover{box-shadow:0 10px 36px rgba(0,52,79,.13);transform:translateY(-4px)}
.opp-card-img{height:190px;overflow:hidden;background:#F0F6FA;position:relative}
.opp-card-img img{width:100%;height:100%;object-fit:contain;background:#fff;display:block;transition:transform .5s;padding:10px}
.opp-card:hover .opp-card-img img{transform:scale(1.06)}
.opp-card-body{padding:16px 18px 18px}
.opp-card-cat{font-size:10px;letter-spacing:2px;color:rgba(0,116,164,.8);text-transform:uppercase;font-weight:700;margin-bottom:6px}
.opp-card-h{font-size:14px;font-weight:700;color:#00344F;line-height:1.4;margin-bottom:8px}
.opp-card-d{font-size:12px;color:rgba(0,52,79,.55);line-height:1.6;font-weight:300;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
/* Archive page */
.odps{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#E9F2F8}
body.lang-en .odps{direction:ltr}
.odps-body{max-width:1280px;margin:0 auto;padding:60px 48px 80px}
.odps-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:60px}
.odps-card{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,52,79,.06);transition:all .3s;direction:rtl}
body.lang-en .odps-card{direction:ltr}
.odps-card:hover{box-shadow:0 10px 36px rgba(0,52,79,.12);transform:translateY(-4px)}
.odps-card-img{height:220px;overflow:hidden;background:#F0F6FA;position:relative}
.odps-card-img img{width:100%;height:100%;object-fit:contain;background:#fff;display:block;transition:transform .5s;padding:12px}
.odps-card:hover .odps-card-img img{transform:scale(1.06)}
.odps-card-body{padding:20px 22px 22px}
.odps-card-sub{font-size:10px;letter-spacing:2px;color:rgba(0,116,164,.8);text-transform:uppercase;font-weight:700;margin-bottom:6px}
.odps-card-h{font-size:15px;font-weight:700;color:#00344F;line-height:1.35;margin-bottom:8px}
.odps-card-d{font-size:13px;color:rgba(0,52,79,.55);line-height:1.6;font-weight:300;margin-bottom:16px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.odps-qf{background:#00344F;border-radius:12px;padding:52px 56px;margin-top:24px}
.odps-qf-h{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:26px;font-weight:700;color:#fff;margin-bottom:8px}
.odps-qf-h i{font-style:italic;color:#0074A4}
.odps-qf-d{font-size:14px;color:rgba(255,255,255,.5);margin-bottom:32px;font-weight:300}
.odps-qf-row{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:14px;align-items:end}
.odps-qf-field label{font-size:11px;letter-spacing:1.5px;color:rgba(255,255,255,.5);text-transform:uppercase;display:block;margin-bottom:7px}
.odps-qf-field input,.odps-qf-field select{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:6px;padding:12px 14px;font-size:14px;color:#fff;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;outline:none;transition:border-color .2s;min-height:44px}
.odps-qf-field input::placeholder{color:rgba(255,255,255,.3)}
.odps-qf-field input:focus,.odps-qf-field select:focus{border-color:rgba(0,116,164,.5)}
.odps-qf-field select option{background:#00344F;color:#fff}
.odps-qf-submit{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:#0074A4;color:#fff;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:0 28px;height:48px;border:none;border-radius:6px;cursor:pointer;transition:background .2s;white-space:nowrap;min-height:44px}
.odps-qf-submit:hover{background:#0089BE}
/* Detail page */
.opdtl{font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;direction:rtl;background:#E9F2F8}
body.lang-en .opdtl{direction:ltr}
.opdtl-hero{background:#00344F;padding:72px 60px 56px;position:relative;overflow:hidden}
.opdtl-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#0074A4,transparent)}
.opdtl-hero-inner{position:relative;z-index:1;max-width:900px}
.opdtl-breadcrumb{display:flex;align-items:center;gap:8px;font-size:11px;color:rgba(255,255,255,.4);margin-bottom:18px;flex-wrap:wrap}
.opdtl-breadcrumb a{color:rgba(255,255,255,.5);text-decoration:none;transition:color .2s}
.opdtl-breadcrumb a:hover{color:#0074A4}
.opdtl-breadcrumb svg{width:10px;height:10px;stroke:rgba(255,255,255,.25);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;transform:scaleX(-1)}
body.lang-en .opdtl-breadcrumb svg{transform:none}
.opdtl-cat-tag{font-size:10px;letter-spacing:3px;color:#0074A4;text-transform:uppercase;font-weight:700;margin-bottom:12px}
.opdtl-h1{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(28px,4vw,44px);font-weight:700;color:#fff;line-height:1.2;margin-bottom:10px}
.opdtl-h1 i{font-style:italic}
.opdtl-subtitle{font-size:14px;color:rgba(255,255,255,.55);font-weight:300}
.opdtl-body{max-width:1100px;margin:0 auto;padding:60px 48px 80px}
.opdtl-grid{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:start;margin-bottom:60px}
.opdtl-img-wrap{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 12px 48px rgba(0,52,79,.12);position:sticky;top:120px}
.opdtl-img-wrap img{width:100%;max-height:460px;object-fit:contain;display:block;padding:24px;background:#fff}
.opdtl-certs-row{display:flex;flex-wrap:wrap;gap:8px;padding:16px 20px;border-top:1px solid rgba(0,52,79,.06)}
.opdtl-cert{display:inline-flex;align-items:center;gap:5px;background:rgba(0,116,164,.05);border:1px solid rgba(0,116,164,.18);color:#0074A4;font-size:10px;font-weight:700;letter-spacing:1px;padding:4px 11px;border-radius:20px}
.opdtl-cert svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.opdtl-desc{font-size:15px;color:rgba(0,52,79,.68);line-height:1.75;font-weight:300;margin-bottom:32px}
.opdtl-specs-wrap h3{font-size:12px;letter-spacing:2px;color:rgba(0,52,79,.4);text-transform:uppercase;font-weight:700;margin-bottom:14px}
.opdtl-specs-table{width:100%;border-collapse:collapse;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,52,79,.06)}
.opdtl-specs-table td{padding:11px 16px;font-size:13px;border-bottom:1px solid rgba(0,52,79,.06)}
.opdtl-specs-table td:first-child{font-weight:600;color:#00344F;background:#fff;width:44%}
.opdtl-specs-table td:last-child{color:rgba(0,52,79,.7);background:#fafaf8}
.opdtl-specs-table tr:last-child td{border-bottom:none}
.opdtl-cta{background:#00344F;border-radius:12px;padding:40px 36px;margin-top:32px;position:relative;overflow:hidden}
.opdtl-cta::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#0074A4,transparent)}
.opdtl-cta h3{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:20px;font-weight:700;color:#fff;margin-bottom:8px;line-height:1.3}
.opdtl-cta h3 i{font-style:italic;color:#0074A4}
.opdtl-cta p{font-size:13px;color:rgba(255,255,255,.5);line-height:1.65;font-weight:300;margin-bottom:24px}
.opdtl-cta-btns{display:flex;flex-direction:column;gap:10px;align-items:stretch;max-width:360px}
.opdtl-cta-p{display:inline-flex;align-items:center;justify-content:center;gap:7px;background:#0074A4;color:#fff;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:12px 22px;border:none;border-radius:3px;cursor:pointer;transition:background .2s;min-height:42px}
.opdtl-cta-p:hover{background:#0089BE}
.opdtl-cta-s{display:inline-flex;align-items:center;justify-content:center;gap:7px;background:transparent;color:#fff;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:12px 22px;border:1.5px solid rgba(255,255,255,.38);border-radius:3px;cursor:pointer;transition:.2s;min-height:42px;text-decoration:none}
.opdtl-cta-s:hover{background:rgba(255,255,255,.1);border-color:#fff}
.opdtl-cta-s svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.opdtl-related{margin-bottom:20px}
.opdtl-related h2{font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:22px;font-weight:700;color:#00344F;margin-bottom:20px}
.opdtl-related h2 i{font-style:italic;color:#0074A4}
.opdtl-related-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.opdtl-rel-card{background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 3px 14px rgba(0,52,79,.06);transition:all .3s;text-decoration:none;display:block}
.opdtl-rel-card:hover{box-shadow:0 8px 28px rgba(0,52,79,.12);transform:translateY(-3px)}
.opdtl-rel-card-img{height:140px;overflow:hidden;background:#F0F6FA}
.opdtl-rel-card-img img{width:100%;height:100%;object-fit:contain;padding:10px;background:#fff;transition:transform .4s;display:block}
.opdtl-rel-card:hover .opdtl-rel-card-img img{transform:scale(1.06)}
.opdtl-rel-card-body{padding:12px 14px}
.opdtl-rel-card-name{font-size:12px;font-weight:700;color:#00344F;line-height:1.35;margin-bottom:6px}
.opdtl-rel-quote{width:100%;background:#0074A4;color:#fff;border:none;border-radius:4px;padding:7px 10px;font-family:'IBM Plex Sans Arabic','IBM Plex Sans',sans-serif;font-size:11px;font-weight:700;cursor:pointer;transition:background .2s;min-height:32px}
.opdtl-rel-quote:hover{background:#0089BE}
@media(max-width:1024px){.opp-grid{grid-template-columns:repeat(2,1fr)}.opp-body,.odps-body{padding:44px 28px 60px}.opp-hero,.odps-hero,.odp-hero{padding:60px 28px 44px}.odps-grid{grid-template-columns:repeat(2,1fr)}.odps-qf-row{grid-template-columns:1fr 1fr}.opdtl-grid{grid-template-columns:1fr}.opdtl-img-wrap{position:static}.opdtl-body{padding:44px 28px 60px}.opdtl-hero{padding:56px 28px 40px}.opdtl-related-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.opp-grid,.odps-grid{grid-template-columns:1fr}.odps-qf-row{grid-template-columns:1fr}.odps-qf{padding:36px 24px}.opdtl-related-grid{grid-template-columns:1fr 1fr}.opdtl-cta-btns{flex-direction:column}}
</style>
	<?php
}

/**
 * Render the data attributes a quote button needs (shared markup helper).
 */
function osoul_quote_button_attrs( $slug, $p ) {
	printf(
		' data-oql-slug="%s" data-oql-name="%s" data-oql-name-en="%s" data-oql-group-ar="%s" data-oql-group-en="%s" data-oql-img="%s"',
		esc_attr( $slug ),
		esc_attr( $p['name'] ),
		esc_attr( $p['name_en'] ),
		esc_attr( $p['group_ar'] ?? '' ),
		esc_attr( $p['group_en'] ?? '' ),
		esc_url( $p['img'] )
	);
}

/* =========================================================
 * Products portal — /products/
 * ========================================================= */
add_action( 'wp_footer', 'osoul_products_portal', 5 );
if ( ! function_exists( 'osoul_products_portal' ) ) {
	function osoul_products_portal() {
		if ( ! is_page( 'products' ) ) {
			return;
		}
		$groups = osoul_catalog_grouped();
		$labels = array(
			'doors'  => array( 'ar' => 'الأبواب بأنواعها', 'en' => 'All Door Types' ),
			'gypsum' => array( 'ar' => 'بروفايلات الجبسوم بورد', 'en' => 'Gypsum Board Profiles' ),
			'strut'  => array( 'ar' => 'أنظمة التثبيت والتعليق', 'en' => 'Installation & Suspension Systems' ),
		);
		osoul_products_page_css();
		$count = count( osoul_catalog() );
		?>
<div id="opp-wrap" class="opp">
<div class="opp-hero"><div class="opp-hero-inner">
	<div class="opp-tag"><?php osoul_bilingual( 'كتالوج المنتجات', 'Products Catalog' ); ?></div>
	<h1 class="opp-h1"><span data-lang="ar">منتجاتنا <i>الصناعية الكاملة</i></span><span data-lang="en">Our Complete <i>Industrial Products</i></span></h1>
	<p class="opp-sub"><span data-lang="ar"><?php echo (int) $count; ?> منتجاً صناعياً موثقاً في ثلاث فئات رئيسية — أضف ما تريد لقائمة عروضك</span><span data-lang="en"><?php echo (int) $count; ?> documented industrial products in three main categories — add what you want to your quote list</span></p>
</div></div>
<div class="opp-body">
<?php foreach ( $groups as $gk => $gitems ) :
	if ( empty( $gitems ) ) { continue; }
	$gl = $labels[ $gk ]; ?>
<div style="margin-bottom:56px">
	<div style="border-bottom:2px solid rgba(0,52,79,.08);padding-bottom:16px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
		<h2 style="font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:24px;font-weight:700;color:#00344F;line-height:1.2"><?php osoul_bilingual( $gl['ar'], $gl['en'] ); ?></h2>
		<span style="font-size:12px;color:rgba(0,52,79,.4);letter-spacing:1px"><?php echo count( $gitems ); ?> <?php osoul_bilingual( 'منتج', 'products' ); ?></span>
	</div>
	<div class="opp-grid">
		<?php foreach ( $gitems as $slug => $p ) : ?>
		<div class="opp-card">
			<div class="opp-card-img">
				<img src="<?php echo esc_url( $p['img'] ); ?>" alt="<?php echo esc_attr( $p['name'] ); ?>" loading="lazy">
				<?php if ( ! empty( $p['badge'] ) ) : ?><div class="opp-card-badge"><?php osoul_bilingual( $p['badge'], $p['badge_en'] ); ?></div><?php endif; ?>
			</div>
			<div class="opp-card-body">
				<div class="opp-card-cat"><?php osoul_bilingual( $p['group_ar'], $p['group_en'] ); ?></div>
				<div class="opp-card-h"><?php osoul_bilingual( $p['name'], $p['name_en'] ); ?></div>
				<div class="opp-card-d"><?php osoul_bilingual( $p['sub'], $p['sub_en'] ); ?></div>
				<div class="opp-card-actions">
					<button type="button" class="opp-card-quote"<?php osoul_quote_button_attrs( $slug, $p ); ?>><span class="oql-btn-ar">+ عرض</span><span class="oql-btn-en">+ Quote</span></button>
					<a href="<?php echo esc_url( $p['url'] ); ?>" class="opp-card-detail"><?php osoul_bilingual( 'تفاصيل', 'Details' ); ?></a>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</div>
<?php endforeach; ?>
</div>
</div>
<script><?php echo osoul_promote_script( 'opp-wrap' ); ?></script>
		<?php
	}
}

/* =========================================================
 * Archive pages — doors / gypsum / strut
 * ========================================================= */
add_action( 'wp_footer', 'osoul_archive_page_output', 5 );
if ( ! function_exists( 'osoul_archive_page_output' ) ) {
	function osoul_archive_page_output() {
		$pages = array(
			'doors'      => array( 'group' => 'doors', 'h' => 'الأبواب بأنواعها الكاملة', 'h_en' => 'All Door Types', 'sub' => 'الأبواب المعدنية والخشبية والمقاومة للحريق', 'sub_en' => 'Metal, wooden and fire-resistant doors', 'tag' => 'أبوابنا', 'tag_en' => 'Our Doors' ),
			'gypsum' => array( 'group' => 'gypsum', 'h' => 'بروفايلات الجبسوم والسمنت بورد', 'h_en' => 'Gypsum Board & Cement Board Profiles', 'sub' => 'أنظمة بروفايلات متكاملة لأعمال التشطيب', 'sub_en' => 'Integrated profile systems for finishing works', 'tag' => 'أنظمة الجبسوم', 'tag_en' => 'Gypsum Systems' ),
			'strut' => array( 'group' => 'strut', 'h' => 'بروفايلات وإكسسوارات التثبيت والتعليق', 'h_en' => 'Strut Profiles & Suspension Accessories', 'sub' => 'منظومة متكاملة لأعمال الميكانيكا والكهرباء', 'sub_en' => 'Complete system for MEP works', 'tag' => 'أنظمة Strut', 'tag_en' => 'Strut Systems' ),
		);
		$current = '';
		foreach ( array_keys( $pages ) as $pg ) {
			if ( is_page( $pg ) ) { $current = $pg; break; }
		}
		if ( ! $current ) {
			return;
		}
		$pd  = $pages[ $current ];
		$cat = osoul_catalog();
		$group_products = array_filter( $cat, function ( $p ) use ( $pd ) { return $p['group'] === $pd['group']; } );
		osoul_products_page_css();
		?>
<div id="odps-wrap" class="odps">
<div class="odps-hero"><div class="odps-hero-inner">
	<div style="font-size:11px;letter-spacing:5px;color:#0074A4;text-transform:uppercase;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px;position:relative"><span style="width:28px;height:1px;background:#0074A4;display:block"></span><?php osoul_bilingual( $pd['tag'], $pd['tag_en'] ); ?></div>
	<h1 style="font-family:'IBM Plex Sans','IBM Plex Sans Arabic',sans-serif;font-size:clamp(28px,4.5vw,50px);font-weight:700;color:#fff;line-height:1.2;margin-bottom:12px;position:relative"><span data-lang="ar"><i style="font-style:italic;color:#0074A4"><?php echo esc_html( $pd['h'] ); ?></i></span><span data-lang="en"><i style="font-style:italic;color:#0074A4"><?php echo esc_html( $pd['h_en'] ); ?></i></span></h1>
	<p style="font-size:15px;color:rgba(255,255,255,.6);font-weight:300;position:relative"><?php osoul_bilingual( $pd['sub'], $pd['sub_en'] ); ?></p>
</div></div>
<div class="odps-body">
	<div class="odps-grid">
		<?php foreach ( $group_products as $slug => $p ) : ?>
		<div class="odps-card">
			<div class="odps-card-img">
				<img src="<?php echo esc_url( $p['img'] ); ?>" alt="<?php echo esc_attr( $p['name'] ); ?>" loading="lazy">
				<?php if ( ! empty( $p['badge'] ) ) : ?><div class="opp-card-badge"><?php osoul_bilingual( $p['badge'], $p['badge_en'] ); ?></div><?php endif; ?>
			</div>
			<div class="odps-card-body">
				<div class="odps-card-sub"><?php osoul_bilingual( $p['group_ar'], $p['group_en'] ); ?></div>
				<div class="odps-card-h"><?php osoul_bilingual( $p['name'], $p['name_en'] ); ?></div>
				<div class="odps-card-d"><?php osoul_bilingual( $p['sub'], $p['sub_en'] ); ?></div>
				<div class="opp-card-actions">
					<button type="button" class="opp-card-quote"<?php osoul_quote_button_attrs( $slug, $p ); ?>><span class="oql-btn-ar">+ أضف لقائمة العرض</span><span class="oql-btn-en">+ Add to Quote</span></button>
					<a href="<?php echo esc_url( $p['url'] ); ?>" class="opp-card-detail"><?php osoul_bilingual( 'تفاصيل', 'Details' ); ?></a>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</div>
</div>
<script><?php echo osoul_promote_script( 'odps-wrap' ); ?></script>
		<?php
	}
}

/* =========================================================
 * Single product detail
 * ========================================================= */
add_action( 'wp_footer', 'osoul_product_detail_output', 5 );
if ( ! function_exists( 'osoul_product_detail_output' ) ) {
	function osoul_product_detail_output() {
		$catalog = osoul_catalog();
		$current = null;
		foreach ( array_keys( $catalog ) as $s ) {
			if ( is_page( $s ) ) { $current = $s; break; }
		}
		if ( ! $current ) {
			$slug = osoul_request_slug();
			if ( isset( $catalog[ $slug ] ) ) { $current = $slug; }
		}
		if ( ! $current ) {
			return;
		}
		$p    = $catalog[ $current ];
		$home = home_url( '/' );
		$group_urls = array(
			'doors'  => $home . 'doors/',
			'gypsum' => $home . 'gypsum/',
			'strut'  => $home . 'strut/',
		);
		$group_url    = $group_urls[ $p['group'] ] ?? $home . 'products/';
		$products_url = $home . 'products/';

		// Build the spec rows (supports text "label: value" and array formats).
		$specs_html = '';
		if ( ! empty( $p['specs'] ) ) {
			$specs_ar = $p['specs'];
			$specs_en = $p['specs_en'] ?? $specs_ar;
			foreach ( $specs_ar as $i => $spec_ar ) {
				$spec_en = $specs_en[ $i ] ?? $spec_ar;
				if ( is_array( $spec_ar ) ) {
					$label_ar = $spec_ar[0] ?? ''; $label_en = $spec_ar[1] ?? $label_ar;
					$val_ar   = $spec_ar[2] ?? ''; $val_en   = $spec_ar[3] ?? $val_ar;
				} else {
					$pa = explode( ':', $spec_ar, 2 ); $pe = explode( ':', $spec_en, 2 );
					$label_ar = trim( $pa[0] ); $val_ar = isset( $pa[1] ) ? trim( $pa[1] ) : $spec_ar;
					$label_en = trim( $pe[0] ); $val_en = isset( $pe[1] ) ? trim( $pe[1] ) : $spec_en;
				}
				$specs_html .= '<tr><td><span data-lang="ar">' . esc_html( $label_ar ) . '</span><span data-lang="en">' . esc_html( $label_en ) . '</span></td>';
				$specs_html .= '<td><span data-lang="ar">' . esc_html( $val_ar ) . '</span><span data-lang="en">' . esc_html( $val_en ) . '</span></td></tr>';
			}
		}

		// Related products (same group).
		$related = array();
		foreach ( $catalog as $rslug => $rp ) {
			if ( $rp['group'] === $p['group'] && $rslug !== $current ) {
				$related[ $rslug ] = $rp;
				if ( count( $related ) >= 4 ) { break; }
			}
		}
		osoul_products_page_css();
		?>
<div id="opdtl-wrap" class="opdtl">
	<div class="opdtl-hero"><div class="opdtl-hero-inner">
		<div class="opdtl-breadcrumb">
			<a href="<?php echo esc_url( $products_url ); ?>"><?php osoul_bilingual( 'المنتجات', 'Products' ); ?></a>
			<svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
			<a href="<?php echo esc_url( $group_url ); ?>"><?php osoul_bilingual( $p['group_ar'] ?? '', $p['group_en'] ?? '' ); ?></a>
			<svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
			<span><?php osoul_bilingual( $p['name'], $p['name_en'] ); ?></span>
		</div>
		<div class="opdtl-cat-tag"><?php osoul_bilingual( $p['group_ar'] ?? '', $p['group_en'] ?? '' ); ?></div>
		<h1 class="opdtl-h1"><span data-lang="ar"><i><?php echo esc_html( $p['name'] ); ?></i></span><span data-lang="en"><i><?php echo esc_html( $p['name_en'] ); ?></i></span></h1>
		<p class="opdtl-subtitle"><?php osoul_bilingual( $p['sub'] ?? '', $p['sub_en'] ?? '' ); ?></p>
	</div></div>
	<div class="opdtl-body">
		<div class="opdtl-grid">
			<div>
				<div class="opdtl-img-wrap">
					<img src="<?php echo esc_url( $p['img'] ); ?>" alt="<?php echo esc_attr( $p['name'] ); ?>" loading="lazy">
					<?php if ( ! empty( $p['certs'] ) ) : ?>
					<div class="opdtl-certs-row">
						<?php foreach ( $p['certs'] as $cert ) : ?>
						<div class="opdtl-cert"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><?php echo esc_html( $cert ); ?></div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<div>
				<p class="opdtl-desc"><?php osoul_bilingual( $p['desc'] ?? $p['sub'] ?? '', $p['desc_en'] ?? $p['sub_en'] ?? '' ); ?></p>
				<?php if ( $specs_html ) : ?>
				<div class="opdtl-specs-wrap">
					<h3><?php osoul_bilingual( 'المواصفات التقنية', 'Technical Specifications' ); ?></h3>
					<table class="opdtl-specs-table"><tbody><?php echo $specs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from esc_html above ?></tbody></table>
				</div>
				<?php endif; ?>
				<div class="opdtl-cta">
					<h3><span data-lang="ar">هل تريد <i>عرض سعر لهذا المنتج؟</i></span><span data-lang="en">Want a <i>Price Quote for This Product?</i></span></h3>
					<p><?php osoul_bilingual( 'أضفه لقائمة طلب العرض أو تواصل معنا مباشرة', 'Add it to your quote list or contact us directly' ); ?></p>
					<div class="opdtl-cta-btns">
						<button type="button" class="opdtl-cta-p opp-card-quote"<?php osoul_quote_button_attrs( $current, $p ); ?>>
							<svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0"><path d="M9 2L3 7v13a1 1 0 001 1h16a1 1 0 001-1V7l-6-5"/><path d="M16 10a4 4 0 01-8 0"/></svg>
							<span class="oql-btn-ar">+ أضف إلى قائمة العرض</span><span class="oql-btn-en">+ Add to Quote List</span>
						</button>
						<a class="opdtl-cta-s" href="<?php echo esc_url( home_url( '/catalog/?items=' . rawurlencode( $current ) ) ); ?>" target="_blank" rel="noopener">
							<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
							<span class="oql-btn-ar">تحميل بطاقة المنتج PDF</span><span class="oql-btn-en">Download Datasheet PDF</span>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php if ( ! empty( $related ) ) : ?>
		<div class="opdtl-related">
			<h2><span data-lang="ar">منتجات <i>مشابهة</i></span><span data-lang="en"><i>Similar</i> Products</span></h2>
			<div class="opdtl-related-grid">
				<?php foreach ( $related as $rslug => $rp ) : ?>
				<a href="<?php echo esc_url( $rp['url'] ); ?>" class="opdtl-rel-card">
					<div class="opdtl-rel-card-img"><img src="<?php echo esc_url( $rp['img'] ); ?>" alt="<?php echo esc_attr( $rp['name'] ); ?>" loading="lazy"></div>
					<div class="opdtl-rel-card-body">
						<div class="opdtl-rel-card-name"><?php osoul_bilingual( $rp['name'], $rp['name_en'] ); ?></div>
						<button type="button" class="opdtl-rel-quote opp-card-quote"<?php osoul_quote_button_attrs( $rslug, $rp ); ?> onclick="event.preventDefault();event.stopPropagation();"><span class="oql-btn-ar">+ عرض</span><span class="oql-btn-en">+ Quote</span></button>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>
<script><?php echo osoul_promote_script( 'opdtl-wrap' ); ?></script>
		<?php
	}
}
