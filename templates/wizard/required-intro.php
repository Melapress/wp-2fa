<?php
/**
 * WP 2FA Wizard Template: Required Intro Screen
 *
 * Template ID: wp2fa-wizard-required-intro
 *
 * Displayed as the very first wizard step (before the welcome step)
 * when the user is enforced to set up 2FA and the required intro
 * content is configured and not empty, and the user has not yet
 * seen/dismissed this intro.
 *
 * Data expected:
 *   - contentHtml   {string} The required intro HTML content.
 *   - continueLabel {string} "Continue" button text.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-wizard-required-intro">
	<div class="wp2fa-wizard-required-intro-content">{{{ data.contentHtml }}}</div>
</script>

<script type="text/html" id="tmpl-wp2fa-wizard-required-intro-footer">
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-primary wp-2fa-button-primary" id="wp2fa-wizard-btn-required-intro-continue" type="button">{{ data.continueLabel }}</button>
</script>
