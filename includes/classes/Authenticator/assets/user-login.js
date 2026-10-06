/**
 * Authenticate login.
 */
async function authenticate( nonce) {

	let token, remember_device, provider = '';

	const loginForm = document.getElementById('loginform');
	let wp_2fa_submit = document.getElementById('wp-submit');

	let user_id = document.getElementById('wp-auth-id');
	if (!user_id) {
		showError(wp.i18n.__('User ID not found.', 'wp-2fa'));
	} else {
		user_id = user_id.value;
	}

	if ( document.getElementsByName('authcode') && document.getElementsByName('authcode').length > 0) {
		token = document.getElementsByName('authcode')[0].value;
	} else if (document.getElementById('authcode')) {
		token = document.getElementById('authcode').value;
	} else {
		showError(wp.i18n.__('Authentication code not found.', 'wp-2fa'));
		throw new Error('Authentication code not found.');
	}

	if ( '' === token.trim() ) {
		showError(wp.i18n.__('Authentication code can not be empty.', 'wp-2fa'));
		throw new Error('Authentication code can not be empty.');
	}

	if ( document.getElementsByName('provider') && document.getElementsByName('provider').length > 0) {
		provider = document.getElementsByName('provider')[0].value;
	} else if (document.getElementById('provider')) {
		provider = document.getElementById('provider').value;
	} else {
		showError(wp.i18n.__('Provider is not provided.', 'wp-2fa'));
		throw new Error('Provider is not provided.');
	}

	if (document.getElementById('remember_device') && document.getElementById('remember_device').checked) {
		remember_device = true;
	}

	/*
	 * Carried from the login form, where core's "Remember Me" was already ticked.
	 *
	 * The challenge form keeps the answer in a hidden field because the sign-in finishes
	 * here, not at wp-login.php — so whatever is not passed on from this point is simply
	 * lost, and the session is issued with the short lifetime however the box was left.
	 *
	 * Not to be confused with remember_device above, which decides whether the challenge
	 * is asked for again on this device. This one only decides how long the session runs.
	 */
	const rememberField = document.getElementById('rememberme');
	const rememberme = !!rememberField
		&& '' !== rememberField.value
		&& '0' !== rememberField.value
		&& 'false' !== rememberField.value;

	// POST the 2FA token to the validation endpoint.
	try {

		let login_nonce = document.getElementById('wp-auth-nonce');
		if (!login_nonce) {
			showError(wp.i18n.__('Login nonce not found.', 'wp-2fa'));
			throw new Error('Login nonce not found.');
		}
		login_nonce = login_nonce.value;

		const body = {
			user_id: parseInt(user_id, 10),
			token: token,
			provider: provider,
			login_nonce: login_nonce,
		};

		if (remember_device) {
			body.remember_device = true;
		}

		if (rememberme) {
			body.rememberme = true;
		}

		/*
		 * Where the login was asked to go, and whether it is the session-expired
		 * login inside the editor. The server decides the destination from these,
		 * as the form-based challenge does; without them it could only guess.
		 */
		const redirectField = document.getElementsByName('redirect_to');
		if (redirectField.length && '' !== redirectField[0].value) {
			body.redirect_to = redirectField[0].value;
		}

		const interim = !!document.getElementsByName('interim-login').length || window !== window.parent;
		if (interim) {
			body.interim_login = true;
		}

		const res = await window.fetch(wp2faLogin.restRoot + 'wp-2fa-methods/v1/login/validate', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(body),
		});
		const response = await res.json();

		/*
		 * A failed attempt spends the transaction, so the server hands back its
		 * replacement. Without taking it, the next attempt - the right code
		 * included - goes out with the spent nonce and is refused.
		 */
		const freshNonce = response.login_nonce || ( response.data && response.data.login_nonce );
		const nonceField = document.getElementById('wp-auth-nonce');
		if ( freshNonce && nonceField ) {
			nonceField.value = freshNonce;
		}

		if (!res.ok) {
			showError(response.message || wp.i18n.__('Authentication failed.', 'wp-2fa'));
			loginForm.classList.add('shake');
			wp_2fa_submit.removeAttribute("disabled");
			throw new Error(response.message || 'Authentication failed.');
		}

		if (true !== response.status) {
			showError(response.message);
			if ('' !== response.redirect_to) {
				window.location.href = response.redirect_to;
			} else {
				loginForm.classList.add('shake');
				wp_2fa_submit.removeAttribute("disabled");
				throw new Error('2FA authentication failed.');
			}
		}
 
		if (response.interim_login) {
			// Signed in again inside the editor: close the login, stay where we were.
			var someIframe = window.parent.document.getElementById('wp-auth-check-wrap');
			if (someIframe) {
				someIframe.parentNode.removeChild(someIframe);
			}
		} else if (response.redirect_to && '' !== response.redirect_to) {
			window.location.href = response.redirect_to;
		} else {

			let iframe = !(window === window.parent); // interim login ?

			if (iframe) {
				var someIframe = window.parent.document.getElementById('wp-auth-check-wrap');
				someIframe.parentNode.removeChild(someIframe);
			} else {

				let redirect_to = document.getElementsByName('redirect_to');

				if ( ! redirect_to.length ) {
					wp_2fa_submit.removeAttribute("disabled");
					// throw new Error('Redirect URL not found.');
				} else {
					redirect_to = redirect_to[0].value;
					window.location.href = redirect_to;
				}
			}
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
	loginForm.classList.remove('shake');

	let wp_2fa_submit = document.getElementById('wp-submit');
	wp_2fa_submit.removeAttribute("disabled");


	if ( document.getElementById('login_error') ) {
		document.getElementById('login_error').textContent = message;
	} else {

		// Create Error element if not exists.
		const errorElement = document.createElement('div');
		errorElement.id = 'login_error';
		errorElement.className = 'notice notice-error';
		errorElement.textContent = message;
		errorElement.style.cssText = 'font-weight: bold;';

		// Add error element before login form.
		loginForm.parentNode.insertBefore(errorElement, loginForm);
	}

	loginForm.classList.add('shake');
}

function onClick() {
	let wp_2fa_submit = document.getElementById('wp-submit');
	wp_2fa_submit.addEventListener('click', function (event) {

		nonce = wp_2fa_submit.dataset.nonce;

		// Handle the form data
		event.preventDefault();
		event.target.setAttribute("disabled", true);
		authenticate( nonce );
	});
}

window.wp.domReady(async () => {
	let wp_2fa_submit = document.getElementById('wp-submit');
	if (wp_2fa_submit) {
		onClick();
	}
});
