<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * بوّابة الاشتراك — **فحصٌ واحد لا فحصٌ في كل شاشة**.
 *
 * الشاشة التي تُنسى هي التي تُفتح مجّانًا. ولذلك لا تسأل شاشةٌ في مداد
 * «هل دفع؟» — يسأل الموجّه مرّةً قبل أن يصل الطلب إلى أيّ شاشة، ومن لم
 * يدفع لا يبلغ إلّا صفحات الدفع والحساب والخروج.
 *
 * وأربع حالات لا ثلاث: **المنتهي غير الموقوف**. الأوّل يُعرَض عليه الدفع
 * لأنه يريد الاستمرار، والثاني تُعرَض عليه وسيلةُ تواصل لأن الإدارة أوقفته
 * لسبب — وعرضُ زرّ الدفع عليه يأخذ ماله ولا يفتح له شيئًا.
 */
final class MDD_Access
{
    /** ما يبلغه المشترك مهما كانت حالته — وإلّا حُبس بلا مخرج. */
    private const ALWAYS = ['billing', 'account', 'logout', 'contact'];

    private static ?array $memo_features = null;

    /** `false` تعني «لم تُحسَب بعد» و`null` تعني «حُسبت ولا باقة». */
    private static mixed $memo_plan = false;

    // ═══════════════════════════ البوّابة ═══════════════════════════

    /**
     * الحارس — يُنادى مرّةً في الموجّه قبل أيّ شاشة.
     *
     * ولا يُرجع قيمةً تُفحَص: من يمرّ يكمل، ومن لا يمرّ يُحوَّل ويُنهى
     * الطلب. وقيمةٌ تُرجَع تعني منادِيًا ينساها.
     */
    public static function guard(string $section): void
    {
        if (in_array($section, self::ALWAYS, true)) {
            return;
        }

        $state = MDD_Tenant::state();

        if ($state === 'trial' || $state === 'active') {
            return;
        }

        wp_safe_redirect(mdd_url('billing') . '?state=' . rawurlencode($state));
        exit;
    }

    public static function state(): string
    {
        return MDD_Tenant::state();
    }

    public static function is_open(): bool
    {
        $state = self::state();

        return $state === 'trial' || $state === 'active';
    }

    // ═══════════════════════════ الباقة وما فيها ═══════════════════════════

    /**
     * باقة المشترك — أو الباقة الافتراضية لنوعه أثناء التجربة.
     *
     * **والتجربة تفتح ما تفتحه الباقة القياسية** بقرار المالك: من جرّب
     * نصف المنتج لا يعرف ما يشتري. والفرق الوحيد علامةٌ مائية على المصدَّر.
     */
    public static function plan(): ?object
    {
        if (self::$memo_plan !== false) {
            return self::$memo_plan;
        }

        $tenant = MDD_Tenant::current();

        if (!$tenant) {
            return self::$memo_plan = null;
        }

        if (self::state() === 'active') {
            $sub = MDD_DB::first('subscriptions', ['status' => 'active'], ['order' => 'id', 'dir' => 'DESC']);

            if ($sub) {
                $plan = MDD_DB::rows('plans', ['id' => (int) $sub->plan_id], ['limit' => 1]);

                if ($plan !== []) {
                    return self::$memo_plan = $plan[0];
                }
            }
        }

        $default = MDD_DB::rows('plans', ['type' => (string) $tenant->type, 'is_default' => 1], ['limit' => 1]);

        return self::$memo_plan = ($default[0] ?? null);
    }

    /** @return array<string,mixed> */
    public static function features(): array
    {
        if (self::$memo_features !== null) {
            return self::$memo_features;
        }

        $plan = self::plan();
        $raw  = $plan ? json_decode((string) $plan->features, true) : null;

        return self::$memo_features = is_array($raw) ? $raw : [];
    }

    /** عدد المقاعد — وصفرٌ يعني بلا حدّ. */
    public static function seats(): int
    {
        $tenant = MDD_Tenant::current();

        // اشتراك المعلّم لشخصٍ واحد مهما قالت الباقة.
        if ($tenant && (string) $tenant->type === 'teacher') {
            return 1;
        }

        return max(0, (int) (self::features()['seats'] ?? 0));
    }

    public static function has_noor(): bool
    {
        return (bool) (self::features()['noor'] ?? true);
    }

    /** حصّة التحسين الشهرية — وصفرٌ يعني إطفاءها لهذه الباقة. */
    public static function ai_quota(): int
    {
        return max(0, (int) (self::features()['ai'] ?? 0));
    }

    /**
     * فئات القوالب المفتوحة لهذه الباقة.
     *
     * وفراغُها يعني **كلّ الفئات** لا لا شيء: باقةٌ جديدة تُنشأ بلا حقلٍ
     * محدَّد يجب أن تفتح لا أن تُغلق، وإلّا اشترى مشتركٌ باقةً لا تُريه شيئًا.
     *
     * @return array<int,string>
     */
    public static function categories(): array
    {
        $raw = self::features()['categories'] ?? [];

        return is_array($raw) && $raw !== [] ? array_map('strval', $raw) : array_keys(MDD_Member::roles());
    }

    // ═══════════════════════════ العلامة المائية ═══════════════════════════

    /**
     * نصّ العلامة — أو فراغ.
     *
     * **تُقرأ من هنا في المخارج الثلاثة** (الطباعة · وورد · إكسل). ونصٌّ
     * مكتوبٌ في كل مخرجٍ على حدة يعني مخرجًا يُنسى فيخرج نظيفًا في التجربة،
     * فيأخذ المشترك ما يحتاجه في سبعة أيّام ويذهب.
     */
    public static function watermark(): string
    {
        return MDD_Tenant::is_trial()
            ? __('تجربة مجانية — مداد', 'midad')
            : '';
    }

    /** يُنادى بعد الدفع أو تغيير الباقة. */
    public static function forget(): void
    {
        self::$memo_features = null;
        self::$memo_plan     = false;
    }
}
