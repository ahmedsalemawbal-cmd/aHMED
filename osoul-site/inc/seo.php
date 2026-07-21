<?php
/**
 * SEO: meta description, Open Graph, canonical, hreflang, schema.org.
 *
 * Security note: the legacy version echoed raw $_SERVER['HTTP_HOST'] and
 * REQUEST_URI into <link rel="canonical"> and og:url. That is attacker
 * controllable (Host-header injection → cache poisoning / SEO hijack). Every
 * URL here is rebuilt from home_url() via osoul_current_url().
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Document titles (only when no SEO plugin is active).
 */
add_action( 'init', function () {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
		return;
	}
	add_filter( 'pre_get_document_title', 'osoul_document_title', 99 );
} );

if ( ! function_exists( 'osoul_document_title' ) ) {
	function osoul_document_title( $title ) {
		global $post;
		$titles = array(
			'about-us'            => array( 'ar' => 'من نحن | أصول البناء للصناعة', 'en' => 'About Us | Osoul Albinaa Industrial Co.' ),
			'products'            => array( 'ar' => 'كتالوج المنتجات | أصول البناء للصناعة', 'en' => 'Products Catalog | Osoul Albinaa Industrial Co.' ),
			'doors'      => array( 'ar' => 'الأبواب بأنواعها | أصول البناء للصناعة', 'en' => 'All Door Types | Osoul Albinaa Industrial Co.' ),
			'gypsum' => array( 'ar' => 'بروفايلات الجبسوم | أصول البناء للصناعة', 'en' => 'Gypsum Board Profiles | Osoul Albinaa Industrial Co.' ),
			'strut' => array( 'ar' => 'أنظمة Strut | أصول البناء للصناعة', 'en' => 'Strut Fixing Systems | Osoul Albinaa Industrial Co.' ),
			'services'   => array( 'ar' => 'خدماتنا | أصول البناء للصناعة', 'en' => 'Our Services | Osoul Albinaa Industrial Co.' ),
			'projects'    => array( 'ar' => 'مشاريعنا | أصول البناء للصناعة', 'en' => 'Our Projects | Osoul Albinaa Industrial Co.' ),
			'blog'        => array( 'ar' => 'المدونة | أصول البناء للصناعة', 'en' => 'Blog | Osoul Albinaa Industrial Co.' ),
			'contact'             => array( 'ar' => 'تواصل معنا | أصول البناء للصناعة', 'en' => 'Contact Us | Osoul Albinaa Industrial Co.' ),
		);
		$lang = osoul_current_lang();
		if ( is_front_page() ) {
			return ( 'en' === $lang )
				? 'Osoul Albinaa Industrial Co. | Metal Doors & Gypsum Systems, Jeddah'
				: 'أصول البناء للصناعة | أبواب معدنية وجبسوم بورد، جدة';
		}
		$slug = $post ? $post->post_name : '';
		if ( isset( $titles[ $slug ] ) ) {
			return ( 'en' === $lang ) ? $titles[ $slug ]['en'] : $titles[ $slug ]['ar'];
		}
		return $title;
	}
}

/**
 * Meta description, Open Graph, robots, geo + schema.org.
 */
add_action( 'wp_head', 'osoul_seo_meta', 2 );
if ( ! function_exists( 'osoul_seo_meta' ) ) {
	function osoul_seo_meta() {
		global $post;
		$home = home_url( '/' );
		$logo = 'https://osoulalbinaa.com/wp-content/uploads/2026/06/تصميم-بدون-عنوان-20.png';

		$descs = array(
			''                    => 'أصول البناء للصناعة — شركة سعودية متخصصة في تصنيع الأبواب المعدنية المقاومة للحريق وأنظمة الجبسوم بورد وإكسسوارات التثبيت في جدة.',
			'about-us'            => 'تعرف على شركة أصول البناء للصناعة في جدة: +20 سنة خبرة، +500 مشروع منجز، منتجات معتمدة ISO وSASO.',
			'products'            => 'كتالوج منتجات أصول البناء: أبواب معدنية مقاومة للحريق، بروفايلات جبسوم بورد، إكسسوارات Strut.',
			'doors'      => 'الأبواب المعدنية المقاومة للحريق، الأبواب المجوفة، MDF، WPC وأبواب تكسوية — من أصول البناء للصناعة في جدة.',
			'gypsum' => 'بروفايلات الجبسوم بورد والسمنت بورد المجلفنة: C-Stud، U-Track، Hat Channel، Main Channel — من أصول البناء.',
			'strut' => 'أنظمة Strut للتثبيت والتعليق وكامل الإكسسوارات من أصول البناء.',
			'services'   => 'خدمات توريد وتركيب وضمان وصيانة وفحص فني — حلول صناعية متكاملة من أصول البناء للصناعة في جدة.',
			'projects'    => 'مشاريع أصول البناء: +500 مشروع في 15 مدينة سعودية للقطاعين الحكومي والخاص.',
			'blog'        => 'مدونة أصول البناء الصناعية: مقالات ومستجدات في قطاع البناء والتشطيب والأبواب وأنظمة الجبسوم.',
			'contact'             => 'تواصل مع أصول البناء للصناعة في جدة — +966 563 627 063 | info@osoulalbinaa.com',
		);

		$slug = $post ? $post->post_name : '';
		if ( is_front_page() ) {
			$slug = '';
		}
		$desc        = isset( $descs[ $slug ] ) ? $descs[ $slug ] : $descs[''];
		$og_img      = get_the_post_thumbnail_url( $post ? $post->ID : 0, 'full' );
		$og_img      = $og_img ? $og_img : $logo;
		$current_url = osoul_current_url();

		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		echo '<meta property="og:image" content="' . esc_url( $og_img ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $current_url ) . '">' . "\n";
		echo '<meta property="og:type" content="' . ( is_single() ? 'article' : 'website' ) . '">' . "\n";
		echo '<meta property="og:locale" content="ar_SA">' . "\n";
		echo '<meta property="og:locale:alternate" content="en_US">' . "\n";
		echo '<meta property="og:site_name" content="أصول البناء للصناعة">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		echo '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n";
		echo '<meta name="geo.region" content="SA-02">' . "\n";
		echo '<meta name="geo.placename" content="Jeddah, Saudi Arabia">' . "\n";

		// hreflang — the AR and EN content live on the same URL (JS-switched),
		// so we self-reference both locales + x-default. This was missing before.
		echo '<link rel="alternate" hreflang="ar" href="' . esc_url( $current_url ) . '">' . "\n";
		echo '<link rel="alternate" hreflang="en" href="' . esc_url( $current_url ) . '">' . "\n";
		echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $current_url ) . '">' . "\n";

		// schema.org
		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type'        => 'Organization',
					'@id'          => $home . '#org',
					'name'         => 'أصول البناء للصناعة',
					'alternateName'=> 'Osoul Albinaa Industrial Co.',
					'url'          => $home,
					'logo'         => array( '@type' => 'ImageObject', 'url' => $logo ),
					'contactPoint' => array(
						'@type'             => 'ContactPoint',
						'telephone'         => '+966563627063',
						'contactType'       => 'customer service',
						'areaServed'        => 'SA',
						'availableLanguage' => array( 'Arabic', 'English' ),
					),
					'address'      => array(
						'@type'           => 'PostalAddress',
						'addressCountry'  => 'SA',
						'addressRegion'   => 'Jeddah',
						'addressLocality' => '3rd Industrial City',
					),
					'sameAs'       => array( 'https://wa.me/966556847029' ),
					'foundingDate' => '2004',
				),
				array(
					'@type'     => 'WebSite',
					'@id'       => $home . '#site',
					'url'       => $home,
					'name'      => 'أصول البناء للصناعة',
					'publisher' => array( '@id' => $home . '#org' ),
					'inLanguage'=> array( 'ar', 'en' ),
				),
			),
		);

		if ( is_front_page() ) {
			$schema['@graph'][] = array(
				'@type'     => 'LocalBusiness',
				'@id'       => $home . '#business',
				'name'      => 'أصول البناء للصناعة',
				'image'     => $logo,
				'url'       => $home,
				'telephone' => '+966563627063',
				'email'     => 'info@osoulalbinaa.com',
				'priceRange'=> '$$',
				'address'   => array( '@type' => 'PostalAddress', 'addressCountry' => 'SA', 'addressRegion' => 'Jeddah' ),
				'openingHoursSpecification' => array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array( 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday' ),
					'opens'     => '08:00',
					'closes'    => '17:00',
				),
			);
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}

/**
 * Canonical URL (only when no SEO plugin is active) — hardened.
 */
add_action( 'wp_head', function () {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
		return;
	}
	echo '<link rel="canonical" href="' . esc_url( osoul_current_url() ) . '">' . "\n";
}, 3 );
