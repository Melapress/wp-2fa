<?php
/**
 * Passkey Setup Modal Template
 *
 * Template ID: wp2fa-passkey-setup-modal
 *
 * Data expected:
 *   - title       {string} Modal title text.
 *   - description {string} Introductory description.
 *   - step1       {string} Step 1 text.
 *   - step2       {string} Step 2 text.
 *   - step3       {string} Step 3 text.
 *   - cancelLabel {string} Cancel link label.
 *   - usbLabel    {string} USB button label.
 *   - passkeyLabel{string} Passkey button label.
 *   - nonce       {string} Registration nonce.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

use WP2FA\WP2FA;

/*
 * The plugin only recolours these buttons when the administrator has asked for
 * it, via "Enable our CSS within user wizards". Left off, they keep WordPress's
 * own primary-button styling.
 *
 * Without this the two buttons in the footer were the odd ones out: WordPress
 * changed its primary blue in 7.0, and every other passkey button on the page
 * already honours this setting, so the modal sat at core blue while the page
 * behind it sat at the plugin's.
 */
$wp2fa_passkey_styling_class = empty( WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_styling' ) ) ? 'default_styling' : 'enable_styling';
?>
<script type="text/html" id="tmpl-wp2fa-passkey-setup-modal">
	<div class="wp2fa-passkey-modal <?php echo \esc_attr( $wp2fa_passkey_styling_class ); ?>" role="dialog" aria-modal="true" aria-labelledby="wp2fa-passkey-setup-title" tabindex="-1" id="wp2fa-passkey-setup-dialog">
		<div class="wp2fa-passkey-modal__header">
			<h2 class="wp2fa-passkey-modal__title" id="wp2fa-passkey-setup-title">{{ data.title }}</h2>
			<button class="wp2fa-passkey-modal__close" type="button" aria-label="<?php esc_attr_e( 'Close modal', 'wp-2fa' ); ?>" data-wp2fa-passkey-close>&times;</button>
		</div>
		<div class="wp2fa-passkey-modal__body">
			<p>{{{ data.description }}}</p>
			<p class="wp2fa-passkey-steps-heading">{{ data.stepsHeading }}</p>
			<ol class="wp2fa-passkey-steps">
				<li>{{ data.step1 }}</li>
				<li>{{ data.step2 }}</li>
				<li>{{ data.step3 }}</li>
			</ol>
		</div>
		<div class="wp2fa-passkey-modal__error" id="wp2fa-passkey-setup-error" role="alert" aria-live="polite"></div>
		<div class="wp2fa-passkey-modal__footer">
			<button type="button" class="wp2fa-passkey-modal__cancel" data-wp2fa-passkey-close>{{ data.cancelLabel }}</button>
			<div class="wp2fa-passkey-modal__footer-actions">
				<button type="button" class="button button-primary" id="wp2fa-passkey-register-usb" data-nonce="{{ data.nonce }}">{{ data.usbLabel }}</button>
				<button type="button" class="button button-primary" id="wp2fa-passkey-register" data-nonce="{{ data.nonce }}">{{ data.passkeyLabel }}</button>
			</div>
		</div>
	</div>
</script>
