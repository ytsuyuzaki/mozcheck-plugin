<?php
/**
 * WordPress integration test bootstrap.
 *
 * @package Mozcheck
 */

use Yoast\WPTestUtils\WPIntegration;

require_once dirname( __DIR__, 2 ) . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

$tests_dir = WPIntegration\get_path_to_wp_test_dir();
if ( false === $tests_dir ) {
	echo 'WordPress test library was not found. Run npm run test:integration.' . PHP_EOL;
	exit( 1 );
}

require_once $tests_dir . 'includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/mozcheck.php';
	}
);

WPIntegration\bootstrap_it();
