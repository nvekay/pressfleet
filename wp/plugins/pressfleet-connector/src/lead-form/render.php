<?php
/**
 * Front-end markup of the Lead form block (Interactivity API: view.js submits it without a reload).
 *
 * @package PressFleet\Connector
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$pressfleet_context = array(
	'endpoint'      => rest_url( 'pressfleet/v1/leads' ),
	'status'        => 'idle',
	'error'         => '',
	'fallbackError' => __( 'Something went wrong. Please try again or call us.', 'pressfleet-connector' ),
);
?>
<form
	<?php echo get_block_wrapper_attributes( array( 'class' => 'pressfleet-lead-form' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress ?>
	data-wp-interactive="pressfleet/lead-form"
	<?php echo wp_interactivity_data_wp_context( $pressfleet_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress ?>
	data-wp-on--submit="actions.submit"
>
	<label class="screen-reader-text" for="pressfleet-name"><?php esc_html_e( 'Your name', 'pressfleet-connector' ); ?></label>
	<input id="pressfleet-name" name="name" type="text" required maxlength="100" autocomplete="name" placeholder="<?php esc_attr_e( 'Your name', 'pressfleet-connector' ); ?>" />

	<label class="screen-reader-text" for="pressfleet-contact"><?php esc_html_e( 'Email or phone', 'pressfleet-connector' ); ?></label>
	<input id="pressfleet-contact" name="contact" type="text" required maxlength="120" autocomplete="email" placeholder="<?php esc_attr_e( 'Email or phone', 'pressfleet-connector' ); ?>" />

	<?php if ( ! empty( $attributes['showMessage'] ) ) : ?>
		<label class="screen-reader-text" for="pressfleet-message"><?php esc_html_e( 'Message', 'pressfleet-connector' ); ?></label>
		<textarea id="pressfleet-message" name="message" maxlength="2000" placeholder="<?php esc_attr_e( 'What would you like help with?', 'pressfleet-connector' ); ?>"></textarea>
	<?php endif; ?>

	<div class="pressfleet-hp" aria-hidden="true">
		<label for="pressfleet-website"><?php esc_html_e( 'Leave this empty', 'pressfleet-connector' ); ?></label>
		<input id="pressfleet-website" name="website" type="text" tabindex="-1" autocomplete="off" />
	</div>
	<input type="hidden" name="token" value="<?php echo esc_attr( \PressFleet\Connector\Lead_Guard::form_token() ); ?>" />
	<input type="hidden" name="page" value="<?php echo esc_url( home_url( add_query_arg( array() ) ) ); ?>" />

	<div class="wp-block-button">
		<button type="submit" class="wp-block-button__link wp-element-button" data-wp-bind--disabled="callbacks.isSending">
			<?php echo esc_html( (string) ( $attributes['buttonText'] ?? '' ) ); ?>
		</button>
	</div>

	<p role="status" hidden data-wp-bind--hidden="!callbacks.isSent">
		<?php echo esc_html( (string) ( $attributes['successMessage'] ?? '' ) ); ?>
	</p>
	<p role="alert" hidden data-wp-bind--hidden="!callbacks.isError" data-wp-text="context.error"></p>
</form>
