<?php
/**
 * The plugin's tables: presets, batches, the links in each batch, short links on this site, and
 * clicks on those short links (one row per link per day, a count only, nothing about the visitor).
 *
 * Times are stored in UTC and shown in the site's time zone. Bump VERSION when a table changes and
 * dbDelta adds the new columns on the next admin or front-end load.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_Database {

	const VERSION = 4;

	const VERSION_OPTION = 'sunshine_utm_db_version';

	public static function tables() {
		global $wpdb;
		return array(
			'presets'     => $wpdb->prefix . 'sunshine_utm_presets',
			'batches'     => $wpdb->prefix . 'sunshine_utm_batches',
			'links'       => $wpdb->prefix . 'sunshine_utm_links',
			'short_links' => $wpdb->prefix . 'sunshine_utm_short_links',
			'clicks'      => $wpdb->prefix . 'sunshine_utm_short_link_clicks',
		);
	}

	public static function maybe_install() {
		if ( (int) get_option( self::VERSION_OPTION ) < self::VERSION ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		$tables  = self::tables();
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$tables['presets']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			utm_source varchar(255) NOT NULL DEFAULT '',
			utm_medium varchar(255) NOT NULL DEFAULT '',
			utm_campaign varchar(255) NOT NULL DEFAULT '',
			utm_term varchar(255) NOT NULL DEFAULT '',
			utm_content varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY name (name)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['batches']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			method varchar(20) NOT NULL DEFAULT '',
			notes text NULL,
			link_count int(10) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['links']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			batch_id bigint(20) unsigned NOT NULL,
			url text NOT NULL,
			tagged_url text NOT NULL,
			short_url varchar(255) NOT NULL DEFAULT '',
			utm_source varchar(255) NOT NULL DEFAULT '',
			utm_medium varchar(255) NOT NULL DEFAULT '',
			utm_campaign varchar(255) NOT NULL DEFAULT '',
			utm_term varchar(255) NOT NULL DEFAULT '',
			utm_content varchar(255) NOT NULL DEFAULT '',
			extra_params text NULL,
			notes text NULL,
			url_ok tinyint(1) NOT NULL DEFAULT 1,
			url_status varchar(191) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY batch_id (batch_id)
			) $charset;"
		);

		// Short links on this site's own domain (Sunshine_UTM_Short_Links).
		dbDelta(
			"CREATE TABLE {$tables['short_links']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(32) NOT NULL,
			url text NOT NULL,
			url_hash char(40) NOT NULL,
			utm_source varchar(255) NOT NULL DEFAULT '',
			utm_medium varchar(255) NOT NULL DEFAULT '',
			utm_campaign varchar(255) NOT NULL DEFAULT '',
			utm_term varchar(255) NOT NULL DEFAULT '',
			utm_content varchar(255) NOT NULL DEFAULT '',
			clicks bigint(20) unsigned NOT NULL DEFAULT 0,
			last_click_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY url_hash (url_hash),
			KEY utm_campaign (utm_campaign(191))
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$tables['clicks']} (
			short_link_id bigint(20) unsigned NOT NULL,
			day date NOT NULL,
			clicks int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (short_link_id,day),
			KEY day (day)
			) $charset;"
		);

		// Version 4 added the tag columns to short links. Fill them in for links made before that.
		if ( (int) get_option( self::VERSION_OPTION ) < 4 ) {
			require_once SUNSHINE_UTM_DIR . 'includes/class-short-links.php';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name from tables().
			$rows = $wpdb->get_results( "SELECT id, url FROM {$tables['short_links']}", ARRAY_A );
			foreach ( (array) $rows as $row ) {
				$wpdb->update( $tables['short_links'], Sunshine_UTM_Short_Links::tags_from_url( $row['url'] ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Drop every table and option. Only uninstall.php calls this, and only when the owner asked for it.
	 */
	public static function remove_all() {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		}
		delete_option( self::VERSION_OPTION );
		delete_option( 'sunshine_utm_custom_codes' );
		delete_option( Sunshine_UTM::OPTION );
	}
}
