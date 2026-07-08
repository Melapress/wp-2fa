import { browserSupportsWebAuthn, startRegistration } from "./index.js";

/**
 * Create Passkey Registration via AJAX (vanilla fetch).
 */
async function createRegistration( nonce, isUsb = false ) {
	let attResp;

	const body = new URLSearchParams();
	body.append( '_wpnonce', nonce );
	body.append( 'action', 'wp2fa_profile_register' );
	body.append( 'is_usb', isUsb ? '1' : '0' );

	try {
		const res = await fetch( wp2faData.ajaxURL, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} );
		const json = await res.json();
		if ( ! json.success ) {
			throw new Error( json.data || 'Registration request failed' );
		}
		attResp = await startRegistration( json.data );
	} catch ( error ) {
		throw error;
	}

	const passkeyName = await window.wp2faOpenPasskeyPrompt();

	const responseBody = new URLSearchParams();
	responseBody.append( '_wpnonce', nonce );
	responseBody.append( 'action', 'wp2fa_profile_response' );
	responseBody.append( 'data', JSON.stringify( attResp ) );
	responseBody.append( 'passkey_name', passkeyName );

	try {
		const res = await fetch( wp2faData.ajaxURL, {
			method: 'POST',
			credentials: 'same-origin',
			body: responseBody,
		} );
		const json = await res.json();
		if ( json.success === true ) {
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
		const { isUsb, nonce } = e.detail;
		try {
			await createRegistration( nonce, isUsb );
		} catch ( error ) {
			if ( error.name === 'InvalidStateError' ) {
				window.wp2faShowPasskeySetupError(
					wp.i18n.__( 'Error: Authenticator was probably already registered by you', 'wp-2fa' )
				);
			} else {
				window.wp2faShowPasskeySetupError( 'Error: ' + error.message );
			}
		}
	} );
} );

/**
 * Revoke Passkey via AJAX (vanilla fetch).
 *
 * @param {Event} event The event.
 */
async function revokePasskey( event ) {
	event.preventDefault();

	if (
		! window.confirm(
			wp.i18n.__( 'Are you sure you want to revoke this passkey? This action cannot be undone.', 'wp-2fa' )
		)
	) {
		return;
	}

	const revokeButton = event.target;
	const fingerprint = revokeButton.dataset.id;
	const nonce = revokeButton.dataset.nonce;
	const user_id = revokeButton.dataset.userid;

	const body = new URLSearchParams();
	body.append( '_wpnonce', nonce );
	body.append( 'user_id', user_id );
	body.append( 'fingerprint', fingerprint );
	body.append( 'action', 'wp2fa_profile_revoke_key' );

	try {
		const res = await fetch( wp2faData.ajaxURL, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} );
		const json = await res.json();
		if ( json.success === true ) {
			window.location.reload();
		}
	} catch ( error ) {
		throw error;
	}
}

/**
 * Enable/Disable Passkey via AJAX (vanilla fetch).
 *
 * @param {Event} event The event.
 */
async function enableDisablePasskey( event ) {
	event.preventDefault();

	const enableButton = event.target;
	const fingerprint = enableButton.dataset.id;
	const nonce = enableButton.dataset.nonce;
	const user_id = enableButton.dataset.userid;

	const body = new URLSearchParams();
	body.append( '_wpnonce', nonce );
	body.append( 'user_id', user_id );
	body.append( 'fingerprint', fingerprint );
	body.append( 'action', 'wp2fa_profile_enable_key' );

	try {
		const res = await fetch( wp2faData.ajaxURL, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} );
		const json = await res.json();
		if ( json.success === true ) {
			window.location.reload();
		}
	} catch ( error ) {
		throw error;
	}
}

/**
 * Passkey Revoke/Enable handler.
 */
wp.domReady( () => {
	const revokeButtons = document.querySelectorAll( '.wp-2fa-passkey-list-table button.delete' );

	if ( revokeButtons ) {
		revokeButtons.forEach( revokeButton => {
			revokeButton.addEventListener( 'click', revokePasskey );
		} );
	}

	const enableButtons = document.querySelectorAll( '.wp-2fa-passkey-list-table button.disable' );

	if ( enableButtons ) {
		enableButtons.forEach( enableButton => {
			enableButton.addEventListener( 'click', enableDisablePasskey );
		} );
	}
} );
