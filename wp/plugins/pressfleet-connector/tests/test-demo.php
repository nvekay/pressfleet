<?php
/**
 * Demo endpoints: simulated outage and reset.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector\Tests;

use PressFleet\Connector\Publisher;

require_once __DIR__ . '/class-rest-test-case.php';

/**
 * POST /outage and POST /reset.
 */
class Test_Demo extends Rest_Test_Case {

	/**
	 * An administrator for the demo endpoints.
	 *
	 * @var int
	 */
	protected static int $admin;

	public static function wpSetUpBeforeClass( $factory ): void {
		parent::wpSetUpBeforeClass( $factory );
		self::$admin = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function test_an_outage_answers_503_until_it_ends(): void {
		wp_set_current_user( self::$admin );
		$this->assertSame( 200, $this->request( 'POST', '/pressfleet/v1/outage', array( 'seconds' => 60 ) )->get_status() );

		$this->assertSame( 503, $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() )->get_status() );
		$this->assertSame( 503, $this->request( 'GET', '/pressfleet/v1/health' )->get_status() );

		$this->request( 'POST', '/pressfleet/v1/outage', array( 'seconds' => 0 ) );
		$this->assertSame( 201, $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() )->get_status() );
	}

	public function test_an_outage_ends_by_itself(): void {
		update_option( 'pressfleet_outage_until', time() - 1 );
		$this->assertSame( 201, $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() )->get_status() );
	}

	public function test_only_an_administrator_can_start_an_outage(): void {
		$this->assertSame( 403, $this->request( 'POST', '/pressfleet/v1/outage', array( 'seconds' => 60 ) )->get_status() );
		wp_set_current_user( self::$admin );
		$this->assertSame( 400, $this->request( 'POST', '/pressfleet/v1/outage', array( 'seconds' => 3600 ) )->get_status() );
	}

	public function test_reset_exists_only_on_demo_sites_and_deletes_hub_posts(): void {
		wp_set_current_user( self::$admin );
		$this->assertSame( 404, $this->request( 'POST', '/pressfleet/v1/reset' )->get_status() );

		$this->request( 'POST', '/pressfleet/v1/posts', $this->payload() );
		$own = self::factory()->post->create();
		add_filter( 'pressfleet_demo_reset_enabled', '__return_true' );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- re-registers the routes
		do_action( 'rest_api_init' );

		$response = $this->request( 'POST', '/pressfleet/v1/reset' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_data()['posts'] );
		$this->assertSame( 0, ( new Publisher( new \PressFleet\Connector\Media(), new \PressFleet\Connector\Seo() ) )->find( 'row-1' ) );
		$this->assertNotNull( get_post( $own ), 'Posts written on the site itself stay' );
		remove_filter( 'pressfleet_demo_reset_enabled', '__return_true' );
	}
}
