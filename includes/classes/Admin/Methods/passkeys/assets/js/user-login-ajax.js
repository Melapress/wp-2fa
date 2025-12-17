import { browserSupportsWebAuthn, browserSupportsWebAuthnAutofill, startAuthentication } from "./index.js";

/**
 * Authenticate Passkey.
 */
async function authenticate( username, redirectTo ) {
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
			},);

		if (response.success !== true) {
			throw new Error('Passkey authentication failed. Method is not set?');
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

			// Redirect to redirect url or wp-admin as default.
			window.location.href = redirect_to;
		}
	} catch (error) {
		throw error;
	}
}

/**
 * Show error message.
 *
 * @param {string} message Error message.
 */
function showError(message) {
	const loginForm = document.getElementById('loginform');

	// Create Error element if not exists.
	const errorElement = document.createElement('div');
	errorElement.id = 'login_error';
	errorElement.className = 'notice notice-error';
	errorElement.innerHTML = message;

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

				try {
					await authenticate( usernameField.value, redirectTo );
				} catch (error) {
					showError(error.message);
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
