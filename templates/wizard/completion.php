<?php
/**
 * WP 2FA Wizard Template: Completion Step
 *
 * Shown at the end of the wizard when no backup methods are available.
 * Displays a congratulatory message from the white-label settings and
 * a close button.
 *
 * Template IDs:
 *   - wp2fa-wizard-completion       — Body content.
 *   - wp2fa-wizard-completion-footer — Footer with close button.
 *
 * tmpl-wp2fa-wizard-completion data:
 *   - contentHtml {string} The "no further action" HTML content.
 *
 * tmpl-wp2fa-wizard-completion-footer data:
 *   - closeLabel {string} "Close wizard" button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-wizard-completion">
	<div class="wp2fa-wizard-success-icon" aria-hidden="true">
		<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
			<circle cx="24" cy="24" r="24" fill="#edfaef"/>
			<circle cx="24" cy="24" r="18" fill="#d4edda"/>
			<path d="M16 24l6 6 10-12" stroke="#2e7d32" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
		</svg>
	</div>
	<div class="wp2fa-wizard-completion-content">{{{ data.contentHtml }}}</div>
</script>

<script type="text/html" id="tmpl-wp2fa-wizard-completion-footer">
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-wizard-btn-completion-close" type="button">{{ data.closeLabel }}</button>
</script>
