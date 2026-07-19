<?php
/**
 * Plugin lifecycle.
 *
 * @package Mozcheck
 */

/**
 * Registers plugin services and lifecycle hooks.
 */
final class Mozcheck_Plugin {
	/**
	 * Register runtime hooks.
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		Mozcheck_Scheduler::init();
		if ( is_admin() ) {
			Mozcheck_Admin::init();
		}
	}

	/**
	 * Load translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'mozcheck', false, dirname( plugin_basename( MOZCHECK_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Activate plugin.
	 *
	 * @param bool $network_wide Network-wide activation.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			return;
		}
		if ( false === get_option( Mozcheck_Settings::OPTION, false ) ) {
			add_option( Mozcheck_Settings::OPTION, Mozcheck_Settings::defaults( false ) );
		}
		Mozcheck_Scheduler::schedule();
	}

	/**
	 * Deactivate plugin.
	 *
	 * @param bool $network_wide Network-wide deactivation.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				Mozcheck_Scheduler::unschedule();
				restore_current_blog();
			}
			return;
		}
		Mozcheck_Scheduler::unschedule();
	}
}
