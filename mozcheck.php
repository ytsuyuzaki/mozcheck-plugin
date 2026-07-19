<?php
/**
 * Plugin Name: Mozcheck
 * Plugin URI:  https://github.com/ytsuyuzaki/mozcheck-plugin
 * Description: Mozcheck plugin.
 * Version:     0.1.0
 * Author:      ytsuyuzaki
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mozcheck
 *
 * @package Mozcheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOZCHECK_VERSION', '0.1.0' );
define( 'MOZCHECK_PLUGIN_FILE', __FILE__ );
define( 'MOZCHECK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once MOZCHECK_PLUGIN_DIR . 'includes/class-mozcheck-settings.php';
require_once MOZCHECK_PLUGIN_DIR . 'includes/class-mozcheck-site-health-collector.php';
require_once MOZCHECK_PLUGIN_DIR . 'includes/class-mozcheck-condition-evaluator.php';
require_once MOZCHECK_PLUGIN_DIR . 'includes/class-mozcheck-mailer.php';
require_once MOZCHECK_PLUGIN_DIR . 'includes/class-mozcheck-runner.php';
require_once MOZCHECK_PLUGIN_DIR . 'includes/class-mozcheck-scheduler.php';
require_once MOZCHECK_PLUGIN_DIR . 'includes/class-mozcheck-admin.php';
require_once MOZCHECK_PLUGIN_DIR . 'includes/class-mozcheck-plugin.php';

register_activation_hook( __FILE__, array( 'Mozcheck_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Mozcheck_Plugin', 'deactivate' ) );

Mozcheck_Plugin::init();
