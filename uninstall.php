<?php
/**
 * Remove MozCheck data.
 *
 * @package Mozcheck
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete site-local MozCheck data.
 */
function mozcheck_uninstall_site(): void {
	wp_clear_scheduled_hook( 'mozcheck_scheduled_report' );
	delete_option( 'mozcheck_settings' );
	delete_option( 'mozcheck_last_snapshot' );
	delete_option( 'mozcheck_last_status' );
	delete_transient( 'mozcheck_report_lock' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $site_id ) {
		switch_to_blog( $site_id );
		mozcheck_uninstall_site();
		restore_current_blog();
	}
} else {
	mozcheck_uninstall_site();
}
