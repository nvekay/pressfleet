<?php
/**
 * Gives a demo site its brand: runs inside WordPress with `wp eval-file - <brand>` (infra/provision.sh).
 * Applies the theme's style variation the way the Site Editor does (user global styles), creates the home and
 * journal pages and three case studies. Safe to run again.
 *
 * @package PressFleet
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- a provisioning script, not plugin code.

$brand = $args[0] ?? 'alder';

set_theme_mod( 'pressfleet_brand', $brand );
// The tagline doubles as the home page meta description.
update_option( 'blogdescription', pressfleet_theme_brand()['lead'] );

// The style variation becomes the site's global styles; switching in the Site Editor still works afterwards.
// WordPress finds that post by its wp_theme term, so posts left without one (from a run without a user) are removed.
$stylesheet = get_stylesheet();
foreach ( get_posts( array( 'post_type' => 'wp_global_styles', 'post_status' => 'any', 'numberposts' => -1 ) ) as $orphan ) {
	if ( ! has_term( $stylesheet, 'wp_theme', $orphan ) ) {
		wp_delete_post( $orphan->ID, true );
	}
}
$variation = json_decode( (string) file_get_contents( get_theme_file_path( "styles/{$brand}.json" ) ), true );
$styles_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
wp_set_object_terms( $styles_id, $stylesheet, 'wp_theme' );
wp_update_post(
	array(
		'ID'           => $styles_id,
		'post_content' => wp_slash(
			wp_json_encode(
				array(
					'version'                     => 3,
					'isGlobalStylesUserThemeJSON' => true,
					'settings'                    => $variation['settings'] ?? new stdClass(),
					'styles'                      => $variation['styles'] ?? new stdClass(),
				)
			)
		),
	)
);

$page = static function ( string $slug, string $title ): int {
	$existing = get_page_by_path( $slug );
	return $existing ? $existing->ID : (int) wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => $slug,
			'post_title'  => $title,
		)
	);
};
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page( 'home', 'Home' ) );
update_option( 'page_for_posts', $page( 'journal', 'Journal' ) );

// WordPress's sample content has no place in the demo.
foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page' ) as $slug => $type ) {
	$sample = get_page_by_path( $slug, OBJECT, $type );
	if ( $sample ) {
		wp_delete_post( $sample->ID, true );
	}
}

$cases = array(
	'alder' => array(
		array( 'Back to running after a knee injury', 'Twelve weeks from limping to a 10 km race, with strength work twice a week and a gradual return to running.' ),
		array( 'A desk job without neck pain', 'Small daily habits and a better screen setup ended two years of stiffness for an office manager.' ),
		array( 'Walking with confidence after hip surgery', 'Balance and strength exercises, at the clinic and at home, from the first week after the operation.' ),
	),
	'birch' => array(
		array( 'A first visit without fear', 'A four-year-old who now asks when the next check-up is, after a slow and playful first appointment.' ),
		array( 'Healthy gums in six months', 'A deep clean, a new routine and regular check-ups stopped bleeding gums for good.' ),
		array( 'A natural-looking crown', 'A cracked front tooth repaired in two visits, matched to the shade of the teeth around it.' ),
	),
	'cedar' => array(
		array( 'The end of evening headaches', 'A developer’s headaches came from eye strain; the right glasses and screen breaks fixed them.' ),
		array( 'Contact lenses for a swimmer', 'Daily lenses and goggles that work together, after a fitting and a two-week trial.' ),
		array( 'A seven-year-old who loves reading again', 'An eye exam at school age found a small prescription that made letters stop dancing.' ),
	),
);

foreach ( $cases[ $brand ] ?? array() as $case ) {
	$slug = sanitize_title( $case[0] );
	if ( get_page_by_path( $slug, OBJECT, 'pf_case' ) ) {
		continue;
	}
	wp_insert_post(
		array(
			'post_type'    => 'pf_case',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $case[0],
			'post_excerpt' => $case[1],
			'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $case[1] ) . '</p><!-- /wp:paragraph -->',
		)
	);
}

WP_CLI::success( "Brand {$brand} applied" );
