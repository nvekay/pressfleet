<?php
/**
 * Wires the plugin's parts to WordPress hooks.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
final class Plugin {

	/**
	 * Registers every hook once WordPress has loaded the plugins.
	 */
	public static function boot(): void {
		Installer::maybe_upgrade();

		add_action( 'init', array( Case_Post_Type::class, 'register' ) );
		add_action( 'rest_api_init', array( new Rest_Controller( new Publisher( new Media(), new Seo() ) ), 'register_routes' ) );
		add_action( 'rest_api_init', array( new Leads_Controller(), 'register_routes' ) );
		add_action( Lead_Sender::HOOK, array( Lead_Sender::class, 'run' ) );
		add_action( 'init', array( self::class, 'register_blocks' ) );
		Seo::register_output();

		if ( is_admin() ) {
			( new Admin() )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'pressfleet', Cli::class );
		}
	}

	/**
	 * The Lead form block, from the build of src/lead-form (npm run build).
	 */
	public static function register_blocks(): void {
		$block = dirname( PLUGIN_FILE ) . '/build/lead-form';
		if ( file_exists( $block . '/block.json' ) ) {
			register_block_type( $block );
		}
	}
}
