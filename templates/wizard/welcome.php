<?php
/**
 * WP 2FA Wizard Template: Welcome Screen
 *
 * Template ID: wp2fa-wizard-welcome
 *
 * Displayed as the first wizard step when the white-label welcome
 * message is enabled and not empty.
 *
 * Data expected:
 *   - contentHtml   {string} The welcome HTML content.
 *   - goBackLabel   {string} "Go back" button text.
 *   - continueLabel {string} "Continue" button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-wizard-welcome">
	<div class="wp2fa-wizard-welcome-content">{{{ data.contentHtml }}}</div>
</script>

<script type="text/html" id="tmpl-wp2fa-wizard-welcome-footer">
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp-2fa-button-secondary" id="wp2fa-wizard-btn-goback" type="button">{{ data.goBackLabel }}</button>
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-wizard-btn-continue" type="button">{{ data.continueLabel }}</button>
</script>
