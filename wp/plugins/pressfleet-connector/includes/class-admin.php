<?php
/**
 * Settings → PressFleet: connection details for the hub and the activity log.
 *
 * @package PressFleet\Connector
 */

namespace PressFleet\Connector;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen.
 */
final class Admin {

	const PAGE = 'pressfleet';

	/**
	 * Registers the menu, the setting and the secret action.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
		add_action( 'admin_post_pressfleet_regenerate_secret', array( $this, 'regenerate_secret' ) );
		add_action( 'admin_post_pressfleet_retry_leads', array( $this, 'retry_leads' ) );
	}

	/**
	 * Adds the page under Settings.
	 */
	public function menu(): void {
		add_options_page(
			__( 'PressFleet', 'pressfleet-connector' ),
			__( 'PressFleet', 'pressfleet-connector' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
		add_menu_page(
			__( 'Leads', 'pressfleet-connector' ),
			__( 'Leads', 'pressfleet-connector' ),
			'edit_pages',
			'pressfleet-leads',
			array( $this, 'render_leads' ),
			'dashicons-email-alt',
			26
		);
	}

	/**
	 * Sends every unsent lead now (admin-post action with a nonce).
	 */
	public function retry_leads(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'pressfleet-connector' ), 403 );
		}
		check_admin_referer( 'pressfleet_retry_leads' );
		$sent = 0;
		foreach ( Leads::unsent_ids() as $id ) {
			$sent += Lead_Sender::send( $id ) ? 1 : 0;
		}
		wp_safe_redirect( add_query_arg( 'sent', $sent, menu_page_url( 'pressfleet-leads', false ) ) );
		exit;
	}

	/**
	 * The leads page.
	 */
	public function render_leads(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$table = new Leads_Table();
		$table->prepare_items();
		$unsent = count( Leads::unsent_ids() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
		$sent = isset( $_GET['sent'] ) ? absint( $_GET['sent'] ) : null;
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads', 'pressfleet-connector' ); ?></h1>
			<?php if ( $unsent && '' !== Settings::hub_url() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="pressfleet_retry_leads" />
					<?php wp_nonce_field( 'pressfleet_retry_leads' ); ?>
					<?php /* translators: %d: number of leads not sent yet */ ?>
					<?php submit_button( sprintf( _n( 'Send %d waiting lead now', 'Send %d waiting leads now', $unsent, 'pressfleet-connector' ), $unsent ), 'page-title-action', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<?php if ( null !== $sent ) : ?>
				<?php /* translators: %d: number of leads sent */ ?>
				<div class="notice notice-info"><p><?php echo esc_html( sprintf( _n( '%d lead sent.', '%d leads sent.', $sent, 'pressfleet-connector' ), $sent ) ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Every lead is saved here first and then forwarded to the hub, with retries after 1, 5 and 30 minutes, 2 and 12 hours.', 'pressfleet-connector' ); ?></p>
			<?php $table->display(); ?>
		</div>
		<?php
	}

	/**
	 * The hub URL setting (where leads go).
	 */
	public function settings(): void {
		register_setting(
			self::PAGE,
			Settings::HUB_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);
		add_settings_section( 'pressfleet_hub', __( 'Hub', 'pressfleet-connector' ), '__return_false', self::PAGE );
		add_settings_field(
			Settings::HUB_URL,
			__( 'Leads endpoint', 'pressfleet-connector' ),
			static function (): void {
				printf(
					'<input type="url" class="regular-text code" name="%1$s" value="%2$s" placeholder="https://hub.example.com/api/leads" /><p class="description">%3$s</p>',
					esc_attr( Settings::HUB_URL ),
					esc_attr( Settings::hub_url() ),
					esc_html__( 'Where submissions of the Lead form block are sent. Leave empty to only store them here.', 'pressfleet-connector' )
				);
			},
			self::PAGE,
			'pressfleet_hub'
		);
	}

	/**
	 * Replaces the shared secret (admin-post action with a nonce).
	 */
	public function regenerate_secret(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'pressfleet-connector' ), 403 );
		}
		check_admin_referer( 'pressfleet_regenerate_secret' );
		Settings::regenerate_secret();
		Log::add( Log::IN, 'secret_regenerated', 'The hub secret was regenerated in the admin' );
		wp_safe_redirect( add_query_arg( 'secret', 'regenerated', menu_page_url( self::PAGE, false ) ) );
		exit;
	}

	/**
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$table = new Log_Table();
		$table->prepare_items();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
		$regenerated = isset( $_GET['secret'] ) && 'regenerated' === $_GET['secret'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PressFleet', 'pressfleet-connector' ); ?></h1>
			<?php if ( $regenerated ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'New secret generated. Update it in the hub, or publishing to this site will fail.', 'pressfleet-connector' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Connection', 'pressfleet-connector' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'REST endpoint', 'pressfleet-connector' ); ?></th>
					<td><code><?php echo esc_html( rest_url( Rest_Controller::NAMESPACE ) ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Shared secret', 'pressfleet-connector' ); ?></th>
					<td>
						<input type="text" class="regular-text code" readonly value="<?php echo esc_attr( Settings::secret() ); ?>" onclick="this.select()" />
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
							<input type="hidden" name="action" value="pressfleet_regenerate_secret" />
							<?php wp_nonce_field( 'pressfleet_regenerate_secret' ); ?>
							<?php submit_button( __( 'Regenerate', 'pressfleet-connector' ), 'secondary', 'submit', false ); ?>
						</form>
						<p class="description"><?php esc_html_e( 'The hub signs every request with this secret, in addition to an Application Password.', 'pressfleet-connector' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'SEO fields go to', 'pressfleet-connector' ); ?></th>
					<td><?php echo esc_html( Seo::active_plugin() ? ucfirst( (string) Seo::active_plugin() ) : __( 'This plugin (no SEO plugin active)', 'pressfleet-connector' ) ); ?></td>
				</tr>
			</table>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<h2><?php esc_html_e( 'Activity', 'pressfleet-connector' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}
}
