/**
 * Puts the cursor in the 2FA code field when the challenge form opens.
 *
 * Its own script, loaded whether the code is checked over REST or by the native form post:
 * user-login.js only loads with REST.
 */
(function () {
	function focusCode() {
		const codeInput = Array.from(document.querySelectorAll(
			'form[name="validate_2fa_form"] input[name="authcode"], form[name="validate_2fa_form"] input[name="wp-2fa-backup-code"]'
		)).find(input => !input.disabled && !input.readOnly && input.getClientRects().length > 0);
		if (codeInput) {
			codeInput.focus();
		}
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', focusCode);
	} else {
		focusCode();
	}
})();
