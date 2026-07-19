<?php
/**
 * Route local wp-env email to Mailpit.
 *
 * This file is mapped as a development-only mu-plugin by .wp-env.json and is
 * not included in the release ZIP.
 *
 * @package Mozcheck
 */

add_action(
	'phpmailer_init',
	static function ( $phpmailer ) {
		$phpmailer->isSMTP();
		// PHPMailer public properties intentionally use these upstream names.
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Host        = 'mailpit';
		$phpmailer->Port        = 1025;
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPSecure  = '';
		$phpmailer->SMTPAutoTLS = false;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
);

add_filter( 'wp_mail_from', static fn() => 'wordpress@mozcheck.test' );
add_filter( 'wp_mail_from_name', static fn() => 'MozCheck wp-env' );
