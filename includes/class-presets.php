<?php
/**
 * Presets: saved sets of tags to reuse. Values can hold tokens ({date}, {slug} and so on), which
 * are filled in when a link is built, not when the preset is saved.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_Presets {

	public static function get_all() {
		global $wpdb;
		$table = Sunshine_UTM_Database::tables()['presets'];
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @param int $id Preset ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Sunshine_UTM_Database::tables()['presets'];
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	/**
	 * Add or update a preset.
	 *
	 * @param array $data name plus the five utm keys.
	 * @param int   $id   Preset to update, or 0 to add one.
	 * @return int|false Preset ID, or false if the database refused it.
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$table = Sunshine_UTM_Database::tables()['presets'];

		$row = array( 'name' => self::clean( isset( $data['name'] ) ? $data['name'] : '', 191 ) );
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			$row[ $key ] = self::clean( isset( $data[ $key ] ) ? $data[ $key ] : '', 255 );
		}

		if ( $id ) {
			$done = $wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return false === $done ? false : (int) $id;
		}

		$row['created_at'] = current_time( 'mysql', true );
		return $wpdb->insert( $table, $row ) ? (int) $wpdb->insert_id : false; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( Sunshine_UTM_Database::tables()['presets'], array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * The preset's tags with tokens filled in for one link.
	 *
	 * @param array  $preset Preset row.
	 * @param string $url    Link.
	 * @param int    $index  Position in the batch.
	 * @return array utm key => value
	 */
	public static function resolve( $preset, $url, $index = 1 ) {
		$tags = array();
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			$tags[ $key ] = Sunshine_UTM_URL_Builder::fill_tokens( isset( $preset[ $key ] ) ? $preset[ $key ] : '', $url, $index );
		}
		return $tags;
	}

	/**
	 * One line of plain text: no tags or line breaks, trimmed to the column size. Unlike
	 * sanitize_text_field it leaves %XX alone, since a tag value can legitimately contain one.
	 *
	 * @param string $value Value.
	 * @param int    $max   Longest allowed.
	 * @return string
	 */
	public static function clean( $value, $max ) {
		$value = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/**
	 * The tokens, for help text.
	 *
	 * @return array token => what it becomes
	 */
	public static function tokens() {
		return array(
			/* translators: %s: today's date, like 2026-10-09. */
			'{date}'       => sprintf( __( 'today\'s date: %s', 'sunshine-utm-builder' ), wp_date( 'Y-m-d' ) ),
			/* translators: %s: today's date, like 261009. */
			'{date_short}' => sprintf( __( 'today\'s date, shorter: %s', 'sunshine-utm-builder' ), wp_date( 'ymd' ) ),
			'{slug}'       => __( 'the last part of the link\'s path, like spring-sale', 'sunshine-utm-builder' ),
			'{page_title}' => __( 'the title of the post or page, for links on this site', 'sunshine-utm-builder' ),
			'{index}'      => __( 'the link\'s number in a batch: 1, 2, 3...', 'sunshine-utm-builder' ),
		);
	}
}
