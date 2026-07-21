<?php
/**
 * Partner Portal — data layer (white-label reseller accounts).
 *
 * A "partner" is an external reseller (contractor / real-estate company, …)
 * that buys Osoul products at a partner (cost) price and re-sells them to its
 * OWN customers under its OWN brand. Two linked records implement a strict
 * privacy wall:
 *
 *   1. SUPPLY order   (_osoul_kind='supply'): the partner requests products;
 *      Osoul sees it as a normal quote whose "customer" is the partner company,
 *      prices the COST, and issues it to the partner. It holds NO end-customer
 *      data.
 *   2. CUSTOMER quote (_osoul_kind='cust'): the partner sets its SELL price per
 *      line, enters its end-customer's details, and issues a white-label
 *      quotation under its OWN identity. Osoul can NEVER see this record — it is
 *      excluded from every Osoul query (see osoul_quote_exclude_cust_clause in
 *      portal-roles.php). That is how "Osoul may not see partners' customers" is
 *      enforced structurally, not merely hidden in the UI.
 *
 * Identity, per-partner numbering and the minimum-margin floor live in per-user
 * meta (prefixed `_osoul_p_`). No custom tables.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 *  Partner identity profile (user-meta)
 * ---------------------------------------------------------------------- */

/** Identity field keys => default value. */
function osoul_partner_fields() {
	return array(
		'company_ar' => '',
		'company_en' => '',
		'logo'       => '',
		'vat'        => '',
		'cr'         => '',
		'address_ar' => '',
		'address_en' => '',
		'bank'       => '',
		'phone'      => '',
		'email'      => '',
		'terms_ar'   => '',
		'terms_en'   => '',
		'color'      => '#00344F',
		'accent'     => '#0074A4',
		'prefix'     => '',
		'margin_min' => '0',
	);
}

/** Validate a #RGB / #RRGGBB colour, else return the fallback. */
function osoul_partner_sanitize_hex( $c, $default = '' ) {
	$c = is_string( $c ) ? trim( $c ) : '';
	return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $c ) ? $c : $default;
}

/** Read one identity field (falls back to its default). */
function osoul_partner_get( $partner_id, $key ) {
	$v = get_user_meta( (int) $partner_id, '_osoul_p_' . $key, true );
	if ( '' === $v || null === $v ) {
		$defs = osoul_partner_fields();
		return $defs[ $key ] ?? '';
	}
	return $v;
}

/** The whole identity profile as an array. */
function osoul_partner_profile( $partner_id ) {
	$out = array();
	foreach ( array_keys( osoul_partner_fields() ) as $k ) {
		$out[ $k ] = osoul_partner_get( $partner_id, $k );
	}
	return $out;
}

/** Sanitise + persist a subset of identity fields. */
function osoul_partner_save_profile( $partner_id, $data ) {
	$partner_id = (int) $partner_id;
	$url_keys   = array( 'logo' );
	$area_keys  = array( 'bank', 'terms_ar', 'terms_en' );
	foreach ( osoul_partner_fields() as $k => $def ) {
		if ( ! array_key_exists( $k, $data ) ) {
			continue;
		}
		$val = wp_unslash( $data[ $k ] );
		if ( in_array( $k, $url_keys, true ) ) {
			$val = esc_url_raw( $val );
		} elseif ( in_array( $k, $area_keys, true ) ) {
			$val = sanitize_textarea_field( $val );
		} elseif ( 'email' === $k ) {
			$val = sanitize_email( $val );
		} elseif ( 'margin_min' === $k ) {
			$val = (string) max( 0, (float) $val );
		} elseif ( in_array( $k, array( 'color', 'accent' ), true ) ) {
			$val = osoul_partner_sanitize_hex( $val, (string) $def );
		} elseif ( 'prefix' === $k ) {
			$val = substr( strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $val ) ), 0, 8 );
		} else {
			$val = sanitize_text_field( $val );
		}
		update_user_meta( $partner_id, '_osoul_p_' . $k, $val );
	}
}

/** True once the admin has approved the partner. */
function osoul_partner_is_approved( $partner_id ) {
	return (bool) get_user_meta( (int) $partner_id, '_osoul_p_approved', true );
}

/* -------------------------------------------------------------------------
 *  Per-partner quote numbering + brand snapshot
 * ---------------------------------------------------------------------- */

/**
 * Next sequential quote number for a partner, using its own prefix + counter,
 * e.g. SHK-2026-0001. Independent of Osoul's global counter so partner quotes
 * never carry Osoul's "OSB-" numbers and never interleave.
 */
function osoul_partner_next_number( $partner_id ) {
	$partner_id = (int) $partner_id;
	$prefix     = osoul_partner_get( $partner_id, 'prefix' );
	if ( '' === $prefix ) {
		$prefix = 'PRT' . $partner_id;
	}
	$year = gmdate( 'Y' );
	$key  = '_osoul_p_counter_' . $year;

	// Per-year reset with a one-time carryover from the partner's old flat
	// counter, so a mid-year switch never reuses one of their numbers.
	if ( '' === get_user_meta( $partner_id, $key, true ) && ! get_user_meta( $partner_id, '_osoul_p_counter_migrated', true ) ) {
		add_user_meta( $partner_id, $key, (int) get_user_meta( $partner_id, '_osoul_p_counter', true ), true );
		update_user_meta( $partner_id, '_osoul_p_counter_migrated', 1 );
	}

	$n = osoul_atomic_usermeta_increment( $partner_id, $key );
	return sprintf( '%s-%s-%04d', $prefix, $year, $n );
}

/**
 * Immutable copy of the partner's identity, stamped onto a customer quote at
 * issue time so historical quotes keep their branding even if the partner later
 * edits its profile.
 */
function osoul_partner_brand_snapshot( $partner_id ) {
	$p = osoul_partner_profile( $partner_id );
	return array(
		'company_ar' => $p['company_ar'],
		'company_en' => $p['company_en'] ?: $p['company_ar'],
		'logo'       => $p['logo'],
		'vat'        => $p['vat'],
		'cr'         => $p['cr'],
		'address_ar' => $p['address_ar'],
		'address_en' => $p['address_en'] ?: $p['address_ar'],
		'bank'       => $p['bank'],
		'phone'      => $p['phone'],
		'email'      => $p['email'],
		'color'      => osoul_partner_sanitize_hex( $p['color'], '#00344F' ),
		'accent'     => osoul_partner_sanitize_hex( $p['accent'], '#0074A4' ),
	);
}

/** The white-label brand stamped on a quote, or null for an ordinary Osoul quote. */
function osoul_quote_brand( $quote_id ) {
	$b = get_post_meta( (int) $quote_id, '_osoul_brand', true );
	return ( is_array( $b ) && ! empty( $b ) ) ? $b : null;
}

/** Partner profit across a set of customer-quote line items (private to the partner). */
function osoul_partner_quote_profit( $items ) {
	$profit = 0;
	foreach ( (array) $items as $it ) {
		$profit += ( (float) ( $it['unit_price'] ?? 0 ) - (float) ( $it['cost'] ?? 0 ) ) * (int) ( $it['qty'] ?? 0 );
	}
	return round( $profit, 2 );
}

/* -------------------------------------------------------------------------
 *  Registration + approval
 * ---------------------------------------------------------------------- */

/**
 * Register a pending partner (blocked until the admin approves).
 *
 * @param array $data company_ar, company_en, email, phone, vat, cr, bank, address_ar
 * @return int|WP_Error new user id
 */
function osoul_partner_register( $data ) {
	$company_ar = sanitize_text_field( $data['company_ar'] ?? '' );
	$company_en = sanitize_text_field( $data['company_en'] ?? '' );
	$email      = sanitize_email( $data['email'] ?? '' );
	$phone      = sanitize_text_field( $data['phone'] ?? '' );

	if ( '' === $company_ar && '' === $company_en ) {
		return new WP_Error( 'osoul_p_no_company', 'اسم الشركة مطلوب.' );
	}
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'osoul_p_bad_email', 'بريد إلكتروني غير صحيح.' );
	}
	if ( email_exists( $email ) || username_exists( $email ) ) {
		return new WP_Error( 'osoul_p_dupe', 'هذا البريد مسجّل مسبقاً.' );
	}

	$display = $company_ar ?: $company_en;
	$user_id = wp_insert_user( array(
		'user_login'   => $email,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 40, true, true ),
		'display_name' => $display,
		'nickname'     => $display,
		'role'         => 'osoul_partner',
	) );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	update_user_meta( $user_id, '_osoul_phone', $phone );
	update_user_meta( $user_id, '_osoul_active', 0 );     // blocked until approved
	update_user_meta( $user_id, '_osoul_p_approved', 0 );
	update_user_meta( $user_id, '_osoul_created', current_time( 'mysql' ) );
	osoul_partner_save_profile( $user_id, array(
		'company_ar' => $company_ar,
		'company_en' => $company_en,
		'vat'        => $data['vat'] ?? '',
		'cr'         => $data['cr'] ?? '',
		'bank'       => $data['bank'] ?? '',
		'address_ar' => $data['address_ar'] ?? '',
		'phone'      => $phone,
		'email'      => $email,
	) );

	osoul_partner_notify_admin_new( $user_id );
	return $user_id;
}

/**
 * Approve a partner: activate the account, ensure a numbering prefix, and issue
 * a one-time invite link so they can set their password.
 *
 * @return string|WP_Error invite URL
 */
function osoul_partner_approve( $partner_id ) {
	$partner_id = (int) $partner_id;
	if ( 'partner' !== osoul_portal_role( $partner_id ) ) {
		return new WP_Error( 'osoul_p_bad', 'حساب غير صالح.' );
	}
	update_user_meta( $partner_id, '_osoul_p_approved', 1 );
	update_user_meta( $partner_id, '_osoul_active', 1 );

	if ( '' === osoul_partner_get( $partner_id, 'prefix' ) ) {
		$base = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', osoul_partner_get( $partner_id, 'company_en' ) ) );
		update_user_meta( $partner_id, '_osoul_p_prefix', $base ? substr( $base, 0, 6 ) : ( 'PRT' . $partner_id ) );
	}

	$token = osoul_portal_new_invite( $partner_id );
	$url   = osoul_portal_invite_url( $token );
	osoul_partner_notify_invite( $partner_id, $url );
	return $url;
}

/* -------------------------------------------------------------------------
 *  Records: supply order (Osoul-visible) + customer quote (partner-only)
 * ---------------------------------------------------------------------- */

/**
 * Create a SUPPLY order: the partner requests products; Osoul will price the
 * cost. Contains no end-customer data — its "customer" is the partner company.
 *
 * @return int|WP_Error quote id
 */
function osoul_partner_create_supply( $partner_id, $items, $note = '' ) {
	$partner_id = (int) $partner_id;
	$items      = osoul_sanitize_quote_items( $items );
	if ( empty( $items ) ) {
		return new WP_Error( 'osoul_p_no_items', 'أضف منتجاً واحداً على الأقل.' );
	}

	$catalog = osoul_catalog();
	foreach ( $items as &$it ) {
		if ( isset( $catalog[ $it['slug'] ] ) ) {
			$it['name']    = $catalog[ $it['slug'] ]['name'] ?? $it['name'];
			$it['name_en'] = $catalog[ $it['slug'] ]['name_en'] ?? $it['name_en'];
		}
		$it['unit_price'] = 0.0; // Osoul sets the cost later.
	}
	unset( $it );

	$company  = osoul_partner_get( $partner_id, 'company_ar' ) ?: osoul_partner_get( $partner_id, 'company_en' );
	$quote_id = wp_insert_post( array(
		'post_type'   => 'osoul_quote',
		'post_status' => 'publish',
		'post_title'  => sprintf( 'توريد — %s', $company ),
	), true );
	if ( is_wp_error( $quote_id ) ) {
		return new WP_Error( 'osoul_p_store', 'تعذّر الحفظ.' );
	}

	update_post_meta( $quote_id, '_osoul_kind', 'supply' );
	update_post_meta( $quote_id, '_osoul_items', $items );
	update_post_meta( $quote_id, '_osoul_stage', 'new' );
	update_post_meta( $quote_id, '_osoul_discount', 0 );
	update_post_meta( $quote_id, '_osoul_token', osoul_quote_token() );
	update_post_meta( $quote_id, '_osoul_lang', 'ar' );
	update_post_meta( $quote_id, '_osoul_note', sanitize_textarea_field( (string) $note ) );
	update_post_meta( $quote_id, '_osoul_customer', array(
		'name'  => $company,
		'phone' => osoul_partner_get( $partner_id, 'phone' ),
		'email' => osoul_partner_get( $partner_id, 'email' ),
	) );
	osoul_quote_set_attribution( $quote_id, get_user_by( 'id', $partner_id ) );

	if ( function_exists( 'osoul_quote_log' ) ) {
		osoul_quote_log( $quote_id, 'طلب توريد جديد من الشريك' );
	}
	if ( function_exists( 'osoul_notify_new_quote' ) ) {
		osoul_notify_new_quote( $quote_id, $company, osoul_partner_get( $partner_id, 'phone' ), osoul_partner_get( $partner_id, 'email' ), $items );
	}
	return $quote_id;
}

/**
 * Create a CUSTOMER quote from a priced supply order: partner sets sell prices,
 * enters the end-customer's details, and a white-label quotation is issued
 * immediately under the partner's identity. Never visible to Osoul.
 *
 * @param array $customer name, phone, email
 * @param array $sell     line index => sell unit price
 * @param array $opts     discount, validity, terms_ar, terms_en
 * @return array|WP_Error { id, url }
 */
function osoul_partner_create_customer_quote( $partner_id, $supply_id, $customer, $sell, $opts = array() ) {
	$partner_id = (int) $partner_id;
	$supply_id  = (int) $supply_id;
	$supply     = get_post( $supply_id );

	if ( ! $supply || 'osoul_quote' !== $supply->post_type
		|| (int) get_post_meta( $supply_id, '_osoul_partner_id', true ) !== $partner_id
		|| 'supply' !== get_post_meta( $supply_id, '_osoul_kind', true ) ) {
		return new WP_Error( 'osoul_p_badsupply', 'طلب التوريد غير موجود.' );
	}
	if ( ! osoul_quote_is_issued( $supply_id ) ) {
		return new WP_Error( 'osoul_p_unpriced', 'لم تُسعّر أصول هذا الطلب بعد.' );
	}

	$name  = sanitize_text_field( $customer['name'] ?? '' );
	$phone = sanitize_text_field( $customer['phone'] ?? '' );
	$email = sanitize_email( $customer['email'] ?? '' );
	if ( '' === $name || ( '' === $phone && '' === $email ) ) {
		return new WP_Error( 'osoul_p_cust', 'اسم العميل ورقم الجوال (أو البريد) مطلوبان.' );
	}

	$cost_items = (array) get_post_meta( $supply_id, '_osoul_items', true );
	$margin_min = (float) osoul_partner_get( $partner_id, 'margin_min' );
	$items      = array();
	foreach ( $cost_items as $i => $it ) {
		$cost  = (float) ( $it['unit_price'] ?? 0 );
		$s     = isset( $sell[ $i ] ) ? max( 0, (float) $sell[ $i ] ) : $cost;
		$floor = $cost * ( 1 + $margin_min / 100 );
		if ( $s + 0.001 < $floor ) {
			return new WP_Error( 'osoul_p_margin', sprintf( 'سعر بيع البند %d أقل من الحد الأدنى المسموح.', (int) $i + 1 ) );
		}
		$items[] = array(
			'slug'       => $it['slug'] ?? '',
			'name'       => $it['name'] ?? '',
			'name_en'    => $it['name_en'] ?? ( $it['name'] ?? '' ),
			'qty'        => (int) ( $it['qty'] ?? 1 ),
			'unit_price' => $s,     // SELL price — what the public quote shows.
			'cost'       => $cost,  // partner's cost — private, never rendered.
		);
	}
	if ( empty( $items ) ) {
		return new WP_Error( 'osoul_p_no_items', 'لا توجد بنود في هذا الطلب.' );
	}

	$quote_id = wp_insert_post( array(
		'post_type'   => 'osoul_quote',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s — %s', $name, $phone ?: $email ),
	), true );
	if ( is_wp_error( $quote_id ) ) {
		return new WP_Error( 'osoul_p_store', 'تعذّر الحفظ.' );
	}

	$discount = max( 0, (float) ( $opts['discount'] ?? 0 ) );
	$validity = max( 1, (int) ( $opts['validity'] ?? osoul_opt( 'validity_days' ) ) );

	update_post_meta( $quote_id, '_osoul_kind', 'cust' );
	update_post_meta( $quote_id, '_osoul_partner_id', $partner_id );
	update_post_meta( $quote_id, '_osoul_supply_id', $supply_id );
	update_post_meta( $quote_id, '_osoul_source', 'partner' );
	update_post_meta( $quote_id, '_osoul_items', $items );
	update_post_meta( $quote_id, '_osoul_customer', array( 'name' => $name, 'phone' => $phone, 'email' => $email ) );
	update_post_meta( $quote_id, '_osoul_discount', $discount );
	update_post_meta( $quote_id, '_osoul_validity', $validity );
	update_post_meta( $quote_id, '_osoul_stage', 'sent' );
	update_post_meta( $quote_id, '_osoul_number', osoul_partner_next_number( $partner_id ) );
	update_post_meta( $quote_id, '_osoul_token', osoul_quote_token() );
	update_post_meta( $quote_id, '_osoul_issued', current_time( 'mysql' ) );
	update_post_meta( $quote_id, '_osoul_lang', 'ar' );
	update_post_meta( $quote_id, '_osoul_brand', osoul_partner_brand_snapshot( $partner_id ) );
	update_post_meta( $quote_id, '_osoul_terms_ar', sanitize_textarea_field( (string) ( $opts['terms_ar'] ?? osoul_partner_get( $partner_id, 'terms_ar' ) ) ) );
	update_post_meta( $quote_id, '_osoul_terms_en', sanitize_textarea_field( (string) ( $opts['terms_en'] ?? osoul_partner_get( $partner_id, 'terms_en' ) ) ) );

	return array( 'id' => $quote_id, 'url' => home_url( '/quote/' . get_post_meta( $quote_id, '_osoul_token', true ) . '/' ) );
}

/** A partner's supply orders (their wholesale requests to Osoul). */
function osoul_partner_supply_orders( $partner_id ) {
	$q = new WP_Query( array(
		'post_type'      => 'osoul_quote',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => '_osoul_partner_id', 'value' => (int) $partner_id ),
			array( 'key' => '_osoul_kind', 'value' => 'supply' ),
		),
	) );
	return $q->posts;
}

/** A partner's customer quotes (private to the partner). */
function osoul_partner_customer_quotes( $partner_id ) {
	$q = new WP_Query( array(
		'post_type'      => 'osoul_quote',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => '_osoul_partner_id', 'value' => (int) $partner_id ),
			array( 'key' => '_osoul_kind', 'value' => 'cust' ),
		),
	) );
	return $q->posts;
}

/**
 * A partner's UNIQUE customers, aggregated from their private customer quotes.
 *
 * Repeat customers collapse into one row (keyed by phone, then email, then name)
 * carrying their quote count, total value and last-quote date. This stays fully
 * behind the privacy wall — it is built only from the partner's own 'cust'
 * quotes, which Osoul's queries never see.
 *
 * @return array<int,array<string,mixed>>
 */
function osoul_partner_customers( $partner_id ) {
	$rows = array();
	foreach ( osoul_partner_customer_quotes( $partner_id ) as $qp ) {
		$c     = (array) get_post_meta( $qp->ID, '_osoul_customer', true );
		$name  = trim( (string) ( $c['name'] ?? '' ) );
		$phone = trim( (string) ( $c['phone'] ?? '' ) );
		$email = trim( (string) ( $c['email'] ?? '' ) );
		if ( '' === $name && '' === $phone && '' === $email ) {
			continue;
		}
		$key = ( '' !== $phone ) ? 'p:' . $phone
			: ( ( '' !== $email ) ? 'e:' . strtolower( $email ) : 'n:' . $name );
		if ( ! isset( $rows[ $key ] ) ) {
			$rows[ $key ] = array( 'name' => $name, 'phone' => $phone, 'email' => $email, 'quotes' => 0, 'total' => 0.0, 'last' => '' );
		}
		if ( '' === $rows[ $key ]['name'] )  { $rows[ $key ]['name'] = $name; }
		if ( '' === $rows[ $key ]['email'] ) { $rows[ $key ]['email'] = $email; }
		$items = (array) get_post_meta( $qp->ID, '_osoul_items', true );
		$t     = osoul_quote_totals( $items, (float) get_post_meta( $qp->ID, '_osoul_discount', true ) );
		$rows[ $key ]['quotes'] += 1;
		$rows[ $key ]['total']  += (float) $t['total'];
		$date = get_the_date( 'Y-m-d', $qp );
		if ( $date > $rows[ $key ]['last'] ) { $rows[ $key ]['last'] = $date; }
	}
	return array_values( $rows );
}

/** Nonce-signed URL that streams the current partner's customers as CSV (opens in Excel). */
function osoul_partner_export_url() {
	return add_query_arg(
		array( 'osoul_export' => 'customers', '_wpnonce' => wp_create_nonce( 'osoul_p_export' ) ),
		home_url( '/' )
	);
}

/**
 * Stream a partner's customer list as a UTF-8 CSV (with BOM so Excel renders
 * Arabic correctly). Guarded to the logged-in partner's OWN customers only.
 */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['osoul_export'] ) || 'customers' !== $_GET['osoul_export'] ) {
		return;
	}
	if ( ! is_user_logged_in() || 'partner' !== osoul_portal_role() ) {
		wp_die( esc_html__( 'غير مصرّح.', 'osoul' ), '', array( 'response' => 403 ) );
	}
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'osoul_p_export' ) ) {
		wp_die( esc_html__( 'رابط غير صالح.', 'osoul' ), '', array( 'response' => 403 ) );
	}

	$rows = osoul_partner_customers( get_current_user_id() );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="my-customers-' . gmdate( 'Y-m-d' ) . '.csv"' );
	echo "\xEF\xBB\xBF"; // UTF-8 BOM — makes Excel show Arabic correctly.
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'الاسم', 'الجوال', 'البريد الإلكتروني', 'عدد العروض', 'الإجمالي (ر.س)', 'آخر عرض' ) );
	foreach ( $rows as $c ) {
		fputcsv( $out, array( $c['name'], $c['phone'], $c['email'], (int) $c['quotes'], number_format( (float) $c['total'], 2, '.', '' ), $c['last'] ) );
	}
	fclose( $out );
	exit;
} );

/* -------------------------------------------------------------------------
 *  Notifications
 * ---------------------------------------------------------------------- */

/** Email the site admin when a new partner registers. */
function osoul_partner_notify_admin_new( $partner_id ) {
	$to      = apply_filters( 'osoul_lead_notify_email', get_option( 'admin_email' ) );
	$company = osoul_partner_get( $partner_id, 'company_ar' ) ?: osoul_partner_get( $partner_id, 'company_en' );
	$subject = 'طلب انضمام شريك جديد: ' . $company;
	$body    = "تقدّم شريك جديد بطلب انضمام إلى بوابة الشركاء.\n\n"
		. 'الشركة: ' . $company . "\n"
		. 'البريد: ' . osoul_partner_get( $partner_id, 'email' ) . "\n"
		. 'الجوال: ' . osoul_partner_get( $partner_id, 'phone' ) . "\n"
		. 'الرقم الضريبي: ' . osoul_partner_get( $partner_id, 'vat' ) . "\n"
		. 'السجل التجاري: ' . osoul_partner_get( $partner_id, 'cr' ) . "\n\n"
		. 'لاعتماد الطلب: لوحة تحكم ووردبريس ← الشركاء.';
	wp_mail( $to, $subject, $body );
}

/** Email the partner their activation (invite) link on approval. */
function osoul_partner_notify_invite( $partner_id, $url ) {
	$u = get_user_by( 'id', $partner_id );
	if ( ! $u ) {
		return;
	}
	$subject = 'تم اعتماد شراكتك مع أصول البناء';
	$body    = "مرحباً،\n\nتم اعتماد حساب شراكتك مع أصول البناء للصناعة. فعّل حسابك واختر كلمة المرور من الرابط التالي:\n\n"
		. $url . "\n\n(الرابط صالح لمدة 7 أيام.)";
	wp_mail( $u->user_email, $subject, $body );
}

/* -------------------------------------------------------------------------
 *  REST callbacks for the admin dashboard "الشركاء" section
 *  (routes registered in portal-rest.php; both are admin-only)
 * ---------------------------------------------------------------------- */

/** GET /portal/partners — list every partner account. */
function osoul_rest_partners( WP_REST_Request $req ) {
	$users = get_users( array( 'role' => 'osoul_partner', 'orderby' => 'registered', 'order' => 'DESC' ) );
	$out   = array();
	foreach ( $users as $u ) {
		$invite = get_user_meta( $u->ID, '_osoul_invite', true );
		$out[]  = array(
			'id'       => (int) $u->ID,
			'name'     => osoul_partner_get( $u->ID, 'company_ar' ) ?: $u->display_name,
			'email'    => $u->user_email,
			'phone'    => osoul_partner_get( $u->ID, 'phone' ),
			'vat'      => osoul_partner_get( $u->ID, 'vat' ),
			'approved' => osoul_partner_is_approved( $u->ID ),
			'active'   => osoul_portal_is_active( $u->ID ),
			'pending'  => (bool) $invite,
			'invite'   => $invite ? osoul_portal_invite_url( $invite ) : '',
		);
	}
	return rest_ensure_response( array( 'partners' => $out ) );
}

/** POST /portal/partner-approve — approve a pending partner + return the invite link. */
function osoul_rest_partner_approve( WP_REST_Request $req ) {
	$id = (int) $req->get_param( 'id' );
	if ( 'partner' !== osoul_portal_role( $id ) ) {
		return new WP_Error( 'osoul_bad', 'حساب غير صالح.', array( 'status' => 400 ) );
	}
	$url = osoul_partner_approve( $id );
	if ( is_wp_error( $url ) ) {
		return new WP_Error( $url->get_error_code(), $url->get_error_message(), array( 'status' => 400 ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'invite_url' => $url ) );
}

/* -------------------------------------------------------------------------
 *  Partner workspace REST callbacks (SPA; perm_partner, own data only)
 * ---------------------------------------------------------------------- */

/** GET /portal/p-supplies — the partner's supply orders (their requests to Osoul). */
function osoul_rest_p_supplies( WP_REST_Request $req ) {
	$pid    = get_current_user_id();
	$stages = osoul_quote_stages();
	$out    = array();
	foreach ( osoul_partner_supply_orders( $pid ) as $sp ) {
		$items  = (array) get_post_meta( $sp->ID, '_osoul_items', true );
		$issued = osoul_quote_is_issued( $sp->ID );
		$stage  = $issued ? 'sent' : ( get_post_meta( $sp->ID, '_osoul_stage', true ) ?: 'new' );
		$st     = $stages[ $stage ] ?? $stages['new'];
		$cost   = null;
		if ( $issued ) {
			$t    = osoul_quote_totals( $items, 0 );
			$cost = osoul_money( $t['total'] );
		}
		$out[] = array(
			'id'          => $sp->ID,
			'date'        => get_the_date( 'Y-m-d', $sp ),
			'items'       => count( $items ),
			'issued'      => $issued,
			'cost'        => $cost,
			'stage'       => $stage,
			'stage_label' => $st['ar'],
			'stage_color' => $st['color'],
		);
	}
	return rest_ensure_response( array( 'supplies' => $out ) );
}

/** GET /portal/p-supply/{id} — one supply order + its cost lines (once priced). */
function osoul_rest_p_supply( WP_REST_Request $req ) {
	$pid = get_current_user_id();
	$id  = (int) $req['id'];
	if ( (int) get_post_meta( $id, '_osoul_partner_id', true ) !== $pid
		|| 'supply' !== get_post_meta( $id, '_osoul_kind', true ) ) {
		return new WP_Error( 'osoul_forbidden', 'غير مصرح.', array( 'status' => 403 ) );
	}
	$items  = (array) get_post_meta( $id, '_osoul_items', true );
	$issued = osoul_quote_is_issued( $id );
	$lines  = array();
	foreach ( $items as $i => $it ) {
		$lines[] = array(
			'i'    => (int) $i,
			'name' => $it['name'] ?? '',
			'qty'  => (int) ( $it['qty'] ?? 1 ),
			'cost' => $issued ? (float) ( $it['unit_price'] ?? 0 ) : null,
		);
	}
	return rest_ensure_response( array(
		'id'         => $id,
		'issued'     => $issued,
		'items'      => $lines,
		'margin_min' => (float) osoul_partner_get( $pid, 'margin_min' ),
		'validity'   => (int) ( osoul_opt( 'validity_days' ) ?: 30 ),
	) );
}

/** POST /portal/p-supply — create a supply request from the catalog. */
function osoul_rest_p_supply_create( WP_REST_Request $req ) {
	$pid = get_current_user_id();
	$res = osoul_partner_create_supply( $pid, (array) $req->get_param( 'items' ), (string) $req->get_param( 'note' ) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 422 ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'id' => $res ) );
}

/** POST /portal/p-quote — issue a white-label customer quote from a priced supply. */
function osoul_rest_p_quote( WP_REST_Request $req ) {
	$pid  = get_current_user_id();
	$sell = array();
	foreach ( (array) $req->get_param( 'sell' ) as $i => $v ) {
		$sell[ (int) $i ] = (float) $v;
	}
	$res = osoul_partner_create_customer_quote(
		$pid,
		(int) $req->get_param( 'supply_id' ),
		(array) $req->get_param( 'customer' ),
		$sell,
		array(
			'discount' => (float) $req->get_param( 'discount' ),
			'validity' => (int) $req->get_param( 'validity' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( $res->get_error_code(), $res->get_error_message(), array( 'status' => 422 ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'id' => $res['id'], 'url' => $res['url'] ) );
}

/** GET /portal/p-quotes — the partner's own customer quotes (private). */
function osoul_rest_p_quotes( WP_REST_Request $req ) {
	$pid    = get_current_user_id();
	$stages = osoul_quote_stages();
	$out    = array();
	foreach ( osoul_partner_customer_quotes( $pid ) as $qp ) {
		$items = (array) get_post_meta( $qp->ID, '_osoul_items', true );
		$cust  = (array) get_post_meta( $qp->ID, '_osoul_customer', true );
		$t     = osoul_quote_totals( $items, (float) get_post_meta( $qp->ID, '_osoul_discount', true ) );
		$stage = get_post_meta( $qp->ID, '_osoul_stage', true ) ?: 'sent';
		$st    = $stages[ $stage ] ?? $stages['sent'];
		$tok   = get_post_meta( $qp->ID, '_osoul_token', true );
		$out[] = array(
			'id'          => $qp->ID,
			'number'      => get_post_meta( $qp->ID, '_osoul_number', true ),
			'customer'    => $cust['name'] ?? '',
			'phone'       => $cust['phone'] ?? '',
			'total'       => osoul_money( $t['total'] ),
			'profit'      => osoul_money( osoul_partner_quote_profit( $items ) ),
			'stage'       => $stage,
			'stage_label' => $st['ar'],
			'stage_color' => $st['color'],
			'public_url'  => $tok ? home_url( '/quote/' . $tok . '/' ) : '',
			'date'        => get_the_date( 'Y-m-d', $qp ),
		);
	}
	return rest_ensure_response( array( 'quotes' => $out ) );
}

/** GET /portal/p-customers — the partner's own customer list (private, aggregated). */
function osoul_rest_p_customers( WP_REST_Request $req ) {
	$out = array();
	foreach ( osoul_partner_customers( get_current_user_id() ) as $c ) {
		$out[] = array(
			'name'   => $c['name'],
			'phone'  => $c['phone'],
			'email'  => $c['email'],
			'quotes' => (int) $c['quotes'],
			'total'  => osoul_money( $c['total'] ),
			'last'   => $c['last'],
		);
	}
	return rest_ensure_response( array( 'customers' => $out, 'export' => osoul_partner_export_url() ) );
}

/** GET /portal/p-profile — the partner's brand identity. */
function osoul_rest_p_profile( WP_REST_Request $req ) {
	return rest_ensure_response( osoul_partner_profile( get_current_user_id() ) );
}

/** POST /portal/p-profile — save the partner's brand identity. */
function osoul_rest_p_profile_save( WP_REST_Request $req ) {
	$pid  = get_current_user_id();
	$keys = array( 'company_ar', 'company_en', 'logo', 'vat', 'cr', 'address_ar', 'address_en', 'bank', 'phone', 'terms_ar', 'terms_en', 'color', 'accent' );
	$data = array();
	foreach ( $keys as $k ) {
		$v = $req->get_param( $k );
		if ( null !== $v ) {
			$data[ $k ] = $v;
		}
	}
	osoul_partner_save_profile( $pid, $data );
	return rest_ensure_response( osoul_partner_profile( $pid ) );
}

/** POST /portal/p-catalog — build a signed, branded catalog link for a selection. */
function osoul_rest_p_catalog( WP_REST_Request $req ) {
	if ( ! function_exists( 'osoul_catalog_url' ) ) {
		return new WP_Error( 'osoul_nocat', 'الكتالوج غير متاح.', array( 'status' => 500 ) );
	}
	$pid   = get_current_user_id();
	$items = (array) $req->get_param( 'items' );
	$lang  = ( 'en' === $req->get_param( 'lang' ) ) ? 'en' : 'ar';
	return rest_ensure_response( array( 'ok' => true, 'url' => osoul_catalog_url( $pid, $items, $lang ) ) );
}
