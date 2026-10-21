<?php
/**
 * Title: Testimonials
 * Slug: pressfleet-theme/testimonials
 * Categories: pressfleet, testimonials
 * Keywords: quotes, reviews
 * Description: Two patient quotes side by side.
 *
 * @package PressFleet\Theme
 */

$pressfleet_brand = pressfleet_theme_brand();
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide">
<?php foreach ( $pressfleet_brand['quotes'] as $pressfleet_quote ) : ?>
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p><?php echo esc_html( '“' . $pressfleet_quote[0] . '”' ); ?></p>
<!-- /wp:paragraph --><cite><?php echo esc_html( $pressfleet_quote[1] ); ?></cite></blockquote>
<!-- /wp:quote --></div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
