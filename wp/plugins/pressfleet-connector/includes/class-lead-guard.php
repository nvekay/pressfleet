<?php
/**
 * Spam protection for the public lead endpoint, without a CAPTCHA.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Three cheap checks: a honeypot field people never see, a signed form token that must be at least a few seconds
 * old (bots submit instantly) and not older than two days, and a per-IP limit.
 */
final class Lead_Guard {

	const MIN_AGE      = 3;
	const MAX_AGE      = 2 * DAY_IN_SECONDS;
	const PER_IP_HOUR  = 5;
	const TOKEN_SCHEME = 'pressfleet-lead-form';

	/**
	 * "{time}.{signature}" placed in the rendered form.
	 *
	 * @param int|null $now Unix time (tests pass their own).
	 */
	public static function form_token( ?int $now = null ): string {
		$time = $now ?? time();
		return $time . '.' . self::sign( $time );
	}

	/**
	 * Checks a submission.
	 *
	 * @param array<string, mixed> $data Submitted fields.
	 * @param string               $ip   Visitor's IP address.
	 * @param int|null             $now  Unix time (tests pass their own).
	 * @return true|WP_Error
	 */
	public static function check( array $data, string $ip, ?int $now = null ) {
		$now = $now ?? time();
		if ( ! empty( $data['website'] ) ) {
			return self::refuse( 'pressfleet_spam', 400 );
		}

		$parts = explode( '.', (string) ( $data['token'] ?? '' ), 2 );
		$time  = (int) $parts[0];
		if ( 2 !== count( $parts ) || ! hash_equals( self::sign( $time ), $parts[1] ) ) {
			return self::refuse( 'pressfleet_bad_token', 400 );
		}
		if ( $now - $time < self::MIN_AGE ) {
			return self::refuse( 'pressfleet_too_fast', 400 );
		}
		if ( $now - $time > self::MAX_AGE ) {
			return new WP_Error( 'pressfleet_expired_form', __( 'The page is out of date. Please reload it and send the form again.', 'pressfleet-connector' ), array( 'status' => 400 ) );
		}

		$key   = 'pressfleet_leads_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::PER_IP_HOUR ) {
			return new WP_Error( 'pressfleet_rate_limited', __( 'Too many requests. Please try again later or call us.', 'pressfleet-connector' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Same message for every spam signal, so bots learn nothing.
	 *
	 * @param string $code   Error code.
	 * @param int    $status HTTP status.
	 */
	private static function refuse( string $code, int $status ): WP_Error {
		return new WP_Error( $code, __( 'The form could not be sent. Please reload the page and try again.', 'pressfleet-connector' ), array( 'status' => $status ) );
	}

	/**
	 * Signature of a form time with a key of this site.
	 *
	 * @param int $time Unix time.
	 */
	private static function sign( int $time ): string {
		return substr( hash_hmac( 'sha256', (string) $time, wp_salt( self::TOKEN_SCHEME ) ), 0, 32 );
	}
}
