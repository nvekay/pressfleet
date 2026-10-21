<?php
/**
 * The Lead form endpoint, spam protection and forwarding to the hub.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector\Tests;

use PressFleet\Connector\Lead_Guard;
use PressFleet\Connector\Lead_Sender;
use PressFleet\Connector\Leads;
use PressFleet\Connector\Settings;
use PressFleet\Connector\Signature;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * POST /pressfleet/v1/leads and Lead_Sender. HTTP to the hub is faked with pre_http_request.
 */
class Test_Leads extends WP_UnitTestCase {

	const HUB = 'https://hub.test/leads';

	/**
	 * Requests the fake hub received.
	 *
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	private array $sent = array();

	/**
	 * What the fake hub answers: an HTTP status or a WP_Error.
	 *
	 * @var int|\WP_Error
	 */
	private $hub_answer = 200;

	public function set_up(): void {
		parent::set_up();
		update_option( Settings::SECRET, 'lead-test-secret-0123456789' );
		update_option( Settings::HUB_URL, self::HUB );
		$this->sent             = array();
		$this->hub_answer       = 200;
		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 250 );
		add_filter( 'pre_http_request', array( $this, 'fake_hub' ), 10, 3 );
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake_hub' ) );
		wp_clear_scheduled_hook( Lead_Sender::HOOK );
		parent::tear_down();
	}

	/**
	 * Records the request and answers as configured.
	 *
	 * @param false|array<string, mixed> $response Short-circuit value.
	 * @param array<string, mixed>       $args     Request arguments.
	 * @param string                     $url      URL.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function fake_hub( $response, $args, $url ) {
		$this->sent[] = array(
			'url'  => $url,
			'args' => $args,
		);
		if ( is_wp_error( $this->hub_answer ) ) {
			return $this->hub_answer;
		}
		return array(
			'headers'  => array(),
			'body'     => '{"duplicate":false}',
			'response' => array(
				'code'    => $this->hub_answer,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	public function test_a_valid_lead_is_stored_sanitized_and_forwarded_signed(): void {
		$response = $this->submit( array( 'name' => 'Ana <b>Silva</b>' ) );

		$this->assertSame( 201, $response->get_status() );
		$lead = Leads::latest( 1 )[0];
		$this->assertSame( 'Ana Silva', $lead->name );
		$this->assertSame( 'sent', $lead->status );

		$this->assertCount( 1, $this->sent );
		$request = $this->sent[0]['args'];
		$this->assertSame( self::HUB, $this->sent[0]['url'] );
		$this->assertSame( home_url(), $request['headers']['X-PressFleet-Site'] );
		$this->assertSame(
			Signature::sign( 'lead-test-secret-0123456789', (int) $request['headers']['X-PressFleet-Timestamp'], 'POST', '/leads', $request['body'] ),
			$request['headers']['X-PressFleet-Signature']
		);
		$this->assertSame( (int) $lead->id, json_decode( $request['body'], true )['id'] );
	}

	public function test_the_honeypot_rejects_bots(): void {
		$this->assertSame( 400, $this->submit( array( 'website' => 'https://spam.test' ) )->get_status() );
		$this->assertSame( 0, Leads::count() );
	}

	public function test_a_forged_or_instant_token_is_rejected(): void {
		$this->assertSame( 400, $this->submit( array( 'token' => time() - 60 . '.forged' ) )->get_status() );
		$this->assertSame( 400, $this->submit( array( 'token' => Lead_Guard::form_token( time() ) ) )->get_status() );
		$this->assertSame( 0, Leads::count() );
	}

	public function test_an_old_form_asks_for_a_reload(): void {
		$response = $this->submit( array( 'token' => Lead_Guard::form_token( time() - 3 * DAY_IN_SECONDS ) ) );
		$this->assertSame( 'pressfleet_expired_form', $response->get_data()['code'] );
	}

	public function test_one_address_is_limited_per_hour(): void {
		for ( $i = 0; $i < Lead_Guard::PER_IP_HOUR; $i++ ) {
			$this->assertSame( 201, $this->submit()->get_status() );
		}
		$this->assertSame( 429, $this->submit()->get_status() );
	}

	public function test_a_hub_that_is_down_gets_retried_later(): void {
		$this->hub_answer = new \WP_Error( 'http_request_failed', 'Connection refused' );
		$this->assertSame( 201, $this->submit()->get_status() );

		$lead = Leads::latest( 1 )[0];
		$this->assertSame( 'pending', $lead->status );
		$this->assertSame( 'Connection refused', $lead->last_error );
		$next = wp_next_scheduled( Lead_Sender::HOOK, array( (int) $lead->id ) );
		$this->assertGreaterThanOrEqual( time() + MINUTE_IN_SECONDS - 5, $next );
	}

	public function test_a_lead_fails_after_the_last_retry_and_can_be_sent_again(): void {
		$this->hub_answer = 503;
		$this->submit();
		$id       = (int) Leads::latest( 1 )[0]->id;
		$attempts = count( Lead_Sender::DELAYS );
		for ( $i = 0; $i < $attempts; $i++ ) {
			Lead_Sender::send( $id );
		}
		$this->assertSame( 'failed', Leads::get( $id )->status );
		$this->assertSame( 'Hub answered 503', Leads::get( $id )->last_error );

		$this->hub_answer = 200;
		$this->assertSame( array( $id ), Leads::unsent_ids() );
		$this->assertTrue( Lead_Sender::send( $id ) );
		$this->assertSame( 'sent', Leads::get( $id )->status );
	}

	public function test_without_a_hub_the_lead_stays_on_the_site(): void {
		update_option( Settings::HUB_URL, '' );
		$this->assertSame( 201, $this->submit()->get_status() );
		$this->assertSame( 'stored', Leads::latest( 1 )[0]->status );
		$this->assertCount( 0, $this->sent );
	}

	/**
	 * Posts the form like the block's script does.
	 *
	 * @param array<string, string> $fields Fields to change.
	 */
	private function submit( array $fields = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/pressfleet/v1/leads' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array_merge(
					array(
						'name'    => 'Ana Silva',
						'contact' => 'ana@example.test',
						'message' => 'Back pain after a long flight.',
						'page'    => home_url( '/' ),
						'token'   => Lead_Guard::form_token( time() - 30 ),
						'website' => '',
					),
					$fields
				)
			)
		);
		return rest_do_request( $request );
	}
}
