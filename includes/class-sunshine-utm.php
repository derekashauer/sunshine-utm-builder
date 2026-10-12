<?php
/**
 * Core: settings, the link builder (on the plugin's admin screen only), the admin bar link, the
 * shortener request, and the REST route.
 *
 * The builder works the link out in the browser as you type (assets/js/builder.js). Bulk links are
 * built in PHP (Sunshine_UTM_URL_Builder). Both follow the same formatting rules, so a link built
 * one at a time matches the same link built in a batch. Change one, change the other.
 *
 * Shortening (Bitly, Rebrandly or the site's own short links) is for admins only. There's
 * deliberately no builder for visitors: a public one would let anyone make short links on the
 * owner's domain, which could dress up spam as a link from the site.
 *
 * Hooks used:
 * - init: database upgrade check.
 * - admin_bar_menu: "UTM link" on the front end.
 * - wp_ajax_sunshine_utm_shorten: shorten one link (admins).
 * - rest_api_init: POST sunshine-utm/v1/links.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM {

	const OPTION = 'sunshine_utm_settings';

	const NONCE = 'sunshine_utm_builder';

	protected static $instance;

	protected $settings;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		require_once SUNSHINE_UTM_DIR . 'includes/class-database.php';
		require_once SUNSHINE_UTM_DIR . 'includes/class-url-builder.php';
		require_once SUNSHINE_UTM_DIR . 'includes/class-presets.php';
		require_once SUNSHINE_UTM_DIR . 'includes/class-batches.php';
		require_once SUNSHINE_UTM_DIR . 'includes/class-bulk.php';
		require_once SUNSHINE_UTM_DIR . 'includes/class-shortener.php';
		require_once SUNSHINE_UTM_DIR . 'includes/class-url-checker.php';
		require_once SUNSHINE_UTM_DIR . 'includes/class-short-links.php';
		require_once SUNSHINE_UTM_DIR . 'includes/class-conversion-bridge.php';
		require_once SUNSHINE_UTM_DIR . 'includes/class-abilities.php';
		new Sunshine_UTM_Short_Links();
		new Sunshine_UTM_Abilities();
		new Sunshine_UTM_Conversion_Bridge();

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
		add_action( 'wp_ajax_sunshine_utm_shorten', array( $this, 'ajax_shorten' ) );
		add_action( 'rest_api_init', array( $this, 'rest_routes' ) );

		if ( is_admin() ) {
			require_once SUNSHINE_UTM_DIR . 'includes/admin/class-admin.php';
			new Sunshine_UTM_Admin();
		}
	}

	public static function activate() {
		require_once SUNSHINE_UTM_DIR . 'includes/class-database.php';
		Sunshine_UTM_Database::install();
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::default_settings() );
		}
	}

	public function init() {
		// Activation doesn't run on updates or some network installs, so check the tables here too.
		Sunshine_UTM_Database::maybe_install();
	}

	public static function default_settings() {
		return array(
			'admin_bar'       => true,
			'row_actions'     => true,
			'editor_panel'    => true,
			'post_types'      => array( 'post', 'page' ),
			'lowercase'       => true,
			'spaces'          => '_',
			'strip_symbols'   => true,
			'check_urls'      => false,
			'site_links'      => true,
			'shortener'       => 'site',
			'bitly_token'     => '',
			'rebrandly_key'   => '',
			'short_prefix'    => '',
			'allowed_sources' => array(),
			'allowed_mediums' => array(),
			'allowed_only'    => false,
			'delete_data'     => false,
		);
	}

	public function get_settings() {
		if ( null === $this->settings ) {
			$this->settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::default_settings() );
		}
		return $this->settings;
	}

	public function get_setting( $key ) {
		$settings = $this->get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	public function update_settings( $changes ) {
		$this->settings = array_merge( $this->get_settings(), $changes );
		update_option( self::OPTION, $this->settings );
	}

	/**
	 * The formatting rules from Settings, in the shape the URL builder (PHP and JS) expects.
	 *
	 * @return array {lowercase: bool, spaces: '_'|'-'|'keep', strip_symbols: bool}
	 */
	public function format_options() {
		$spaces = $this->get_setting( 'spaces' );
		return array(
			'lowercase'     => (bool) $this->get_setting( 'lowercase' ),
			'spaces'        => in_array( $spaces, array( '_', '-' ), true ) ? $spaces : 'keep',
			'strip_symbols' => (bool) $this->get_setting( 'strip_symbols' ),
		);
	}

	/**
	 * One sentence saying what the formatting rules do to typed tags, or '' when they're all off.
	 *
	 * @return string
	 */
	public function format_summary() {
		$format = $this->format_options();
		$parts  = array();
		if ( $format['lowercase'] ) {
			$parts[] = __( 'made lowercase', 'sunshine-utm-builder' );
		}
		if ( '_' === $format['spaces'] ) {
			$parts[] = __( 'spaces become underscores', 'sunshine-utm-builder' );
		} elseif ( '-' === $format['spaces'] ) {
			$parts[] = __( 'spaces become dashes', 'sunshine-utm-builder' );
		}
		if ( $format['strip_symbols'] ) {
			$parts[] = __( 'symbols are removed', 'sunshine-utm-builder' );
		}
		if ( ! $parts ) {
			return '';
		}
		/* translators: %s: list of formatting rules, like "made lowercase, spaces become underscores". */
		return sprintf( __( 'Tags are %s.', 'sunshine-utm-builder' ), wp_sprintf_l( '%l', $parts ) );
	}

	/**
	 * The team's list of sources or mediums from Settings, or an empty list when there isn't one.
	 *
	 * @param string $key utm_source or utm_medium.
	 * @return string[]
	 */
	public function allowed_values( $key ) {
		$setting = array(
			'utm_source' => 'allowed_sources',
			'utm_medium' => 'allowed_mediums',
		);
		return isset( $setting[ $key ] ) ? array_values( (array) $this->get_setting( $setting[ $key ] ) ) : array();
	}

	/**
	 * Why a tag value isn't allowed, or '' when it is. Only when "Only allow these" is on in
	 * Settings, and only for the source and medium. The value is checked as it will be in the link,
	 * after formatting. An empty value isn't checked: the medium is optional, and a missing source
	 * is refused elsewhere.
	 *
	 * @param string $key   utm_source or utm_medium.
	 * @param string $value Formatted value.
	 * @return string
	 */
	public function tag_problem( $key, $value ) {
		$list = $this->allowed_values( $key );
		if ( '' === (string) $value || ! $this->get_setting( 'allowed_only' ) || ! $list || in_array( (string) $value, $list, true ) ) {
			return '';
		}
		return 'utm_source' === $key
			/* translators: 1: the source typed, 2: the allowed sources. */
			? sprintf( __( 'The source "%1$s" isn\'t on your team\'s list. Use one of: %2$s.', 'sunshine-utm-builder' ), $value, implode( ', ', $list ) )
			/* translators: 1: the medium typed, 2: the allowed mediums. */
			: sprintf( __( 'The medium "%1$s" isn\'t on your team\'s list. Use one of: %2$s.', 'sunshine-utm-builder' ), $value, implode( ', ', $list ) );
	}

	/**
	 * Shortening is offered: at least one service is set up.
	 *
	 * @return bool
	 */
	public function can_shorten() {
		return (bool) Sunshine_UTM_Shortener::ready();
	}

	/**
	 * The builder's script and styles. Registered everywhere, enqueued by whoever shows the builder.
	 */
	public function register_builder_assets() {
		if ( wp_script_is( 'sunshine-utm-builder', 'registered' ) ) {
			return;
		}
		wp_register_style( 'sunshine-utm-builder', SUNSHINE_UTM_URL . 'assets/css/builder.css', array(), SUNSHINE_UTM_VERSION );
		wp_register_script( 'sunshine-utm-qr', SUNSHINE_UTM_URL . 'assets/js/qr.js', array(), SUNSHINE_UTM_VERSION, true );
		wp_localize_script(
			'sunshine-utm-qr',
			'SunshineUTMQRTexts',
			array(
				/* translators: %s: the link in the QR code. */
				'alt' => __( 'QR code for %s', 'sunshine-utm-builder' ),
				'png' => __( 'Download PNG', 'sunshine-utm-builder' ),
				'svg' => __( 'Download SVG', 'sunshine-utm-builder' ),
				'tip' => __( 'PNG for most uses, SVG for print, since it stays sharp at any size. Test it with your phone before printing.', 'sunshine-utm-builder' ),
			)
		);
		wp_register_script( 'sunshine-utm-channels', SUNSHINE_UTM_URL . 'assets/js/channels.js', array(), SUNSHINE_UTM_VERSION, true );
		wp_register_script( 'sunshine-utm-builder', SUNSHINE_UTM_URL . 'assets/js/builder.js', array( 'sunshine-utm-qr', 'sunshine-utm-channels' ), SUNSHINE_UTM_VERSION, true );
		wp_localize_script(
			'sunshine-utm-builder',
			'SunshineUTMBuilder',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'texts'    => array(
					'need_url'           => __( 'Enter the page you want to link to.', 'sunshine-utm-builder' ),
					'bad_url'            => __( 'That doesn\'t look like a web address. It should start with https://', 'sunshine-utm-builder' ),
					'need_source'        => __( 'Add a source to finish your link.', 'sunshine-utm-builder' ),
					'copy'               => __( 'Copy link', 'sunshine-utm-builder' ),
					'copied'             => __( 'Copied!', 'sunshine-utm-builder' ),
					'copy_failed'        => __( 'Your browser blocked copying. Select the link and copy it yourself.', 'sunshine-utm-builder' ),
					'shortening'         => __( 'Making your short link...', 'sunshine-utm-builder' ),
					'short_failed'       => __( 'The link could not be shortened.', 'sunshine-utm-builder' ),
					'preset_wait'        => __( 'Loading preset...', 'sunshine-utm-builder' ),
					/* translators: %s: a Google Analytics channel name, like Organic Social. */
					'channel'            => __( 'Google Analytics 4 channel: %s', 'sunshine-utm-builder' ),
					/* translators: 1: the medium typed, 2: a medium Google Analytics knows. */
					'unassigned_hint'    => __( 'Google Analytics 4 will put these visits under Unassigned, because it doesn\'t know the medium "%1$s". Try %2$s.', 'sunshine-utm-builder' ),
					'unassigned'         => __( 'Google Analytics 4 will put these visits under Unassigned, because it doesn\'t know this source and medium. Use a medium it knows, like social, email, cpc, referral, affiliate or display.', 'sunshine-utm-builder' ),
					/* translators: 1: the source typed, 2: the allowed sources. */
					'not_allowed_source' => __( 'The source "%1$s" isn\'t on your team\'s list. Use one of: %2$s.', 'sunshine-utm-builder' ),
					/* translators: 1: the medium typed, 2: the allowed mediums. */
					'not_allowed_medium' => __( 'The medium "%1$s" isn\'t on your team\'s list. Use one of: %2$s.', 'sunshine-utm-builder' ),
				),
			)
		);
	}

	/**
	 * The link builder form, for the plugin's admin screen.
	 *
	 * @param array $args url (to fill in).
	 * @return string HTML.
	 */
	public function render_builder( $args ) {
		$args = wp_parse_args( $args, array( 'url' => '' ) );

		$url        = $args['url'];
		$id         = wp_unique_id( 'sunshine-utm-' );
		$presets    = Sunshine_UTM_Presets::get_all();
		$shorteners = Sunshine_UTM_Shortener::ready();
		$format     = $this->format_options();
		$summary    = $this->format_summary();
		$fields     = Sunshine_UTM_URL_Builder::fields();
		$datalist   = Sunshine_UTM_URL_Builder::suggestions();
		$allowed    = array();
		foreach ( array( 'utm_source', 'utm_medium' ) as $key ) {
			$list = $this->allowed_values( $key );
			if ( $list ) {
				// The team's own list replaces the general suggestions.
				$datalist[ $key ] = $list;
				if ( $this->get_setting( 'allowed_only' ) ) {
					$allowed[ $key ] = $list;
				}
			}
		}

		ob_start();
		include SUNSHINE_UTM_DIR . 'includes/views/builder.php';
		return ob_get_clean();
	}

	/**
	 * "UTM link" in the admin bar on the front end, opening the builder with the current page.
	 *
	 * Post types chosen in Settings limit it to those single pages. With none chosen it shows on
	 * every front-end page.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public function admin_bar( $bar ) {
		if ( is_admin() || ! $this->get_setting( 'admin_bar' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_types = (array) $this->get_setting( 'post_types' );
		if ( $post_types ) {
			if ( ! is_singular( $post_types ) ) {
				return;
			}
			$url = get_permalink( get_queried_object_id() );
		} else {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			$url  = set_url_scheme( 'http://' . $host . $path );
		}
		if ( ! $url ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => 'sunshine-utm',
				'title' => '<span class="ab-icon dashicons dashicons-admin-links" style="top:2px" aria-hidden="true"></span>' . esc_html__( 'UTM link', 'sunshine-utm-builder' ),
				'href'  => self::builder_url( Sunshine_UTM_URL_Builder::strip_utm( $url ) ),
				'meta'  => array( 'title' => __( 'Build a UTM link for this page', 'sunshine-utm-builder' ) ),
			)
		);
	}

	/**
	 * Admin link to the builder with a page filled in.
	 *
	 * @param string $url Page to link to.
	 * @return string
	 */
	public static function builder_url( $url = '' ) {
		$args = array( 'page' => 'sunshine-utm-builder' );
		if ( $url ) {
			$args['url'] = rawurlencode( $url );
		}
		return add_query_arg( $args, admin_url( 'tools.php' ) );
	}

	/**
	 * The link goes to this site: same host (with or without www.), and inside the site's folder
	 * when WordPress lives in one.
	 *
	 * A backslash or a user:password@ part is refused, because PHP and browsers read the host of
	 * those differently (https://evil.com\@mysite.com goes to evil.com in a browser).
	 *
	 * @param string $url Link.
	 * @return bool
	 */
	public static function is_this_site( $url ) {
		$url = (string) $url;
		if ( false !== strpos( $url, '\\' ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url() );
		if ( ! $parts || isset( $parts['user'] ) || isset( $parts['pass'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		$host = function ( $host ) {
			return preg_replace( '/^www\./', '', strtolower( (string) $host ) );
		};
		if ( $host( $parts['host'] ) !== $host( isset( $home['host'] ) ? $home['host'] : '' ) ) {
			return false;
		}
		// Same host on another port can be a different server (yoursite.com:8443).
		if ( ( isset( $parts['port'] ) ? (int) $parts['port'] : 0 ) !== ( isset( $home['port'] ) ? (int) $home['port'] : 0 ) ) {
			return false;
		}
		$home_path = trailingslashit( isset( $home['path'] ) ? $home['path'] : '/' );
		return 0 === strpos( trailingslashit( isset( $parts['path'] ) ? $parts['path'] : '/' ), $home_path );
	}

	public function ajax_shorten() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page is out of date. Reload it and try again.', 'sunshine-utm-builder' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'sunshine-utm-builder' ) ), 403 );
		}

		// Not sanitize_text_field: it strips %XX sequences, which breaks encoded links.
		$url = isset( $_POST['url'] ) ? trim( (string) wp_unslash( $_POST['url'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated next.
		if ( ! Sunshine_UTM_URL_Builder::is_valid_url( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'That link isn\'t a full web address.', 'sunshine-utm-builder' ) ), 400 );
		}

		$service = '';
		if ( isset( $_POST['service'] ) ) {
			$service = sanitize_key( wp_unslash( $_POST['service'] ) );
			if ( ! Sunshine_UTM_Shortener::is_ready( $service ) ) {
				$service = '';
			}
		}
		$short = Sunshine_UTM_Shortener::shorten( $url, $service );
		if ( is_wp_error( $short ) ) {
			wp_send_json_error( array( 'message' => $short->get_error_message() ) );
		}
		wp_send_json_success( array( 'short_url' => $short ) );
	}

	/**
	 * POST /wp-json/sunshine-utm/v1/links
	 *
	 * Body: urls (array), source, medium, campaign, term, content, preset (ID), params (object of
	 * extra parameters), shorten (bool), shortener (site, bitly or rebrandly; defaults to the default
	 * from Settings), save (bool), name (batch name).
	 */
	public function rest_routes() {
		register_rest_route(
			'sunshine-utm/v1',
			'/links',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_links' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'urls' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_links( $request ) {
		$tags = array();
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			$tags[ $key ] = (string) $request->get_param( substr( $key, 4 ) );
		}
		$input = array(
			'method'    => 'urls',
			'urls'      => implode( "\n", array_map( 'strval', (array) $request->get_param( 'urls' ) ) ),
			'preset_id' => absint( $request->get_param( 'preset' ) ),
			'tags'      => $tags,
			'extra'     => array_map( 'strval', (array) $request->get_param( 'params' ) ),
		);

		$result = Sunshine_UTM_Bulk::rows( $input );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		$shorten = rest_sanitize_boolean( $request->get_param( 'shorten' ) ) && $this->can_shorten();
		$service = sanitize_key( (string) $request->get_param( 'shortener' ) );
		if ( $shorten ) {
			$service = '' !== $service ? $service : Sunshine_UTM_Shortener::default_service();
			if ( ! Sunshine_UTM_Shortener::is_ready( $service ) ) {
				/* translators: %s: list of shorteners. */
				return new WP_Error( 'not_ready', sprintf( __( 'That shortener isn\'t set up. These are: %s.', 'sunshine-utm-builder' ), implode( ', ', array_keys( Sunshine_UTM_Shortener::ready() ) ) ), array( 'status' => 400 ) );
			}
		}
		if ( $shorten && Sunshine_UTM_Shortener::is_outside( $service ) && count( $result['rows'] ) > Sunshine_UTM_Bulk::SHORTEN_MAX ) {
			/* translators: %s: the most links that can be shortened at once. */
			return new WP_Error( 'too_many_to_shorten', sprintf( __( 'Shortening works on up to %s links at a time.', 'sunshine-utm-builder' ), Sunshine_UTM_Bulk::SHORTEN_MAX ), array( 'status' => 400 ) );
		}
		$links = Sunshine_UTM_Bulk::generate(
			$result['rows'],
			array(
				'shorten' => $shorten ? $service : false,
				'check'   => false,
			)
		);

		$batch_id = 0;
		if ( $request->get_param( 'save' ) ) {
			$batch_id = Sunshine_UTM_Batches::create(
				array(
					'name'   => (string) $request->get_param( 'name' ),
					'method' => 'api',
				),
				$links
			);
		}

		return rest_ensure_response(
			array(
				'links'    => $links,
				'count'    => count( $links ),
				'skipped'  => $result['skipped'],
				'batch_id' => $batch_id,
			)
		);
	}
}
