<?php
/**
 * Condition evaluator unit tests.
 *
 * @package Mozcheck
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/class-mozcheck-condition-evaluator.php';

/**
 * Tests notification rules without WordPress.
 */
class ConditionEvaluatorTest extends TestCase {
	/**
	 * Build settings used by evaluator tests.
	 *
	 * @return array<string, mixed>
	 */
	private function settings(): array {
		return array(
			'policy'                => 'custom',
			'custom_critical'       => true,
			'critical_threshold'    => 1,
			'custom_recommended'    => false,
			'recommended_threshold' => 3,
			'custom_updates'        => true,
			'custom_worsened'       => false,
		);
	}

	/**
	 * Custom conditions use OR semantics.
	 */
	public function test_custom_conditions_use_or_semantics(): void {
		$report = array(
			'counts' => array(
				'good'        => 2,
				'recommended' => 0,
				'critical'    => 0,
				'updates'     => 1,
			),
		);
		$this->assertTrue( Mozcheck_Condition_Evaluator::should_send( $report, null, $this->settings() ) );
	}

	/**
	 * Worsening requires a prior snapshot and an increased problem count.
	 */
	public function test_worsening_requires_an_increase(): void {
		$current  = array(
			'good'        => 0,
			'recommended' => 2,
			'critical'    => 0,
			'updates'     => 0,
		);
		$previous = array(
			'good'        => 0,
			'recommended' => 1,
			'critical'    => 0,
			'updates'     => 0,
		);
		$this->assertFalse( Mozcheck_Condition_Evaluator::worsened( $current, null ) );
		$this->assertTrue( Mozcheck_Condition_Evaluator::worsened( $current, $previous ) );
	}

	/**
	 * Category filtering recalculates all counts.
	 */
	public function test_filter_report_recalculates_counts(): void {
		$report   = array(
			'results' => array(
				array(
					'category'  => 'updates',
					'status'    => 'recommended',
					'is_update' => true,
				),
				array(
					'category'  => 'https',
					'status'    => 'critical',
					'is_update' => false,
				),
			),
		);
		$filtered = Mozcheck_Condition_Evaluator::filter_report( $report, array( 'https' ) );
		$this->assertCount( 1, $filtered['results'] );
		$this->assertSame( 1, $filtered['counts']['critical'] );
		$this->assertSame( 0, $filtered['counts']['updates'] );
	}

	/**
	 * Supplemental inventory is shown but does not inflate Site Health counts.
	 */
	public function test_supplemental_results_do_not_increment_status_counts(): void {
		$counts = Mozcheck_Condition_Evaluator::counts(
			array(
				array(
					'status'       => 'recommended',
					'is_update'    => true,
					'count_status' => false,
				),
			)
		);
		$this->assertSame( 0, $counts['recommended'] );
		$this->assertSame( 1, $counts['updates'] );
	}
}
