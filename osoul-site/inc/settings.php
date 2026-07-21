<?php
/**
 * Site settings — manage contact info (phone, WhatsApp, email, address, hours,
 * social URLs, maps embed) from the WP admin instead of hard-coding them.
 *
 * All template output reads these through osoul_opt(); defaults match the
 * values that were previously hard-coded, so nothing changes until edited.
 *
 * @package Osoul
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Default values (= the original hard-coded values).
 *
 * @return array<string,string>
 */
function osoul_default_options() {
	return array(
		'phone_primary'   => '+966 563 627 063',
		'phone_secondary' => '+966 11 810 8717',
		'email'           => 'info@osoulalbinaa.com',
		'whatsapp'        => '966556847029',
		'address_ar'      => 'المملكة العربية السعودية — جدة، المدينة الصناعية الثالثة',
		'address_en'      => 'Saudi Arabia — Jeddah, 3rd Industrial City',
		'hours_ar'        => 'السبت — الخميس | 8:00 ص — 5:00 م',
		'hours_en'        => 'Sat – Thu | 8:00 AM – 5:00 PM',
		'instagram'       => 'https://instagram.com/',
		'linkedin'        => 'https://linkedin.com/company/',
		'twitter'         => 'https://x.com/',
		'maps_embed'      => 'https://www.google.com/maps?q=21.1401875,39.3309375&hl=ar&z=16&output=embed',
		'favicon'         => '', // Site icon shown in Google results + browser tabs (square PNG, ASCII filename). Empty => WP Site Icon / logo_icon.

		/* ── Quote document (placeholders — edit later) ── */
		'company_name_ar'    => 'شركة أصول البناء للصناعة',
		'company_name_en'    => 'Osoul Albinaa Industrial Co.',
		'logo_url'           => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/تصميم-بدون-عنوان-20.png',
		'logo_url_en'        => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/تصميم-بدون-عنوان-22.png',
		'logo_icon'          => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/شعار-اصول.ai_.png',
		'login_video_url'    => 'https://osoulalbinaa.com/wp-content/uploads/2026/06/تغطية-المصنع3.mp4',
		'vat_number'         => '300000000000003',
		'cr_number'          => '4030000000',
		'national_address_ar'=> 'جدة — المدينة الصناعية الثالثة، المملكة العربية السعودية',
		'national_address_en'=> 'Jeddah — 3rd Industrial City, Saudi Arabia',
		'bank_accounts'      => "البنك الأهلي السعودي | اسم الحساب: شركة أصول البناء للصناعة | آيبان: SA0000000000000000000000",
		'terms_ar'           => "• الأسعار بالريال السعودي ولا تشمل ضريبة القيمة المضافة (تُضاف 15%).\n• هذا العرض ساري لمدة المدة الموضحة أعلاه.\n• التسليم والتركيب حسب الاتفاق.",
		'terms_en'           => "• Prices are in SAR and exclude VAT (15% added).\n• This quotation is valid for the period shown above.\n• Delivery and installation as agreed.",
		'validity_days'      => '30',
		'quote_prefix'       => 'OSB',

		/* ── Customer login: Google Sign-In (OAuth) ── */
		'google_client_id'     => '',
		'google_client_secret' => '',
	);
}

/**
 * Read one setting, falling back to its default.
 *
 * @param string $key
 * @return string
 */
function osoul_opt( $key ) {
	$opts     = get_option( 'osoul_settings', array() );
	$defaults = osoul_default_options();
	if ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
		return $opts[ $key ];
	}
	return $defaults[ $key ] ?? '';
}

/**
 * Convert a display phone number into a `tel:` href value (digits + leading +).
 *
 * @param string $display
 * @return string
 */
function osoul_tel( $display ) {
	$tel = preg_replace( '/[^\d+]/', '', $display );
	return $tel;
}

/**
 * WhatsApp deep link from the configured number.
 *
 * @param string $text Optional prefilled message.
 * @return string
 */
function osoul_wa_url( $text = '' ) {
	$num = preg_replace( '/\D/', '', osoul_opt( 'whatsapp' ) );
	$url = 'https://wa.me/' . $num;
	if ( '' !== $text ) {
		$url .= '?text=' . rawurlencode( $text );
	}
	return $url;
}

/* =========================================================
 * Settings registration + admin UI
 * ========================================================= */

add_action( 'admin_init', function () {
	register_setting( 'osoul_settings_group', 'osoul_settings', array(
		'type'              => 'array',
		'sanitize_callback' => 'osoul_sanitize_settings',
		'default'           => array(),
	) );
} );

/**
 * Sanitise the settings array on save.
 *
 * @param mixed $input
 * @return array
 */
function osoul_sanitize_settings( $input ) {
	$out  = array();
	$keys = array_keys( osoul_default_options() );
	$url_fields      = array( 'instagram', 'linkedin', 'twitter', 'maps_embed', 'favicon', 'logo_url', 'logo_url_en', 'logo_icon' );
	$textarea_fields = array( 'bank_accounts', 'terms_ar', 'terms_en', 'national_address_ar', 'national_address_en' );
	foreach ( $keys as $k ) {
		$val = isset( $input[ $k ] ) ? wp_unslash( $input[ $k ] ) : '';
		if ( in_array( $k, $url_fields, true ) ) {
			$out[ $k ] = esc_url_raw( $val );
		} elseif ( in_array( $k, $textarea_fields, true ) ) {
			$out[ $k ] = sanitize_textarea_field( $val );
		} elseif ( 'email' === $k ) {
			$out[ $k ] = sanitize_email( $val );
		} else {
			$out[ $k ] = sanitize_text_field( $val );
		}
	}
	return $out;
}

/**
 * Add the settings page under the Quote Leads menu.
 */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=osoul_lead',
		__( 'Site Settings', 'osoul' ),
		__( 'Site Settings', 'osoul' ),
		'manage_options',
		'osoul-settings',
		'osoul_render_settings_page'
	);
} );

/**
 * Render the settings form.
 */
function osoul_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$fields = array(
		'phone_primary'   => __( 'Primary phone', 'osoul' ),
		'phone_secondary' => __( 'Secondary phone', 'osoul' ),
		'email'           => __( 'Email', 'osoul' ),
		'whatsapp'        => __( 'WhatsApp number (digits only, e.g. 9665XXXXXXXX)', 'osoul' ),
		'address_ar'      => __( 'Address (Arabic)', 'osoul' ),
		'address_en'      => __( 'Address (English)', 'osoul' ),
		'hours_ar'        => __( 'Working hours (Arabic)', 'osoul' ),
		'hours_en'        => __( 'Working hours (English)', 'osoul' ),
		'instagram'       => __( 'Instagram URL', 'osoul' ),
		'linkedin'        => __( 'LinkedIn URL', 'osoul' ),
		'twitter'         => __( 'Twitter / X URL', 'osoul' ),
		'maps_embed'      => __( 'Google Maps embed URL (the src of the iframe)', 'osoul' ),
		'favicon'         => __( 'أيقونة الموقع في جوجل (Favicon)', 'osoul' ),
		'__quote'             => '— ' . __( 'بيانات عرض السعر (Quote document)', 'osoul' ) . ' —',
		'company_name_ar'     => __( 'اسم الشركة (عربي)', 'osoul' ),
		'company_name_en'     => __( 'اسم الشركة (إنجليزي)', 'osoul' ),
		'logo_url'            => __( 'رابط الشعار (Logo URL)', 'osoul' ),
		'logo_url_en'         => __( 'شعار العرض الإنجليزي (English quote logo)', 'osoul' ),
		'logo_icon'           => __( 'أيقونة صفحة الدخول (Login icon)', 'osoul' ),
		'login_video_url'     => __( 'فيديو خلفية صفحة الدخول (Login background video)', 'osoul' ),
		'vat_number'          => __( 'الرقم الضريبي (VAT number)', 'osoul' ),
		'cr_number'           => __( 'السجل التجاري (CR number)', 'osoul' ),
		'national_address_ar' => __( 'العنوان الوطني (عربي)', 'osoul' ),
		'national_address_en' => __( 'العنوان الوطني (إنجليزي)', 'osoul' ),
		'bank_accounts'       => __( 'الحسابات البنكية (كل حساب في سطر)', 'osoul' ),
		'terms_ar'            => __( 'الشروط والأحكام (عربي)', 'osoul' ),
		'terms_en'            => __( 'الشروط والأحكام (إنجليزي)', 'osoul' ),
		'validity_days'       => __( 'مدة صلاحية العرض (أيام)', 'osoul' ),
		'quote_prefix'        => __( 'بادئة رقم العرض (مثل OSB)', 'osoul' ),
		'__google'            => '— ' . __( 'ربط جوجل (تسجيل دخول العملاء)', 'osoul' ) . ' —',
		'google_client_id'     => __( 'Google Client ID', 'osoul' ),
		'google_client_secret' => __( 'Google Client Secret', 'osoul' ),
	);
	$textarea_keys = array( 'maps_embed', 'bank_accounts', 'terms_ar', 'terms_en', 'national_address_ar', 'national_address_en' );
	$descriptions  = array(
		'favicon' => __( 'الأيقونة التي تظهر بجانب الموقع في نتائج جوجل وفي تبويب المتصفح. الأفضل: صورة PNG مربّعة 512×512 بكسل باسم إنجليزي (مثل favicon.png) — تجنّب الأسماء العربية. اتركه فارغاً لاستخدام «أيقونة الموقع» في ووردبريس. Square PNG (512×512), ASCII filename.', 'osoul' ),
		'login_video_url' => __( 'رابط فيديو MP4 يظهر كخلفية متحركة في صفحة دخول العملاء (مثل الهيرو سيكشن). اتركه فارغاً لاستخدام الخلفية الافتراضية. Direct MP4 URL — muted autoplay loop.', 'osoul' ),
		'google_client_id'     => __( 'من Google Cloud Console ← Credentials. اترك الحقلين فارغين لتعطيل زر «الدخول عبر Google». Authorized redirect URI = عنوان موقعك، مثل https://osoulalbinaa.com/', 'osoul' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Osoul — Site Settings', 'osoul' ); ?></h1>
		<p><?php esc_html_e( 'These values feed the header, footer and contact page. Leave a field blank to use the built-in default.', 'osoul' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'osoul_settings_group' ); ?>
			<table class="form-table" role="presentation"><tbody>
			<?php foreach ( $fields as $key => $label ) : ?>
				<?php if ( 0 === strpos( (string) $key, '__' ) ) : ?>
					<tr><th colspan="2" style="padding-top:24px"><h2 style="margin:0;font-size:15px;color:#0d1f3c"><?php echo esc_html( $label ); ?></h2></th></tr>
					<?php continue; ?>
				<?php endif; ?>
				<?php $val = osoul_opt( $key ); ?>
				<tr>
					<th scope="row"><label for="osoul_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td>
						<?php if ( in_array( $key, $textarea_keys, true ) ) : ?>
							<textarea id="osoul_<?php echo esc_attr( $key ); ?>" name="osoul_settings[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text<?php echo 'maps_embed' === $key ? ' code' : ''; ?>"><?php echo esc_textarea( $val ); ?></textarea>
						<?php else : ?>
							<input type="text" id="osoul_<?php echo esc_attr( $key ); ?>" name="osoul_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>" class="regular-text">
						<?php endif; ?>
						<?php if ( isset( $descriptions[ $key ] ) ) : ?>
							<p class="description"><?php echo esc_html( $descriptions[ $key ] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
