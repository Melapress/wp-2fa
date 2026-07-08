<?php
/**
 * WP 2FA Wizard Template: TOTP Verify
 *
 * Contains two template IDs:
 *   - wp2fa-totp-verify        — Verification code input form.
 *   - wp2fa-totp-verify-footer — Footer buttons for the verify step.
 *
 * tmpl-wp2fa-totp-verify data:
 *   - description {string} Introductory text for the verify step.
 *   - codeLabel   {string} Label for the code input.
 *   - placeholder {string} Placeholder text (e.g. '000000').
 *
 * tmpl-wp2fa-totp-verify-footer data:
 *   - goBackLabel   {string} "Go back" button text.
 *   - validateLabel {string} "Validate & Save" button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-totp-verify">
	<div class="wp2fa-totp-verify-wrapper" id="wp2fa-totp-verify-wrapper">
		<div class="wp2fa-totp-verify-description" id="wp2fa-totp-verify-description">{{{ data.description }}}</div>
		<label for="wp2fa-totp-authcode">{{ data.codeLabel }}</label>
		<input type="tel" class="wp2fa-totp-verify-input" id="wp2fa-totp-authcode" name="wp-2fa-totp-authcode" pattern="[0-9]*" autocomplete="off" placeholder="{{ data.placeholder }}" />
		<div class="wp2fa-wizard-verification-response" id="wp2fa-totp-verification-response"></div>
	</div>

	<style>
		#wp2fa-wizard-title {
			display: none !important;
		}
		.wp2fa-setup-wizard-body {
			margin-top: -44px !important;
		}
	</style>
</script>

<script type="text/html" id="tmpl-wp2fa-totp-verify-footer">
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp-2fa-button-secondary" id="wp2fa-totp-verify-btn-back" type="button">{{ data.goBackLabel }}</button>
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-totp-btn-validate" type="button">{{ data.validateLabel }}</button>
</script>
