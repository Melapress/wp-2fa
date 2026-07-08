<?php
/**
 * WP 2FA Wizard Template: Modal Shell
 *
 * Template ID: wp2fa-wizard-modal
 *
 * Data expected:
 *   - additionalClasses {string} Extra CSS classes for the modal.
 *   - title             {string} Modal title text.
 *   - closeLabel        {string} Accessible label for the close button.
 *
 * @package wp2fa
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

$wp2fa_wizard_logo_html = '';
$wp2fa_enable_logo      = \WP2FA\WP2FA::get_wp2fa_white_label_setting( 'enable_wizard_logo', false );
if ( $wp2fa_enable_logo ) {
	$wp2fa_logo_url = \WP2FA\WP2FA::get_wp2fa_white_label_setting( 'logo-code-page', false );
	if ( $wp2fa_logo_url ) {
		$wp2fa_wizard_logo_html = '<img class="wp2fa-setup-wizard-header-logo" src="' . esc_url( $wp2fa_logo_url ) . '" alt="" />';
	}
}
?>
<script type="text/html" id="tmpl-wp2fa-wizard-modal">
	<div class="wp2fa-setup-wizard-modal {{ data.additionalClasses }}" id="wp2fa-setup-wizard-modal" role="dialog" aria-label="{{ data.title }}" tabindex="-1">
		<div class="wp2fa-setup-wizard-header" id="wp2fa-wizard-header">
			<div class="wp2fa-setup-wizard-header-title-row">
				<?php if ( $wp2fa_wizard_logo_html ) : ?>
				<div class="wp2fa-setup-wizard-header-logo-row">
					<?php echo $wp2fa_wizard_logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
				</div>
				<?php endif; ?>
				<div class="wp2fa-setup-wizard-title" id="wp2fa-wizard-title">{{{ data.title }}}</div>
				<button class="wp2fa-setup-wizard-close" id="wp2fa-wizard-close" type="button" aria-label="{{ data.closeLabel }}">&times;</button>
			</div>
		</div>
		<div class="wp2fa-setup-wizard-body" id="wp2fa-wizard-body"></div>
		<div class="wp2fa-setup-wizard-footer" id="wp2fa-wizard-footer"></div>
	</div>
</script>
