<?php
/**
 * Creates the plugin's table and options, and upgrades them between versions.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * Activation and schema upgrades.
 */
final class Installer {

	const DB_VERSION        = 2;
	const DB_VERSION_OPTION = 'pressfleet_db_version';

	/**
	 * Runs on plugin activation.
	 */
	public static function activate(): void {
		self::install();
		Settings::ensure_secret();
		Case_Post_Type::register();
		flush_rewrite_rules();
	}

	/**
	 * Applies schema changes after an update without a re-activation.
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) < self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Creates or updates the plugin's tables (dbDelta only adds what is missing).
	 */
	private static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = Log::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				direction varchar(3) NOT NULL,
				event varchar(40) NOT NULL,
				external_id varchar(100) NOT NULL DEFAULT '',
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				message text NOT NULL,
				context longtext NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY external_id (external_id)
			) {$charset};"
		);
		$leads = Leads::table();
		dbDelta(
			"CREATE TABLE {$leads} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				name varchar(100) NOT NULL,
				contact varchar(120) NOT NULL,
				message text NOT NULL,
				page varchar(255) NOT NULL DEFAULT '',
				status varchar(10) NOT NULL DEFAULT 'pending',
				attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
				last_error text NULL,
				sent_at datetime NULL,
				PRIMARY KEY  (id),
				KEY status (status)
			) {$charset};"
		);
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}
}
