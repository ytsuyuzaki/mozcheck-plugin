const { test, expect } = require( '@playwright/test' );

test( 'Mozcheck is active in WordPress', async ( { page } ) => {
	await page.goto( '/wp-admin/plugins.php' );
	const row = page.locator( 'tr[data-slug="mozcheck"]' );

	await expect( row ).toBeVisible();
	await expect( row ).toHaveClass( /active/ );
} );
