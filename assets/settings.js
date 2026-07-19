/**
 * Toggle schedule and custom condition controls.
 */
function updateMozcheckSettingsVisibility() {
	const frequency = document.querySelector( '#mozcheck-frequency' );
	const policy = document.querySelector( '#mozcheck-policy' );
	const weekday = document.querySelector( '#mozcheck-weekday-field' );
	const monthday = document.querySelector( '#mozcheck-monthday-field' );
	const custom = document.querySelector( '#mozcheck-custom-conditions' );

	if ( frequency && weekday && monthday ) {
		weekday.hidden = frequency.value !== 'weekly';
		monthday.hidden = frequency.value !== 'monthly';
	}
	if ( policy && custom ) {
		custom.hidden = policy.value !== 'custom';
	}
}

document.addEventListener( 'DOMContentLoaded', () => {
	updateMozcheckSettingsVisibility();
	document
		.querySelector( '#mozcheck-frequency' )
		?.addEventListener( 'change', updateMozcheckSettingsVisibility );
	document
		.querySelector( '#mozcheck-policy' )
		?.addEventListener( 'change', updateMozcheckSettingsVisibility );
} );
