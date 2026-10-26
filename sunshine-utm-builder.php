<?php
/**
 * Plugin Name: Sunshine UTM Builder
 * Plugin URI: https://wpsunshine.com/plugins/utm-builder/
 * Description: Build UTM links one at a time or hundreds at once, with GA4 channel checks, QR codes, and click-counting short links on your own domain.
 * Version: 1.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: WP Sunshine
 * Author URI: https://wpsunshine.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sunshine-utm-builder
 */

defined( 'ABSPATH' ) || exit;

define( 'SUNSHINE_UTM_VERSION', '1.0.1' );
define( 'SUNSHINE_UTM_FILE', __FILE__ );
define( 'SUNSHINE_UTM_DIR', plugin_dir_path( __FILE__ ) );
define( 'SUNSHINE_UTM_URL', plugin_dir_url( __FILE__ ) );

require_once SUNSHINE_UTM_DIR . 'includes/class-sunshine-utm.php';

/**
 * The plugin.
 *
 * @return Sunshine_UTM
 */
function sunshine_utm() {
	return Sunshine_UTM::instance();
}
add_action( 'plugins_loaded', 'sunshine_utm' );

register_activation_hook( __FILE__, array( 'Sunshine_UTM', 'activate' ) );
