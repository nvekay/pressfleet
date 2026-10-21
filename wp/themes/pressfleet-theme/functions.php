<?php
/**
 * PressFleet Clinic theme setup. Design tokens live in theme.json; this file only registers what theme.json cannot:
 * block style names, the pattern category and a few decorative rules.
 *
 * @package PressFleet\Theme
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/brands.php';

add_action(
	'after_setup_theme',
	static function (): void {
		add_editor_style( 'assets/theme.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$version = wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'pressfleet-theme', get_theme_file_uri( 'assets/theme.css' ), array(), is_string( $version ) ? $version : null );
	}
);

add_action(
	'init',
	static function (): void {
		// Styles are named here; how they look is in theme.json (styles.blocks.*.variations).
		register_block_style(
			'core/group',
			array(
				'name'  => 'card',
				'label' => __( 'Card', 'pressfleet-theme' ),
			)
		);
		register_block_style(
			'core/paragraph',
			array(
				'name'  => 'eyebrow',
				'label' => __( 'Eyebrow', 'pressfleet-theme' ),
			)
		);
		register_block_pattern_category(
			'pressfleet',
			array( 'label' => __( 'Clinic sections', 'pressfleet-theme' ) )
		);
	}
);
