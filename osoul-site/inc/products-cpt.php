<?php
/**
 * Product storage: a custom post type that backs the admin Products dashboard,
 * a builder that turns it into the catalog array the templates already consume,
 * and a one-time seeder that imports the built-in defaults so the existing 28
 * products become editable.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Group keys → bilingual labels (must match the values used in catalog defaults).
 *
 * @return array<string,array{ar:string,en:string}>
 */
function osoul_group_labels() {
	return array(
		'doors'  => array( 'ar' => 'الأبواب', 'en' => 'Doors' ),
		'gypsum' => array( 'ar' => 'الجبسوم بورد', 'en' => 'Gypsum Board' ),
		'strut'  => array( 'ar' => 'أنظمة التثبيت والتعليق', 'en' => 'Installation Systems' ),
	);
}

/**
 * Register the (admin-only) product post type. Front-end URLs stay as /slug/
 * via the existing virtual-page renderer, so this type is not publicly queryable.
 */
add_action( 'init', function () {
	register_post_type( 'osoul_product', array(
		'labels'             => array(
			'name'          => __( 'Products', 'osoul' ),
			'singular_name' => __( 'Product', 'osoul' ),
		),
		'public'             => false,
		'show_ui'            => false,
		'show_in_menu'       => false,
		'publicly_queryable' => false,
		'exclude_from_search'=> true,
		'rewrite'            => false,
		'has_archive'        => false,
		'supports'           => array( 'title', 'thumbnail', 'page-attributes' ),
	) );
} );

/**
 * The list of meta fields stored per product (without the `_osoul_` prefix).
 *
 * @return string[]
 */
function osoul_product_meta_fields() {
	return array(
		'group', 'name_ar', 'name_en', 'sub_ar', 'sub_en',
		'desc_ar', 'desc_en', 'badge_ar', 'badge_en', 'img',
		'specs_ar', 'specs_en', 'certs', 'price',
	);
}

/**
 * Save a product's meta from a catalog-style array (used by the seeder).
 *
 * @param int   $id
 * @param array $p
 */
function osoul_seed_product_meta( $id, $p ) {
	update_post_meta( $id, '_osoul_group', $p['group'] ?? 'doors' );
	update_post_meta( $id, '_osoul_name_ar', $p['name'] ?? '' );
	update_post_meta( $id, '_osoul_name_en', $p['name_en'] ?? '' );
	update_post_meta( $id, '_osoul_sub_ar', $p['sub'] ?? '' );
	update_post_meta( $id, '_osoul_sub_en', $p['sub_en'] ?? '' );
	update_post_meta( $id, '_osoul_desc_ar', $p['desc'] ?? '' );
	update_post_meta( $id, '_osoul_desc_en', $p['desc_en'] ?? '' );
	update_post_meta( $id, '_osoul_badge_ar', $p['badge'] ?? '' );
	update_post_meta( $id, '_osoul_badge_en', $p['badge_en'] ?? '' );
	update_post_meta( $id, '_osoul_img', $p['img'] ?? '' );
	update_post_meta( $id, '_osoul_specs_ar', ( isset( $p['specs'] ) && is_array( $p['specs'] ) ) ? implode( "\n", $p['specs'] ) : '' );
	update_post_meta( $id, '_osoul_specs_en', ( isset( $p['specs_en'] ) && is_array( $p['specs_en'] ) ) ? implode( "\n", $p['specs_en'] ) : '' );
	update_post_meta( $id, '_osoul_certs', ( isset( $p['certs'] ) && is_array( $p['certs'] ) ) ? implode( ', ', $p['certs'] ) : '' );
}

/**
 * One-time import of the built-in defaults into editable product posts.
 */
add_action( 'init', 'osoul_seed_products', 20 );
if ( ! function_exists( 'osoul_seed_products' ) ) {
	function osoul_seed_products() {
		if ( get_option( 'osoul_products_seeded' ) ) {
			return;
		}
		if ( ! function_exists( 'osoul_catalog_defaults' ) ) {
			return;
		}
		$i = 0;
		foreach ( osoul_catalog_defaults() as $slug => $p ) {
			// Don't duplicate if a product with this slug already exists.
			if ( get_page_by_path( $slug, OBJECT, 'osoul_product' ) ) {
				continue;
			}
			$id = wp_insert_post( array(
				'post_type'   => 'osoul_product',
				'post_status' => 'publish',
				'post_title'  => $p['name'] ?? $slug,
				'post_name'   => $slug,
				'menu_order'  => $i,
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				osoul_seed_product_meta( $id, $p );
			}
			$i++;
		}
		update_option( 'osoul_products_seeded', 1 );
	}
}

/**
 * Build the catalog array from the product posts.
 *
 * @return array<string,array>
 */
function osoul_products_from_cpt() {
	$labels = osoul_group_labels();
	$home   = home_url( '/' );

	$q = new WP_Query( array(
		'post_type'      => 'osoul_product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
		'no_found_rows'  => true,
	) );

	$out = array();
	foreach ( $q->posts as $post ) {
		$slug = $post->post_name;
		if ( ! $slug ) {
			continue;
		}
		$group = get_post_meta( $post->ID, '_osoul_group', true );
		$group = isset( $labels[ $group ] ) ? $group : 'doors';
		$gl    = $labels[ $group ];

		$img = get_post_meta( $post->ID, '_osoul_img', true );
		if ( ! $img ) {
			$thumb = get_the_post_thumbnail_url( $post->ID, 'full' );
			if ( $thumb ) {
				$img = $thumb;
			}
		}

		$lines = function ( $key, $sep ) use ( $post ) {
			$raw = (string) get_post_meta( $post->ID, $key, true );
			return array_values( array_filter( array_map( 'trim', explode( $sep, $raw ) ) ) );
		};

		$out[ $slug ] = array(
			'group'    => $group,
			'group_ar' => $gl['ar'],
			'group_en' => $gl['en'],
			'name'     => get_post_meta( $post->ID, '_osoul_name_ar', true ) ?: $post->post_title,
			'name_en'  => get_post_meta( $post->ID, '_osoul_name_en', true ),
			'sub'      => get_post_meta( $post->ID, '_osoul_sub_ar', true ),
			'sub_en'   => get_post_meta( $post->ID, '_osoul_sub_en', true ),
			'desc'     => get_post_meta( $post->ID, '_osoul_desc_ar', true ),
			'desc_en'  => get_post_meta( $post->ID, '_osoul_desc_en', true ),
			'badge'    => get_post_meta( $post->ID, '_osoul_badge_ar', true ),
			'badge_en' => get_post_meta( $post->ID, '_osoul_badge_en', true ),
			'img'      => $img,
			'price'    => (float) get_post_meta( $post->ID, '_osoul_price', true ),
			'url'      => $home . $slug . '/',
			'specs'    => $lines( '_osoul_specs_ar', "\n" ),
			'specs_en' => $lines( '_osoul_specs_en', "\n" ),
			'certs'    => $lines( '_osoul_certs', ',' ),
		);
	}
	return $out;
}

/**
 * The live catalog the templates use.
 *
 * Reads from the editable product posts once seeded; falls back to the built-in
 * defaults before that (e.g. the very first request after activation).
 *
 * @return array<string,array>
 */
if ( ! function_exists( 'osoul_get_all_products_catalog' ) ) {
	function osoul_get_all_products_catalog() {
		if ( get_option( 'osoul_products_seeded' ) ) {
			$cpt = osoul_products_from_cpt();
			if ( ! empty( $cpt ) ) {
				return $cpt;
			}
		}
		return function_exists( 'osoul_catalog_defaults' ) ? osoul_catalog_defaults() : array();
	}
}
