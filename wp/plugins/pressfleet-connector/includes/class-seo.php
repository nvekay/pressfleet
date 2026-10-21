<?php
/**
 * SEO title and description: written for Yoast SEO or Rank Math when one is active, otherwise output by the plugin.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * SEO fields.
 */
class Seo {

	const TITLE_META       = '_pressfleet_seo_title';
	const DESCRIPTION_META = '_pressfleet_seo_description';

	/**
	 * Which SEO plugin handles the head, if any.
	 *
	 * @return 'yoast'|'rankmath'|null
	 */
	public static function active_plugin(): ?string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		return null;
	}

	/**
	 * Stores the fields where the active SEO plugin reads them, and always in the plugin's own meta.
	 *
	 * @param int    $post_id     Post.
	 * @param string $title       SEO title; empty leaves it unset.
	 * @param string $description Meta description; empty leaves it unset.
	 */
	public function save( int $post_id, string $title, string $description ): void {
		$title       = sanitize_text_field( $title );
		$description = sanitize_textarea_field( $description );
		$keys        = array(
			self::TITLE_META       => $title,
			self::DESCRIPTION_META => $description,
		);
		if ( 'yoast' === self::active_plugin() ) {
			$keys['_yoast_wpseo_title']    = $title;
			$keys['_yoast_wpseo_metadesc'] = $description;
		} elseif ( 'rankmath' === self::active_plugin() ) {
			$keys['rank_math_title']       = $title;
			$keys['rank_math_description'] = $description;
		}
		foreach ( $keys as $key => $value ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Without an SEO plugin: sets the document title of single posts and prints a meta description on singular
	 * pages, the front page and the posts page.
	 */
	public static function register_output(): void {
		add_action(
			'wp',
			static function (): void {
				if ( null !== self::active_plugin() ) {
					return;
				}
				if ( is_singular() ) {
					add_filter( 'pre_get_document_title', array( self::class, 'document_title' ) );
				}
				if ( is_singular() || is_front_page() || is_home() ) {
					add_action( 'wp_head', array( self::class, 'print_description' ), 1 );
				}
			}
		);
	}

	/**
	 * Filter for pre_get_document_title.
	 *
	 * @param string $title Title WordPress would use.
	 */
	public static function document_title( string $title ): string {
		$custom = (string) get_post_meta( (int) get_queried_object_id(), self::TITLE_META, true );
		return '' !== $custom ? $custom : $title;
	}

	/**
	 * Prints the meta description tag.
	 */
	public static function print_description(): void {
		$description = self::description();
		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}
	}

	/**
	 * The hub's SEO description, else the excerpt of a single post, else the site tagline.
	 */
	public static function description(): string {
		if ( is_singular() && ! is_front_page() ) {
			$post_id = (int) get_queried_object_id();
			$custom  = (string) get_post_meta( $post_id, self::DESCRIPTION_META, true );
			return '' !== $custom ? $custom : wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 30, '…' );
		}
		return wp_strip_all_tags( (string) get_bloginfo( 'description' ) );
	}
}
