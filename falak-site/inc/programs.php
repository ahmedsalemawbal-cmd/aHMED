<?php
/**
 * المراحل والبرامج — نوع محتوى قابل للإدارة (بديل «المنتجات» في إضافة أصول).
 * يُدار من لوحة التحكم، ومزروع ببرامج افتراضية عند التفعيل.
 *
 * @package Falak
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * تسجيل نوع المحتوى «برنامج».
 */
add_action( 'init', 'falak_register_program_cpt' );
function falak_register_program_cpt() {
	register_post_type(
		'falak_program',
		array(
			'labels'       => array(
				'name'          => 'المراحل والبرامج',
				'singular_name' => 'برنامج',
				'add_new'       => 'إضافة برنامج',
				'add_new_item'  => 'إضافة برنامج جديد',
				'edit_item'     => 'تعديل البرنامج',
				'menu_name'     => 'المراحل والبرامج',
				'all_items'     => 'كل البرامج',
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-book-alt',
			'menu_position'=> 26,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			'has_archive'  => false,
			'rewrite'      => false,
		)
	);
}

/**
 * البرامج الافتراضية (تُزرع عند التفعيل، وتُستخدم كاحتياطي في المعاينة).
 */
function falak_default_programs() {
	return array(
		array(
			'title'   => 'رياض الأطفال (التمهيدي)',
			'excerpt' => 'تأسيسٌ تربويٌّ مبكّر يُنمّي مهارات الطفل بأسلوبٍ محبّبٍ وآمن.',
			'content' => 'مرحلةٌ تأسيسيةٌ تُعنى ببناء شخصية الطفل ومهاراته اللغوية والحسابية والحركية والاجتماعية، عبر مناهج حديثة وأنشطة تفاعلية وبيئة آمنة ومحفّزة تُهيّئه لدخول المرحلة الابتدائية بثقة.',
			'icon'    => 'star',
			'badge'   => 'تأسيس مبكّر',
			'tags'    => array( 'حضانة – تمهيدي', 'بنين وبنات' ),
		),
		array(
			'title'   => 'المرحلة الابتدائية',
			'excerpt' => 'بناء الأساس العلمي والقيمي وفق منهج وزارة التعليم المعتمد.',
			'content' => 'نُرسي في المرحلة الابتدائية أساسًا متينًا في المهارات الأساسية (القراءة والكتابة والرياضيات والعلوم واللغات) وفق منهج وزارة التعليم، مع عنايةٍ بالجانب القيمي والسلوكي ومتابعةٍ فرديةٍ لكل طالب.',
			'icon'    => 'book',
			'badge'   => '',
			'tags'    => array( 'الصف الأول – السادس', 'بنين وبنات' ),
		),
		array(
			'title'   => 'المرحلة المتوسطة',
			'excerpt' => 'تعميق المعرفة وتنمية مهارات التفكير والبحث لدى الطالبات.',
			'content' => 'تُركّز المرحلة المتوسطة على تعميق المفاهيم العلمية وتنمية مهارات التفكير الناقد والبحث والعمل الجماعي، وإعداد الطالبات للمرحلة الثانوية بثقةٍ واقتدار.',
			'icon'    => 'target',
			'badge'   => '',
			'tags'    => array( 'الصف الأول – الثالث متوسط', 'بنات' ),
		),
		array(
			'title'   => 'المرحلة الثانوية',
			'excerpt' => 'إعدادٌ أكاديميٌّ متكامل يمهّد للجامعة والمستقبل المهني.',
			'content' => 'تُهيّئ المرحلة الثانوية الطالبات للمرحلة الجامعية عبر مسارات تعليمية حديثة، وإرشادٍ أكاديميٍّ ومهني، وتنمية مهارات القرن الحادي والعشرين، مع تحصيلٍ دراسيٍّ عالٍ يفتح أبواب القبول الجامعي.',
			'icon'    => 'award',
			'badge'   => 'مسارات حديثة',
			'tags'    => array( 'الصف الأول – الثالث ثانوي', 'بنات' ),
		),
		array(
			'title'   => 'العناية بالقرآن والتربية الإسلامية',
			'excerpt' => 'إلى جانب المنهج، نولي القرآن والقيم الإسلامية عنايةً خاصة.',
			'content' => 'انطلاقًا من رسالة المدرسة، نُولي كتاب الله والتربية الإسلامية عنايةً خاصة عبر حصص القرآن والتجويد والحفظ الميسّر، وبرامج تربوية تُرسّخ القيم والأخلاق في نفوس الطلاب جنبًا إلى جنب مع تفوّقهم الأكاديمي.',
			'icon'    => 'quran',
			'badge'   => 'رسالتنا',
			'tags'    => array( 'جميع المراحل', 'قيم وأخلاق' ),
		),
		array(
			'title'   => 'الأنشطة ورعاية الموهوبين',
			'excerpt' => 'برامج لا صفية وأنشطة تُنمّي المواهب والمهارات والقيادة.',
			'content' => 'نُتيح للطلاب طيفًا واسعًا من الأنشطة اللاصفية: الرياضية والعلمية والفنية والثقافية، مع برنامجٍ لرعاية الموهوبين والمتفوقين يكتشف قدراتهم ويصقلها ويُشارك بهم في المسابقات والمعارض.',
			'icon'    => 'sparkle',
			'badge'   => 'مواهب وتميّز',
			'tags'    => array( 'جميع المراحل', 'رياضة – فنون – علوم' ),
		),
	);
}

/**
 * زرع البرامج الافتراضية (مرة واحدة).
 */
function falak_seed_programs() {
	$existing = get_posts( array( 'post_type' => 'falak_program', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( ! empty( $existing ) ) {
		return;
	}
	$order = 0;
	foreach ( falak_default_programs() as $p ) {
		$order += 10;
		$id = wp_insert_post(
			array(
				'post_type'    => 'falak_program',
				'post_status'  => 'publish',
				'post_title'   => $p['title'],
				'post_content' => $p['content'],
				'post_excerpt' => $p['excerpt'],
				'menu_order'   => $order,
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_falak_icon', $p['icon'] );
			update_post_meta( $id, '_falak_badge', $p['badge'] );
			update_post_meta( $id, '_falak_tags', implode( ' | ', $p['tags'] ) );
		}
	}
}

/**
 * جلب البرامج للعرض (من قاعدة البيانات، أو الافتراضية كاحتياطي).
 *
 * @return array قائمة عناصر موحّدة الشكل.
 */
function falak_get_programs() {
	$posts = get_posts(
		array(
			'post_type'   => 'falak_program',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		)
	);
	if ( empty( $posts ) ) {
		// احتياطي: البرامج الافتراضية مباشرةً (تُظهر الموقع حتى قبل التفعيل).
		$out = array();
		foreach ( falak_default_programs() as $p ) {
			$out[] = array(
				'title'   => $p['title'],
				'excerpt' => $p['excerpt'],
				'content' => $p['content'],
				'icon'    => $p['icon'],
				'badge'   => $p['badge'],
				'tags'    => $p['tags'],
				'image'   => '',
			);
		}
		return $out;
	}
	$out = array();
	foreach ( $posts as $post ) {
		$tags = get_post_meta( $post->ID, '_falak_tags', true );
		$out[] = array(
			'title'   => $post->post_title,
			'excerpt' => $post->post_excerpt,
			'content' => $post->post_content,
			'icon'    => get_post_meta( $post->ID, '_falak_icon', true ) ?: 'quran',
			'badge'   => get_post_meta( $post->ID, '_falak_badge', true ),
			'tags'    => $tags ? array_map( 'trim', explode( '|', $tags ) ) : array(),
			'image'   => get_the_post_thumbnail_url( $post->ID, 'large' ) ?: '',
		);
	}
	return $out;
}

/**
 * صندوق تحرير الحقول الإضافية للبرنامج.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'falak_program_meta', 'بيانات البرنامج', 'falak_program_metabox', 'falak_program', 'side' );
} );

function falak_program_metabox( $post ) {
	wp_nonce_field( 'falak_program_meta', 'falak_program_nonce' );
	$icon  = get_post_meta( $post->ID, '_falak_icon', true );
	$badge = get_post_meta( $post->ID, '_falak_badge', true );
	$tags  = get_post_meta( $post->ID, '_falak_tags', true );
	$icons = array( 'quran', 'book', 'star', 'mosque', 'award', 'sparkle', 'target', 'heart' );
	?>
	<p>
		<label for="falak_icon"><b>الأيقونة</b></label><br>
		<select name="falak_icon" id="falak_icon" style="width:100%">
			<?php foreach ( $icons as $ic ) : ?>
				<option value="<?php echo esc_attr( $ic ); ?>" <?php selected( $icon, $ic ); ?>><?php echo esc_html( $ic ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="falak_badge"><b>الشارة (اختياري)</b></label><br>
		<input type="text" name="falak_badge" id="falak_badge" value="<?php echo esc_attr( $badge ); ?>" style="width:100%" placeholder="مثال: للأطفال">
	</p>
	<p>
		<label for="falak_tags"><b>الوسوم (افصل بـ |)</b></label><br>
		<input type="text" name="falak_tags" id="falak_tags" value="<?php echo esc_attr( $tags ); ?>" style="width:100%" placeholder="جميع المراحل | حضوري">
	</p>
	<p style="color:#666">استخدم «الملخّص» أسفل المحرّر للسطر القصير في البطاقة.</p>
	<?php
}

add_action( 'save_post_falak_program', function ( $post_id ) {
	if ( ! isset( $_POST['falak_program_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['falak_program_nonce'] ) ), 'falak_program_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( 'falak_icon' => '_falak_icon', 'falak_badge' => '_falak_badge', 'falak_tags' => '_falak_tags' ) as $field => $meta ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $meta, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
		}
	}
} );
