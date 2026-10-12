<?php
/**
 * Short links on this site (Sunshine_UTM_Short_Links), or through Bitly or Rebrandly with the site
 * owner's own key. Any service that's set up can be picked for each link. The default from Settings
 * is used when the AI tools or the API don't pick one.
 *
 * Other services can be added with the sunshine_utm_shorten filter: return the short link (or a
 * WP_Error) for the service you handle, and null for anything else.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_Shortener {

	public static function services() {
		return array(
			'site'      => __( 'Your site', 'sunshine-utm-builder' ),
			'bitly'     => 'Bitly',
			'rebrandly' => 'Rebrandly',
		);
	}

	/**
	 * What to call a service in "Shorten with ...": the site's domain for its own short links.
	 *
	 * @param string $service site, bitly or rebrandly.
	 * @return string
	 */
	public static function label( $service ) {
		if ( 'site' === $service ) {
			return (string) wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$services = self::services();
		return isset( $services[ $service ] ) ? $services[ $service ] : '';
	}

	/**
	 * The service is set up: short links on this site are turned on (and permalinks allow them), or
	 * Bitly or Rebrandly has its key.
	 *
	 * @param string $service site, bitly or rebrandly.
	 * @return bool
	 */
	public static function is_ready( $service ) {
		$plugin = sunshine_utm();
		switch ( $service ) {
			case 'site':
				return (bool) $plugin->get_setting( 'site_links' ) && Sunshine_UTM_Short_Links::available();
			case 'bitly':
				return '' !== (string) $plugin->get_setting( 'bitly_token' );
			case 'rebrandly':
				return '' !== (string) $plugin->get_setting( 'rebrandly_key' );
		}
		return false;
	}

	/**
	 * Every service that's set up, in the order they're offered.
	 *
	 * @return array service => label
	 */
	public static function ready() {
		$ready = array();
		foreach ( array_keys( self::services() ) as $service ) {
			if ( self::is_ready( $service ) ) {
				$ready[ $service ] = self::label( $service );
			}
		}
		return $ready;
	}

	/**
	 * The default from Settings when it's set up, otherwise the first one that is, or ''.
	 *
	 * @return string
	 */
	public static function default_service() {
		$saved = (string) sunshine_utm()->get_setting( 'shortener' );
		if ( self::is_ready( $saved ) ) {
			return $saved;
		}
		$ready = array_keys( self::ready() );
		return $ready ? $ready[0] : '';
	}

	/**
	 * Bitly or Rebrandly: one web request per link, and a plan limit.
	 *
	 * @param string $service Service.
	 * @return bool
	 */
	public static function is_outside( $service ) {
		return in_array( $service, array( 'bitly', 'rebrandly' ), true );
	}

	/**
	 * @param string $url     Link to shorten.
	 * @param string $service site, bitly or rebrandly. Defaults to the default from Settings.
	 * @return string|WP_Error Short link.
	 */
	public static function shorten( $url, $service = '' ) {
		$plugin  = sunshine_utm();
		$service = '' === (string) $service ? self::default_service() : (string) $service;

		/**
		 * Shorten with another service.
		 *
		 * @param string|WP_Error|null $short   Short link, or null to let the plugin handle it.
		 * @param string               $url     Link to shorten.
		 * @param string               $service Service chosen in Settings.
		 */
		$short = apply_filters( 'sunshine_utm_shorten', null, $url, $service );
		if ( null !== $short ) {
			return $short;
		}

		if ( '' !== $service && ! self::is_ready( $service ) ) {
			/* translators: %s: Bitly, Rebrandly or "Your site". */
			return new WP_Error( 'not_ready', sprintf( __( '%s isn\'t set up. Check Link shortening in Settings.', 'sunshine-utm-builder' ), self::services()[ $service ] ?? $service ) );
		}

		if ( 'site' === $service ) {
			return Sunshine_UTM_Short_Links::shorten( $url );
		}

		if ( 'bitly' === $service ) {
			$response = wp_remote_post(
				'https://api-ssl.bitly.com/v4/shorten',
				array(
					'timeout' => 6,
					'headers' => array(
						'Authorization' => 'Bearer ' . $plugin->get_setting( 'bitly_token' ),
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( array( 'long_url' => $url ) ),
				)
			);
			return self::read( $response, 'Bitly', 'link' );
		}

		if ( 'rebrandly' === $service ) {
			$response = wp_remote_post(
				'https://api.rebrandly.com/v1/links',
				array(
					'timeout' => 6,
					'headers' => array(
						'apikey'       => $plugin->get_setting( 'rebrandly_key' ),
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode( array( 'destination' => $url ) ),
				)
			);
			$short    = self::read( $response, 'Rebrandly', 'shortUrl' );
			// Rebrandly leaves off the https://.
			return is_wp_error( $short ) || false !== strpos( $short, '://' ) ? $short : 'https://' . $short;
		}

		return new WP_Error( 'no_service', __( 'Choose a link shortener in Settings first.', 'sunshine-utm-builder' ) );
	}

	/**
	 * The short link from a service's reply, or what went wrong in words the owner can act on.
	 *
	 * @param array|WP_Error $response Reply.
	 * @param string         $name     Service name.
	 * @param string         $field    Where the short link is in the reply.
	 * @return string|WP_Error
	 */
	protected static function read( $response, $name, $field ) {
		if ( is_wp_error( $response ) ) {
			/* translators: 1: service name, 2: error. */
			return new WP_Error( 'request', sprintf( __( 'Could not reach %1$s: %2$s', 'sunshine-utm-builder' ), $name, $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 && ! empty( $body[ $field ] ) ) {
			return (string) $body[ $field ];
		}

		if ( 401 === $code || 403 === $code ) {
			/* translators: %s: service name. */
			return new WP_Error( 'auth', sprintf( __( '%s did not accept your key. Check it in Settings.', 'sunshine-utm-builder' ), $name ) );
		}

		$said = '';
		if ( is_array( $body ) ) {
			foreach ( array( 'description', 'message' ) as $key ) {
				if ( ! empty( $body[ $key ] ) && is_string( $body[ $key ] ) ) {
					$said = $body[ $key ];
					break;
				}
			}
		}
		return new WP_Error(
			'service',
			$said
				/* translators: 1: service name, 2: their message. */
				? sprintf( __( '%1$s said: %2$s', 'sunshine-utm-builder' ), $name, $said )
				/* translators: 1: service name, 2: HTTP status code. */
				: sprintf( __( '%1$s could not shorten the link (error %2$d).', 'sunshine-utm-builder' ), $name, $code )
		);
	}
}
