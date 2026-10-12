<?php
/**
 * Admin screen: Tools → UTM Builder, with tabs for the link builder, the Bulk Builder, saved
 * batches, presets and settings.
 *
 * Looks like the other WP Sunshine plugins (Confetti, Address Autocomplete): the white header with
 * the logo, help links and tabs, the cream page, white panels with option rows, and a sidebar.
 * assets/css/admin.css keeps the shared parts in step with theirs.
 *
 * Forms post back to this screen and redirect after saving (post/redirect/get), with the message in
 * a short-lived transient. The Bulk Builder's preview and create, the builder's preset fill, and
 * link notes use admin-ajax. Everything needs manage_options and a nonce.
 *
 * Hooks used:
 * - admin_menu, admin_enqueue_scripts, admin_body_class: the screen.
 * - admin_init: form saves.
 * - admin_notices: the message after a save.
 * - admin_post_sunshine_utm_export: batch CSV download.
 * - wp_ajax_sunshine_utm_preset, _bulk_preview, _bulk_create, _link_note.
 * - post_row_actions, page_row_actions: "UTM link" under published posts.
 * - plugin_action_links_{plugin}: link on the Plugins screen.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_Admin {

	const SLUG = 'sunshine-utm-builder';

	const NONCE = 'sunshine_utm_admin';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'admin_post_sunshine_utm_export', array( $this, 'export' ) );
		add_action( 'wp_ajax_sunshine_utm_preset', array( $this, 'ajax_preset' ) );
		add_action( 'wp_ajax_sunshine_utm_bulk_preview', array( $this, 'ajax_bulk_preview' ) );
		add_action( 'wp_ajax_sunshine_utm_bulk_create', array( $this, 'ajax_bulk_create' ) );
		add_action( 'wp_ajax_sunshine_utm_link_note', array( $this, 'ajax_link_note' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( SUNSHINE_UTM_FILE ), array( $this, 'action_links' ) );
	}

	public function menu() {
		add_management_page(
			__( 'UTM Builder', 'sunshine-utm-builder' ),
			__( 'UTM Builder', 'sunshine-utm-builder' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	protected function is_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen check only.
		return isset( $_GET['page'] ) && self::SLUG === $_GET['page'];
	}

	public function body_class( $classes ) {
		return $this->is_screen() ? $classes . ' sunshine-utm-admin' : $classes;
	}

	/**
	 * Screen URL.
	 *
	 * @param array $args Query args, like ['tab' => 'batches'].
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'tools.php' ) );
	}

	public function tabs() {
		return array(
			'builder'  => __( 'Link Builder', 'sunshine-utm-builder' ),
			'bulk'     => __( 'Bulk Builder', 'sunshine-utm-builder' ),
			'batches'  => __( 'Saved Batches', 'sunshine-utm-builder' ),
			'links'    => __( 'Short Links', 'sunshine-utm-builder' ),
			'presets'  => __( 'Presets', 'sunshine-utm-builder' ),
			'settings' => __( 'Settings', 'sunshine-utm-builder' ),
		);
	}

	public function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'builder';
		return isset( $this->tabs()[ $tab ] ) ? $tab : 'builder';
	}

	public function assets() {
		if ( ! $this->is_screen() ) {
			return;
		}
		$plugin = sunshine_utm();
		$plugin->register_builder_assets();
		wp_enqueue_style( 'sunshine-utm-admin', SUNSHINE_UTM_URL . 'assets/css/admin.css', array(), SUNSHINE_UTM_VERSION );

		$tab = $this->current_tab();
		// The settings tab uses the builder's formatValue() for its "Try it" example.
		if ( 'builder' === $tab || 'settings' === $tab ) {
			wp_enqueue_script( 'sunshine-utm-builder' );
		}
		if ( 'builder' === $tab ) {
			wp_enqueue_style( 'sunshine-utm-builder' );
		}
		if ( 'builder' !== $tab ) {
			wp_enqueue_script( 'sunshine-utm-admin', SUNSHINE_UTM_URL . 'assets/js/admin.js', array( 'sunshine-utm-qr', 'sunshine-utm-channels' ), SUNSHINE_UTM_VERSION, true );
			wp_localize_script(
				'sunshine-utm-admin',
				'SunshineUTMAdmin',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( self::NONCE ),
					'texts'    => array(
						'copy'            => __( 'Copy', 'sunshine-utm-builder' ),
						'copied'          => __( 'Copied!', 'sunshine-utm-builder' ),
						/* translators: %d: number of links. */
						'copied_all'      => __( 'Copied %d links.', 'sunshine-utm-builder' ),
						'copy_failed'     => __( 'Your browser blocked copying.', 'sunshine-utm-builder' ),
						'loading'         => __( 'Working out your links...', 'sunshine-utm-builder' ),
						'creating'        => __( 'Creating your links. Shortening and link checks can take a minute.', 'sunshine-utm-builder' ),
						'failed'          => __( 'Something went wrong. Reload the page and try again.', 'sunshine-utm-builder' ),
						'saved'           => __( 'Saved', 'sunshine-utm-builder' ),
						/* translators: %s: number of links. */
						'create'          => __( 'Create %s links', 'sunshine-utm-builder' ),
						'create_one'      => __( 'Create 1 link', 'sunshine-utm-builder' ),
						'create_none'     => __( 'Create links', 'sunshine-utm-builder' ),
						/* translators: %s: number of links. */
						'count'           => __( '%s links', 'sunshine-utm-builder' ),
						'count_one'       => __( '1 link', 'sunshine-utm-builder' ),
						/* translators: %s: number of links. */
						'more'            => __( 'And %s more, not shown here.', 'sunshine-utm-builder' ),
						'empty'           => __( 'Add your links above and a preview of the first 20 shows here.', 'sunshine-utm-builder' ),
						'col_page'        => __( 'Page and tags', 'sunshine-utm-builder' ),
						'col_link'        => __( 'Link', 'sunshine-utm-builder' ),
						/* translators: %s: a Google Analytics channel name, like Organic Social. */
						'channel'         => __( 'GA4 channel: %s', 'sunshine-utm-builder' ),
						'unassigned'      => __( 'GA4 will show this as Unassigned. Use a medium it knows, like social, email, cpc, referral or affiliate.', 'sunshine-utm-builder' ),
						/* translators: %s: a medium Google Analytics knows. */
						'unassigned_hint' => __( 'GA4 will show this as Unassigned. Try the medium %s.', 'sunshine-utm-builder' ),
					),
				)
			);
		}
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Build a link', 'sunshine-utm-builder' ) . '</a>' );
		return $links;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs   = $this->tabs();
		$tab    = $this->current_tab();
		$plugin = sunshine_utm();
		$admin  = $this;

		echo '<div id="sunshine-utm-admin">';
		include SUNSHINE_UTM_DIR . 'includes/admin/views/header.php';
		/* translators: %s: tab name. */
		echo '<div class="wrap wps-wrap"><h1 class="screen-reader-text">' . esc_html( sprintf( __( 'UTM Builder: %s', 'sunshine-utm-builder' ), $tabs[ $tab ] ) ) . '</h1>';
		include SUNSHINE_UTM_DIR . 'includes/admin/views/tab-' . $tab . '.php';
		echo '</div></div>';
	}

	/**
	 * Open a panel: the white box with a heading. Same markup as Confetti and Address Autocomplete.
	 *
	 * @param string $id    Panel ID.
	 * @param string $title Heading.
	 * @param array  $args  intro (a line under the heading), badge.
	 */
	public static function panel_open( $id, $title, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'intro' => '',
				'badge' => '',
			)
		);
		?>
		<section class="wps-panel" id="wps-panel-<?php echo esc_attr( $id ); ?>">
			<div class="wps-panel__head">
				<h2 class="wps-panel__title"><?php echo esc_html( $title ); ?></h2>
				<?php if ( $args['badge'] ) : ?>
					<span class="wps-badge"><?php echo esc_html( $args['badge'] ); ?></span>
				<?php endif; ?>
			</div>
			<div class="wps-panel__body">
			<?php if ( $args['intro'] ) : ?>
				<p class="wps-panel__intro"><?php echo wp_kses_post( $args['intro'] ); ?></p>
			<?php endif; ?>
		<?php
	}

	public static function panel_close() {
		echo '</div></section>';
	}

	/**
	 * Open an option row: label on the left, control on the right.
	 *
	 * @param string $label Label.
	 * @param string $for   ID of the control the label is for.
	 */
	public static function option_row_open( $label, $for = '' ) {
		?>
		<div class="wps-option-row">
			<div class="wps-option-row__label">
				<?php if ( $for ) : ?>
					<label for="<?php echo esc_attr( $for ); ?>"><?php echo esc_html( $label ); ?></label>
				<?php else : ?>
					<?php echo esc_html( $label ); ?>
				<?php endif; ?>
			</div>
			<div class="wps-option-row__control">
		<?php
	}

	/**
	 * Close an option row.
	 *
	 * @param string $description Help under the control. May hold links.
	 */
	public static function option_row_close( $description = '' ) {
		if ( $description ) {
			echo '<p class="description">' . wp_kses_post( $description ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * A checkbox with its label, the way Confetti's settings show them.
	 *
	 * @param string $name    Field name.
	 * @param bool   $checked On.
	 * @param string $label   Text beside it.
	 * @param string $id      Input ID.
	 */
	public static function checkbox_field( $name, $checked, $label, $id = '' ) {
		$id = $id ? $id : 'sunshine-utm-' . sanitize_key( $name );
		?>
		<label for="<?php echo esc_attr( $id ); ?>">
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
			<?php echo esc_html( $label ); ?>
		</label>
		<?php
	}

	/**
	 * Opening form tag with the action and nonce.
	 *
	 * @param string $action sunshine_utm_action value.
	 * @param string $class  Extra class.
	 */
	public static function form_open( $action, $class = '' ) {
		echo '<form method="post" action="' . esc_url( self::url() ) . '" class="' . esc_attr( $class ) . '">';
		echo '<input type="hidden" name="sunshine_utm_action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( self::NONCE, '_sunshine_utm_nonce' );
	}

	/**
	 * "(opens in a new tab)" for screen readers and a small icon for everyone else.
	 */
	public static function new_tab_note() {
		echo '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'sunshine-utm-builder' ) . '</span><span aria-hidden="true" class="dashicons dashicons-external wps-new-tab"></span>';
	}

	public function handle_post() {
		if ( empty( $_POST['sunshine_utm_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change UTM Builder.', 'sunshine-utm-builder' ) );
		}
		check_admin_referer( self::NONCE, '_sunshine_utm_nonce' );

		$post   = wp_unslash( $_POST );
		$action = sanitize_key( $post['sunshine_utm_action'] );
		$next   = self::url();

		switch ( $action ) {
			case 'save_settings':
				sunshine_utm()->update_settings( $this->sanitize_settings( $post ) );
				$this->add_notice( __( 'Settings saved.', 'sunshine-utm-builder' ) );
				$next = self::url( array( 'tab' => 'settings' ) );
				break;

			case 'save_preset':
				$id = isset( $post['preset_id'] ) ? absint( $post['preset_id'] ) : 0;
				if ( '' === trim( isset( $post['name'] ) ? (string) $post['name'] : '' ) ) {
					$this->add_notice( __( 'Give the preset a name.', 'sunshine-utm-builder' ), 'error' );
					$next = self::url( array_filter( array(
'tab'    => 'presets',
'preset' => $id
) ) );
					break;
				}
				$saved = Sunshine_UTM_Presets::save( $post, $id );
				if ( $saved ) {
					$this->add_notice( $id ? __( 'Preset updated.', 'sunshine-utm-builder' ) : __( 'Preset added.', 'sunshine-utm-builder' ) );
				} else {
					$this->add_notice( __( 'The preset could not be saved.', 'sunshine-utm-builder' ), 'error' );
				}
				$next = self::url( array( 'tab' => 'presets' ) );
				break;

			case 'delete_preset':
				Sunshine_UTM_Presets::delete( isset( $post['preset_id'] ) ? absint( $post['preset_id'] ) : 0 );
				$this->add_notice( __( 'Preset deleted.', 'sunshine-utm-builder' ) );
				$next = self::url( array( 'tab' => 'presets' ) );
				break;

			case 'save_batch_notes':
				$batch_id = isset( $post['batch_id'] ) ? absint( $post['batch_id'] ) : 0;
				Sunshine_UTM_Batches::update_notes( $batch_id, isset( $post['notes'] ) ? (string) $post['notes'] : '' );
				$this->add_notice( __( 'Notes saved.', 'sunshine-utm-builder' ) );
				$next = self::url(
					array(
						'tab'   => 'batches',
						'batch' => $batch_id,
					)
				);
				break;

			case 'update_short_link':
				$code   = isset( $post['code'] ) ? sanitize_title( $post['code'] ) : '';
				$result = Sunshine_UTM_Short_Links::update(
					$code,
					isset( $post['new_code'] ) && is_string( $post['new_code'] ) ? $post['new_code'] : '',
					// Not esc_url_raw or sanitize_text_field: they'd change an encoded link. update() checks it.
					isset( $post['url'] ) && is_string( $post['url'] ) ? trim( $post['url'] ) : ''
				);
				if ( is_wp_error( $result ) ) {
					$this->add_notice( $result->get_error_message(), 'error' );
				} else {
					$this->add_notice( __( 'Short link saved.', 'sunshine-utm-builder' ) );
					$code = $result;
				}
				$next = self::url(
					array(
						'tab'  => 'links',
						'code' => $code,
					)
				);
				break;

			case 'delete_short_link':
				$code = isset( $post['code'] ) ? sanitize_key( $post['code'] ) : '';
				if ( Sunshine_UTM_Short_Links::delete( $code ) ) {
					$this->add_notice( __( 'Short link deleted.', 'sunshine-utm-builder' ) );
				}
				$next = self::url( array( 'tab' => 'links' ) );
				break;

			case 'delete_batch':
				Sunshine_UTM_Batches::delete( isset( $post['batch_id'] ) ? absint( $post['batch_id'] ) : 0 );
				$this->add_notice( __( 'Batch deleted.', 'sunshine-utm-builder' ) );
				$next = self::url( array( 'tab' => 'batches' ) );
				break;
		}

		wp_safe_redirect( $next );
		exit;
	}

	protected function sanitize_settings( $post ) {
		$post_types = array();
		$allowed    = get_post_types( array( 'public' => true ) );
		foreach ( isset( $post['post_types'] ) ? (array) $post['post_types'] : array() as $type ) {
			$type = sanitize_key( $type );
			if ( isset( $allowed[ $type ] ) ) {
				$post_types[] = $type;
			}
		}
		$shortener = isset( $post['shortener'] ) ? sanitize_key( $post['shortener'] ) : '';
		$spaces    = isset( $post['spaces'] ) ? (string) $post['spaces'] : '_';
		$spaces    = in_array( $spaces, array( '_', '-', 'keep' ), true ) ? $spaces : '_';
		// Team lists are saved the way the values will look in links, using the rules saved with them.
		$format = array(
			'lowercase'     => ! empty( $post['lowercase'] ),
			'spaces'        => $spaces,
			'strip_symbols' => ! empty( $post['strip_symbols'] ),
		);
		$list   = function ( $name ) use ( $post, $format ) {
			$values = array();
			foreach ( preg_split( '/\r\n|\r|\n|,/', isset( $post[ $name ] ) && is_string( $post[ $name ] ) ? $post[ $name ] : '' ) as $value ) {
				$value = Sunshine_UTM_URL_Builder::format_value( sanitize_text_field( $value ), $format );
				if ( '' !== $value ) {
					$values[] = substr( $value, 0, 100 );
				}
			}
			return array_slice( array_values( array_unique( $values ) ), 0, 200 );
		};

		return array(
			'admin_bar'       => ! empty( $post['admin_bar'] ),
			'row_actions'     => ! empty( $post['row_actions'] ),
			'editor_panel'    => ! empty( $post['editor_panel'] ),
			'post_types'      => $post_types,
			'lowercase'       => ! empty( $post['lowercase'] ),
			'spaces'          => $spaces,
			'strip_symbols'   => ! empty( $post['strip_symbols'] ),
			'check_urls'      => ! empty( $post['check_urls'] ),
			'site_links'      => ! empty( $post['site_links'] ),
			'shortener'       => isset( Sunshine_UTM_Shortener::services()[ $shortener ] ) ? $shortener : 'site',
			'bitly_token'     => isset( $post['bitly_token'] ) ? trim( sanitize_text_field( $post['bitly_token'] ) ) : '',
			'rebrandly_key'   => isset( $post['rebrandly_key'] ) ? trim( sanitize_text_field( $post['rebrandly_key'] ) ) : '',
			'short_prefix'    => Sunshine_UTM_Short_Links::clean_prefix( isset( $post['short_prefix'] ) ? $post['short_prefix'] : '' ),
			'allowed_sources' => $list( 'allowed_sources' ),
			'allowed_mediums' => $list( 'allowed_mediums' ),
			'allowed_only'    => ! empty( $post['allowed_only'] ),
			'delete_data'     => ! empty( $post['delete_data'] ),
		);
	}

	/**
	 * Read the Bulk Builder's fields from a request.
	 *
	 * @param array $post Unslashed POST data.
	 * @return array Input for Sunshine_UTM_Bulk::rows().
	 */
	protected function bulk_input( $post ) {
		$tags       = array();
		$variations = array();
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			$tags[ $key ]       = isset( $post['tags'][ $key ] ) ? (string) $post['tags'][ $key ] : '';
			$variations[ $key ] = isset( $post['variations'][ $key ] ) ? (string) $post['variations'][ $key ] : '';
		}
		// Links, CSV and parameters aren't run through sanitize_text_field, which deletes %XX and
		// breaks encoded links. Each value is checked or encoded where it's used.
		return array(
			'method'     => isset( $post['method'] ) ? sanitize_key( $post['method'] ) : 'urls',
			'urls'       => isset( $post['urls'] ) ? (string) $post['urls'] : '',
			'csv'        => isset( $post['csv'] ) ? (string) $post['csv'] : '',
			'variations' => $variations,
			'networks'   => isset( $post['networks'] ) ? array_map( 'sanitize_key', (array) $post['networks'] ) : array(),
			'preset_id'  => 0,
			'tags'       => $tags,
			'extra'      => Sunshine_UTM_URL_Builder::parse_extra( isset( $post['extra'] ) ? (string) $post['extra'] : '' ),
		);
	}

	protected function check_ajax() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page is out of date. Reload it and try again.', 'sunshine-utm-builder' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'sunshine-utm-builder' ) ), 403 );
		}
	}

	/**
	 * A preset's tags with tokens filled in for the link in the builder.
	 */
	public function ajax_preset() {
		if ( ! check_ajax_referer( Sunshine_UTM::NONCE, 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$preset = Sunshine_UTM_Presets::get( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		if ( ! $preset ) {
			wp_send_json_error( null, 404 );
		}
		$url = isset( $_POST['url'] ) ? (string) wp_unslash( $_POST['url'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only read for tokens.
		wp_send_json_success( Sunshine_UTM_Presets::resolve( $preset, $url ) );
	}

	/**
	 * How many links the Bulk Builder will make, and the first 20 of them. No shortening or link
	 * checks here: those only run when the batch is created.
	 */
	public function ajax_bulk_preview() {
		$this->check_ajax();
		$result = Sunshine_UTM_Bulk::rows( $this->bulk_input( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- sanitized in bulk_input(); nonce checked in check_ajax().
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$links = Sunshine_UTM_Bulk::generate(
			array_slice( $result['rows'], 0, 20 ),
			array(
				'shorten' => false,
				'check'   => false,
			)
		);
		wp_send_json_success(
			array(
				'total'   => count( $result['rows'] ),
				'links'   => $links,
				'skipped' => $result['skipped'],
			)
		);
	}

	public function ajax_bulk_create() {
		$this->check_ajax();
		$post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- sanitized in bulk_input(); nonce checked in check_ajax().
		$input  = $this->bulk_input( $post );
		$result = Sunshine_UTM_Bulk::rows( $input );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$plugin  = sunshine_utm();
		$service = isset( $post['shortener'] ) ? sanitize_key( $post['shortener'] ) : '';
		$shorten = '' !== $service;
		$check   = (bool) $plugin->get_setting( 'check_urls' );
		$pages   = count( array_unique( wp_list_pluck( $result['rows'], 'url' ) ) );

		if ( $shorten && ! Sunshine_UTM_Shortener::is_ready( $service ) ) {
			wp_send_json_error( array( 'message' => __( 'That link shortener isn\'t set up. Check Link shortening in Settings.', 'sunshine-utm-builder' ) ) );
		}
		if ( $shorten && Sunshine_UTM_Shortener::is_outside( $service ) && count( $result['rows'] ) > Sunshine_UTM_Bulk::SHORTEN_MAX ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: Bitly or Rebrandly, 2: the most links it can shorten at once. */
						__( '%1$s works on batches of up to %2$s links. Choose short links on your own site, or make a smaller batch.', 'sunshine-utm-builder' ),
						Sunshine_UTM_Shortener::label( $service ),
						number_format_i18n( Sunshine_UTM_Bulk::SHORTEN_MAX )
					),
				)
			);
		}
		if ( $check && $pages > Sunshine_UTM_Bulk::CHECK_MAX ) {
			$check = false;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- checking up to 50 links can take a few minutes; not allowed on every host.
		}

		$links    = Sunshine_UTM_Bulk::generate(
			$result['rows'],
			array(
				'shorten' => $shorten ? $service : false,
				'check'   => $check,
			)
		);
		$batch_id = Sunshine_UTM_Batches::create(
			array(
				'name'   => isset( $post['name'] ) ? (string) $post['name'] : '',
				'method' => $input['method'],
				'notes'  => isset( $post['notes'] ) ? (string) $post['notes'] : '',
			),
			$links
		);
		if ( ! $batch_id ) {
			wp_send_json_error( array( 'message' => __( 'The batch could not be saved to the database.', 'sunshine-utm-builder' ) ) );
		}

		$message = sprintf(
			/* translators: %s: number of links. */
			_n( '%s link created.', '%s links created.', count( $links ), 'sunshine-utm-builder' ),
			number_format_i18n( count( $links ) )
		);
		if ( $shorten && count( array_filter( wp_list_pluck( $links, 'short_url' ) ) ) < count( $links ) ) {
			$message .= ' ' . __( 'Some links could not be shortened. Check your link shortening settings.', 'sunshine-utm-builder' );
		}
		if ( $plugin->get_setting( 'check_urls' ) && ! $check ) {
			/* translators: %s: the most pages that can be checked at once. */
			$message .= ' ' . sprintf( __( 'Links weren\'t checked, since the batch goes to more than %s different pages.', 'sunshine-utm-builder' ), number_format_i18n( Sunshine_UTM_Bulk::CHECK_MAX ) );
		}
		$broken = count( $links ) - count( array_filter( wp_list_pluck( $links, 'url_ok' ) ) );
		if ( $broken ) {
			/* translators: %s: number of links. */
			$message .= ' ' . sprintf( _n( '%s link goes to a page that didn\'t load. It\'s marked below.', '%s links go to pages that didn\'t load. They\'re marked below.', $broken, 'sunshine-utm-builder' ), number_format_i18n( $broken ) );
		}
		$this->add_notice( $message, $broken ? 'warning' : 'success' );

		wp_send_json_success(
			array(
				'redirect' => self::url(
					array(
						'tab'   => 'batches',
						'batch' => $batch_id,
					)
				),
			)
		);
	}

	public function ajax_link_note() {
		$this->check_ajax();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in check_ajax().
		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
		$note    = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		// phpcs:enable
		if ( ! $link_id || ! Sunshine_UTM_Batches::update_link_note( $link_id, $note ) ) {
			wp_send_json_error( array( 'message' => __( 'The note could not be saved.', 'sunshine-utm-builder' ) ) );
		}
		wp_send_json_success();
	}

	/**
	 * Download a batch as CSV.
	 */
	public function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this.', 'sunshine-utm-builder' ) );
		}
		check_admin_referer( 'sunshine_utm_export' );
		$batch_id = isset( $_GET['batch'] ) ? absint( $_GET['batch'] ) : 0;
		$batch    = Sunshine_UTM_Batches::get( $batch_id );
		if ( ! $batch ) {
			wp_die( esc_html__( 'That batch no longer exists.', 'sunshine-utm-builder' ) );
		}

		$filename = sanitize_file_name( 'utm-links-' . sanitize_title( $batch['name'] ) . '.csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo Sunshine_UTM_Batches::to_csv( Sunshine_UTM_Batches::links( $batch_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV file.
		exit;
	}

	public static function export_url( $batch_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'sunshine_utm_export',
					'batch'  => $batch_id,
				),
				admin_url( 'admin-post.php' )
			),
			'sunshine_utm_export'
		);
	}

	/**
	 * "UTM link" under published posts of the post types chosen in Settings.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	/**
	 * The "UTM link" panel in the block editor's post sidebar (assets/js/editor.js): a button that
	 * opens the Link Builder with this post filled in, and this post's short links with their
	 * clicks. Only for admins, on the post types chosen in Settings, and not in the site editor.
	 */
	public function editor_assets() {
		$plugin = sunshine_utm();
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post   = get_post();
		if ( ! $plugin->get_setting( 'editor_panel' ) || ! $screen || 'post' !== $screen->base || ! $post || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, (array) $plugin->get_setting( 'post_types' ), true ) ) {
			return;
		}
		$links = array();
		foreach ( 'publish' === $post->post_status ? Sunshine_UTM_Short_Links::for_page( get_permalink( $post ) ) : array() as $row ) {
			$links[] = array(
				'short'   => Sunshine_UTM_Short_Links::url( $row['code'] ),
				'clicks'  => (int) $row['clicks'],
				'details' => self::url(
					array(
						'tab'  => 'links',
						'code' => $row['code'],
					)
				),
			);
		}
		wp_enqueue_script( 'sunshine-utm-editor', SUNSHINE_UTM_URL . 'assets/js/editor.js', array( 'wp-plugins', 'wp-element', 'wp-data', 'wp-components', 'wp-edit-post', 'wp-editor' ), SUNSHINE_UTM_VERSION, true );
		wp_localize_script(
			'sunshine-utm-editor',
			'SunshineUTMEditor',
			array(
				'builder' => Sunshine_UTM::builder_url(),
				'links'   => $links,
				'texts'   => array(
					'title'       => __( 'UTM link', 'sunshine-utm-builder' ),
					'build'       => __( 'Build a UTM link', 'sunshine-utm-builder' ),
					'new_tab'     => __( '(opens in a new tab)', 'sunshine-utm-builder' ),
					'unpublished' => __( 'Publish this first, then build tagged links to it.', 'sunshine-utm-builder' ),
					'short_links' => __( 'Short links to this page', 'sunshine-utm-builder' ),
					/* translators: %s: number of clicks. */
					'clicks'      => __( '%s clicks', 'sunshine-utm-builder' ),
					'click'       => __( '1 click', 'sunshine-utm-builder' ),
				),
			)
		);
	}

	public function row_action( $actions, $post ) {
		$plugin = sunshine_utm();
		if ( ! $plugin->get_setting( 'row_actions' ) || 'publish' !== $post->post_status || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		if ( ! in_array( $post->post_type, (array) $plugin->get_setting( 'post_types' ), true ) ) {
			return $actions;
		}
		$permalink = get_permalink( $post );
		if ( $permalink ) {
			$actions['sunshine_utm'] = sprintf(
				'<a href="%1$s" aria-label="%2$s">%3$s</a>',
				esc_url( Sunshine_UTM::builder_url( $permalink ) ),
				/* translators: %s: post title. */
				esc_attr( sprintf( __( 'Build a UTM link for “%s”', 'sunshine-utm-builder' ), get_the_title( $post ) ) ),
				esc_html__( 'UTM link', 'sunshine-utm-builder' )
			);
		}
		return $actions;
	}

	public function add_notice( $message, $type = 'success' ) {
		set_transient( 'sunshine_utm_notice_' . get_current_user_id(), array( $message, $type ), 60 );
	}

	public function notices() {
		if ( ! $this->is_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = get_transient( 'sunshine_utm_notice_' . get_current_user_id() );
		if ( ! $notice ) {
			return;
		}
		delete_transient( 'sunshine_utm_notice_' . get_current_user_id() );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notice[1] ),
			esc_html( $notice[0] )
		);
	}

	/**
	 * Display a stored UTC time in the site's time zone.
	 *
	 * @param string $mysql_utc Y-m-d H:i:s in UTC.
	 * @return string
	 */
	public static function date( $mysql_utc ) {
		$time = strtotime( $mysql_utc . ' UTC' );
		return $time ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) : '';
	}
}
