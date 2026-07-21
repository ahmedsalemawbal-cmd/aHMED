<?php
/**
 * Admin data tools: Excel (CSV) exports + quote archive (soft-delete).
 *
 * Exports stream a UTF-8 CSV with a BOM so Excel renders Arabic correctly, and
 * are gated to a logged-in administrator with a nonce (opened via a plain link).
 *
 * Archiving a quote moves it to WordPress' native "trash" status — it disappears
 * from the live lists but can be restored or permanently deleted from the
 * Archive view. No data is lost until the admin chooses "delete permanently".
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================================
 *  Excel (CSV) exports
 * ====================================================================== */

/** Nonce-signed URL that streams one of the admin data lists as CSV. */
function osoul_admin_export_url( $what ) {
	return add_query_arg(
		array( 'osoul_admin_export' => $what, '_wpnonce' => wp_create_nonce( 'osoul_admin_export' ) ),
		home_url( '/' )
	);
}

/** All export links the admin dashboard offers (handed to the SPA via bootstrap). */
function osoul_admin_export_links() {
	return array(
		'requests'  => osoul_admin_export_url( 'requests' ),
		'customers' => osoul_admin_export_url( 'customers' ),
		'partners'  => osoul_admin_export_url( 'partners' ),
		'accounts'  => osoul_admin_export_url( 'accounts' ),
	);
}

add_action( 'template_redirect', function () {
	if ( empty( $_GET['osoul_admin_export'] ) ) {
		return;
	}
	$what = sanitize_key( wp_unslash( $_GET['osoul_admin_export'] ) );

	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'غير مصرّح.', 'osoul' ), '', array( 'response' => 403 ) );
	}
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'osoul_admin_export' ) ) {
		wp_die( esc_html__( 'رابط غير صالح.', 'osoul' ), '', array( 'response' => 403 ) );
	}

	$builders = array(
		'requests'  => 'osoul_admin_export_requests',
		'customers' => 'osoul_admin_export_customers',
		'partners'  => 'osoul_admin_export_partners',
		'accounts'  => 'osoul_admin_export_accounts',
	);
	if ( empty( $builders[ $what ] ) ) {
		wp_die( esc_html__( 'نوع تصدير غير معروف.', 'osoul' ), '', array( 'response' => 400 ) );
	}
	list( $header, $rows ) = call_user_func( $builders[ $what ] );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="osoul-' . $what . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
	echo "\xEF\xBB\xBF"; // UTF-8 BOM — makes Excel show Arabic correctly.
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, $header );
	foreach ( $rows as $r ) {
		fputcsv( $out, $r );
	}
	fclose( $out );
	exit;
} );

/** Requests / quotes (respects the Osoul privacy scope — partner white-label quotes excluded). */
function osoul_admin_export_requests() {
	$user  = wp_get_current_user();
	$args  = array(
		'post_type'      => 'osoul_quote',
		'post_status'    => 'publish',
		'posts_per_page' => 2000,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);
	$scope = function_exists( 'osoul_quote_scope_meta_query' ) ? osoul_quote_scope_meta_query( $user ) : null;
	if ( $scope ) {
		$args['meta_query'] = array( 'relation' => 'AND', $scope[0] );
	}
	$q    = new WP_Query( $args );
	$rows = array();
	foreach ( $q->posts as $post ) {
		$s      = osoul_quote_summary( $post, 'admin' );
		$rows[] = array(
			$s['number'],
			$s['customer'],
			$s['phone'],
			$s['source_label'],
			(int) $s['items'],
			null === $s['total'] ? '' : $s['total'],
			$s['stage_label'],
			$s['date'],
		);
	}
	return array(
		array( 'رقم العرض', 'العميل', 'الجوال', 'المصدر', 'عدد المنتجات', 'الإجمالي (ر.س)', 'الحالة', 'التاريخ' ),
		$rows,
	);
}

/** Registered website customers. */
function osoul_admin_export_customers() {
	$list = function_exists( 'osoul_customers_list' ) ? osoul_customers_list() : array();
	$rows = array();
	foreach ( $list as $c ) {
		$rows[] = array( $c['name'], $c['company'], $c['email'], $c['phone'], (int) $c['quotes'], $c['source'], $c['joined'] );
	}
	return array(
		array( 'العميل', 'الشركة', 'البريد الإلكتروني', 'الجوال', 'عدد الطلبات', 'عبر', 'تاريخ التسجيل' ),
		$rows,
	);
}

/** Partners (resellers). */
function osoul_admin_export_partners() {
	$users = get_users( array( 'role' => 'osoul_partner', 'orderby' => 'registered', 'order' => 'DESC' ) );
	$rows  = array();
	foreach ( $users as $u ) {
		$approved = osoul_partner_is_approved( $u->ID );
		$pending  = (bool) get_user_meta( $u->ID, '_osoul_invite', true );
		$active   = osoul_portal_is_active( $u->ID );
		$status   = ! $approved ? 'بانتظار الاعتماد' : ( $pending ? 'لم يُفعّل بعد' : ( $active ? 'نشط' : 'موقوف' ) );
		$rows[]   = array(
			osoul_partner_get( $u->ID, 'company_ar' ) ?: $u->display_name,
			$u->user_email,
			osoul_partner_get( $u->ID, 'phone' ),
			osoul_partner_get( $u->ID, 'vat' ),
			$status,
		);
	}
	return array(
		array( 'الشركة', 'البريد الإلكتروني', 'الجوال', 'الرقم الضريبي', 'الحالة' ),
		$rows,
	);
}

/** Branches + sales reps. */
function osoul_admin_export_accounts() {
	$rows = array();
	foreach ( get_users( array( 'role' => 'osoul_branch', 'fields' => array( 'ID' ), 'orderby' => 'display_name', 'order' => 'ASC' ) ) as $u ) {
		$rec = osoul_account_record( (int) $u->ID, 'branch' );
		if ( $rec ) {
			$rows[] = array( 'فرع', $rec['name'], $rec['email'], $rec['phone'], $rec['city'] ?? '', osoul_admin_account_status( $rec ) );
		}
	}
	foreach ( get_users( array( 'role' => 'osoul_rep', 'fields' => array( 'ID' ), 'orderby' => 'display_name', 'order' => 'ASC' ) ) as $u ) {
		$rec = osoul_account_record( (int) $u->ID, 'rep' );
		if ( $rec ) {
			$rows[] = array( 'مندوب', $rec['name'], $rec['email'], $rec['phone'], $rec['branch'] ?? '', osoul_admin_account_status( $rec ) );
		}
	}
	return array(
		array( 'النوع', 'الاسم', 'البريد الإلكتروني', 'الجوال', 'الفرع / المدينة', 'الحالة' ),
		$rows,
	);
}

/** Arabic status label for a branch/rep account record. */
function osoul_admin_account_status( $rec ) {
	if ( ! empty( $rec['pending'] ) ) { return 'بانتظار التفعيل'; }
	return ! empty( $rec['active'] ) ? 'نشط' : 'موقوف';
}

/* =========================================================================
 *  Quote archive (soft-delete via WordPress trash)
 * ====================================================================== */

/** Confirm the id is a real quote the admin may manage; returns the post or a WP_Error. */
function osoul_admin_quote_or_error( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'osoul_quote' !== $post->post_type ) {
		return new WP_Error( 'osoul_not_found', 'غير موجود.', array( 'status' => 404 ) );
	}
	return $post;
}

/** POST /portal/quote-archive — move a quote to the archive (trash). */
function osoul_rest_quote_archive( WP_REST_Request $req ) {
	$post = osoul_admin_quote_or_error( (int) $req->get_param( 'id' ) );
	if ( is_wp_error( $post ) ) { return $post; }
	wp_trash_post( $post->ID );
	return rest_ensure_response( array( 'ok' => true ) );
}

/** POST /portal/quote-restore — bring an archived quote back to the live list. */
function osoul_rest_quote_restore( WP_REST_Request $req ) {
	$post = osoul_admin_quote_or_error( (int) $req->get_param( 'id' ) );
	if ( is_wp_error( $post ) ) { return $post; }
	wp_untrash_post( $post->ID );
	// Untrash restores the previous status; make sure it lands back as published.
	if ( 'publish' !== get_post_status( $post->ID ) ) {
		wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish' ) );
	}
	return rest_ensure_response( array( 'ok' => true ) );
}

/** POST /portal/quote-delete — permanently delete an archived quote. */
function osoul_rest_quote_delete( WP_REST_Request $req ) {
	$post = osoul_admin_quote_or_error( (int) $req->get_param( 'id' ) );
	if ( is_wp_error( $post ) ) { return $post; }
	wp_delete_post( $post->ID, true );
	return rest_ensure_response( array( 'ok' => true ) );
}

/* =========================================================================
 *  Delete a registered website customer (removes the account)
 * ====================================================================== */

/** POST /portal/customer-delete — permanently delete a website-customer account. */
function osoul_rest_customer_delete( WP_REST_Request $req ) {
	$id = (int) $req->get_param( 'id' );
	$u  = get_user_by( 'id', $id );
	// Only ever delete accounts that are actually website customers — never staff/partners.
	if ( ! $u || ! in_array( 'osoul_customer', (array) $u->roles, true ) ) {
		return new WP_Error( 'osoul_not_found', 'عميل غير موجود.', array( 'status' => 404 ) );
	}
	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}
	wp_delete_user( $id );
	return rest_ensure_response( array( 'ok' => true ) );
}

/** Register the archive + customer-delete endpoints (admin only). */
add_action( 'rest_api_init', function () {
	$ns   = 'osoul/v1';
	$perm = function_exists( 'osoul_portal_perm_admin' ) ? 'osoul_portal_perm_admin' : '__return_false';
	foreach ( array(
		'quote-archive'   => 'osoul_rest_quote_archive',
		'quote-restore'   => 'osoul_rest_quote_restore',
		'quote-delete'    => 'osoul_rest_quote_delete',
		'customer-delete' => 'osoul_rest_customer_delete',
	) as $route => $cb ) {
		register_rest_route( $ns, '/portal/' . $route, array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => $cb,
			'permission_callback' => $perm,
		) );
	}
} );
