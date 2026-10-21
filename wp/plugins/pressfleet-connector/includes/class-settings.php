<?php
/**
 * The plugin's options.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * Option names and accessors.
 */
final class Settings {

	const SECRET  = 'pressfleet_hub_secret';
	const HUB_URL = 'pressfleet_hub_url';

	/**
	 * The shared secret the hub signs requests with.
	 */
	public static function secret(): string {
		return (string) get_option( self::SECRET, '' );
	}

	/**
	 * Generates a secret on first activation, so signing is on from the start.
	 */
	public static function ensure_secret(): void {
		if ( '' === self::secret() ) {
			self::regenerate_secret();
		}
	}

	/**
	 * Replaces the secret; the hub must be given the new one.
	 */
	public static function regenerate_secret(): string {
		$secret = wp_generate_password( 48, false, false );
		update_option( self::SECRET, $secret, false );
		return $secret;
	}

	/**
	 * Where leads from this site are sent (the hub's inbound endpoint), or an empty string.
	 */
	public static function hub_url(): string {
		return (string) get_option( self::HUB_URL, '' );
	}
}
