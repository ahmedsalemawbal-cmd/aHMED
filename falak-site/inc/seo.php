<?php
/**
 * SEO خفيف: وصف، canonical، Open Graph، وبيانات schema.org للمؤسسة التعليمية.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_head', 'falak_seo_head', 2 );
function falak_seo_head() {
	$name    = falak_opt( 'school_name' );
	$tagline = falak_opt( 'tagline' );
	$url     = falak_current_url();
	$desc    = $tagline;

	// وصف حسب الصفحة.
	if ( is_singular() ) {
		$excerpt = get_the_excerpt();
		if ( $excerpt ) {
			$desc = wp_strip_all_tags( $excerpt );
		}
	}
	$desc = mb_substr( trim( $desc ), 0, 160 );

	echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";

	// Open Graph.
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( $name ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( wp_get_document_title() ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:locale" content="ar_SA">' . "\n";
	$logo = falak_opt( 'logo' );
	if ( $logo ) {
		echo '<meta property="og:image" content="' . esc_url( $logo ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary">' . "\n";

	// schema.org — مؤسسة تعليمية.
	$schema = array(
		'@context' => 'https://schema.org',
		'@type'    => 'EducationalOrganization',
		'name'     => $name,
		'url'      => home_url( '/' ),
		'description' => $tagline,
	);
	$phone = falak_opt( 'phone' );
	if ( $phone ) {
		$schema['telephone'] = $phone;
	}
	$email = falak_opt( 'email' );
	if ( $email ) {
		$schema['email'] = $email;
	}
	$address = falak_opt( 'address' );
	if ( $address ) {
		$schema['address'] = array(
			'@type'          => 'PostalAddress',
			'addressCountry' => 'SA',
			'streetAddress'  => $address,
		);
	}
	if ( $logo ) {
		$schema['logo'] = $logo;
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

/**
 * تعيين لغة الصفحة إلى العربية RTL.
 */
add_filter( 'language_attributes', function ( $output ) {
	return 'lang="ar" dir="rtl"';
} );
