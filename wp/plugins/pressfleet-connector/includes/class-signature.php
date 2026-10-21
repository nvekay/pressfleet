<?php
/**
 * HMAC signatures on requests from the hub, on top of WordPress authentication.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * The hub signs "{timestamp}.{METHOD}.{route}.{body}" with the shared secret (HMAC-SHA256, hex), so a leaked
 * Application Password alone cannot publish, and a captured request cannot be replayed later or elsewhere.
 */
final class Signature {

	const MAX_SKEW = 300;

	/**
	 * Signs a request; the hub does the same in TypeScript.
	 *
	 * @param string $secret    Shared secret.
	 * @param int    $timestamp Unix time of the request.
	 * @param string $method    HTTP method.
	 * @param string $route     REST route, e.g. /pressfleet/v1/posts.
	 * @param string $body      Raw request body.
	 */
	public static function sign( string $secret, int $timestamp, string $method, string $route, string $body ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . strtoupper( $method ) . '.' . $route . '.' . $body, $secret );
	}

	/**
	 * Checks the signature headers of a REST request.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param int|null        $now     Current Unix time (tests pass their own).
	 * @return true|WP_Error
	 */
	public static function verify( WP_REST_Request $request, ?int $now = null ) {
		$secret = Settings::secret();
		if ( '' === $secret ) {
			return new WP_Error( 'pressfleet_no_secret', __( 'The hub secret is not configured on this site.', 'pressfleet-connector' ), array( 'status' => 503 ) );
		}

		$timestamp = (int) $request->get_header( 'x_pressfleet_timestamp' );
		$given     = (string) $request->get_header( 'x_pressfleet_signature' );
		if ( ! $timestamp || '' === $given ) {
			return new WP_Error( 'pressfleet_unsigned', __( 'The request is not signed.', 'pressfleet-connector' ), array( 'status' => 401 ) );
		}
		if ( abs( ( $now ?? time() ) - $timestamp ) > self::MAX_SKEW ) {
			return new WP_Error( 'pressfleet_expired', __( 'The signature has expired.', 'pressfleet-connector' ), array( 'status' => 401 ) );
		}

		$expected = self::sign( $secret, $timestamp, $request->get_method(), $request->get_route(), (string) $request->get_body() );
		if ( ! hash_equals( $expected, $given ) ) {
			return new WP_Error( 'pressfleet_bad_signature', __( 'The signature does not match.', 'pressfleet-connector' ), array( 'status' => 401 ) );
		}
		return true;
	}
}
