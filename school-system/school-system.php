<?php
/**
 * Plugin Name:       School System
 * Description:       نظام إدارة مدرسة — طلاب، نقل، حضور، مالية. يشمل REST API للتطبيقات.
 * Version:           10.8.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Text Domain:       school-system
 * Domain Path:       /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('SCH_VERSION', '10.8.0');
define('SCH_FILE', __FILE__);
define('SCH_PATH', plugin_dir_path(__FILE__));
define('SCH_URL', plugin_dir_url(__FILE__));
define('SCH_API_NS', 'school/v1');

require_once SCH_PATH . 'includes/functions.php';
require_once SCH_PATH . 'includes/icons.php';
require_once SCH_PATH . 'includes/class-qr.php';
require_once SCH_PATH . 'includes/class-activator.php';
require_once SCH_PATH . 'includes/class-loader.php';

register_activation_hook(__FILE__, ['SCH_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['SCH_Activator', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('school-system', false, dirname(plugin_basename(SCH_FILE)) . '/languages');
    SCH_Loader::init();
    SCH_Activator::maybe_upgrade();
});
