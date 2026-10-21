<?php
/**
 * Featured images from a URL, downloaded once per image.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Media library import with de-duplication: the same URL is never downloaded twice, and the same file under a
 * different URL is reused (matched by its SHA-1).
 */
class Media {

	const URL_META  = '_pressfleet_source_url';
	const HASH_META = '_pressfleet_source_hash';

	/**
	 * Returns the attachment ID for an image URL, importing it if needed.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Post the image belongs to.
	 * @param string $alt     Alt text.
	 * @return int|WP_Error
	 */
	public function import( string $url, int $post_id, string $alt = '' ) {
		$existing = $this->find( self::URL_META, $url );
		if ( $existing ) {
			return $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$hash = (string) sha1_file( $tmp );
		$same = $this->find( self::HASH_META, $hash );
		if ( $same ) {
			wp_delete_file( $tmp );
			add_post_meta( $same, self::URL_META, $url );
			return $same;
		}

		$file = array(
			'name'     => $this->file_name( $url, $tmp ),
			'tmp_name' => $tmp,
		);
		$id   = media_handle_sideload( $file, $post_id );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}

		add_post_meta( $id, self::URL_META, $url );
		add_post_meta( $id, self::HASH_META, $hash, true );
		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}
		return $id;
	}

	/**
	 * Attachment with the given meta value, or 0.
	 *
	 * @param string $key   Meta key.
	 * @param string $value Meta value.
	 */
	private function find( string $key, string $value ): int {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one indexed lookup per imported image
				'meta_query'     => array(
					array(
						'key'   => $key,
						'value' => $value,
					),
				),
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * A file name with an extension WordPress accepts, even for URLs without one.
	 *
	 * @param string $url Image URL.
	 * @param string $tmp Downloaded file.
	 */
	private function file_name( string $url, string $tmp ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name = sanitize_file_name( basename( $path ) );
		if ( preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $name ) ) {
			return $name;
		}
		$type = wp_get_image_mime( $tmp );
		$ext  = $type ? wp_get_default_extension_for_mime_type( $type ) : '';
		if ( ! $ext ) {
			$ext = 'jpg';
		}
		return ( $name ? $name : 'image' ) . '.' . $ext;
	}
}
