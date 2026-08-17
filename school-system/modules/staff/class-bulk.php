<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * أدوات القوائم المشتركة.
 *
 * عمل المدرسة عمل جماعي: تطبع بطاقات شعبة كاملة، وتعتمد ثلاثين إجازة صباح الأحد،
 * وتذكّر سبعة وثلاثين متأخرًا عن الرسوم. وفعلها واحدًا واحدًا يعني ثلاثين ضغطة
 * وثلاثين تحميل صفحة لعمل ثانيتين.
 *
 * فتُكتب الطبقة مرة، وكل شاشة تعلن أفعالها فتكسب: التحديد الجماعي · العروض
 * المحفوظة · التصفية بزر مسح لكل حقل · التوسّع داخل الصف.
 *
 * وقيدان مبنيان لا اختياريان:
 * 1. **الفعل الجماعي يتبع الصلاحية** — من لا يملك «يعدّل» لا يرى الزر أصلًا.
 * 2. **الفعل الخطر يسأل مرة** — فعل جماعي بلا تأكيد يعني خطأً جماعيًا.
 */
final class SCH_Bulk
{
    /**
     * سجل الأفعال لكل شاشة.
     *
     * كل فعل: [التسمية, الأيقونة, الصلاحية, تأكيد؟, معامل؟]
     * والمعامل إما ['text', 'العنوان'] أو ['select', 'العنوان', callable]
     */
    public static function registry(): array
    {
        return [
            'students' => [
                'print_badges' => [
                    __('طباعة البطاقات', 'school-system'), 'print', 'sch_manage_students', '',
                ],
                'message_guardians' => [
                    __('مراسلة أولياء الأمور', 'school-system'), 'mail', 'sch_send_messages', '',
                    ['text', __('نص الرسالة', 'school-system')],
                ],
                'assign_route' => [
                    __('إسناد لمسار', 'school-system'), 'route', 'sch_manage_transport', '',
                    ['select', __('المسار', 'school-system'), [self::class, 'route_options']],
                ],
                // الشهادة تخرج من المدرسة ولا تعود، ويصل إشعارها لولي الأمر —
                // فهي أولى الأفعال بالسؤال لا آخرها. كان حقل التأكيد فارغًا وحده.
                'issue_cert' => [
                    __('إصدار شهادة', 'school-system'), 'award', 'sch_manage_students',
                    __('ستصدر %d شهادة، ويصل إشعار إلى ولي أمر كل طالب. متابعة؟', 'school-system'),
                    ['select', __('نوع الشهادة', 'school-system'), [self::class, 'cert_options']],
                ],
                'mark_present' => [
                    __('رصد حضور اليوم', 'school-system'), 'check', 'sch_manage_attendance',
                    __('سيُرصد حضور %d طالبًا اليوم. متابعة؟', 'school-system'),
                ],
            ],

            'leaves' => [
                'approve' => [
                    __('اعتماد', 'school-system'), 'check', 'sch_decide_leaves',
                    __('ستُعتمد %d إجازة. متابعة؟', 'school-system'),
                ],
                'reject' => [
                    __('رفض', 'school-system'), 'x', 'sch_decide_leaves',
                    __('سترفض %d إجازة. متابعة؟', 'school-system'),
                    ['text', __('سبب الرفض — إجباري', 'school-system')],
                ],
            ],

            'finance' => [
                'remind' => [
                    __('تذكير أولياء الأمور', 'school-system'), 'mail', 'sch_manage_finance', '',
                ],
                'print' => [
                    __('طباعة الفواتير', 'school-system'), 'print', 'sch_manage_finance', '',
                ],
            ],

            'alerts' => [
                'ack' => [
                    __('استلام', 'school-system'), 'check', 'sch_manage_alerts', '',
                ],
                'close' => [
                    __('إغلاق', 'school-system'), 'x', 'sch_manage_alerts',
                    __('سيُغلق %d إنذارًا. متابعة؟', 'school-system'),
                    ['text', __('ماذا فُعل؟', 'school-system')],
                ],
            ],

            'employees' => [
                'reactivate' => [
                    __('إعادة للعمل', 'school-system'), 'check', 'sch_manage_staff',
                    __('سيُعاد %d موظفًا إلى العمل. متابعة؟', 'school-system'),
                ],
                'suspend' => [
                    __('إيقاف عن العمل', 'school-system'), 'x', 'sch_manage_staff',
                    __('سيُوقَف %d موظفًا عن العمل — لا يُحذف، ويمكن إعادته لاحقًا. متابعة؟', 'school-system'),
                ],
            ],
        ];
    }

    /** الأفعال التي يملكها المستخدم في هذه الشاشة. */
    public static function allowed(string $screen): array
    {
        $out = [];

        foreach (self::registry()[$screen] ?? [] as $op => $spec) {
            if (current_user_can((string) $spec[2]) && SCH_Perms::may($screen, 'edit')) {
                $out[$op] = $spec;
            }
        }

        return $out;
    }

    public static function cert_options(): array
    {
        $out = [];

        foreach (SCH_Certificates::TYPES as $slug => $meta) {
            $out[$slug] = (string) $meta[0];
        }

        return $out;
    }

    public static function route_options(): array
    {
        $out = [];

        foreach (SCH_Routes::all() as $r) {
            $out[(int) $r->id] = (string) $r->name;
        }

        return $out;
    }

    // ---------- التنفيذ ----------

    public static function run(string $screen, string $op, array $ids, array $args = []): array|WP_Error
    {
        $spec = self::allowed($screen)[$op] ?? null;

        if (!$spec) {
            return sch_api_error('forbidden', __('لا تملك صلاحية هذا الإجراء.', 'school-system'), 403);
        }

        $ids = array_values(array_filter(array_map('absint', $ids)));

        if ($ids === []) {
            return sch_api_error('empty', __('لم تحدد شيئًا.', 'school-system'), 422);
        }

        // حد أعلى يمنع طلبًا يسقط الخادم بالخطأ أو بالعبث.
        if (count($ids) > 300) {
            return sch_api_error('too_many', __('حدّد ٣٠٠ سجل أو أقل في المرة الواحدة.', 'school-system'), 422);
        }

        $done = match ($screen . '.' . $op) {
            'students.message_guardians' => self::message_guardians($ids, (string) ($args['text'] ?? '')),
            'students.assign_route'      => self::assign_route($ids, absint($args['select'] ?? 0)),
            'students.issue_cert'        => self::issue_certs($ids, (string) ($args['select'] ?? '')),
            'students.mark_present'      => self::mark_present($ids),
            'students.print_badges'      => count($ids),
            'leaves.approve'             => self::decide_leaves($ids, 'approved'),
            'leaves.reject'              => self::decide_leaves($ids, 'rejected', (string) ($args['text'] ?? '')),
            'invoices.remind'            => self::remind_invoices($ids),
            'invoices.print'             => count($ids),
            'alerts.ack'                 => self::alerts($ids, 'ack'),
            'alerts.close'               => self::alerts($ids, 'close', (string) ($args['text'] ?? '')),
            'employees.suspend'          => self::staff_status($ids, 'suspend'),
            'employees.reactivate'       => self::staff_status($ids, 'reactivate'),
            default                      => 0,
        };

        if (is_wp_error($done)) {
            return $done;
        }

        sch_audit('bulk.' . $screen . '.' . $op, 'bulk', null, ['count' => $done]);

        return ['done' => (int) $done, 'op' => $op];
    }

    // ---------- الأفعال ----------

    /** إيقاف/إعادة موظفين دفعةً — الموظف يُوقَف لا يُحذف، وإعادته بضغطة. */
    private static function staff_status(array $ids, string $mode): int
    {
        $done = 0;

        foreach ($ids as $sid) {
            $res = $mode === 'suspend'
                ? SCH_Staff::suspend($sid)
                : SCH_Staff::update($sid, ['status' => 'active']);

            if ($res === true) {
                $done++;
            }
        }

        return $done;
    }

    private static function message_guardians(array $ids, string $text): int|WP_Error
    {
        $text = sanitize_textarea_field($text);

        if (trim($text) === '') {
            return sch_api_error('no_text', __('اكتب نص الرسالة.', 'school-system'), 422);
        }

        $sent = 0;

        foreach ($ids as $student_id) {
            $student = SCH_Students::get($student_id);

            if (!$student) {
                continue;
            }

            foreach (SCH_Guardians::of_student($student_id) as $g) {
                SCH_Comms::notify(
                    (int) $g->user_id,
                    sprintf(
                        /* translators: %s: اسم الطالب */
                        __('رسالة بخصوص %s', 'school-system'),
                        $student->full_name
                    ),
                    $text,
                    'message',
                    $student_id
                );
                $sent++;
            }
        }

        return $sent;
    }

    private static function assign_route(array $ids, int $route_id): int|WP_Error
    {
        if ($route_id === 0) {
            return sch_api_error('no_route', __('اختر المسار.', 'school-system'), 422);
        }

        $done = 0;

        foreach ($ids as $student_id) {
            $result = SCH_Routes::subscribe([
                'student_id' => $student_id,
                'route_id'   => $route_id,
            ]);

            if (!is_wp_error($result)) {
                $done++;
            }
        }

        return $done;
    }

    private static function issue_certs(array $ids, string $type): int|WP_Error
    {
        if (!isset(SCH_Certificates::TYPES[$type])) {
            return sch_api_error('bad_type', __('اختر نوع الشهادة.', 'school-system'), 422);
        }

        $done = 0;

        foreach ($ids as $student_id) {
            if (!is_wp_error(SCH_Certificates::issue(['student_id' => $student_id, 'type' => $type]))) {
                $done++;
            }
        }

        return $done;
    }

    private static function mark_present(array $ids): int
    {
        $today = current_time('Y-m-d');
        $done  = 0;

        foreach ($ids as $student_id) {
            SCH_Attendance::mark($student_id, $today, 'present', 'manual');
            $done++;
        }

        return $done;
    }

    private static function decide_leaves(array $ids, string $decision, string $note = ''): int|WP_Error
    {
        if ($decision === 'rejected' && trim($note) === '') {
            return sch_api_error('no_reason', __('اكتب سبب الرفض — الرفض الصامت يدفع ولي الأمر للاتصال.', 'school-system'), 422);
        }

        $done = 0;

        foreach ($ids as $id) {
            if (SCH_StudentLeave::decide($id, $decision, $note) === true) {
                $done++;
            }
        }

        return $done;
    }

    private static function remind_invoices(array $ids): int
    {
        global $wpdb;

        $done = 0;

        foreach ($ids as $invoice_id) {
            $inv = $wpdb->get_row($wpdb->prepare(
                'SELECT i.*, (i.total - i.paid) AS balance, s.full_name FROM ' . sch_table('invoices') . ' i
                 INNER JOIN ' . sch_table('students') . ' s ON s.id = i.student_id
                 WHERE i.id = %d',
                $invoice_id
            ));

            if (!$inv) {
                continue;
            }

            foreach (SCH_Guardians::of_student((int) $inv->student_id) as $g) {
                SCH_Comms::notify(
                    (int) $g->user_id,
                    __('تذكير برسوم مستحقة', 'school-system'),
                    sprintf('%s — %s ﷼', $inv->full_name, number_format((float) $inv->balance, 2)),
                    'invoice',
                    (int) $inv->student_id
                );
            }

            $done++;
        }

        return $done;
    }

    private static function alerts(array $ids, string $what, string $note = ''): int
    {
        $done = 0;

        foreach ($ids as $id) {
            $ok = $what === 'ack'
                ? SCH_Alerts::acknowledge($id)
                : SCH_Alerts::close($id, $note);

            if ($ok) {
                $done++;
            }
        }

        return $done;
    }

    // ---------- العرض ----------

    /** شريط الأفعال — يظهر عند التحديد ويختفي بعده. */
    public static function bar(string $screen): void
    {
        $ops = self::allowed($screen);

        if ($ops === []) {
            return;
        }
        ?>
        <div class="sch-bulk" id="sch-bulk" data-screen="<?php echo esc_attr($screen); ?>" hidden>
            <span class="sch-bulk__n" id="sch-bulk-n">٠</span>

            <?php foreach ($ops as $op => $spec) :
                $param   = $spec[4] ?? null;
                $confirm = (string) ($spec[3] ?? ''); ?>
                <button type="button"
                        data-op="<?php echo esc_attr($op); ?>"
                        data-confirm="<?php echo esc_attr($confirm); ?>"
                        <?php if ($param) : ?>
                            data-param="<?php echo esc_attr((string) $param[0]); ?>"
                            data-label="<?php echo esc_attr((string) $param[1]); ?>"
                            <?php if (($param[0] ?? '') === 'select') : ?>
                                data-options="<?php echo esc_attr((string) wp_json_encode(
                                    is_callable($param[2] ?? null) ? call_user_func($param[2]) : []
                                )); ?>"
                            <?php endif; ?>
                        <?php endif; ?>>
                    <?php echo sch_icon((string) $spec[1], 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php echo esc_html((string) $spec[0]); ?>
                </button>
            <?php endforeach; ?>

            <button type="button" class="sch-bulk__x" data-clear
                    aria-label="<?php esc_attr_e('إلغاء التحديد', 'school-system'); ?>">
                <?php echo sch_icon('x', 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </button>
        </div>

        <form method="post" id="sch-bulk-form" hidden>
            <?php wp_nonce_field('sch_bulk', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="bulk">
            <input type="hidden" name="screen" value="<?php echo esc_attr($screen); ?>">
            <input type="hidden" name="bulk_op" id="sch-bulk-op">
            <input type="hidden" name="bulk_text" id="sch-bulk-text">
            <input type="hidden" name="bulk_select" id="sch-bulk-select">
            <input type="hidden" name="ids" id="sch-bulk-ids">
        </form>
        <?php
    }

    /** مربع تحديد صف. */
    public static function pick(int $id): void
    {
        printf(
            '<label class="sch-ck"><input type="checkbox" data-pick value="%d"><span></span></label>',
            $id
        );
    }

    /** مربع تحديد الكل. */
    public static function pick_all(): void
    {
        echo '<label class="sch-ck"><input type="checkbox" data-pick-all><span></span></label>';
    }
}

/**
 * العروض المحفوظة.
 *
 * الوكيل ينظر إلى الشرائح فيعرف حال المدرسة قبل أن يضغط شيئًا:
 * «لم يُرصد حضورهم ١٢ · متأخرو الرسوم ٣٧». **العدّاد وحده يغني عن فتح الشاشة.**
 */
final class SCH_Views
{
    public static function of(string $screen): array
    {
        return match ($screen) {
            'students' => [
                ''          => [__('الكل', 'school-system'), [self::class, 'count_students']],
                'unmarked'  => [__('لم يُرصد حضورهم', 'school-system'), [self::class, 'count_unmarked']],
                'overdue'   => [__('متأخرو الرسوم', 'school-system'), [self::class, 'count_overdue']],
                'transport' => [__('مشتركو النقل', 'school-system'), [self::class, 'count_transport']],
                'watched'   => [__('تحت المتابعة', 'school-system'), [self::class, 'count_watched']],
            ],
            'leaves' => [
                ''         => [__('بانتظار القرار', 'school-system'), [SCH_StudentLeave::class, 'pending_count']],
            ],
            'alerts' => [
                ''         => [__('المفتوحة', 'school-system'), [SCH_Alerts::class, 'open_count']],
            ],
            default => [],
        };
    }

    /**
     * العروض قائمة منسدلة لا صفًّا من الحبّات.
     *
     * الحبّات كانت تأكل سطرًا كاملًا بعرض الشاشة لتقول ما يقوله زرّ واحد،
     * وتزدحم كلما زاد عدد العروض. المنسدلة تعرض **العرض النشط وعدده** وتُخفي
     * البقية حتى تُطلَب — والعدد يبقى ظاهرًا فلا تضيع المعلومة.
     */
    public static function menu(string $screen, string $active = ''): string
    {
        $views = self::of($screen);

        if ($views === []) {
            return '';
        }

        $counts = [];
        $label  = '';

        foreach ($views as $slug => [$text, $counter]) {
            $counts[$slug] = ['label' => (string) $text, 'n' => is_callable($counter) ? (int) call_user_func($counter) : 0];

            if ($slug === $active) {
                $label = (string) $text;
            }
        }

        if ($label === '') {
            $label = (string) ($views[''][0] ?? __('الكل', 'school-system'));
        }

        $now = $counts[$active] ?? reset($counts);

        $out = '<details class="sch-menu" data-menu>'
            . '<summary class="sch-menu__b">'
            . '<span>' . esc_html($label) . '</span>'
            . '<b>' . esc_html(number_format_i18n((int) $now['n'])) . '</b>'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>'
            . '</summary><div class="sch-menu__p">';

        foreach ($counts as $slug => $row) {
            $url = $slug === ''
                ? SCH_Dashboard::url($screen)
                : add_query_arg('view', $slug, SCH_Dashboard::url($screen));

            $out .= '<a class="sch-menu__i' . ($slug === $active ? ' is-on' : '') . '" href="' . esc_url($url) . '">'
                . '<svg class="sch-menu__ck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"'
                . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6.5 9.5 17 4 11.5"/></svg>'
                . '<span>' . esc_html($row['label']) . '</span>'
                . '<b>' . esc_html(number_format_i18n((int) $row['n'])) . '</b>'
                . '</a>';
        }

        return $out . '</div></details>';
    }

    public static function render(string $screen, string $active = ''): void
    {
        $views = self::of($screen);

        if ($views === []) {
            return;
        }
        ?>
        <nav class="sch-views" aria-label="<?php esc_attr_e('العروض', 'school-system'); ?>">
            <?php foreach ($views as $slug => [$label, $counter]) :
                $count = is_callable($counter) ? (int) call_user_func($counter) : 0; ?>
                <a class="sch-view<?php echo $slug === $active ? ' is-on' : ''; ?>"
                   href="<?php echo esc_url($slug === ''
                       ? SCH_Dashboard::url($screen)
                       : add_query_arg('view', $slug, SCH_Dashboard::url($screen))); ?>">
                    <?php echo esc_html($label); ?>
                    <b><?php echo esc_html(number_format_i18n($count)); ?></b>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    // ---------- العدّادات ----------

    public static function count_students(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . sch_table('students') . " WHERE status = 'active'");
    }

    public static function count_unmarked(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . sch_table('students') . " s
             LEFT JOIN " . sch_table('attendance') . " a ON a.student_id = s.id AND a.att_date = %s
             WHERE s.status = 'active' AND a.id IS NULL",
            current_time('Y-m-d')
        ));
    }

    public static function count_overdue(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT student_id) FROM " . sch_table('invoices') . "
             WHERE (total - paid) > 0 AND status <> 'paid'"
        );
    }

    public static function count_transport(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM " . sch_table('transport_subs') . " WHERE status = 'active'"
        );
    }

    public static function count_watched(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . sch_table('watchlist') . ' WHERE user_id = %d',
            get_current_user_id()
        ));
    }

    // ---------- المتابعة ----------

    /** نجمة المتابعة: الأخصائية تتابع تسعة طلاب، والمشرف يراقب عائدًا بعد غياب. */
    public static function toggle_watch(int $student_id, bool $notify = false): bool
    {
        global $wpdb;

        $user_id = get_current_user_id();

        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('watchlist') . ' WHERE user_id = %d AND student_id = %d',
            $user_id,
            $student_id
        ));

        if ($existing) {
            if ($notify) {
                $wpdb->update(sch_table('watchlist'), [
                    'notify' => (int) $existing->notify === 1 ? 0 : 1,
                ], ['id' => (int) $existing->id]);

                return true;
            }

            $wpdb->delete(sch_table('watchlist'), ['id' => (int) $existing->id]);

            return false;
        }

        $wpdb->insert(sch_table('watchlist'), [
            'user_id'    => $user_id,
            'student_id' => $student_id,
            'notify'     => $notify ? 1 : 0,
            'created_at' => sch_now(),
        ]);

        return true;
    }

    public static function watch_state(int $student_id): array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT notify FROM ' . sch_table('watchlist') . ' WHERE user_id = %d AND student_id = %d',
            get_current_user_id(),
            $student_id
        ));

        return ['watched' => $row !== null, 'notify' => $row !== null && (int) $row->notify === 1];
    }
}
