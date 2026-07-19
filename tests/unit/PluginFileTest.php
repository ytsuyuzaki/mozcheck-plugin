<?php
/**
 * Unit tests for plugin metadata.
 *
 * @package Mozcheck
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests that do not require WordPress.
 */
class PluginFileTest extends TestCase {
	/**
	 * The main plugin file exposes the minimum required metadata.
	 */
	public function test_plugin_file_contains_required_metadata(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/mozcheck.php' );

		$this->assertStringContainsString( 'Plugin Name: Mozcheck', $source );
		$this->assertStringContainsString( "define( 'MOZCHECK_VERSION', '0.1.0' );", $source );
	}
}
