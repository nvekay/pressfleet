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
		Seo::register_output();

		if ( is_admin() ) {
			( new Admin() )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'pressfleet', Cli::class );
		}
	}
}
