<?php
/**
 * Single-event report scheduling.
 *
 * @package Mozcheck
 */

/**
 * Schedules reports in the site's timezone.
 */
final class Mozcheck_Scheduler {
	public const HOOK = 'mozcheck_scheduled_report';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'handle' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'schedule' ), 10, 0 );
		add_action( 'update_option_' . Mozcheck_Settings::OPTION, array( __CLASS__, 'settings_updated' ), 10, 2 );
		add_action( 'add_option_' . Mozcheck_Settings::OPTION, array( __CLASS__, 'settings_added' ), 10, 2 );
	}

	/**
	 * Run the scheduled report and reserve the next slot.
	 */
	public static function handle(): void {
		try {
			Mozcheck_Runner::run_scheduled();
		} finally {
			self::schedule();
		}
	}

	/**
	 * Reschedule when settings change.
	 *
	 * @param mixed $old Old settings.
	 * @param mixed $new_value New settings.
	 */
	public static function settings_updated( $old, $new_value ): void {
		self::unschedule();
		if ( ! empty( $new_value['enabled'] ) ) {
			self::schedule( is_array( $new_value ) ? $new_value : null );
		}
	}

	/**
	 * Schedule when a site saves settings for the first time.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value New settings.
	 */
	public static function settings_added( string $option_name, $value ): void {
		unset( $option_name );
		if ( ! empty( $value['enabled'] ) ) {
			self::schedule( is_array( $value ) ? $value : null );
		}
	}

	/**
	 * Schedule the next event.
	 *
	 * @param array<string, mixed>|null $settings Settings override.
	 */
	public static function schedule( ?array $settings = null ): void {
		$settings = $settings ?? Mozcheck_Settings::get();
		if ( ! $settings['enabled'] || wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		wp_schedule_single_event( self::next_timestamp( $settings ), self::HOOK );
	}

	/**
	 * Remove all matching events.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Calculate the next future timestamp.
	 *
	 * @param array<string, mixed>   $settings Settings.
	 * @param DateTimeImmutable|null $now Current local time for tests.
	 * @return int
	 */
	public static function next_timestamp( array $settings, ?DateTimeImmutable $now = null ): int {
		$timezone              = wp_timezone();
		$now                   = $now ? $now->setTimezone( $timezone ) : new DateTimeImmutable( 'now', $timezone );
		list( $hour, $minute ) = array_map( 'intval', explode( ':', $settings['time'] ) );

		if ( 'monthly' === $settings['frequency'] ) {
			$candidate = $now->setDate( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), (int) $settings['monthday'] )->setTime( $hour, $minute );
			if ( $candidate <= $now ) {
				$next_month = $candidate->modify( 'first day of next month' );
				$candidate  = $next_month->setDate( (int) $next_month->format( 'Y' ), (int) $next_month->format( 'n' ), (int) $settings['monthday'] )->setTime( $hour, $minute );
			}
		} else {
			$days      = ( (int) $settings['weekday'] - (int) $now->format( 'N' ) + 7 ) % 7;
			$candidate = $now->modify( '+' . $days . ' days' )->setTime( $hour, $minute );
			if ( $candidate <= $now ) {
				$candidate = $candidate->modify( '+7 days' );
			}
		}

		return $candidate->getTimestamp();
	}
}
