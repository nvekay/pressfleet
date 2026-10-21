<?php
/**
 * Endpoints for the public demo: a simulated outage and a nightly reset.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * POST /outage {seconds}: the publishing endpoints answer 503 for a while, like a host that went down, so demo
 * visitors can watch the hub retry. POST /reset: deletes everything the hub published, so each day of the demo
 * starts with "created" again. Both need the hub's signature and an administrator's Application Password; reset
 * also needs PRESSFLEET_DEMO set to true in wp-config.php.
 */
final class Demo_Controller {

	const OUTAGE      = 'pressfleet_outage_until';
	const MAX_SECONDS = 300;

	/**
	 * Hooked to rest_api_init.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/outage',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'outage' ),
				'permission_callback' => array( $this, 'allow' ),
				'args'                => array(
					'seconds' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 0,
						'maximum'  => self::MAX_SECONDS,
					),
				),
			)
		);
		if ( self::reset_enabled() ) {
			register_rest_route(
				Rest_Controller::NAMESPACE,
				'/reset',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset' ),
					'permission_callback' => array( $this, 'allow' ),
				)
			);
		}
	}

	/**
	 * Administrators only, with the hub's signature.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public function allow( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Only an administrator can do this.', 'pressfleet-connector' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return Signature::verify( $request );
	}

	/**
	 * Starts (or with 0 seconds, ends) a simulated outage.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function outage( WP_REST_Request $request ): WP_REST_Response {
		$seconds = (int) $request->get_param( 'seconds' );
		if ( $seconds > 0 ) {
			update_option( self::OUTAGE, time() + $seconds, false );
			Log::add( Log::IN, 'outage_started', sprintf( 'Simulated outage for %d seconds', $seconds ) );
		} else {
			delete_option( self::OUTAGE );
			Log::add( Log::IN, 'outage_ended', 'Simulated outage ended' );
		}
		return new WP_REST_Response( array( 'offline_until' => $seconds > 0 ? gmdate( 'c', time() + $seconds ) : null ) );
	}

	/**
	 * Whether a simulated outage is running.
	 */
	public static function offline(): bool {
		return (int) get_option( self::OUTAGE, 0 ) > time();
	}

	/**
	 * Answers 503 on publishing routes during a simulated outage (hooked to rest_pre_dispatch).
	 *
	 * @param mixed           $result  Response so far.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return mixed
	 */
	public static function block_during_outage( $result, $server, $request ) {
		$route = $request->get_route();
		$down  = str_starts_with( $route, '/' . Rest_Controller::NAMESPACE . '/posts' ) || '/' . Rest_Controller::NAMESPACE . '/health' === $route;
		if ( null === $result && $down && self::offline() ) {
			return new WP_Error( 'pressfleet_offline', __( 'The site is offline (an outage simulated by the demo).', 'pressfleet-connector' ), array( 'status' => 503 ) );
		}
		return $result;
	}

	/**
	 * Deletes every post the hub published, with its imported images, and empties the log.
	 */
	public function reset(): WP_REST_Response {
		$posts  = get_posts(
			array(
				'post_type'   => 'post',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a nightly demo reset
				'meta_key'    => Publisher::EXTERNAL_ID_META,
			)
		);
		$images = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => -1,
				'fields'      => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a nightly demo reset
				'meta_key'    => Media::URL_META,
			)
		);
		foreach ( $posts as $id ) {
			wp_delete_post( (int) $id, true );
		}
		foreach ( $images as $id ) {
			wp_delete_attachment( (int) $id, true );
		}
		Log::clear();
		delete_option( self::OUTAGE );
		Log::add( Log::IN, 'demo_reset', sprintf( 'Demo reset: %d posts and %d images deleted', count( $posts ), count( $images ) ) );
		return new WP_REST_Response(
			array(
				'posts'  => count( $posts ),
				'images' => count( $images ),
			)
		);
	}

	/**
	 * The reset route exists only on demo sites (PRESSFLEET_DEMO in wp-config.php, or the filter in tests).
	 */
	public static function reset_enabled(): bool {
		return (bool) apply_filters( 'pressfleet_demo_reset_enabled', defined( 'PRESSFLEET_DEMO' ) && PRESSFLEET_DEMO );
	}
}
