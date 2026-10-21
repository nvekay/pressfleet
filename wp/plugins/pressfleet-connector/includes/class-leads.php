<?php
/**
 * Leads from the Lead form block, stored on the site before they are forwarded.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * Leads table access. Status: pending (not sent yet or waiting for a retry), sent, failed (out of retries),
 * stored (no hub configured).
 *
 * @phpstan-type Lead object{id: string, created_at: string, name: string, contact: string, message: string, page: string, status: string, attempts: string, last_error: ?string, sent_at: ?string}
 */
final class Leads {

	/**
	 * Full table name with the site's prefix.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pressfleet_leads';
	}

	/**
	 * Stores a lead.
	 *
	 * @param array{name: string, contact: string, message: string, page: string} $lead Sanitized fields.
	 * @return int The new lead's ID.
	 */
	public static function insert( array $lead ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own table
		$wpdb->insert(
			self::table(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'name'       => $lead['name'],
				'contact'    => $lead['contact'],
				'message'    => $lead['message'],
				'page'       => $lead['page'],
				'status'     => '' === Settings::hub_url() ? 'stored' : 'pending',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * One lead, or null.
	 *
	 * @param int $id Lead ID.
	 * @phpstan-return Lead|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		/**
		 * A row of the leads table.
		 *
		 * @phpstan-var Lead|null $row
		 */
		return is_object( $row ) ? $row : null;
	}

	/**
	 * Latest leads, newest first.
	 *
	 * @param int    $limit  How many.
	 * @param int    $offset How many to skip.
	 * @param string $status Only this status, if set.
	 * @return array<int, object>
	 * @phpstan-return array<int, Lead>
	 */
	public static function latest( int $limit = 20, int $offset = 0, string $status = '' ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name
		$rows = $status
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $status, $limit, $offset ) )
			: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) );
		// phpcs:enable
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Number of leads, optionally with one status.
	 *
	 * @param string $status Only this status, if set.
	 */
	public static function count( string $status = '' ): int {
		global $wpdb;
		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name
		$count = $status
			? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) )
			: $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable
		return (int) $count;
	}

	/**
	 * IDs of leads that still have to reach the hub.
	 *
	 * @return int[]
	 */
	public static function unsent_ids(): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name
		return array_map( 'intval', $wpdb->get_col( "SELECT id FROM {$table} WHERE status IN ('pending', 'failed') ORDER BY id" ) );
	}

	/**
	 * Updates the delivery fields.
	 *
	 * @param int                  $id     Lead ID.
	 * @param array<string, mixed> $fields Columns to set.
	 */
	public static function update( int $id, array $fields ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own table
		$wpdb->update( self::table(), $fields, array( 'id' => $id ) );
	}
}
