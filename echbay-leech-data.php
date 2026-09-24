<?php
/**
 * Plugin Name: EchBay Leech Data
 * Plugin URI:  https://echbay.com/
 * Description: Import / crawl content from remote HTML sites into WordPress posts (independent of EchBay theme).
 * Version:     0.1.0
 * Author:      Dao Quoc Dai
 * Author URI:  https://echbay.com/
 * Text Domain: echbay-leech-data
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Read plugin version from VERSION or version.txt.
 */
function eld_get_plugin_version()
{
	foreach (array('VERSION', 'version.txt') as $version_file) {
		$path = __DIR__ . '/' . $version_file;
		if (is_readable($path)) {
			return trim((string) file_get_contents($path));
		}
	}

	return '0.0.0';
}

define('ELD_VERSION', eld_get_plugin_version());
define('ELD_FILE', __FILE__);
define('ELD_PATH', plugin_dir_path(__FILE__));
define('ELD_URL', plugin_dir_url(__FILE__));
define('ELD_BASENAME', plugin_basename(__FILE__));

require_once ELD_PATH . 'includes/autoload.php';

add_action('plugins_loaded', static function () {
	\ELD\Plugin::instance()->init();
});

register_activation_hook(__FILE__, static function () {
	$upload = wp_upload_dir();
	$dir = trailingslashit($upload['basedir']) . 'eld-cache';
	if (!is_dir($dir)) {
		wp_mkdir_p($dir);
	}
	if (!file_exists($dir . '/index.php')) {
		file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
	}
});
