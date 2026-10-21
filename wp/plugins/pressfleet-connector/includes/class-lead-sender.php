<?php
/**
 * Forwards stored leads to the hub, signed, retrying with growing delays.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * Delivery of leads to the hub. A lead is saved first, so a visitor's request never fails because the hub is down;
 * a scheduled event retries after 1, 5 and 30 minutes, 2 and 12 hours.
 */
final class Lead_Sender {

	const HOOK    = 'pressfleet_send_lead';
	const DELAYS  = array( MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS, 30 * MINUTE_IN_SECONDS, 2 * HOUR_IN_SECONDS, 12 * HOUR_IN_SECONDS );
	const TIMEOUT = 5;

	/**
	 * Cron handler.
	 *
	 * @param int $id Lead ID.
	 */
	public static function run( $id ): void {
		self::send( (int) $id );
	}

	/**
	 * Sends one lead now. Returns true when the hub accepted it.
	 *
	 * @param int $id Lead ID.
	 */
	public static function send( int $id ): bool {
		$lead = Leads::get( $id );
		$url  = Settings::hub_url();
		if ( ! $lead || 'sent' === $lead->status || '' === $url ) {
			return false;
		}

		$body = (string) wp_json_encode(
			array(
				'id'         => (int) $lead->id,
				'name'       => $lead->name,
				'contact'    => $lead->contact,
				'message'    => $lead->message,
				'page'       => $lead->page,
				'created_at' => gmdate( 'c', (int) strtotime( $lead->created_at . ' UTC' ) ),
			)
		);
		$time = time();
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type'           => 'application/json',
					'X-PressFleet-Site'      => home_url(),
					'X-PressFleet-Timestamp' => (string) $time,
					'X-PressFleet-Signature' => Signature::sign( Settings::secret(), $time, 'POST', $path, $body ),
				),
				'body'    => $body,
			)
		);

		$attempts = (int) $lead->attempts + 1;
		$code     = (int) wp_remote_retrieve_response_code( $response );
		if ( ! is_wp_error( $response ) && $code >= 200 && $code < 300 ) {
			Leads::update(
				$id,
				array(
					'status'     => 'sent',
					'attempts'   => $attempts,
					'last_error' => null,
					'sent_at'    => current_time( 'mysql', true ),
				)
			);
			Log::add( Log::OUT, 'lead_sent', sprintf( 'Lead #%d sent to the hub', $id ) );
			return true;
		}

		$error = is_wp_error( $response ) ? $response->get_error_message() : sprintf( 'Hub answered %d', $code );
		$delay = self::DELAYS[ $attempts - 1 ] ?? null;
		Leads::update(
			$id,
			array(
				'status'     => null === $delay ? 'failed' : 'pending',
				'attempts'   => $attempts,
				'last_error' => $error,
			)
		);
		if ( null !== $delay ) {
			wp_schedule_single_event( time() + $delay, self::HOOK, array( $id ) );
		}
		Log::add( Log::OUT, 'lead_failed', sprintf( 'Lead #%d not sent (attempt %d): %s', $id, $attempts, $error ) );
		return false;
	}
}
