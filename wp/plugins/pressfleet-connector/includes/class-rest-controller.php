<?php
/**
 * REST API for the hub: pressfleet/v1.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes: POST /posts (upsert), GET /posts/{external_id}, GET /health.
 *
 * Every route needs a WordPress user (an Application Password) with the right capability and a valid HMAC
 * signature from the hub.
 */
final class Rest_Controller {

	const NAMESPACE = 'pressfleet/v1';

	/**
	 * Publisher.
	 *
	 * @var Publisher
	 */
	private Publisher $publisher;

	/**
	 * Constructor.
	 *
	 * @param Publisher $publisher Publisher.
	 */
	public function __construct( Publisher $publisher ) {
		$this->publisher = $publisher;
	}

	/**
	 * Hooked to rest_api_init.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upsert' ),
				'permission_callback' => $this->allow( 'publish_posts' ),
				'args'                => $this->post_args(),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<external_id>[A-Za-z0-9._:-]{1,100})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get' ),
				'permission_callback' => $this->allow( 'edit_posts' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health' ),
				'permission_callback' => $this->allow( 'edit_posts' ),
			)
		);
	}

	/**
	 * POST /posts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upsert( WP_REST_Request $request ) {
		$fields = array( 'external_id', 'title', 'content', 'excerpt', 'slug', 'status', 'date', 'categories', 'tags', 'featured_image', 'seo' );
		$data   = array();
		foreach ( $fields as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}
		/**
		 * Validated by the route's argument schema.
		 *
		 * @var array{external_id: string, title: string, content: string} $data
		 */
		$result = $this->publisher->upsert( $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 'created' === $result['action'] ? 201 : 200 );
	}

	/**
	 * GET /posts/{external_id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get( WP_REST_Request $request ) {
		$external_id = (string) $request->get_param( 'external_id' );
		$post_id     = $this->publisher->find( $external_id );
		if ( ! $post_id ) {
			return new WP_Error( 'pressfleet_not_found', __( 'No post with this external ID.', 'pressfleet-connector' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response(
			array(
				'id'          => $post_id,
				'external_id' => $external_id,
				'status'      => get_post_status( $post_id ),
				'link'        => get_permalink( $post_id ),
				'modified'    => get_post_modified_time( 'c', true, $post_id ),
			)
		);
	}

	/**
	 * GET /health: lets the hub test the connection and show the site's state.
	 */
	public function health(): WP_REST_Response {
		$posts = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a count for the health check
				'meta_key'       => Publisher::EXTERNAL_ID_META,
			)
		);
		return new WP_REST_Response(
			array(
				'site'             => get_bloginfo( 'name' ),
				'url'              => home_url( '/' ),
				'plugin_version'   => VERSION,
				'wordpress'        => get_bloginfo( 'version' ),
				'php'              => PHP_VERSION,
				'seo_plugin'       => Seo::active_plugin(),
				'posts_from_hub'   => (int) $posts->found_posts,
				'last_received_at' => Log::last_received_at(),
				'time'             => gmdate( 'c' ),
			)
		);
	}

	/**
	 * Permission check: the capability, then the signature.
	 *
	 * @param string $capability Required capability.
	 * @return callable(WP_REST_Request<array<string, mixed>>): (bool|WP_Error)
	 */
	private function allow( string $capability ): callable {
		return static function ( WP_REST_Request $request ) use ( $capability ) {
			if ( ! current_user_can( $capability ) ) {
				return new WP_Error(
					'rest_forbidden',
					__( 'Authenticate with an Application Password of a user who can publish posts.', 'pressfleet-connector' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}
			return Signature::verify( $request );
		};
	}

	/**
	 * Argument schema for POST /posts; WordPress validates and sanitizes before the callback runs.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function post_args(): array {
		$string_list = array(
			'type'     => 'array',
			'items'    => array(
				'type'      => 'string',
				'maxLength' => 200,
			),
			'maxItems' => 20,
			'default'  => array(),
		);
		return array(
			'external_id'    => array(
				'type'     => 'string',
				'required' => true,
				'pattern'  => '^[A-Za-z0-9._:-]{1,100}$',
			),
			'title'          => array(
				'type'      => 'string',
				'required'  => true,
				'minLength' => 1,
				'maxLength' => 300,
			),
			'content'        => array(
				'type'     => 'string',
				'required' => true,
			),
			'excerpt'        => array( 'type' => 'string' ),
			'slug'           => array(
				'type'      => 'string',
				'maxLength' => 200,
			),
			'status'         => array(
				'type'    => 'string',
				'enum'    => array( 'publish', 'draft', 'pending', 'private' ),
				'default' => 'publish',
			),
			'date'           => array(
				'type'   => 'string',
				'format' => 'date-time',
			),
			'categories'     => $string_list,
			'tags'           => $string_list,
			'featured_image' => array(
				'type'       => array( 'object', 'null' ),
				'properties' => array(
					'url' => array(
						'type'     => 'string',
						'format'   => 'uri',
						'required' => true,
					),
					'alt' => array( 'type' => 'string' ),
				),
			),
			'seo'            => array(
				'type'       => 'object',
				'properties' => array(
					'title'       => array(
						'type'      => 'string',
						'maxLength' => 200,
					),
					'description' => array(
						'type'      => 'string',
						'maxLength' => 400,
					),
				),
				'default'    => array(),
			),
		);
	}
}
