<?php
/**
 * معلومات التواصل والهوية القابلة للإدارة من لوحة التحكم.
 * تُقرأ عبر falak_opt() في كل القوالب — القيم الافتراضية placeholder تُعدّل من الأدمن.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * القيم الافتراضية لكل الإعدادات.
 */
function falak_default_opts() {
	return array(
		'school_name'   => 'مدرسة الفلك المنير لتحفيظ القرآن الكريم',
		'school_short'  => 'الفلك المنير',
		'tagline'       => 'نورُ القرآن يُضيء دربَ أبنائنا — حفظًا وتلاوةً وعملًا',
		'ayah'          => 'وَنُنَزِّلُ مِنَ الْقُرْآنِ مَا هُوَ شِفَاءٌ وَرَحْمَةٌ لِلْمُؤْمِنِينَ',
		'phone'         => '+966500000000',
		'whatsapp'      => '+966500000000',
		'email'         => 'info@alfalak-almunir.com',
		'address'       => 'المملكة العربية السعودية',
		'hours'         => 'السبت – الخميس: 7:00 ص – 9:00 م',
		'map_embed'     => '',
		'twitter'       => '',
		'instagram'     => '',
		'youtube'       => '',
		'snapchat'      => '',
		'telegram'      => '',
		'tiktok'        => '',
		'logo'          => '',
		'favicon'       => '',
		// إحصائيات الواجهة الرئيسية.
		'stat_students' => '850',
		'stat_grads'    => '320',
		'stat_teachers' => '45',
		'stat_years'    => '12',
	);
}

/**
 * يقرأ إعدادًا واحدًا (مع الرجوع للافتراضي).
 */
function falak_opt( $key ) {
	$opts     = get_option( 'falak_opts', array() );
	$defaults = falak_default_opts();
	if ( isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
		return $opts[ $key ];
	}
	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

/**
 * قائمة قنوات التواصل الاجتماعي المفعّلة (لها قيمة).
 */
function falak_social_links() {
	$map = array(
		'twitter'   => array( 'label' => 'إكس (تويتر)', 'icon' => 'x' ),
		'instagram' => array( 'label' => 'إنستقرام', 'icon' => 'instagram' ),
		'youtube'   => array( 'label' => 'يوتيوب', 'icon' => 'youtube' ),
		'snapchat'  => array( 'label' => 'سناب شات', 'icon' => 'snapchat' ),
		'telegram'  => array( 'label' => 'تليجرام', 'icon' => 'telegram' ),
		'tiktok'    => array( 'label' => 'تيك توك', 'icon' => 'tiktok' ),
	);
	$out = array();
	foreach ( $map as $key => $meta ) {
		$val = falak_opt( $key );
		if ( '' !== $val ) {
			$out[ $key ] = array_merge( $meta, array( 'url' => $val ) );
		}
	}
	return $out;
}

/**
 * تسجيل صفحة الإعدادات ضمن قائمة «التسجيلات».
 */
add_action( 'admin_menu', 'falak_settings_menu', 20 );
function falak_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=falak_enroll',
		'إعدادات الموقع',
		'إعدادات الموقع',
		'manage_options',
		'falak-settings',
		'falak_settings_page'
	);
}

/**
 * تسجيل الإعدادات.
 */
add_action( 'admin_init', 'falak_settings_register' );
function falak_settings_register() {
	register_setting(
		'falak_settings_group',
		'falak_opts',
		array( 'sanitize_callback' => 'falak_sanitize_opts' )
	);
}

/**
 * تنقية كل الحقول.
 */
function falak_sanitize_opts( $input ) {
	$clean    = array();
	$defaults = falak_default_opts();
	$urls     = array( 'map_embed', 'twitter', 'instagram', 'youtube', 'snapchat', 'telegram', 'tiktok', 'logo', 'favicon' );
	foreach ( $defaults as $key => $default ) {
		$val = isset( $input[ $key ] ) ? $input[ $key ] : '';
		if ( 'email' === $key ) {
			$clean[ $key ] = sanitize_email( $val );
		} elseif ( 'map_embed' === $key ) {
			$clean[ $key ] = wp_kses( $val, array( 'iframe' => array( 'src' => array(), 'width' => array(), 'height' => array(), 'style' => array(), 'loading' => array(), 'allowfullscreen' => array(), 'referrerpolicy' => array() ) ) );
		} elseif ( in_array( $key, $urls, true ) ) {
			$clean[ $key ] = esc_url_raw( $val );
		} elseif ( in_array( $key, array( 'tagline', 'ayah', 'address', 'hours' ), true ) ) {
			$clean[ $key ] = sanitize_text_field( $val );
		} else {
			$clean[ $key ] = sanitize_text_field( $val );
		}
	}
	return $clean;
}

/**
 * صفحة الإعدادات.
 */
function falak_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$fields = array(
		'الهوية' => array(
			'school_name'  => array( 'اسم المدرسة الكامل', 'text' ),
			'school_short' => array( 'الاسم المختصر', 'text' ),
			'tagline'      => array( 'العبارة التعريفية', 'text' ),
			'ayah'         => array( 'الآية المعروضة في الترويسة', 'text' ),
			'logo'         => array( 'رابط الشعار (PNG مربّع)', 'text' ),
			'favicon'      => array( 'رابط أيقونة الموقع (favicon)', 'text' ),
		),
		'التواصل' => array(
			'phone'    => array( 'رقم الهاتف', 'text' ),
			'whatsapp' => array( 'رقم واتساب (بصيغة دولية)', 'text' ),
			'email'    => array( 'البريد الإلكتروني', 'text' ),
			'address'  => array( 'العنوان', 'text' ),
			'hours'    => array( 'أوقات الدوام', 'text' ),
			'map_embed'=> array( 'كود خريطة جوجل (iframe)', 'textarea' ),
		),
		'وسائل التواصل' => array(
			'twitter'   => array( 'رابط إكس (تويتر)', 'text' ),
			'instagram' => array( 'رابط إنستقرام', 'text' ),
			'youtube'   => array( 'رابط يوتيوب', 'text' ),
			'snapchat'  => array( 'رابط سناب شات', 'text' ),
			'telegram'  => array( 'رابط تليجرام', 'text' ),
			'tiktok'    => array( 'رابط تيك توك', 'text' ),
		),
		'إحصائيات الواجهة' => array(
			'stat_students' => array( 'عدد الطلاب', 'text' ),
			'stat_grads'    => array( 'عدد الحفّاظ المتخرجين', 'text' ),
			'stat_teachers' => array( 'عدد المعلمين', 'text' ),
			'stat_years'    => array( 'سنوات الخبرة', 'text' ),
		),
	);
	?>
	<div class="wrap" dir="rtl" style="max-width:760px">
		<h1>إعدادات موقع مدرسة الفلك المنير</h1>
		<p>عدّل معلومات المدرسة هنا؛ تظهر مباشرة في كل صفحات الموقع.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'falak_settings_group' ); ?>
			<?php foreach ( $fields as $section => $rows ) : ?>
				<h2 style="border-bottom:2px solid #1F9D5A;padding-bottom:6px;color:#0E4D33"><?php echo esc_html( $section ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $rows as $key => $meta ) : ?>
						<tr>
							<th scope="row"><label for="falak_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $meta[0] ); ?></label></th>
							<td>
								<?php if ( 'textarea' === $meta[1] ) : ?>
									<textarea id="falak_<?php echo esc_attr( $key ); ?>" name="falak_opts[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( falak_opt( $key ) ); ?></textarea>
								<?php else : ?>
									<input type="text" id="falak_<?php echo esc_attr( $key ); ?>" name="falak_opts[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( falak_opt( $key ) ); ?>" class="regular-text" dir="auto">
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button( 'حفظ الإعدادات' ); ?>
		</form>
	</div>
	<?php
}
