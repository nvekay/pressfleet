<?php
/**
 * Removes the plugin's table and options. Posts the hub published stay: they are the site's content.
 *
 * @package PressFleet\Connector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dropping the plugin's own table
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressfleet_log" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dropping the plugin's own table
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pressfleet_leads" );
wp_clear_scheduled_hook( 'pressfleet_send_lead' );

foreach ( array( 'pressfleet_hub_secret', 'pressfleet_hub_url', 'pressfleet_db_version' ) as $pressfleet_option ) {
	delete_option( $pressfleet_option );
}
