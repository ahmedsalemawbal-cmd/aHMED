<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * المشترك — مدرسةٌ أو معلّم.
 *
 * وهو **جذر كل شيء في مداد** كما أن السنة الدراسية جذر كل شيء في أُصول:
 * لا صفَّ بيانات بلا مشتركٍ يملكه، ولا شاشةَ تُفتح بلا معرفةِ من يقف
 * خلفها. و`MDD_DB` تُحقنه في كل استعلام، وهذا الصنف يقول من هو.
 */
final class MDD_Tenant
{
    /** نوعا الاشتراك — ولكلٍّ سعرُه وخدماتُه. */
    public const TYPES = [
        'school'  => 'مدرسة',
        'teacher' => 'معلّم',
    ];

    /**
     * حالات الاشتراك الأربع.
     *
     * `trial` تجربةٌ سارية · `active` مشتركٌ مدفوع · `expired` انتهى ولم
     * يُجدَّد · `suspended` أوقفته الإدارة. والفرق بين الأخيرين مقصود:
     * المنتهي يُعرَض عليه الدفع، والموقوف يُعرَض عليه التواصل.
     */
    public const STATES = [
        'trial'     => 'تجربة مجانية',
        'active'    => 'اشتراك ساري',
        'expired'   => 'انتهى الاشتراك',
        'suspended' => 'موقوف',
    ];

    /** أيّام التجربة — ثابتٌ واحد يقرؤه الإنشاء والبوّابة والشاشة. */
    public const TRIAL_DAYS = 7;

    private static ?int $memo_id = null;
    private static bool $resolved = false;

    // ═══════════════════════════ من الحاليّ ═══════════════════════════

    /**
     * معرّف مشترك المستخدم الحاليّ — أو صفر.
     *
     * يُحلّ مرّةً في الطلب: `membership_of()` استعلامٌ، و`MDD_DB` تناديه
     * قبل كل قراءةٍ وكتابة — فبلا ذاكرةٍ تصير مئات الاستعلامات المتطابقة
     * في رسمة الصفحة الواحدة. (وهو الدرس نفسه من `SCH_Perms` في أُصول.)
     */
    public static function current_id(): int
    {
        if (self::$resolved) {
            return self::$memo_id ?? 0;
        }

        self::$resolved = true;
        $user = get_current_user_id();

        if ($user <= 0) {
            return 0;
        }

        $member = MDD_DB::membership_of($user);
        self::$memo_id = $member ? (int) $member->tenant_id : null;

        return self::$memo_id ?? 0;
    }

    /** يُنادى بعد كل تغييرٍ في العضوية — وإلّا عمل الطلب بمشتركٍ سابق. */
    public static function forget(): void
    {
        self::$memo_id  = null;
        self::$resolved = false;
    }

    /** صفّ المشترك الحاليّ. */
    public static function current(): ?object
    {
        $id = self::current_id();

        return $id > 0 ? MDD_DB::one('tenants', $id) : null;
    }

    // ═══════════════════════════ الإنشاء ═══════════════════════════

    /**
     * مشتركٌ جديد ومالكُه — في معاملةٍ واحدة.
     *
     * **ولا يُترك نصفُه.** مشتركٌ بلا مالك لا يستطيع أحدٌ الدخول إليه، ومالكٌ
     * بلا مشترك يدخل فلا يجد شيئًا — وكلاهما يحتاج تدخّلًا يدويًّا في قاعدةٍ
     * فيها ألف صفّ. والتراجع هنا يجعل الفشل «لم يحدث شيء».
     *
     * @param array{type:string,name:string,phone:string,email:string,password:string,role:string} $d
     * @return array{tenant_id:int,user_id:int}|WP_Error
     */
    public static function create(array $d): array|WP_Error
    {
        $type = (string) ($d['type'] ?? '');
        $name = sanitize_text_field((string) ($d['name'] ?? ''));
        $phone = mdd_phone((string) ($d['phone'] ?? ''));
        $email = sanitize_email((string) ($d['email'] ?? ''));

        if (!array_key_exists($type, self::TYPES)) {
            return mdd_error('bad_type', __('نوع الاشتراك غير معروف.', 'midad'), 422);
        }
        if ($name === '') {
            return mdd_error('no_name', __('الاسم مطلوب.', 'midad'), 422);
        }
        if ($phone === '') {
            return mdd_error('bad_phone', __('رقم الجوّال غير صحيح.', 'midad'), 422);
        }
        if ($email !== '' && !is_email($email)) {
            return mdd_error('bad_email', __('البريد غير صحيح.', 'midad'), 422);
        }

        // الجوّال اسم الدخول — فوجودُه يعني حسابًا قائمًا لا حسابًا جديدًا.
        if (username_exists($phone) || ($email !== '' && email_exists($email))) {
            return mdd_error('exists', __('هذا الجوّال أو البريد مسجّل مسبقًا — سجّل الدخول.', 'midad'), 409);
        }

        $password = (string) ($d['password'] ?? '');

        if (strlen($password) < 8) {
            return mdd_error('weak', __('كلمة المرور ثمانية أحرف فأكثر.', 'midad'), 422);
        }

        $role = sanitize_key((string) ($d['role'] ?? ''));

        // المعلّم المستقلّ دورُه معلّم دائمًا؛ والمدرسة يختار مالكُها دورَه.
        if ($type === 'teacher' || !MDD_Member::role_exists($role)) {
            $role = 'teacher';
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

        $now   = mdd_now();
        $trial = gmdate('Y-m-d H:i:s', (int) strtotime($now . ' +' . self::TRIAL_DAYS . ' days'));

        // الإنشاء يسبق وجود المستأجر، فلا نطاق يُحقَن — و`tenants` يُقيَّد
        // بـ`id` لا بـ`tenant_id`، فالإدراج يمرّ بلا نطاق بحكم البنية.
        $tenant_id = MDD_DB::as_tenant(0, static fn (): int => MDD_DB::insert('tenants', [
            'type'          => $type,
            'name'          => $name,
            'phone'         => $phone,
            'email'         => $email,
            'status'        => 'trial',
            'trial_ends_at' => $trial,
        ]));

        if ($tenant_id <= 0) {
            wp_delete_user($user_id);
            MDD_DB::rollback();

            return mdd_error('db_error', __('تعذّر إنشاء الاشتراك — ', 'midad') . MDD_DB::last_error(), 500);
        }

        $member = MDD_DB::as_tenant($tenant_id, static fn (): int => MDD_DB::insert('members', [
            'user_id'  => $user_id,
            'role'     => $role,
            'is_owner' => 1,
            'status'   => 'active',
        ]));

        if ($member <= 0) {
            wp_delete_user($user_id);
            MDD_DB::rollback();

            return mdd_error('db_error', __('تعذّر إنشاء الحساب — ', 'midad') . MDD_DB::last_error(), 500);
        }

        MDD_DB::commit();
        self::forget();

        MDD_DB::as_tenant($tenant_id, static function () use ($type, $name): void {
            mdd_audit('tenant.created', 'tenant', null, ['type' => $type, 'name' => $name]);
        });

        return ['tenant_id' => $tenant_id, 'user_id' => (int) $user_id];
    }

    // ═══════════════════════════ الحالة ═══════════════════════════

    /**
     * الحالة الحقيقية الآن — **محسوبةً لا مقروءةً كما هي**.
     *
     * العمود يقول `trial`، والتجربة قد تكون انتهت قبل دقيقة. وكرونٌ يُحدّث
     * الأعمدة ليلًا يترك نافذةً كاملة يدخل فيها المنتهي مجّانًا — والحساب
     * عند القراءة لا يترك نافذة. والعمود يبقى للتقارير وللسوبر أدمن.
     */
    public static function state(?object $tenant = null): string
    {
        $tenant ??= self::current();

        if (!$tenant) {
            return 'expired';
        }

        $status = (string) $tenant->status;

        if ($status === 'suspended') {
            return 'suspended';
        }

        $now = mdd_now();

        if ($status === 'active') {
            $until = (string) ($tenant->subscription_ends_at ?? '');

            return $until !== '' && $until >= $now ? 'active' : 'expired';
        }

        if ($status === 'trial') {
            $until = (string) ($tenant->trial_ends_at ?? '');

            return $until !== '' && $until >= $now ? 'trial' : 'expired';
        }

        return 'expired';
    }

    /** كم بقي من التجربة بالأيّام — صفرٌ إن انتهت. */
    public static function trial_days_left(?object $tenant = null): int
    {
        $tenant ??= self::current();

        if (!$tenant || self::state($tenant) !== 'trial') {
            return 0;
        }

        $left = (int) strtotime((string) $tenant->trial_ends_at) - (int) strtotime(mdd_now());

        return max(0, (int) ceil($left / DAY_IN_SECONDS));
    }

    /** هل يخرج المصدَّر بعلامةٍ مائية؟ — التجربة وحدها. */
    public static function is_trial(?object $tenant = null): bool
    {
        return self::state($tenant) === 'trial';
    }

    public static function type_label(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }
}
