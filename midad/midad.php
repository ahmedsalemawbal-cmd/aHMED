<?php
/**
 * Plugin Name:       مداد — منصّة الملفّات المدرسية
 * Plugin URI:        https://midad.sa
 * Description:       منصّة اشتراكات للمدارس والمعلّمين: قوالب الملفّات المدرسية تُملأ وتُصدَّر، وجداول نور تُنزَّل وتُصدَّر.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            أحمد سالم
 * Text Domain:       midad
 * Domain Path:       /languages
 *
 * ══════════════════════════════════════════════════════════════════════
 *
 * **مداد ليس نسخةً من أُصول.** أُصول أربعون جدولًا لمدرسةٍ واحدة على موقعٍ
 * واحد؛ ومداد آلاف المشتركين على تثبيتٍ واحد. والفرق ليس في الحجم بل في
 * أنّ كل صفٍّ هنا يجب أن يعرف **لمن هو**.
 *
 * وخدمتان لا تلتقيان:
 *   ① **القوالب** — مكتبةٌ من الملفّات المدرسية تُملأ في الشاشة وتُصدَّر.
 *   ② **نور** — جداولُ تُنزَّل بأعمدتها كما تُقرأ وتُصدَّر.
 *
 * ولا رابط بينهما بقرارٍ من المالك: الاختلاط بينهما يُقيّد كلًّا منهما
 * بالآخر بلا فائدةٍ لأحد.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('MDD_VERSION', '0.1.0');
define('MDD_FILE', __FILE__);
define('MDD_PATH', plugin_dir_path(__FILE__));
define('MDD_URL', plugin_dir_url(__FILE__));
define('MDD_API_NS', 'midad/v1');

/** جذر الموقع والداشبورد — كلاهما داخل مداد، فالمسار واحدٌ لا اثنان. */
define('MDD_BASE', 'midad');

require_once MDD_PATH . 'includes/class-loader.php';

register_activation_hook(__FILE__, ['MDD_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['MDD_Activator', 'deactivate']);

add_action('plugins_loaded', ['MDD_Loader', 'boot'], 5);
