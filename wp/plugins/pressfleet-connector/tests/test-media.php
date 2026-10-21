<?php
/**
 * Featured images: each image is downloaded and stored once.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector\Tests;

require_once __DIR__ . '/class-rest-test-case.php';

/**
 * Image import without duplicates. HTTP is faked with pre_http_request, so the tests stay offline.
 */
class Test_Media extends Rest_Test_Case {

	/**
	 * URLs that were "downloaded".
	 *
	 * @var string[]
	 */
	private array $downloads = array();

	/**
	 * Fakes image downloads: every URL returns the same small PNG unless it contains "other".
	 */
	public function set_up(): void {
		parent::set_up();
		$this->downloads = array();
		add_filter( 'pre_http_request', array( $this, 'fake_download' ), 10, 3 );
	}

	/**
	 * Removes the fake.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake_download' ) );
		parent::tear_down();
	}

	/**
	 * Writes a PNG where download_url() expects the file.
	 *
	 * @param false|array<string, mixed> $response Short-circuit value.
	 * @param array<string, mixed>       $args     Request arguments.
	 * @param string                     $url      URL.
	 * @return array<string, mixed>
	 */
	public function fake_download( $response, $args, $url ): array {
		$this->downloads[] = $url;
		$image             = imagecreatetruecolor( 4, 4 );
		imagefill( $image, 0, 0, str_contains( $url, 'other' ) ? 0xff0000 : 0x0000ff );
		ob_start();
		imagepng( $image );
		$png = (string) ob_get_clean();
		if ( ! empty( $args['filename'] ) ) {
			file_put_contents( $args['filename'], $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- the HTTP API's stream target
		}
		return array(
			'headers'  => array( 'content-type' => 'image/png' ),
			'body'     => $png,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => $args['filename'] ?? null,
		);
	}

	public function test_imports_the_featured_image(): void {
		$data = $this->send( 'row-1', 'https://cdn.test/cover.png', 'A cover' );

		$thumbnail = get_post_thumbnail_id( $data['id'] );
		$this->assertGreaterThan( 0, $thumbnail );
		$this->assertSame( 'A cover', get_post_meta( $thumbnail, '_wp_attachment_image_alt', true ) );
		$this->assertSame( array(), $data['warnings'] );
	}

	public function test_the_same_url_is_downloaded_once(): void {
		$first  = $this->send( 'row-1', 'https://cdn.test/cover.png' );
		$second = $this->send( 'row-2', 'https://cdn.test/cover.png' );

		$this->assertSame( get_post_thumbnail_id( $first['id'] ), get_post_thumbnail_id( $second['id'] ) );
		$this->assertCount( 1, $this->downloads );
	}

	public function test_the_same_file_under_another_url_is_reused(): void {
		$first  = $this->send( 'row-1', 'https://cdn.test/cover.png' );
		$second = $this->send( 'row-2', 'https://mirror.test/copy-of-cover.png' );

		$this->assertSame( get_post_thumbnail_id( $first['id'] ), get_post_thumbnail_id( $second['id'] ) );
		$this->assertSame( 1, $this->count_attachments() );
	}

	public function test_a_different_image_is_a_new_attachment(): void {
		$this->send( 'row-1', 'https://cdn.test/cover.png' );
		$this->send( 'row-2', 'https://cdn.test/other.png' );

		$this->assertSame( 2, $this->count_attachments() );
	}

	public function test_a_failed_download_publishes_the_post_with_a_warning(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake_download' ) );
		add_filter( 'pre_http_request', static fn() => new \WP_Error( 'http_request_failed', 'Connection refused' ) );

		$data = $this->send( 'row-1', 'https://cdn.test/cover.png' );

		$this->assertSame( 'created', $data['action'] );
		$this->assertSame( 0, get_post_thumbnail_id( $data['id'] ) );
		$this->assertStringContainsString( 'Connection refused', $data['warnings'][0] );
		// Not marked as complete, so the next run retries the image.
		$this->assertSame( '', get_post_meta( $data['id'], '_pressfleet_hash', true ) );
	}

	/**
	 * Publishes an item with a featured image.
	 *
	 * @param string $external_id The hub's ID.
	 * @param string $url         Image URL.
	 * @param string $alt         Alt text.
	 * @return array<string, mixed>
	 */
	private function send( string $external_id, string $url, string $alt = '' ): array {
		return $this->request(
			'POST',
			'/pressfleet/v1/posts',
			$this->payload(
				array(
					'external_id'    => $external_id,
					'featured_image' => array(
						'url' => $url,
						'alt' => $alt,
					),
				)
			)
		)->get_data();
	}

	/**
	 * Attachments in the media library.
	 */
	private function count_attachments(): int {
		return count(
			get_posts(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}
}
