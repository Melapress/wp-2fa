<?php
/**
 * WP 2FA Wizard Template: Email Setup
 *
 * Contains two template IDs:
 *   - wp2fa-email-setup        — Email address selection form.
 *   - wp2fa-email-setup-footer — Footer buttons for the setup step.
 *
 * tmpl-wp2fa-email-setup data:
 *   - description      {string} Introductory text.
 *   - userEmail        {string} The user's WP email address.
 *   - useMyEmailLabel  {string} "Use my user email" label text.
 *   - allowCustomEmail {bool}   Whether to show the custom email option.
 *   - useAnotherLabel  {string} "Use another email address" label text.
 *   - emailPlaceholder {string} Placeholder for the custom email input.
 *   - showNotice       {bool}   Whether to show the whitelist notice.
 *   - noticeHtml       {string} Full HTML for the whitelist notice.
 *
 * tmpl-wp2fa-email-setup-footer data:
 *   - goBackLabel {string} "Go back" link text.
 *   - readyLabel  {string} "I'm Ready" button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-email-setup">
	<div class="wp2fa-email-setup-description" id="wp2fa-email-setup-description">{{{ data.description }}}</div>

	<ul class="wp2fa-email-options-list" id="wp2fa-email-options-list">
		<li class="wp2fa-email-option-item" id="wp2fa-email-option-item-wp">
			<label class="wp2fa-email-option-label" id="wp2fa-email-option-label-wp">
				<input type="radio" name="wp2fa_wizard_email_address" class="wp2fa-email-option-radio" id="wp2fa-email-use-wp" value="{{ data.userEmail }}" checked />
				<span class="wp2fa-email-option-text" id="wp2fa-email-option-text-wp">{{ data.useMyEmailLabel }} ({{ data.userEmail }})</span>
			</label>
		</li>
		<# if ( data.allowCustomEmail ) { #>
			<li class="wp2fa-email-option-item" id="wp2fa-email-option-item-custom">
				<label class="wp2fa-email-option-label" id="wp2fa-email-option-label-custom">
					<input type="radio" name="wp2fa_wizard_email_address" class="wp2fa-email-option-radio" id="wp2fa-email-use-custom" value="use_custom_email" />
					<span class="wp2fa-email-option-text" id="wp2fa-email-option-text-custom">
						{{ data.useAnotherLabel }}
						<input type="email" class="wp2fa-email-custom-input" id="wp2fa-email-custom-address" name="custom-email-address" placeholder="{{ data.emailPlaceholder }}" />
					</span>
				</label>
			</li>
		<# } #>
	</ul>

	<# if ( data.emailHelpText ) { #>
		<div class="wp2fa-email-help-text" id="wp2fa-email-help-text">{{{ data.emailHelpText }}}</div>
	<# } #>

	<# if ( data.showNotice ) { #>
		<div class="wp2fa-email-notice" id="wp2fa-email-notice">
			<span class="wp2fa-email-notice-icon">&#9888;</span>
			<span>{{{ data.noticeHtml }}}</span>
		</div>
	<# } #>

	<style>
		#wp2fa-wizard-title {
			display: none !important;
		}
		.wp2fa-setup-wizard-body {
			margin-top: -44px !important;
		}
	</style>
</script>

<script type="text/html" id="tmpl-wp2fa-email-setup-footer">
	<a class="wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp-2fa-button-secondary" id="wp2fa-email-btn-goback" href="#">{{ data.goBackLabel }}</a>
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-email-btn-ready" type="button">{{ data.readyLabel }}</button>
</script>
