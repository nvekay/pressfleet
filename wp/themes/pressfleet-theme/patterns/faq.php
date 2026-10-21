<?php
/**
 * Title: FAQ
 * Slug: pressfleet-theme/faq
 * Categories: pressfleet, text
 * Keywords: questions, faq, accordion
 * Description: Questions and answers in native details blocks (no JavaScript).
 *
 * @package PressFleet\Theme
 */

$pressfleet_brand = pressfleet_theme_brand();
?>
<!-- wp:group {"tagName":"section","anchor":"faq","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<section id="faq" class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php esc_html_e( 'Questions', 'pressfleet-theme' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Before your first visit', 'pressfleet-theme' ); ?></h2>
<!-- /wp:heading -->
<?php foreach ( $pressfleet_brand['faq'] as $pressfleet_i => $pressfleet_faq ) : ?>
<!-- wp:details<?php echo 0 === $pressfleet_i ? ' {"showContent":true}' : ''; ?> -->
<details class="wp-block-details"<?php echo 0 === $pressfleet_i ? ' open' : ''; ?>><summary><?php echo esc_html( $pressfleet_faq[0] ); ?></summary><!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo esc_html( $pressfleet_faq[1] ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->
<?php endforeach; ?>
</section>
<!-- /wp:group -->
