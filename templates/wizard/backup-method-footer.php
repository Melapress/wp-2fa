<?php
/**
 * WP 2FA Wizard Template: Backup Method Footer
 *
 * Template ID: wp2fa-wizard-backup-footer
 *
 * Data expected:
 *   - skipLabel {string} Text for the skip/later button.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<script type="text/html" id="tmpl-wp2fa-wizard-backup-footer">
	<button class="wp2fa-wizard-btn wp2fa-wizard-btn-secondary wp-2fa-button-secondary" id="wp2fa-wizard-backup-btn-skip" type="button">{{ data.skipLabel }}</button>
</script>
