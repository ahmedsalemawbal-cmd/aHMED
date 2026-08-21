<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * المحتوى التعليمي: كتب · فيديوهات · ألعاب.
 *
 * **اللعبة تُخزَّن ككود ويُشغَّل في صندوق مغلق.** هذه الفكرة تكسر جدار المحتوى:
 * بدل توظيف مبرمج ألعاب، يطلب المنسق كودًا من نموذج فتظهر لعبة.
 *
 * لكن كودًا يُلصق ويُشغَّل في تطبيق يفتحه أطفال خطر حقيقي، فثلاث طبقات تحميه:
 * 1. إطار معزول بلا هوية أصل — لا يرى بيانات التطبيق ولا كوكيزه ولا صفحته.
 * 2. سياسة أمان تقطع الشبكة تمامًا — لا اتصال خارجي مهما حاول الكود.
 * 3. قناة خروج واحدة ضيقة: النتيجة فقط، ويُتحقَّق من شكلها قبل قبولها.
 */
final class SCH_Content
{
    public const TYPES = [
        'book'  => 'كتاب إلكتروني',
        'video' => 'فيديو تعليمي',
        'game'  => 'لعبة تعليمية',
        'link'  => 'رابط مصدر',
    ];

    public const STATUSES = [
        'draft'     => 'مسودة',
        'published' => 'منشور',
        'archived'  => 'مؤرشف',
    ];

    /** مهلة المراجعة — المكتبات تموت بالتراكم لا بالنقص. */
    private const REVIEW_MONTHS = 12;

    // ---------- الإنشاء ----------

    public static function create(array $d, array $files = []): array|WP_Error
    {
        global $wpdb;

        $type  = (string) ($d['content_type'] ?? '');
        $title = sanitize_text_field((string) ($d['title'] ?? ''));

        if (!isset(self::TYPES[$type])) {
            return sch_api_error('bad_type', __('نوع المحتوى غير معروف.', 'school-system'), 422);
        }
        if ($title === '') {
            return sch_api_error('missing_title', __('عنوان المحتوى مطلوب.', 'school-system'), 422);
        }

        $stage = array_key_exists($d['stage'] ?? '', SCH_Classes::STAGES) ? $d['stage'] : 'primary';
        $code  = null;
        $url   = null;
        $file  = null;

        if ($type === 'game') {
            $code = self::sanitize_game_code((string) ($d['game_code'] ?? ''));

            if ($code === '') {
                return sch_api_error('missing_code', __('الصق كود اللعبة.', 'school-system'), 422);
            }
        } elseif ($type === 'video' || $type === 'link') {
            $url = esc_url_raw((string) ($d['media_url'] ?? ''));

            if ($url === '') {
                return sch_api_error('missing_url', __('رابط المصدر مطلوب.', 'school-system'), 422);
            }
        } elseif (!empty($files['file']['tmp_name'])) {
            $saved = SCH_Enrollment::store_upload($files['file'], 0, 'content');

            if (is_wp_error($saved)) {
                return $saved;
            }

            $file = $saved['stored'];
        } else {
            $url = esc_url_raw((string) ($d['media_url'] ?? ''));

            if ($url === '') {
                return sch_api_error('missing_file', __('ارفع ملفًا أو ضع رابطًا.', 'school-system'), 422);
            }
        }

        $wpdb->insert(sch_table('content'), [
            'content_type' => $type,
            'title'        => $title,
            'description'  => sanitize_textarea_field((string) ($d['description'] ?? '')) ?: null,
            'stage'        => $stage,
            'grade_level'  => sanitize_text_field((string) ($d['grade_level'] ?? '')) ?: null,
            'subject_id'   => absint($d['subject_id'] ?? 0) ?: null,
            'lesson_ref'   => sanitize_text_field((string) ($d['lesson_ref'] ?? '')) ?: null,
            'media_url'    => $url,
            'file_stored'  => $file,
            'game_code'    => $code,
            'status'       => 'draft',
            'review_at'    => gmdate('Y-m-d', strtotime('+' . self::REVIEW_MONTHS . ' months')),
            'created_by'   => get_current_user_id() ?: null,
            'created_at'   => sch_now(),
            'updated_at'   => sch_now(),
        ]);

        sch_audit('content.created', 'content', (int) $wpdb->insert_id, ['type' => $type]);

        return ['id' => (int) $wpdb->insert_id];
    }

    /**
     * تنظيف كود اللعبة.
     *
     * لا نعتمد على التنظيف وحده — العزل هو الحماية الحقيقية.
     * لكن نمنع ما لا معنى له في لعبة أصلًا، فنقلّل السطح.
     */
    private static function sanitize_game_code(string $code): string
    {
        $code = trim(wp_unslash($code));

        if ($code === '') {
            return '';
        }

        // لا وسوم خادمية ولا إطارات متداخلة داخل اللعبة.
        $code = preg_replace('#<\?php.*?\?>#is', '', $code) ?? '';
        $code = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $code) ?? '';

        return $code;
    }

    public static function set_status(int $id, string $status): bool|WP_Error
    {
        global $wpdb;

        if (!isset(self::STATUSES[$status])) {
            return sch_api_error('bad_status', __('الحالة غير معروفة.', 'school-system'), 422);
        }

        $wpdb->update(sch_table('content'), [
            'status'     => $status,
            'updated_at' => sch_now(),
        ], ['id' => $id]);

        sch_audit('content.' . $status, 'content', $id);

        return true;
    }

    /** تجديد المراجعة — يخرج المحتوى من قائمة «يحتاج مراجعة». */
    public static function renew(int $id): bool
    {
        global $wpdb;

        $wpdb->update(sch_table('content'), [
            'review_at'  => gmdate('Y-m-d', strtotime('+' . self::REVIEW_MONTHS . ' months')),
            'updated_at' => sch_now(),
        ], ['id' => $id]);

        return true;
    }

    // ---------- القراءة ----------

    public static function get(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sch_table('content') . ' WHERE id = %d', $id)) ?: null;
    }

    /** مكتبة المرحلة — ما يراه الطالب. */
    public static function for_stage(string $stage, string $type = '', int $limit = 60): array
    {
        global $wpdb;

        if ($type !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT c.*, s.name AS subject_name FROM " . sch_table('content') . " c
                 LEFT JOIN " . sch_table('subjects') . " s ON s.id = c.subject_id
                 WHERE c.stage = %s AND c.status = 'published' AND c.content_type = %s
                 ORDER BY c.updated_at DESC LIMIT %d",
                $stage,
                $type,
                $limit
            )) ?: [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, s.name AS subject_name FROM " . sch_table('content') . " c
             LEFT JOIN " . sch_table('subjects') . " s ON s.id = c.subject_id
             WHERE c.stage = %s AND c.status = 'published'
             ORDER BY c.updated_at DESC LIMIT %d",
            $stage,
            $limit
        )) ?: [];
    }

    /** كل المحتوى للمنسق، مع عدّاد الفتح وحالة المراجعة. */
    public static function all(string $stage = '', string $type = ''): array
    {
        global $wpdb;

        $where  = ['1=1'];
        $params = [];

        if ($stage !== '') {
            $where[]  = 'c.stage = %s';
            $params[] = $stage;
        }
        if ($type !== '') {
            $where[]  = 'c.content_type = %s';
            $params[] = $type;
        }

        $sql = "SELECT c.*, s.name AS subject_name, u.display_name AS author
                FROM " . sch_table('content') . " c
                LEFT JOIN " . sch_table('subjects') . " s ON s.id = c.subject_id
                LEFT JOIN {$wpdb->users} u ON u.ID = c.created_by
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.status, c.updated_at DESC LIMIT 200";

        return ($params === []
            ? $wpdb->get_results($sql)
            : $wpdb->get_results($wpdb->prepare($sql, ...$params))) ?: [];
    }

    /** ما يحتاج مراجعة — يمنع تعفّن المكتبة. */
    public static function needs_review(): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . sch_table('content') . "
             WHERE status = 'published' AND review_at IS NOT NULL AND review_at <= %s
             ORDER BY review_at LIMIT 50",
            current_time('Y-m-d')
        )) ?: [];
    }

    /** محتوى مرتبط بدرس — يُقترح على الطالب الغائب. */
    public static function for_lesson(?int $subject_id, string $stage, int $limit = 3): array
    {
        global $wpdb;

        if (!$subject_id) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . sch_table('content') . "
             WHERE subject_id = %d AND stage = %s AND status = 'published'
             ORDER BY updated_at DESC LIMIT %d",
            $subject_id,
            $stage,
            $limit
        )) ?: [];
    }

    // ---------- التشغيل ----------

    /** تسجيل فتح — عدّاد الفتح يخبر المنسق أي محتوى يستحق الإنتاج. */
    /**
     * تسجيل فتح محتوى.
     *
     * والفتح **بلا نتيجة** يُسجَّل مرة واحدة في الساعة لكل طالب ومحتوى:
     * شاشة المكتبة تُناديه في كل رسمٍ للصفحة، فالتحديث يُضاعف العدّاد، وضغطة
     * «رجوع» ثم دخول تُضاعفه ثالثة — و«الأكثر فتحًا» يقيس عدد مرات التحديث
     * لا عدد من قرأ. أمّا النتيجة فحدثٌ حقيقيّ يُسجَّل دائمًا: لعبةٌ لُعبت
     * مرتين نتيجتان.
     */
    public static function record_open(int $content_id, int $student_id, ?int $score = null, int $seconds = 0): void
    {
        global $wpdb;

        if ($score === null) {
            $recent = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . sch_table('content_opens') . '
                  WHERE content_id = %d AND student_id = %d AND opened_at > %s',
                $content_id,
                $student_id,
                gmdate('Y-m-d H:i:s', strtotime(sch_now()) - HOUR_IN_SECONDS)
            ));

            if ($recent > 0) {
                return;
            }
        }

        $wpdb->insert(sch_table('content_opens'), [
            'content_id' => $content_id,
            'student_id' => $student_id,
            'score'      => $score !== null ? max(0, min(65535, $score)) : null,
            'seconds'    => max(0, min(86400, $seconds)),
            'opened_at'  => sch_now(),
        ]);

        $wpdb->query($wpdb->prepare(
            'UPDATE ' . sch_table('content') . ' SET opens = opens + 1 WHERE id = %d',
            $content_id
        ));
    }

    /**
     * صندوق اللعبة.
     *
     * `sandbox="allow-scripts"` **بلا** `allow-same-origin` يعطي الإطار هوية فارغة:
     * لا كوكيز ولا تخزين ولا وصول لصفحة الأب. وسياسة الأمان تقطع كل اتصال شبكي.
     * فحتى لو كان الكود خبيثًا، لا يجد ما يسرقه ولا طريقًا يخرج منه.
     */
    public static function game_frame(object $content, int $height = 520): string
    {
        $csp = "default-src 'none'; "
             . "script-src 'unsafe-inline' 'unsafe-eval'; "
             . "style-src 'unsafe-inline'; "
             . "img-src data: blob:; "
             . "media-src data: blob:; "
             . "font-src data:; "
             . "connect-src 'none'; "
             . "form-action 'none'; "
             . "base-uri 'none'";

        $doc = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
             . '<meta http-equiv="Content-Security-Policy" content="' . esc_attr($csp) . '">'
             . '<meta name="viewport" content="width=device-width,initial-scale=1">'
             . '<style>html,body{margin:0;height:100%;background:#0F1720;color:#F1F5F9;'
             . "font-family:'Cairo',system-ui,sans-serif;overflow:hidden}</style>"
             . '<script>'
             // القناة الوحيدة للخروج: النتيجة. لا شيء غيرها يُقبل.
             . 'window.schScore=function(v){try{parent.postMessage('
             . '{type:"sch_score",value:Number(v)||0},"*")}catch(e){}};'
             . '</script></head><body>'
             . (string) $content->game_code
             . '</body></html>';

        return sprintf(
            '<iframe class="schs-game" title="%s" height="%d" '
            . 'sandbox="allow-scripts" referrerpolicy="no-referrer" loading="lazy" '
            . 'srcdoc="%s"></iframe>',
            esc_attr((string) $content->title),
            $height,
            esc_attr($doc)
        );
    }

    /** رابط تشغيل الفيديو — يوتيوب وفيميو بصيغة التضمين. */
    public static function embed_url(string $url): string
    {
        if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/))([\w-]{6,})#', $url, $m) === 1) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }

        if (preg_match('#vimeo\.com/(\d+)#', $url, $m) === 1) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return $url;
    }

    public static function icon_of(string $type): string
    {
        return match ($type) {
            'video' => 'sun',
            'game'  => 'award',
            'link'  => 'route',
            default => 'book',
        };
    }
}
