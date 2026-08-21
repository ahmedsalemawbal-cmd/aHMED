<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * تطبيق المعلم — PWA على /teacher.
 *
 * ليس تطبيق مسح بل قائمة. صور الطلاب، ضغطة، أربعة تصنيفات، سطر واحد.
 * **دور المعلم عشر ثوانٍ ثم يقف**: لا يقرر من يُبلَّغ، ولا يصيغ رسالة،
 * ولا يتحمل مسؤولية النبرة. هو عين لا صوت.
 *
 * أما الدرجات والاختبارات والواجبات فمكانها الداشبورد على الكمبيوتر —
 * رصد 150 درجة من جوال عذاب يجعل المعلم يؤجّل الرصد لآخر الفصل.
 */
final class SCH_Teacher
{
    public const BASE = 'teacher';

    public static function init(): void
    {
        add_action('init', [self::class, 'add_rewrite']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'route'], 0);
    }

    public static function add_rewrite(): void
    {
        $b = self::BASE;
        add_rewrite_rule("^{$b}/?$", 'index.php?sch_tch=1&sch_tch_section=', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/?$", 'index.php?sch_tch=1&sch_tch_section=$matches[1]', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/(\d+)/?$", 'index.php?sch_tch=1&sch_tch_section=$matches[1]&sch_tch_id=$matches[2]', 'top');
    }

    public static function query_vars(array $vars): array
    {
        return [...$vars, 'sch_tch', 'sch_tch_section', 'sch_tch_id'];
    }

    public static function url(string $section = '', int $id = 0): string
    {
        $path = '/' . self::BASE;
        if ($section !== '') {
            $path .= '/' . $section;
        }
        if ($id > 0) {
            $path .= '/' . $id;
        }
        return home_url($path . '/');
    }

    public static function route(): void
    {
        if (!get_query_var('sch_tch')) {
            return;
        }

        $raw = (string) get_query_var('sch_tch_section');

        if ($raw === 'manifest.webmanifest') {
            self::send_manifest();
        }
        if ($raw === 'sw.js') {
            self::send_service_worker();
        }

        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-Content-Type-Options: nosniff');
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        $section = sanitize_key(str_replace('.js', '', $raw));
        $id      = absint(get_query_var('sch_tch_id'));

        if ($section === 'logout') {
            // بنونسٍ لا بمجرّد زيارة: الخروج بـGET يُنفَّذ بصورةٍ في صفحةٍ أخرى.
            if (sch_logout_ok()) {
                wp_logout();
            }

            wp_safe_redirect(self::url());
            exit;
        }

        if (!is_user_logged_in()) {
            self::render_login();
        }

        if (!current_user_can('sch_write_notes')) {
            wp_safe_redirect(SCH_Dashboard::url());
            exit;
        }

        self::handle_post();

        if (!in_array($section, ['', 'klass', 'student'], true)) {
            $section = '';
        }

        // القراءة تُفحص كالكتابة: رابطٌ مكتوب باليد لا يفتح فصل زميل.
        if (($section === 'klass'   && !self::may_touch_class($id))
         || ($section === 'student' && !self::may_touch_student($id))) {
            self::render('home', ['section' => '', 'id' => 0, 'denied' => true]);
        }

        self::render($section === '' ? 'home' : $section, ['section' => $section, 'id' => $id]);
    }

    /**
     * إلى أين نعود بعد الحفظ.
     *
     * `wp_get_referer()` تُرجع **`false`** حين يساوي المُحيل الطلبَ نفسه —
     * وهذا بالضبط ما يحدث في نمط POST/Redirect/GET: النموذج يُرسَل من
     * الصفحة إلى نفسها. فكان كل رصد حضور وكل ملاحظة يقذفان المعلم إلى
     * «فصولي»، ومن يرصد ثلاثين طالبًا يُعاد ثلاثين مرة.
     *
     * والوجهة تُبنى من حقلٍ مُصرَّح به لا من ترويسةٍ يرسلها المتصفّح —
     * `klass:12` أو `student:412` — فلا تُفتح ثغرة إعادة توجيه.
     */
    private static function back_to(): string
    {
        $raw = sanitize_text_field((string) ($_POST['back'] ?? ''));

        if (preg_match('/^(klass|student):(\d+)$/', $raw, $m) === 1) {
            return self::url($m[1], (int) $m[2]);
        }

        return self::url();
    }

    private static function handle_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action = sanitize_key((string) ($_POST['sch_tch_action'] ?? ''));

        if ($action !== 'note' && $action !== 'attend') {
            return;
        }

        $back       = self::back_to();
        $student_id = absint($_POST['student_id'] ?? 0);
        $nonce      = (string) ($_POST['_sch_nonce'] ?? '');

        // النونس مكتوبٌ حرفًا لا مُركَّبًا: الاسم المُركَّب يعمل، لكنه يُخفي
        // العقد عن القارئ وعن `audit.py` معًا — والفحص هو ما يُبقيه صحيحًا.
        $expect = $action === 'note' ? 'sch_tch_note' : 'sch_tch_attend';

        // وفشل النونس كان يُبتلَع بصمت: المعلم يكتب ملاحظته ويضغط «حفظ»
        // فتُعاد الشاشة كما هي بلا سطرٍ مكتوب ولا رسالة.
        if (!wp_verify_nonce($nonce, $expect)) {
            wp_safe_redirect(add_query_arg('err', 'nonce', $back));
            exit;
        }

        /*
         * الملكيّة تُفحص في الخادم: لم يكن شيء يمنع أي حساب يحمل
         * `sch_write_notes` من رصد حضور **أي طالب في المدرسة** وإشعار أهله،
         * أو كتابة ملاحظةٍ باسمه على ملفٍّ لا يخصّه.
         */
        if (!self::may_touch_student($student_id)) {
            wp_safe_redirect(add_query_arg('err', 'forbidden', $back));
            exit;
        }

        if ($action === 'note') {
            $result = SCH_Notes::create([
                'student_id' => $student_id,
                'category'   => sanitize_key((string) ($_POST['category'] ?? '')),
                'body'       => wp_unslash((string) ($_POST['body'] ?? '')),
            ]);

            wp_safe_redirect(add_query_arg(
                is_wp_error($result) ? ['err' => $result->get_error_code()] : ['ok' => 'note'],
                $back
            ));
            exit;
        }

        /*
         * الحضور صلاحيةٌ مستقلّة عن كتابة الملاحظات: الأخصائي الاجتماعي
         * يملك `sch_write_notes` ولا يملك `sch_manage_attendance`، والداشبورد
         * يمنعه — وكان هذا المسار يفتح له ما أُغلق هناك.
         */
        if (!current_user_can('sch_manage_attendance')) {
            wp_safe_redirect(add_query_arg('err', 'forbidden', $back));
            exit;
        }

        $marked = SCH_Attendance::mark(
            $student_id,
            current_time('Y-m-d'),
            sanitize_key((string) ($_POST['status'] ?? 'present')),
            'manual'
        );

        wp_safe_redirect(add_query_arg(
            is_wp_error($marked) || $marked === false
                ? ['err' => is_wp_error($marked) ? $marked->get_error_code() : 'save']
                : ['ok' => 'attend'],
            $back
        ));
        exit;
    }

    // ---------- الدخول ----------

    private static function render_login(): never
    {
        // باب واحد للجميع: خمسة أبواب تعني خمسة روابط يحفظها المستخدم،
        // وأبًا يفتح باب الطالب فيُقال له «لا صلاحية» وهو محق في حسابه.
        wp_safe_redirect(SCH_Portal::url());
        exit;
    }


    // ---------- ملفات التطبيق ----------

    private static function send_manifest(): never
    {
        header('Content-Type: application/manifest+json; charset=utf-8');

        echo wp_json_encode([
            'name'             => __('فصلي — المعلم', 'school-system'),
            'short_name'       => __('فصلي', 'school-system'),
            'start_url'        => self::url(),
            'scope'            => self::url(),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'lang'             => 'ar',
            'dir'              => 'rtl',
            'background_color' => '#F1F4F3',
            'theme_color'      => '#1B3A57',
            'icons'            => [
                ['src' => SCH_URL . 'assets/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => SCH_URL . 'assets/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    private static function send_service_worker(): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . wp_parse_url(self::url(), PHP_URL_PATH));

        /*
         * `teacher.css` لا `app.css`: القالب يطلب الأولى وعامل الخدمة كان
         * يُخزّن الثانية — فالتطبيق بلا شبكة يفتح **بلا تنسيقٍ إطلاقًا**،
         * وهو أسوأ من ألّا يفتح لأنه يبدو معطوبًا لا مقطوعًا.
         */
        $css   = sch_asset('assets/teacher.css');
        $core  = sch_asset('assets/shared-ui.css');
        $tools = sch_asset('assets/list-tools.js');

        echo "const CACHE='sch-tch-" . SCH_VERSION . "';\nconst SHELL=['{$css}','{$core}','{$tools}'];\n";
        echo <<<'JS'

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// لا نخزّن أي صفحة فيها بيانات طالب — الأصول الثابتة فقط.
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  if (!/\.(css|js|png|svg|woff2?)$/.test(new URL(req.url).pathname)) return;
  e.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
});
JS;

        // مستمعا الإشعار الفوري — نصّ واحد لكل عوامل الخدمة.
        SCH_Push::sw_handlers();
        exit;
    }

    // ---------- العرض ----------

    private static function render(string $view, array $data): never
    {
        $file = SCH_PATH . 'frontend/teacher/' . $view . '.php';
        if (!file_exists($file)) {
            $file = SCH_PATH . 'frontend/teacher/home.php';
        }

        $sch_view = $view;
        $sch_data = $data;

        require SCH_PATH . 'frontend/teacher/layout.php';
        exit;
    }

    // ---------- البيانات ----------

    /**
     * شعب المعلم من الجدول ومن إسناد المواد.
     *
     * والعمود `homeroom_teacher_id` لا `homeroom_user_id`: الاسم المخترَع
     * كان يجعل MySQL يرفض **الاستعلام كلّه**، فتكون «فصولي» فارغة لكل معلم
     * في المدرسة — لا لمن ليس مربّي فصل وحده. وقيمة العمود تُقرأ من
     * `class-activator.php` لا من الذاكرة.
     */
    public static function my_classes(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT c.* FROM ' . sch_table('classes') . ' c
             WHERE c.year_id = %d AND (
                   c.homeroom_teacher_id = %d
                OR c.id IN (SELECT cs.class_id FROM ' . sch_table('class_subjects') . ' cs WHERE cs.teacher_user_id = %d)
                OR c.id IN (SELECT t.class_id FROM ' . sch_table('timetable') . ' t WHERE t.teacher_user_id = %d)
             )
             ORDER BY c.grade_level, c.section',
            SCH_Years::current_id(),
            $user_id,
            $user_id,
            $user_id
        )) ?: [];
    }

    /**
     * هل هذه الشعبة من شعبي؟
     *
     * كانت `/teacher/klass/17/` تُفتح لأي حساب يملك `sch_write_notes` —
     * أي معلّمٍ في المدرسة يرى شبكة وجوه فصلٍ ليس له، بصور الطلاب وحالة
     * عهدتهم؛ و`/teacher/student/412/` تكشف ملاحظاتٍ صحّية وسلوكية.
     * والفحص في الخادم دائمًا: التطبيق لا يُؤتمن أبدًا.
     */
    public static function may_touch_class(int $class_id, ?int $user_id = null): bool
    {
        if ($class_id <= 0) {
            return false;
        }

        // من يدير الطلاب يرى الجميع أصلًا في الداشبورد — فلا نُغلقها عليه هنا.
        if (current_user_can('sch_manage_students')) {
            return true;
        }

        $user_id ??= get_current_user_id();

        foreach (self::my_classes($user_id) as $c) {
            if ((int) $c->id === $class_id) {
                return true;
            }
        }

        return false;
    }

    /** وهل هذا الطالب في إحدى شعبي؟ */
    public static function may_touch_student(int $student_id, ?int $user_id = null): bool
    {
        if ($student_id <= 0) {
            return false;
        }

        if (current_user_can('sch_manage_students')) {
            return true;
        }

        $class = SCH_Students::current_class($student_id);

        return $class !== null && self::may_touch_class((int) $class->id, $user_id);
    }

    /** طلاب شعبة مع حالتهم اليوم — الأساس لشاشة الفصل. */
    public static function roster(int $class_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT s.id, s.full_name, s.first_name, s.father_name, s.grand_name, s.family_name,
                    s.photo_file, s.custody_state, a.status AS att_status
             FROM ' . sch_table('enrollments') . ' e
             INNER JOIN ' . sch_table('students') . ' s ON s.id = e.student_id
             LEFT JOIN ' . sch_table('attendance') . ' a
                    ON a.student_id = s.id AND a.att_date = %s
             WHERE e.class_id = %d AND e.status = %s AND s.status = %s
             ORDER BY s.full_name',
            current_time('Y-m-d'),
            $class_id,
            'active',
            'active'
        )) ?: [];
    }

    /** من لم يُرصد دخوله — شبكة الأمان الساعة 8:30. */
    public static function unmarked(array $roster): array
    {
        return array_values(array_filter(
            $roster,
            static fn (object $s): bool => $s->att_status === null
        ));
    }

    /**
     * صورة الطالب من `SCH_Files` — الباب الواحد للملفّات المحمية.
     *
     * كانت تُطلب من مسار الداشبورد، وهو يمرّ بفحص القسم وبـ`SCH_Perms::may()`
     * قبل أن يصل إلى الصورة — فمعلّمٌ صلاحياته المخصّصة تستثني «الطلاب»
     * يرى شبكة وجوهٍ فارغة في فصله هو.
     */
    public static function photo_url(object $student): string
    {
        return $student->photo_file
            ? SCH_Files::url('photo', (int) $student->id)
            : '';
    }
}
