<?php
/**
 * WP-CLI commands: wp pressfleet status|log|secret.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Inspect and manage the PressFleet connection.
 */
final class Cli {

	/**
	 * Shows what the hub has published here and how the connection is configured.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pressfleet status
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		$rows = array(
			array(
				'key'   => 'Posts from the hub',
				'value' => (string) $this->posts_from_hub(),
			),
			array(
				'key'   => 'Created / updated / unchanged',
				'value' => sprintf( '%d / %d / %d', Log::count( 'post_created' ), Log::count( 'post_updated' ), Log::count( 'post_unchanged' ) ),
			),
			array(
				'key'   => 'Failed',
				'value' => (string) Log::count( 'post_failed' ),
			),
			array(
				'key'   => 'Last received (UTC)',
				'value' => Log::last_received_at() ?? 'never',
			),
			array(
				'key'   => 'Signing secret',
				'value' => '' !== Settings::secret() ? 'set' : 'missing',
			),
			array(
				'key'   => 'Leads sent / waiting / failed',
				'value' => sprintf( '%d / %d / %d', Leads::count( 'sent' ), Leads::count( 'pending' ), Leads::count( 'failed' ) ),
			),
			array(
				'key'   => 'SEO fields go to',
				'value' => Seo::active_plugin() ?? 'this plugin',
			),
		);
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'key', 'value' ) );
	}

	/**
	 * Prints the latest log entries.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : How many entries.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--event=<event>]
	 * : Only this event, e.g. post_failed.
	 *
	 * [--format=<format>]
	 * : table, csv, json or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pressfleet log --limit=50 --event=post_failed
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function log( array $args, array $assoc_args ): void {
		$items = array_map(
			static fn( $row ) => array(
				'time'        => $row->created_at,
				'event'       => $row->event,
				'external_id' => $row->external_id,
				'post_id'     => $row->post_id,
				'message'     => $row->message,
			),
			Log::latest( (int) ( $assoc_args['limit'] ?? 20 ), 0, (string) ( $assoc_args['event'] ?? '' ) )
		);
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array( 'time', 'event', 'external_id', 'post_id', 'message' ) );
	}

	/**
	 * Prints the shared secret, or replaces it.
	 *
	 * ## OPTIONS
	 *
	 * [--regenerate]
	 * : Generate a new secret (the hub must be updated).
	 *
	 * ## EXAMPLES
	 *
	 *     wp pressfleet secret
	 *     wp pressfleet secret --regenerate
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function secret( array $args, array $assoc_args ): void {
		if ( isset( $assoc_args['regenerate'] ) ) {
			$secret = Settings::regenerate_secret();
			Log::add( Log::IN, 'secret_regenerated', 'The hub secret was regenerated with WP-CLI' );
			WP_CLI::line( $secret );
			WP_CLI::warning( 'Update the secret in the hub.' );
			return;
		}
		Settings::ensure_secret();
		WP_CLI::line( Settings::secret() );
	}

	/**
	 * Lists leads from the Lead form block.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : pending, sent, failed or stored.
	 *
	 * [--limit=<number>]
	 * : How many.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : table, csv, json or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function leads( array $args, array $assoc_args ): void {
		$items = array_map(
			static fn( $lead ) => array(
				'id'       => $lead->id,
				'time'     => $lead->created_at,
				'name'     => $lead->name,
				'contact'  => $lead->contact,
				'status'   => $lead->status,
				'attempts' => $lead->attempts,
				'error'    => (string) $lead->last_error,
			),
			Leads::latest( (int) ( $assoc_args['limit'] ?? 20 ), 0, (string) ( $assoc_args['status'] ?? '' ) )
		);
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array( 'id', 'time', 'name', 'contact', 'status', 'attempts', 'error' ) );
	}

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- WP-CLI passes both arguments to every command.

	/**
	 * Sends every pending or failed lead to the hub now.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pressfleet retry
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function retry( array $args, array $assoc_args ): void {
		$ids  = Leads::unsent_ids();
		$sent = 0;
		foreach ( $ids as $id ) {
			$sent += Lead_Sender::send( $id ) ? 1 : 0;
		}
		WP_CLI::line( sprintf( 'Sent %d of %d leads.', $sent, count( $ids ) ) );
	}

	// phpcs:enable

	/**
	 * Count of posts the hub created.
	 */
	private function posts_from_hub(): int {
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a count for the status command
				'meta_key'       => Publisher::EXTERNAL_ID_META,
			)
		);
		return (int) $query->found_posts;
	}
}
