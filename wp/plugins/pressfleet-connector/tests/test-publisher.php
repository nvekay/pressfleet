<?php
/**
 * Publishing through POST /pressfleet/v1/posts.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector\Tests;

use PressFleet\Connector\Log;
use PressFleet\Connector\Publisher;

require_once __DIR__ . '/class-rest-test-case.php';

/**
 * Upsert without duplicates, sanitized content, terms, SEO, scheduling.
 */
class Test_Publisher extends Rest_Test_Case {

	public function test_creates_a_post(): void {
		$response = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'created', $data['action'] );
		$post = get_post( $data['id'] );
		$this->assertSame( 'Five stretches for desk workers', $post->post_title );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 'row-1', get_post_meta( $post->ID, Publisher::EXTERNAL_ID_META, true ) );
	}

	public function test_sending_the_same_item_again_changes_nothing(): void {
		$first  = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() )->get_data();
		$second = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() );

		$this->assertSame( 200, $second->get_status() );
		$this->assertSame( 'unchanged', $second->get_data()['action'] );
		$this->assertSame( $first['id'], $second->get_data()['id'] );
		$this->assertSame( 1, $this->count_posts_from_hub() );
	}

	public function test_a_changed_item_updates_the_same_post(): void {
		$first  = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() )->get_data();
		$second = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload( array( 'title' => 'Six stretches' ) ) )->get_data();

		$this->assertSame( 'updated', $second['action'] );
		$this->assertSame( $first['id'], $second['id'] );
		$this->assertSame( 'Six stretches', get_post( $second['id'] )->post_title );
		$this->assertSame( 1, $this->count_posts_from_hub() );
	}

	public function test_content_is_sanitized(): void {
		$data    = $this->request(
			'POST',
			'/pressfleet/v1/posts',
			$this->payload( array( 'content' => '<p onclick="steal()">Hi</p><script>alert(1)</script><iframe src="https://evil.test"></iframe>' ) )
		)->get_data();
		$content = get_post( $data['id'] )->post_content;

		$this->assertStringNotContainsString( '<script', $content );
		$this->assertStringNotContainsString( 'onclick', $content );
		$this->assertStringNotContainsString( '<iframe', $content );
		$this->assertStringContainsString( '<p>Hi</p>', $content );
	}

	public function test_creates_missing_categories_and_tags(): void {
		$data = $this->request(
			'POST',
			'/pressfleet/v1/posts',
			$this->payload(
				array(
					'categories' => array( 'Guides', 'News' ),
					'tags'       => array( 'posture', 'office' ),
				)
			)
		)->get_data();

		$this->assertEqualsCanonicalizing( array( 'Guides', 'News' ), wp_get_post_categories( $data['id'], array( 'fields' => 'names' ) ) );
		$this->assertEqualsCanonicalizing( array( 'posture', 'office' ), wp_get_post_tags( $data['id'], array( 'fields' => 'names' ) ) );
	}

	public function test_stores_seo_fields(): void {
		$data = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() )->get_data();

		$this->assertSame( 'Desk stretches', get_post_meta( $data['id'], '_pressfleet_seo_title', true ) );
		$this->assertSame( 'Five quick stretches.', get_post_meta( $data['id'], '_pressfleet_seo_description', true ) );
	}

	public function test_a_future_date_schedules_the_post(): void {
		$data = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload( array( 'date' => '2035-01-01T09:00:00Z' ) ) )->get_data();

		$this->assertSame( 'future', $data['status'] );
		$this->assertSame( '2035-01-01 09:00:00', get_post( $data['id'] )->post_date_gmt );
	}

	public function test_invalid_payload_is_rejected_by_the_schema(): void {
		$response = $this->request(
			'POST',
			'/pressfleet/v1/posts',
			array(
				'external_id' => 'has spaces',
				'title'       => 'x',
				'content'     => 'y',
			)
		);
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 0, $this->count_posts_from_hub() );
	}

	public function test_a_concurrent_request_for_the_same_item_waits(): void {
		add_option( 'pressfleet_lock_' . md5( 'row-1' ), time() );
		$response = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 0, $this->count_posts_from_hub() );
	}

	public function test_a_stale_lock_expires(): void {
		add_option( 'pressfleet_lock_' . md5( 'row-1' ), time() - 120 );
		$this->assertSame( 201, $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() )->get_status() );
	}

	public function test_get_finds_the_post_by_external_id(): void {
		$created = $this->request( 'POST', '/pressfleet/v1/posts', $this->payload() )->get_data();
		$found   = $this->request( 'GET', '/pressfleet/v1/posts/row-1' );

		$this->assertSame( 200, $found->get_status() );
		$this->assertSame( $created['id'], $found->get_data()['id'] );
		$this->assertSame( 404, $this->request( 'GET', '/pressfleet/v1/posts/missing' )->get_status() );
	}

	public function test_every_write_is_logged(): void {
		$this->request( 'POST', '/pressfleet/v1/posts', $this->payload() );
		$this->request( 'POST', '/pressfleet/v1/posts', $this->payload() );

		$this->assertSame( 1, Log::count( 'post_created' ) );
		$this->assertSame( 1, Log::count( 'post_unchanged' ) );
	}

	/**
	 * Posts that carry an external ID.
	 */
	private function count_posts_from_hub(): int {
		return count(
			get_posts(
				array(
					'post_type'   => 'post',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
					'meta_key'    => Publisher::EXTERNAL_ID_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- tests
				)
			)
		);
	}
}
