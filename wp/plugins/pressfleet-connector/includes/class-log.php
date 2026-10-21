<?php
/**
 * The plugin's own log: what the hub sent and what this site sent back.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * Log table access.
 */
final class Log {

	const IN  = 'in';
	const OUT = 'out';

	/**
	 * Entries older than this are deleted when new ones are written.
	 */
	const KEEP_DAYS = 30;

	/**
	 * Full table name with the site's prefix.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pressfleet_log';
	}

	/**
	 * Adds an entry.
	 *
	 * @param string               $direction   Log::IN or Log::OUT.
	 * @param string               $event       Short machine name, e.g. post_created.
	 * @param string               $message     Human-readable line for the admin screen.
	 * @param string               $external_id The hub's ID of the item, if any.
	 * @param int                  $post_id     Related post, if any.
	 * @param array<string, mixed> $context     Extra details, stored as JSON.
	 */
	public static function add( string $direction, string $event, string $message, string $external_id = '', int $post_id = 0, array $context = array() ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- a custom table; caching a write makes no sense
		$wpdb->insert(
			self::table(),
			array(
				'created_at'  => current_time( 'mysql', true ),
				'direction'   => $direction,
				'event'       => $event,
				'external_id' => $external_id,
				'post_id'     => $post_id,
				'message'     => $message,
				'context'     => $context ? wp_json_encode( $context ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		self::prune();
	}

	/**
	 * Latest entries, newest first.
	 *
	 * @param int    $limit  How many.
	 * @param int    $offset How many to skip.
	 * @param string $event  Only this event, if set.
	 * @return array<int, object{id: string, created_at: string, direction: string, event: string, external_id: string, post_id: string, message: string, context: ?string}>
	 */
	public static function latest( int $limit = 20, int $offset = 0, string $event = '' ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name
		$rows = $event
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE event = %s ORDER BY id DESC LIMIT %d OFFSET %d", $event, $limit, $offset ) )
			: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) );
		// phpcs:enable
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Number of entries, optionally of one event.
	 *
	 * @param string $event Only this event, if set.
	 */
	public static function count( string $event = '' ): int {
		global $wpdb;
		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name
		$count = $event
			? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event = %s", $event ) )
			: $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable
		return (int) $count;
	}

	/**
	 * Time of the latest entry received from the hub, in UTC, or null.
	 */
	public static function last_received_at(): ?string {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(created_at) FROM {$table} WHERE direction = %s", self::IN ) );
		return $value ? (string) $value : null;
	}

	/**
	 * Deletes every entry (the demo's nightly reset).
	 */
	public static function clear(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Deletes old entries, at most once an hour.
	 */
	private static function prune(): void {
		if ( get_transient( 'pressfleet_log_pruned' ) ) {
			return;
		}
		set_transient( 'pressfleet_log_pruned', 1, HOUR_IN_SECONDS );
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - self::KEEP_DAYS * DAY_IN_SECONDS ) ) );
	}
}
