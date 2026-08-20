<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * تطبيق ولي الأمر — PWA على /app.
 * نفس قاعدة البيانات ونفس المنطق، بواجهة مبنية للجوال.
 * يُثبَّت على الشاشة الرئيسية فيعمل كتطبيق بلا متجر.
 */
final class SCH_App
{
    public const BASE = 'app';

    private const SECTIONS = ['', 'child', 'log', 'invoices', 'alerts', 'messages',
                              'schedule', 'kg', 'clinic', 'leave', 'certificates', 'card', 'transport', 'track', 'account', 'pickers', 'install'];

    public static function init(): void
    {
        add_action('init', [self::class, 'add_rewrite']);
        add_filter('query_vars', [self::class, 'query_vars']);
        add_action('template_redirect', [self::class, 'route'], 0);
    }

    public static function add_rewrite(): void
    {
        $b = self::BASE;
        add_rewrite_rule("^{$b}/?$", 'index.php?sch_app=1&sch_app_section=', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/?$", 'index.php?sch_app=1&sch_app_section=$matches[1]', 'top');
        add_rewrite_rule("^{$b}/([^/]+)/(\d+)/?$", 'index.php?sch_app=1&sch_app_section=$matches[1]&sch_app_id=$matches[2]', 'top');
    }

    public static function query_vars(array $vars): array
    {
        return [...$vars, 'sch_app', 'sch_app_section', 'sch_app_id'];
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
        if (!get_query_var('sch_app')) {
            return;
        }

        $raw     = (string) get_query_var('sch_app_section');
        $id      = absint(get_query_var('sch_app_id'));
        $section = sanitize_key(str_replace(['.webmanifest', '.js', '.json'], '', $raw));

        // ملفات التطبيق تُخدم قبل أي تحقق من الدخول.
        if ($raw === 'manifest.webmanifest') {
            self::send_manifest();
        }
        if ($raw === 'sw.js') {
            self::send_service_worker();
        }

        self::no_cache_headers();

        if ($section === 'logout') {
            wp_logout();
            wp_safe_redirect(self::url());
            exit;
        }

        // صفحة التثبيت عامة: تُشارَك مع أولياء الأمور قبل أن يكون لهم حساب.
        if ($section === 'install') {
            self::render('install', ['section' => 'install']);
        }

        if (!is_user_logged_in()) {
            self::render_login();
        }

        if (isset($_GET['sch_avatar'])) {
            self::send_avatar();
        }
        if (isset($_GET['sch_kid_photo']) && $id > 0) {
            self::send_child_photo($id);
        }

        if (!current_user_can('sch_view_own_children')) {
            // موظفو المدرسة مكانهم الداشبورد لا التطبيق.
            wp_safe_redirect(SCH_Dashboard::url());
            exit;
        }

        if (!in_array($section, self::SECTIONS, true)) {
            $section = '';
        }

        // ولي الأمر لا يفتح إلا ملفات أبنائه.
        if ($id > 0 && !SCH_Students::guardian_has_child(get_current_user_id(), $id)) {
            status_header(403);
            self::render('denied', []);
        }

        self::handle_post();

        $children = SCH_Students::children_of(get_current_user_id());

        // ابن واحد فقط؟ افتح ملفه مباشرة بلا شاشة اختيار.
        if ($section === '' && count($children) === 1) {
            wp_safe_redirect(self::url('child', (int) $children[0]->id));
            exit;
        }

        // أقسام الطالب: لو لم يُحدَّد ابن وكان له ابن واحد، افتحه تلقائيًا.
        $needs_child = ['child', 'log', 'invoices', 'schedule', 'kg', 'clinic', 'leave', 'card', 'transport', 'track', 'certificates'];

        if ($id === 0 && in_array($section, $needs_child, true)) {
            if (count($children) === 1) {
                wp_safe_redirect(self::url($section, (int) $children[0]->id));
                exit;
            }
            $section = '';
        }

        self::render($section === '' ? 'home' : $section, [
            'section'  => $section,
            'id'       => $id,
            'children' => $children,
        ]);
    }

    private static function handle_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action = sanitize_key((string) ($_POST['sch_app_action'] ?? ''));

        // نداء الانصراف — الأب عند البوابة يطلب ابنه، والفحوص كلها في الطبقة.
        if ($action === 'pickup_call' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_app_pickup')) {
            $sid  = absint($_POST['student_id'] ?? 0);
            $done = SCH_Pickup::call($sid, get_current_user_id(), (string) ($_POST['note'] ?? ''));

            wp_safe_redirect(is_wp_error($done)
                ? add_query_arg('err', $done->get_error_code(), self::url('child', $sid))
                : self::url('child', $sid));
            exit;
        }

        if ($action === 'pickup_cancel' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_app_pickup')) {
            $sid  = absint($_POST['student_id'] ?? 0);
            $call = SCH_Pickup::open_for($sid);

            // لا يُلغي إلا صاحبه — لا يُلغى نداء غيرك بتخمين رقمه.
            if ($call && (int) $call->caller_id === get_current_user_id()) {
                SCH_Pickup::cancel((int) $call->id);
            }

            wp_safe_redirect(self::url('child', $sid));
            exit;
        }

        /* التفويض المؤقّت — «خالته اليوم فقط».
           والفحوص كلّها في SCH_Custody: الشاشة تجمع الحقول ولا تقرّر. */
        if ($action === 'delegate_add' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_app_delegate')) {
            $sid = absint($_POST['student_id'] ?? 0);

            // لا يفوّض إلا من يحقّ له هو الاستلام — وإلا صار التفويض بابًا
            // خلفيًّا يفوّض به من لا يملك أصلًا.
            if (!SCH_Custody::may_pick_up($sid, 'g:' . get_current_user_id())) {
                wp_safe_redirect(add_query_arg('err', 'not_yours', self::url('pickers')));
                exit;
            }

            // حقل datetime-local يرسل «2026-08-19T14:30» بلا ثوانٍ
            $until = trim((string) ($_POST['valid_until'] ?? ''));
            if ($until !== '') {
                $until = str_replace('T', ' ', $until);
                $until .= substr_count($until, ':') === 1 ? ':00' : '';
            }

            $done = SCH_Custody::delegate([
                'student_id'  => $sid,
                'person_name' => (string) ($_POST['person_name'] ?? ''),
                'relation'    => (string) ($_POST['relation'] ?? ''),
                'id_number'   => (string) ($_POST['id_number'] ?? ''),
                'phone'       => (string) ($_POST['phone'] ?? ''),
                'valid_until' => $until,
            ]);

            wp_safe_redirect(is_wp_error($done)
                ? add_query_arg('err', $done->get_error_code(), self::url('pickers'))
                : self::url('pickers'));
            exit;
        }

        if ($action === 'delegate_revoke' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_app_delegate')) {
            $del = absint($_POST['delegation_id'] ?? 0);
            $row = SCH_Custody::get_delegation($del);

            // لا يُلغى إلا تفويض أنشأتَه أنت — لا يُلغى تفويض غيرك بتخمين رقمه
            if ($row && (int) $row->granted_by === get_current_user_id()) {
                SCH_Custody::revoke_delegation($del);
            }

            wp_safe_redirect(self::url('pickers'));
            exit;
        }

        if ($action === 'read_alerts' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_read_alerts')) {
            SCH_Comms::mark_read(get_current_user_id());
            wp_safe_redirect(self::url('alerts'));
            exit;
        }

        if ($action === 'change_password' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_change_password')) {
            self::change_password();
        }

        // رد ولي الأمر على ملاحظة — جملة واحدة قد تحلّ القضية في دقيقة.
        if ($action === 'request_leave' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_app_leave')) {
            $student_id = absint($_POST['student_id'] ?? 0);

            if (SCH_Students::guardian_has_child(get_current_user_id(), $student_id)) {
                $result = SCH_StudentLeave::request([
                    'student_id'  => $student_id,
                    'reason_code' => sanitize_key((string) ($_POST['reason_code'] ?? '')),
                    'start_date'  => (string) ($_POST['start_date'] ?? ''),
                    'end_date'    => (string) ($_POST['end_date'] ?? ''),
                    'note'        => wp_unslash((string) ($_POST['note'] ?? '')),
                ], $_FILES);

                wp_safe_redirect(add_query_arg(
                    is_wp_error($result) ? ['err' => $result->get_error_message()] : ['ok' => '1'],
                    self::url('leave', $student_id)
                ));
                exit;
            }
        }

        if ($action === 'request_med' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_app_med')) {
            $student_id = absint($_POST['student_id'] ?? 0);

            if (SCH_Students::guardian_has_child(get_current_user_id(), $student_id)) {
                $result = SCH_Medication::request([
                    'student_id' => $student_id,
                    'med_name'   => wp_unslash((string) ($_POST['med_name'] ?? '')),
                    'dose'       => wp_unslash((string) ($_POST['dose'] ?? '')),
                    'times'      => (array) ($_POST['times'] ?? []),
                    'days'       => absint($_POST['days'] ?? 1),
                    'start_date' => (string) ($_POST['start_date'] ?? ''),
                    'note'       => wp_unslash((string) ($_POST['note'] ?? '')),
                ], $_FILES);

                wp_safe_redirect(add_query_arg(
                    is_wp_error($result) ? ['err' => $result->get_error_message()] : ['ok' => '1'],
                    self::url('clinic', $student_id)
                ));
                exit;
            }
        }

        if ($action === 'upload_avatar' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_app_avatar')) {
            self::save_avatar();
        }

        if ($action === 'note_reply' && wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_note_reply')) {
            SCH_Notes::parent_reply(
                absint($_POST['note_id'] ?? 0),
                sanitize_key((string) ($_POST['response'] ?? '')),
                wp_unslash((string) ($_POST['reply_body'] ?? ''))
            );

            wp_safe_redirect(wp_get_referer() ?: self::url('alerts'));
            exit;
        }
    }

    /** صورة ولي الأمر — في المجلد المحمي كصور الطلاب. */
    private static function save_avatar(): never
    {
        global $wpdb;

        if (empty($_FILES['avatar']['tmp_name'])) {
            wp_safe_redirect(self::url('account'));
            exit;
        }

        $user_id  = get_current_user_id();
        $guardian = SCH_Guardians::by_user($user_id);

        $saved = SCH_Enrollment::store_upload($_FILES['avatar'], $user_id, 'avatar');

        if (!is_wp_error($saved) && $guardian) {
            if ($guardian->photo_file) {
                $old = SCH_Enrollment::private_dir() . '/' . $guardian->photo_file;
                if (file_exists($old)) {
                    @unlink($old);
                }
            }

            $wpdb->update(sch_table('guardians'), ['photo_file' => $saved['stored']], ['user_id' => $user_id]);
        }

        wp_safe_redirect(self::url('account'));
        exit;
    }

    /** خدمة صورة ولي الأمر — لصاحبها فقط. */
    public static function send_avatar(): never
    {
        $guardian = SCH_Guardians::by_user(get_current_user_id());

        if (!$guardian || !$guardian->photo_file) {
            status_header(404);
            exit;
        }

        $path = SCH_Enrollment::private_dir() . '/' . $guardian->photo_file;
        if (!is_readable($path)) {
            status_header(404);
            exit;
        }

        header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
        header('Content-Length: ' . (string) filesize($path));
        // العنوان يحمل بصمة الملف فمحتواه لا يتغيّر أبدًا — والكاش الطويل هنا
        // يجعل الصورة تظهر بلا طلب، والجديدة تظهر فورًا لأن عنوانها عنوان آخر.
        header('Cache-Control: private, max-age=31536000, immutable');
        readfile($path);
        exit;
    }

    /** صورة طالب لولي أمره — مسار خاص بالتطبيق. */
    public static function send_child_photo(int $student_id): never
    {
        if (!SCH_Students::guardian_has_child(get_current_user_id(), $student_id)) {
            status_header(403);
            exit;
        }

        $student = SCH_Students::get($student_id);
        if (!$student || !$student->photo_file) {
            status_header(404);
            exit;
        }

        $path = SCH_Enrollment::private_dir() . '/' . $student->photo_file;
        if (!is_readable($path)) {
            status_header(404);
            exit;
        }

        header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=600');
        readfile($path);
        exit;
    }

    public static function photo_url(int $student_id): string
    {
        return add_query_arg('sch_kid_photo', '1', self::url('child', $student_id));
    }

    /**
     * أيقونة الإشعار وصنف لونه من نوعه.
     *
     * كانت الخريطة تعرف سبعة أنواع من ستة عشر، والباقي يسقط على «ساعة» —
     * فيرى وليّ الأمر ثماني ساعات متطابقة لا تفرّق شهادةً عن دواء عن إجازة.
     * وهذه هي **كل** الأنواع التي يبثّها النظام فعلًا.
     *
     * @return array{0:string,1:string} [اسم الأيقونة، صنف النوع]
     */
    public static function alert_look(string $type): array
    {
        $map = [
            'certificate'     => 'award',
            'attendance'      => 'user-check',
            'leave'           => 'check',
            'med'             => 'pill',
            'clinic'          => 'heart',
            'custody'         => 'route',
            'custody_silent'  => 'route',
            'transport'       => 'bus',
            'invoice'         => 'invoice',
            'finance'         => 'wallet',
            'payment'         => 'wallet',
            'reversal'        => 'wallet',
            'payroll'         => 'wallet',
            'timetable'       => 'calendar',
            'substitution'    => 'calendar',
            'note'            => 'pen',
            'message'         => 'mail',
            'alert'           => 'clock',
            'kg'              => 'sun',
            'homework_silent' => 'book',
        ];

        // «الصامت» لاحقة توجيه داخلي لا نوعٌ آخر — يُعرض بأيقونة أصله.
        $base = str_ends_with($type, '_silent') ? substr($type, 0, -7) : $type;

        return [$map[$type] ?? $map[$base] ?? 'bell', isset($map[$base]) ? $base : 'message'];
    }

    /** «اليوم» · «أمس» · وإلا التاريخ — رأس مجموعة الإشعارات. */
    public static function day_label(string $date): string
    {
        $today = current_time('Y-m-d');

        if ($date === $today) {
            return __('اليوم', 'school-system');
        }

        if ($date === gmdate('Y-m-d', strtotime($today) - DAY_IN_SECONDS)) {
            return __('أمس', 'school-system');
        }

        return wp_date('j F', strtotime($date)) ?: $date;
    }

    /**
     * وقت الحدث كما يقوله إنسان: «قبل ٢٢ دقيقة» لما قرُب، والساعة لما بَعُد.
     * التاريخ الكامل في رأس المجموعة، فلا يُكرَّر في كل سطر.
     */
    public static function when_label(string $stamp): string
    {
        $t   = strtotime($stamp);
        $now = (int) current_time('timestamp');

        if ($t === false) {
            return '';
        }

        if ($now - $t < 12 * HOUR_IN_SECONDS && $now >= $t) {
            return sprintf(
                /* translators: %s: مدة مثل «٢٢ دقيقة» */
                __('قبل %s', 'school-system'),
                human_time_diff($t, $now)
            );
        }

        return wp_date('g:i a', $t) ?: '';
    }

    /**
     * وسم وقتٍ لصفٍّ في قائمة: الساعة لليوم، ثم «أمس»، ثم التاريخ.
     *
     * و«قبل ساعتين» تبقى للحالة الحيّة وحدها (`when_label`). في قائمةٍ
     * تُفتح مرّةً في اليوم لا تقول المسافةُ من الآن شيئًا: الأب يسأل
     * «متى دخل البوابة؟» فيريد الساعة، لا بُعدها عن لحظة الفتح.
     */
    public static function stamp_label(string $stamp): string
    {
        $t = strtotime($stamp);

        if ($t === false) {
            return '';
        }

        $day = wp_date('Y-m-d', $t);

        if ($day === wp_date('Y-m-d')) {
            return wp_date('g:i a', $t) ?: '';
        }

        if ($day === wp_date('Y-m-d', (int) current_time('timestamp') - DAY_IN_SECONDS)) {
            return __('أمس', 'school-system');
        }

        return wp_date('j M', $t) ?: '';
    }

    /**
     * رابط صورة ولي الأمر — **فارغ لمن لا صورة له** فيظهر الحرف بديلًا.
     *
     * كانت تُرجع رابطًا دائمًا، فيمرّ شرط `if (avatar_url())` دومًا وتُطلب صورة
     * تردّ 404 — فيرى كلُّ من لم يرفع صورةً أيقونةَ صورة مكسورة في رأس كل شاشة.
     *
     * **والبصمة في العنوان ليست زينة:** الملف يُخدَم بكاش، والعنوان الثابت
     * يجعل المتصفح يعرض الصورة القديمة بعد تبديلها. البصمة تتغيّر مع الملف،
     * فتظهر الجديدة فورًا ويبقى الكاش طويلًا لأن كل عنوان صار ثابت المحتوى.
     */
    public static function avatar_url(): string
    {
        $guardian = SCH_Guardians::by_user(get_current_user_id());
        $file     = (string) ($guardian->photo_file ?? '');

        if ($file === '') {
            return '';
        }

        return add_query_arg([
            'sch_avatar' => '1',
            'v'          => substr(md5($file), 0, 10),
        ], self::url('account'));
    }

    private static function change_password(): never
    {
        $user    = wp_get_current_user();
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $error = '';

        if (!wp_check_password($current, $user->user_pass, $user->ID)) {
            $error = 'wrong_current';
        } elseif (strlen($new) < 8) {
            $error = 'too_short';
        } elseif ($new !== $confirm) {
            $error = 'mismatch';
        }

        if ($error !== '') {
            wp_safe_redirect(add_query_arg('err', $error, self::url('account')));
            exit;
        }

        wp_set_password($new, $user->ID);
        sch_audit('app.password_changed', 'user', $user->ID);

        // تغيير كلمة المرور يُنهي كل الجلسات — أعد تسجيل الدخول لهذا الجهاز.
        wp_set_auth_cookie($user->ID, true, is_ssl());
        wp_set_current_user($user->ID);

        wp_safe_redirect(add_query_arg('ok', '1', self::url('account')));
        exit;
    }

    // ---------- الدخول ----------

    private static function render_login(): never
    {
        // باب واحد للجميع: خمسة أبواب تعني خمسة روابط يحفظها المستخدم،
        // وأبًا يفتح باب الطالب فيُقال له «لا صلاحية» وهو محق في حسابه.
        wp_safe_redirect(SCH_Portal::url());
        exit;

        $error = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['sch_app_login'])) {
            $error = self::attempt_login();
        }

        self::render('login', ['error' => $error]);
    }

    private static function attempt_login(): string
    {
        if (!wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_app_login')) {
            return __('انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.', 'school-system');
        }

        $input = sanitize_text_field(wp_unslash((string) ($_POST['username'] ?? '')));
        $key   = 'sch_applock_' . md5($input . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ((int) get_transient($key) >= 5) {
            return __('محاولات كثيرة. أعد المحاولة بعد 15 دقيقة.', 'school-system');
        }

        // الدخول برقم الجوال مباشرة — أسهل على ولي الأمر من اسم مستخدم.
        $login = self::resolve_login($input);

        $user = wp_signon([
            'user_login'    => $login,
            'user_password' => (string) ($_POST['password'] ?? ''),
            'remember'      => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            set_transient($key, (int) get_transient($key) + 1, 15 * MINUTE_IN_SECONDS);
            sch_audit('app.login_failed', 'user', null, ['login' => $input]);
            return __('رقم الجوال أو كلمة المرور غير صحيحة.', 'school-system');
        }

        delete_transient($key);
        wp_set_current_user($user->ID);
        sch_audit('app.login', 'user', $user->ID);

        wp_safe_redirect(self::url());
        exit;
    }

    /** يقبل رقم الجوال أو اسم المستخدم. */
    private static function resolve_login(string $input): string
    {
        global $wpdb;

        $phone = sch_normalize_phone($input);
        if ($phone === '') {
            return $input;
        }

        $user_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT user_id FROM ' . sch_table('guardians') . ' WHERE phone = %s LIMIT 1',
            $phone
        ));

        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user instanceof WP_User) {
                return $user->user_login;
            }
        }

        return $input;
    }

    // ---------- ملفات التطبيق ----------

    private static function send_manifest(): never
    {
        header('Content-Type: application/manifest+json; charset=utf-8');

        echo wp_json_encode([
            'name'             => sch_settings('school_name', get_bloginfo('name')),
            'short_name'       => __('مدرستي', 'school-system'),
            'description'      => __('متابعة الأبناء: الباص، الحضور، الدرجات، الرسوم.', 'school-system'),
            'start_url'        => self::url(),
            'scope'            => self::url(),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'lang'             => 'ar',
            'dir'              => 'rtl',
            'background_color' => '#F1F4F3',
            'theme_color'      => '#0F6E8C',
            'icons'            => [
                ['src' => SCH_URL . 'assets/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => SCH_URL . 'assets/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    /**
     * عامل الخدمة: يخزّن هيكل التطبيق ليفتح بلا إنترنت،
     * ولا يخزّن أي صفحة فيها بيانات الطالب.
     */
    private static function send_service_worker(): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . wp_parse_url(self::url(), PHP_URL_PATH));

        $css     = sch_asset('assets/app.css');
        $version = 'sch-app-' . SCH_VERSION;

        echo "const CACHE = '{$version}';\n";
        echo "const SHELL = ['{$css}'];\n";
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

// الصفحات من الشبكة دائمًا — لا نخزّن بيانات الطلاب.
// الأصول الثابتة من الكاش أولًا.
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const isAsset = /\.(css|js|png|svg|woff2?)$/.test(new URL(req.url).pathname);

  if (isAsset) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
    return;
  }

  e.respondWith(
    fetch(req).catch(() => new Response(
      '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8">'
      + '<meta name="viewport" content="width=device-width,initial-scale=1">'
      + '<body style="font-family:system-ui;background:#F6F2EA;color:#23201B;'
      + 'display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center">'
      + '<div><h1 style="font-size:20px">لا يوجد اتصال</h1>'
      + '<p style="color:#6D6459">تحقق من الشبكة ثم أعد المحاولة.</p></div></body></html>',
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    ))
  );
});
JS;

        // مستمعا الإشعار الفوري — نصّ واحد لكل عوامل الخدمة.
        SCH_Push::sw_handlers();
        exit;
    }

    // ---------- العرض ----------

    private static function render(string $view, array $data): never
    {
        $file = SCH_PATH . 'frontend/app/' . $view . '.php';
        if (!file_exists($file)) {
            $file = SCH_PATH . 'frontend/app/home.php';
        }

        // الأرقام الهندية في التطبيق وحده — تُفعَّل عند التصيير لا عند الإقلاع
        sch_localize_output();

        $sch_view = $view;
        $sch_data = $data;

        require SCH_PATH . 'frontend/app/layout.php';
        exit;
    }

    private static function no_cache_headers(): void
    {
        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-Content-Type-Options: nosniff');

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
    }

    // ---------- مساعدات العرض ----------

    /** ملخص يوم الطالب — نفس منطق مسار API. */
    public static function today(int $student_id): array
    {
        global $wpdb;

        $today = current_time('Y-m-d');

        $attendance = $wpdb->get_row($wpdb->prepare(
            'SELECT status, method FROM ' . sch_table('attendance') . ' WHERE student_id = %d AND att_date = %s',
            $student_id,
            $today
        ));

        $sub  = SCH_Routes::subscription_of($student_id);
        $trip = null;

        if ($sub) {
            $active = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . sch_table('trips') . " WHERE route_id = %d AND trip_date = %s AND status = 'running' LIMIT 1",
                (int) $sub->route_id,
                $today
            ));

            if ($active) {
                $event = $wpdb->get_row($wpdb->prepare(
                    'SELECT type, occurred_at FROM ' . sch_table('trip_events') . '
                     WHERE trip_id = %d AND student_id = %d ORDER BY id DESC LIMIT 1',
                    (int) $active->id,
                    $student_id
                ));

                $trip = [
                    'id'        => (int) $active->id,
                    'direction' => $active->direction,
                    'state'     => $event->type ?? null,
                    'since'     => $event->occurred_at ?? null,
                    'position'  => SCH_Trips::last_position((int) $active->id),
                ];
            }
        }

        $due = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total - paid), 0) FROM " . sch_table('invoices') . "
             WHERE student_id = %d AND status IN ('open','partial')",
            $student_id
        ));

        return [
            'attendance' => $attendance,
            'transport'  => $sub,
            'trip'       => $trip,
            'balance'    => round($due, 2),
        ];
    }

    /**
     * الخط الزمني ليوم الطفل — قلب شاشة «اليوم».
     * يدمج مصادر حقيقية زمنيًا: أحداث العهدة (المسح الميداني) + الحضور +
     * ملاحظات اليوم الإيجابية. مرتّبة صعوديًا (الصباح أولًا).
     *
     * @return array<int, array{time:string, kind:string, title:string, detail:string}>
     */
    public static function today_timeline(int $student_id): array
    {
        global $wpdb;

        $today = current_time('Y-m-d');
        $rows  = [];

        // 1) أحداث العهدة — كل مسح نقل مسؤولية من جهة لجهة.
        $events = $wpdb->get_results($wpdb->prepare(
            'SELECT checkpoint, receiver_name, occurred_at FROM ' . sch_table('custody_events') . '
             WHERE student_id = %d AND DATE(occurred_at) = %s ORDER BY occurred_at ASC',
            $student_id,
            $today
        ));
        $kind_of = [
            'bus_board'  => 'bus',   'gate_in'    => 'login', 'gate_out' => 'logout',
            'early_out'  => 'alert', 'bus_alight' => 'home',  'manual'   => 'check', 'correction' => 'check',
        ];
        foreach ((array) $events as $e) {
            $cp     = (string) $e->checkpoint;
            $title  = SCH_Custody::CHECKPOINTS[$cp] ?? __('تحديث الحالة', 'school-system');
            $detail = ($cp === 'bus_alight' && $e->receiver_name)
                ? sprintf(/* translators: %s: اسم المستلم */ __('سُلّم إلى %s', 'school-system'), (string) $e->receiver_name)
                : $title;
            $rows[] = [
                '_s'     => (string) $e->occurred_at,
                'time'   => substr((string) $e->occurred_at, 11, 5),
                'kind'   => $kind_of[$cp] ?? 'check',
                'title'  => $title,
                'detail' => $detail,
            ];
        }

        // 2) الحضور — تأكيد المعلم.
        $att = $wpdb->get_row($wpdb->prepare(
            'SELECT status, created_at FROM ' . sch_table('attendance') . '
             WHERE student_id = %d AND att_date = %s',
            $student_id,
            $today
        ));
        if ($att) {
            $ok = $att->status === 'present';
            $rows[] = [
                '_s'     => (string) $att->created_at,
                'time'   => substr((string) $att->created_at, 11, 5),
                'kind'   => $ok ? 'check' : 'alert',
                'title'  => $ok ? __('حضور مؤكّد', 'school-system') : (SCH_Attendance::STATUSES[$att->status] ?? __('غياب', 'school-system')),
                'detail' => __('رصده المعلم في الفصل', 'school-system'),
            ];
        }

        // 3) ملاحظات اليوم الإيجابية — تصل ولي الأمر صامتةً في خطه الزمني.
        $notes = $wpdb->get_results($wpdb->prepare(
            'SELECT category, body, created_at FROM ' . sch_table('student_notes') . "
             WHERE student_id = %d AND DATE(created_at) = %s AND category IN ('positive','participation')
             ORDER BY created_at ASC",
            $student_id,
            $today
        ));
        foreach ((array) $notes as $n) {
            $rows[] = [
                '_s'     => (string) $n->created_at,
                'time'   => substr((string) $n->created_at, 11, 5),
                'kind'   => 'star',
                'title'  => SCH_Notes::CATEGORIES[$n->category][0] ?? __('ملاحظة', 'school-system'),
                'detail' => (string) $n->body,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['_s'], (string) $b['_s']));

        return $rows;
    }

    /**
     * «خيط اليوم» — يوم الطفل كاملًا لا ما مضى منه فقط.
     *
     * الخط الزمني وحده يعرض ما **حدث**، فالأب يقرأ آخر سطر ولا يعرف أين ينتهي
     * اليوم. والخيط يضيف المحطّات المتبقّية مفرَّغةً، فيُقرأ موضع الطفل من
     * موضع النقطة قبل أن تُقرأ كلمة واحدة:
     *
     * - `done` ما مضى · `now` أين هو الآن · `next` ما بقي.
     *
     * والمحطّات المتبقّية **مشتقّة لا مخزَّنة**: نهاية الدوام من الإعدادات،
     * والوصول للمنزل يظهر لراكب الباص وحده — فمن لا يركبه لا يُوعَد بمحطّة
     * لا تأتي.
     *
     * @return list<array{time:string,kind:string,title:string,detail:string,state:string}>
     */
    public static function day_thread(int $student_id): array
    {
        $past = self::today_timeline($student_id);
        $out  = [];

        foreach ($past as $i => $ev) {
            unset($ev['_s']);
            // آخر ما حدث هو «الآن»: هناك الطفل حتى يقع الحدث التالي.
            $ev['state'] = ($i === count($past) - 1) ? 'now' : 'done';
            $out[]       = $ev;
        }

        $student = SCH_Students::get($student_id);
        $state   = (string) ($student->custody_state ?? '');
        $rides   = SCH_Routes::subscription_of($student_id) !== null;
        $end     = (string) sch_settings('attendance_day_end', '13:00');

        // يوم لم يبدأ: محطّة واحدة تقول ذلك — **ولا محطّات قادمة**.
        // «الانصراف ١:٠٠» أمام طفل ما زال في سريره وعدٌ لا خبر، ويوم الجمعة
        // يجعله وعدًا كاذبًا.
        if ($out === []) {
            return [[
                'time'   => '',
                'kind'   => 'check',
                'title'  => __('لم يبدأ يومه بعد', 'school-system'),
                'detail' => __('تظهر أول محطّة فور صعوده الباص أو دخوله البوابة', 'school-system'),
                'state'  => 'now',
            ]];
        }

        $seen = array_column($out, 'kind');

        // الانصراف: لمن هو في المدرسة الآن ولم يخرج بعد.
        if ($state === 'at_school' && !in_array('logout', $seen, true)) {
            $out[] = [
                'time'   => $end,
                'kind'   => 'logout',
                'title'  => __('الانصراف', 'school-system'),
                'detail' => __('يخرج من البوابة', 'school-system'),
                'state'  => 'next',
            ];
        }

        // الوصول للمنزل: لراكب الباص وحده — فمن لا يركبه لا يُوعَد بمحطّة لا تأتي.
        if ($rides && $state !== 'home' && !in_array('home', $seen, true)) {
            $out[] = [
                'time'   => '',
                'kind'   => 'home',
                'title'  => __('يصل المنزل', 'school-system'),
                'detail' => __('بعد رحلة العودة', 'school-system'),
                'state'  => 'next',
            ];
        }

        return $out;
    }

    /**
     * ما يراه ولي الأمر من السجل الصحي: الحساسية وفصيلة الدم فقط.
     * الأمراض المزمنة والملاحظات تبقى للعيادة — عرضها في تطبيق يُفتح يوميًا
     * يوسّع دائرة الاطلاع بلا فائدة تُذكر لولي الأمر الذي يعرفها أصلًا.
     */
    public static function child_health(int $student_id): array
    {
        global $wpdb;

        if (!SCH_Students::guardian_has_child(get_current_user_id(), $student_id)) {
            return ['allergies' => '', 'blood_type' => ''];
        }

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT allergies, blood_type FROM ' . sch_table('student_health') . ' WHERE student_id = %d',
            $student_id
        ));

        return [
            'allergies'  => (string) ($row->allergies ?? ''),
            'blood_type' => (string) ($row->blood_type ?? ''),
        ];
    }

    public static function trip_state_label(?string $state): string
    {
        return match ($state) {
            'board'    => __('صعد إلى الباص', 'school-system'),
            'alight'   => __('نزل من الباص', 'school-system'),
            'handover' => __('تم التسليم', 'school-system'),
            'absent'   => __('لم يحضر للباص', 'school-system'),
            default    => __('لم يُسجَّل صعوده بعد', 'school-system'),
        };
    }
}
