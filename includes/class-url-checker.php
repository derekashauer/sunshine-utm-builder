<?php
/**
 * Checks that a page loads before links to it are saved, so a typo doesn't end up in an email or
 * an ad. Turned on in Settings, used by the Bulk Builder.
 *
 * Asks for the headers only (HEAD). Some servers refuse HEAD, so a 405 or 501 gets a normal GET.
 */

defined( 'ABSPATH' ) || exit;

class Sunshine_UTM_URL_Checker {

	/**
	 * @param string $url Page.
	 * @return array {ok: bool, message: string}
	 */
	public static function check( $url ) {
		$args = array(
			'timeout'     => 5,
			'redirection' => 5,
			'user-agent'  => 'Mozilla/5.0 (compatible; Sunshine UTM Builder)',
		);
		// Pages on this site get the same certificate rule WordPress uses when it calls itself (cron,
		// Site Health), so a local dev site's self-signed certificate doesn't mark every link broken.
		// Other sites go through the "safe" requests, which refuse private and internal addresses.
		// Redirects on this site aren't followed, since one could lead anywhere (an off-site short
		// link, another plugin's redirect) without the safe requests' address check. A redirect
		// answer means the page is there, so it counts as loading.
		$own = Sunshine_UTM::is_this_site( $url );
		if ( $own ) {
			$args['sslverify']   = apply_filters( 'https_local_ssl_verify', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter.
			$args['redirection'] = 0;
		}
		$response = $own ? wp_remote_head( $url, $args ) : wp_safe_remote_head( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( 405 === $code || 501 === $code ) {
			$args['limit_response_size'] = 1024;
			$response                    = $own ? wp_remote_get( $url, $args ) : wp_safe_remote_get( $url, $args );
			$code                        = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				/* translators: %s: error message. */
				'message' => sprintf( __( 'Could not load the page: %s', 'sunshine-utm-builder' ), $response->get_error_message() ),
			);
		}
		if ( 404 === $code || 410 === $code ) {
			return array(
				'ok'      => false,
				'message' => __( 'Page not found (404)', 'sunshine-utm-builder' ),
			);
		}
		// Bot protection (Cloudflare and the like) often answers checks like this one with 401, 403 or
		// 429 while the page works fine in a browser, so those aren't treated as broken.
		if ( $code >= 400 && ! in_array( $code, array( 401, 403, 429 ), true ) ) {
			return array(
				'ok'      => false,
				/* translators: %d: HTTP status code. */
				'message' => sprintf( __( 'The page returned an error (%d)', 'sunshine-utm-builder' ), $code ),
			);
		}
		return array(
			'ok'      => true,
			'message' => '',
		);
	}
}
