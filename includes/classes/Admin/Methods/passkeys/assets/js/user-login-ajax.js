import { browserSupportsWebAuthn, browserSupportsWebAuthnAutofill, startAuthentication } from "./index.js";

/**
 * Authenticate Passkey.
 */

/**
 * Hand the browser over to the 2FA challenge.
 *
 * There is deliberately no session at this point: the passkey has been verified
 * but the second factor has not been given, so the account is carried across by
 * a one-time login nonce instead — the same way the password flow does it. The
 * challenge handler reads these from POST, so this has to be a form submission
 * rather than a redirect.
 *
 * @param {Object} data Response payload carrying user_id, login_nonce, provider.
 */
function handOverToSecondFactor( data ) {
	if ( ! data || ! data.user_id || ! data.login_nonce || ! data.provider ) {
		throw new Error( 'Could not start the second authentication step.' );
	}

	let loginUrl;
	try {
		loginUrl = new URL( data.login_url || 'wp-login.php', window.location.href );
	} catch ( error ) {
		throw new Error( 'Could not start the second authentication step.' );
	}

	// The login nonce is a temporary credential. Never submit it to another
	// origin, even if a login_url filter supplied that URL on the server.
	if ( loginUrl.origin !== window.location.origin ) {
		throw new Error( 'Could not start the second authentication step.' );
	}

	loginUrl.searchParams.set( 'action', 'validate_2fa' );
	loginUrl.hash = '';

	const form = document.createElement( 'form' );
	form.method = 'POST';
	form.action = loginUrl.href;
	form.style.display = 'none';

	const fields = {
		'wp-auth-id': data.user_id,
		'wp-auth-nonce': data.login_nonce,
		'provider': data.provider || '',
		'redirect_to': data.redirect_to || '',
	};

	Object.keys( fields ).forEach( function ( name ) {
		const input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = name;
		input.value = fields[ name ];
		form.appendChild( input );
	} );

	document.body.appendChild( form );
	form.submit();
}

async function authenticate( username, redirectTo, rememberMe ) {
	let asseResp;
	let requestId;
	try {
		const response = await jQuery.post(
			login.ajaxurl,
			{
				"action": "wp2fa_signin_request",
				"user": username,
			},);

		const { options, request_id } = response.data;

		requestId = request_id;
		asseResp = await startAuthentication(options);
	} catch (error) {
		throw error;
	}

	// POST the response to the endpoint that calls.
	try {
		const response = await jQuery.post(
			login.ajaxurl,
			{
				"action": "wp2fa_signin_response",
				request_id: requestId,
				"data": asseResp,
				'user': username,
				'redirect_to': redirectTo,
				'rememberme': rememberMe ? '1' : '',
			},);

		if (response.success !== true) {
			const refused = response.data && response.data.message
				? response.data.message
				: 'Passkey authentication failed. Method is not set?';
			throw new Error( refused );
		}

		// A second factor is owed. No session was issued, so go and collect it.
		if ( response.data && 'pending_2fa' === response.data.status ) {
			handOverToSecondFactor( response.data );
			return;
		}

		let iframe = !(window === window.parent); // interim login ?

		if (iframe) {
			var someIframe = window.parent.document.getElementById('wp-auth-check-wrap');
			someIframe.parentNode.removeChild(someIframe);
		} else {

			let redirect_to = '';

			if (response.data.redirect_to && '' !== response.data.redirect_to) {
				redirect_to = response.data.redirect_to;
			} else {
				// Get redirect_to from query string.
				const urlParams = new URLSearchParams(window.location.search);
				redirect_to = urlParams.get('redirect_to') || '/wp-admin';
			}

			// Validate redirect is same-origin to prevent open redirect attacks.
			try {
				const parsed = new URL(redirect_to, window.location.origin);
				if (parsed.origin !== window.location.origin) {
					redirect_to = '/wp-admin';
				}
			} catch (e) {
				if (!redirect_to.startsWith('/')) {
					redirect_to = '/wp-admin';
				}
			}

			// Redirect to redirect url or wp-admin as default.
			window.location.href = redirect_to;
		}
	} catch (error) {
		throw error;
	}
}

/**
 * Extract a useful message from either an Error or jQuery's jqXHR rejection.
 *
 * @param {*} error Rejected value.
 * @return {string} Safe text for the login notice.
 */
function getErrorMessage( error ) {
	const responseData = error && error.responseJSON ? error.responseJSON.data : null;

	if ( 'string' === typeof responseData && '' !== responseData.trim() ) {
		return responseData;
	}

	if ( responseData && 'string' === typeof responseData.message && '' !== responseData.message.trim() ) {
		return responseData.message;
	}

	if ( error && 'string' === typeof error.message && '' !== error.message.trim() ) {
		return error.message;
	}

	return 'Passkey authentication failed. Please try again.';
}

/**
 * Show error message.
 *
 * @param {string} message Error message.
 */
function showError(message) {
	const loginForm = document.getElementById('loginform');
	if ( ! loginForm || ! loginForm.parentNode ) {
		return;
	}

	const previousError = document.getElementById('login_error');
	if ( previousError ) {
		previousError.remove();
	}

	// Create Error element if not exists.
	const errorElement = document.createElement('div');
	errorElement.id = 'login_error';
	errorElement.className = 'notice notice-error';
	errorElement.textContent = message;

	// Add error element before login form.
	loginForm.parentNode.insertBefore(errorElement, loginForm);

	loginForm.classList.add('shake');
}

async function delay(time) {
	return new Promise(resolve => setTimeout(resolve, time));
}

function onClick() {

	// create invisible dummy input to receive the focus first
	const fakeInput = document.createElement('input')
	fakeInput.setAttribute('type', 'text')
	fakeInput.style.position = 'absolute'
	fakeInput.style.opacity = 0
	fakeInput.style.height = 0
	fakeInput.style.fontSize = '16px' // disable auto zoom

	// you may need to append to another element depending on the browser's auto 
	// zoom/scroll behavior
	document.body.prepend(fakeInput)

	// focus so that subsequent async focus will work
	fakeInput.focus()

	setTimeout(() => {

		// now we can focus on the target input
		document.getElementById('user_login').focus()

		// cleanup
		fakeInput.remove()

	}, 1000)

}

wp.domReady(async () => {
	// If the browser doesn't support WebAuthn, don't do anything.
	if (!browserSupportsWebAuthn()) {
		return;
	}

	let usernameField = document.getElementById('user_login');

	if ( ! usernameField ) {
		usernameField = document.getElementById('username');
	}

	if ( !usernameField ) {
		return;
	}

	// add autocomplete="webauthn" to the username field.
	if (usernameField) {
		usernameField.setAttribute('autocomplete', 'username webauthn');
	}

	if (browserSupportsWebAuthnAutofill()) {

		const usePasskeysButton = document.querySelector('.wp-2fa-login-via-passkey');
		const useStandardButton = document.querySelector('.wp-2fa-login-standard');

		// Helper to detect if the password field is currently visible
		const isPasswordVisible = () => {
			let $user_password = jQuery('.user-pass-wrap');
			if (!$user_password.length) {
				$user_password = jQuery(jQuery('.woocommerce-form-row.woocommerce-form-row--wide.form-row.form-row-wide')[1]);
			}
			return $user_password.length ? $user_password.is(':visible') : false;
		};

		if ( usePasskeysButton ) {
			usePasskeysButton.addEventListener('click', async () => {

				if ( useStandardButton ) {
					const standardLoginWrap = jQuery( '#wp-2fa-standard-login-wrapper' );
					standardLoginWrap.show();
				}

				let $user_password = jQuery( '.user-pass-wrap' );

				if ( ! $user_password.length ) {
					$user_password = jQuery(jQuery( '.woocommerce-form-row.woocommerce-form-row--wide.form-row.form-row-wide')[1]);
				}
				if ($user_password.is(":visible")) {
					$user_password.hide();

					/*
					 * Hidden along with the password, but its state is still read when the
					 * ceremony runs — so a choice made before switching to a passkey is kept.
					 * It is not left on screen because this form is also rendered into the small
					 * interim-login frame, where the extra row pushes the passkey control out of
					 * reach and the re-authentication cannot be completed at all.
					 */
					jQuery( 'p.forgetmenot' ).hide();
					jQuery( 'p.submit' ).hide();

					jQuery( 'button[name="login"]' ).parent().hide();

					return;
				}

				jQuery( '#user_login' ).prop( 'required', false );
				jQuery( '#user_pass' ).prop( 'required', false );

				if ('' === usernameField.value) {
					showError('Please enter your username or email address to use Passkey login.');

					return;
				}

				// Collect redirect input value with fallbacks: redirect_to -> redirect -> 'wp-admin/'
				let redirectTo = '';
				const redirectInput = document.querySelector('input[name="redirect_to"]') || document.querySelector('input[name="redirect"]');
				if (redirectInput && redirectInput.value && redirectInput.value.trim() !== '') {
					redirectTo = redirectInput.value;
				} else {
					redirectTo = '';
				}


				/*
				 * Core's "Remember Me", as the person left it.
				 *
				 * On wp-login.php this is the real checkbox; on the 2FA challenge form and on
				 * third-party forms it is carried in a hidden field. Both shapes are read here,
				 * because the session is issued by the endpoint below and whatever is not sent
				 * to it is decided without reference to what the user chose.
				 */
				let rememberMe = false;
				const rememberInput = document.querySelector('input[name="rememberme"]');
				if (rememberInput) {
					rememberMe = 'checkbox' === rememberInput.type
						? rememberInput.checked
						: ('' !== rememberInput.value && '0' !== rememberInput.value && 'false' !== rememberInput.value);
				}

				try {
					await authenticate( usernameField.value, redirectTo, rememberMe );
				} catch (error) {
					showError( getErrorMessage( error ) );
				}
			});
		}
		if ( useStandardButton ) {
			useStandardButton.addEventListener('click', async () => {

				var $user_password = jQuery( '.user-pass-wrap' );

				if ( ! $user_password.length ) {
					$user_password = jQuery(jQuery( '.woocommerce-form-row.woocommerce-form-row--wide.form-row.form-row-wide')[1]);
				}

				$user_password.show();

				jQuery( '#user_login' ).prop( 'required', true );
				jQuery( '#user_pass' ).prop( 'required', true );

				jQuery( 'p.forgetmenot' ).show();
				jQuery( 'p.submit' ).show();

				jQuery( 'button[name="login"]' ).parent().show();

				const standardLoginWrap = jQuery( '#wp-2fa-standard-login-wrapper' );
				standardLoginWrap.hide();
			});
		}

		// Trigger Passkey authentication when pressing Enter on the username field
		// if the password field is hidden (i.e., passkey flow is active).
		if ( usePasskeysButton && usernameField ) {
			usernameField.addEventListener('keydown', (e) => {
				const isEnter = (e.key && e.key.toLowerCase() === 'enter') || e.keyCode === 13;
				if (!isEnter) return;

				if (!isPasswordVisible()) {
					e.preventDefault();
					usePasskeysButton.click();
				}
			});
		}

	} else {
		const passkeyUseWrap = document.getElementById('wp-2fa-login-wrapper');
		passkeyUseWrap.style.display = 'none';
	}
});
