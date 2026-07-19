<?php
/**
 * Plugin integration smoke tests.
 *
 * @package Mozcheck
 */

/**
 * Verifies that WordPress can load the plugin.
 */
class PluginIntegrationTest extends WP_UnitTestCase {
	/**
	 * Reset plugin state around each test.
	 */
	public function setUp(): void {
		parent::setUp();
		Mozcheck_Scheduler::unschedule();
		delete_option( Mozcheck_Settings::OPTION );
		delete_option( Mozcheck_Settings::SNAPSHOT_OPTION );
		delete_option( Mozcheck_Settings::STATUS_OPTION );
	}

	/**
	 * Remove scheduled state after each test.
	 */
	public function tearDown(): void {
		Mozcheck_Scheduler::unschedule();
		parent::tearDown();
	}

	/**
	 * The plugin bootstrap defines its public version constant.
	 */
	public function test_plugin_is_loaded_by_wordpress(): void {
		$this->assertTrue( defined( 'MOZCHECK_VERSION' ) );
		$this->assertSame( '0.1.0', MOZCHECK_VERSION );
	}

	/**
	 * Normal activation enables a weekly report and schedules one event.
	 */
	public function test_activation_creates_default_schedule(): void {
		Mozcheck_Plugin::activate( false );
		$settings = Mozcheck_Settings::get();

		$this->assertTrue( $settings['enabled'] );
		$this->assertSame( 'weekly', $settings['frequency'] );
		$this->assertSame( 1, $settings['weekday'] );
		$this->assertNotFalse( wp_next_scheduled( Mozcheck_Scheduler::HOOK ) );
	}

	/**
	 * The monthly scheduler chooses the next month after a passed slot.
	 */
	public function test_monthly_next_timestamp_is_in_the_future(): void {
		$settings              = Mozcheck_Settings::defaults( false );
		$settings['frequency'] = 'monthly';
		$settings['monthday']  = 10;
		$settings['time']      = '09:00';
		$now                   = new DateTimeImmutable( '2026-07-19 12:00:00', wp_timezone() );
		$next                  = new DateTimeImmutable( '@' . Mozcheck_Scheduler::next_timestamp( $settings, $now ) );
		$next                  = $next->setTimezone( wp_timezone() );

		$this->assertSame( '2026-08-10 09:00', $next->format( 'Y-m-d H:i' ) );
	}

	/**
	 * Settings validation accepts multiple addresses and limits month days.
	 */
	public function test_settings_are_sanitized(): void {
		$settings = Mozcheck_Settings::sanitize(
			array(
				'enabled'    => '1',
				'recipients' => "first@example.com, invalid\nsecond@example.com",
				'frequency'  => 'monthly',
				'monthday'   => 31,
				'time'       => '10:30',
				'policy'     => 'always',
				'categories' => array( 'updates' ),
			)
		);

		$this->assertSame( array( 'first@example.com', 'second@example.com' ), $settings['recipients'] );
		$this->assertSame( 28, $settings['monthday'] );
	}

	/**
	 * Site Health exposes a MozCheck tab with a settings link.
	 */
	public function test_site_health_tab_links_to_settings(): void {
		$tabs = Mozcheck_Admin::site_health_tab( array() );
		$this->assertArrayHasKey( 'mozcheck', $tabs );

		ob_start();
		Mozcheck_Admin::site_health_tab_content( 'mozcheck' );
		$content = (string) ob_get_clean();
		$this->assertStringContainsString( 'options-general.php?page=mozcheck', $content );
		$this->assertStringContainsString( 'Open MozCheck settings', $content );
	}

	/**
	 * Collector includes tests registered through the public Site Health filter.
	 */
	public function test_collector_runs_plugin_site_health_tests(): void {
		$filter = static function ( $tests ) {
			$tests['direct']['mozcheck_fixture'] = array(
				'label' => 'MozCheck fixture',
				'test'  => static function () {
					return array(
						'test'        => 'mozcheck_fixture',
						'label'       => 'MozCheck fixture passed',
						'status'      => 'good',
						'badge'       => array(
							'label' => 'Test',
							'color' => 'blue',
						),
						'description' => '<p>Fixture result.</p>',
						'actions'     => '',
					);
				},
			);
			return $tests;
		};
		add_filter( 'site_status_tests', $filter );
		$report = ( new Mozcheck_Site_Health_Collector() )->collect();
		remove_filter( 'site_status_tests', $filter );

		$matches = array_values( array_filter( $report['results'], static fn( $result ) => 'mozcheck_fixture' === $result['id'] ) );
		$this->assertCount( 1, $matches );
		$this->assertSame( 'good', $matches[0]['status'] );
		$this->assertSame( 'other', $matches[0]['category'] );
	}

	/**
	 * Mailer sends separate HTML messages without exposing recipients.
	 */
	public function test_mailer_sends_to_each_recipient(): void {
		$calls  = array();
		$filter = static function ( $short_circuit, $atts ) use ( &$calls ) {
			unset( $short_circuit );
			$calls[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );
		$report = array(
			'site_name'    => 'Test site',
			'site_url'     => home_url( '/' ),
			'generated_at' => current_time( 'mysql', true ),
			'counts'       => array(
				'critical'    => 0,
				'recommended' => 0,
				'good'        => 1,
				'updates'     => 0,
			),
			'results'      => array(),
		);
		$result = ( new Mozcheck_Mailer() )->send( $report, array( 'one@example.com', 'two@example.com' ) );
		remove_filter( 'pre_wp_mail', $filter, 10 );

		$this->assertSame( 'success', $result['status'] );
		$this->assertCount( 2, $calls );
		$this->assertSame( 'one@example.com', $calls[0]['to'] );
		$this->assertSame( 'two@example.com', $calls[1]['to'] );
	}
}
