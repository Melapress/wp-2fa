<?php
/**
 * Profile section: Backup Codes Generated dialog.
 *
 * Overlay dialog shown after backup codes are generated from the profile page.
 * Displays the codes with Copy, Print, Send via email actions.
 *
 * This template is included via admin_footer so it is available on the page.
 *
 * @var array $data Template data (user_id, nonce).
 *
 * @package wp2fa
 * @since   4.0.0
 */

use WP2FA\WP2FA;

defined( 'ABSPATH' ) || exit;
?>
<div class="wp2fa-profile__confirm-overlay" id="wp2fa-profile-backup-codes-dialog" role="dialog" aria-modal="true" aria-labelledby="wp2fa-profile-backup-codes-title" style="display:none;">
	<div class="wp2fa-profile__confirm-dialog" style="max-width:480px;text-align:center;">
		<div class="wp2fa-profile-backup-codes__icon" id="wp2fa-profile-backup-codes-icon">
			<svg width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<circle cx="28" cy="28" r="28" fill="#e8f5e9"/>
				<path d="M18 28l7 7 13-13" stroke="#4caf50" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
			</svg>
		</div>
		<!-- <h3 id="wp2fa-profile-backup-codes-title"><?php \esc_html_e( 'Backup Codes Generated', 'wp-2fa' ); ?></h3> -->
		<p id="wp2fa-profile-backup-codes-desc"><?php echo \wp_kses_post( WP2FA::get_wp2fa_white_label_setting( 'backup_codes_generated', true ) ) ?: \esc_html__( 'Store these codes somewhere safe. Each code can only be used once.', 'wp-2fa' ); ?></p>
		<textarea class="wp2fa-profile-backup-codes__textarea" id="wp2fa-profile-backup-codes-textarea" readonly rows="6" cols="30"></textarea>
		<div class="wp2fa-profile-backup-codes__actions" id="wp2fa-profile-backup-codes-actions">
			<button type="button" class="wp2fa-profile__btn wp2fa-profile__btn--primary wp2fa-profile-backup-codes__action-btn" id="wp2fa-profile-backup-codes-copy">
				<?php \esc_html_e( 'Copy Codes', 'wp-2fa' ); ?>
			</button>
			<button type="button" class="wp2fa-profile__btn wp2fa-profile__btn--primary wp2fa-profile-backup-codes__action-btn" id="wp2fa-profile-backup-codes-print">
				<?php \esc_html_e( 'Print codes', 'wp-2fa' ); ?>
			</button>
			<button type="button" class="wp2fa-profile__btn wp2fa-profile__btn--primary wp2fa-profile-backup-codes__action-btn" id="wp2fa-profile-backup-codes-email"
				data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'wp-2fa-send-backup-codes-email-nonce' ) ); ?>">
				<?php \esc_html_e( 'Send Codes via email', 'wp-2fa' ); ?>
			</button>
		</div>
		<div class="wp2fa-profile-backup-codes__close-wrap">
			<a href="#" class="wp2fa-profile-backup-codes__close-link" id="wp2fa-profile-backup-codes-close">
				<?php \esc_html_e( "I'm ready, close popup", 'wp-2fa' ); ?>
			</a>
		</div>
		<div id="wp2fa-profile-backup-codes-status" class="wp2fa-profile-backup-codes__status" style="display:none;"></div>
	</div>
</div>
