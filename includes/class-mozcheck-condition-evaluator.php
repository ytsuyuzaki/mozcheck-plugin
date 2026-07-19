<?php
/**
 * Notification condition evaluation.
 *
 * @package Mozcheck
 */

/**
 * Evaluates report conditions without side effects.
 */
final class Mozcheck_Condition_Evaluator {
	/**
	 * Filter a report to configured categories and calculate counts.
	 *
	 * @param array<string, mixed> $report Report data.
	 * @param string[]             $categories Selected categories.
	 * @return array<string, mixed>
	 */
	public static function filter_report( array $report, array $categories ): array {
		$report['results'] = array_values(
			array_filter(
				$report['results'] ?? array(),
				static fn( $result ) => in_array( $result['category'] ?? 'other', $categories, true )
			)
		);
		$report['counts']  = self::counts( $report['results'] );
		return $report;
	}

	/**
	 * Count statuses and supplemental flags.
	 *
	 * @param array<int, array<string, mixed>> $results Results.
	 * @return array<string, int>
	 */
	public static function counts( array $results ): array {
		$counts = array(
			'good'        => 0,
			'recommended' => 0,
			'critical'    => 0,
			'updates'     => 0,
		);
		foreach ( $results as $result ) {
			$status = $result['status'] ?? 'recommended';
			if ( false !== ( $result['count_status'] ?? true ) && isset( $counts[ $status ] ) ) {
				++$counts[ $status ];
			}
			if ( ! empty( $result['is_update'] ) ) {
				++$counts['updates'];
			}
		}
		return $counts;
	}

	/**
	 * Determine whether a report should be sent.
	 *
	 * @param array<string, mixed>      $report Current filtered report.
	 * @param array<string, mixed>|null $previous Previous filtered snapshot.
	 * @param array<string, mixed>      $settings Settings.
	 * @return bool
	 */
	public static function should_send( array $report, ?array $previous, array $settings ): bool {
		$counts   = $report['counts'];
		$worsened = self::worsened( $counts, $previous['counts'] ?? null );

		switch ( $settings['policy'] ) {
			case 'always':
				return true;
			case 'issues':
				return $counts['critical'] > 0 || $counts['recommended'] > 0;
			case 'critical':
				return $counts['critical'] > 0;
			case 'updates':
				return $counts['updates'] > 0;
			case 'worsened':
				return $worsened;
			case 'custom':
				return ( $settings['custom_critical'] && $counts['critical'] >= $settings['critical_threshold'] )
					|| ( $settings['custom_recommended'] && $counts['recommended'] >= $settings['recommended_threshold'] )
					|| ( $settings['custom_updates'] && $counts['updates'] > 0 )
					|| ( $settings['custom_worsened'] && $worsened );
			default:
				return false;
		}
	}

	/**
	 * Has either problem count increased?
	 *
	 * @param array<string, int>      $current Current counts.
	 * @param array<string, int>|null $previous Previous counts.
	 * @return bool
	 */
	public static function worsened( array $current, ?array $previous ): bool {
		return null !== $previous && ( $current['critical'] > $previous['critical'] || $current['recommended'] > $previous['recommended'] );
	}
}
