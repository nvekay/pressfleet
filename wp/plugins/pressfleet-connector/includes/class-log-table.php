<?php
/**
 * The activity log as a standard admin list table.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Log list table with paging and an event filter.
 */
final class Log_Table extends \WP_List_Table {

	const PER_PAGE = 25;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'pressfleet_log_entry',
				'plural'   => 'pressfleet_log',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'created_at'  => __( 'Time (UTC)', 'pressfleet-connector' ),
			'event'       => __( 'Event', 'pressfleet-connector' ),
			'message'     => __( 'Message', 'pressfleet-connector' ),
			'external_id' => __( 'External ID', 'pressfleet-connector' ),
		);
	}

	/**
	 * Loads one page of entries.
	 */
	public function prepare_items(): void {
		$event = $this->current_event();
		$page  = $this->get_pagenum();
		$this->set_pagination_args(
			array(
				'total_items' => Log::count( $event ),
				'per_page'    => self::PER_PAGE,
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = Log::latest( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE, $event );
	}

	/**
	 * Event filter above the table.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$events = array( 'post_created', 'post_updated', 'post_unchanged', 'post_failed', 'lead_received', 'lead_sent', 'lead_failed' );
		echo '<div class="alignleft actions"><select name="event"><option value="">' . esc_html__( 'All events', 'pressfleet-connector' ) . '</option>';
		foreach ( $events as $event ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $event ), selected( $this->current_event(), $event, false ) );
		}
		echo '</select>';
		submit_button( __( 'Filter', 'pressfleet-connector' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Default column output.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column.
	 */
	protected function column_default( $item, $column_name ): string {
		$value = isset( $item->$column_name ) ? (string) $item->$column_name : '';
		return esc_html( $value );
	}

	/**
	 * Message with a link to the post.
	 *
	 * @param object{message: string, post_id: string} $item Row.
	 */
	protected function column_message( $item ): string {
		$post_id = (int) $item->post_id;
		$link    = $post_id ? get_edit_post_link( $post_id ) : null;
		return $link
			? sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $item->message ) )
			: esc_html( $item->message );
	}

	/**
	 * Message when the log is empty.
	 */
	public function no_items(): void {
		esc_html_e( 'Nothing yet. Entries appear when the hub publishes here or a lead is submitted.', 'pressfleet-connector' );
	}

	/**
	 * Event chosen in the filter.
	 */
	private function current_event(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only filter
		return isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : '';
	}
}
