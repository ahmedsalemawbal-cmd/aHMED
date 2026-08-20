<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * بوابة الدخول الموحّدة.
 *
 * باب واحد يدخل منه الجميع — ولي الأمر والطالب والمعلم والسائق والحارس —
 * ثم يوجّههم النظام كلًّا لتطبيقه.
 *
 * **لماذا باب واحد:** خمسة أبواب تعني خمسة روابط يحفظها المستخدم، وأبًا
 * يفتح باب الطالب فيُقال له «لا صلاحية» وهو محق في حسابه. والباب الواحد
 * يزيل السؤال: تدخل، فيعرف النظام من أنت ويأخذك حيث تنتمي.
 *
 * وإدارة النظام تبقى على `/dashboard` — لأن من يدخلها يعرف ما يفعل،
 * وفصلها يقلّل سطح الهجوم على الحساب الأهم.
 */
final class SCH_Portal
{
    public const BASE = 'login';

    /** ترتيب التوجيه: الأخص أولًا — فالمعلم قد يكون وليّ أمر أيضًا. */
    private const ROUTES = [
        ['sch_scan_gate',         [SCH_Gate::class,      'url']],
        ['sch_drive_trip',        [SCH_Driver::class,    'url']],
        ['sch_manage_attendance', [SCH_Teacher::class,   'url']],
        ['sch_student_app',       [SCH_Student::class,   'url']],
        ['sch_view_own_children', [SCH_App::class,       'url']],
    ];

    /**
     * أدوارٌ لكلٍّ منها تطبيقُه — بيتها تطبيقُها لا الداشبورد.
     *
     * الصلاحية وحدها لا تكفي للتوجيه: المعلم يملك `sch_manage_attendance`،
     * وتملكها كذلك مشرفةُ المرحلة ووكيلُ الشؤون التعليمية — وبيت هاتين
     * الداشبورد. فالدور هو الفيصل، والترتيب هنا هو ترتيب الأولوية.
     */
    private const APP_ROLES = [
        'sch_guard'    => [SCH_Gate::class,    'url'],
        'sch_driver'   => [SCH_Driver::class,  'url'],
        'sch_teacher'  => [SCH_Teacher::class, 'url'],
        'sch_student'  => [SCH_Student::class, 'url'],
        'sch_guardian' => [SCH_App::class,     'url'],
    ];

    public static function init(): void
    {
        add_action('init', [self::class, 'rewrite']);
        add_action('template_redirect', [self::class, 'route'], 1);
    }

    public static function rewrite(): void
    {
        add_rewrite_rule('^' . self::BASE . '/?$', 'index.php?sch_portal=1', 'top');
        add_rewrite_tag('%sch_portal%', '1');
    }

    public static function url(): string
    {
        return home_url('/' . self::BASE . '/');
    }

    /** الوجهة التي تخص هذا المستخدم، أو لوحة الإدارة إن كان له مجال فيها. */
    public static function home_for(int $user_id = 0): string
    {
        $user_id = $user_id ?: get_current_user_id();

        if ($user_id === 0) {
            return self::url();
        }

        /*
         * صاحب تطبيقٍ مخصّص يذهب إليه أوّلًا — قبل سؤال الداشبورد.
         *
         * كان السؤال معكوسًا: `allowed_areas()` تُسأل أوّلًا، والحارس يملك
         * `sch_scan_gate` وهي صلاحية قسم «نداء الانصراف» في الداشبورد،
         * والمعلم يملك `sch_manage_attendance` وهي صلاحية «الحضور» —
         * فكان الاثنان يُرَدّان إلى الداشبورد ولا يبلغان `/gate` و`/teacher`
         * أبدًا، مهما كان ترتيب `ROUTES` تحتها.
         *
         * ومن حمل مع دور تطبيقه دورًا إداريًّا (معلّمٌ هو مشرف مرحلة) يبقى
         * على الداشبورد: الدور الأوسع يغلب، وهو ما يتوقّعه صاحبه.
         */
        $roles = (array) (get_userdata($user_id)->roles ?? []);
        if ($roles !== [] && array_diff($roles, array_keys(self::APP_ROLES)) === []) {
            foreach (self::APP_ROLES as $role => $dest) {
                if (in_array($role, $roles, true) && is_callable($dest)) {
                    return (string) call_user_func($dest);
                }
            }
        }

        // من له مجال في الداشبورد مكانه الداشبورد — المدير والوكيل والمحاسب.
        // و`allowed_areas()` تقرأ **المستخدم الحالي**، فلا تُسأل إلا عنه: النداء
        // نيابةً عن غيره (بناء رابط إشعار في الكرون) كان سيعطي كلَّ الناس
        // داشبورد من يشغّل الطلب.
        if ($user_id === get_current_user_id() && SCH_Dashboard::allowed_areas() !== []) {
            return SCH_Dashboard::url();
        }

        foreach (self::ROUTES as [$cap, $dest]) {
            if (user_can($user_id, $cap) && is_callable($dest)) {
                return (string) call_user_func($dest);
            }
        }

        return self::url();
    }

    public static function route(): void
    {
        if (get_query_var('sch_portal') !== '1') {
            return;
        }

        if (is_user_logged_in()) {
            wp_safe_redirect(self::home_for());
            exit;
        }

        $error = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['sch_portal_login'])) {
            $error = self::attempt();
        }

        self::render($error);
    }

    private static function attempt(): string
    {
        if (!wp_verify_nonce((string) ($_POST['_sch_nonce'] ?? ''), 'sch_portal_login')) {
            return __('انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.', 'school-system');
        }

        $input = sanitize_text_field(wp_unslash((string) ($_POST['username'] ?? '')));
        $key   = 'sch_portlock_' . md5($input . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ((int) get_transient($key) >= 5) {
            return __('محاولات كثيرة. أعد المحاولة بعد ١٥ دقيقة.', 'school-system');
        }

        $user = wp_signon([
            'user_login'    => self::resolve($input),
            'user_password' => (string) ($_POST['password'] ?? ''),
            'remember'      => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            set_transient($key, (int) get_transient($key) + 1, 15 * MINUTE_IN_SECONDS);
            sch_audit('portal.login_failed', 'user', null, ['login' => $input]);

            return __('بيانات الدخول غير صحيحة.', 'school-system');
        }

        delete_transient($key);
        wp_set_current_user($user->ID);
        sch_audit('portal.login', 'user', $user->ID);

        wp_safe_redirect(self::home_for($user->ID));
        exit;
    }

    /**
     * يقبل ثلاثة مداخل ويحوّلها لاسم مستخدم:
     * رقم الجوال (ولي أمر أو موظف) · الرقم الأكاديمي (طالب) · البريد.
     * فالمستخدم يكتب ما يحفظه لا ما نحفظه نحن عنه.
     */
    private static function resolve(string $input): string
    {
        global $wpdb;

        $input = trim($input);

        if ($input === '') {
            return '';
        }

        if (is_email($input)) {
            $user = get_user_by('email', $input);

            return $user ? $user->user_login : $input;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if ($digits === '') {
            return $input;
        }

        // الرقم الأكاديمي: حساب الطالب اسمه s + الرقم.
        if (username_exists('s' . $digits)) {
            return 's' . $digits;
        }

        // جوال ولي أمر.
        $guardian = $wpdb->get_var($wpdb->prepare(
            'SELECT user_id FROM ' . sch_table('guardians') . ' WHERE phone = %s LIMIT 1',
            $digits
        ));

        if ($guardian) {
            $user = get_userdata((int) $guardian);

            if ($user) {
                return $user->user_login;
            }
        }

        // جوال موظف.
        $staff = $wpdb->get_var($wpdb->prepare(
            'SELECT user_id FROM ' . sch_table('employees') . ' WHERE phone = %s LIMIT 1',
            $digits
        ));

        if ($staff) {
            $user = get_userdata((int) $staff);

            if ($user) {
                return $user->user_login;
            }
        }

        return $input;
    }

    private static function render(string $error): never
    {
        // الحزمة التي فُتحت منها البوابة — تُلوّن الشاشة وتُحمّل بيانها
        // فيصير التثبيت من صفحة الدخول نفسها.
        $sch_pack  = isset($_GET['pack']) ? sanitize_key(wp_unslash((string) $_GET['pack'])) : '';
        $sch_packs = SCH_Apps::packs();

        if (!isset($sch_packs[$sch_pack])) {
            $sch_pack = '';
        }

        $sch_data  = ['error' => $error, 'pack' => $sch_pack];
        $sch_view  = 'portal';

        require SCH_PATH . 'frontend/portal.php';
        exit;
    }
}
