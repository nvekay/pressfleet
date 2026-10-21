<?php
/**
 * Creates or updates a post from the hub's payload, idempotently.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Upsert by external ID.
 *
 * The hub may send the same item again (a retry after a timeout, a re-run campaign). The post is found by its
 * external ID, a content hash skips writes when nothing changed, and a short lock stops two concurrent requests
 * for the same item from creating two posts.
 *
 * @phpstan-type Payload array{
 *     external_id: string,
 *     title: string,
 *     content: string,
 *     excerpt?: string,
 *     slug?: string,
 *     status?: string,
 *     date?: string,
 *     categories?: list<string>,
 *     tags?: list<string>,
 *     featured_image?: array{url: string, alt?: string}|null,
 *     seo?: array{title?: string, description?: string}
 * }
 * @phpstan-type Result array{id: int, external_id: string, action: 'created'|'updated'|'unchanged', status: string, link: string, warnings: list<string>}
 */
class Publisher {

	const EXTERNAL_ID_META = '_pressfleet_external_id';
	const HASH_META        = '_pressfleet_hash';
	const LOCK_TTL         = 60;

	/**
	 * Media importer.
	 *
	 * @var Media
	 */
	private Media $media;

	/**
	 * SEO writer.
	 *
	 * @var Seo
	 */
	private Seo $seo;

	/**
	 * Constructor.
	 *
	 * @param Media $media Media importer.
	 * @param Seo   $seo   SEO writer.
	 */
	public function __construct( Media $media, Seo $seo ) {
		$this->media = $media;
		$this->seo   = $seo;
	}

	/**
	 * Creates the post, updates it, or leaves it alone when nothing changed.
	 *
	 * @param array<string, mixed> $data Validated payload.
	 * @phpstan-param Payload $data
	 * @return array<string, mixed>|WP_Error
	 * @phpstan-return Result|WP_Error
	 */
	public function upsert( array $data ) {
		$external_id = $data['external_id'];
		if ( ! $this->lock( $external_id ) ) {
			return new WP_Error( 'pressfleet_busy', __( 'This item is being published by another request.', 'pressfleet-connector' ), array( 'status' => 409 ) );
		}

		try {
			return $this->write( $data );
		} finally {
			$this->unlock( $external_id );
		}
	}

	/**
	 * Post with the given external ID, in any status, or 0.
	 *
	 * @param string $external_id The hub's ID.
	 */
	public function find( string $external_id ): int {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any,trash',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one lookup per published item
				'meta_query'     => array(
					array(
						'key'   => self::EXTERNAL_ID_META,
						'value' => $external_id,
					),
				),
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Does the actual work while the lock is held.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @phpstan-param Payload $data
	 * @return array<string, mixed>|WP_Error
	 * @phpstan-return Result|WP_Error
	 */
	private function write( array $data ) {
		$external_id = $data['external_id'];
		$existing    = $this->find( $external_id );
		$hash        = $this->hash( $data );

		if ( $existing && get_post_meta( $existing, self::HASH_META, true ) === $hash ) {
			Log::add( Log::IN, 'post_unchanged', sprintf( 'Unchanged: %s', $data['title'] ), $external_id, $existing );
			return $this->result( $existing, $external_id, 'unchanged', array() );
		}

		$postarr = array(
			'post_type'    => 'post',
			'post_title'   => sanitize_text_field( $data['title'] ),
			'post_content' => wp_kses_post( $data['content'] ),
			'post_excerpt' => sanitize_textarea_field( $data['excerpt'] ?? '' ),
			'post_status'  => $this->status( $data['status'] ?? 'publish' ),
			'meta_input'   => array( self::EXTERNAL_ID_META => $external_id ),
		);
		if ( ! empty( $data['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $data['slug'] );
		}
		if ( ! empty( $data['date'] ) ) {
			$gmt                      = gmdate( 'Y-m-d H:i:s', (int) strtotime( $data['date'] ) );
			$postarr['post_date_gmt'] = $gmt;
			$postarr['post_date']     = get_date_from_gmt( $gmt );
			$postarr['edit_date']     = true;
		}
		if ( $existing ) {
			$postarr['ID'] = $existing;
		}

		// A future date with status "publish" becomes a scheduled post (status "future") in wp_insert_post.
		$post_id = $existing ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			Log::add( Log::IN, 'post_failed', sprintf( 'Failed: %s (%s)', $data['title'], $post_id->get_error_message() ), $external_id );
			return $post_id;
		}

		$warnings = array();
		wp_set_post_categories( $post_id, $this->category_ids( $data['categories'] ?? array() ) );
		wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $data['tags'] ?? array() ) );

		$image = $data['featured_image'] ?? null;
		if ( $image && ! empty( $image['url'] ) ) {
			$attachment = $this->media->import( $image['url'], $post_id, $image['alt'] ?? '' );
			if ( is_wp_error( $attachment ) ) {
				// The post is still worth publishing; the hub shows the warning.
				$warnings[] = sprintf( 'Featured image not imported: %s', $attachment->get_error_message() );
			} else {
				set_post_thumbnail( $post_id, $attachment );
			}
		} elseif ( $existing ) {
			delete_post_thumbnail( $post_id );
		}

		$this->seo->save( $post_id, $data['seo']['title'] ?? '', $data['seo']['description'] ?? '' );
		// Only a complete write counts: a failed image is retried on the next run.
		update_post_meta( $post_id, self::HASH_META, $warnings ? '' : $hash );

		$action = $existing ? 'updated' : 'created';
		Log::add(
			Log::IN,
			'post_' . $action,
			sprintf( '%s: %s', ucfirst( $action ), $data['title'] ),
			$external_id,
			$post_id,
			$warnings ? array( 'warnings' => $warnings ) : array()
		);
		return $this->result( $post_id, $external_id, $action, $warnings );
	}

	/**
	 * Response for the hub.
	 *
	 * @param int      $post_id     Post.
	 * @param string   $external_id The hub's ID.
	 * @param string   $action      What happened.
	 * @param string[] $warnings    Non-fatal problems.
	 * @phpstan-param list<string> $warnings
	 * @phpstan-param 'created'|'updated'|'unchanged' $action
	 * @return array<string, mixed>
	 * @phpstan-return Result
	 */
	private function result( int $post_id, string $external_id, string $action, array $warnings ): array {
		return array(
			'id'          => $post_id,
			'external_id' => $external_id,
			'action'      => $action,
			'status'      => (string) get_post_status( $post_id ),
			'link'        => (string) get_permalink( $post_id ),
			'warnings'    => $warnings,
		);
	}

	/**
	 * Hash of everything that ends up in the post.
	 *
	 * @param array<string, mixed> $data Payload.
	 */
	private function hash( array $data ): string {
		ksort( $data );
		return sha1( (string) wp_json_encode( $data ) );
	}

	/**
	 * Allowed statuses; anything else publishes.
	 *
	 * @param string $status Requested status.
	 */
	private function status( string $status ): string {
		return in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ? $status : 'publish';
	}

	/**
	 * Category IDs for names, creating missing categories.
	 *
	 * @param string[] $names Category names.
	 * @return list<int>
	 */
	private function category_ids( array $names ): array {
		$ids = array();
		foreach ( $names as $name ) {
			$name = sanitize_text_field( $name );
			if ( '' === $name ) {
				continue;
			}
			$term = term_exists( $name, 'category' );
			if ( ! $term ) {
				$term = wp_insert_term( $name, 'category' );
			}
			if ( is_array( $term ) ) {
				$ids[] = (int) $term['term_id'];
			}
		}
		return $ids;
	}

	/**
	 * Takes a per-item lock; add_option fails if the row already exists, which makes it atomic.
	 *
	 * @param string $external_id The hub's ID.
	 */
	private function lock( string $external_id ): bool {
		$name = $this->lock_name( $external_id );
		if ( add_option( $name, time(), '', false ) ) {
			return true;
		}
		// A lock left behind by a crashed request expires.
		if ( time() - (int) get_option( $name, 0 ) > self::LOCK_TTL ) {
			delete_option( $name );
			return add_option( $name, time(), '', false );
		}
		return false;
	}

	/**
	 * Releases the lock.
	 *
	 * @param string $external_id The hub's ID.
	 */
	private function unlock( string $external_id ): void {
		delete_option( $this->lock_name( $external_id ) );
	}

	/**
	 * Option name of an item's lock.
	 *
	 * @param string $external_id The hub's ID.
	 */
	private function lock_name( string $external_id ): string {
		return 'pressfleet_lock_' . md5( $external_id );
	}
}
