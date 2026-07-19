<?php
/**
 * Settings page.
 *
 * @package Mozcheck
 */

/**
 * Renders and handles the MozCheck settings screen.
 */
final class Mozcheck_Admin {
	/**
	 * Register admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_mozcheck_send_now', array( __CLASS__, 'send_now' ) );
	}

	/**
	 * Enqueue the small settings-page behavior script.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( 'settings_page_mozcheck' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script( 'mozcheck-settings', plugins_url( 'assets/settings.js', MOZCHECK_PLUGIN_FILE ), array(), MOZCHECK_VERSION, true );
	}

	/**
	 * Add settings menu entry.
	 */
	public static function menu(): void {
		add_options_page( __( 'MozCheck', 'mozcheck' ), __( 'MozCheck', 'mozcheck' ), 'manage_options', 'mozcheck', array( __CLASS__, 'render' ) );
	}

	/**
	 * Register the single settings option.
	 */
	public static function register_settings(): void {
		register_setting(
			'mozcheck',
			Mozcheck_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Mozcheck_Settings', 'sanitize' ),
				'default'           => Mozcheck_Settings::defaults(),
			)
		);
	}

	/**
	 * Handle a manual report.
	 */
	public static function send_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'mozcheck' ) );
		}
		check_admin_referer( 'mozcheck_send_now' );
		$result = Mozcheck_Runner::run_manual();
		$status = $result['delivery']['status'] ?? $result['status'] ?? 'error';
		wp_safe_redirect( add_query_arg( 'mozcheck_sent', sanitize_key( $status ), admin_url( 'options-general.php?page=mozcheck' ) ) );
		exit;
	}

	/**
	 * Render settings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Mozcheck_Settings::get();
		$next     = wp_next_scheduled( Mozcheck_Scheduler::HOOK );
		$last     = get_option( Mozcheck_Settings::STATUS_OPTION, array() );
		$sent     = isset( $_GET['mozcheck_sent'] ) ? sanitize_key( wp_unslash( $_GET['mozcheck_sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$weekdays = array(
			1 => __( 'Monday', 'mozcheck' ),
			2 => __( 'Tuesday', 'mozcheck' ),
			3 => __( 'Wednesday', 'mozcheck' ),
			4 => __( 'Thursday', 'mozcheck' ),
			5 => __( 'Friday', 'mozcheck' ),
			6 => __( 'Saturday', 'mozcheck' ),
			7 => __( 'Sunday', 'mozcheck' ),
		);
		$policies = array(
			'always'   => __( 'Always send a report', 'mozcheck' ),
			'issues'   => __( 'When there is a critical or recommended item', 'mozcheck' ),
			'critical' => __( 'When there is at least one critical item', 'mozcheck' ),
			'updates'  => __( 'When an update is available', 'mozcheck' ),
			'worsened' => __( 'When the result is worse than the previous scheduled check', 'mozcheck' ),
			'custom'   => __( 'Custom conditions', 'mozcheck' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MozCheck Site Health email', 'mozcheck' ); ?></h1>
			<p><?php esc_html_e( 'Receive a readable reminder of WordPress Site Health results. WP-Cron runs when the site receives traffic, so delivery may be later than the selected time.', 'mozcheck' ); ?></p>
			<?php settings_errors( Mozcheck_Settings::OPTION ); ?>
			<?php if ( $sent ) : ?>
				<div class="notice <?php echo 'success' === $sent ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( 'success' === $sent ? __( 'The report email was sent.', 'mozcheck' ) : __( 'The report could not be sent to every recipient.', 'mozcheck' ) ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'mozcheck' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Email notifications', 'mozcheck' ); ?></th><td><label><input type="checkbox" name="mozcheck_settings[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Enable scheduled email notifications', 'mozcheck' ); ?></label></td></tr>
					<tr><th scope="row"><label for="mozcheck-recipients"><?php esc_html_e( 'Recipients', 'mozcheck' ); ?></label></th><td><textarea id="mozcheck-recipients" class="large-text" rows="3" name="mozcheck_settings[recipients]"><?php echo esc_textarea( implode( "\n", $settings['recipients'] ) ); ?></textarea><p class="description"><?php esc_html_e( 'Enter one address per line or separate addresses with commas. Each recipient receives a separate email.', 'mozcheck' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Schedule', 'mozcheck' ); ?></th><td>
						<select name="mozcheck_settings[frequency]" id="mozcheck-frequency"><option value="weekly" <?php selected( $settings['frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'mozcheck' ); ?></option><option value="monthly" <?php selected( $settings['frequency'], 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'mozcheck' ); ?></option></select>
						<label id="mozcheck-weekday-field"><span class="screen-reader-text"><?php esc_html_e( 'Weekday', 'mozcheck' ); ?></span> <select name="mozcheck_settings[weekday]">
						<?php
						foreach ( $weekdays as $number => $label ) :
							?>
							<option value="<?php echo esc_attr( (string) $number ); ?>" <?php selected( $settings['weekday'], $number ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
						<label id="mozcheck-monthday-field"><?php esc_html_e( 'Day of month', 'mozcheck' ); ?> <input type="number" min="1" max="28" name="mozcheck_settings[monthday]" value="<?php echo esc_attr( (string) $settings['monthday'] ); ?>"></label>
						<input type="time" name="mozcheck_settings[time]" value="<?php echo esc_attr( $settings['time'] ); ?>">
						<p class="description"><?php /* translators: %s: site timezone. */ echo esc_html( sprintf( __( 'Times use the site timezone: %s. Monthly dates are limited to 1–28.', 'mozcheck' ), wp_timezone_string() ) ); ?></p>
					</td></tr>
					<tr><th scope="row"><label for="mozcheck-policy"><?php esc_html_e( 'Send when', 'mozcheck' ); ?></label></th><td><select id="mozcheck-policy" name="mozcheck_settings[policy]">
					<?php
					foreach ( $policies as $value => $label ) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['policy'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
						<fieldset id="mozcheck-custom-conditions" style="margin-top:12px"><legend class="screen-reader-text"><?php esc_html_e( 'Custom conditions', 'mozcheck' ); ?></legend>
							<label><input type="checkbox" name="mozcheck_settings[custom_critical]" value="1" <?php checked( $settings['custom_critical'] ); ?>> <?php esc_html_e( 'Critical count is at least', 'mozcheck' ); ?> <input type="number" min="1" name="mozcheck_settings[critical_threshold]" value="<?php echo esc_attr( (string) $settings['critical_threshold'] ); ?>"></label><br>
							<label><input type="checkbox" name="mozcheck_settings[custom_recommended]" value="1" <?php checked( $settings['custom_recommended'] ); ?>> <?php esc_html_e( 'Recommended count is at least', 'mozcheck' ); ?> <input type="number" min="1" name="mozcheck_settings[recommended_threshold]" value="<?php echo esc_attr( (string) $settings['recommended_threshold'] ); ?>"></label><br>
							<label><input type="checkbox" name="mozcheck_settings[custom_updates]" value="1" <?php checked( $settings['custom_updates'] ); ?>> <?php esc_html_e( 'An update is available', 'mozcheck' ); ?></label><br>
							<label><input type="checkbox" name="mozcheck_settings[custom_worsened]" value="1" <?php checked( $settings['custom_worsened'] ); ?>> <?php esc_html_e( 'Critical or recommended count increased', 'mozcheck' ); ?></label>
							<p class="description"><?php esc_html_e( 'Custom conditions are combined with OR: matching any selected condition sends the email.', 'mozcheck' ); ?></p>
						</fieldset>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Report categories', 'mozcheck' ); ?></th><td><fieldset>
					<?php
					foreach ( Mozcheck_Settings::categories() as $value => $label ) :
						?>
						<label style="display:block"><input type="checkbox" name="mozcheck_settings[categories][]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $settings['categories'], true ) ); ?>> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Report status', 'mozcheck' ); ?></h2>
			<p><?php /* translators: %s: next check date and time. */ echo esc_html( $next ? sprintf( __( 'Next scheduled check: %s', 'mozcheck' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) ) : __( 'No report is scheduled.', 'mozcheck' ) ); ?></p>
			<?php
			if ( ! empty( $last['run_at'] ) ) :
				?>
				<p><?php /* translators: 1: last check date and time, 2: delivery status. */ echo esc_html( sprintf( __( 'Last check: %1$s — delivery status: %2$s', 'mozcheck' ), get_date_from_gmt( $last['run_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ), $last['delivery']['status'] ?? 'unknown' ) ); ?></p><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mozcheck_send_now">
				<?php wp_nonce_field( 'mozcheck_send_now' ); ?>
				<?php submit_button( __( 'Run diagnosis and send email now', 'mozcheck' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
