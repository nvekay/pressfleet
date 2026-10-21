<?php
/**
 * The part of WP-CLI the plugin uses, for static analysis only (php-stubs/wp-cli-stubs does not support
 * WordPress 7 stubs yet).
 *
 * @package PressFleet\Connector
 */

// phpcs:disable

namespace {
	class WP_CLI {
		/**
		 * @param string               $name     Command name.
		 * @param callable|string      $callable Command class or callable.
		 * @param array<string, mixed> $args     Registration options.
		 */
		public static function add_command( $name, $callable, $args = array() ): bool {
			return true;
		}

		/** @param string $message Line to print. */
		public static function line( $message = '' ): void {}

		/** @param string $message Warning to print. */
		public static function warning( $message ): void {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param string                           $format Output format.
	 * @param array<int, array<string, mixed>> $items  Rows.
	 * @param string[]                         $fields Columns.
	 */
	function format_items( $format, $items, $fields ): void {}
}
