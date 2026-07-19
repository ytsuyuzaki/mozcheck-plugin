const { chromium } = require( '@playwright/test' );
const { execFileSync } = require( 'node:child_process' );

module.exports = async ( config ) => {
	execFileSync(
		'npx',
		[
			'wp-env',
			'run',
			'tests-cli',
			'wp',
			'plugin',
			'activate',
			'mozcheck-plugin',
		],
		{ stdio: 'ignore' }
	);

	const { baseURL, storageState } = config.projects[ 0 ].use;
	const browser = await chromium.launch();
	const page = await browser.newPage( { baseURL } );

	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( '**/wp-admin/**' );

	if ( typeof storageState === 'string' ) {
		await page.context().storageState( { path: storageState } );
	}

	await browser.close();
};
