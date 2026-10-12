<?php
/**
 * Bulk links: turns the Bulk Builder's input into rows (a link and its tags), then builds, checks
 * and shortens them.
 *
 * Four ways in:
 * - urls: a list of links, all getting the same tags.
 * - csv: one row per link. Its own utm columns win over the shared tags, and any other column
 *   becomes an extra parameter (a "ref" column adds ref=... to that row's link).
 * - variations: every combination of the values listed for each tag, for every link.
 * - social: every link once per chosen social network, with that network as the source and
 *   "social" as the medium.
 *
 * Shared tags start from the chosen preset (if any), and anything typed in the tag fields replaces
 * the preset's value. Tokens like {slug} are filled in per link.
 *
 * A batch is capped at MAX_LINKS, checked before any link is built, because a few variation lists
 * multiply fast. Shortening and link checks make one web request per link, so they only run when
 * the batch is created, never for the preview.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_Bulk {

	const MAX_LINKS = 2000;

	/**
	 * Shortening and link checks make one web request each, and a request that runs too long gets
	 * cut off by the server, so they're limited per batch.
	 */
	const SHORTEN_MAX = 50;

	const CHECK_MAX = 50;

	const METHODS = array( 'urls', 'csv', 'variations', 'social' );

	/**
	 * Turn the Bulk Builder's input into rows.
	 *
	 * @param array $input method, urls, csv, variations (utm key => lines), networks, preset_id,
	 *                     tags (utm key => value), extra (name => value).
	 * @return array|WP_Error {rows, skipped} or an error to show.
	 */
	public static function rows( $input ) {
		$method = isset( $input['method'] ) && in_array( $input['method'], self::METHODS, true ) ? $input['method'] : 'urls';
		$tags   = self::shared_tags( $input );
		$extra  = isset( $input['extra'] ) ? (array) $input['extra'] : array();

		$skipped = array();
		$rows    = array();

		if ( 'csv' === $method ) {
			$csv = self::parse_csv( isset( $input['csv'] ) ? $input['csv'] : '' );
			if ( is_wp_error( $csv ) ) {
				return $csv;
			}
			if ( count( $csv ) > self::MAX_LINKS ) {
				return self::too_many( count( $csv ) );
			}
			foreach ( $csv as $line => $row ) {
				if ( ! Sunshine_UTM_URL_Builder::is_valid_url( $row['url'] ) ) {
					$skipped[] = self::skipped_message( $line, $row['url'] );
					continue;
				}
				$row_tags = array_merge( $tags, array_filter( $row['tags'], 'strlen' ) );
				if ( '' === trim( $row_tags['utm_source'] ) ) {
					/* translators: %d: line number. */
					$skipped[] = sprintf( __( 'Line %d skipped, it has no source.', 'sunshine-utm-builder' ), $line );
					continue;
				}
				$rows[] = array(
					'url'   => Sunshine_UTM_URL_Builder::normalize_url( $row['url'] ),
					'tags'  => $row_tags,
					'extra' => array_merge( $extra, array_filter( $row['extra'], 'strlen' ) ),
				);
			}
			return self::result( $rows, $skipped );
		}

		$urls = array();
		foreach ( preg_split( '/\r\n|\r|\n/', isset( $input['urls'] ) ? (string) $input['urls'] : '' ) as $line => $url ) {
			$url = trim( $url );
			if ( '' === $url ) {
				continue;
			}
			if ( ! Sunshine_UTM_URL_Builder::is_valid_url( $url ) ) {
				$skipped[] = self::skipped_message( $line + 1, $url );
				continue;
			}
			$urls[] = Sunshine_UTM_URL_Builder::normalize_url( $url );
		}

		if ( 'variations' === $method ) {
			$lists = array();
			foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
				$values = isset( $input['variations'][ $key ] ) ? self::lines( $input['variations'][ $key ] ) : array();
				if ( $values ) {
					$lists[ $key ] = $values;
				}
			}
			$combos = 1;
			foreach ( $lists as $values ) {
				$combos *= count( $values );
			}
			if ( count( $urls ) * $combos > self::MAX_LINKS ) {
				return self::too_many( count( $urls ) * $combos );
			}
			foreach ( $urls as $url ) {
				foreach ( self::combinations( $lists ) as $combo ) {
					$rows[] = array(
						'url'   => $url,
						'tags'  => array_merge( $tags, $combo ),
						'extra' => $extra,
					);
				}
			}
			return self::result( $rows, $skipped );
		}

		if ( 'social' === $method ) {
			$all      = self::social_networks();
			$networks = array_values( array_intersect( array_keys( $all ), isset( $input['networks'] ) ? (array) $input['networks'] : array() ) );
			if ( ! $networks ) {
				return new WP_Error( 'no_networks', __( 'Choose at least one social network.', 'sunshine-utm-builder' ) );
			}
			if ( count( $urls ) * count( $networks ) > self::MAX_LINKS ) {
				return self::too_many( count( $urls ) * count( $networks ) );
			}
			foreach ( $urls as $url ) {
				foreach ( $networks as $network ) {
					$rows[] = array(
						'url'   => $url,
						'tags'  => array_merge(
							$tags,
							array(
								'utm_source' => $all[ $network ]['source'],
								'utm_medium' => $all[ $network ]['medium'],
							)
						),
						'extra' => $extra,
					);
				}
			}
			return self::result( $rows, $skipped );
		}

		if ( count( $urls ) > self::MAX_LINKS ) {
			return self::too_many( count( $urls ) );
		}
		foreach ( $urls as $url ) {
			$rows[] = array(
				'url'   => $url,
				'tags'  => $tags,
				'extra' => $extra,
			);
		}
		return self::result( $rows, $skipped );
	}

	/**
	 * Build the links.
	 *
	 * @param array $rows    Rows from rows().
	 * @param array $options shorten (false, or site, bitly or rebrandly; true means the default),
	 *                       check (bool, defaults to the setting).
	 * @return array Links, ready for Sunshine_UTM_Batches::create().
	 */
	public static function generate( $rows, $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'shorten' => false,
				'check'   => (bool) sunshine_utm()->get_setting( 'check_urls' ),
			)
		);
		$format  = sunshine_utm()->format_options();
		$checked = array();
		$links   = array();

		foreach ( array_values( $rows ) as $i => $row ) {
			$tags = array();
			foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
				$tags[ $key ] = Sunshine_UTM_URL_Builder::fill_tokens( isset( $row['tags'][ $key ] ) ? $row['tags'][ $key ] : '', $row['url'], $i + 1 );
			}
			$extra = array();
			foreach ( $row['extra'] as $name => $value ) {
				$extra[ $name ] = Sunshine_UTM_URL_Builder::fill_tokens( $value, $row['url'], $i + 1 );
			}

			$link = array(
				'url'          => $row['url'],
				'tagged_url'   => Sunshine_UTM_URL_Builder::build( $row['url'], $tags, $extra, $format ),
				'short_url'    => '',
				'extra_params' => array_filter( $extra, 'strlen' ),
				'url_ok'       => true,
				'url_status'   => '',
			);
			foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
				$link[ $key ] = Sunshine_UTM_URL_Builder::format_value( $tags[ $key ], $format );
			}

			if ( $options['check'] ) {
				if ( ! isset( $checked[ $row['url'] ] ) ) {
					$checked[ $row['url'] ] = Sunshine_UTM_URL_Checker::check( $row['url'] );
				}
				$link['url_ok']     = $checked[ $row['url'] ]['ok'];
				$link['url_status'] = $checked[ $row['url'] ]['message'];
			}

			if ( $options['shorten'] ) {
				$short = Sunshine_UTM_Shortener::shorten( $link['tagged_url'], is_string( $options['shorten'] ) ? $options['shorten'] : '' );
				if ( ! is_wp_error( $short ) ) {
					$link['short_url'] = $short;
				}
			}

			$links[] = $link;
		}

		return $links;
	}

	/**
	 * The networks offered for social batches.
	 *
	 * @return array id => {name, source, medium}
	 */
	public static function social_networks() {
		$networks = array(
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'x'         => 'X (Twitter)',
			'linkedin'  => 'LinkedIn',
			'threads'   => 'Threads',
			'bluesky'   => 'Bluesky',
			'pinterest' => 'Pinterest',
			'youtube'   => 'YouTube',
			'tiktok'    => 'TikTok',
			'reddit'    => 'Reddit',
		);
		$list     = array();
		foreach ( $networks as $id => $name ) {
			$list[ $id ] = array(
				'name'   => $name,
				'source' => $id,
				'medium' => 'social',
			);
		}

		/**
		 * Add or change social networks. Each needs a name, source and medium.
		 *
		 * @param array $list id => {name, source, medium}
		 */
		return (array) apply_filters( 'sunshine_utm_social_networks', $list );
	}

	/**
	 * Read CSV text. The first row names the columns: url (required), source/utm_source and the
	 * other tags, and any other column becomes an extra parameter. Commas, semicolons and tabs all
	 * work as separators.
	 *
	 * @param string $text CSV.
	 * @return array|WP_Error Rows keyed by line number: {url, tags, extra}.
	 */
	public static function parse_csv( $text ) {
		$text = trim( (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text ) );
		if ( '' === $text ) {
			return new WP_Error( 'empty', __( 'Paste your CSV or choose a CSV file.', 'sunshine-utm-builder' ) );
		}

		$first     = strtok( $text, "\n" );
		$delimiter = ',';
		foreach ( array( ';', "\t" ) as $other ) {
			if ( substr_count( $first, $other ) > substr_count( $first, $delimiter ) ) {
				$delimiter = $other;
			}
		}

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream.
		fwrite( $handle, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $handle );

		$columns = array();
		foreach ( (array) fgetcsv( $handle, 0, $delimiter, '"', '' ) as $column ) {
			$column = strtolower( trim( (string) $column ) );
			if ( in_array( 'utm_' . $column, Sunshine_UTM_URL_Builder::KEYS, true ) ) {
				$column = 'utm_' . $column;
			} elseif ( in_array( $column, array( 'link', 'destination', 'website url' ), true ) ) {
				$column = 'url';
			}
			$columns[] = $column;
		}
		if ( ! in_array( 'url', $columns, true ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'no_url_column', __( 'The first row of your CSV needs a column named url.', 'sunshine-utm-builder' ) );
		}

		$rows = array();
		$line = 1;
		while ( false !== ( $cells = fgetcsv( $handle, 0, $delimiter, '"', '' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			++$line;
			if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
				continue;
			}
			$row = array(
				'url'   => '',
				'tags'  => array(),
				'extra' => array(),
			);
			foreach ( $columns as $i => $column ) {
				$value = isset( $cells[ $i ] ) ? trim( (string) $cells[ $i ] ) : '';
				if ( 'url' === $column ) {
					$row['url'] = $value;
				} elseif ( in_array( $column, Sunshine_UTM_URL_Builder::KEYS, true ) ) {
					$row['tags'][ $column ] = $value;
				} elseif ( '' !== $column ) {
					$row['extra'][ $column ] = $value;
				}
			}
			$rows[ $line ] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $rows;
	}

	/**
	 * Preset values, then anything typed in the tag fields on top.
	 *
	 * @param array $input Bulk input.
	 * @return array utm key => value
	 */
	protected static function shared_tags( $input ) {
		$tags   = array_fill_keys( Sunshine_UTM_URL_Builder::KEYS, '' );
		$preset = ! empty( $input['preset_id'] ) ? Sunshine_UTM_Presets::get( (int) $input['preset_id'] ) : null;
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			if ( $preset ) {
				$tags[ $key ] = (string) $preset[ $key ];
			}
			if ( isset( $input['tags'][ $key ] ) && '' !== trim( (string) $input['tags'][ $key ] ) ) {
				$tags[ $key ] = (string) $input['tags'][ $key ];
			}
		}
		return $tags;
	}

	/**
	 * Every combination of the lists, like a times table.
	 *
	 * @param array $lists utm key => values.
	 * @return array Each one utm key => value.
	 */
	protected static function combinations( $lists ) {
		$combos = array( array() );
		foreach ( $lists as $key => $values ) {
			$next = array();
			foreach ( $combos as $combo ) {
				foreach ( $values as $value ) {
					$next[] = array_merge( $combo, array( $key => $value ) );
				}
			}
			$combos = $next;
		}
		return $combos;
	}

	protected static function lines( $text ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ), 'strlen' ) );
	}

	protected static function result( $rows, $skipped ) {
		// Google Analytics ignores a link's other tags when it has no source. Checked as the link will
		// have it, after tokens and formatting, since {slug} can be empty and "!!!" can be stripped away.
		$format = sunshine_utm()->format_options();
		foreach ( array_values( $rows ) as $i => $row ) {
			$source = Sunshine_UTM_URL_Builder::fill_tokens( (string) $row['tags']['utm_source'], $row['url'], $i + 1 );
			if ( '' === Sunshine_UTM_URL_Builder::format_value( $source, $format ) ) {
				return new WP_Error( 'no_source', __( 'At least one link would have no source. Check the source under Tags, since Google Analytics needs one to report the link.', 'sunshine-utm-builder' ) );
			}
		}
		// The team's lists from Settings, when "Only allow these" is on. Rows outside them are skipped
		// with the reason, so one typo doesn't stop a whole batch.
		$plugin = sunshine_utm();
		foreach ( $rows as $i => $row ) {
			foreach ( array( 'utm_source', 'utm_medium' ) as $key ) {
				$value   = Sunshine_UTM_URL_Builder::format_value( Sunshine_UTM_URL_Builder::fill_tokens( isset( $row['tags'][ $key ] ) ? (string) $row['tags'][ $key ] : '', $row['url'], $i + 1 ), $format );
				$problem = $plugin->tag_problem( $key, $value );
				if ( '' !== $problem ) {
					/* translators: 1: page, 2: why it was skipped. */
					$skipped[] = sprintf( __( 'Link to %1$s skipped. %2$s', 'sunshine-utm-builder' ), wp_html_excerpt( $row['url'], 60, '...' ), $problem );
					unset( $rows[ $i ] );
					break;
				}
			}
		}
		$rows = array_values( $rows );
		if ( ! $rows ) {
			return new WP_Error(
				'no_links',
				$skipped ? implode( ' ', array_slice( $skipped, 0, 3 ) ) : __( 'Add at least one link.', 'sunshine-utm-builder' )
			);
		}
		return array(
			'rows'    => $rows,
			'skipped' => $skipped,
		);
	}

	protected static function too_many( $count ) {
		return new WP_Error(
			'too_many',
			sprintf(
				/* translators: 1: number of links, 2: the most allowed. */
				__( 'That would make %1$s links. A batch can have up to %2$s, so split it into smaller batches.', 'sunshine-utm-builder' ),
				number_format_i18n( $count ),
				number_format_i18n( self::MAX_LINKS )
			)
		);
	}

	protected static function skipped_message( $line, $url ) {
		if ( '' === trim( (string) $url ) ) {
			/* translators: %d: line number. */
			return sprintf( __( 'Line %d skipped, it has no link.', 'sunshine-utm-builder' ), $line );
		}
		/* translators: 1: line number, 2: what was on that line. */
		return sprintf( __( 'Line %1$d skipped, "%2$s" isn\'t a web address.', 'sunshine-utm-builder' ), $line, wp_html_excerpt( $url, 60, '...' ) );
	}
}
