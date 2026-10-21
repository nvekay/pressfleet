<?php
/**
 * Title: Stats bar
 * Slug: pressfleet-theme/stats
 * Categories: pressfleet
 * Keywords: numbers, trust
 * Description: Four numbers that build trust, overlapping the hero.
 *
 * @package PressFleet\Theme
 */

$pressfleet_brand = pressfleet_theme_brand();
?>
<!-- wp:group {"align":"wide","className":"is-style-card pressfleet-stats","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"}}},"layout":{"type":"grid","columnCount":4,"minimumColumnWidth":"11rem"}} -->
<div class="wp-block-group alignwide is-style-card pressfleet-stats" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
<?php foreach ( $pressfleet_brand['stats'] as $pressfleet_stat ) : ?>
<!-- wp:group {"style":{"spacing":{"blockGap":"0.1rem"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontSize":"1.8rem","fontWeight":"700","lineHeight":"1.2"}}} -->
<p style="font-size:1.8rem;font-weight:700;line-height:1.2"><?php echo esc_html( $pressfleet_stat[0] ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html( $pressfleet_stat[1] ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<?php endforeach; ?>
</div>
<!-- /wp:group -->
