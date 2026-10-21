<?php
/**
 * Plugin Name:       PressFleet Connector
 * Plugin URI:        https://github.com/nvekay/pressfleet
 * Description:       Receives posts from the PressFleet hub over a signed REST API, keeps a log, adds WP-CLI commands and a lead form block.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Author:            nvekay
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pressfleet-connector
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;

/*
 * Classes live in includes/class-{name}.php (WordPress file naming), so a tiny autoloader maps
 * PressFleet\Connector\Rest_Controller to includes/class-rest-controller.php.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$name = strtolower( str_replace( '_', '-', substr( $class_name, strlen( $prefix ) ) ) );
		$file = __DIR__ . '/includes/class-' . $name . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
