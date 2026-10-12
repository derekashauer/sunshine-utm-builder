<?php
/**
 * Abilities for AI assistants: build one tagged link, build many, and list presets. Registered
 * with the WordPress Abilities API (WordPress 6.9 and later; skipped quietly on older versions).
 *
 * Each ability is marked mcp.public, so the MCP Adapter plugin lets AI tools like Claude find and
 * run them, and show_in_rest, so they can also be run at /wp-json/wp-abilities/v1. Both need an
 * administrator's login (an Application Password works for scripts and AI tools).
 *
 * They use the same code as the screens (Sunshine_UTM_Bulk, the formatting rules from Settings,
 * presets, tokens, the shorteners), so a link an AI makes matches one made by hand. The
 * descriptions are written for the AI reading them: what each field means and what's required.
 *
 * Hooks used:
 * - wp_abilities_api_categories_init: the "UTM links" category.
 * - wp_abilities_api_init: the abilities.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_Abilities {

	const CATEGORY = 'sunshine-utm';

	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register' ) );
	}

	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'UTM links', 'sunshine-utm-builder' ),
				'description' => __( 'Build UTM-tagged links for campaigns, with this site\'s presets and formatting rules.', 'sunshine-utm-builder' ),
			)
		);
	}

	public function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		$permission = function () {
			return current_user_can( 'manage_options' );
		};

		wp_register_ability(
			'sunshine-utm/build-link',
			array(
				'label'               => __( 'Build a UTM link', 'sunshine-utm-builder' ),
				'description'         => __( 'Adds UTM tags to one link so analytics tools can report where visits came from. Tags are cleaned up with the site\'s formatting rules (for example lowercase, spaces to underscores), so use the returned link, not your own version. A source is required. Use a preset (see sunshine-utm/list-presets) to match tags the site already uses. Set shorten to true for a short link for social posts, and shortener to pick which service makes it.', 'sunshine-utm-builder' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'url' ),
					'properties'           => array_merge(
						array(
							'url' => array(
								'type'        => 'string',
								'description' => __( 'The page to link to, like https://example.com/sale/. Any UTM tags already on it are replaced. Its other parameters are kept.', 'sunshine-utm-builder' ),
							),
						),
						self::tag_properties(),
						array(
							'shorten'   => array(
								'type'        => 'boolean',
								'description' => __( 'Also make a short link. Defaults to false.', 'sunshine-utm-builder' ),
							),
							'shortener' => self::shortener_property(),
						)
					),
				),
				'output_schema'       => self::link_schema(),
				'execute_callback'    => array( $this, 'run_build_link' ),
				'permission_callback' => $permission,
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);

		wp_register_ability(
			'sunshine-utm/build-links',
			array(
				'label'               => __( 'Build many UTM links', 'sunshine-utm-builder' ),
				'description'         => __( 'Builds many tagged links at once. method "urls": every link in urls gets the same tags. method "social": every link once per social network in networks, with the network as the source and social as the medium. method "variations": every combination of the values in variations, for every link in urls. method "csv": one row per link from csv text, whose first row names the columns (url required, plus source, medium, campaign, term, content; other columns become extra parameters). Up to 2,000 links. Set save to true to keep them as a batch the site owner can find under Tools, UTM Builder, Saved Batches.', 'sunshine-utm-builder' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array_merge(
						array(
							'method'     => array(
								'type'        => 'string',
								'enum'        => Sunshine_UTM_Bulk::METHODS,
								'description' => __( 'How to make the links. Defaults to urls.', 'sunshine-utm-builder' ),
							),
							'urls'       => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Pages to link to, for the urls, social and variations methods.', 'sunshine-utm-builder' ),
							),
							'networks'   => array(
								'type'        => 'array',
								'items'       => array(
									'type' => 'string',
									'enum' => array_keys( Sunshine_UTM_Bulk::social_networks() ),
								),
								'description' => __( 'Social networks for the social method.', 'sunshine-utm-builder' ),
							),
							'variations' => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => self::variation_properties(),
								'description'          => __( 'For the variations method: a list of values for any tag. Every combination becomes a link. Tags with no list use the shared tag below.', 'sunshine-utm-builder' ),
							),
							'csv'        => array(
								'type'        => 'string',
								'description' => __( 'CSV text for the csv method. Blank cells use the shared tags.', 'sunshine-utm-builder' ),
							),
						),
						self::tag_properties(),
						array(
							'shorten'   => array(
								'type'        => 'boolean',
								'description' => __( 'Also make a short link for every link. Defaults to false.', 'sunshine-utm-builder' ),
							),
							'shortener' => self::shortener_property(),
							'save'      => array(
								'type'        => 'boolean',
								'description' => __( 'Save the links as a batch. Defaults to false.', 'sunshine-utm-builder' ),
							),
							'name'      => array(
								'type'        => 'string',
								'description' => __( 'Batch name, when saving. A dated name is used if empty.', 'sunshine-utm-builder' ),
							),
							'notes'     => array(
								'type'        => 'string',
								'description' => __( 'Batch notes, when saving: where the links will be used, who asked for them.', 'sunshine-utm-builder' ),
							),
						)
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'count'     => array( 'type' => 'integer' ),
						'links'     => array(
							'type'  => 'array',
							'items' => self::link_schema(),
						),
						'skipped'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Lines that were left out, and why.', 'sunshine-utm-builder' ),
						),
						'batch_id'  => array( 'type' => 'integer' ),
						'batch_url' => array(
							'type'        => 'string',
							'description' => __( 'Where the site owner can see the saved batch, when saved.', 'sunshine-utm-builder' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'run_build_links' ),
				'permission_callback' => $permission,
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);

		wp_register_ability(
			'sunshine-utm/list-presets',
			array(
				'label'               => __( 'List UTM presets', 'sunshine-utm-builder' ),
				'description'         => __( 'Lists the saved sets of tags this site uses, like a newsletter or a partner, so new links match them. Pass a preset\'s name or ID to the build abilities. Values may hold tokens ({date}, {date_short}, {slug}, {page_title}, {index}) that are filled in for each link. Also returns the formatting rules the site applies to tags.', 'sunshine-utm-builder' ),
				'category'            => self::CATEGORY,
				// Takes nothing, but some MCP clients send {}, which WordPress refuses without a schema.
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'presets'           => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'       => array( 'type' => 'integer' ),
									'name'     => array( 'type' => 'string' ),
									'source'   => array( 'type' => 'string' ),
									'medium'   => array( 'type' => 'string' ),
									'campaign' => array( 'type' => 'string' ),
									'term'     => array( 'type' => 'string' ),
									'content'  => array( 'type' => 'string' ),
								),
							),
						),
						'formatting'        => array(
							'type'        => 'string',
							'description' => __( 'What the site does to tags, in words.', 'sunshine-utm-builder' ),
						),
						'shorteners'        => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'string' ),
							'description'          => __( 'The link shorteners that are set up, by the value to send as shortener, like {"site": "example.com", "bitly": "Bitly"}. Empty when none are.', 'sunshine-utm-builder' ),
						),
						'default_shortener' => array(
							'type'        => 'string',
							'description' => __( 'The shortener used when shorten is true and no shortener is sent.', 'sunshine-utm-builder' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'list_presets' ),
				'permission_callback' => $permission,
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);
	}

	public function run_build_link( $input ) {
		return self::for_ai( $this->build_link( $input ) );
	}

	public function run_build_links( $input ) {
		return self::for_ai( $this->build_links( $input ) );
	}

	/**
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function build_link( $input ) {
		$input  = (array) $input;
		$error  = self::check_params( $input );
		$preset = $error ? $error : self::preset_id( $input );
		if ( is_wp_error( $preset ) ) {
			return $preset;
		}
		$result = Sunshine_UTM_Bulk::rows(
			array(
				'method'    => 'urls',
				'urls'      => (string) $input['url'],
				'preset_id' => $preset,
				'tags'      => self::tags( $input ),
				'extra'     => self::params( $input ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $result['skipped'] ) {
			return new WP_Error( 'bad_url', __( 'url needs to be a full web address, like https://example.com/page/.', 'sunshine-utm-builder' ) );
		}

		$links = Sunshine_UTM_Bulk::generate( $result['rows'], array( 'check' => false ) );
		$link  = $links[0];
		if ( self::flag( $input, 'shorten' ) ) {
			$short = self::shorten( $link['tagged_url'], self::service( $input ) );
			if ( is_wp_error( $short ) ) {
				return $short;
			}
			$link['short_url'] = $short;
		}
		return self::link_output( $link );
	}

	/**
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public function build_links( $input ) {
		$input  = (array) $input;
		$error  = self::check_params( $input );
		$preset = $error ? $error : self::preset_id( $input );
		if ( is_wp_error( $preset ) ) {
			return $preset;
		}
		$method     = isset( $input['method'] ) ? $input['method'] : 'urls';
		$variations = array();
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			$short = substr( $key, 4 );
			if ( ! empty( $input['variations'][ $short ] ) ) {
				$variations[ $key ] = implode( "\n", array_map( 'strval', (array) $input['variations'][ $short ] ) );
			}
		}
		$result = Sunshine_UTM_Bulk::rows(
			array(
				'method'     => $method,
				'urls'       => implode( "\n", array_map( 'strval', isset( $input['urls'] ) ? (array) $input['urls'] : array() ) ),
				'csv'        => isset( $input['csv'] ) ? (string) $input['csv'] : '',
				'variations' => $variations,
				'networks'   => isset( $input['networks'] ) ? (array) $input['networks'] : array(),
				'preset_id'  => $preset,
				'tags'       => self::tags( $input ),
				'extra'      => self::params( $input ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$plugin  = sunshine_utm();
		$shorten = self::flag( $input, 'shorten' );
		$service = $shorten ? self::ready_service( self::service( $input ) ) : '';
		if ( is_wp_error( $service ) ) {
			return $service;
		}
		if ( $shorten && Sunshine_UTM_Shortener::is_outside( $service ) && count( $result['rows'] ) > Sunshine_UTM_Bulk::SHORTEN_MAX ) {
			return new WP_Error(
				'too_many_to_shorten',
				sprintf(
					/* translators: 1: Bitly or Rebrandly, 2: the most links it can shorten at once. */
					__( '%1$s works on up to %2$s links at a time. Send fewer links, use shortener "site" if it\'s set up, or leave out shorten.', 'sunshine-utm-builder' ),
					Sunshine_UTM_Shortener::label( $service ),
					Sunshine_UTM_Bulk::SHORTEN_MAX
				)
			);
		}
		$save  = self::flag( $input, 'save' );
		$check = $save && $plugin->get_setting( 'check_urls' ) && count( array_unique( wp_list_pluck( $result['rows'], 'url' ) ) ) <= Sunshine_UTM_Bulk::CHECK_MAX;
		$links = Sunshine_UTM_Bulk::generate(
			$result['rows'],
			array(
				'shorten' => $shorten ? $service : false,
				'check'   => $check,
			)
		);

		$output = array(
			'count'     => count( $links ),
			'links'     => array_map( array( __CLASS__, 'link_output' ), $links ),
			'skipped'   => array_values( $result['skipped'] ),
			'batch_id'  => 0,
			'batch_url' => '',
		);
		if ( $save ) {
			$batch_id = Sunshine_UTM_Batches::create(
				array(
					'name'   => isset( $input['name'] ) ? (string) $input['name'] : '',
					'method' => $method,
					'notes'  => isset( $input['notes'] ) ? (string) $input['notes'] : '',
				),
				$links
			);
			if ( ! $batch_id ) {
				return new WP_Error( 'save', __( 'The links were built but the batch could not be saved.', 'sunshine-utm-builder' ) );
			}
			$output['batch_id']  = $batch_id;
			$output['batch_url'] = add_query_arg(
				array(
					'page'  => 'sunshine-utm-builder',
					'tab'   => 'batches',
					'batch' => $batch_id,
				),
				admin_url( 'tools.php' )
			);
		}
		return $output;
	}

	/**
	 * @return array
	 */
	public function list_presets() {
		$presets = array();
		foreach ( Sunshine_UTM_Presets::get_all() as $preset ) {
			$item = array(
				'id'   => (int) $preset['id'],
				'name' => (string) $preset['name'],
			);
			foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
				$item[ substr( $key, 4 ) ] = (string) $preset[ $key ];
			}
			$presets[] = $item;
		}
		$plugin = sunshine_utm();
		return array(
			'presets'           => $presets,
			'formatting'        => $plugin->format_summary() ? $plugin->format_summary() : __( 'Tags are used as typed.', 'sunshine-utm-builder' ),
			'shorteners'        => (object) Sunshine_UTM_Shortener::ready(),
			'default_shortener' => Sunshine_UTM_Shortener::default_service(),
		);
	}

	/**
	 * The five tags, plus preset and params, as schema properties.
	 *
	 * @return array
	 */
	protected static function tag_properties() {
		$properties = array(
			'preset' => array(
				'type'        => array( 'string', 'integer' ),
				'description' => __( 'A preset\'s name or ID. Its tags are used, and any tag you also send replaces the preset\'s value.', 'sunshine-utm-builder' ),
			),
		);
		foreach ( Sunshine_UTM_URL_Builder::fields() as $key => $field ) {
			$properties[ substr( $key, 4 ) ] = array(
				'type'        => 'string',
				/* translators: 1: what the tag is for, 2: example values, 3: utm parameter name. */
				'description' => sprintf( __( '%1$s Examples: %2$s. Becomes %3$s.', 'sunshine-utm-builder' ), $field['help'], $field['example'], $key ) . ( 'utm_source' === $key ? ' ' . __( 'Required, unless a preset or method supplies it.', 'sunshine-utm-builder' ) : '' ),
			);
		}
		$properties['params'] = array(
			'type'                 => 'object',
			'additionalProperties' => array( 'type' => 'string' ),
			'description'          => __( 'Other parameters to add exactly as given, like {"ref": "partner"} or {"utm_id": "summer"}.', 'sunshine-utm-builder' ),
		);
		return $properties;
	}

	protected static function variation_properties() {
		$properties = array();
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			$properties[ substr( $key, 4 ) ] = array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			);
		}
		return $properties;
	}

	protected static function link_schema() {
		$properties = array(
			'url'        => array(
				'type'        => 'string',
				'description' => __( 'The page linked to.', 'sunshine-utm-builder' ),
			),
			'link'       => array(
				'type'        => 'string',
				'description' => __( 'The full tagged link.', 'sunshine-utm-builder' ),
			),
			'short_link' => array(
				'type'        => 'string',
				'description' => __( 'The short link, when one was asked for.', 'sunshine-utm-builder' ),
			),
			'params'     => array(
				'type'                 => 'object',
				'additionalProperties' => array( 'type' => 'string' ),
			),
			'page_loads' => array(
				'type'        => 'boolean',
				'description' => __( 'False when the page was checked and didn\'t load.', 'sunshine-utm-builder' ),
			),
		);
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			$properties[ substr( $key, 4 ) ] = array(
				'type'        => 'string',
				/* translators: %s: utm parameter name. */
				'description' => sprintf( __( 'The %s value in the link, after formatting.', 'sunshine-utm-builder' ), $key ),
			);
		}
		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	/**
	 * A built link in the shape the abilities return.
	 *
	 * @param array $link Link from Sunshine_UTM_Bulk::generate().
	 * @return array
	 */
	public static function link_output( $link ) {
		$output = array(
			'url'        => $link['url'],
			'link'       => $link['tagged_url'],
			'short_link' => (string) $link['short_url'],
			'params'     => (object) array_map( 'strval', (array) $link['extra_params'] ),
			'page_loads' => (bool) $link['url_ok'],
		);
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			$output[ substr( $key, 4 ) ] = $link[ $key ];
		}
		return $output;
	}

	/**
	 * @param array $input Ability input.
	 * @return array utm key => value
	 */
	protected static function tags( $input ) {
		$tags = array();
		foreach ( Sunshine_UTM_URL_Builder::KEYS as $key ) {
			$tags[ $key ] = isset( $input[ substr( $key, 4 ) ] ) ? (string) $input[ substr( $key, 4 ) ] : '';
		}
		return $tags;
	}

	/**
	 * A yes/no field. WordPress's schema check lets "false" through as a boolean but doesn't
	 * convert it, and "false" is not empty, so it's read with rest_sanitize_boolean.
	 *
	 * @param array  $input Ability input.
	 * @param string $key   Field.
	 * @return bool
	 */
	protected static function flag( $input, $key ) {
		return isset( $input[ $key ] ) && rest_sanitize_boolean( $input[ $key ] );
	}

	/**
	 * WordPress counts a list as an object, so ["ref=partner"] would pass the schema and add 0=... to
	 * the link.
	 *
	 * @param array $input Ability input.
	 * @return WP_Error|null
	 */
	protected static function check_params( $input ) {
		if ( ! empty( $input['params'] ) && is_array( $input['params'] ) && wp_is_numeric_array( $input['params'] ) ) {
			return new WP_Error( 'bad_params', __( 'params needs names and values, like {"ref": "partner"}, not a list.', 'sunshine-utm-builder' ) );
		}
		return null;
	}

	protected static function params( $input ) {
		return isset( $input['params'] ) ? array_map( 'strval', (array) $input['params'] ) : array();
	}

	/**
	 * The preset ID for a name or ID, 0 for none, or an error naming the presets that exist.
	 *
	 * @param array $input Ability input.
	 * @return int|WP_Error
	 */
	protected static function preset_id( $input ) {
		if ( ! isset( $input['preset'] ) || '' === (string) $input['preset'] ) {
			return 0;
		}
		$wanted  = (string) $input['preset'];
		$presets = Sunshine_UTM_Presets::get_all();
		foreach ( $presets as $preset ) {
			if ( (string) $preset['id'] === $wanted || 0 === strcasecmp( $preset['name'], $wanted ) ) {
				return (int) $preset['id'];
			}
		}
		return new WP_Error(
			'no_preset',
			$presets
				/* translators: 1: preset asked for, 2: list of preset names. */
				? sprintf( __( 'There\'s no preset called "%1$s". The presets are: %2$s.', 'sunshine-utm-builder' ), $wanted, implode( ', ', wp_list_pluck( $presets, 'name' ) ) )
				/* translators: %s: preset asked for. */
				: sprintf( __( 'There\'s no preset called "%s", and this site has no presets yet.', 'sunshine-utm-builder' ), $wanted )
		);
	}

	/**
	 * Errors as a caller should get them: reworded where the shared bulk code points at the screen,
	 * and marked as a bad request (400) rather than a server error.
	 *
	 * @param array|WP_Error $result Ability result.
	 * @return array|WP_Error
	 */
	protected static function for_ai( $result ) {
		if ( ! is_wp_error( $result ) ) {
			return $result;
		}
		if ( 'no_source' === $result->get_error_code() ) {
			$result = new WP_Error( 'no_source', __( 'Send a source (where the click comes from, like newsletter or facebook), or a preset that has one. Every link needs a source for analytics tools to report it.', 'sunshine-utm-builder' ) );
		}
		$data = $result->get_error_data();
		if ( ! is_array( $data ) || empty( $data['status'] ) ) {
			$result->add_data( array_merge( is_array( $data ) ? $data : array(), array( 'status' => 400 ) ) );
		}
		return $result;
	}

	/**
	 * The shortener input property.
	 *
	 * @return array
	 */
	protected static function shortener_property() {
		return array(
			'type'        => 'string',
			'enum'        => array_keys( Sunshine_UTM_Shortener::services() ),
			'description' => __( 'Which service makes the short link: site (a short link on this site\'s own domain), bitly or rebrandly. Only ones that are set up work (see sunshine-utm/list-presets). Defaults to the site\'s default.', 'sunshine-utm-builder' ),
		);
	}

	protected static function service( $input ) {
		return isset( $input['shortener'] ) ? (string) $input['shortener'] : '';
	}

	/**
	 * The service to shorten with, if it's set up.
	 *
	 * @param string $service Asked for, or '' for the default.
	 * @return string|WP_Error
	 */
	protected static function ready_service( $service ) {
		$service = '' === $service ? Sunshine_UTM_Shortener::default_service() : $service;
		if ( '' === $service ) {
			return new WP_Error( 'no_shortener', __( 'No link shortener is set up on this site. Build the link without shorten, or ask the site owner to turn one on in the plugin\'s settings.', 'sunshine-utm-builder' ) );
		}
		if ( ! Sunshine_UTM_Shortener::is_ready( $service ) ) {
			return new WP_Error(
				'not_ready',
				sprintf(
					/* translators: 1: shortener asked for, 2: the ones that are set up. */
					__( 'The %1$s shortener isn\'t set up on this site. These are: %2$s.', 'sunshine-utm-builder' ),
					$service,
					implode( ', ', array_keys( Sunshine_UTM_Shortener::ready() ) )
				)
			);
		}
		return $service;
	}

	protected static function shorten( $url, $service ) {
		$service = self::ready_service( $service );
		if ( is_wp_error( $service ) ) {
			return $service;
		}
		return Sunshine_UTM_Shortener::shorten( $url, $service );
	}
}
