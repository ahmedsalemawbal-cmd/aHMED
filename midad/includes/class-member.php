<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * أعضاء المشترك وأدوارهم.
 *
 * **والدور يحدّد ما يراه من القوالب، لا ما يقدر عليه في النظام.** فمداد
 * ليس نظام صلاحيات: المدرسةُ تشترك، ومن دخل تحتها يرى ملفّات دوره —
 * ومديرُها لا يحتاج أن يمنع الوكيل من فتح ملفّاته، بل أن يجد كلٌّ ملفّاته
 * بلا بحثٍ في إحدى وسبعين ورقة.
 *
 * والأدوار في جدولٍ لا في ثابت: أضفتَ «محضر المختبر» من السوبر أدمن،
 * فظهرت فئتُه لمن يختارها بلا سطر شفرة.
 */
final class MDD_Member
{
    /**
     * الأدوار الستّة الأولى — بذرةٌ تُكتب مرّةً عند التفعيل، وتُدار بعدها
     * من السوبر أدمن. والمفاتيح ثابتة لأن القوالب تشير إليها.
     */
    public const SEED_ROLES = [
        'principal' => ['مدير المدرسة',   10],
        'deputy'    => ['وكيل المدرسة',   20],
        'teacher'   => ['المعلّم',          30],
        'activity'  => ['رائد النشاط',     40],
        'counselor' => ['الموجّه الطلابي',  50],
        'general'   => ['ملفّات عامّة',     90],
    ];

    /** الفئة العامّة يراها الجميع مهما كان دورهم. */
    public const GENERAL = 'general';

    private static ?object $memo = null;
    private static bool $resolved = false;

    // ═══════════════════════════ الحاليّ ═══════════════════════════

    public static function current(): ?object
    {
        if (self::$resolved) {
            return self::$memo;
        }

        self::$resolved = true;
        self::$memo = MDD_DB::membership_of(get_current_user_id());

        return self::$memo;
    }

    public static function forget(): void
    {
        self::$memo = null;
        self::$resolved = false;
    }

    public static function current_role(): string
    {
        $one = self::current();

        return $one ? (string) $one->role : '';
    }

    public static function is_owner(): bool
    {
        $one = self::current();

        return $one !== null && (int) $one->is_owner === 1;
    }

    // ═══════════════════════════ الأدوار ═══════════════════════════

    /** @return array<string,object> */
    public static function roles(): array
    {
        static $memo = null;

        if ($memo !== null) {
            return $memo;
        }

        $out = [];

        // الأدوار مكتبةٌ عامّة لا بيانات مشترك — فتُقرأ بلا نطاق.
        foreach (MDD_DB::rows('roles', [], ['order' => 'sort']) as $row) {
            $out[(string) $row->slug] = $row;
        }

        return $memo = $out;
    }

    public static function role_exists(string $slug): bool
    {
        return isset(self::roles()[$slug]);
    }

    public static function role_label(string $slug): string
    {
        $all = self::roles();

        return isset($all[$slug]) ? (string) $all[$slug]->label : $slug;
    }

    /**
     * الفئات التي يراها هذا الدور: دورُه والعامّة.
     *
     * @return array<int,string>
     */
    public static function visible_roles(string $slug = ''): array
    {
        $slug = $slug !== '' ? $slug : self::current_role();
        $out  = [];

        if ($slug !== '' && $slug !== self::GENERAL) {
            $out[] = $slug;
        }

        $out[] = self::GENERAL;

        return $out;
    }

    // ═══════════════════════════ الإضافة ═══════════════════════════

    /**
     * عضوٌ جديد تحت مدرسة.
     *
     * **والمعلّم المستقلّ لا أعضاء له** — اشتراكُه لشخصٍ واحد، وفتحُ الباب
     * لغيره يجعل معلّمًا واحدًا يشتري ويستعمله عشرة.
     *
     * @return int|WP_Error معرّف العضو
     */
    public static function add(string $name, string $phone, string $email, string $role, string $password): int|WP_Error
    {
        $tenant = MDD_Tenant::current();

        if (!$tenant) {
            return mdd_error('no_tenant', __('لا اشتراك.', 'midad'), 403);
        }
        if ((string) $tenant->type !== 'school') {
            return mdd_error('solo', __('اشتراك المعلّم لشخصٍ واحد — والمدرسة هي التي تُضيف أعضاء.', 'midad'), 403);
        }
        if (!self::is_owner()) {
            return mdd_error('forbidden', __('صاحب الاشتراك وحده يُضيف الأعضاء.', 'midad'), 403);
        }

        $seats = MDD_Access::seats();
        $used  = MDD_DB::count('members', ['status' => 'active']);

        if ($seats > 0 && $used >= $seats) {
            return mdd_error('seats', sprintf(
                /* translators: %d: عدد المقاعد */
                __('باقتك %d مقاعد وقد اكتملت — ارفع الباقة أو أوقف عضوًا.', 'midad'),
                $seats
            ), 409);
        }

        $name  = sanitize_text_field($name);
        $phone = mdd_phone($phone);
        $email = sanitize_email($email);

        if ($name === '' || $phone === '') {
            return mdd_error('invalid', __('الاسم والجوّال مطلوبان.', 'midad'), 422);
        }
        if (!self::role_exists($role)) {
            return mdd_error('bad_role', __('الدور غير معروف.', 'midad'), 422);
        }
        if (username_exists($phone) || ($email !== '' && email_exists($email))) {
            return mdd_error('exists', __('هذا الجوّال أو البريد مسجّل مسبقًا.', 'midad'), 409);
        }
        if (strlen($password) < 8) {
            return mdd_error('weak', __('كلمة المرور ثمانية أحرف فأكثر.', 'midad'), 422);
        }

        MDD_DB::begin();

        $user_id = wp_insert_user([
            'user_login'   => $phone,
            'user_pass'    => $password,
            'user_email'   => $email !== '' ? $email : $phone . '@midad.invalid',
            'display_name' => $name,
            'role'         => 'mdd_member',
        ]);

        if (is_wp_error($user_id)) {
            MDD_DB::rollback();

            return mdd_error('user_failed', $user_id->get_error_message(), 500);
        }

        $id = MDD_DB::insert('members', [
            'user_id'  => $user_id,
            'role'     => $role,
            'is_owner' => 0,
            'status'   => 'active',
        ]);

        if ($id <= 0) {
            wp_delete_user($user_id);
            MDD_DB::rollback();

            return mdd_error('db_error', __('تعذّرت الإضافة — ', 'midad') . MDD_DB::last_error(), 500);
        }

        MDD_DB::commit();
        mdd_audit('member.added', 'member', $id, ['role' => $role]);

        return $id;
    }

    /**
     * إيقاف عضو — ولا حذف.
     *
     * الحذف يترك ملفّاته بلا صاحب، وإعادتَه تعني حسابًا جديدًا. والإيقاف
     * يمنع الدخول ويُبقي الأثر — وهي قاعدة أُصول نفسها في الموظّفين.
     */
    public static function suspend(int $member_id): bool|WP_Error
    {
        if (!self::is_owner()) {
            return mdd_error('forbidden', __('صاحب الاشتراك وحده يوقف الأعضاء.', 'midad'), 403);
        }

        $one = MDD_DB::one('members', $member_id);

        if (!$one) {
            return mdd_error('not_found', __('العضو غير موجود.', 'midad'), 404);
        }
        if ((int) $one->is_owner === 1) {
            return mdd_error('owner', __('لا يُوقف صاحب الاشتراك نفسه.', 'midad'), 409);
        }

        MDD_DB::update('members', $member_id, ['status' => 'suspended']);
        mdd_audit('member.suspended', 'member', $member_id);

        return true;
    }

    public static function restore(int $member_id): bool|WP_Error
    {
        if (!self::is_owner()) {
            return mdd_error('forbidden', __('صاحب الاشتراك وحده يُعيد الأعضاء.', 'midad'), 403);
        }

        if (!MDD_DB::one('members', $member_id)) {
            return mdd_error('not_found', __('العضو غير موجود.', 'midad'), 404);
        }

        MDD_DB::update('members', $member_id, ['status' => 'active']);
        mdd_audit('member.restored', 'member', $member_id);

        return true;
    }

    /** @return array<int,object> أعضاء المشترك بأسمائهم */
    public static function all(): array
    {
        $rows = MDD_DB::rows('members', [], ['order' => 'id']);

        foreach ($rows as $row) {
            $user = get_userdata((int) $row->user_id);
            $row->name  = $user ? (string) $user->display_name : '—';
            $row->login = $user ? (string) $user->user_login : '';
        }

        return $rows;
    }
}
