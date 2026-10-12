<?php
/**
 * Conversion Bridge has its own small UTM builder (a tool on its Tools tab, a "UTM link" in the
 * admin bar, and a "UTM Link" under each post). When both plugins are active, this plugin's
 * versions replace those, so nobody sees two of each.
 *
 * Conversion Bridge already stepped aside for this plugin's first draft by checking for its old
 * class name (UTM_Link_Builder). This plugin was renamed before release, so the hiding is done from
 * here instead, which works with every Conversion Bridge version from 1.12 (when the tools filter
 * was added):
 * - conversion_bridge_available_tools: drop the utm-builder tool, which also drops its Tools menu link.
 * - admin_bar_menu (after CB's priority 100): remove the cb-utm-builder node.
 * - post_row_actions / page_row_actions (after CB's priority 10): remove cb-utm-link.
 * - admin_init: Conversion Bridge's own links to its builder (onboarding, UTM Reports, old
 *   bookmarks) go to tab=tools&tool=utm-builder, which would otherwise show a different tool. They're
 *   sent to this plugin's builder, keeping the page that was filled in (utm_base_url).
 *
 * It also reads Conversion Bridge's events so the Short Links tab can show conversions next to
 * clicks (conversions()). Conversion Bridge saves the UTM tags of the page a visitor landed on,
 * so a conversion counts for a short link when its visitor had a page view with exactly the
 * link's five tags, since the link was made. Tags aren't unique to one link (the same tags typed
 * by hand count too), so the screens say "with these tags". It only runs on this plugin's own
 * screens, uses Conversion Bridge's UTM indexes, and its name follows Conversion Bridge's white
 * label setting.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_Conversion_Bridge {

	public function __construct() {
		add_filter( 'conversion_bridge_available_tools', array( $this, 'remove_tool' ) );
		add_action( 'admin_bar_menu', array( $this, 'remove_admin_bar_link' ), 110 );
		add_filter( 'post_row_actions', array( $this, 'remove_row_action' ), 20 );
		add_filter( 'page_row_actions', array( $this, 'remove_row_action' ), 20 );
		add_action( 'admin_init', array( $this, 'redirect_tool' ) );
	}

	/**
	 * Conversion Bridge is active and has its events table.
	 *
	 * @return bool
	 */
	public static function active() {
		static $active = null;
		if ( null === $active ) {
			global $wpdb;
			$table  = $wpdb->prefix . 'cb_events';
			$active = function_exists( 'CB' ) && $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return $active;
	}

	/**
	 * Conversion Bridge's name, or the one it's white labeled with.
	 *
	 * @return string
	 */
	public static function name() {
		return method_exists( CB(), 'get_white_label_name' ) ? CB()->get_white_label_name() : 'Conversion Bridge';
	}

	/**
	 * Conversions from visitors who arrived with a short link's tags, since the link was made.
	 *
	 * @param array $link A short link row: the five utm columns and created_at.
	 * @return int|null Null when Conversion Bridge isn't there to ask.
	 */
	public static function conversions( $link ) {
		global $wpdb;
		if ( ! self::active() || '' === (string) $link['utm_source'] ) {
			return null;
		}
		$table = $wpdb->prefix . 'cb_events';
		// Conversion Bridge's own list of conversion types when it's loaded (it reads the types
		// actually in the table), otherwise everything that isn't a page load.
		$conversion = function_exists( 'cb_report_conversion_sql' ) ? cb_report_conversion_sql( 'c' ) : "c.event_type NOT IN ( 'pageview', 'page' )";
		// A day early, since Conversion Bridge's event times may be in the site's time zone.
		$since = gmdate( 'Y-m-d H:i:s', strtotime( $link['created_at'] . ' UTC' ) - DAY_IN_SECONDS );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name from $wpdb->prefix; $conversion is built by Conversion Bridge from esc_sql'd values.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} c WHERE {$conversion} AND c.session IN (
					SELECT p.session FROM {$table} p
					WHERE p.event_type IN ( 'pageview', 'page' ) AND p.utm_source = %s AND p.utm_medium = %s
						AND p.utm_campaign = %s AND p.utm_term = %s AND p.utm_content = %s AND p.event_time >= %s
				)",
				$link['utm_source'],
				$link['utm_medium'],
				$link['utm_campaign'],
				$link['utm_term'],
				$link['utm_content'],
				$since
			)
		);
		// phpcs:enable
	}

	public function remove_tool( $tools ) {
		unset( $tools['utm-builder'] );
		return $tools;
	}

	public function remove_admin_bar_link( $bar ) {
		$bar->remove_node( 'cb-utm-builder' );
	}

	public function remove_row_action( $actions ) {
		unset( $actions['cb-utm-link'] );
		return $actions;
	}

	public function redirect_tool() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect.
		if ( ! function_exists( 'CB' ) || ! isset( $_GET['page'], $_GET['tab'], $_GET['tool'] ) || 'tools' !== $_GET['tab'] || 'utm-builder' !== $_GET['tool'] ) {
			return;
		}
		$slug = method_exists( CB(), 'get_white_label_slug' ) ? CB()->get_white_label_slug() : 'conversion_bridge';
		if ( $slug !== $_GET['page'] ) {
			return;
		}
		$url = isset( $_GET['utm_base_url'] ) ? esc_url_raw( wp_unslash( $_GET['utm_base_url'] ) ) : '';
		// phpcs:enable
		wp_safe_redirect( Sunshine_UTM::builder_url( $url ) );
		exit;
	}
}
