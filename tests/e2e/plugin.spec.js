const { test, expect } = require( '@playwright/test' );

test( 'Mozcheck is active in WordPress', async ( { page } ) => {
	await page.goto( '/wp-admin/plugins.php' );
	const row = page.locator( 'tr[data-slug="mozcheck"]' );

	await expect( row ).toBeVisible();
	await expect( row ).toHaveClass( /active/ );
} );

test( 'Mozcheck settings expose the report controls', async ( { page } ) => {
	await page.goto( '/wp-admin/options-general.php?page=mozcheck' );

	await expect(
		page.getByRole( 'heading', { name: 'MozCheck Site Health email' } )
	).toBeVisible();
	await expect(
		page.getByLabel( 'Enable scheduled email notifications' )
	).toBeChecked();
	await expect( page.getByLabel( 'Recipients' ) ).toHaveValue(
		'admin@example.org'
	);
	await expect( page.locator( '#mozcheck-frequency' ) ).toHaveValue(
		'weekly'
	);
	await expect(
		page.getByRole( 'button', {
			name: 'Run diagnosis and send email now',
		} )
	).toBeVisible();
} );
