<?php
/**
 * Short links on the site's own domain: yourdomain.com/x8d89c, or yourdomain.com/g/x8d89c with a
 * prefix, sending visitors on to the full tagged link. Chosen as "Your site" under Settings → Link
 * shortening, next to Bitly and Rebrandly, so everything that shortens can use it.
 *
 * How a visit is answered:
 * - With a prefix, /g/x8d89c is recognized on init, before WordPress looks for a page, so the
 *   redirect costs one small database lookup and nothing else.
 * - Without a prefix, the code is only looked up once WordPress has found no page at that address
 *   (template_redirect on a 404, before redirect_canonical guesses at a similar post). Normal page
 *   views never touch the short links table, and a real page always wins over a code.
 * - The 404 check also looks at the last part of any missing address, so links shared under an
 *   old prefix keep working after the prefix changes.
 * - The database is only asked when the address could be a code (could_be_code()): the exact
 *   shape of a made-up code (6 characters from the code alphabet), or one of the custom endings
 *   people chose. Custom endings are kept in a small option WordPress loads with every page
 *   anyway, so checking them costs nothing. Bots probing /admin or /wp-config.php on a 404, and
 *   new post slugs like "about-us", never cause a lookup.
 *
 * A short link's ending and where it goes can be changed on its page in the Short Links tab. A
 * custom ending (yoursite.com/spring-sale) can't be one of WordPress's own addresses or an
 * existing page's. Changing the ending stops the old one working, since only one is kept.
 *
 * Each short link stores its five UTM tags in their own columns, read from the link when it's
 * saved, so the Short Links tab can total clicks by campaign and Conversion Bridge's
 * conversions can be matched to a link's tags without reading every link.
 *
 * Made-up codes are 6 lowercase letters and numbers, leaving out ones that are easy to mix up (l, 1, o, 0,
 * i). The same long link always gets the same code. Short links are never deleted with a batch,
 * since they may already be posted somewhere. They can be deleted one at a time on the Short Links
 * tab. That stops the link working, removes its clicks, and clears it from any saved batch so
 * nobody copies a dead link.
 *
 * Redirects are 302s sent with no-cache headers, not 301s. A browser keeps a 301 and goes straight
 * to the full link the next time, so the site never sees the second click and can't count it.
 *
 * Each redirect adds one to the link's total and to its count for the day (UTC). Only the count is
 * stored, nothing about the visitor, so there's nothing to ask consent for. Bots aren't counted:
 * Facebook, X, Slack and the rest fetch a link the moment it's posted to build the preview, and
 * search engines and uptime checkers follow links too. Those are told apart by user agent, and HEAD
 * requests (which only check a link) aren't counted either.
 *
 * Anything added to the short link's address (fbclid from Facebook, gclid from Google Ads
 * auto-tagging) is passed on to the full link, so ad click tracking keeps working. utm_ parameters
 * aren't passed on, since the full link already has its own.
 *
 * New posts and pages never get a slug that a short link already uses (wp_unique_post_slug), so
 * nobody can take over a short link's address by publishing a page there.
 *
 * Needs pretty permalinks without index.php in them. Otherwise the server never hands /x8d89c
 * to WordPress.
 *
 * Hooks used:
 * - init (priority 1): the prefixed route.
 * - template_redirect (priority 1): codes on a 404.
 * - wp_unique_post_slug: keep new slugs off live codes.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_Short_Links {

	const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

	const LENGTH = 6;

	/**
	 * Prefixes that would clash with WordPress's own addresses.
	 */
	const RESERVED = array( 'wp', 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'page', 'comments', 'search', 'author', 'category', 'tag', 'embed', 'trackback', 'attachment', 'type' );

	/**
	 * Option holding the custom endings (ending => 1). Autoloaded, so checking it costs no query.
	 */
	const CUSTOM_OPTION = 'sunshine_utm_custom_codes';

	public function __construct() {
		add_action( 'init', array( $this, 'prefixed_route' ), 1 );
		add_action( 'template_redirect', array( $this, 'not_found_route' ), 1 );
		add_filter( 'wp_unique_post_slug', array( $this, 'avoid_codes' ), 10, 6 );
	}

	/**
	 * A new post or page slug that matches a live code gets "-2" added, like any taken slug.
	 *
	 * @param string $slug          Slug WordPress chose.
	 * @param int    $post_id       Post ID.
	 * @param string $post_status   Status.
	 * @param string $post_type     Post type.
	 * @param int    $post_parent   Parent post ID.
	 * @param string $original_slug Slug before WordPress made it unique.
	 * @return string
	 */
	public function avoid_codes( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
		if ( $post_parent || ! self::could_be_code( $slug ) || '' === self::find( $slug ) ) {
			return $slug;
		}
		return $slug . '-2';
	}

	/**
	 * Short links work on this site's address setup.
	 *
	 * @return bool
	 */
	public static function available() {
		$structure = (string) get_option( 'permalink_structure' );
		return '' !== $structure && ! preg_match( '#^/?index\.php#', $structure );
	}

	/**
	 * The prefix from Settings, cleaned: lowercase letters, numbers and dashes, or ''.
	 *
	 * @return string
	 */
	public static function prefix() {
		return self::clean_prefix( (string) sunshine_utm()->get_setting( 'short_prefix' ) );
	}

	/**
	 * @param string $prefix Prefix as typed.
	 * @return string Cleaned prefix, or '' if it's empty or would clash with WordPress.
	 */
	public static function clean_prefix( $prefix ) {
		$prefix = trim( strtolower( (string) preg_replace( '/[^A-Za-z0-9-]/', '', (string) $prefix ) ), '-' );
		$prefix = substr( $prefix, 0, 20 );
		if ( in_array( $prefix, self::RESERVED, true ) || 0 === strpos( $prefix, 'wp-' ) ) {
			return '';
		}
		return $prefix;
	}

	/**
	 * The short address for a code.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function url( $code ) {
		$prefix = self::prefix();
		return home_url( '/' . ( '' === $prefix ? '' : $prefix . '/' ) . $code );
	}

	/**
	 * Short link for a long one, made the first time and reused after that.
	 *
	 * @param string $url Long link.
	 * @return string|WP_Error Short link.
	 */
	public static function shorten( $url ) {
		global $wpdb;
		$table = Sunshine_UTM_Database::tables()['short_links'];
		$url   = trim( (string) $url );
		if ( ! Sunshine_UTM_URL_Builder::is_valid_url( $url ) ) {
			return new WP_Error( 'bad_url', __( 'That link isn\'t a full web address.', 'sunshine-utm-builder' ) );
		}
		if ( ! self::available() ) {
			return new WP_Error( 'permalinks', __( 'Short links on your site need pretty permalinks. Choose any setting other than Plain, without index.php, under Settings → Permalinks.', 'sunshine-utm-builder' ) );
		}

		$hash = sha1( $url );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE url_hash = %s AND url = %s LIMIT 1", $hash, $url ) );
		if ( $code ) {
			return self::url( $code );
		}

		for ( $try = 0; $try < 10; $try++ ) {
			$code = self::new_code();
			if ( self::find( $code ) || self::is_page( $code ) ) {
				continue;
			}
			$saved = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array_merge(
					array(
						'code'       => $code,
						'url'        => $url,
						'url_hash'   => $hash,
						'created_at' => current_time( 'mysql', true ),
					),
					self::tags_from_url( $url )
				)
			);
			if ( $saved ) {
				return self::url( $code );
			}
		}
		return new WP_Error( 'save', __( 'The short link could not be saved.', 'sunshine-utm-builder' ) );
	}

	/**
	 * The long link for a code, or '' when there isn't one.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function find( $code ) {
		global $wpdb;
		$table = Sunshine_UTM_Database::tables()['short_links'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT url FROM {$table} WHERE code = %s LIMIT 1", strtolower( (string) $code ) ) );
	}

	public function prefixed_route() {
		$prefix = self::prefix();
		if ( '' === $prefix || is_admin() || ! self::available() ) {
			return;
		}
		$path = self::request_path();
		if ( 0 !== strpos( $path, $prefix . '/' ) ) {
			return;
		}
		$code = substr( $path, strlen( $prefix ) + 1 );
		if ( self::could_be_code( $code ) ) {
			self::redirect( $code );
		}
	}

	public function not_found_route() {
		if ( ! is_404() ) {
			return;
		}
		$parts = explode( '/', self::request_path() );
		$code  = end( $parts );
		// Only a bare code (no prefix), the current prefix, or an older one: at most one folder deep.
		if ( count( $parts ) <= 2 && self::could_be_code( $code ) ) {
			self::redirect( $code );
		}
	}

	/**
	 * The text has the exact shape of a code: LENGTH characters from ALPHABET, in any case (people
	 * typing a link by hand use capitals). Checked before any database lookup.
	 *
	 * @param string $text Last part of an address, or a slug.
	 * @return bool
	 */
	public static function is_code( $text ) {
		return (bool) preg_match( '/^[' . self::ALPHABET . ']{' . self::LENGTH . '}$/i', (string) $text );
	}

	/**
	 * Worth a database lookup: shaped like a made-up code, or one of the custom endings.
	 *
	 * @param string $text Last part of an address, or a slug.
	 * @return bool
	 */
	public static function could_be_code( $text ) {
		return self::is_code( $text ) || isset( self::custom_codes()[ strtolower( (string) $text ) ] );
	}

	/**
	 * @return array Custom endings, ending => 1.
	 */
	public static function custom_codes() {
		$codes = get_option( self::CUSTOM_OPTION, array() );
		return is_array( $codes ) ? $codes : array();
	}

	/**
	 * A custom ending cleaned up: lowercase letters, numbers and dashes, at most 50 characters.
	 *
	 * @param string $text Ending as typed.
	 * @return string
	 */
	public static function clean_code( $text ) {
		$text = strtolower( trim( (string) $text ) );
		$text = (string) preg_replace( '/[\s_]+/', '-', $text );
		$text = (string) preg_replace( '/[^a-z0-9-]/', '', $text );
		$text = (string) preg_replace( '/-{2,}/', '-', $text );
		return trim( substr( $text, 0, 50 ), '-' );
	}

	/**
	 * The five UTM tags in a link, for the short link's own columns. Missing ones are ''.
	 *
	 * @param string $url Link.
	 * @return array utm key => value
	 */
	public static function tags_from_url( $url ) {
		$tags  = array_fill_keys( Sunshine_UTM_URL_Builder::KEYS, '' );
		$query = (string) wp_parse_url( (string) $url, PHP_URL_QUERY );
		foreach ( explode( '&', $query ) as $pair ) {
			$parts = explode( '=', $pair, 2 );
			$key   = strtolower( rawurldecode( str_replace( '+', ' ', $parts[0] ) ) );
			if ( isset( $tags[ $key ] ) && '' === $tags[ $key ] && isset( $parts[1] ) ) {
				$tags[ $key ] = substr( rawurldecode( str_replace( '+', ' ', $parts[1] ) ), 0, 255 );
			}
		}
		return $tags;
	}

	/**
	 * One short link with its totals, or null.
	 *
	 * @param string $code Code.
	 * @return array|null id, code, url, the five tags, clicks, recent (last 30 days), last_click_at, created_at.
	 */
	public static function get( $code ) {
		global $wpdb;
		$tables = Sunshine_UTM_Database::tables();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table names come from Sunshine_UTM_Database::tables().
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, (SELECT COALESCE(SUM(c.clicks), 0) FROM {$tables['clicks']} c WHERE c.short_link_id = s.id AND c.day >= %s) AS recent
				FROM {$tables['short_links']} s WHERE s.code = %s LIMIT 1",
				gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ),
				strtolower( (string) $code )
			),
			ARRAY_A
		);
		// phpcs:enable
		return $row ? $row : null;
	}

	/**
	 * Change a short link's ending, where it goes, or both.
	 *
	 * @param string $code Current code.
	 * @param string $new_code New ending, as typed ('' or the same to keep it).
	 * @param string $url New full link ('' or the same to keep it).
	 * @return string|WP_Error The code it has now.
	 */
	public static function update( $code, $new_code, $url ) {
		global $wpdb;
		$tables = Sunshine_UTM_Database::tables();
		$row    = self::get( $code );
		if ( ! $row ) {
			return new WP_Error( 'missing', __( 'That short link no longer exists.', 'sunshine-utm-builder' ) );
		}
		$data = array();

		$url = trim( (string) $url );
		if ( '' !== $url && $url !== $row['url'] ) {
			if ( ! Sunshine_UTM_URL_Builder::is_valid_url( $url ) ) {
				return new WP_Error( 'bad_url', __( 'That link isn\'t a full web address.', 'sunshine-utm-builder' ) );
			}
			$url  = Sunshine_UTM_URL_Builder::normalize_url( $url );
			$data = array_merge(
				array(
					'url'      => $url,
					'url_hash' => sha1( $url ),
				),
				self::tags_from_url( $url )
			);
		}

		$new_code = self::clean_code( $new_code );
		if ( '' !== $new_code && $new_code !== $row['code'] ) {
			$error = self::code_problem( $new_code );
			if ( $error ) {
				return $error;
			}
			$data['code'] = $new_code;
		}

		if ( ! $data ) {
			return $row['code'];
		}
		if ( false === $wpdb->update( $tables['short_links'], $data, array( 'id' => (int) $row['id'] ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new WP_Error( 'save', __( 'The short link could not be saved.', 'sunshine-utm-builder' ) );
		}

		if ( isset( $data['code'] ) ) {
			$custom = self::custom_codes();
			unset( $custom[ $row['code'] ] );
			if ( ! self::is_code( $new_code ) ) {
				$custom[ $new_code ] = 1;
			}
			update_option( self::CUSTOM_OPTION, $custom, true );
			self::replace_in_batches( $row['code'], self::url( $new_code ) );
		}
		return isset( $data['code'] ) ? $data['code'] : $row['code'];
	}

	/**
	 * Why an ending can't be used, or null when it can.
	 *
	 * @param string $code Cleaned ending.
	 * @return WP_Error|null
	 */
	protected static function code_problem( $code ) {
		if ( strlen( $code ) < 3 ) {
			return new WP_Error( 'short', __( 'Use at least 3 letters or numbers for the ending.', 'sunshine-utm-builder' ) );
		}
		if ( in_array( $code, self::RESERVED, true ) || 0 === strpos( $code, 'wp-' ) || $code === self::prefix() ) {
			return new WP_Error( 'reserved', __( 'WordPress uses that address for itself. Choose another ending.', 'sunshine-utm-builder' ) );
		}
		if ( '' !== self::find( $code ) ) {
			return new WP_Error( 'taken', __( 'Another short link already uses that ending.', 'sunshine-utm-builder' ) );
		}
		if ( null !== get_page_by_path( $code, OBJECT, get_post_types( array( 'public' => true ) ) ) ) {
			return new WP_Error( 'page', __( 'A page on your site already uses that address, and a page always wins over a short link. Choose another ending.', 'sunshine-utm-builder' ) );
		}
		return null;
	}

	/**
	 * Point saved batches that had a short link at its new address, or clear it ('' as $new_url).
	 * Batches keep the short link as it was made, with or without a prefix: home/code or home/prefix/code.
	 *
	 * @param string $code    Old code.
	 * @param string $new_url New short link, or ''.
	 */
	protected static function replace_in_batches( $code, $new_url ) {
		global $wpdb;
		$links = Sunshine_UTM_Database::tables()['links'];
		$home  = untrailingslashit( home_url() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name comes from Sunshine_UTM_Database::tables().
		$wpdb->query( $wpdb->prepare( "UPDATE {$links} SET short_url = %s WHERE short_url = %s OR short_url LIKE %s", $new_url, $home . '/' . $code, $wpdb->esc_like( $home . '/' ) . '%' . $wpdb->esc_like( '/' . $code ) ) );
	}

	/**
	 * Clicks per day for one short link, oldest first, with a 0 for days without clicks.
	 *
	 * @param int $id   Short link ID.
	 * @param int $days How many days, ending today (UTC).
	 * @return int[] Y-m-d => clicks
	 */
	public static function daily( $id, $days = 90 ) {
		global $wpdb;
		$out = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$out[ gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) ] = 0;
		}
		$table = Sunshine_UTM_Database::tables()['clicks'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name comes from Sunshine_UTM_Database::tables().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT day, clicks FROM {$table} WHERE short_link_id = %d AND day >= %s", $id, array_key_first( $out ) ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['day'] ] ) ) {
				$out[ $row['day'] ] = (int) $row['clicks'];
			}
		}
		return $out;
	}

	/**
	 * Clicks totalled by campaign, most clicked first.
	 *
	 * @param int $limit How many campaigns.
	 * @return array[] campaign, links, clicks, recent (last 30 days)
	 */
	public static function campaigns( $limit = 10 ) {
		global $wpdb;
		$tables = Sunshine_UTM_Database::tables();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table names come from Sunshine_UTM_Database::tables().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.utm_campaign AS campaign, COUNT(*) AS links, SUM(s.clicks) AS clicks,
					COALESCE(SUM(r.recent), 0) AS recent
				FROM {$tables['short_links']} s
				LEFT JOIN (SELECT short_link_id, SUM(clicks) AS recent FROM {$tables['clicks']} WHERE day >= %s GROUP BY short_link_id) r ON r.short_link_id = s.id
				GROUP BY s.utm_campaign ORDER BY clicks DESC, links DESC LIMIT %d",
				gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ),
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable
		return (array) $rows;
	}

	/**
	 * Send the visitor on, if the code exists. Otherwise nothing happens and WordPress carries on.
	 *
	 * @param string $code Code.
	 */
	protected static function redirect( $code ) {
		global $wpdb;
		$table = Sunshine_UTM_Database::tables()['short_links'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, url FROM {$table} WHERE code = %s LIMIT 1", strtolower( (string) $code ) ), ARRAY_A );
		if ( ! $row ) {
			return;
		}
		$url = $row['url'];
		if ( self::counts() ) {
			self::record_click( (int) $row['id'] );
		}
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed on encoded, as received.
		$url   = self::pass_on( $url, $query );
		// wp_redirect() deletes characters it doesn't expect, like | and {. Encoding them first keeps
		// a partner's link (?ids=1|2) exactly as it was saved.
		$url = (string) preg_replace_callback(
			'/[^a-z0-9\-~+_.?#=&;,\/:%!*\[\]()@]/iu',
			function ( $found ) {
				return rawurlencode( $found[0] );
			},
			$url
		);
		// Stored links can go to other sites on purpose (shortening a link to a partner's page), so
		// this is wp_redirect, not wp_safe_redirect. Only admins can make short links, and every
		// stored link was checked to be an http(s) address.
		nocache_headers();
		wp_redirect( $url, 302, 'Sunshine UTM Builder' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * This request is a person following the link: a GET from something that isn't a known bot or
	 * link preview fetcher.
	 *
	 * @return bool
	 */
	protected static function counts() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		$agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched against a pattern.
		return 'GET' === $method && ! self::is_bot( $agent );
	}

	/**
	 * Bots, crawlers, link preview fetchers and command line tools, by user agent. No user agent at
	 * all counts as a bot too, since every browser sends one.
	 *
	 * @param string $agent User agent.
	 * @return bool
	 */
	public static function is_bot( $agent ) {
		return '' === trim( $agent ) || (bool) preg_match( '/bot\b|bot\/|crawl|spider|slurp|facebookexternalhit|facebookcatalog|meta-externalagent|twitterbot|linkedinbot|slackbot|discordbot|telegrambot|whatsapp|skypeuripreview|redditbot|embedly|iframely|preview|vkshare|bitlybot|google-inspectiontool|googleother|feedfetcher|lighthouse|headless|curl\/|wget\/|python|go-http-client|okhttp|axios|node-fetch|java\/|libwww|httpclient|monitor|uptime|pingdom|statuscake|site24x7/i', $agent );
	}

	/**
	 * Add one to the link's total and to today's count.
	 *
	 * @param int $id Short link ID.
	 */
	protected static function record_click( $id ) {
		global $wpdb;
		$tables = Sunshine_UTM_Database::tables();
		$now    = current_time( 'mysql', true );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$tables['short_links']} SET clicks = clicks + 1, last_click_at = %s WHERE id = %d", $now, $id ) );
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$tables['clicks']} (short_link_id, day, clicks) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE clicks = clicks + 1", $id, substr( $now, 0, 10 ) ) );
		// phpcs:enable
	}

	/**
	 * Short links with their clicks, newest first, for the Short links tab.
	 *
	 * @param array $args search (in the full link or the code), campaign (null for any), page, per_page.
	 * @return array items (code, url, the five tags, clicks, recent = last 30 days, last_click_at,
	 *               created_at) and total.
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args     = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'campaign' => null,
				'page'     => 1,
				'per_page' => 20,
			)
		);
		$filter   = null === $args['campaign'] ? 0 : 1;
		$campaign = (string) $args['campaign'];
		$tables   = Sunshine_UTM_Database::tables();
		$search   = (string) $args['search'];
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$since    = gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS );
		$limit    = max( 1, (int) $args['per_page'] );
		$skip     = ( max( 1, (int) $args['page'] ) - 1 ) * $limit;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table names come from Sunshine_UTM_Database::tables().
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tables['short_links']} s WHERE ( %s = '' OR s.url LIKE %s OR s.code LIKE %s ) AND ( %d = 0 OR s.utm_campaign = %s )",
				$search,
				$like,
				$like,
				$filter,
				$campaign
			)
		);
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.code, s.url, s.utm_source, s.utm_medium, s.utm_campaign, s.utm_term, s.utm_content, s.clicks, s.last_click_at, s.created_at,
					(SELECT COALESCE(SUM(c.clicks), 0) FROM {$tables['clicks']} c WHERE c.short_link_id = s.id AND c.day >= %s) AS recent
				FROM {$tables['short_links']} s WHERE ( %s = '' OR s.url LIKE %s OR s.code LIKE %s ) AND ( %d = 0 OR s.utm_campaign = %s ) ORDER BY s.id DESC LIMIT %d OFFSET %d",
				$since,
				$search,
				$like,
				$like,
				$filter,
				$campaign,
				$limit,
				$skip
			),
			ARRAY_A
		);
		// phpcs:enable
		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Delete a short link and its clicks, and clear it from saved batches. Visiting it gives a 404
	 * from then on.
	 *
	 * @param string $code Code.
	 * @return bool Whether a short link was deleted.
	 */
	public static function delete( $code ) {
		global $wpdb;
		$tables = Sunshine_UTM_Database::tables();
		$code   = strtolower( (string) $code );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table names come from Sunshine_UTM_Database::tables().
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['short_links']} WHERE code = %s LIMIT 1", $code ) );
		if ( ! $id ) {
			return false;
		}
		$wpdb->delete( $tables['short_links'], array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( $tables['clicks'], array( 'short_link_id' => $id ), array( '%d' ) );
		// phpcs:enable
		self::replace_in_batches( $code, '' );
		$custom = self::custom_codes();
		if ( isset( $custom[ $code ] ) ) {
			unset( $custom[ $code ] );
			update_option( self::CUSTOM_OPTION, $custom, true );
		}
		return true;
	}

	/**
	 * Short links that go to one page (with any tags or parameters), most clicked first, for the
	 * block editor's panel.
	 *
	 * @param string $page_url The page's address.
	 * @param int    $limit    How many.
	 * @return array[] code, url, clicks
	 */
	public static function for_page( $page_url, $limit = 10 ) {
		global $wpdb;
		$page_url = (string) $page_url;
		if ( '' === $page_url ) {
			return array();
		}
		$table = Sunshine_UTM_Database::tables()['short_links'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name comes from Sunshine_UTM_Database::tables().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT code, url, clicks FROM {$table} WHERE url = %s OR url LIKE %s OR url LIKE %s ORDER BY clicks DESC, id DESC LIMIT %d", $page_url, $wpdb->esc_like( $page_url . '?' ) . '%', $wpdb->esc_like( $page_url . '#' ) . '%', $limit ), ARRAY_A );
		return (array) $rows;
	}

	/**
	 * Total clicks for a list of short links, keyed by the short link as given. Links that aren't
	 * this site's short links are left out.
	 *
	 * @param string[] $short_urls Short links, like https://example.com/g/x8d89c.
	 * @return int[]
	 */
	public static function clicks_for( $short_urls ) {
		global $wpdb;
		$codes = array();
		$home  = untrailingslashit( home_url() ) . '/';
		foreach ( array_filter( (array) $short_urls ) as $short_url ) {
			$parts = explode( '/', untrailingslashit( (string) $short_url ) );
			$code  = strtolower( (string) end( $parts ) );
			if ( 0 === strpos( (string) $short_url, $home ) && preg_match( '/^[a-z0-9-]{3,50}$/', $code ) ) {
				$codes[ $short_url ] = $code;
			}
		}
		if ( ! $codes ) {
			return array();
		}
		$table = Sunshine_UTM_Database::tables()['short_links'];
		$in    = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT code, clicks FROM {$table} WHERE code IN ({$in})", array_values( array_unique( $codes ) ) ), OBJECT_K );
		$out   = array();
		foreach ( $codes as $short_url => $code ) {
			if ( isset( $found[ $code ] ) ) {
				$out[ $short_url ] = (int) $found[ $code ]->clicks;
			}
		}
		return $out;
	}

	/**
	 * Add the short link's own query string to the full link, leaving out utm_ parameters and any
	 * the full link already has. Kept before the #anchor.
	 *
	 * @param string $url   Full link.
	 * @param string $query Query string the visitor arrived with.
	 * @return string
	 */
	public static function pass_on( $url, $query ) {
		if ( '' === $query ) {
			return $url;
		}
		$hash = '';
		$pos  = strpos( $url, '#' );
		if ( false !== $pos ) {
			$hash = substr( $url, $pos );
			$url  = substr( $url, 0, $pos );
		}
		$name  = function ( $pair ) {
			return strtolower( rawurldecode( str_replace( '+', ' ', explode( '=', $pair, 2 )[0] ) ) );
		};
		$have  = array();
		$pos   = strpos( $url, '?' );
		$pairs = false === $pos ? array() : array_filter( explode( '&', substr( $url, $pos + 1 ) ), 'strlen' );
		foreach ( $pairs as $pair ) {
			$have[] = $name( $pair );
		}
		$add = array();
		foreach ( explode( '&', $query ) as $pair ) {
			$key = $name( $pair );
			if ( '' === $pair || '' === $key || 0 === strpos( $key, 'utm_' ) || in_array( $key, $have, true ) ) {
				continue;
			}
			$add[]  = $pair;
			$have[] = $key;
		}
		if ( ! $add ) {
			return $url . $hash;
		}
		return $url . ( false === $pos ? '?' : ( '?' === substr( $url, -1 ) ? '' : '&' ) ) . implode( '&', $add ) . $hash;
	}

	/**
	 * The requested path without the site's own folder or slashes at either end, like "g/x8d89c".
	 *
	 * @return string
	 */
	protected static function request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched against a pattern.
		$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $home && 0 === strpos( $path . '/', $home . '/' ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		return $path;
	}

	protected static function new_code() {
		$code = '';
		$max  = strlen( self::ALPHABET ) - 1;
		for ( $i = 0; $i < self::LENGTH; $i++ ) {
			$code .= self::ALPHABET[ random_int( 0, $max ) ];
		}
		return $code;
	}

	/**
	 * A post or page already uses this address, so a code here would never be reached.
	 *
	 * @param string $code Code.
	 * @return bool
	 */
	protected static function is_page( $code ) {
		return '' === self::prefix() && null !== get_page_by_path( $code, OBJECT, get_post_types( array( 'public' => true ) ) );
	}
}
