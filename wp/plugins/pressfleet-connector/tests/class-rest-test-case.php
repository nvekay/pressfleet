<?php
/**
 * Base class: signed REST requests as an editor.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector\Tests;

use PressFleet\Connector\Settings;
use PressFleet\Connector\Signature;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Helpers for the REST tests.
 */
abstract class Rest_Test_Case extends WP_UnitTestCase {

	const SECRET = 'test-secret-for-the-pressfleet-connector-suite';

	/**
	 * An editor can publish posts.
	 *
	 * @var int
	 */
	protected static int $editor;

	/**
	 * Creates the users once for the class.
	 *
	 * @param \WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::$editor = $factory->user->create( array( 'role' => 'editor' ) );
	}

	/**
	 * Every test starts signed in as the editor, with a known secret.
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( Settings::SECRET, self::SECRET );
		wp_set_current_user( self::$editor );
		do_action( 'rest_api_init' );
	}

	/**
	 * Sends a request through the REST server, signed like the hub signs it.
	 *
	 * @param string                                          $method HTTP method.
	 * @param string                                          $route  Route, e.g. /pressfleet/v1/posts.
	 * @param array<string, mixed>|null                       $body   JSON body.
	 * @param array{sign?: bool, secret?: string, time?: int} $options Signing options.
	 */
	protected function request( string $method, string $route, ?array $body = null, array $options = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$raw     = null === $body ? '' : (string) wp_json_encode( $body );
		if ( '' !== $raw ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( $raw );
		}
		if ( $options['sign'] ?? true ) {
			$time = $options['time'] ?? time();
			$request->set_header( 'x-pressfleet-timestamp', (string) $time );
			$request->set_header( 'x-pressfleet-signature', Signature::sign( $options['secret'] ?? self::SECRET, $time, $method, $route, $raw ) );
		}
		return rest_do_request( $request );
	}

	/**
	 * A valid payload.
	 *
	 * @param array<string, mixed> $overrides Fields to change.
	 * @return array<string, mixed>
	 */
	protected function payload( array $overrides = array() ): array {
		return array_merge(
			array(
				'external_id' => 'row-1',
				'title'       => 'Five stretches for desk workers',
				'content'     => '<p>Sit less, move more.</p>',
				'categories'  => array( 'Guides' ),
				'tags'        => array( 'posture' ),
				'seo'         => array(
					'title'       => 'Desk stretches',
					'description' => 'Five quick stretches.',
				),
			),
			$overrides
		);
	}
}
