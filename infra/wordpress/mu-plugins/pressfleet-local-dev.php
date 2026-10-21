<?php
/**
 * Plugin Name: PressFleet local development
 * Description: Lets the local demo sites download images from the dashboard on the host (host.docker.internal:3000).
 *              WordPress refuses private addresses and unusual ports by default to prevent SSRF; this is mounted
 *              only by infra/docker-compose.yml and never ships with the plugin.
 *
 * @package PressFleet
 */

add_filter(
	'http_request_host_is_external',
	static function ( $external, $host ) {
		return $external || 'host.docker.internal' === $host;
	},
	10,
	2
);

add_filter(
	'http_allowed_safe_ports',
	static function ( $ports, $host ) {
		if ( 'host.docker.internal' === $host ) {
			$ports[] = 3000; // dashboard (sample images)
			$ports[] = 4000; // hub API (leads)
		}
		return $ports;
	},
	10,
	2
);
