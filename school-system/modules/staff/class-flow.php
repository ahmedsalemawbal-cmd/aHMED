<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * محرك سير العمل.
 *
 * السجل الناقص لا يشتكي: يجلس صامتًا حتى تكتشف في سبتمبر أربعين طالبًا بلا فصول.
 * **فالنظام هو من يشتكي عن الموظف، لا الموظف من النظام.**
 *
 * وثلاث قواعد تحكمه:
 *
 * 1. **يقترح ولا يمنع.** معالج يحبسك في تسلسل أسوأ من غيابه — الموظف المتمرس
 *    يعرف ما يفعل، وحبسه يجعله يكره النظام. القائمة تعرض ما بقي وتترك له الاختيار.
 * 2. **يقول لماذا لا ماذا فقط.** «ربط ولي الأمر — بدونه لا إشعار ولا تطبيق ولا من
 *    يستلمه». من يفهم السبب لا ينساه.
 * 3. **«لا ينطبق» خيار صريح.** بلاه لن يصل العدّاد صفرًا أبدًا، وسيتجاهله الجميع
 *    بعد شهر. نصف الطلاب لا يشتركون في النقل.
 */
final class SCH_Flow
{
    /**
     * تعريف الخطوات.
     *
     * كل خطوة: [التسمية, الأيقونة, السبب, إجبارية؟, دالة الاكتمال, دالة الرابط]
     * ودالة الاكتمال ترجع null للخطوة اليدوية التي تُعلَّم بالضغط.
     */
    public static function steps(string $flow): array
    {
        return match ($flow) {
            'student' => [
                'reg' => [
                    __('تسجيل الطالب', 'school-system'), 'user',
                    __('يتولّد الرقم الأكاديمي ورمز البطاقة وحساب دخوله', 'school-system'),
                    true, static fn (int $id): bool => SCH_Students::get($id) !== null,
                    static fn (int $id): string => SCH_Dashboard::url('students', $id),
                ],
                'class' => [
                    __('تحديد الصف والشعبة', 'school-system'), 'grid',
                    __('بدونه لا حضور ولا جدول ولا معلم — النظام لا يعرف أين يضعه', 'school-system'),
                    true, static fn (int $id): bool => SCH_Students::current_class($id) !== null,
                    static fn (int $id): string => SCH_Dashboard::url('classes'),
                ],
                'guardian' => [
                    __('ربط ولي الأمر', 'school-system'), 'users',
                    __('بدونه لا إشعار ولا تطبيق ولا من يستلمه عند البوابة', 'school-system'),
                    true, [self::class, 'has_guardian'],
                    static fn (int $id): string => SCH_Dashboard::url('guardians'),
                ],
                'fees' => [
                    __('الرسوم الدراسية', 'school-system'), 'wallet',
                    __('تُولَّد الفاتورة وأقساطها', 'school-system'),
                    true, [self::class, 'has_invoice'],
                    static fn (int $id): string => SCH_Dashboard::url('finance'),
                ],
                'bus' => [
                    __('النقل المدرسي', 'school-system'), 'bus',
                    __('اختياري — كثير من الطلاب يأتون بسيارة الأهل', 'school-system'),
                    false, static fn (int $id): bool => SCH_Routes::subscription_of($id) !== null,
                    static fn (int $id): string => SCH_Dashboard::url('transport'),
                ],
                'app' => [
                    __('تفعيل التطبيق', 'school-system'), 'badge',
                    __('سلّم اسم المستخدم وكلمة المرور لولي الأمر ليدخل التطبيق', 'school-system'),
                    false, null,
                    static fn (int $id): string => SCH_Dashboard::url('students', $id),
                ],
                'card' => [
                    __('طباعة البطاقة', 'school-system'), 'print',
                    __('تحمل الصف والشعبة — فلا تُطبع قبل تحديدهما', 'school-system'),
                    true, null,
                    static fn (int $id): string => SCH_Dashboard::url('badges'),
                ],
            ],

            'staff' => [
                'create' => [
                    __('بيانات الموظف', 'school-system'), 'user',
                    __('اسمه وبريده ودوره', 'school-system'),
                    true, static fn (int $id): bool => SCH_Staff::get_by_user($id) !== null,
                    static fn (int $id): string => SCH_Dashboard::url('employees'),
                ],
                'perms' => [
                    __('ضبط الصلاحيات', 'school-system'), 'shield',
                    __('ما يظهر له في النظام — بلاها يعمل بصلاحيات دوره كاملة', 'school-system'),
                    true, static fn (int $id): bool => SCH_Perms::is_custom($id),
                    static fn (int $id): string => add_query_arg('user_id', $id, SCH_Dashboard::url('perms')),
                ],
                'scope' => [
                    __('تحديد النطاق', 'school-system'), 'grid',
                    __('على مَن يرى البيانات: كل المدرسة أم مرحلته أم فصوله', 'school-system'),
                    false, static fn (int $id): bool => SCH_Perms::scope($id) !== 'all',
                    static fn (int $id): string => add_query_arg('user_id', $id, SCH_Dashboard::url('perms')),
                ],
                'handover' => [
                    __('تسليم كلمة المرور', 'school-system'), 'badge',
                    __('تظهر مرة واحدة ولا تُسترجَع — سلّمها الآن', 'school-system'),
                    true, null,
                    static fn (int $id): string => SCH_Dashboard::url('employees'),
                ],
            ],

            'route' => [
                'create' => [
                    __('إنشاء المسار', 'school-system'), 'route',
                    __('اسمه واتجاهه', 'school-system'),
                    true, static fn (int $id): bool => SCH_Routes::get($id) !== null,
                    static fn (int $id): string => SCH_Dashboard::url('transport'),
                ],
                'stops' => [
                    __('نقاط التوقف', 'school-system'), 'route',
                    __('بترتيبها — منها يعرف النظام من ينزل أولًا', 'school-system'),
                    true, static fn (int $id): bool => SCH_Routes::stops($id) !== [],
                    static fn (int $id): string => SCH_Dashboard::url('transport'),
                ],
                'coords' => [
                    __('إحداثيات النقاط', 'school-system'), 'route',
                    __('بدونها لا تظهر الخريطة لولي الأمر إطلاقًا', 'school-system'),
                    true, [self::class, 'stops_have_coords'],
                    static fn (int $id): string => SCH_Dashboard::url('transport'),
                ],
                'bus' => [
                    __('إسناد حافلة', 'school-system'), 'bus',
                    __('بلا حافلة لا تبدأ رحلة', 'school-system'),
                    true, [self::class, 'route_has_bus'],
                    static fn (int $id): string => SCH_Dashboard::url('transport'),
                ],
                'subs' => [
                    __('اشتراك الطلاب', 'school-system'), 'users',
                    __('من يركب هذا المسار', 'school-system'),
                    true, [self::class, 'route_has_subs'],
                    static fn (int $id): string => SCH_Dashboard::url('transport'),
                ],
            ],

            // إعداد النظام: مكانه الطبيعي داخل النظام لا في ملف وورد
            'setup' => [
                'school' => [
                    __('اسم المدرسة', 'school-system'), 'cog',
                    __('يظهر في البطاقات والتطبيقات والتقارير', 'school-system'),
                    true, static fn (): bool => trim((string) sch_settings('school_name', '')) !== '',
                    static fn (): string => SCH_Dashboard::url('settings'),
                ],
                'year' => [
                    __('السنة الدراسية', 'school-system'), 'calendar',
                    __('بدونها لن يعمل شيء — الشعب والجداول والاشتراكات كلها مرتبطة بها', 'school-system'),
                    true, static fn (): bool => SCH_Years::current_id() > 0,
                    static fn (): string => SCH_Dashboard::url('settings'),
                ],
                'classes' => [
                    __('الصفوف والشعب', 'school-system'), 'grid',
                    __('ومرحلة كل شعبة — منها يعرف النظام مشرفها تلقائيًا', 'school-system'),
                    true, static fn (): bool => SCH_Classes::list() !== [],
                    static fn (): string => SCH_Dashboard::url('classes'),
                ],
                'subjects' => [
                    __('المواد الدراسية', 'school-system'), 'book',
                    __('تُربط بالجدول والدرجات والواجبات', 'school-system'),
                    true, static fn (): bool => SCH_Subjects::all() !== [],
                    static fn (): string => SCH_Dashboard::url('subjects'),
                ],
                'principal' => [
                    __('مدير المدرسة', 'school-system'), 'user',
                    __('أنت وحدك من يستطيع إنشاءه', 'school-system'),
                    true, static fn (): bool => SCH_Org::principal_user_id() !== null,
                    static fn (): string => SCH_Dashboard::url('employees'),
                ],
                'deputy' => [
                    __('الوكيل', 'school-system'), 'user',
                    __('يُنشأ من حساب المدير — يبني الجدول ويضيف المشرفين', 'school-system'),
                    true, static fn (): bool => SCH_Org::deputy_user_id() !== null,
                    static fn (): string => SCH_Dashboard::url('employees'),
                ],
                'supervisors' => [
                    __('مشرفو المراحل', 'school-system'), 'users',
                    __('واحد لكل مرحلة — إليهم تذهب الحصص الشاغرة', 'school-system'),
                    true, static fn (): bool => SCH_Org::supervisors() !== [],
                    static fn (): string => SCH_Dashboard::url('employees'),
                ],
                'students' => [
                    __('تسجيل الطلاب', 'school-system'), 'user',
                    __('أو استيرادهم دفعة من ملف', 'school-system'),
                    true, static fn (): bool => SCH_Views::count_students() > 0,
                    static fn (): string => SCH_Dashboard::url('students'),
                ],
                'guardians' => [
                    __('أولياء الأمور', 'school-system'), 'users',
                    __('واربط كل طالب بوليّه', 'school-system'),
                    true, [self::class, 'has_any_guardian'],
                    static fn (): string => SCH_Dashboard::url('guardians'),
                ],
                'transport' => [
                    __('الحافلات والمسارات', 'school-system'), 'bus',
                    __('اختياري — لو كانت مدرستك بلا نقل', 'school-system'),
                    false, static fn (): bool => SCH_Routes::all() !== [],
                    static fn (): string => SCH_Dashboard::url('transport'),
                ],
                'timetable' => [
                    __('نشر جدول الحصص', 'school-system'), 'calendar',
                    __('قبل النشر لا يرى المعلمون حصصهم', 'school-system'),
                    true, static fn (): bool => SCH_Org::timetable_published(),
                    static fn (): string => SCH_Dashboard::url('timetable'),
                ],
                'cron' => [
                    __('تفعيل المؤقت', 'school-system'), 'clock',
                    __('بدونه لن يصلك إنذار واحد — الإنذارات والأدوية وإشعار الباص كلها به', 'school-system'),
                    true, null,
                    static fn (): string => SCH_Dashboard::url('settings'),
                ],
                'cache' => [
                    __('استثناء صفحات النظام من الكاش', 'school-system'), 'shield',
                    __('لولاه سيرى الحارس كشفًا قديمًا', 'school-system'),
                    true, null,
                    static fn (): string => SCH_Dashboard::url('settings'),
                ],
                'email' => [
                    __('إعداد البريد', 'school-system'), 'mail',
                    __('لتصل رسائل استعادة كلمة المرور', 'school-system'),
                    false, null,
                    static fn (): string => SCH_Dashboard::url('settings'),
                ],
            ],

            default => [],
        };
    }

    // ---------- الحالة ----------

    /** @return array{steps:array,done:int,total:int,next:?string} */
    public static function progress(string $flow, int $entity_id = 0): array
    {
        $marks = self::marks($flow, $entity_id);
        $out   = [];
        $done  = 0;
        $next  = null;

        foreach (self::steps($flow) as $key => [$label, $icon, $why, $required, $checker, $link]) {
            $mark = $marks[$key] ?? '';

            if ($mark === 'na') {
                $state = 'na';
            } elseif ($mark === 'done') {
                $state = 'done';
            } elseif (is_callable($checker)) {
                $state = call_user_func($checker, $entity_id) ? 'done' : 'todo';
            } else {
                $state = 'todo';
            }

            if ($state === 'done' || $state === 'na') {
                $done++;
            } elseif ($next === null) {
                $next  = $key;
                $state = 'current';
            }

            $out[$key] = [
                'label'    => $label,
                'icon'     => $icon,
                'why'      => $why,
                'required' => (bool) $required,
                'state'    => $state,
                'manual'   => !is_callable($checker),
                'url'      => is_callable($link) ? call_user_func($link, $entity_id) : '',
            ];
        }

        return ['steps' => $out, 'done' => $done, 'total' => count($out), 'next' => $next];
    }

    public static function is_complete(string $flow, int $entity_id = 0): bool
    {
        $p = self::progress($flow, $entity_id);

        return $p['next'] === null;
    }

    // ---------- العلامات ----------

    private static function marks(string $flow, int $entity_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT step, state FROM ' . sch_table('flow_marks') . ' WHERE flow = %s AND entity_id = %d',
            $flow,
            $entity_id
        )) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->step] = (string) $r->state;
        }

        return $out;
    }

    public static function mark(string $flow, int $entity_id, string $step, string $state): bool
    {
        global $wpdb;

        if (!isset(self::steps($flow)[$step]) || !in_array($state, ['done', 'na', 'todo'], true)) {
            return false;
        }

        if ($state === 'todo') {
            $wpdb->delete(sch_table('flow_marks'), [
                'flow' => $flow, 'entity_id' => $entity_id, 'step' => $step,
            ]);

            return true;
        }

        $wpdb->replace(sch_table('flow_marks'), [
            'flow'       => $flow,
            'entity_id'  => $entity_id,
            'step'       => $step,
            'state'      => $state,
            'user_id'    => get_current_user_id() ?: null,
            'created_at' => sch_now(),
        ]);

        return true;
    }

    // ---------- فاحصات ----------

    public static function has_guardian(int $student_id): bool
    {
        return SCH_Guardians::of_student($student_id) !== [];
    }

    public static function has_invoice(int $student_id): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT 1 FROM ' . sch_table('invoices') . ' WHERE student_id = %d LIMIT 1',
            $student_id
        ));
    }

    public static function has_any_guardian(int $unused = 0): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var('SELECT 1 FROM ' . sch_table('guardian_student') . ' LIMIT 1');
    }

    public static function stops_have_coords(int $route_id): bool
    {
        $stops = SCH_Routes::stops($route_id);

        if ($stops === []) {
            return false;
        }

        foreach ($stops as $s) {
            if ($s->lat === null || $s->lng === null) {
                return false;
            }
        }

        return true;
    }

    public static function route_has_bus(int $route_id): bool
    {
        $route = SCH_Routes::get($route_id);

        return $route !== null && !empty($route->bus_id);
    }

    public static function route_has_subs(int $route_id): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM " . sch_table('transport_subs') . "
             WHERE route_id = %d AND status = 'active' LIMIT 1",
            $route_id
        ));
    }

    // ---------- الفجوات ----------

    /** النواقص عبر النظام كله — يظهر في يوم الوكيل. */
    public static function gaps(): array
    {
        global $wpdb;

        $out = [];

        $no_class = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . sch_table('students') . " s
             WHERE s.status = 'active'
               AND NOT EXISTS (SELECT 1 FROM " . sch_table('enrollments') . " e
                               WHERE e.student_id = s.id AND e.status = 'active')"
        );
        if ($no_class > 0) {
            $out[] = ['n' => $no_class, 'label' => __('طالبًا بلا شعبة', 'school-system'),
                      'why' => __('لا حضور ولا جدول لهم', 'school-system'),
                      'url' => SCH_Dashboard::url('classes'), 'level' => 'bad'];
        }

        $no_guardian = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . sch_table('students') . " s
             WHERE s.status = 'active'
               AND NOT EXISTS (SELECT 1 FROM " . sch_table('guardian_student') . " g
                               WHERE g.student_id = s.id)"
        );
        if ($no_guardian > 0) {
            $out[] = ['n' => $no_guardian, 'label' => __('بلا ولي أمر', 'school-system'),
                      'why' => __('لا يصلهم إشعار ولا يستلمهم أحد', 'school-system'),
                      'url' => SCH_Dashboard::url('guardians'), 'level' => 'bad'];
        }

        $no_coords = (int) $wpdb->get_var(
            'SELECT COUNT(*) FROM ' . sch_table('route_stops') . ' WHERE lat IS NULL OR lng IS NULL'
        );
        if ($no_coords > 0) {
            $out[] = ['n' => $no_coords, 'label' => __('نقطة توقف بلا إحداثيات', 'school-system'),
                      'why' => __('لا تظهر خريطة الباص لأوليائهم', 'school-system'),
                      'url' => SCH_Dashboard::url('transport'), 'level' => 'warn'];
        }

        $no_fees = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . sch_table('students') . " s
             WHERE s.status = 'active'
               AND NOT EXISTS (SELECT 1 FROM " . sch_table('invoices') . " i WHERE i.student_id = s.id)"
        );
        if ($no_fees > 0) {
            $out[] = ['n' => $no_fees, 'label' => __('بلا رسوم', 'school-system'),
                      'why' => __('لن تُولَّد فواتيرهم', 'school-system'),
                      'url' => SCH_Dashboard::url('finance'), 'level' => 'warn'];
        }

        return $out;
    }

    // ---------- العرض ----------

    /** بطاقة «التالي» — الدفعة بعد كل حفظ. */
    public static function next_card(string $flow, int $entity_id = 0): void
    {
        $p = self::progress($flow, $entity_id);

        if ($p['next'] === null) {
            ?>
            <div class="sch-next is-done">
                <span class="sch-next__ic"><?php echo sch_icon('check', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
                <span class="sch-next__t">
                    <b><?php esc_html_e('اكتمل الملف', 'school-system'); ?></b>
                    <span><?php esc_html_e('لا شيء ناقص.', 'school-system'); ?></span>
                </span>
            </div>
            <?php
            return;
        }

        $step = $p['steps'][$p['next']];
        ?>
        <div class="sch-next">
            <span class="sch-next__ic"><?php echo sch_icon('check', 22); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <span class="sch-next__t">
                <b><?php echo esc_html(sprintf(
                    /* translators: 1: المنجَز 2: الإجمالي */
                    __('أُنجز %1$s من %2$s', 'school-system'),
                    number_format_i18n($p['done']),
                    number_format_i18n($p['total'])
                )); ?></b>
                <span><?php echo esc_html(sprintf(
                    /* translators: 1: الخطوة 2: سببها */
                    __('التالي: %1$s — %2$s', 'school-system'),
                    $step['label'],
                    $step['why']
                )); ?></span>
            </span>
            <a class="sch-btn" href="<?php echo esc_url($step['url']); ?>">
                <?php echo esc_html($step['label']); ?>
            </a>
        </div>
        <?php
    }

    /** قائمة التحقق — تعمل بعد أسبوع كما تعمل الآن. */
    public static function checklist(string $flow, int $entity_id = 0, string $title = ''): void
    {
        $p   = self::progress($flow, $entity_id);
        $pct = $p['total'] > 0 ? $p['done'] / $p['total'] : 0;
        ?>
        <section class="sch-setup">
            <!-- التقدّم في الأعلى بشريط عريض ورقم كبير: أربعة عشر صفًا متطابقة
                 كانت قائمة تُمرَّر لا لوحة تُقرأ، والحلقة الصغيرة لا تُرى. -->
            <header class="sch-setup__h">
                <div>
                    <h2><?php echo esc_html($title !== '' ? $title : __('خطوات الإكمال', 'school-system')); ?></h2>
                    <p><?php esc_html_e('يقترح ولا يمنع — أكمل ما تشاء متى شئت.', 'school-system'); ?></p>
                </div>

                <span class="sch-setup__n">
                    <b><?php echo esc_html(number_format_i18n($p['done'])); ?></b>
                    <em><?php echo esc_html('/ ' . number_format_i18n($p['total'])); ?></em>
                </span>
            </header>

            <div class="sch-setup__bar">
                <i style="width:<?php echo esc_attr((string) round($pct * 100)); ?>%"></i>
            </div>

            <!-- بطاقات في شبكة لا صفوف متطابقة: ثلاث حالات مميّزة —
                 منجَزة هادئة · التالية بارزة بزرّها · لم تبدأ باهتة. -->
            <div class="sch-setup__grid">
                <?php foreach ($p['steps'] as $key => $s) : ?>
                    <article class="sch-step2 is-<?php echo esc_attr($s['state']); ?>">
                        <header>
                            <span class="sch-step2__c">
                                <?php if ($s['state'] === 'done') : ?>
                                    <?php echo sch_icon('check', 13); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                <?php elseif ($s['state'] === 'na') : ?>
                                    <?php echo sch_icon('x', 12); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                <?php endif; ?>
                            </span>

                            <?php if ($s['state'] === 'current') : ?>
                                <em class="sch-step2__now"><?php esc_html_e('التالي', 'school-system'); ?></em>
                            <?php elseif ($s['state'] !== 'done' && $s['state'] !== 'na' && !$s['required']) : ?>
                                <em class="sch-step2__opt"><?php esc_html_e('اختياري', 'school-system'); ?></em>
                            <?php endif; ?>
                        </header>

                        <b><?php echo esc_html($s['label']); ?></b>
                        <p><?php echo esc_html($s['why']); ?></p>

                        <?php if ($s['state'] !== 'done' && $s['state'] !== 'na') : ?>
                            <footer>
                                <a class="sch-btn sch-btn--sm" href="<?php echo esc_url($s['url']); ?>">
                                    <?php echo esc_html($s['state'] === 'current' ? __('ابدأ الآن', 'school-system') : __('ابدأ', 'school-system')); ?>
                                </a>

                                <?php if ($s['manual']) : ?>
                                    <?php self::mark_button($flow, $entity_id, $key, 'done', __('تم', 'school-system')); ?>
                                <?php endif; ?>

                                <?php if (!$s['required']) : ?>
                                    <?php self::mark_button($flow, $entity_id, $key, 'na', __('لا ينطبق', 'school-system')); ?>
                                <?php endif; ?>
                            </footer>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private static function mark_button(string $flow, int $entity_id, string $step, string $state, string $label): void
    {
        ?>
        <form method="post" class="sch-fstep__f">
            <?php wp_nonce_field('sch_flow_mark', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="flow_mark">
            <input type="hidden" name="flow" value="<?php echo esc_attr($flow); ?>">
            <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $entity_id); ?>">
            <input type="hidden" name="step" value="<?php echo esc_attr($step); ?>">
            <input type="hidden" name="state" value="<?php echo esc_attr($state); ?>">
            <button class="sch-fstep__b<?php echo $state === 'na' ? ' is-na' : ''; ?>">
                <?php echo esc_html($label); ?>
            </button>
        </form>
        <?php
    }
}
