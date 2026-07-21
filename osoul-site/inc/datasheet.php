<?php
/**
 * White-label product catalog / datasheet (print-to-PDF).
 *
 * Renders a branded, print-ready catalog at /catalog/ — a single product
 * datasheet or a selected set — under either Osoul's identity (public) or a
 * PARTNER's identity (logo, colours, contact), reusing the same brand system
 * as the white-label quotes. Partners generate a signed link from their
 * dashboard; the HMAC signature stops anyone forging another company's brand.
 *
 * URL params:
 *   items=slug,slug   selected products (else the whole catalog)
 *   group=doors       one product group (public/Osoul only)
 *   lang=ar|en        language
 *   p=<id>&sig=<hmac> partner branding (signature required)
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Signature binding a partner id + product selection (anti-forgery).
 *
 * Language is intentionally NOT signed: it is a pure display toggle, so a single
 * signed link stays valid across both Arabic and English. (Binding language here
 * used to break the on-page language switch — the toggle kept the old signature,
 * which failed re-verification and silently dropped the partner brand back to
 * Osoul's on the other language.)
 */
function osoul_catalog_sig( $partner_id, $items_csv ) {
	return substr( hash_hmac( 'sha256', (int) $partner_id . '|' . $items_csv, wp_salt( 'auth' ) ), 0, 24 );
}

/** Build the shareable/printable catalog URL for a partner's selection. */
function osoul_catalog_url( $partner_id, $items, $lang ) {
	$items = array_values( array_filter( array_map( 'sanitize_title', (array) $items ) ) );
	$csv   = implode( ',', $items );
	$lang  = ( 'en' === $lang ) ? 'en' : 'ar';
	return add_query_arg( array(
		'items' => $csv,
		'lang'  => $lang,
		'p'     => (int) $partner_id,
		'sig'   => osoul_catalog_sig( $partner_id, $csv ),
	), home_url( '/catalog/' ) );
}

/** Resolve the brand (partner or Osoul) for the catalog document. */
function osoul_catalog_brand( $partner_id ) {
	$hex = function ( $v, $d ) { return ( is_string( $v ) && preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v ) ) ? $v : $d; };
	if ( $partner_id && function_exists( 'osoul_partner_brand_snapshot' ) ) {
		$b = osoul_partner_brand_snapshot( $partner_id );
		return array(
			'company_ar' => $b['company_ar'] ?: $b['company_en'],
			'company_en' => $b['company_en'] ?: $b['company_ar'],
			'logo'       => $b['logo'],
			'phone'      => $b['phone'],
			'email'      => $b['email'],
			'address_ar' => $b['address_ar'],
			'address_en' => $b['address_en'] ?: $b['address_ar'],
			'color'      => $hex( $b['color'], '#0d1f3c' ),
			'accent'     => $hex( $b['accent'], '#D21217' ),
		);
	}
	return array(
		'company_ar' => osoul_opt( 'company_name_ar' ),
		'company_en' => osoul_opt( 'company_name_en' ),
		'logo'       => osoul_opt( 'logo_url' ),
		'phone'      => osoul_opt( 'phone_primary' ),
		'email'      => osoul_opt( 'email' ),
		'address_ar' => osoul_opt( 'national_address_ar' ) ?: osoul_opt( 'address_ar' ),
		'address_en' => osoul_opt( 'national_address_en' ) ?: osoul_opt( 'address_en' ),
		'color'      => '#0d1f3c',
		'accent'     => '#D21217',
	);
}

/**
 * Intercept /catalog/ and render the branded catalog document.
 */
add_action( 'template_redirect', function () {
	if ( 'catalog' !== osoul_request_slug() ) {
		return;
	}
	nocache_headers();
	status_header( 200 );

	// Language: an explicit ?lang wins; otherwise follow the visitor's saved
	// site language (osoul_lang cookie) so a product-page PDF matches the page.
	if ( isset( $_GET['lang'] ) ) {
		$lang = ( 'en' === $_GET['lang'] ) ? 'en' : 'ar';
	} else {
		$lang = ( isset( $_COOKIE['osoul_lang'] ) && 'en' === $_COOKIE['osoul_lang'] ) ? 'en' : 'ar';
	}
	$p    = isset( $_GET['p'] ) ? (int) $_GET['p'] : 0;
	$sig  = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

	$items = array();
	if ( isset( $_GET['items'] ) && '' !== $_GET['items'] ) {
		$items = array_values( array_filter( array_map( 'sanitize_title', explode( ',', wp_unslash( $_GET['items'] ) ) ) ) );
	}
	$items_csv = implode( ',', $items );
	$group     = isset( $_GET['group'] ) ? sanitize_key( wp_unslash( $_GET['group'] ) ) : '';

	// Partner branding requires a valid signature for exactly this selection.
	$brand_pid = 0;
	if ( $p > 0 && $sig
		&& hash_equals( osoul_catalog_sig( $p, $items_csv ), $sig )
		&& 'partner' === osoul_portal_role( $p ) ) {
		$brand_pid = $p;
	}

	$catalog  = function_exists( 'osoul_catalog' ) ? osoul_catalog() : array();
	$products = array();
	if ( $items ) {
		foreach ( $items as $slug ) {
			if ( isset( $catalog[ $slug ] ) ) { $products[ $slug ] = $catalog[ $slug ]; }
		}
	} elseif ( $group ) {
		foreach ( $catalog as $slug => $prod ) {
			if ( ( $prod['group'] ?? '' ) === $group ) { $products[ $slug ] = $prod; }
		}
	} else {
		$products = $catalog;
	}

	osoul_catalog_render( $products, osoul_catalog_brand( $brand_pid ), $lang );
	exit;
}, 0 );

/**
 * Render the branded, print-ready catalog HTML.
 *
 * @param array  $products slug => product data
 * @param array  $brand    resolved brand (company, logo, colours, contact)
 * @param string $lang     'ar'|'en'
 */
function osoul_catalog_render( $products, $brand, $lang ) {
	$ar      = ( 'ar' === $lang );
	$t       = function ( $a, $e ) use ( $ar ) { return $ar ? $a : $e; };
	$dir     = $ar ? 'rtl' : 'ltr';
	$company = $ar ? $brand['company_ar'] : $brand['company_en'];
	$address = $ar ? $brand['address_ar'] : $brand['address_en'];
	$labels  = function_exists( 'osoul_group_labels' ) ? osoul_group_labels() : array();
	$single  = ( 1 === count( $products ) );

	// Group the products for section headings.
	$grouped = array();
	foreach ( $products as $slug => $p ) {
		$g = $p['group'] ?? 'other';
		$grouped[ $g ][ $slug ] = $p;
	}
	$switch_lang = add_query_arg( 'lang', $ar ? 'en' : 'ar' );
	?><!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $dir ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( ( $single ? reset( $products )['name'] : $t( 'كتالوج المنتجات', 'Product Catalog' ) ) . ' — ' . $company ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=IBM+Plex+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--n:<?php echo $brand['color']; ?>;--o:<?php echo $brand['accent']; ?>;--line:#e3e7ee;--muted:#6b7280}
body{font-family:<?php echo $ar ? "'Cairo'" : "'IBM Plex Sans','Cairo'"; ?>,sans-serif;background:#eef1f5;color:#1f2937;direction:<?php echo esc_attr( $dir ); ?>;padding:20px;line-height:1.6;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.bar{max-width:820px;margin:0 auto 14px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:7px;border:none;border-radius:8px;padding:11px 18px;font-family:inherit;font-size:14px;font-weight:800;cursor:pointer;text-decoration:none;color:#fff;background:var(--o)}
.lang a{color:var(--n);text-decoration:none;font-weight:700;font-size:13px;padding:6px 11px;border:1px solid var(--line);border-radius:6px;background:#fff}
.sheet{max-width:820px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:8px;padding:34px 38px;box-shadow:0 6px 24px rgba(13,31,60,.08)}
.head{display:flex;justify-content:space-between;align-items:center;gap:20px;border-bottom:3px solid var(--n);padding-bottom:16px;margin-bottom:8px}
.head img{height:64px;width:auto}
.co{font-size:12.5px;color:var(--muted);text-align:<?php echo $ar ? 'left' : 'right'; ?>}
.co b{color:var(--n);font-size:16px;display:block;margin-bottom:3px}
.title{text-align:center;font-size:23px;font-weight:800;color:var(--n);letter-spacing:.5px;margin:18px 0 22px}
.title span{color:var(--o)}
.grp{color:var(--o);font-size:13px;font-weight:800;letter-spacing:1px;text-transform:uppercase;margin:22px 0 12px;border-bottom:1px solid var(--line);padding-bottom:6px}
.card{display:flex;gap:18px;border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:14px;page-break-inside:avoid;break-inside:avoid}
.card .img{width:150px;min-width:150px;height:150px;border-radius:8px;object-fit:cover;background:#f2f4f7;border:1px solid var(--line);display:block}
.card .body{flex:1;min-width:0}
.card h3{font-size:17px;color:var(--n);font-weight:800;margin-bottom:2px}
.badge{display:inline-block;background:var(--o);color:#fff;font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:12px;margin-inline-start:6px;vertical-align:middle}
.card .sub{color:var(--muted);font-size:13px;margin-bottom:8px}
.card .desc{font-size:13px;color:#374151;margin-bottom:10px}
.specs{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:4px 16px}
.specs li{font-size:12.5px;color:#374151;padding-inline-start:16px;position:relative}
.specs li:before{content:'✓';position:absolute;inset-inline-start:0;color:var(--o);font-weight:800}
.certs{margin-top:10px;display:flex;flex-wrap:wrap;gap:6px}
.certs span{font-size:11px;font-weight:700;color:var(--n);background:#eef2f8;border:1px solid var(--line);border-radius:20px;padding:3px 10px}
.foot{margin-top:26px;border-top:1px solid var(--line);padding-top:16px;text-align:center;font-size:13px;color:#374151}
.foot b{color:var(--n)}
.cta{display:inline-block;margin-top:8px;background:var(--n);color:#fff;font-weight:800;font-size:13px;padding:9px 18px;border-radius:8px}
@media print{body{background:#fff;padding:0}.bar{display:none!important}.sheet{box-shadow:none;border:none;border-radius:0;max-width:none;padding:0}.card{border-color:#ddd}}
@media(max-width:560px){.sheet{padding:20px 16px}.card{flex-direction:column}.card .img{width:100%;height:180px}.specs{grid-template-columns:1fr}.head{flex-direction:column;text-align:center}.co{text-align:center}}
</style>
</head>
<body>
	<div class="bar">
		<button class="btn" onclick="window.print()"><?php echo esc_html( $t( 'تحميل / طباعة PDF', 'Download / Print PDF' ) ); ?></button>
		<div class="lang"><a href="<?php echo esc_url( $switch_lang ); ?>"><?php echo esc_html( $t( 'English', 'عربي' ) ); ?></a></div>
	</div>
	<div class="sheet">
		<div class="head">
			<?php if ( $brand['logo'] ) : ?><img src="<?php echo esc_url( $brand['logo'] ); ?>" alt=""><?php endif; ?>
			<div class="co"><b><?php echo esc_html( $company ); ?></b>
				<?php if ( $address ) : ?><?php echo esc_html( $address ); ?><br><?php endif; ?>
				<?php if ( $brand['phone'] ) : ?><?php echo esc_html( $brand['phone'] ); ?><?php endif; ?>
				<?php if ( $brand['email'] ) : ?> · <?php echo esc_html( $brand['email'] ); ?><?php endif; ?>
			</div>
		</div>
		<div class="title"><?php echo $single ? esc_html( $t( 'بطاقة منتج', 'Product Datasheet' ) ) : ( esc_html( $t( 'كتالوج ', 'PRODUCT ' ) ) . '<span>' . esc_html( $t( 'المنتجات', 'CATALOG' ) ) . '</span>' ); ?></div>

		<?php if ( ! $products ) : ?>
			<p style="text-align:center;color:var(--muted)"><?php echo esc_html( $t( 'لا توجد منتجات.', 'No products.' ) ); ?></p>
		<?php endif; ?>

		<?php foreach ( $grouped as $g => $items ) : ?>
			<?php if ( ! $single ) : ?><div class="grp"><?php echo esc_html( $labels[ $g ]['ar'] ?? $g ); if ( ! $ar && isset( $labels[ $g ]['en'] ) ) { echo ' — ' . esc_html( $labels[ $g ]['en'] ); } ?></div><?php endif; ?>
			<?php foreach ( $items as $slug => $p ) :
				$name  = $ar ? ( $p['name'] ?? '' ) : ( $p['name_en'] ?: ( $p['name'] ?? '' ) );
				$sub   = $ar ? ( $p['sub'] ?? '' ) : ( $p['sub_en'] ?? '' );
				$desc  = $ar ? ( $p['desc'] ?? '' ) : ( $p['desc_en'] ?? '' );
				$badge = $ar ? ( $p['badge'] ?? '' ) : ( $p['badge_en'] ?? '' );
				// osoul_catalog() returns specs/certs as arrays; tolerate strings too.
				$specs_raw = $ar ? ( $p['specs'] ?? array() ) : ( $p['specs_en'] ?? array() );
				$specs     = array_filter( array_map( 'trim', is_array( $specs_raw ) ? $specs_raw : explode( "\n", (string) $specs_raw ) ) );
				$certs_raw = $p['certs'] ?? array();
				$certs     = array_filter( array_map( 'trim', is_array( $certs_raw ) ? $certs_raw : explode( ',', (string) $certs_raw ) ) );
				?>
				<div class="card">
					<?php if ( ! empty( $p['img'] ) ) : ?><img class="img" src="<?php echo esc_url( $p['img'] ); ?>" alt="" loading="eager"><?php endif; ?>
					<div class="body">
						<h3><?php echo esc_html( $name ); ?><?php if ( $badge ) : ?><span class="badge"><?php echo esc_html( $badge ); ?></span><?php endif; ?></h3>
						<?php if ( $sub ) : ?><div class="sub"><?php echo esc_html( $sub ); ?></div><?php endif; ?>
						<?php if ( $desc ) : ?><div class="desc"><?php echo esc_html( $desc ); ?></div><?php endif; ?>
						<?php if ( $specs ) : ?><ul class="specs"><?php foreach ( $specs as $s ) : ?><li><?php echo esc_html( $s ); ?></li><?php endforeach; ?></ul><?php endif; ?>
						<?php if ( $certs ) : ?><div class="certs"><?php foreach ( $certs as $c ) : ?><span><?php echo esc_html( $c ); ?></span><?php endforeach; ?></div><?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endforeach; ?>

		<div class="foot">
			<?php echo esc_html( $t( 'لطلب عرض سعر أو الاستفسار، تواصل معنا:', 'For a quotation or enquiry, contact us:' ) ); ?>
			<b><?php echo esc_html( $brand['phone'] ); ?></b><?php if ( $brand['email'] ) : ?> · <b><?php echo esc_html( $brand['email'] ); ?></b><?php endif; ?>
		</div>
	</div>
</body>
</html>
	<?php
}
