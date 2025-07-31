/**
 * Authenticate login.
 */
async function authenticate() {

	let token, remember_device, provider = '';

	const loginForm = document.getElementById('loginform');
	let wp_2fa_submit = document.getElementById('submit');

	let user_id = document.getElementById('wp-auth-id');
	if (!user_id) {
		showError(wp.i18n.__('User ID not found.'));
	} else {
		user_id = user_id.value;
	}

	if ( document.getElementsByName('authcode') && document.getElementsByName('authcode').length > 0) {
		token = document.getElementsByName('authcode')[0].value;
	} else if (document.getElementById('authcode')) {
		token = document.getElementById('authcode').value;
	} else {
		showError(wp.i18n.__('Authentication code not found.'));
		throw new Error('Authentication code not found.');
	}

	if ( '' === token.trim() ) {
		showError(wp.i18n.__('Authentication code can not be empty.'));
		throw new Error('Authentication code can not be empty.');
	}

	if ( document.getElementsByName('provider') && document.getElementsByName('provider').length > 0) {
		provider = document.getElementsByName('provider')[0].value;
	} else if (document.getElementById('provider')) {
		provider = document.getElementById('provider').value;
	} else {
		showError(wp.i18n.__('Provider is not provided.'));
		throw new Error('Provider is not provided.');
	}

	if (document.getElementById('remember_device') && document.getElementById('remember_device').checked) {
		remember_device = true;
	}

	// GET the response to the endpoint that calls.
	try {

		let path = '/wp-2fa-methods/v1/login/' + user_id + '/' + token + '/' + provider + ((remember_device) ? '/' + remember_device : '');

		const response = await window.wp.apiFetch({
			path: path,
			method: 'GET',
		});

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

		if ('' !== response.redirect_to) {
			window.location.href = response.redirect_to;
		} else {

			let redirect_to = document.getElementsByName('redirect_to');

			if (!redirect_to.length) {
				wp_2fa_submit.removeAttribute("disabled");
				throw new Error('Redirect URL not found.');
			} else {
				redirect_to = redirect_to[0].value;
				window.location.href = redirect_to;
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

	let wp_2fa_submit = document.getElementById('submit');
	wp_2fa_submit.removeAttribute("disabled");


	if ( document.getElementById('login_error') ) {
		document.getElementById('login_error').innerHTML = message;
	} else {

		// Create Error element if not exists.
		const errorElement = document.createElement('div');
		errorElement.id = 'login_error';
		errorElement.className = 'notice notice-error';
		errorElement.innerHTML = message;
		errorElement.style.cssText = 'font-weight: bold;';

		// Add error element before login form.
		loginForm.parentNode.insertBefore(errorElement, loginForm);
	}

	loginForm.classList.add('shake');
}

function onClick() {
	let wp_2fa_submit = document.getElementById('submit');
	wp_2fa_submit.addEventListener('click', function (event) {

		// Handle the form data
		event.preventDefault();
		event.target.setAttribute("disabled", true);
		authenticate();
	});
}

window.wp.domReady(async () => {
	let wp_2fa_submit = document.getElementById('submit');
	if (wp_2fa_submit) {
		onClick();
	}
});
