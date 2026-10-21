<?php
/**
 * Title: Hero
 * Slug: pressfleet-theme/hero
 * Categories: pressfleet, banner
 * Keywords: hero, header, intro
 * Description: Headline, lead, two buttons and a brand card with the clinic's rating.
 *
 * @package PressFleet\Theme
 */

$pressfleet_brand = pressfleet_theme_brand();
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70"}}},"gradient":"brand-glow","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-brand-glow-gradient-background has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:columns {"verticalAlignment":"center","align":"wide"} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"56%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:56%"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow"><?php echo esc_html( $pressfleet_brand['eyebrow'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php echo esc_html( $pressfleet_brand['title'] ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"large"} -->
<p class="has-muted-color has-text-color has-large-font-size"><?php echo esc_html( $pressfleet_brand['lead'] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#contact"><?php echo esc_html( $pressfleet_brand['cta'] ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#services"><?php esc_html_e( 'See treatments', 'pressfleet-theme' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"44%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:44%"><!-- wp:group {"className":"pressfleet-hero-card","style":{"border":{"radius":"28px"},"spacing":{"padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"}},"dimensions":{"minHeight":"420px"}},"textColor":"base","gradient":"brand-diagonal","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"bottom"}} -->
<div class="wp-block-group pressfleet-hero-card has-base-color has-brand-diagonal-gradient-background has-text-color has-background" style="border-radius:28px;min-height:420px;padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:paragraph {"style":{"typography":{"fontSize":"3.2rem","fontWeight":"700","lineHeight":"1"}}} -->
<p style="font-size:3.2rem;font-weight:700;line-height:1"><?php echo esc_html( $pressfleet_brand['rating'][0] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php echo esc_html( $pressfleet_brand['rating'][1] ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
