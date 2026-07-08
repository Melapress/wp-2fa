<?php
/**
 * WP 2FA Wizard Template: TOTP Setup
 *
 * Contains two template IDs:
 *   - wp2fa-totp-setup        — QR code and manual key entry.
 *   - wp2fa-totp-setup-footer — Footer buttons for the setup step.
 *
 * tmpl-wp2fa-totp-setup data:
 *   - qrCodeUrl    {string} URL for the QR code image.
 *   - totpKey      {string} Manual entry key.
 *   - step1        {string} Instruction step 1 text.
 *   - step2        {string} Instruction step 2 text (may contain HTML).
 *   - step3        {string} Instruction step 3 text.
 *   - copyKeyLabel {string} Accessible label for the copy button.
 *
 * tmpl-wp2fa-totp-setup-footer data:
 *   - goBackLabel   {string} "Go back" link text.
 *   - continueLabel {string} "Continue" / "I'm Ready" button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-totp-setup">
	<div class="wp2fa-totp-setup-wrapper" id="wp2fa-totp-setup-wrapper">
		<div class="wp2fa-totp-qr-wrapper" id="wp2fa-totp-qr-wrapper">
			<img class="wp2fa-totp-qr-code" id="wp2fa-totp-qr-code" src="{{ data.qrCodeUrl }}" alt="TOTP QR Code" />
		</div>
		<div class="wp2fa-totp-instructions" id="wp2fa-totp-instructions">
			<ol class="wp2fa-totp-steps-list" id="wp2fa-totp-steps-list">
				<li class="wp2fa-totp-step-item" id="wp2fa-totp-step-1">{{ data.step1 }}</li>
				<li class="wp2fa-totp-step-item" id="wp2fa-totp-step-2">
					<div>{{{ data.step2 }}}</div>
					<div class="wp2fa-totp-key-wrapper" id="wp2fa-totp-key-wrapper">
						<input type="text" class="wp2fa-totp-key-input" id="wp2fa-totp-key-input" readonly value="{{ data.totpKey }}" />
						<button type="button" class="wp2fa-totp-key-copy" id="wp2fa-totp-key-copy" aria-label="{{ data.copyKeyLabel }}">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
						</button>
					</div>
				</li>
				<li class="wp2fa-totp-step-item" id="wp2fa-totp-step-3">{{ data.step3 }}</li>
			</ol>
		</div>
	</div>
</script>

<script type="text/html" id="tmpl-wp2fa-totp-setup-footer">
	<a class="wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp-2fa-button-secondary" id="wp2fa-totp-btn-goback" href="#">{{ data.goBackLabel }}</a>
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-totp-btn-ready" type="button">{{ data.continueLabel }}</button>
</script>
