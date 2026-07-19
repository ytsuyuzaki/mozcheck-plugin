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
	 * The plugin bootstrap defines its public version constant.
	 */
	public function test_plugin_is_loaded_by_wordpress(): void {
		$this->assertTrue( defined( 'MOZCHECK_VERSION' ) );
		$this->assertSame( '0.1.0', MOZCHECK_VERSION );
	}
}
