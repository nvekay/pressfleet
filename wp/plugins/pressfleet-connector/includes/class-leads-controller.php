<?php
/**
 * Public endpoint the Lead form block posts to: POST /pressfleet/v1/leads.
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
 * Stores the lead, then tries to forward it once right away; retries happen in the background.
 */
final class Leads_Controller {

	/**
	 * Hooked to rest_api_init.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/leads',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				// A public form: spam is handled by Lead_Guard instead of authentication.
				'permission_callback' => '__return_true',
				'args'                => array(
					'name'    => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
						'maxLength' => 100,
					),
					'contact' => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 3,
						'maxLength' => 120,
					),
					'message' => array(
						'type'      => 'string',
						'maxLength' => 2000,
						'default'   => '',
					),
					'page'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'token'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'website' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * POST /leads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$params = $request->get_params();
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$check  = Lead_Guard::check( $params, $ip );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$id = Leads::insert(
			array(
				'name'    => sanitize_text_field( (string) $params['name'] ),
				'contact' => sanitize_text_field( (string) $params['contact'] ),
				'message' => sanitize_textarea_field( (string) $params['message'] ),
				'page'    => esc_url_raw( (string) $params['page'] ),
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'pressfleet_not_saved', __( 'The form could not be saved. Please call us.', 'pressfleet-connector' ), array( 'status' => 500 ) );
		}
		Log::add( Log::IN, 'lead_received', sprintf( 'Lead #%d from the form', $id ) );

		// One quick attempt now; if the hub is slow or down, the retry schedule takes over.
		Lead_Sender::send( $id );
		return new WP_REST_Response( array( 'ok' => true ), 201 );
	}
}
