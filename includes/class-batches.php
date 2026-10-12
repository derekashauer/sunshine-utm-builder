<?php
/**
 * Saved batches and their links. A batch's links don't change after it's made. Only the batch's
 * notes and each link's note can be edited.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_Batches {

	/**
	 * Links inserted per query when saving a batch.
	 */
	const INSERT_CHUNK = 100;

	/**
	 * A page of batches, newest first.
	 *
	 * @param array $args search, page, per_page.
	 * @return array {items, total}
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = Sunshine_UTM_Database::tables()['batches'];
		$args  = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'page'     => 1,
				'per_page' => 20,
			)
		);

		$search   = (string) $args['search'];
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name comes from Sunshine_UTM_Database::tables().
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE ( %s = '' OR name LIKE %s OR notes LIKE %s ) ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $search, $like, $like, $per_page, $offset ), ARRAY_A );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ( %s = '' OR name LIKE %s OR notes LIKE %s )", $search, $like, $like ) );
		// phpcs:enable

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * @param int $id Batch ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Sunshine_UTM_Database::tables()['batches'];
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	/**
	 * Save a batch and its links.
	 *
	 * @param array $batch name, method, notes.
	 * @param array $links Links from Sunshine_UTM_Bulk::generate().
	 * @return int Batch ID, or 0 if it couldn't be saved.
	 */
	public static function create( $batch, $links ) {
		global $wpdb;
		$tables = Sunshine_UTM_Database::tables();

		$name = Sunshine_UTM_Presets::clean( isset( $batch['name'] ) ? $batch['name'] : '', 191 );
		if ( '' === $name ) {
			/* translators: %s: date and time. */
			$name = sprintf( __( 'Links from %s', 'sunshine-utm-builder' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
		}

		$saved = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$tables['batches'],
			array(
				'name'       => $name,
				'method'     => sanitize_key( isset( $batch['method'] ) ? $batch['method'] : '' ),
				'notes'      => isset( $batch['notes'] ) ? sanitize_textarea_field( $batch['notes'] ) : '',
				'link_count' => count( $links ),
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		if ( ! $saved ) {
			return 0;
		}
		$batch_id = (int) $wpdb->insert_id;

		$columns = array( 'batch_id', 'url', 'tagged_url', 'short_url', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'extra_params', 'url_ok', 'url_status' );
		foreach ( array_chunk( $links, self::INSERT_CHUNK ) as $chunk ) {
			$rows   = array();
			$values = array();
			foreach ( $chunk as $link ) {
				$rows[] = '(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s)';
				array_push(
					$values,
					$batch_id,
					$link['url'],
					$link['tagged_url'],
					$link['short_url'],
					$link['utm_source'],
					$link['utm_medium'],
					$link['utm_campaign'],
					$link['utm_term'],
					$link['utm_content'],
					$link['extra_params'] ? wp_json_encode( $link['extra_params'] ) : '',
					$link['url_ok'] ? 1 : 0,
					Sunshine_UTM_Presets::clean( $link['url_status'], 191 )
				);
			}
			$sql = "INSERT INTO {$tables['links']} (" . implode( ', ', $columns ) . ') VALUES ' . implode( ', ', $rows );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- placeholders built above.
			if ( false === $wpdb->query( $wpdb->prepare( $sql, $values ) ) ) {
				self::delete( $batch_id );
				return 0;
			}
		}

		return $batch_id;
	}

	public static function update_notes( $id, $notes ) {
		global $wpdb;
		return false !== $wpdb->update( Sunshine_UTM_Database::tables()['batches'], array( 'notes' => sanitize_textarea_field( $notes ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function delete( $id ) {
		global $wpdb;
		$tables = Sunshine_UTM_Database::tables();
		$wpdb->delete( $tables['links'], array( 'batch_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $tables['batches'], array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Every link in a batch, in the order they were made.
	 *
	 * @param int $batch_id Batch ID.
	 * @return array
	 */
	public static function links( $batch_id ) {
		global $wpdb;
		$table = Sunshine_UTM_Database::tables()['links'];
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE batch_id = %d ORDER BY id ASC", $batch_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as &$row ) {
			$extra               = $row['extra_params'] ? json_decode( $row['extra_params'], true ) : array();
			$row['extra_params'] = is_array( $extra ) ? $extra : array();
		}
		return $rows;
	}

	/**
	 * Change one link's note.
	 *
	 * @param int    $link_id Link ID.
	 * @param string $note    Note.
	 * @return bool
	 */
	public static function update_link_note( $link_id, $note ) {
		global $wpdb;
		return false !== $wpdb->update( Sunshine_UTM_Database::tables()['links'], array( 'notes' => sanitize_textarea_field( $note ) ), array( 'id' => $link_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * The link to share: the short one when there is one.
	 *
	 * @param array $link Link row.
	 * @return string
	 */
	public static function share_url( $link ) {
		return ! empty( $link['short_url'] ) ? $link['short_url'] : $link['tagged_url'];
	}

	/**
	 * Batch rows as a CSV file, with a byte order mark so Excel reads the characters right.
	 *
	 * @param array $links Link rows.
	 * @return string
	 */
	public static function to_csv( $links ) {
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream.
		fputcsv( $handle, array( 'url', 'tagged_url', 'short_url', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'other_parameters', 'notes', 'link_check' ), ',', '"', '' );
		foreach ( $links as $link ) {
			$extra = array();
			foreach ( (array) $link['extra_params'] as $name => $value ) {
				$extra[] = $name . '=' . $value;
			}
			fputcsv(
				$handle,
				array_map(
					array( __CLASS__, 'csv_cell' ),
					array(
						$link['url'],
						$link['tagged_url'],
						$link['short_url'],
						$link['utm_source'],
						$link['utm_medium'],
						$link['utm_campaign'],
						$link['utm_term'],
						$link['utm_content'],
						implode( '&', $extra ),
						(string) $link['notes'],
						$link['url_ok'] ? '' : $link['url_status'],
					)
				),
				',',
				'"',
				''
			);
		}
		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * Stop spreadsheet apps from running a cell as a formula (CSV injection), by putting a quote
	 * in front of any value that starts with =, +, -, @ or a tab.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		return ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) ? "'" . $value : $value;
	}
}
