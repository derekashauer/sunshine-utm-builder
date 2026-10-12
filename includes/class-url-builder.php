<?php
/**
 * Builds tagged links: formats the tag values, swaps out any UTM tags already on the link, keeps
 * the rest of the link's own parameters, and puts the tags before any #anchor (tags after a # never
 * reach the server or Google Analytics).
 *
 * assets/js/builder.js has a twin of build(), format_value(), add_params() and normalize_url() for
 * the live builder. They must give the same result for the same input, so change both together.
 *
 * Values are encoded with rawurlencode (spaces become %20, not +). Keys and values are never run
 * through sanitize_text_field, because it deletes %XX sequences and breaks encoded links.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_URL_Builder {

	const KEYS = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );

	/**
	 * The five tags with their labels and help, shared by every form that asks for them.
	 *
	 * @return array utm key => {label, help, example}
	 */
	public static function fields() {
		return array(
			'utm_source'   => array(
				'label'   => __( 'Source', 'sunshine-utm-builder' ),
				'help'    => __( 'Where the click comes from.', 'sunshine-utm-builder' ),
				'example' => 'newsletter, facebook, google',
			),
			'utm_medium'   => array(
				'label'   => __( 'Medium', 'sunshine-utm-builder' ),
				'help'    => __( 'The type of link. Google Analytics uses this to sort traffic into channels.', 'sunshine-utm-builder' ),
				'example' => 'email, social, cpc',
			),
			'utm_campaign' => array(
				'label'   => __( 'Campaign', 'sunshine-utm-builder' ),
				'help'    => __( 'The promotion or project the link is part of.', 'sunshine-utm-builder' ),
				'example' => 'spring_sale, product_launch',
			),
			'utm_term'     => array(
				'label'   => __( 'Term', 'sunshine-utm-builder' ),
				'help'    => __( 'The keyword you paid for. Mostly used for search ads.', 'sunshine-utm-builder' ),
				'example' => 'running_shoes',
			),
			'utm_content'  => array(
				'label'   => __( 'Content', 'sunshine-utm-builder' ),
				'help'    => __( 'Tells apart links that go to the same page, like two buttons in one email.', 'sunshine-utm-builder' ),
				'example' => 'header_button, footer_link',
			),
		);
	}

	/**
	 * Common values offered as you type. Mediums follow the spellings Google Analytics recognizes for
	 * its channels, which is where most reporting mix-ups start.
	 *
	 * @return array utm key => values
	 */
	/**
	 * Placeholder for a tag field: "e.g." and the first example.
	 *
	 * @param array $field One of fields().
	 * @return string
	 */
	public static function placeholder( $field ) {
		/* translators: %s: an example value. */
		return sprintf( __( 'e.g. %s', 'sunshine-utm-builder' ), trim( strtok( $field['example'], ',' ) ) );
	}

	public static function suggestions() {
		return array(
			'utm_source' => array( 'google', 'facebook', 'instagram', 'linkedin', 'x', 'youtube', 'tiktok', 'pinterest', 'reddit', 'bing', 'newsletter' ),
			'utm_medium' => array( 'email', 'social', 'paid_social', 'cpc', 'display', 'affiliate', 'referral', 'sms', 'audio', 'video' ),
		);
	}

	/**
	 * Build a tagged link.
	 *
	 * @param string     $url    Page to link to.
	 * @param array      $tags   utm key => value, as typed.
	 * @param array      $extra  Other parameters, name => value. Added as typed, not formatted, since
	 *                           things like coupon codes and affiliate IDs can be case sensitive.
	 * @param array|null $format Formatting rules. Defaults to Settings.
	 * @return string
	 */
	public static function build( $url, $tags, $extra = array(), $format = null ) {
		if ( null === $format ) {
			$format = sunshine_utm()->format_options();
		}

		$params = array();
		foreach ( self::KEYS as $key ) {
			$value = self::format_value( isset( $tags[ $key ] ) ? $tags[ $key ] : '', $format );
			if ( '' !== $value ) {
				$params[ $key ] = $value;
			}
		}
		foreach ( (array) $extra as $key => $value ) {
			$key   = trim( (string) $key );
			$value = trim( (string) $value );
			// The five main tags come from their own fields. Others, like utm_id, are fine here.
			if ( '' === $key || '' === $value || in_array( strtolower( $key ), self::KEYS, true ) ) {
				continue;
			}
			$params[ $key ] = $value;
		}

		/**
		 * Change the parameters added to a link.
		 *
		 * @param array  $params Name => value, already formatted.
		 * @param string $url    Page being linked to.
		 */
		$params = (array) apply_filters( 'sunshine_utm_link_params', $params, $url );

		return self::add_params( self::normalize_url( $url ), $params );
	}

	/**
	 * Apply the formatting rules to one tag value.
	 *
	 * @param string $value  Value as typed.
	 * @param array  $format lowercase, spaces (_, - or keep), strip_symbols.
	 * @return string
	 */
	public static function format_value( $value, $format ) {
		$value = trim( (string) preg_replace( '/\s+/u', ' ', (string) $value ) );
		if ( ! empty( $format['lowercase'] ) ) {
			$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		}
		if ( ! empty( $format['strip_symbols'] ) ) {
			// Keep letters (any language), numbers, dash, underscore, period and space. Symbols go
			// before spaces are swapped, so "Sale & Clearance" becomes sale_clearance, not sale__clearance.
			$value = (string) preg_replace( '/[^\p{L}\p{N}_.\- ]/u', '', $value );
			$value = trim( (string) preg_replace( '/ {2,}/', ' ', $value ) );
		}
		$spaces = isset( $format['spaces'] ) ? $format['spaces'] : 'keep';
		if ( '_' === $spaces || '-' === $spaces ) {
			$value = str_replace( ' ', $spaces, $value );
		}
		return $value;
	}

	/**
	 * Add parameters to a link. Every utm_ parameter already on the link is removed first (a link
	 * gets one set of tags), and so is any parameter being set again. Other parameters stay where
	 * they were.
	 *
	 * @param string $url    Link.
	 * @param array  $params Name => value.
	 * @return string
	 */
	public static function add_params( $url, $params ) {
		$hash = '';
		$pos  = strpos( $url, '#' );
		if ( false !== $pos ) {
			$hash = substr( $url, $pos );
			$url  = substr( $url, 0, $pos );
		}

		$query = '';
		$pos   = strpos( $url, '?' );
		if ( false !== $pos ) {
			$query = substr( $url, $pos + 1 );
			$url   = substr( $url, 0, $pos );
		}

		$replace = array_map( 'strtolower', array_keys( $params ) );
		$pairs   = array();
		foreach ( explode( '&', $query ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$name = strtolower( rawurldecode( str_replace( '+', ' ', explode( '=', $pair, 2 )[0] ) ) );
			if ( 0 === strpos( $name, 'utm_' ) || in_array( $name, $replace, true ) ) {
				continue;
			}
			$pairs[] = $pair;
		}
		foreach ( $params as $name => $value ) {
			$pairs[] = rawurlencode( (string) $name ) . '=' . rawurlencode( (string) $value );
		}

		return $url . ( $pairs ? '?' . implode( '&', $pairs ) : '' ) . $hash;
	}

	/**
	 * Remove every utm_ parameter from a link.
	 *
	 * @param string $url Link.
	 * @return string
	 */
	public static function strip_utm( $url ) {
		return self::add_params( $url, array() );
	}

	/**
	 * Trim, and add https:// when someone pastes "example.com/page".
	 *
	 * @param string $url Link as typed.
	 * @return string
	 */
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' !== $url && false === strpos( $url, '://' ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}
		return $url;
	}

	/**
	 * A full http(s) address with a host and no spaces.
	 *
	 * @param string $url Link.
	 * @return bool
	 */
	public static function is_valid_url( $url ) {
		// mailto:, tel: and javascript: aren't pages. (A colon before a number is a port, like localhost:8080.)
		if ( preg_match( '/^[a-z][a-z0-9+.-]*:(?![0-9\/])/i', trim( (string) $url ) ) ) {
			return false;
		}
		$url = self::normalize_url( $url );
		if ( '' === $url || preg_match( '/\s/', $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || ! isset( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}
		// A single word ("sale") is almost always a typo, not a site. localhost and IPv6 are fine.
		return false !== strpos( $parts['host'], '.' ) || 'localhost' === strtolower( $parts['host'] ) || 0 === strpos( $parts['host'], '[' );
	}

	/**
	 * "name=value" lines to an array. Lines without = are skipped.
	 *
	 * @param string $text One parameter per line.
	 * @return array
	 */
	public static function parse_extra( $text ) {
		$params = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$parts = explode( '=', $line, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$name = trim( $parts[0] );
			if ( '' !== $name ) {
				$params[ $name ] = trim( $parts[1] );
			}
		}
		return $params;
	}

	/**
	 * Fill in {date}, {date_short}, {index}, {slug} and {page_title}.
	 *
	 * @param string $value Value with tokens.
	 * @param string $url   The link it's for.
	 * @param int    $index Position in the batch, starting at 1.
	 * @return string
	 */
	public static function fill_tokens( $value, $url, $index = 1 ) {
		$value = (string) $value;
		if ( false === strpos( $value, '{' ) ) {
			return $value;
		}
		$path  = trim( (string) wp_parse_url( self::normalize_url( $url ), PHP_URL_PATH ), '/' );
		$parts = '' === $path ? array() : explode( '/', $path );
		$slug  = $parts ? rawurldecode( end( $parts ) ) : '';

		return strtr(
			$value,
			array(
				'{date}'       => wp_date( 'Y-m-d' ),
				'{date_short}' => wp_date( 'ymd' ),
				'{index}'      => (string) $index,
				'{slug}'       => $slug,
				'{page_title}' => false !== strpos( $value, '{page_title}' ) ? self::page_title( $url ) : '',
			)
		);
	}

	/**
	 * Title of the post or page a link points to, when it's on this site.
	 *
	 * @param string $url Link.
	 * @return string
	 */
	protected static function page_title( $url ) {
		static $titles = array();
		if ( ! isset( $titles[ $url ] ) ) {
			$post_id        = url_to_postid( self::strip_utm( self::normalize_url( $url ) ) );
			$titles[ $url ] = $post_id ? wp_strip_all_tags( get_the_title( $post_id ) ) : '';
		}
		return $titles[ $url ];
	}
}
