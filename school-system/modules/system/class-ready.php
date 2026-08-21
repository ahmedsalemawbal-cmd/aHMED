<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * جاهزية النظام.
 *
 * تجيب سؤالًا واحدًا: **هل النظام يعمل كما ينبغي الآن؟**
 *
 * وهي شاشةٌ للوكيل لا للمطوّر. فما كان يُبلَّغ عنه متفرّقًا — الجداول الناقصة
 * في «بيانات المدرسة»، وتوقّف الكرون في «لوحة العهدة»، ومفتاح الدفع في
 * «الإشعارات» — يجتمع هنا، **لأن من يفتح ثلاث شاشات ليطمئنّ لا يطمئنّ**.
 *
 * **والكرون هو ما يُشغّل النظام فعلًا**: إنذارات العهدة كل خمس دقائق، وإشعار
 * الغياب بعد مهلة التصحيح، وطابور البريد والدفع. وتوقُّفه **يُسكت هذا كلّه
 * بلا رسالة واحدة** — والمدرسة تظنّ النظام يعمل وهو صامت.
 *
 * وثلاث قواعد في كل بند:
 *
 * 1. **بلغة الوكيل لا بلغة المبرمج.** «الإنذارات متوقّفة منذ ساعتين» لا
 *    «wp_cron stale».
 * 2. **يقول الأثر لا العَرَض.** «لن يصل إشعار غياب لأيّ وليّ أمر» — فمن يقرأ
 *    يعرف لماذا يهتمّ.
 * 3. **ومعه طريقُ إصلاحه.** بندٌ ناقص بلا رابط يترك القارئ حيث وجده.
 */
final class SCH_Ready
{
    /** درجات الحالة — والترتيب هو ترتيب الخطورة. */
    public const LEVELS = ['bad' => 'يحتاج إصلاحًا', 'warn' => 'انتبه', 'ok' => 'سليم'];

    /**
     * كل بنود الجاهزية.
     *
     * @return array<int,array{key:string,level:string,title:string,detail:string,url:string,cta:string}>
     */
    public static function items(): array
    {
        return array_merge(
            self::database(),
            self::cron(),
            self::backup(),
            self::channels(),
            self::essentials(),
            self::hosting()
        );
    }

    /** ملخّصٌ لرأس الشاشة وللشارة في التنقّل. */
    public static function summary(): array
    {
        $bad = 0;
        $warn = 0;

        foreach (self::items() as $i) {
            if ($i['level'] === 'bad')  { $bad++; }
            if ($i['level'] === 'warn') { $warn++; }
        }

        return ['bad' => $bad, 'warn' => $warn, 'ok' => $bad === 0 && $warn === 0];
    }

    // ═══════════════════════ البنود ═══════════════════════

    private static function database(): array
    {
        $missing = SCH_Activator::missing_tables();

        if ($missing === []) {
            return [[
                'key'    => 'db',
                'level'  => 'ok',
                'title'  => __('قاعدة البيانات كاملة', 'school-system'),
                'detail' => sprintf(
                    /* translators: %s: عدد الجداول */
                    __('%s جدولًا أُنشئت كلّها.', 'school-system'),
                    number_format_i18n(count(SCH_Activator::table_names()))
                ),
                'url'    => '',
                'cta'    => '',
            ]];
        }

        /*
         * جدولٌ لم يُنشأ يجعل **كل عملية عليه تفشل برسالة عامّة** لا تدلّ على
         * السبب — والدرس من v2.12.2 حين رفض MySQL جدول الطلاب لعمودٍ مكرّر
         * فلم يُسجَّل طالبٌ واحد ولم يعرف أحد لماذا.
         */
        return [[
            'key'    => 'db',
            'level'  => 'bad',
            'title'  => sprintf(
                /* translators: %s: عدد الجداول */
                _n('جدولٌ لم يُنشأ في قاعدة البيانات', '%s جداول لم تُنشأ في قاعدة البيانات', count($missing), 'school-system'),
                number_format_i18n(count($missing))
            ),
            'detail' => __('كل عملية على جدولٍ ناقص تفشل برسالةٍ عامّة لا تدلّ على السبب. أعد تفعيل الإضافة، وإن بقي النقص فراجع صلاحيات قاعدة البيانات.', 'school-system')
                . ' — ' . implode('، ', array_slice($missing, 0, 6)),
            'url'    => SCH_Dashboard::url('settings'),
            'cta'    => __('بيانات المدرسة', 'school-system'),
        ]];
    }

    private static function cron(): array
    {
        $h    = SCH_Alerts::health();
        $last = (string) $h['last_run'];

        if (!$h['stale']) {
            return [[
                'key'    => 'cron',
                'level'  => 'ok',
                'title'  => __('الحارس الآليّ يعمل', 'school-system'),
                'detail' => sprintf(
                    /* translators: %s: وقت آخر فحص */
                    __('آخر فحص: %s. الإنذارات وإشعارات الغياب وطابور الرسائل تعمل.', 'school-system'),
                    mysql2date('H:i', $last)
                ),
                'url'    => '',
                'cta'    => '',
            ]];
        }

        return [[
            'key'    => 'cron',
            'level'  => 'bad',
            'title'  => __('الحارس الآليّ متوقّف', 'school-system'),
            'detail' => ($last !== ''
                    ? sprintf(
                        /* translators: %s: وقت آخر فحص */
                        __('آخر فحص كان %s. ', 'school-system'),
                        mysql2date('j F · H:i', $last)
                    )
                    : __('لم يعمل بعد. ', 'school-system'))
                . __('وما دام متوقّفًا: لا إنذار «لم يصل المدرسة»، ولا إشعار غياب يصل وليّ أمر، ولا رسالة تخرج من الطابور. شغّل Cron حقيقيًّا كل خمس دقائق على wp-cron.php من لوحة الاستضافة.', 'school-system'),
            'url'    => SCH_Dashboard::url('custody'),
            'cta'    => __('لوحة العهدة', 'school-system'),
        ]];
    }

    private static function backup(): array
    {
        if (!class_exists('ZipArchive')) {
            return [[
                'key'    => 'backup',
                'level'  => 'bad',
                'title'  => __('النسخ الاحتياطي معطَّل', 'school-system'),
                'detail' => __('الخادم لا يدعم ZipArchive، فلا يمكن أخذ نسخة. راجع مزوّد الاستضافة لتفعيل إضافة zip في PHP.', 'school-system'),
                'url'    => SCH_Dashboard::url('backup'),
                'cta'    => __('النسخ الاحتياطي', 'school-system'),
            ]];
        }

        $last = SCH_Backup::latest();

        if ($last === null) {
            return [[
                'key'    => 'backup',
                'level'  => 'bad',
                'title'  => __('لا توجد نسخة احتياطية', 'school-system'),
                'detail' => __('كشف الطلاب والحضور والعهدة والفواتير على قاعدةٍ واحدة بلا نسخة. أوّل خطأ إداريّ يمحو سنةً من العمل.', 'school-system'),
                'url'    => SCH_Dashboard::url('backup'),
                'cta'    => __('خذ نسخة الآن', 'school-system'),
            ]];
        }

        $age = (int) floor((strtotime(current_time('mysql')) - strtotime($last['at'])) / DAY_IN_SECONDS);

        return [[
            'key'    => 'backup',
            'level'  => $age > 7 ? 'warn' : 'ok',
            'title'  => $age > 7
                ? __('آخر نسخة احتياطية قديمة', 'school-system')
                : __('النسخ الاحتياطي محدَّث', 'school-system'),
            'detail' => sprintf(
                /* translators: 1: التاريخ 2: عدد الأيام */
                __('آخر نسخة %1$s — قبل %2$s. واحفظها خارج الخادم: نسخةٌ على الخادم نفسه تضيع معه.', 'school-system'),
                mysql2date('j F', $last['at']),
                $age === 0
                    ? __('اليوم', 'school-system')
                    : sprintf(_n('يوم', '%s أيام', $age, 'school-system'), number_format_i18n($age))
            ),
            'url'    => SCH_Dashboard::url('backup'),
            'cta'    => __('النسخ الاحتياطي', 'school-system'),
        ]];
    }

    /** قنوات الوصول: بريدٌ صادر وإشعارٌ فوريّ. */
    private static function channels(): array
    {
        $out = [];

        /*
         * البريد يُختبَر بوجود دالّته وبإعدادٍ يسمح به — لا بإرسال رسالةٍ
         * فعلية: الشاشة تُفتح عشرات المرّات يوميًّا، وإرسالٌ في كل فتحة
         * يغرق صندوق المدير ويُصنَّف النظام مُرسِلًا مزعجًا.
         */
        $out[] = sch_settings('notify_email', true)
            ? [
                'key'    => 'email',
                'level'  => 'ok',
                'title'  => __('البريد مفعَّل', 'school-system'),
                'detail' => __('إشعارات أولياء الأمور تخرج بالبريد. وإن لم تصل فالسبب غالبًا في خادم البريد لا في النظام — جرّب إضافة SMTP.', 'school-system'),
                'url'    => '',
                'cta'    => '',
            ]
            : [
                'key'    => 'email',
                'level'  => 'warn',
                'title'  => __('البريد معطَّل', 'school-system'),
                'detail' => __('لن يصل أيّ إشعار بالبريد. ومن لا جهاز مشترك له لن يصله شيء إطلاقًا.', 'school-system'),
                'url'    => SCH_Dashboard::url('settings-push'),
                'cta'    => __('الإشعارات', 'school-system'),
            ];

        $out[] = SCH_Push::ready()
            ? [
                'key'    => 'push',
                'level'  => 'ok',
                'title'  => __('الإشعارات الفورية جاهزة', 'school-system'),
                'detail' => __('المفتاح مولَّد. ويبقى أن يأذن كل مستخدم من جهازه — الإذن لا يُمنَح نيابةً عنه.', 'school-system'),
                'url'    => '',
                'cta'    => '',
            ]
            : [
                'key'    => 'push',
                'level'  => 'warn',
                'title'  => __('الإشعارات الفورية غير مهيّأة', 'school-system'),
                'detail' => __('لا مفتاح مولَّد، فلا إشعار فوريّ يصل جهازًا. والبريد وحده يبقى — وهو أبطأ وأقلّ قراءةً.', 'school-system'),
                'url'    => SCH_Dashboard::url('settings-push'),
                'cta'    => __('ولّد المفتاح', 'school-system'),
            ];

        return $out;
    }

    /** ما لا يعمل النظام بدونه: اسم المدرسة وسنةٌ جارية وشعبةٌ واحدة. */
    private static function essentials(): array
    {
        $out = [];

        if (trim((string) sch_settings('school_name', '')) === '') {
            $out[] = [
                'key'    => 'name',
                'level'  => 'warn',
                'title'  => __('اسم المدرسة غير مُدخَل', 'school-system'),
                'detail' => __('يظهر في البطاقات والشهادات وإشعارات أولياء الأمور — وبدونه تُطبع وثائق باسم فارغ.', 'school-system'),
                'url'    => SCH_Dashboard::url('settings'),
                'cta'    => __('بيانات المدرسة', 'school-system'),
            ];
        }

        /*
         * السنة جذر كل شيء: أي شعبة أو تسجيل يحمل `year_id`. وبلا سنةٍ جارية
         * لا يُسجَّل طالب ولا تُبنى شعبة — وهي أوّل خطوة في معالج الإعداد.
         */
        if (SCH_Years::current_id() <= 0) {
            $out[] = [
                'key'    => 'year',
                'level'  => 'bad',
                'title'  => __('لا سنة دراسية جارية', 'school-system'),
                'detail' => __('السنة جذر كل شيء في النظام: بلاها لا يُسجَّل طالب ولا تُبنى شعبة ولا يُرصد حضور.', 'school-system'),
                'url'    => SCH_Dashboard::url('settings-years'),
                'cta'    => __('السنوات الدراسية', 'school-system'),
            ];
        }

        return $out;
    }

    /** ما يخصّ الاستضافة: المساحة والاتّصال الآمن. */
    private static function hosting(): array
    {
        $out = [];

        /*
         * HTTPS ليس تفضيلًا أمنيًّا هنا بل **شرط تشغيل**: الكاميرا (مسح
         * البوابة والسائق) وعامل الخدمة (العمل بلا شبكة) والإشعارات الفورية
         * — ثلاثتها يمنعها المتصفّح على اتّصالٍ غير آمن. فتطبيقا الميدان
         * لا يعملان أصلًا.
         */
        if (!is_ssl() && !str_starts_with(home_url('/'), 'https://')) {
            $out[] = [
                'key'    => 'https',
                'level'  => 'bad',
                'title'  => __('الموقع بلا اتّصال آمن (HTTPS)', 'school-system'),
                'detail' => __('المتصفّح يمنع الكاميرا والعمل بلا شبكة والإشعارات على اتّصالٍ غير آمن — فتطبيقا البوابة والسائق لا يعملان إطلاقًا. فعّل شهادة SSL من لوحة الاستضافة.', 'school-system'),
                'url'    => '',
                'cta'    => '',
            ];
        }

        $dir  = SCH_Enrollment::private_dir();
        $free = @disk_free_space($dir);

        if (is_float($free) && $free > 0) {
            $low = $free < 512 * MB_IN_BYTES;

            $out[] = [
                'key'    => 'disk',
                'level'  => $low ? 'warn' : 'ok',
                'title'  => $low
                    ? __('المساحة الحرّة قليلة', 'school-system')
                    : __('المساحة كافية', 'school-system'),
                'detail' => sprintf(
                    /* translators: %s: المساحة الحرّة */
                    __('المتبقّي %s. والصور والمستندات والنسخ الاحتياطية تتراكم — وامتلاء القرص يوقف الرفع والنسخ معًا.', 'school-system'),
                    size_format($free)
                ),
                'url'    => $low ? SCH_Dashboard::url('backup') : '',
                'cta'    => $low ? __('احذف نسخًا قديمة', 'school-system') : '',
            ];
        }

        return $out;
    }
}
