<?php
/**
 * Passkey Success / Name Input Template
 *
 * Template ID: wp2fa-passkey-success
 *
 * Data expected:
 *   - title           {string} Success title.
 *   - description     {string} Success description.
 *   - placeholder     {string} Input placeholder.
 *   - submitLabel     {string} Submit button label.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-passkey-success">
	<div class="wp2fa-passkey-modal" role="dialog" aria-modal="true" aria-labelledby="wp2fa-passkey-success-title" tabindex="-1" id="wp2fa-passkey-success-dialog">
		<div class="wp2fa-passkey-modal__header">
			<h2 class="wp2fa-passkey-modal__title" id="wp2fa-passkey-success-title">&nbsp;</h2>
			<button class="wp2fa-passkey-modal__close" type="button" aria-label="<?php esc_attr_e( 'Close modal', 'wp-2fa' ); ?>" data-wp2fa-passkey-close>&times;</button>
		</div>
		<div class="wp2fa-passkey-modal__body">
			<div class="wp2fa-passkey-success">
				<div class="wp2fa-passkey-success__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
				</div>
				<h3 class="wp2fa-passkey-success__title">{{ data.title }}</h3>
				<p class="wp2fa-passkey-success__description">{{ data.description }}</p>
				<div class="wp2fa-passkey-success__input-wrap">
					<input
						type="text"
						class="wp2fa-passkey-success__input"
						id="wp2fa-passkey-name-input"
						placeholder="{{ data.placeholder }}"
						autocomplete="off"
						aria-label="{{ data.placeholder }}"
					/>
					<div class="wp2fa-passkey-success__error" id="wp2fa-passkey-name-error" role="alert" aria-live="polite"></div>
				</div>
				<button type="button" class="wp2fa-passkey-success__submit" id="wp2fa-passkey-name-submit">{{ data.submitLabel }}</button>
			</div>
		</div>
	</div>
</script>
