<?php
/**
 * Settings storage and validation.
 *
 * @package Mozcheck
 */

/**
 * Manages plugin settings.
 */
final class Mozcheck_Settings {
	public const OPTION          = 'mozcheck_settings';
	public const SNAPSHOT_OPTION = 'mozcheck_last_snapshot';
	public const STATUS_OPTION   = 'mozcheck_last_status';

	/**
	 * Return defaults for a site.
	 *
	 * @param bool|null $network_activation Whether defaults are for a network activation.
	 * @return array<string, mixed>
	 */
	public static function defaults( $network_activation = null ): array {
		if ( null === $network_activation ) {
			$network_plugins    = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
			$network_activation = isset( $network_plugins[ plugin_basename( MOZCHECK_PLUGIN_FILE ) ] );
		}

		return array(
			'enabled'               => ! $network_activation,
			'recipients'            => array( get_option( 'admin_email' ) ),
			'frequency'             => 'weekly',
			'weekday'               => 1,
			'monthday'              => 1,
			'time'                  => '09:00',
			'policy'                => 'always',
			'critical_threshold'    => 1,
			'recommended_threshold' => 3,
			'custom_critical'       => true,
			'custom_recommended'    => false,
			'custom_updates'        => false,
			'custom_worsened'       => false,
			'categories'            => array_keys( self::categories() ),
		);
	}

	/**
	 * Return merged settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$value = get_option( self::OPTION, array() );
		return array_replace( self::defaults(), is_array( $value ) ? $value : array() );
	}

	/**
	 * Available report categories.
	 *
	 * @return array<string, string>
	 */
	public static function categories(): array {
		return array(
			'updates'  => __( 'Updates', 'mozcheck' ),
			'php_db'   => __( 'PHP and database', 'mozcheck' ),
			'rest'     => __( 'REST API', 'mozcheck' ),
			'loopback' => __( 'Loopback requests', 'mozcheck' ),
			'https'    => __( 'HTTPS and SSL', 'mozcheck' ),
			'cron'     => __( 'Scheduled events', 'mozcheck' ),
			'unused'   => __( 'Unused plugins and themes', 'mozcheck' ),
			'other'    => __( 'Other Site Health tests', 'mozcheck' ),
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$old   = self::get();
		$out   = self::defaults( false );

		$out['enabled']   = ! empty( $input['enabled'] );
		$out['frequency'] = in_array( $input['frequency'] ?? '', array( 'weekly', 'monthly' ), true ) ? $input['frequency'] : 'weekly';
		$out['weekday']   = min( 7, max( 1, absint( $input['weekday'] ?? 1 ) ) );
		$out['monthday']  = min( 28, max( 1, absint( $input['monthday'] ?? 1 ) ) );
		$out['time']      = preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $input['time'] ?? '' ) ? $input['time'] : '09:00';

		$policies                     = array( 'always', 'issues', 'critical', 'updates', 'worsened', 'custom' );
		$out['policy']                = in_array( $input['policy'] ?? '', $policies, true ) ? $input['policy'] : 'always';
		$out['critical_threshold']    = max( 1, absint( $input['critical_threshold'] ?? 1 ) );
		$out['recommended_threshold'] = max( 1, absint( $input['recommended_threshold'] ?? 3 ) );
		$out['custom_critical']       = ! empty( $input['custom_critical'] );
		$out['custom_recommended']    = ! empty( $input['custom_recommended'] );
		$out['custom_updates']        = ! empty( $input['custom_updates'] );
		$out['custom_worsened']       = ! empty( $input['custom_worsened'] );

		$recipient_source  = is_array( $input['recipients'] ?? null ) ? implode( ',', $input['recipients'] ) : (string) ( $input['recipients'] ?? '' );
		$recipients        = preg_split( '/[\s,;]+/', $recipient_source, -1, PREG_SPLIT_NO_EMPTY );
		$out['recipients'] = array_values( array_unique( array_filter( array_map( 'sanitize_email', false !== $recipients ? $recipients : array() ), 'is_email' ) ) );
		if ( empty( $out['recipients'] ) ) {
			add_settings_error( self::OPTION, 'mozcheck_recipients', __( 'Enter at least one valid email address.', 'mozcheck' ) );
			$out['recipients'] = $old['recipients'];
		}

		$valid_categories  = array_keys( self::categories() );
		$out['categories'] = array_values( array_intersect( $valid_categories, array_map( 'sanitize_key', (array) ( $input['categories'] ?? array() ) ) ) );
		if ( empty( $out['categories'] ) ) {
			add_settings_error( self::OPTION, 'mozcheck_categories', __( 'Select at least one report category.', 'mozcheck' ) );
			$out['categories'] = $old['categories'];
		}

		if ( 'custom' === $out['policy'] && ! $out['custom_critical'] && ! $out['custom_recommended'] && ! $out['custom_updates'] && ! $out['custom_worsened'] ) {
			add_settings_error( self::OPTION, 'mozcheck_conditions', __( 'Select at least one custom notification condition.', 'mozcheck' ) );
			$out['custom_critical'] = true;
		}

		return $out;
	}
}
