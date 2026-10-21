<?php
/**
 * Signed requests.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector\Tests;

require_once __DIR__ . '/class-rest-test-case.php';

/**
 * The hub's HMAC signature is required on every route.
 */
class Test_Signature extends Rest_Test_Case {

	public function test_signed_request_passes(): void {
		$this->assertSame( 200, $this->request( 'GET', '/pressfleet/v1/health' )->get_status() );
	}

	public function test_unsigned_request_is_refused(): void {
		$response = $this->request( 'GET', '/pressfleet/v1/health', null, array( 'sign' => false ) );
		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'pressfleet_unsigned', $response->get_data()['code'] );
	}

	public function test_wrong_secret_is_refused(): void {
		$response = $this->request( 'GET', '/pressfleet/v1/health', null, array( 'secret' => 'another-secret' ) );
		$this->assertSame( 'pressfleet_bad_signature', $response->get_data()['code'] );
	}

	public function test_old_signature_is_refused(): void {
		$response = $this->request( 'GET', '/pressfleet/v1/health', null, array( 'time' => time() - 600 ) );
		$this->assertSame( 'pressfleet_expired', $response->get_data()['code'] );
	}

	public function test_signature_covers_the_body(): void {
		$request = new \WP_REST_Request( 'POST', '/pressfleet/v1/posts' );
		$time    = time();
		$signed  = (string) wp_json_encode( $this->payload() );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_header( 'x-pressfleet-timestamp', (string) $time );
		$request->set_header( 'x-pressfleet-signature', \PressFleet\Connector\Signature::sign( self::SECRET, $time, 'POST', '/pressfleet/v1/posts', $signed ) );
		$request->set_body( (string) wp_json_encode( $this->payload( array( 'title' => 'Tampered' ) ) ) );

		$this->assertSame( 'pressfleet_bad_signature', rest_do_request( $request )->get_data()['code'] );
		$this->assertSame( 0, ( new \PressFleet\Connector\Publisher( new \PressFleet\Connector\Media(), new \PressFleet\Connector\Seo() ) )->find( 'row-1' ) );
	}

	public function test_user_who_cannot_publish_is_refused_before_the_signature(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() );
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_forbidden', $response->get_data()['code'] );
	}
}
