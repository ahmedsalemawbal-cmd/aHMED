<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * وحدة التسجيل: توليد المعرّفات، المستندات المحمية، السجل الصحي المقيّد.
 *
 * مبدأ التخزين: ملفات الطلاب لا تُوضع في مجلد ووردبريس العام إطلاقًا.
 * تُخزَّن في مجلد محمي وتُخدَم عبر مسار يتحقق من الصلاحية قبل كل بايت.
 */
final class SCH_Enrollment
{
    public const DOC_TYPES = [
        'id'         => 'الهوية أو الإقامة',
        'birth'      => 'شهادة الميلاد',
        'transcript' => 'آخر شهادة دراسية',
        'photo'      => 'صورة شخصية',
    ];

    public const ID_TYPES = [
        'national' => 'هوية وطنية',
        'iqama'    => 'إقامة',
        'visitor'  => 'زيارة',
    ];

    public const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    private const MAX_UPLOAD = 5242880; // 5 ميجابايت
    private const ALLOWED    = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    // ---------- المجلد المحمي ----------

    public static function private_dir(): string
    {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit($uploads['basedir']) . 'sch-private';

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        // طبقات منع الوصول المباشر — لخوادم Apache وLiteSpeed.
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
        }

        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        return $dir;
    }

    // ---------- المعرّفات ----------

    /** السنة الهجرية المعتمدة — من اسم السنة الدراسية إن أمكن، وإلا تُحسب. */
    public static function hijri_year(): int
    {
        $year = SCH_Years::current();

        if ($year && preg_match('/\b(1[3-5]\d{2})\b/', (string) $year->name, $m) === 1) {
            return (int) $m[1];
        }

        $gregorian = (int) current_time('Y');
        return (int) floor(($gregorian - 622) * 33 / 32);
    }

    /** رقم أكاديمي فريد بصيغة 14470001. */
    public static function next_academic_no(): string
    {
        global $wpdb;

        $prefix = (string) self::hijri_year();

        $last = (string) $wpdb->get_var($wpdb->prepare(
            'SELECT academic_no FROM ' . sch_table('students') . '
             WHERE academic_no LIKE %s ORDER BY academic_no DESC LIMIT 1',
            $wpdb->esc_like($prefix) . '%'
        ));

        $seq = $last !== '' ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** معرّف الطالب الداخلي: S + الرقم الأكاديمي. */
    public static function student_no(string $academic_no): string
    {
        return 'S' . $academic_no;
    }

    /** معرّف ولي الأمر: P + سنة + تسلسل. */
    public static function next_parent_no(): string
    {
        global $wpdb;

        $prefix = 'P' . self::hijri_year();

        $last = (string) $wpdb->get_var($wpdb->prepare(
            'SELECT parent_no FROM ' . sch_table('guardians') . '
             WHERE parent_no LIKE %s ORDER BY parent_no DESC LIMIT 1',
            $wpdb->esc_like($prefix) . '%'
        ));

        $seq = $last !== '' ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    // ---------- الرفع ----------

    /**
     * حفظ ملف مرفوع في المجلد المحمي.
     * يتحقق من النوع الفعلي لا من الامتداد، ويمسح بيانات EXIF من الصور.
     *
     * @return array{stored:string,name:string,mime:string,size:int}|WP_Error
     */
    public static function store_upload(array $file, int $student_id, string $doc_type): array|WP_Error
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return sch_api_error('no_file', __('لم يُرفع ملف.', 'school-system'), 422);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_UPLOAD) {
            return sch_api_error('bad_size', __('حجم الملف يتجاوز 5 ميجابايت.', 'school-system'), 422);
        }

        // النوع من محتوى الملف نفسه — الامتداد يمكن تزويره.
        $check = wp_check_filetype_and_ext($file['tmp_name'], (string) ($file['name'] ?? ''));
        $mime  = (string) ($check['type'] ?: '');

        if (!isset(self::ALLOWED[$mime])) {
            return sch_api_error('bad_type', __('الملفات المقبولة: JPG أو PNG أو PDF.', 'school-system'), 422);
        }

        $dir    = self::private_dir();
        $stored = sprintf('%d-%s-%s.%s', $student_id, $doc_type, wp_generate_password(12, false), self::ALLOWED[$mime]);
        $path   = $dir . '/' . $stored;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return sch_api_error('move_failed', __('تعذّر حفظ الملف.', 'school-system'), 500);
        }

        @chmod($path, 0600);
        self::strip_exif($path, $mime);

        return [
            'stored' => $stored,
            'name'   => sanitize_file_name((string) ($file['name'] ?? $stored)),
            'mime'   => $mime,
            'size'   => (int) filesize($path),
        ];
    }

    /**
     * مسح بيانات EXIF.
     * صور الجوال قد تحمل إحداثيات المكان الذي التُقطت فيه — أي منزل الطالب.
     */
    private static function strip_exif(string $path, string $mime): void
    {
        if ($mime !== 'image/jpeg' || !function_exists('imagecreatefromjpeg')) {
            return;
        }

        $img = @imagecreatefromjpeg($path);
        if ($img === false) {
            return;
        }

        // إعادة الترميز تُسقط كل البيانات الوصفية.
        @imagejpeg($img, $path, 88);
        imagedestroy($img);
    }

    public static function save_doc(int $student_id, string $doc_type, array $file): int|WP_Error
    {
        global $wpdb;

        if (!array_key_exists($doc_type, self::DOC_TYPES)) {
            $doc_type = 'other';
        }

        $saved = self::store_upload($file, $student_id, $doc_type);
        if (is_wp_error($saved)) {
            return $saved;
        }

        // نسخة واحدة لكل نوع — الجديدة تحل محل القديمة.
        foreach (self::docs($student_id) as $old) {
            if ($old->doc_type === $doc_type) {
                self::delete_doc((int) $old->id);
            }
        }

        $wpdb->insert(sch_table('student_docs'), [
            'student_id'  => $student_id,
            'doc_type'    => $doc_type,
            'file_name'   => $saved['name'],
            'stored_as'   => $saved['stored'],
            'mime_type'   => $saved['mime'],
            'file_size'   => $saved['size'],
            'uploaded_by' => get_current_user_id() ?: null,
            'created_at'  => sch_now(),
        ]);

        // insert_id يُحتجَز قبل sch_audit(): التدقيق يُدرج صفًا بنفسه
        // فيصير insert_id رقم صف السجل لا رقم السجل المُنشأ.
        $new_id = (int) $wpdb->insert_id;

        sch_audit('doc.uploaded', 'student', $student_id, ['type' => $doc_type]);

        return $new_id;
    }

    public static function docs(int $student_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sch_table('student_docs') . ' WHERE student_id = %d ORDER BY doc_type',
            $student_id
        )) ?: [];
    }

    public static function get_doc(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('student_docs') . ' WHERE id = %d', $id)) ?: null;
    }

    public static function delete_doc(int $id): bool
    {
        global $wpdb;

        $doc = self::get_doc($id);
        if (!$doc) {
            return false;
        }

        $path = self::private_dir() . '/' . $doc->stored_as;
        if (file_exists($path)) {
            @unlink($path);
        }

        $wpdb->delete(sch_table('student_docs'), ['id' => $id]);
        sch_audit('doc.deleted', 'student', (int) $doc->student_id, ['type' => $doc->doc_type]);

        return true;
    }

    /** حفظ صورة الطالب — نفس المجلد المحمي. */
    public static function save_photo(int $student_id, array $file): bool|WP_Error
    {
        global $wpdb;

        $saved = self::store_upload($file, $student_id, 'avatar');
        if (is_wp_error($saved)) {
            return $saved;
        }

        $student = SCH_Students::get($student_id);
        if ($student && $student->photo_file) {
            $old = self::private_dir() . '/' . $student->photo_file;
            if (file_exists($old)) {
                @unlink($old);
            }
        }

        $wpdb->update(sch_table('students'), [
            'photo_file' => $saved['stored'],
            'updated_at' => sch_now(),
        ], ['id' => $student_id]);

        return true;
    }

    // ---------- السجل الصحي ----------

    /** لا يُقرأ إلا بصلاحية sch_view_health، وكل اطلاع يُسجَّل. */
    public static function health(int $student_id): ?object
    {
        global $wpdb;

        if (!current_user_can('sch_view_health')) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . sch_table('student_health') . ' WHERE student_id = %d',
            $student_id
        ));

        if ($row) {
            sch_audit('health.viewed', 'student', $student_id);
        }

        return $row ?: null;
    }

    public static function save_health(int $student_id, array $d): bool|WP_Error
    {
        global $wpdb;

        if (!current_user_can('sch_view_health')) {
            return sch_api_error('forbidden', __('لا تملك صلاحية السجل الصحي.', 'school-system'), 403);
        }

        $blood = (string) ($d['blood_type'] ?? '');

        $data = [
            'blood_type'    => in_array($blood, self::BLOOD_TYPES, true) ? $blood : null,
            'chronic'       => sanitize_text_field((string) ($d['chronic'] ?? '')) ?: null,
            'allergies'     => sanitize_text_field((string) ($d['allergies'] ?? '')) ?: null,
            'special_needs' => sanitize_text_field((string) ($d['special_needs'] ?? '')) ?: null,
            'notes'         => sanitize_textarea_field((string) ($d['health_notes'] ?? '')) ?: null,
            'updated_by'    => get_current_user_id() ?: null,
            'updated_at'    => sch_now(),
        ];

        if (array_filter($data, static fn ($v): bool => $v !== null && $v !== '') === []) {
            return true;
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . sch_table('student_health') . ' WHERE student_id = %d',
            $student_id
        ));

        if ($existing) {
            $wpdb->update(sch_table('student_health'), $data, ['id' => (int) $existing]);
        } else {
            $wpdb->insert(sch_table('student_health'), $data + ['student_id' => $student_id]);
        }

        sch_audit('health.updated', 'student', $student_id);

        return true;
    }

    // ---------- التسجيل الكامل ----------

    /**
     * تسجيل طالب جديد بكل ما يتبعه: المعرّفات، الشعبة، المستندات، السجل الصحي، النقل.
     * كل شيء داخل معاملة واحدة — إما يتم كله أو لا شيء.
     */
    public static function register(array $d, array $files): array|WP_Error
    {
        global $wpdb;

        $parts = [
            'first_name'  => sanitize_text_field((string) ($d['first_name'] ?? '')),
            'father_name' => sanitize_text_field((string) ($d['father_name'] ?? '')),
            'grand_name'  => sanitize_text_field((string) ($d['grand_name'] ?? '')),
            'family_name' => sanitize_text_field((string) ($d['family_name'] ?? '')),
        ];

        foreach ($parts as $key => $value) {
            if ($value === '') {
                return sch_api_error('missing_name', __('أجزاء الاسم الأربعة مطلوبة.', 'school-system'), 422);
            }
        }

        $national = sanitize_text_field((string) ($d['national_id'] ?? ''));
        if (preg_match('/^\d{10}$/', $national) !== 1) {
            return sch_api_error('bad_id', __('رقم الهوية أو الإقامة يجب أن يكون 10 أرقام.', 'school-system'), 422);
        }

        $birth = sch_sanitize_date($d['birth_date'] ?? null);
        if (!$birth) {
            return sch_api_error('bad_birth', __('تاريخ الميلاد غير صحيح.', 'school-system'), 422);
        }

        $class_id = absint($d['class_id'] ?? 0);
        if ($class_id === 0 || !SCH_Classes::get($class_id)) {
            return sch_api_error('bad_class', __('اختر الفصل الدراسي.', 'school-system'), 422);
        }

        $full_name = trim(implode(' ', $parts));

        $wpdb->query('START TRANSACTION');

        $created = SCH_Students::create([
            'full_name'   => $full_name,
            'national_id' => $national,
            'birth_date'  => $birth,
            'gender'      => (string) ($d['gender'] ?? ''),
        ]);

        if (is_wp_error($created)) {
            $wpdb->query('ROLLBACK');
            return $created;
        }

        $student_id = (int) $created['id'];

        // الرقم الأكاديمي فريد — تحت التسجيل المتزامن قد يحسب طلبان الرقم نفسه،
        // فنجرّب أرقامًا متتابعة حتى ينجح الإدراج (القيد الفريد يرفض المكرَّر
        // فعليًّا لا في اللقطة). بلا هذا يبقى الطالب برقم NULL بينما يُنشأ حسابه
        // من الرقم المحسوب فينفصلان.
        $prefix = (string) self::hijri_year();
        $seq    = (int) substr(self::next_academic_no(), strlen($prefix));

        $fields = $parts + [
            'name_en'     => sanitize_text_field((string) ($d['name_en'] ?? '')) ?: null,
            'id_type'     => array_key_exists($d['id_type'] ?? '', self::ID_TYPES) ? $d['id_type'] : 'national',
            'nationality' => sanitize_text_field((string) ($d['nationality'] ?? '')) ?: null,
            'enrolled_at' => current_time('Y-m-d'),
            'updated_at'  => sch_now(),
        ];

        $academic_no = '';
        $applied     = false;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $academic_no = $prefix . str_pad((string) ($seq + $attempt), 4, '0', STR_PAD_LEFT);

            $res = $wpdb->update(sch_table('students'), $fields + [
                'academic_no' => $academic_no,
                'student_no'  => self::student_no($academic_no),
            ], ['id' => $student_id]);

            if ($res !== false) {
                $applied = true;
                break;
            }
        }

        if (!$applied) {
            $wpdb->query('ROLLBACK');
            return sch_api_error('academic_no_failed', __('تعذّر توليد رقم أكاديمي فريد — أعد المحاولة.', 'school-system'), 500);
        }

        $enrolled = SCH_Students::enroll($student_id, $class_id);
        if (is_wp_error($enrolled)) {
            $wpdb->query('ROLLBACK');
            return $enrolled;
        }

        $wpdb->query('COMMIT');

        // ما بعد المعاملة: الملفات والسجلات الملحقة.
        if (!empty($files['photo']['tmp_name'])) {
            self::save_photo($student_id, $files['photo']);
        }

        foreach (array_keys(self::DOC_TYPES) as $type) {
            $key = 'doc_' . $type;
            if (!empty($files[$key]['tmp_name'])) {
                self::save_doc($student_id, $type, $files[$key]);
            }
        }

        self::save_health($student_id, $d);

        if (!empty($d['transport_route'])) {
            SCH_Routes::subscribe([
                'student_id' => $student_id,
                'route_id'   => absint($d['transport_route']),
                'stop_id'    => absint($d['transport_stop'] ?? 0),
                'fee_amount' => (float) ($d['transport_fee'] ?? 0),
            ]);
        }

        if (!empty($d['notes'])) {
            $wpdb->update(
                sch_table('students'),
                ['notes' => sanitize_textarea_field((string) $d['notes'])],
                ['id' => $student_id]
            );
        }

        $account = self::create_student_account($student_id, $academic_no, $full_name);

        sch_audit('student.registered', 'student', $student_id, ['academic_no' => $academic_no]);

        return [
            'id'          => $student_id,
            'academic_no' => $academic_no,
            'goto_id'     => $student_id,
            'credentials' => $account,
        ];
    }

    /**
     * الخط الزمني للطالب — أحدث ما جرى له عبر كل الوحدات.
     * يجمع من النقل والحضور والدرجات والمالية والتسجيل.
     *
     * @return array<int,array{title:string,when:string}>
     */
    public static function timeline(int $student_id, int $limit = 6): array
    {
        global $wpdb;

        $out = [];

        $trip = $wpdb->get_row($wpdb->prepare(
            'SELECT type, occurred_at FROM ' . sch_table('trip_events') . '
             WHERE student_id = %d ORDER BY id DESC LIMIT 1',
            $student_id
        ));

        if ($trip) {
            $out[] = [
                'title' => match ($trip->type) {
                    'board'    => __('صعد إلى الباص', 'school-system'),
                    'alight'   => __('نزل من الباص', 'school-system'),
                    'handover' => __('تم تسليمه', 'school-system'),
                    default    => __('لم يحضر للباص', 'school-system'),
                },
                'when'  => (string) $trip->occurred_at,
            ];
        }

        $att = $wpdb->get_row($wpdb->prepare(
            'SELECT status, att_date, recorded_at FROM ' . sch_table('attendance') . '
             WHERE student_id = %d ORDER BY att_date DESC LIMIT 1',
            $student_id
        ));

        if ($att) {
            $out[] = [
                'title' => sprintf(
                    /* translators: %s: حالة الحضور */
                    __('الحضور: %s', 'school-system'),
                    SCH_Attendance::STATUSES[$att->status] ?? $att->status
                ),
                'when'  => (string) ($att->recorded_at ?: $att->att_date),
            ];
        }

        $exam = $wpdb->get_row($wpdb->prepare(
            'SELECT r.score, e.title, e.max_score, r.recorded_at
             FROM ' . sch_table('exam_results') . ' r
             INNER JOIN ' . sch_table('exams') . ' e ON e.id = r.exam_id
             WHERE r.student_id = %d AND r.score IS NOT NULL
             ORDER BY r.id DESC LIMIT 1',
            $student_id
        ));

        if ($exam) {
            $out[] = [
                'title' => sprintf(
                    '%s — %s / %s',
                    $exam->title,
                    number_format((float) $exam->score, 1),
                    number_format((float) $exam->max_score, 0)
                ),
                'when'  => (string) $exam->recorded_at,
            ];
        }

        $pay = $wpdb->get_row($wpdb->prepare(
            'SELECT p.amount, p.paid_at FROM ' . sch_table('payments') . ' p
             INNER JOIN ' . sch_table('invoices') . ' i ON i.id = p.invoice_id
             WHERE i.student_id = %d ORDER BY p.id DESC LIMIT 1',
            $student_id
        ));

        if ($pay) {
            $out[] = [
                'title' => sprintf(
                    /* translators: %s: المبلغ */
                    __('سداد دفعة — %s', 'school-system'),
                    sch_money($pay->amount)
                ),
                'when'  => (string) $pay->paid_at,
            ];
        }

        $student = SCH_Students::get($student_id);
        if ($student && $student->enrolled_at) {
            $out[] = [
                'title' => __('تسجيل في المدرسة', 'school-system'),
                'when'  => (string) $student->enrolled_at,
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($b['when'], $a['when']));

        return array_slice($out, 0, $limit);
    }

    /**
     * حساب دخول الطالب — يُنشأ تلقائيًا مع تسجيله.
     * اسم المستخدم من رقمه الأكاديمي، وكلمة المرور تظهر مرة واحدة.
     */
    public static function create_student_account(int $student_id, string $academic_no, string $name): array
    {
        global $wpdb;

        $login = 's' . $academic_no;

        if (username_exists($login)) {
            return [];
        }

        $password = wp_generate_password(10, false);

        $user_id = wp_insert_user([
            'user_login'   => $login,
            'user_email'   => $login . '@student.local',
            'user_pass'    => $password,
            'display_name' => $name,
            'first_name'   => $name,
            'role'         => 'sch_student',
        ]);

        if (is_wp_error($user_id)) {
            return [];
        }

        $wpdb->update(sch_table('students'), ['user_id' => (int) $user_id], ['id' => $student_id]);

        return ['login' => $login, 'password' => $password];
    }

    /**
     * توليد كلمة مرور جديدة لحساب الطالب.
     *
     * الأولى تظهر مرة واحدة عند التسجيل ثم تُفقد — ومن يفقدها كان يعلق.
     * فصارت تُولَّد من جديد بضغطة، ويُنشأ الحساب إن لم يكن موجودًا أصلًا
     * (الطلاب المسجَّلون قبل هذه الميزة بلا حساب).
     */
    public static function reset_student_password(int $student_id): array|WP_Error
    {
        global $wpdb;

        $student = SCH_Students::get($student_id);

        if (!$student) {
            return sch_api_error('no_student', __('الطالب غير موجود.', 'school-system'), 404);
        }

        $name = self::full_name($student);

        // حساب ناقص: يُنشأ الآن بدل أن يُطلب إعادة التسجيل.
        if (empty($student->user_id)) {
            $account = self::create_student_account($student_id, (string) $student->academic_no, $name);

            if ($account !== []) {
                return $account;
            }

            // الاسم محجوز لكن الربط مفقود — نصله بالحساب القائم.
            $existing = get_user_by('login', 's' . $student->academic_no);

            if (!$existing) {
                return sch_api_error('no_account', __('تعذّر إنشاء حساب الطالب.', 'school-system'), 500);
            }

            $wpdb->update(sch_table('students'), ['user_id' => (int) $existing->ID], ['id' => $student_id]);
            $student->user_id = (int) $existing->ID;
        }

        $user = get_userdata((int) $student->user_id);

        if (!$user) {
            return sch_api_error('no_account', __('حساب الطالب غير موجود.', 'school-system'), 404);
        }

        $password = wp_generate_password(10, false);
        wp_set_password($password, (int) $user->ID);

        sch_audit('student.password_reset', 'student', $student_id);

        return ['login' => $user->user_login, 'password' => $password];
    }

    /** الاسم الكامل من الأجزاء، مع تراجع للاسم المخزَّن. */
    public static function full_name(object $student): string
    {
        $parts = array_filter([
            $student->first_name ?? '',
            $student->father_name ?? '',
            $student->grand_name ?? '',
            $student->family_name ?? '',
        ]);

        return $parts !== [] ? implode(' ', $parts) : (string) $student->full_name;
    }
}
