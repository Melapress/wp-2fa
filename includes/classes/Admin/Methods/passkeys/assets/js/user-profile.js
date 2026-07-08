import { browserSupportsWebAuthn, startRegistration } from "./index.js";

/**
 * Create Passkey Registration.
 */
async function createRegistration( isUsb = false ) {
	let attResp;

	try {
		const response = await wp.apiFetch( {
			path: '/wp-2fa-passkeys/v1/register/request',
			method: 'POST',
			data: {
				'is_usb': isUsb,
			},
		} );

		attResp = await startRegistration(response);
	} catch (error) {
		throw error;
	}

	const passkeyName = await window.wp2faOpenPasskeyPrompt();

	// POST the response to the endpoint that calls.
	try {
		const response = await wp.apiFetch( {
			path: '/wp-2fa-passkeys/v1/register/response',
			method: 'POST',
			data: {
				attResp,
				'passkey_name': passkeyName,
			},
		} );

		if ( response.status === 'verified' ) {
			window.location.reload();
		}
	} catch ( error ) {
		throw error;
	}
}

/**
 * Passkey Registration Handler via custom event from modal.
 */
wp.domReady( () => {
	// Hide register button if browser doesn't support WebAuthn.
	if ( ! browserSupportsWebAuthn() ) {
		const addBtn = document.querySelector( '[data-open-configure-2fa-wizard-passkey]' );
		if ( addBtn ) {
			addBtn.style.display = 'none';
		}
		return;
	}

	// Listen for registration events from the new modal.
	document.addEventListener( 'wp2fa-passkey-register', async ( e ) => {
		const { isUsb } = e.detail;
		try {
			await createRegistration( isUsb );
		} catch ( error ) {
			if ( error.name === 'InvalidStateError' ) {
				window.wp2faShowPasskeySetupError(
					wp.i18n.__( 'Error: Authenticator was probably already registered by you', 'wp-2fa' )
				);
			} else {
				window.wp2faShowPasskeySetupError( `Error: ${ error.message }` );
			}
		}
	} );
} );

/**
 * Revoke Passkey.
 *
 * @param {Event} event The event.
 */
async function revokePasskey( event ) {
	event.preventDefault();

	if (
		// eslint-disable-next-line no-alert
		! window.confirm(
			wp.i18n.__( 'Are you sure you want to revoke this passkey? This action cannot be undone.', 'wp-2fa' ),
		)
	) {
		return;
	}

	const revokeButton = event.target;
	const fingerprint = revokeButton.dataset.id;

	try {
		const response = await wp.apiFetch( {
			path: '/wp-2fa-passkeys/v1/register/revoke',
			method: 'POST',
			data: {
				fingerprint,
			},
		} );

		if ( response.status === 'success' ) {
			window.location.reload();
		}
	} catch ( error ) {
		throw error;
	}
}

/**
 * Enable/Disable Passkey.
 *
 * @param {Event} event The event.
 */
async function enableDisablePasskey(event) {
	event.preventDefault();

	const enableButton = event.target;
	const fingerprint = enableButton.dataset.id;
	const user_id = enableButton.dataset.userid;

	try {
		const response = await wp.apiFetch( {
			path: '/wp-2fa-passkeys/v1/register/enable',
			method: 'POST',
			data: {
				'info': { 'fingerprint': fingerprint, 'user_id': user_id }
			},
		} );

		if (response.status === 'success') {
			window.location.reload();
		}
	} catch (error) {
		throw error;
	}
}

/**
 * Passkey Revoke handler.
 */
wp.domReady( () => {
	const revokeButtons = document.querySelectorAll( '.wp-2fa-passkey-list-table button.delete' );

	if (revokeButtons) {
		revokeButtons.forEach(revokeButton => {
			revokeButton.addEventListener('click', revokePasskey);
		});
	}

	const enableButtons = document.querySelectorAll('.wp-2fa-passkey-list-table button.disable');

	if (enableButtons) {
		enableButtons.forEach(enableButton => {
			enableButton.addEventListener('click', enableDisablePasskey);
		});
	}
});
