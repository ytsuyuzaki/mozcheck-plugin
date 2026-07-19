<?php
/**
 * Report orchestration.
 *
 * @package Mozcheck
 */

/**
 * Runs collection, condition evaluation and delivery.
 */
final class Mozcheck_Runner {
	private const LOCK = 'mozcheck_report_lock';

	/**
	 * Run a scheduled report.
	 *
	 * @return array<string, mixed>
	 */
	public static function run_scheduled(): array {
		$settings = Mozcheck_Settings::get();
		if ( ! $settings['enabled'] ) {
			return array( 'status' => 'disabled' );
		}
		return self::run( false, $settings );
	}

	/**
	 * Run and always send a manual report.
	 *
	 * @return array<string, mixed>
	 */
	public static function run_manual(): array {
		return self::run( true, Mozcheck_Settings::get() );
	}

	/**
	 * Execute a report.
	 *
	 * @param bool                 $manual Manual execution.
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	private static function run( bool $manual, array $settings ): array {
		if ( get_transient( self::LOCK ) ) {
			return array( 'status' => 'locked' );
		}
		set_transient( self::LOCK, 1, 15 * MINUTE_IN_SECONDS );

		try {
			$report   = ( new Mozcheck_Site_Health_Collector() )->collect();
			$report   = Mozcheck_Condition_Evaluator::filter_report( $report, $settings['categories'] );
			$previous = get_option( Mozcheck_Settings::SNAPSHOT_OPTION, null );
			$send     = $manual || Mozcheck_Condition_Evaluator::should_send( $report, is_array( $previous ) ? $previous : null, $settings );
			$delivery = $send ? ( new Mozcheck_Mailer() )->send( $report, $settings['recipients'] ) : array(
				'status' => 'skipped',
				'sent'   => array(),
				'failed' => array(),
			);

			if ( ! $manual ) {
				update_option( Mozcheck_Settings::SNAPSHOT_OPTION, $report, false );
			}
			$status = array(
				'run_at'   => current_time( 'mysql', true ),
				'manual'   => $manual,
				'delivery' => $delivery,
				'counts'   => $report['counts'],
			);
			update_option( Mozcheck_Settings::STATUS_OPTION, $status, false );
			return $status;
		} catch ( Throwable $error ) {
			$status = array(
				'run_at'   => current_time( 'mysql', true ),
				'manual'   => $manual,
				'delivery' => array( 'status' => 'error' ),
			);
			update_option( Mozcheck_Settings::STATUS_OPTION, $status, false );
			error_log( 'MozCheck: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return $status;
		} finally {
			delete_transient( self::LOCK );
		}
	}
}
