<?php
/**
 * Title: Contact
 * Slug: pressfleet-theme/contact
 * Categories: pressfleet, call-to-action
 * Keywords: contact, booking, form, cta
 * Description: A dark panel with the address and the PressFleet lead form.
 *
 * @package PressFleet\Theme
 */

$pressfleet_brand = pressfleet_theme_brand();
?>
<!-- wp:group {"tagName":"section","anchor":"contact","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<section id="contact" class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:group {"align":"wide","style":{"border":{"radius":"28px"},"spacing":{"padding":{"top":"var:preset|spacing|60","right":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60"}},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"backgroundColor":"contrast","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide has-base-color has-contrast-background-color has-text-color has-background has-link-color" style="border-radius:28px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Book a visit', 'pressfleet-theme' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Tell us what is bothering you and when you are free. We answer within one working day.', 'pressfleet-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php echo esc_html( $pressfleet_brand['address'] ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:pressfleet/lead-form /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
