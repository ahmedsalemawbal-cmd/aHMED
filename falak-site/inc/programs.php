<?php
/**
 * البرامج / الحلقات — نوع محتوى قابل للإدارة (بديل «المنتجات» في إضافة أصول).
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
				'name'          => 'البرامج',
				'singular_name' => 'برنامج',
				'add_new'       => 'إضافة برنامج',
				'add_new_item'  => 'إضافة برنامج جديد',
				'edit_item'     => 'تعديل البرنامج',
				'menu_name'     => 'البرامج',
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
			'title'   => 'تحفيظ القرآن الكريم كاملًا',
			'excerpt' => 'خطة متدرّجة لحفظ القرآن كاملًا بإتقان، بمتابعة فردية وخطة أسبوعية لكل طالب.',
			'content' => 'برنامجنا الأساسي لحفظ كتاب الله كاملًا وفق خطة مدروسة تراعي قدرات كل طالب. يبدأ الطالب من قِصار السور تدرّجًا حتى يُتمّ حفظ المصحف الشريف، مع مراجعة دورية تثبّت المحفوظ وتصونه من النسيان.',
			'icon'    => 'quran',
			'badge'   => 'البرنامج الأساسي',
			'tags'    => array( 'جميع المراحل', 'حضوري وعن بُعد', 'متابعة فردية' ),
		),
		array(
			'title'   => 'التجويد وأحكام التلاوة',
			'excerpt' => 'إتقان مخارج الحروف وأحكام التجويد لتلاوة سليمة كما أُنزلت.',
			'content' => 'دورة متخصّصة في أحكام التجويد النظرية والتطبيقية: مخارج الحروف، الصفات، أحكام النون الساكنة والتنوين، المدود، وغيرها؛ ليتلو الطالب كتاب الله تلاوةً مجوّدة صحيحة.',
			'icon'    => 'book',
			'badge'   => '',
			'tags'    => array( 'من 8 سنوات فأكثر', 'حضوري وعن بُعد' ),
		),
		array(
			'title'   => 'التأسيس ونور البيان للأطفال',
			'excerpt' => 'تعليم الصغار القراءة الصحيحة من الصفر بأسلوب محبّب وممتع.',
			'content' => 'برنامج تأسيسي للأطفال في مرحلة الحضانة والابتدائية المبكرة، يعتمد منهج «نور البيان» والقاعدة النورانية لتعليم النطق السليم للحروف والكلمات القرآنية، بأسلوب تربوي جاذب يبني علاقة الطفل بالقرآن مبكرًا.',
			'icon'    => 'star',
			'badge'   => 'للأطفال',
			'tags'    => array( 'حضانة – ابتدائي', 'تلقين وتأسيس' ),
		),
		array(
			'title'   => 'المراجعة والإتقان',
			'excerpt' => 'حلقات مخصّصة لتثبيت المحفوظ وإتقانه ومنع النسيان.',
			'content' => 'حلقات للطلاب الذين أتمّوا أجزاءً من القرآن ويرغبون في تثبيتها وإتقانها. جدول مراجعة منظّم يضمن بقاء المحفوظ حاضرًا مُتقنًا مع تصحيح مستمر للتلاوة.',
			'icon'    => 'target',
			'badge'   => '',
			'tags'    => array( 'للحُفّاظ والمتقدّمين', 'حضوري' ),
		),
		array(
			'title'   => 'الإجازة بالسند المتّصل',
			'excerpt' => 'إجازة برواية حفص عن عاصم بسندٍ متّصل إلى النبي ﷺ.',
			'content' => 'برنامج متقدّم للطلاب المتقنين لكامل القرآن، يتلون على معلّم مُجاز حتى يحصلوا على الإجازة بالسند المتّصل برواية حفص عن عاصم؛ شرفٌ لحاملي كتاب الله وامتدادٌ لسلسلة الرواية.',
			'icon'    => 'award',
			'badge'   => 'متقدّم',
			'tags'    => array( 'للحُفّاظ المتقنين', 'رواية حفص' ),
		),
		array(
			'title'   => 'الدورات الصيفية والمكثّفة',
			'excerpt' => 'برامج مكثّفة في الإجازات لاستثمار الوقت في حفظ القرآن.',
			'content' => 'دورات موسمية مكثّفة في الإجازة الصيفية وإجازات منتصف العام، تتيح للطلاب حفظ أكبر قدر ممكن في وقت قصير، مع مسابقات وحوافز تشجيعية وأجواء إيمانية محفّزة.',
			'icon'    => 'sparkle',
			'badge'   => 'موسمي',
			'tags'    => array( 'جميع الأعمار', 'إجازات' ),
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
