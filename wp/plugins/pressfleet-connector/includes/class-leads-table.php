<?php
/**
 * Leads as an admin list table.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Leads with their delivery status to the hub.
 */
final class Leads_Table extends \WP_List_Table {

	const PER_PAGE = 25;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'pressfleet_lead',
				'plural'   => 'pressfleet_leads',
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
			'created_at' => __( 'Received (UTC)', 'pressfleet-connector' ),
			'name'       => __( 'Name', 'pressfleet-connector' ),
			'contact'    => __( 'Contact', 'pressfleet-connector' ),
			'message'    => __( 'Message', 'pressfleet-connector' ),
			'status'     => __( 'Sent to the hub', 'pressfleet-connector' ),
		);
	}

	/**
	 * Loads one page of leads.
	 */
	public function prepare_items(): void {
		$page = $this->get_pagenum();
		$this->set_pagination_args(
			array(
				'total_items' => Leads::count(),
				'per_page'    => self::PER_PAGE,
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = Leads::latest( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
	}

	/**
	 * Default column output.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column.
	 */
	protected function column_default( $item, $column_name ): string {
		$value = isset( $item->$column_name ) ? (string) $item->$column_name : '';
		return esc_html( 'message' === $column_name ? wp_trim_words( $value, 20, '…' ) : $value );
	}

	/**
	 * Delivery status with the last error.
	 *
	 * @param object{status: string, attempts: string, last_error: ?string, sent_at: ?string} $item Row.
	 */
	protected function column_status( $item ): string {
		$labels = array(
			'sent'    => __( 'Sent', 'pressfleet-connector' ),
			'pending' => __( 'Waiting for a retry', 'pressfleet-connector' ),
			'failed'  => __( 'Failed', 'pressfleet-connector' ),
			'stored'  => __( 'Stored here only (no hub configured)', 'pressfleet-connector' ),
		);
		$out    = esc_html( $labels[ $item->status ] ?? $item->status );
		if ( (int) $item->attempts > 1 || 'sent' !== $item->status ) {
			/* translators: %d: number of delivery attempts */
			$out .= '<br><small>' . esc_html( sprintf( _n( '%d attempt', '%d attempts', (int) $item->attempts, 'pressfleet-connector' ), (int) $item->attempts ) ) . '</small>';
		}
		if ( $item->last_error && 'sent' !== $item->status ) {
			$out .= '<br><small>' . esc_html( $item->last_error ) . '</small>';
		}
		return $out;
	}

	/**
	 * Message when there are no leads.
	 */
	public function no_items(): void {
		esc_html_e( 'No leads yet. Add the Lead form block to a page to start receiving them.', 'pressfleet-connector' );
	}
}
