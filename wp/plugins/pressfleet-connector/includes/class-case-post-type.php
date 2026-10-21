<?php
/**
 * The "Case study" post type: content, so it belongs to a plugin and survives a theme switch.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * Registers pf_case.
 */
final class Case_Post_Type {

	const NAME = 'pf_case';

	/**
	 * Hooked to init.
	 */
	public static function register(): void {
		register_post_type(
			self::NAME,
			array(
				'labels'       => array(
					'name'          => __( 'Case studies', 'pressfleet-connector' ),
					'singular_name' => __( 'Case study', 'pressfleet-connector' ),
					'add_new_item'  => __( 'Add case study', 'pressfleet-connector' ),
					'edit_item'     => __( 'Edit case study', 'pressfleet-connector' ),
					'all_items'     => __( 'All case studies', 'pressfleet-connector' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-portfolio',
				'has_archive'  => 'cases',
				'rewrite'      => array( 'slug' => 'cases' ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			)
		);
	}
}
