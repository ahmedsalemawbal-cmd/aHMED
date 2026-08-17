# نظام المدرسة — تقرير الإصلاحات البرمجية

**مراجعة تشغيل كاملة · 17 أغسطس 2026**
**من النسخة `8.3.0` (كما وصلت) إلى `8.3.1`**
**الفرع:** `claude/madri-system-review-w84eir` في `ahmedsalemawbal-cmd/aHMED`
**خط الأساس:** الـcommit `ce7ddc7` — النسخة الأصلية بلا أي تعديل

---

## كيف تقرأ هذه الوثيقة

كل إصلاح هنا **ظهر بتشغيل النظام لا بقراءته**. رُكِّب النظام على خادم حقيقي
(WordPress 6.4.3 · PHP 8.4 · MySQL 8.0.46) ببيانات مدرسة كاملة (٤٠ طالبًا ·
٢٠ ولي أمر · ١٢ موظفًا بكل دور · مسار وباص · ٤٨٠ سجل حضور)، ثم مُرَّ عليه آليًا:
تسجيل دخول بكل دور، فتح **الأقسام الـ47 والواجهات الست**، وجمع أخطاء المتصفح
والطلبات الفاشلة وأخطاء PHP من `debug.log`.

لكل إصلاح: **الملف والدالة · الكود قبل · الكود بعد · السبب الجذري · الأثر ·
كيف تتحقق بنفسك**.

### نوعان من التغيير — لا تخلط بينهما

| النوع | المعنى | الأقسام |
|---|---|---|
| **إصلاح منطق** | سلوك تغيّر لأنه كان خاطئًا | ١ – ٧ |
| **إعادة هيكلة** | **لا سلوك تغيّر** — نقل ميكانيكي مُثبَت | ٨ |
| **أدوات** | ملفات جديدة لا تدخل نسخة الإنتاج | ٩ |

تغيير المنطق كله: **22 ملفًا · 353 سطرًا مضافًا · 62 محذوفًا**.

```bash
# الفرق في المنطق وحده — نفّذه لترى كل ما تغيّر سلوكيًا
git diff ce7ddc7 HEAD -- api includes modules \
  frontend/class-app.php frontend/class-teacher.php \
  frontend/views/perms.php frontend/views/finance.php
```

---

## الخلاصة قبل التفصيل

النسخة الأصلية كانت **مبنيّة جيدًا**: 219 ملف PHP تمر كلها بـ`php -l` بلا خطأ،
ولا ثغرة حقن SQL واحدة (كل استعلام عبر `$wpdb->prepare()`)، وتهريب المخرجات
سليم، ومُوجّه الأفعال يفرض النونس ثم الصلاحية على الأفعال الـ106 بلا استثناء،
وخدمة الملفات المحمية محصّنة بـ`realpath` وفحص النوع من محتوى الملف ومسح EXIF.

الأعطال أدناه **لا تظهر على موقع يعمل منذ مدة**. أغلبها يظهر عند **أول تركيب
لعميل جديد** — وهو بالضبط سيناريو التسليم.

| # | العطل | الخطورة | الأثر العملي | ملفات |
|---|---|---|---|---|
| ١ | `insert_id` يُقرأ بعد `sch_audit()` | حرج | 28 من 40 طالب فشل تسجيلهم | 11 |
| ٢ | تفريغ قواعد التوجيه مبكّر | حرج | ست واجهات من سبع = 404 | 3 |
| ٣ | مفتاح JWT قد يكون فارغًا | حرج | انتحال هوية المدير بلا كلمة مرور | 2 |
| ٤ | خلط قفلين في الصلاحيات | مرتفع | قسمان يردّان 403 للجميع | 1 |
| ٥ | أربعة أعطال تشغيل | مرتفع | تطبيق المعلم بلا فصول · صورة مكسورة · تحذيرات · استعلام ميت | 4 |
| ٦ | صلاحيتان بلا دور + اسم دور خاطئ | متوسط | 4 أقسام للمدير وحده · مشرف نقل لا يُنشأ | 3 |
| ٧ | الخط من CDN بعائلتين | متوسط | الواجهة بلا خط إن رُشِّحت الشبكة | 16 |

---

## ١. دوال الإنشاء تُرجع رقم صف سجل التدقيق

**الـcommit:** `ed9fd4d` · **18 موضعًا في 11 ملفًا** · **خطورة: حرجة**

### السبب الجذري

`sch_audit()` في `includes/functions.php` تُنفّذ `$wpdb->insert()` بنفسها على
جدول التدقيق:

```php
function sch_audit(string $action, ?string $object_type = null, ?int $object_id = null, array $meta = []): void
{
    global $wpdb;

    $wpdb->insert(sch_table('audit_log'), [ /* … */ ]);
}
```

و`$wpdb->insert_id` خاصية **واحدة على الكائن العام** تحمل آخر إدراج. فكل دالة
إنشاء كانت تقرأها **بعد** نداء التدقيق تحصل على رقم صف سجل التدقيق، لا رقم
السجل الذي أنشأتْه.

الوسيط داخل نداء `sch_audit(...)` كان يُقيَّم **قبل** تنفيذها، فسجل التدقيق
نفسه كان يحمل الرقم الصحيح — وهذا ما جعل العطل خفيًّا: السجل سليم والمُرجَع خطأ.

### قبل

```php
// modules/academic/class-academic.php — SCH_Classes::create()
sch_audit('class.created', 'class', (int) $wpdb->insert_id);
return ['id' => (int) $wpdb->insert_id];   // ← رقم صف التدقيق
```

### بعد

```php
// insert_id يُحتجَز قبل sch_audit(): التدقيق يُدرج صفًا بنفسه
// فيصير insert_id رقم صف السجل لا رقم السجل المُنشأ.
$new_id = (int) $wpdb->insert_id;

sch_audit('class.created', 'class', $new_id);
return ['id' => $new_id];
```

ونفس النمط حيث الإرجاع عدد صحيح لا مصفوفة:

```php
// modules/enrollment/class-enrollment.php — SCH_Enrollment::save_doc()
$new_id = (int) $wpdb->insert_id;

sch_audit('doc.uploaded', 'student', $student_id, ['type' => $doc_type]);

return $new_id;
```

### المواضع الثمانية عشر

| الملف | الدالة | الجدول |
|---|---|---|
| `modules/academic/class-academic.php` | `create()` | `classes` |
| `modules/academic/class-assessment.php` | `create()` | `subjects` |
| `modules/academic/class-assessment.php` | `create_exam()` | `exams` |
| `modules/accounting/class-accounting.php` | `create()` | `accounts` |
| `modules/clinic/class-medication.php` | `request()` | `student_leaves` |
| `modules/enrollment/class-enrollment.php` | `save_doc()` | `student_docs` |
| `modules/finance/class-finance.php` | `create_plan()` | `fee_plans` |
| `modules/hr/class-hr.php` | `create_contract()` | `contracts` |
| `modules/hr/class-hr.php` | `request_leave()` | `leaves` |
| `modules/learning/class-content.php` | `create()` | `content` |
| `modules/services/class-services.php` | `record()` | `clinic_visits` |
| `modules/services/class-services.php` | `check_in()` | `visitors` |
| `modules/services/class-services.php` | `report_incident()` | `incidents` |
| `modules/services/class-services.php` | `create()` | `assets` |
| `modules/services/class-services.php` | `report_maintenance()` | `maintenance` |
| `modules/staff/class-staff.php` | `create()` | `employees` |
| `modules/transport/class-transport.php` | `create()` | `buses` |
| `modules/transport/class-transport.php` | `create()` | `routes` |

### الأثر العملي

الرقم المُرجَع يُستعمل للربط والتوجيه بعد الإنشاء. في سيناريو تسجيل واقعي:
**28 من 40 طالبًا فشل تسجيلهم** برسالة «اختر الفصل الدراسي» لأن رقم الشعبة
المُرجَع لم يكن موجودًا، **والاثنا عشر الباقون وُضعوا في شعبة غير التي اختِيرت**.

### كيف تتحقق

```php
// شغّل هذا على نسخة فيها الإصلاح
$r    = SCH_Classes::create(['stage'=>'secondary','grade_level'=>'ثالث','section'=>'ج']);
$real = (int) $wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}sch_classes");
// يجب أن يتطابقا. قبل الإصلاح: الحقيقي 7 والمُرجَع 97 (رقم صف التدقيق).
```

الفحص الآلي لا يمسك هذا النمط بعد — لكن اختبار التسجيل في `tools/seed.php`
يمسكه: قبل الإصلاح ينجح 12 طالبًا فقط، وبعده 40.

---

## ٢. ست واجهات من سبع تُرجع 404 على تركيب نظيف

**الـcommits:** `98ad748` ثم `c1d9488` · **خطورة: حرجة**

### السبب الجذري

كل واجهة تسجّل قواعد توجيهها على خطاف `init` بالأولوية الافتراضية 10:

```php
// SCH_App · SCH_Driver · SCH_Gate · SCH_Teacher · SCH_Student · SCH_Portal
add_action('init', [self::class, 'add_rewrite']);
```

وكان **تفريغ الجدول** داخل `SCH_Dashboard::add_rewrite()` — أي داخل واحدة من
هذه الدوال نفسها:

```php
// قبل — frontend/class-dashboard.php
public static function add_rewrite(): void
{
    add_rewrite_rule("^{$b}/?$", '…', 'top');
    // …
    if (get_option('sch_rewrite_version') !== SCH_VERSION) {
        flush_rewrite_rules(false);
        update_option('sch_rewrite_version', SCH_VERSION, false);
    }
}
```

فيُحفظ جدول القواعد **وفيه قواعد الداشبورد وحدها**، ثم يصير
`sch_rewrite_version` مطابقًا للنسخة فلا يُفرَّغ ثانيةً أبدًا.

وزاد الطين بلّة أن `SCH_Activator::activate()` كانت تفرّغ عند التفعيل — وخطاف
`init` لم يعمل بعد في تلك اللحظة، فلا قاعدة واحدة مسجَّلة.

### الأثر العملي

على تركيب نظيف: `/app` · `/driver` · `/gate` · `/teacher` · `/student` ·
`/login` **كلها 404**. لا يظهر العطل على موقع قائم لأن أي حفظ لإعدادات الروابط
الدائمة يفرّغ الجدول وقتها كل القواعد مسجّلة.

### بعد — التفريغ في مكان واحد متأخر

```php
// includes/class-loader.php — داخل init()
// تفريغ قواعد التوجيه مرة واحدة لكل نسخة — **على init بأولوية 99**،
// أي بعد أن تسجّل كل واجهة قواعدها (كلها على init بالأولوية 10).
add_action('init', [self::class, 'maybe_flush_rewrites'], 99);
```

```php
public static function maybe_flush_rewrites(): void
{
    // **الحكم على وجود القواعد فعلًا، لا على رقم النسخة وحده.**
    $rules   = get_option('rewrite_rules');
    $known   = '^' . SCH_Dashboard::BASE . '/?$';
    $present = is_array($rules) && isset($rules[$known]);

    if ($present && get_option('sch_rewrite_version') === SCH_VERSION) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('sch_rewrite_version', SCH_VERSION, false);
}
```

وفي `includes/class-activator.php` صار التفعيل **يمحو العلامة** بدل أن يفرّغ:

```php
// لا تفريغ هنا: عند التفعيل لم يعمل خطاف `init` بعد، فلا واجهة سجّلت
// قاعدةً واحدة — والتفريغ في هذه اللحظة كان يخزّن جدولًا بلا قواعدنا.
delete_option('sch_rewrite_version');
```

### لماذا الشرط الثاني (`$present`) ضروري

الإصلاح الأول وحده لم يكفِ. ظهر أثناء الاختبار أن الجدول يُمحى ويبقى الرقم
مطابقًا — **فلا يُعاد التفريغ أبدًا**. يحدث هذا كلما أُعيد توليد الجدول بينما
الإضافة غير محمّلة (تعطيل مؤقت · نسخ مجلد الإضافة أثناء طلب وارد · إضافة أخرى
تفرّغ الجدول). رأيتُ الحالة بعيني: **صفر قاعدة والرقم `8.3.0`**.

بالفحص على وجود القاعدة نفسها صار النظام **يتعافى تلقائيًا عند أول طلب**.

### كيف تتحقق

```bash
wp eval '$r=get_option("rewrite_rules"); $n=0;
  foreach((array)$r as $re=>$q) if(preg_match("~^\^(app|driver|gate|student|teacher|dashboard|login)~",$re)) $n++;
  echo "قواعدنا: $n\n";'
```

| | قبل | بعد |
|---|---|---|
| تركيب نظيف من صفر | **3** قواعد | **17** قاعدة |

والفحص الآلي `ROUTE_NOT_FLUSHED` يمسك عودة العطل.

---

## ٣. الأمن — انتحال الهوية وحدّ المحاولات وتدوير الرموز

**الـcommit:** `a2dc54d` · `api/class-auth.php` + `includes/class-activator.php`

### ٣-أ. مفتاح توقيع فارغ ⇒ انتحال هوية المدير · **حرج**

#### قبل

```php
private static function secret(): string
{
    return (string) get_option('sch_jwt_secret', '');
}
```

المفتاح كان يُولَّد في `SCH_Activator::activate()` **وحدها**. ومن نسخ مجلد
الإضافة يدويًا — وهذا بالضبط ما يحدث عند التسليم بملف ZIP — أو حُذف الخيار من
قاعدته، يبقى المفتاح **نصًّا فارغًا**.

**ولماذا هذا كارثي:** التوقيع `HMAC-SHA256(payload, key)`. حين يكون المفتاح
معروفًا — والفراغ معروف — يستطيع **أي أحد** حساب التوقيع الصحيح لأي حمولة
يختارها، بما فيها `{"sub": 1}` أي المستخدم رقم 1 (المدير).

#### الدليل — نُفِّذ فعلًا على النسخة الأصلية

```python
import hmac, hashlib, base64, json, time
b = lambda r: base64.urlsafe_b64encode(r).rstrip(b'=').decode()
h = b(json.dumps({"typ":"JWT","alg":"HS256"},separators=(',',':')).encode())
p = b(json.dumps({"sub":1,"iat":int(time.time()),"exp":int(time.time())+3600},separators=(',',':')).encode())
sig = b(hmac.new(b"", f"{h}.{p}".encode(), hashlib.sha256).digest())   # ← مفتاح فارغ
print(f"{h}.{p}.{sig}")
```

```
GET /?rest_route=/school/v1/me   Authorization: Bearer <الرمز المزوَّر>
→ {"id":1,"name":"admin","roles":["administrator"]}
```

**سيطرة إدارية كاملة بلا كلمة مرور.** وبعد الإصلاح: نفس الرمز ونفس المفتاح
الفارغ ⇒ `401 rest_forbidden`.

#### بعد

```php
private static function secret(): string
{
    $secret = (string) get_option('sch_jwt_secret', '');

    if (strlen($secret) < 32) {
        $secret = wp_generate_password(64, true, true);
        update_option('sch_jwt_secret', $secret, false);
        sch_audit('auth.secret_generated', 'system');
    }

    return $secret;
}
```

وثلاث طبقات إضافية:

```php
private static function encode(array $payload): string
{
    $secret = self::secret();
    if (strlen($secret) < 32) {
        return '';                    // لا نوقّع بمفتاح ضعيف
    }
    // …
}

private static function decode(string $jwt): ?array
{
    $secret = self::secret();
    if (strlen($secret) < 32) {
        return null;                  // الرفض أسلم من القبول
    }
    // …
}
```

```php
// includes/class-activator.php — maybe_upgrade()
// المفتاح كان يُضبط عند التفعيل وحده — فمن رقّى نسخةً نُسخت يدويًا
// بلا تفعيل يبقى بلا مفتاح.
update_option('sch_jwt_secret', self::ensure_secret(), false);
```

### ٣-ب. `/auth/login` بلا أي حدّ للمحاولات · **حرج**

شاشة الداشبورد محمية بـ`MAX_ATTEMPTS = 5` و`LOCK_MINUTES = 15`. مسار REST لم
يكن يملك شيئًا — **فهو طريق جانبي مفتوح لتجربة كلمات المرور يتجاوز القفل تمامًا**.

#### بعد

```php
public static function login(WP_REST_Request $req): WP_REST_Response|WP_Error
{
    $username = sanitize_user((string) $req->get_param('username'));

    if (self::is_throttled($username)) {
        return sch_api_error('too_many_attempts',
            __('محاولات كثيرة. انتظر ربع ساعة ثم أعد المحاولة.', 'school-system'), 429);
    }

    $user = wp_authenticate($username, (string) $req->get_param('password'));

    if (is_wp_error($user)) {
        self::count_failure($username);
        // رسالة واحدة لكل فشل: «اسم غير موجود» تكشف من هو مسجَّل ومن ليس.
        return sch_api_error('invalid_credentials', __('بيانات الدخول غير صحيحة.', 'school-system'), 401);
    }

    self::clear_failures($username);
    // …
}
```

```php
private const MAX_ATTEMPTS = 5;
private const LOCK_MINUTES = 15;

/** مفتاح العدّاد: الاسم + IP — فلا يُقفل حساب موظف بمحاولات من شبكة أخرى. */
private static function throttle_key(string $username): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    return 'sch_api_try_' . md5(strtolower($username) . '|' . $ip);
}

private static function is_throttled(string $username): bool
{
    return (int) get_transient(self::throttle_key($username)) >= self::MAX_ATTEMPTS;
}

private static function count_failure(string $username): void
{
    $key = self::throttle_key($username);
    set_transient($key, (int) get_transient($key) + 1, self::LOCK_MINUTES * MINUTE_IN_SECONDS);
}

private static function clear_failures(string $username): void
{
    delete_transient(self::throttle_key($username));
}
```

**لماذا (الاسم + IP) لا الاسم وحده:** لو كان المفتاح الاسم وحده لاستطاع مهاجم
أن **يقفل حساب أي موظف** بخمس محاولات خاطئة — حجب خدمة بدل حماية.

### ٣-ج. `refresh_token` لا يُدوَّر ولا يتحقق من صاحبه · **مرتفع**

#### قبل

```php
if (!$row) {
    return sch_api_error('invalid_refresh_token', …, 401);
}

return new WP_REST_Response([
    'access_token' => self::issue_access_token((int) $row->user_id),
    'expires_in'   => self::ACCESS_TTL,
]);
```

الرمز يعيش **ثلاثين يومًا** ويُعطي رموز وصول بلا حدّ. رمز مسروق يعمل شهرًا
كاملًا، ولا طريقة لكشف الاستخدام المزدوج.

#### بعد

```php
// صاحب الرمز قد يكون حُذف أو أُوقف بعد إصداره — والرمز يعيش ٣٠ يومًا.
$user_id = (int) $row->user_id;
if (!get_userdata($user_id)) {
    $wpdb->update(sch_table('refresh_tokens'), ['revoked_at' => sch_now()], ['id' => (int) $row->id]);
    return sch_api_error('invalid_refresh_token', …, 401);
}

// **تدوير الرمز:** الرمز المستهلك يُلغى ويُصدر بديله.
$fresh = self::issue_refresh_token($user_id, (string) $row->device_id, (string) ($row->device_name ?? ''));

return new WP_REST_Response([
    'access_token'  => self::issue_access_token($user_id),
    'refresh_token' => $fresh,
    'expires_in'    => self::ACCESS_TTL,
]);
```

(`issue_refresh_token()` كانت تلغي رموز الجهاز السابقة أصلًا، فالتدوير يستفيد منها.)

### كيف تتحقق — اختبار يدوي كامل

```bash
API="https://موقعك/?rest_route=/school/v1"

# ١) دخول صحيح
R=$(curl -s -X POST "$API/auth/login" -d "username=driver&password=…&device_id=d1")
AT=$(echo "$R" | jq -r .access_token); RT=$(echo "$R" | jq -r .refresh_token)

# ٢) Bearer يعمل
curl -s "$API/me" -H "Authorization: Bearer $AT"          # → بيانات المستخدم

# ٣) التحديث يُدوّر
R2=$(curl -s -X POST "$API/auth/refresh" -d "refresh_token=$RT")
echo "$R2" | jq -r .refresh_token                          # → رمز **مختلف**

# ٤) الرمز المستهلك مرفوض
curl -s -X POST "$API/auth/refresh" -d "refresh_token=$RT" # → 401

# ٥) القفل بعد خمس محاولات
for i in $(seq 1 6); do
  curl -s -o /dev/null -w "%{http_code} " -X POST "$API/auth/login" \
    -d "username=driver&password=خطأ$i&device_id=d"
done                                                       # → 401 401 401 401 401 429
```

| الاختبار | النتيجة |
|---|---|
| رمز مزوَّر بمفتاح فارغ | **مرفوض** (كان يدخل كـ`administrator`) |
| المحاولة السادسة | **429** |
| كلمة مرور صحيحة أثناء القفل | **429** — القفل لا يُلتَف عليه |
| رمز تحديث مستهلك | **401** |

---

## ٤. «لوحة العهدة» و«سجل النظام» — 403 للجميع حتى المدير

**الـcommit:** `783c6da` · `modules/staff/class-perms.php` · **خطورة: مرتفعة**

### السبب الجذري — مفهومان مختلفان في دالة واحدة

في `SCH_Perms` ثابتان يعنيان شيئين مختلفين تمامًا:

```php
/** أقسام سجلّها غير قابل للتعديل — كلا السببين يقول «للقراءة فقط» */
public const LOCKED = [
    'custody' => 'سجل العهدة لا يُعدَّل — الخطأ يُصحَّح بحدث مضاد لا بمحو…',
    'audit'   => 'سجل النظام للقراءة فقط — تعديله يُبطل قيمته كسجل',
];

/** أقسام لا تُمنح لأدوار بعينها — جدار بيانات */
public const LOCKED_FOR = [
    'sch_guard'  => ['clinic', 'meds', 'referrals', 'inbox', 'nerve', 'payroll', 'accounting'],
    'sch_driver' => ['clinic', 'meds', 'referrals', 'inbox', 'nerve', 'payroll', 'accounting'],
];
```

الأول يقول «**لا يُكتَب**»، والثاني يقول «**لا يُرى**». وكانت `is_locked()` تدمجهما،
و`may()` ترفض على أساسها **في أي وضع بما فيه القراءة**:

```php
// قبل
if (self::is_locked($section, $user_id)) {
    return false;
}
```

وفي `SCH_Dashboard::route()` تُستدعى `may()` **فوق** فحص صلاحية الدور:

```php
if (!current_user_can(self::SECTIONS[$section][1]) || !SCH_Perms::may($section, 'view')) {
    status_header(403);
    self::render('403', ['section' => $section]);
}
```

فصار قسمان مسجَّلان في `SECTIONS` ولهما ملف عرض وصلاحية دور (`sch_view_custody`
و`sch_view_audit` — والمدير يملكهما) **لا يُفتحان أبدًا**.

### الأثر العملي

**لوحة العهدة قلب سلسلة العهدة**، و**سجل التدقيق لا قيمة له إن لم يُقرأ** —
والنظام يكتب فيه كل عملية حساسة. قسمان ميّتان في التنقّل.

### بعد

```php
public static function may(string $section, string $mode = 'view', ?int $user_id = null): bool
{
    $user_id ??= get_current_user_id();

    if ($user_id === 0) {
        return false;
    }

    // جدار الدور: لا قراءة ولا كتابة.
    if (self::is_denied_for_role($section, $user_id)) {
        return false;
    }

    // سجل غير قابل للتعديل: الكتابة مرفوضة دائمًا، والقراءة من صلاحية الدور.
    if (isset(self::LOCKED[$section])) {
        return $mode !== 'edit';
    }

    if (self::expired($user_id)) {
        return false;
    }
    // … باقي منطق الصلاحيات المخصصة كما هو
}
```

ودالة جديدة تفصل الجدار عن القفل:

```php
/** هل هذا القسم **ممنوع على دور هذا المستخدم**؟ (جدار بيانات) */
public static function is_denied_for_role(string $section, ?int $user_id = null): bool
{
    $user = get_userdata($user_id ?? get_current_user_id());

    if (!$user) {
        return false;
    }

    foreach ($user->roles as $role) {
        if (in_array($section, self::LOCKED_FOR[$role] ?? [], true)) {
            return true;
        }
    }

    return false;
}

/**
 * هل هذا القسم **لا يُمنح في طبقة الصلاحيات المخصصة**؟
 * **لا تستعملها بوابةً للقراءة** — لذلك `may()` وحدها.
 */
public static function is_locked(string $section, ?int $user_id = null): bool
{
    return isset(self::LOCKED[$section]) || self::is_denied_for_role($section, $user_id);
}
```

**`is_locked()` احتفظت بمعناها تمامًا**، فشاشة الصلاحيات
(`frontend/views/perms.php:189`) ودالة `save()` تعملان كما كانتا: القسمان يظهران
رماديين ومعهما سببهما، ولا يُخزَّن لهما صف في `sch_user_perms`.

### كيف تتحقق

```php
wp_set_current_user(1);  // المدير
SCH_Perms::may('custody', 'view');   // قبل: false   بعد: true
SCH_Perms::may('custody', 'edit');   // قبل: false   بعد: false  ← يبقى ممنوعًا
SCH_Perms::is_locked('custody');     // true في الحالتين (شاشة الصلاحيات)

// والجدار لم ينكسر:
$g = get_users(['role' => 'sch_guard', 'number' => 1])[0];
SCH_Perms::may('clinic', 'view', $g->ID);   // false ✓
```

بالمتصفح: `/dashboard/custody/` و`/dashboard/audit/` كانتا **403**، صارتا **200**.

---

## ٥. أربعة أعطال ظهرت بالتشغيل

**الـcommit:** `e444335`

### ٥-أ. تطبيق المعلم بلا فصول إطلاقًا · **مرتفع**

اسم العمود في المخطط (`includes/class-activator.php:283`):

```sql
homeroom_teacher_id BIGINT UNSIGNED DEFAULT NULL,
```

والاستعلام كان يسأل عن اسم آخر:

```php
// قبل — frontend/class-teacher.php:270  (SCH_Teacher::my_classes)
'SELECT DISTINCT c.* FROM ' . sch_table('classes') . ' c
 WHERE c.year_id = %d AND (
       c.homeroom_user_id = %d          ← عمود غير موجود
    OR c.id IN (SELECT cs.class_id FROM …)
 )'
```

```php
// بعد
       c.homeroom_teacher_id = %d
```

**الأثر:** الاستعلام يفشل كاملًا، فتُرجع الدالة `[]` بسبب `?: []` — فيرى المعلم
شاشة بلا فصل واحد، **بلا رسالة خطأ**. الأثر الوحيد سطر في `debug.log`:

```
WordPress database error Unknown column 'c.homeroom_user_id' in 'where clause'
```

**كيف تتحقق:** `WP_DEBUG_LOG` مفعّلًا، افتح `/teacher/` بحساب معلم — يجب ألّا
يظهر سطر خطأ، وأن تظهر فصوله. والفحص `SQL_UNKNOWN_COLUMN` يمسك هذا النوع كله آليًا.

### ٥-ب. تحذيرا PHP في شاشة الصلاحيات

`SCH_Staff::list()` تُرجع مُغلِّفًا لا قائمة:

```php
return ['items' => $items ?: [], 'total' => $total];
```

```php
// قبل — frontend/views/perms.php:7
$staff = SCH_Staff::list(['status' => 'active']);
// ثم:  foreach ($staff as $s) { $uid = (int) $s->user_id; … }
```

```php
// بعد — السطر 10
// SCH_Staff::list() ترجع ['items' => [...], 'total' => n] لا قائمة مسطّحة.
// قراءتها مباشرةً كانت تمرّ على 'items' و'total' فتقرأ خاصية على مصفوفة وعلى عدد.
$staff = SCH_Staff::list(['status' => 'active'])['items'];
```

**الأثر:** `Attempt to read property "user_id" on array` ثم `… on int` في كل فتح
للشاشة. والفحص `LIST_SHAPE` يمسكه.

> **ملاحظة للمبرمج:** فحصتُ بقية مواضع `::list()` — `overview.php` و`kg` و
> `admin/views/students.php` و`class-dashboard.php` كلها تستعمل `['items']`
> أو `['total']` صحيحًا. الخطأ كان في هذا الموضع وحده.

### ٥-ج. صورة مكسورة في كل شاشة من تطبيق ولي الأمر

القوالب تكتب:

```php
// frontend/app/layout.php:60  و  frontend/app/account.php:20
<?php if (SCH_App::avatar_url()) : ?>
    <img src="<?php echo esc_url(SCH_App::avatar_url()); ?>" alt="" width="40" height="40">
<?php else : ?>
    <?php echo sch_avatar_svg(mb_substr((string) $p_user->display_name, 0, 1), 40); ?>
<?php endif; ?>
```

```php
// قبل — frontend/class-app.php
public static function avatar_url(): string
{
    return add_query_arg('sch_avatar', '1', self::url('account'));
}
```

`add_query_arg()` **لا تُرجع فراغًا أبدًا**، فالشرط صحيح دائمًا. و`send_avatar()`
تردّ 404 لمن لا صورة له:

```php
if (!$guardian || !$guardian->photo_file) {
    status_header(404);
    exit;
}
```

**النتيجة:** `<img>` مكسورة في كل شاشة، و**بديل حرف الاسم لا يظهر أبدًا**.

```php
// بعد
public static function avatar_url(): string
{
    $guardian = SCH_Guardians::by_user(get_current_user_id());

    if (!$guardian || empty($guardian->photo_file)) {
        return '';
    }

    return add_query_arg('sch_avatar', '1', self::url('account'));
}
```

### ٥-د. استعلام ميت في شاشة الرسوم

```php
// قبل — frontend/views/finance.php:10
$students = SCH_Students::list(['status' => 'active', 'per_page' => 300]);   // ← لا يُستعمل أبدًا
$list     = SCH_Finance::invoices([...]);
// …
$sch_pick = SCH_Students::list([... 'with' => false])['items'];              // ← المستعمَل فعلًا
```

`$students` يجلب **300 صف طالب بضمّ الشعبة وولي الأمر والنقل** في كل فتح للشاشة،
ولا يُستعمل في سطر واحد. حُذف السطر.

---

## ٦. أدوار مفقودة واسم دور خاطئ وLeaflet محليًا

**الـcommit:** `e17ef9d`

### ٦-أ. صلاحيتان لا يملكهما أي دور

`sch_manage_hr` و`sch_manage_assets` كانتا في قائمة `administrator` فقط داخل
`register_roles()`. وأربعة أقسام تعتمد عليهما
(`frontend/class-dashboard.php:78-81`):

```php
'hr'        => ['العقود والإجازات','sch_manage_hr',     'erp', 'الموارد البشرية','badge'],
'payroll'   => ['الرواتب',        'sch_manage_hr',     'erp', 'الموارد البشرية','wallet'],
'assets'    => ['الأصول والصيانة','sch_manage_assets', 'erp', 'العمليات',      'tool'],
'inventory' => ['المستودع',       'sch_manage_assets', 'erp', 'العمليات',      'box'],
```

**الأثر:** أربعة أقسام مبنيّة كاملة لا يفتحها إلا حساب المدير العام، ولا يوجد
دور يُوكَل إليه هذا العمل.

#### الدور الجديد

```php
// includes/class-activator.php — register_roles()
'sch_hr' => [
    'label' => __('مسؤول الموارد البشرية والعمليات', 'school-system'),
    'caps'  => ['read', 'sch_view_students', 'sch_manage_hr', 'sch_manage_assets',
                'sch_manage_staff', 'sch_send_messages'],
],
```

**وسُجِّل في ثلاثة مواضع** — لو نقص واحد منها لا يعمل:

| الموضع | لماذا |
|---|---|
| `SCH_Activator::register_roles()` | إنشاء الدور وقدراته في ووردبريس |
| `SCH_Staff::ROLES` | ليظهر في نموذج إضافة موظف وفي عمود الدور |
| `SCH_Org::LEVELS` | ليُعرف مستواه في سلسلة الإنشاء |

### ٦-ب. اسم دور خاطئ في سلّم الهيكل

```php
// قبل — modules/org/class-org.php
'sch_transport'  => 30,      // ← لا وجود لدور بهذا الاسم
```

الدور الحقيقي `sch_transport_supervisor`. و`level_of()` تقرأ
`self::LEVELS[$role] ?? 0` فتُعطيه **صفرًا**، و`can_create()` تشترط:

```php
return $mine > 0 && $target > 0 && $target < $mine;
```

**فلم يستطع أحد إنشاء مشرف نقل** إلا المدير العام (يتجاوز الفحص بـ`manage_options`).

```php
// بعد
// الاسم الصحيح للدور ‎sch_transport_supervisor‎ — وكان مكتوبًا
// ‎sch_transport‎ فيقرأ ‎level_of()‎ صفرًا…
'sch_transport_supervisor' => 30,
'sch_accountant' => 30,
'sch_hr'         => 30,
```

#### الدليل — نُفِّذ فعلًا

```
acting as: أحمد سالم عوبل (sch_principal, level 80)
  create sch_teacher                : YES  (level 20)
  create sch_transport_supervisor   : NO   (level 0)   ← العطل
  create sch_guard                  : YES  (level 10)
  create sch_accountant             : YES  (level 30)
```

بعد الإصلاح: `YES` للاثنين.

### ٦-ج. Leaflet من CDN إلى نسخة محلية

```php
// قبل — frontend/app/track.php
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
```

```php
// بعد
<link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/vendor/leaflet/leaflet.css')); ?>">
<script src="<?php echo esc_url(sch_asset('assets/vendor/leaflet/leaflet.js')); ?>" defer></script>
```

المكتبة الآن في `assets/vendor/leaflet/` (1.9.4 · 192 KB · ومعها أيقونات
العلامات). **البلاطات تبقى من OpenStreetMap** كما هو موثّق — المكتبة وحدها انتقلت.

**لماذا:** شاشة التتبع الحي هي «الميزة المميزة للمنتج» بنص الوثيقة. تعليقها على
CDN يعني: شبكة مدرسة مُرشَّحة أو انقطاع عند unpkg ⇒ **خريطة بيضاء** لكل أب في
اللحظة التي يريد فيها معرفة أين ابنه. وبلا SRI، أي تغيير عند الطرف الثالث
يُنفَّذ في متصفحه.

---

## ٧. الخط — عائلة واحدة مستضافة محليًا

**الـcommit:** `ac92691` · **16 ملفًا**

### ثلاث مشاكل متشابكة

1. **عائلتان** عبر ست واجهات: `Cairo` في الداشبورد والطالب والمعلم والسائق
   والبوابة، و`IBM Plex Sans Arabic` في تطبيق ولي الأمر وبوابة الدخول —
   وقاعدة النظام في `CLAUDE.md`: «عائلة خط واحدة… التمييز بالوزن لا بالعائلة».
2. **الرمز معرَّف في ثلاثة ملفات**: `shared-ui.css` و`dashboard.css` و`admin.css`.
   والأخير المحمَّل يطمس ما قبله — وقاعدة v5.0 تقول `shared-ui.css` هو البيت الوحيد.
3. **12 نداءً لـ`fonts.googleapis.com`** من ملفات القوالب.

### الأثر العملي

**حدث فعلًا أثناء المراجعة:** الشبكة هنا تحجب جوجل، فظهر النظام كله بخط المتصفح
الخام. وشبكة المدرسة تُرشَّح غالبًا. ومع ذلك كل فتح شاشة زيارة لخادم طرف ثالث،
وولي الأمر يفتح التطبيق مرتين يوميًا لكل ابن.

### الحل

```php
// قبل — frontend/views/layout.php
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="… shared-ui.css">
```

```php
// بعد
<link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/fonts.css')); ?>">
<link rel="stylesheet" href="<?php echo esc_url(sch_asset('assets/shared-ui.css')); ?>">
```

**تعريف واحد** في `assets/shared-ui.css` — وهو المكان الوحيد الذي تُبدَّل منه
العائلة كلها:

```css
/* البيت الوحيد لعائلة الخط في النظام. تبديلها من هنا وحدها. */
--sch-font-display: 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif;
--sch-font-body:    'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif;
--sch-mono:         ui-monospace, 'SFMono-Regular', 'Cascadia Mono', monospace;
```

وحُذف التعريف المكرَّر من `dashboard.css`:

```css
/* عائلة الخط تُورَث من shared-ui.css — لا تُعرَّف هنا ثانيةً.
   تعريفها مرتين بقيمتين مختلفتين هو ما كان يخلط الخطوط بين الشاشات. */
```

### الملفات المستضافة

`assets/fonts/` — ثمانية ملفات `woff2` بأربعة أوزان (400 · 500 · 600 · 700 —
وهي بالضبط ما توثّقه الهوية)، عربية ولاتينية منفصلتين بـ`unicode-range`:

```
plex-arabic-400.woff2  42 KB      plex-latin-400.woff2  19 KB
plex-arabic-500.woff2  44 KB      plex-latin-500.woff2  20 KB
plex-arabic-600.woff2  45 KB      plex-latin-600.woff2  20 KB
plex-arabic-700.woff2  43 KB      plex-latin-700.woff2  19 KB
                                            المجموع: 272 KB
```

و`assets/fonts.css` يعلنها بـ`font-display: swap` — فالنص يُقرأ بخط النظام ثم
يُستبدل، ولا يبقى فراغًا أبيض.

### كيف تتحقق

```bash
# لا نداء خارجي في HTML أي واجهة
curl -s https://موقعك/dashboard/ | grep -oE 'https?://[a-z0-9.-]+' | sort -u
# يجب ألّا يظهر fonts.googleapis.com ولا unpkg.com
```

والفحصان `EXTERNAL_HOST` و`CSS_FONT_HARDCODED` يمسكان عودة العطل.

---

## ٨. إعادة الهيكلة — تقسيم الملفين الضخمين

> **مهم:** هذا القسم **لا يغيّر سلوكًا**. نقل ميكانيكي مُثبَت آليًا.

### ٨-أ. `assets/dashboard.css` — 144 KB إلى 31 طبقة

**الـcommit:** `3e80987`

الملف كان **4048 سطرًا**، مبنيًّا من إحدى وثلاثين طبقة تاريخية تراكمت إصدارًا
بعد إصدار (v5.0 ← v6.0 ← v7.1 … ← v8.3)، كل واحدة تُضيف وتطمس بعض ما قبلها
جزئيًا. تعديل زر واحد يعني قراءة أربعة آلاف سطر لمعرفة أين يُطمس.

الآن لكل طبقة ملفها في `assets/dashboard/` باسمها العربي:

```
00-Dashboard-نظام-التصميم-الكامل.css      36 KB   ← الأساس
12-بطاقات-الطلاب-أورora-جلاس.css          16 KB
22-هوية-أُصول-SaaS-طبقة-الداشبورد.css     12 KB
05-قائمة-الصلاحيات-إعادة-تصميم.css        12 KB
17-شريط-التنقّل.css                        …
_order.txt                                 ← الترتيب، والترتيب يحمل معنى
```

`assets/dashboard.css` صار **مولَّدًا**. والتقسيم **نصّي بحت** — لا سطر تغيّر:

```bash
$ python3 tools/css-split.py check
مطابق تمامًا (147600 بايت · 31 جزءًا)

$ cmp dashboard.css.original assets/dashboard.css && echo IDENTICAL
IDENTICAL
```

#### دورة عمل المبرمج الجديدة

```bash
vim assets/dashboard/17-شريط-التنقّل.css   # ١. عدّل الجزء
python3 tools/css-split.py build            # ٢. ابنِ المولَّد
./dev.sh sync                               # (يفعل الخطوة ٢ عنك)
```

وفحص `CSS_PARTS_STALE` يمسك حالتين:
- مولَّد لا يطابق أجزاءه ⇒ تعديلك سيضيع عند أول بناء.
- **جزء غير مذكور في `_order.txt` ⇒ يُتجاهَل بصمت** (وقعتُ في هذه أثناء
  اختبار الدورة، فصارت فحصًا).

### ٨-ب. `frontend/class-dashboard.php` — 1857 إلى 980 سطرًا

**الـcommit:** `a1e264d`

الملف كان يحمل الموجّه والسجل المركزي **و106 معالج فعل**. المعالجات انتقلت إلى
سبع سمات (traits) في `frontend/controllers/`:

| السمة | المجال | معالجات |
|---|---|---|
| `trait-academic.php` | الشعب والسنوات والمواد والاختبارات والجدول والمحتوى | 19 |
| `trait-services.php` | العيادة والأدوية والمكتبة والزوار والسلامة والأصول | 18 |
| `trait-system.php` | الموظفون والصلاحيات والإعدادات والاستيراد والجسر | 18 |
| `trait-students.php` | الطلاب وأولياء الأمور والملفات والشهادات | 16 |
| `trait-attendance.php` | الحضور والملاحظات والإنذارات والعهدة | 14 |
| `trait-finance.php` | الرسوم والفواتير والمحاسبة والرواتب والعقود | 14 |
| `trait-transport.php` | الباصات والمسارات والرحلات والاشتراكات | 8 |
| | **المجموع** | **107** |

> **107 دالة مقابل 106 فعل مسجَّل — لا تناقض:** الدالة الزائدة
> `merge_settings()` مُعينة داخلية تناديها `do_save_settings()`، ونُقلت معها
> إلى `trait-system.php`. تحقّق:
> `grep -c 'static function' frontend/controllers/*.php` ⇒ 107،
> وعدد مداخل `actions()` ⇒ 106.

```php
// frontend/class-dashboard.php
final class SCH_Dashboard
{
    // معالِجات الأفعال موزّعة على سمات حسب المجال في
    // frontend/controllers/. السمة تُدمَج عند الترجمة، فالسلوك
    // والخصوصية و self:: كما هي — والسجل المركزي actions() هنا.
    use SCH_Ctl_Students;
    use SCH_Ctl_Academic;
    use SCH_Ctl_Attendance;
    use SCH_Ctl_Finance;
    use SCH_Ctl_Transport;
    use SCH_Ctl_Services;
    use SCH_Ctl_System;
```

والسمات تُحمَّل **قبل** الصنف في `includes/class-loader.php`.

#### لماذا `trait` لا صنف مستقل

السمة تُدمَج في الصنف **عند الترجمة**، فتبقى:
- `private static` كما هي — لا حاجة لجعل شيء عامًّا.
- `self::` تعمل كما كانت — لا تمرير حالة ولا وسيط.
- **السجل المركزي `actions()` كما هو حرفًا بحرف** — و106 مدخل فيه لم يُلمس.
- مسار التنفيذ لم يتغيّر: `handle_post()` ← نونس ← صلاحية ← معالج.

التركيب (composition) كان سيتطلّب تعديل 106 مدخل وتمرير حالة — وكل تعديل فرصة عطل.

#### إثبات أن لا شيء تغيّر

```
عدد الدوال قبل: 145      بعد: 145
مفقودة: لا شيء           مضافة: لا شيء
أجسام دوال تغيّرت: لا شيء   (مقارنة نصّية بعد توحيد المسافات)
```

```php
// وبالانعكاس على النظام الشغّال:
$r = new ReflectionClass('SCH_Dashboard');
count($r->getMethods());     // 145
$r->getTraitNames();         // 7 سمات
// و106 فعل مسجَّل، وصفر معالج مفقود
```

#### والأهم — اختبار حقيقي عبر HTTP

```
POST /dashboard/classes/  sch_action=add_class + نونس صحيح
→ 302  ?ok=add_class        والشعب في القاعدة: 6 ← 7        ✓

POST نفس الشيء بنونس خاطئ
→ 302  ?err=nonce           والقاعدة بلا تغيير              ✓

POST بفعل مجهول
→ 302  ?err=unknown_action  والقاعدة بلا تغيير              ✓
```

**فرض النونس والصلاحية لم ينكسر.**

---

## ٩. الأدوات المضافة للصيانة والتسليم

هذه ملفات **لا تدخل نسخة الإنتاج** (`build.sh` يستبعدها).

### `audit.py` — 34 فحصًا آليًا

`CLAUDE.md` يقول نصًّا: «قبل أي تسليم شغّل `python3 audit.py` … لا تسلّم ونتيجته
غير نظيفة» — **والملف لم يكن موجودًا في النسخة إطلاقًا**.

| الفحص | ماذا يمسك |
|---|---|
| `TPL_UNBALANCED` | `if(…):` بلا `endif` — صفحة بيضاء بلا دليل |
| `TAG_BROKEN` | خطأ نحوي (عبر `php -l`) |
| `SCHEMA_DUP_COLUMN` | عمود مكرر في `CREATE TABLE` — الجدول لا يُنشأ بصمت |
| `SQL_UNKNOWN_COLUMN` | عمود يُستعلم عنه ولا وجود له ← **أمسك عطل المعلم** |
| `PREPARE_ARITY` | عدد `%s/%d` لا يطابق الوسائط |
| `ACTION_NO_HANDLER` | نموذج يرسل فعلًا لا يعرفه السجل |
| `ACTION_METHOD_MISSING` | فعل مسجَّل ومعالجه غير معرَّف |
| `FORM_NO_NONCE` | نموذج POST بلا نونس |
| `APP_NONCE_UNKNOWN` | نونس لا يتحقق منه أي معالج |
| `CAP_ORPHAN` | صلاحية لا يملكها أي دور ← **أمسك عطل HR** |
| `ROLE_NO_CAPS` · `ROLE_NO_HOME` | دور بلا صلاحيات · دور بلا وجهة |
| `SECTION_NO_VIEW` | قسم بلا ملف عرض |
| `ROUTE_NOT_FLUSHED` | ← **أمسك عطل الـ404** |
| `LINK_DEAD_PARAM` | رابط بمعامل لا يقرأه أحد |
| `LIST_SHAPE` | ← **أمسك عطل شاشة الصلاحيات** |
| `CSS_DUP_LAYOUT` | صنف معرَّف مرتين بخصائص تخطيط ← **38 موضعًا** |
| `CSS_VAR_UNDEFINED` · `CSS_FONT_HARDCODED` · `SCH_TOKEN_HOME` | رموز وخطوط |
| `CSS_PARTS_STALE` | المولَّد لا يطابق أجزاءه |
| `EXTERNAL_HOST` | مورد من CDN خارجي |
| `MODAL_UNBALANCED` · `MODAL_NO_OPENER` | نوافذ |
| `FIELD_UNLABELLED` · `IMG_NO_ALT` · `PHYSICAL_PROPS` | وصولية وRTL |
| `FILE_NOT_LOADED` · `CLASS_UNDEFINED` · `FUNC_UNDEFINED` | تحميل ووجود |
| `TABLE_UNKNOWN` · `TABLE_UNUSED` | جداول |
| `VERSION_SYNC` · `MONEY_RAW` | النسخة والمبالغ |

```bash
python3 audit.py              # كل الفحوص
python3 audit.py --list       # الأسماء والوصف
python3 audit.py -o CSS_DUP_LAYOUT
python3 audit.py --strict     # التحذير يُفشِل أيضًا
```

### `tools/audit-selftest.py` — ما يجعل الفحص جديرًا بالثقة

**كل فحص يُثبت بإحداث عطله ثم إزالته: 32/32.**

فحص صامت لا يُعرَف: هل صمت لأن الكود سليم أم لأنه معطوب؟ وهذا وقع فعلًا —
الاختبار الذاتي كشف **ثلاثة فحوص كانت معطوبة صامتة**:

| الفحص | ما كان معطوبًا | الإصلاح |
|---|---|---|
| `TPL_UNBALANCED` | تعبير نمطي لا يميّز `if():` من `? :` من `case x:` — **119 نتيجة خاطئة** على كود سليم | أُعيد بناؤه بمحلّل PHP نفسه (`tools/php-tokens.php` + `token_get_all`) |
| `SCHEMA_DUP_COLUMN` و`SQL_UNKNOWN_COLUMN` | تعبير `CREATE TABLE` توقّع `) $charset` والحقيقة `) {$charset};` — **لم يطابق شيئًا فكانا صامتين تمامًا** | صُحّح التعبير |
| `PREPARE_ARITY` | يقسم على أول فاصلة، ونص SQL فيه فواصل — 6 نتائج خاطئة | مسح يحترم علامات النص والأقواس |

### `dev.sh` — بيئة كاملة بأمر واحد

```bash
./dev.sh up      # ووردبريس + MySQL + الإضافة + بيانات   (~3 دقائق)
./dev.sh sync    # بعد كل تعديل — أقل من ثانية
./dev.sh reset   # من صفر
./dev.sh seed    # بيانات تجريبية فقط
./dev.sh audit   # php -l على كل ملف + audit.py
./dev.sh shots   # صورة لكل شاشة + تقرير أخطاء
./dev.sh logs    # متابعة سجل أخطاء PHP
./dev.sh stop
```

يبني مدرسة كاملة: **٤٠ طالبًا · ٢٠ ولي أمر · ١٢ موظفًا بكل دور · ٦ شعب ·
مسار وباص و٤ نقاط · ٤٨٠ سجل حضور**، وحسابًا بكلمة مرور معروفة لكل واجهة.

**درسان تعلّمهما السكربت نفسه أثناء اختباره** (موثّقان داخله):
- **الوصلة الرمزية للإضافة لا تعمل** — خادم PHP المدمج يرفض خدمة ملف خارج جذر
  المستند، و`plugin_dir_url(__FILE__)` تحسب الرابط من المسار الحقيقي فيخرج
  `/wp-content/plugins/home/user/…`. النتيجة كانت **260 استجابة 500**.
  فصار نسخًا يُبنى جانبًا ويُبدَّل بحركة واحدة.
- **`sync` يعيد تشغيل الخادم** — الخادم المدمج عملية واحدة طويلة العمر
  و`__FILE__` يُحسب عند أول ترجمة ويبقى في ذاكرتها.

### `build.sh` — لا يبني قبل أن يُفحَص

```bash
./build.sh              # بالنسخة الحالية
./build.sh --patch      # 8.3.0 ← 8.3.1
./build.sh 8.4.0        # رقم صريح
```

يرفع الرقم في **موضعين** (ترويسة الإضافة و`SCH_VERSION`)، يبني المولَّدات،
ثم **يرفض البناء** إن لم يمرّ `php -l` و`audit.py`. ويستبعد أدوات التطوير من الـZIP.

### ملفات أخرى

| الملف | ماذا |
|---|---|
| `README.md` | التركيب · البنية · القواعد · **شروط النشر الثلاثة** |
| `CHANGELOG.md` | كل عطل بسببه وأثره، وقائمة ما يبقى مفتوحًا |
| `languages/school-system.pot` | **1865 نصًّا**. الإضافة كانت تنادي `load_plugin_textdomain` على مجلد غير موجود |
| `tools/seed.php` · `tools/shoot.js` | البيانات التجريبية · الجولة المصوّرة |
| `tools/css-split.py` · `tools/php-split-dashboard.py` | أدوات التقسيم |
| `tools/php-tokens.php` | محلّل PHP يخدم `audit.py` |

---

## ١٠. جرد الملفات

### عُدِّلت — منطق (22 ملفًا)

```
api/class-auth.php                       ٣ · الأمن كاملًا
includes/class-activator.php             ٢·٣·٦ · التوجيه · المفتاح · الدور الجديد
includes/class-loader.php                ٢ · تفريغ التوجيه + التعافي الذاتي
modules/staff/class-perms.php            ٤ · فصل القفلين
modules/staff/class-staff.php            ١·٦ · insert_id · تسجيل الدور
modules/org/class-org.php                ٦ · اسم الدور + مستوى الدور الجديد
modules/academic/class-academic.php      ١
modules/academic/class-assessment.php    ١ (موضعان)
modules/accounting/class-accounting.php  ١
modules/clinic/class-medication.php      ١
modules/enrollment/class-enrollment.php  ١
modules/finance/class-finance.php        ١
modules/hr/class-hr.php                  ١ (موضعان)
modules/learning/class-content.php       ١·٧ · insert_id · خط الصندوق المعزول
modules/services/class-services.php      ١ (خمسة مواضع)
modules/transport/class-transport.php    ١ (موضعان)
frontend/class-app.php                   ٥-ج · avatar_url
frontend/class-teacher.php               ٥-أ · اسم العمود
frontend/class-dashboard.php             ٢·٨ · التوجيه + السمات
frontend/views/perms.php                 ٥-ب · شكل list()
frontend/views/finance.php               ٥-د · حذف استعلام ميت
frontend/app/track.php                   ٦-ج · Leaflet محليًا
admin/class-admin.php                    ٧ · الخط محليًا
```

### عُدِّلت — أصول وقوالب (الخط، القسم ٧)

```
assets/shared-ui.css · assets/dashboard.css · assets/admin.css
assets/app.css · assets/driver.css · assets/gate.css
assets/student.css · assets/teacher.css
frontend/views/layout.php · frontend/views/student-print.php
frontend/app/layout.php · frontend/portal.php
frontend/driver/layout.php · frontend/gate/layout.php
frontend/student/layout.php · frontend/teacher/layout.php
```

### جديدة

```
audit.py · dev.sh · build.sh                  الأدوات
README.md · CHANGELOG.md                      التوثيق
languages/school-system.pot                   1865 نصًّا
assets/fonts.css + assets/fonts/              الخط محليًا (8 ملفات)
assets/vendor/leaflet/                        الخريطة محليًا (7 ملفات)
assets/dashboard/                             31 جزء CSS + _order.txt
frontend/controllers/trait-*.php              7 سمات · 107 معالجًا
tools/audit-selftest.py · css-split.py
tools/php-split-dashboard.py · php-tokens.php
tools/seed.php · tools/shoot.js
```

---

## ١١. كيف يتحقق المبرمج من كل شيء

```bash
git clone <repo> && cd aHMED/school-system
git checkout claude/madri-system-review-w84eir

# ١) الفرق كاملًا عن النسخة الأصلية
git diff ce7ddc7 HEAD --stat

# ٢) الفرق في المنطق وحده
git diff ce7ddc7 HEAD -- api includes modules \
  frontend/class-app.php frontend/class-teacher.php \
  frontend/views/perms.php frontend/views/finance.php

# ٣) كل إصلاح على حدة — commit مستقل موصوف
git log --oneline ce7ddc7..HEAD
git show ed9fd4d          # مثال: عطل insert_id

# ٤) الفحص النحوي
find . -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors'

# ٥) الفحص الآلي
python3 audit.py

# ٦) أن كل فحص يمسك عطله فعلًا
python3 tools/audit-selftest.py

# ٧) أن CSS المولَّد يطابق أجزاءه بايتًا ببايت
python3 tools/css-split.py check

# ٨) تشغيل النظام كاملًا ورؤيته
./dev.sh up
./dev.sh shots
```

### النتيجة المتوقّعة اليوم

| الفحص | النتيجة |
|---|---|
| `php -l` على 219 ملفًا | **0 خطأ** |
| `audit.py` | **0 خطأ** · 49 تحذيرًا (بنود مفتوحة، القسم ١٢) |
| `audit-selftest.py` | **32/32** |
| `css-split.py check` | **مطابق تمامًا** (147600 بايت · 31 جزءًا) |
| جولة الشاشات الآلية | **0 بند** · 65 صورة · **0 خطأ PHP** |
| تركيب الـZIP على موقع نظيف | 68 جدولًا · 17 قاعدة توجيه · كل الواجهات تستجيب |

---

## ١٢. ما لم يُصلَح — عمدًا

بنود حقيقية تُركت لأنها تخصّ قرار المالك أو تحتاج مراجعة بصرية.
**كلها يمسكها `audit.py` فلا تُنسى.**

| البند | العدد | الفحص | لماذا تُرك |
|---|---|---|---|
| صنف CSS معرَّف مرتين بخصائص تخطيط | 38 | `CSS_DUP_LAYOUT` | نفس العطل الموثّق الذي أخفى بطاقات الأبناء مرتين (`.scha-track` ثم `.scha-kid`). أخطرها `.sch-top` معرَّف **٥ مرات**. إصلاحه يحتاج مقارنة «قبل/بعد» بصرية لكل شاشة من الـ47. |
| مبالغ بـ`number_format()` بدل `sch_money()` | 10 | `MONEY_RAW` | في تطبيق ولي الأمر. تجميلي لا وظيفي. |
| جدول `assignments` يُنشأ ولا يُستعمل | 1 | `TABLE_UNUSED` | إما ميزة ناقصة أو بقية محذوفة — المالك يعرف أيّهما. |
| `--sch-accent` = `#5B6B8C` والوثيقة تقول `#1F5F52` | — | — | قرار هوية بصرية. |
| `CLAUDE.md` يصف v6.1 والكود على 8.3.x | — | — | إصداران غير موثّقين. يحتاج مراجعة المالك. |

### وثلاثة شروط نشر ليست في الكود

1. **على Nginx مستندات الطلاب مكشوفة.** الحماية تُكتب كملف `.htaccess` في
   `uploads/sch-private/` (`modules/enrollment/class-enrollment.php:46`)،
   و**Nginx لا يقرأ `.htaccess` إطلاقًا**:
   ```nginx
   location ~* /wp-content/uploads/sch-private/ {
       deny all;
       return 403;
   }
   ```
2. **لغة ووردبريس يجب أن تكون عربية.** الكود يستعمل `wp_date()` صحيحًا،
   لكن بلا حزمة اللغة العربية تُعرض **التواريخ بالإنجليزية** داخل واجهة عربية.
   النصوص نفسها لا تتأثر لأنها عربية في المصدر.
3. **الروابط الدائمة شرط لا تحسين** — كل المسارات قواعد توجيه لا معاملات استعلام.

---

## ملحق — الالتزامات بالترتيب

| Commit | ماذا |
|---|---|
| `ce7ddc7` | **خط الأساس** — النسخة 8.3.0 كما وصلت، بلا تعديل |
| `ed9fd4d` | إصلاح `insert_id` (القسم ١) |
| `98ad748` | إصلاح 404 للواجهات الست (القسم ٢) |
| `783c6da` | إصلاح 403 للعهدة والتدقيق (القسم ٤) |
| `e444335` | أربعة أعطال تشغيل (القسم ٥) |
| `ac92691` | توحيد الخط واستضافته (القسم ٧) |
| `a2dc54d` | الأمن — JWT وحد المحاولات والتدوير (القسم ٣) |
| `078a5b5` | `audit.py` والاختبار الذاتي (القسم ٩) |
| `e17ef9d` | الأدوار وLeaflet (القسم ٦) |
| `c1d9488` | `dev.sh` + تعافي قواعد التوجيه ذاتيًا (٢ · ٩) |
| `4c05533` | `build.sh` · README · CHANGELOG · pot (القسم ٩) |
| `3e80987` | تقسيم `dashboard.css` (٨-أ) |
| `a1e264d` | تقسيم معالجات الداشبورد (٨-ب) |
| `e7ee0e9` | تقرير المراجعة (HTML) |
| `dee83cc` | رفع النسخة إلى 8.3.1 |

كل commit مستقل وقابل للتراجع وحده.
