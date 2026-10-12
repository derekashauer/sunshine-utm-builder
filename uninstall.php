<?php
/**
 * Runs when the plugin is deleted. Data is only removed when the owner turned that on in Settings,
 * so reinstalling later brings back their presets and batches.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sunshine_utm_settings = get_option( 'sunshine_utm_settings', array() );
if ( empty( $sunshine_utm_settings['delete_data'] ) ) {
	return;
}

require_once __DIR__ . '/includes/class-sunshine-utm.php';
require_once __DIR__ . '/includes/class-database.php';
Sunshine_UTM_Database::remove_all();
