<?php
/**
 * SEO احترافي: عناوين ووصف لكل صفحة، Open Graph + Twitter Cards، Geo،
 * وبيانات schema.org غنية (مؤسسة تعليمية + WebSite + مسار تنقّل).
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * تحسين عنوان المستند (title) لكل صفحة.
 */
add_filter( 'document_title_separator', function () { return '—'; } );
add_filter( 'document_title_parts', function ( $parts ) {
	if ( is_front_page() ) {
		$parts['title']   = falak_opt( 'school_name' );
		$parts['tagline'] = falak_opt( 'tagline' );
		unset( $parts['site'] );
	} else {
		$parts['site'] = falak_opt( 'school_short' ) ? falak_opt( 'school_short' ) : falak_opt( 'school_name' );
	}
	return $parts;
} );

add_action( 'wp_head', 'falak_seo_head', 2 );
function falak_seo_head() {
	$name    = falak_opt( 'school_name' );
	$tagline = falak_opt( 'tagline' );
	$url     = falak_current_url();
	$title   = wp_get_document_title();
	$desc    = $tagline;

	// وصف حسب الصفحة.
	if ( is_singular() ) {
		$excerpt = get_the_excerpt();
		if ( $excerpt ) {
			$desc = wp_strip_all_tags( $excerpt );
		}
	}
	$desc = mb_substr( trim( $desc ), 0, 160 );
	$logo = falak_opt( 'logo' );

	// أساسيات.
	echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";

	// Geo (جدة — منطقة مكة المكرمة).
	echo '<meta name="geo.region" content="SA-02">' . "\n";
	echo '<meta name="geo.placename" content="جدة">' . "\n";

	// Open Graph.
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( $name ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:locale" content="ar_AR">' . "\n";
	if ( $logo ) {
		echo '<meta property="og:image" content="' . esc_url( $logo ) . '">' . "\n";
		echo '<meta property="og:image:alt" content="' . esc_attr( $name ) . '">' . "\n";
	}

	// Twitter.
	echo '<meta name="twitter:card" content="' . ( $logo ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
	if ( $logo ) {
		echo '<meta name="twitter:image" content="' . esc_url( $logo ) . '">' . "\n";
	}

	// ── schema.org (@graph) ──
	$graph = array();

	// المؤسسة التعليمية.
	$org = array(
		'@type'       => 'EducationalOrganization',
		'@id'         => home_url( '/#organization' ),
		'name'        => $name,
		'url'         => home_url( '/' ),
		'description' => $tagline,
		'inLanguage'  => 'ar',
	);
	if ( $logo ) {
		$org['logo']  = $logo;
		$org['image'] = $logo;
	}
	$phone = falak_opt( 'phone' );
	if ( $phone ) {
		$org['telephone'] = $phone;
	}
	$email = falak_opt( 'email' );
	if ( $email ) {
		$org['email'] = $email;
	}
	$address = falak_opt( 'address' );
	if ( $address ) {
		$org['address'] = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $address,
			'addressLocality' => 'جدة',
			'addressRegion'   => 'منطقة مكة المكرمة',
			'postalCode'      => '23443',
			'addressCountry'  => 'SA',
		);
	}
	// أوقات العمل (السبت–الخميس).
	$org['openingHoursSpecification'] = array(
		'@type'     => 'OpeningHoursSpecification',
		'dayOfWeek' => array( 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday' ),
		'opens'     => '07:00',
		'closes'    => '21:00',
	);
	// روابط التواصل (sameAs).
	$same = array();
	if ( function_exists( 'falak_social_links' ) ) {
		foreach ( falak_social_links() as $s ) {
			$same[] = $s['url'];
		}
	}
	if ( $same ) {
		$org['sameAs'] = array_values( $same );
	}
	$graph[] = $org;

	// WebSite + بحث الموقع.
	$graph[] = array(
		'@type'           => 'WebSite',
		'@id'             => home_url( '/#website' ),
		'url'             => home_url( '/' ),
		'name'            => $name,
		'inLanguage'      => 'ar',
		'publisher'       => array( '@id' => home_url( '/#organization' ) ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);

	// مسار التنقّل (Breadcrumbs) للصفحات الداخلية.
	if ( ! is_front_page() && is_page() ) {
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'الرئيسية', 'item' => home_url( '/' ) ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => wp_strip_all_tags( get_the_title() ), 'item' => $url ),
			),
		);
	}

	$schema = array( '@context' => 'https://schema.org', '@graph' => $graph );
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

/**
 * تعيين لغة الصفحة إلى العربية RTL.
 */
add_filter( 'language_attributes', function () {
	return 'lang="ar" dir="rtl"';
} );
